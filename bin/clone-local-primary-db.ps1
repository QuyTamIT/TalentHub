<#
.SYNOPSIS
    Clone the protected local TalentHub backup database into the canonical
    local primary database without overwriting either schema.

.DESCRIPTION
    This command is intentionally pinned to 127.0.0.1:3306. It reads only
    talenthub_local, refuses to run when talenthub already exists, restores a
    full logical dump into talenthub, verifies exact table/row/migration/data
    equality, and leaves all backup evidence under the Git-ignored .tmp tree.

    It never edits .env and it never drops a database.
#>

[CmdletBinding()]
param(
    [string]$MysqlBin = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe',
    [string]$MysqldumpBin = 'D:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe'
)

$ErrorActionPreference = 'Stop'
$DbHost = '127.0.0.1'
$DbPort = '3306'
$SourceDatabase = 'talenthub_local'
$TargetDatabase = 'talenthub'
$ApplicationUser = 'talenthub_app'
$AdminUser = if ($env:TALENTHUB_LOCAL_ADMIN_USER) { $env:TALENTHUB_LOCAL_ADMIN_USER } else { 'root' }
$AdminPassword = if ($env:TALENTHUB_LOCAL_ADMIN_PASSWORD) { $env:TALENTHUB_LOCAL_ADMIN_PASSWORD } else { '' }
$TargetCreated = $false

$ScriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
$RepositoryRoot = (Resolve-Path (Join-Path $ScriptDirectory '..')).Path
$GitCommonRaw = (& git -C $RepositoryRoot rev-parse --git-common-dir).Trim()
if ($LASTEXITCODE -ne 0 -or $GitCommonRaw -eq '') {
    throw 'Unable to resolve the Git common directory for backup placement.'
}
$GitCommonDirectory = if ([System.IO.Path]::IsPathRooted($GitCommonRaw)) {
    [System.IO.Path]::GetFullPath($GitCommonRaw)
} else {
    [System.IO.Path]::GetFullPath((Join-Path $RepositoryRoot $GitCommonRaw))
}
$WorkspaceRoot = Split-Path -Parent $GitCommonDirectory
$BackupRoot = Join-Path $WorkspaceRoot '.tmp\db-backups'
$Timestamp = [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ')
$BackupDirectory = Join-Path $BackupRoot $Timestamp
$FullDump = Join-Path $BackupDirectory 'talenthub_local.sql'
$SourceDataDump = Join-Path $BackupDirectory 'source-data.sql'
$TargetDataDump = Join-Path $BackupDirectory 'target-data.sql'
$RestoreStdout = Join-Path $BackupDirectory 'restore.stdout.log'
$RestoreStderr = Join-Path $BackupDirectory 'restore.stderr.log'
$EvidencePath = Join-Path $BackupDirectory 'evidence.json'

function Get-ConnectionArguments {
    return @(
        "--host=$DbHost",
        "--port=$DbPort",
        "--user=$AdminUser",
        '--default-character-set=utf8mb4'
    )
}

function Invoke-MySqlLines {
    param(
        [Parameter(Mandatory = $true)][string]$Sql,
        [string]$Database = ''
    )

    $arguments = @(
        @(Get-ConnectionArguments)
        '--batch'
        '--raw'
        '--skip-column-names'
    )
    if ($Database -ne '') {
        $arguments += "--database=$Database"
    }
    $arguments += "--execute=$Sql"

    $output = & $MysqlBin @arguments
    if ($LASTEXITCODE -ne 0) {
        throw "mysql command failed with exit code $LASTEXITCODE."
    }

    return @($output | ForEach-Object { [string]$_ })
}

function Invoke-MySqlScalar {
    param(
        [Parameter(Mandatory = $true)][string]$Sql,
        [string]$Database = ''
    )

    $lines = @(Invoke-MySqlLines -Sql $Sql -Database $Database)
    if ($lines.Count -ne 1) {
        throw "Expected one MySQL scalar row, received $($lines.Count)."
    }
    return $lines[0].Trim()
}

function Invoke-LogicalDump {
    param(
        [Parameter(Mandatory = $true)][string]$Database,
        [Parameter(Mandatory = $true)][string]$OutputPath,
        [switch]$DataOnly
    )

    $arguments = @(
        @(Get-ConnectionArguments)
        '--single-transaction'
        '--quick'
        '--hex-blob'
        '--set-gtid-purged=OFF'
        '--skip-comments'
    )

    if ($DataOnly) {
        $arguments += @('--no-create-info', '--skip-triggers', '--compact', '--order-by-primary')
    } else {
        $arguments += @('--routines', '--events', '--triggers')
    }

    $arguments += "--result-file=$OutputPath"
    $arguments += $Database

    & $MysqldumpBin @arguments
    if ($LASTEXITCODE -ne 0) {
        throw "mysqldump failed for $Database with exit code $LASTEXITCODE."
    }
    if (-not (Test-Path -LiteralPath $OutputPath) -or (Get-Item -LiteralPath $OutputPath).Length -eq 0) {
        throw "mysqldump produced an empty file for $Database."
    }
}

function Get-BaseTables {
    param([Parameter(Mandatory = $true)][string]$Database)

    $sql = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$Database' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME"
    return @(Invoke-MySqlLines -Sql $sql)
}

function Get-ExactRowCounts {
    param(
        [Parameter(Mandatory = $true)][string]$Database,
        [Parameter(Mandatory = $true)][string[]]$Tables
    )

    $counts = [ordered]@{}
    foreach ($table in $Tables) {
        if ($table -notmatch '^[A-Za-z_][A-Za-z0-9_]*$') {
            throw "Unsafe table identifier returned by MySQL: $table"
        }
        $count = Invoke-MySqlScalar -Sql "SELECT COUNT(*) FROM ``$Database``.``$table``"
        $counts[$table] = [int64]$count
    }
    return $counts
}

function Assert-SameJson {
    param(
        [Parameter(Mandatory = $true)]$Expected,
        [Parameter(Mandatory = $true)]$Actual,
        [Parameter(Mandatory = $true)][string]$Message
    )

    $expectedJson = ConvertTo-Json $Expected -Depth 10 -Compress
    $actualJson = ConvertTo-Json $Actual -Depth 10 -Compress
    if ($expectedJson -cne $actualJson) {
        throw $Message
    }
}

if ($DbHost -ne '127.0.0.1' -or $DbPort -ne '3306') {
    throw 'Clone command is not pinned to the approved local MySQL endpoint.'
}
if ($SourceDatabase -ne 'talenthub_local' -or $TargetDatabase -ne 'talenthub') {
    throw 'Clone command database names do not match the approved migration.'
}
if ($AdminUser -notmatch '^[A-Za-z0-9_]+$') {
    throw 'TALENTHUB_LOCAL_ADMIN_USER contains unsupported characters.'
}
foreach ($binary in @($MysqlBin, $MysqldumpBin)) {
    if (-not (Test-Path -LiteralPath $binary -PathType Leaf)) {
        throw "Required local MySQL binary is missing: $binary"
    }
}

$PreviousMysqlPwd = $env:MYSQL_PWD
try {
    if ($AdminPassword -ne '') {
        $env:MYSQL_PWD = $AdminPassword
    } else {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }

    $sourceExists = [int](Invoke-MySqlScalar -Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$SourceDatabase'")
    if ($sourceExists -ne 1) {
        throw 'Source database talenthub_local does not exist exactly once.'
    }

    $targetExists = [int](Invoke-MySqlScalar -Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$TargetDatabase'")
    if ($targetExists -ne 0) {
        throw 'Target database already exists; refusing overwrite or merge.'
    }

    $sourceDefaults = @(Invoke-MySqlLines -Sql "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$SourceDatabase'")
    if ($sourceDefaults.Count -ne 1) {
        throw 'Unable to read source charset and collation.'
    }
    $defaults = $sourceDefaults[0] -split "`t", 2
    if ($defaults.Count -ne 2 -or $defaults[0] -notmatch '^[A-Za-z0-9_]+$' -or $defaults[1] -notmatch '^[A-Za-z0-9_]+$') {
        throw 'Source charset or collation contains unsupported characters.'
    }
    $sourceCharset = $defaults[0]
    $sourceCollation = $defaults[1]

    $sourceTablesBefore = @(Get-BaseTables -Database $SourceDatabase)
    if ($sourceTablesBefore.Count -eq 0) {
        throw 'Source database has no base tables.'
    }
    $sourceCountsBefore = Get-ExactRowCounts -Database $SourceDatabase -Tables $sourceTablesBefore
    $sourceMigrationsBefore = @(Invoke-MySqlLines -Sql 'SELECT * FROM schema_migrations ORDER BY version' -Database $SourceDatabase)

    New-Item -ItemType Directory -Path $BackupDirectory -Force | Out-Null
    Invoke-LogicalDump -Database $SourceDatabase -OutputPath $FullDump
    $fullDumpHash = (Get-FileHash -LiteralPath $FullDump -Algorithm SHA256).Hash.ToLowerInvariant()

    # Second target check closes the preflight-to-create race window.
    $targetExists = [int](Invoke-MySqlScalar -Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$TargetDatabase'")
    if ($targetExists -ne 0) {
        throw 'Target database already exists; refusing overwrite or merge.'
    }

    Invoke-MySqlLines -Sql "CREATE DATABASE ``$TargetDatabase`` CHARACTER SET $sourceCharset COLLATE $sourceCollation" | Out-Null
    $TargetCreated = $true

    $restoreArguments = @(
        @(Get-ConnectionArguments)
        "--database=$TargetDatabase"
    )
    $restore = Start-Process -FilePath $MysqlBin -ArgumentList $restoreArguments -RedirectStandardInput $FullDump -RedirectStandardOutput $RestoreStdout -RedirectStandardError $RestoreStderr -WindowStyle Hidden -Wait -PassThru
    if ($restore.ExitCode -ne 0) {
        $restoreError = if (Test-Path -LiteralPath $RestoreStderr) { (Get-Content -Raw -LiteralPath $RestoreStderr).Trim() } else { '' }
        throw "Restore failed with exit code $($restore.ExitCode): $restoreError"
    }

    foreach ($applicationHost in @('127.0.0.1', 'localhost')) {
        $accountExists = [int](Invoke-MySqlScalar -Sql "SELECT COUNT(*) FROM mysql.user WHERE User='$ApplicationUser' AND Host='$applicationHost'")
        if ($accountExists -ne 1) {
            throw "Required application account $ApplicationUser@$applicationHost is missing."
        }
        Invoke-MySqlLines -Sql "GRANT ALL PRIVILEGES ON ``$TargetDatabase``.* TO '$ApplicationUser'@'$applicationHost'" | Out-Null
    }
    Invoke-MySqlLines -Sql 'FLUSH PRIVILEGES' | Out-Null

    $sourceTablesAfter = @(Get-BaseTables -Database $SourceDatabase)
    $sourceCountsAfter = Get-ExactRowCounts -Database $SourceDatabase -Tables $sourceTablesAfter
    Assert-SameJson -Expected $sourceTablesBefore -Actual $sourceTablesAfter -Message 'Source table list changed during clone; cutover is refused.'
    Assert-SameJson -Expected $sourceCountsBefore -Actual $sourceCountsAfter -Message 'Source row counts changed during clone; cutover is refused.'

    $targetTables = @(Get-BaseTables -Database $TargetDatabase)
    $targetCounts = Get-ExactRowCounts -Database $TargetDatabase -Tables $targetTables
    Assert-SameJson -Expected $sourceTablesBefore -Actual $targetTables -Message 'Target base-table list differs from source.'
    Assert-SameJson -Expected $sourceCountsBefore -Actual $targetCounts -Message 'Target exact row counts differ from source.'

    $targetMigrations = @(Invoke-MySqlLines -Sql 'SELECT * FROM schema_migrations ORDER BY version' -Database $TargetDatabase)
    Assert-SameJson -Expected $sourceMigrationsBefore -Actual $targetMigrations -Message 'Target migration registry differs from source.'

    Invoke-LogicalDump -Database $SourceDatabase -OutputPath $SourceDataDump -DataOnly
    Invoke-LogicalDump -Database $TargetDatabase -OutputPath $TargetDataDump -DataOnly
    $sourceDataHash = (Get-FileHash -LiteralPath $SourceDataDump -Algorithm SHA256).Hash.ToLowerInvariant()
    $targetDataHash = (Get-FileHash -LiteralPath $TargetDataDump -Algorithm SHA256).Hash.ToLowerInvariant()
    if (-not [string]::Equals($sourceDataHash, $targetDataHash, [StringComparison]::Ordinal)) {
        throw 'Deterministic source and target data SHA256 hashes differ.'
    }

    $businessTables = @(
        'users',
        'student_profiles',
        'test_attempts',
        'test_results',
        'learner_assessment_answers',
        'student_skills',
        'learner_ai_consent_events',
        'learner_recommendation_runs',
        'learner_recommendation_items',
        'learner_recommendation_evidence',
        'learner_ai_evaluation_runs'
    )
    $businessCounts = [ordered]@{}
    foreach ($table in $businessTables) {
        if (-not $sourceCountsBefore.Contains($table)) {
            throw "Required Student/AI table is missing from source: $table"
        }
        $businessCounts[$table] = $sourceCountsBefore[$table]
    }

    $evidence = [ordered]@{
        host = $DbHost
        port = [int]$DbPort
        source_database = $SourceDatabase
        target_database = $TargetDatabase
        source_unchanged = $true
        table_count = $sourceTablesBefore.Count
        exact_row_counts_match = $true
        migration_registry_match = $true
        full_dump_sha256 = $fullDumpHash
        source_data_sha256 = $sourceDataHash
        target_data_sha256 = $targetDataHash
        student_ai_counts = $businessCounts
        backup_directory = $BackupDirectory
        verified_at_utc = [DateTime]::UtcNow.ToString('o')
    }
    $evidence | ConvertTo-Json -Depth 10 | Set-Content -LiteralPath $EvidencePath -Encoding utf8NoBOM

    Write-Host '[OK] Local TalentHub primary database clone verified.' -ForegroundColor Green
    Write-Host "  Source: $DbHost`:$DbPort/$SourceDatabase (unchanged)"
    Write-Host "  Target: $DbHost`:$DbPort/$TargetDatabase"
    Write-Host "  Tables: $($sourceTablesBefore.Count); exact row counts: MATCH"
    Write-Host "  Data SHA256: $sourceDataHash"
    Write-Host "  Evidence: $EvidencePath"
} catch {
    Write-Error $_
    if ($TargetCreated) {
        Write-Error 'The target database may be incomplete. It was intentionally left in place for inspection; no database was dropped.'
    }
    exit 1
} finally {
    if ($null -eq $PreviousMysqlPwd) {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    } else {
        $env:MYSQL_PWD = $PreviousMysqlPwd
    }
}
