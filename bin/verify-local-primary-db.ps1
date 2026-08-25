<#
.SYNOPSIS
    Verify the existing local TalentHub primary clone without changing either
    database.

.DESCRIPTION
    This command is pinned to 127.0.0.1:3306 and compares talenthub_local with
    talenthub using schema inventories, canonical schema hashes, migration
    records, exact row counts, and stable deterministic data hashes. Evidence
    is written only under the Git-ignored .tmp tree.
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
$AdminUser = if ($env:TALENTHUB_LOCAL_ADMIN_USER) { $env:TALENTHUB_LOCAL_ADMIN_USER } else { 'root' }
$AdminPassword = if ($env:TALENTHUB_LOCAL_ADMIN_PASSWORD) { $env:TALENTHUB_LOCAL_ADMIN_PASSWORD } else { '' }

$ScriptDirectory = Split-Path -Parent $MyInvocation.MyCommand.Path
$RepositoryRoot = (Resolve-Path (Join-Path $ScriptDirectory '..')).Path
$GitCommonRaw = (& git -C $RepositoryRoot rev-parse --git-common-dir).Trim()
if ($LASTEXITCODE -ne 0 -or $GitCommonRaw -eq '') {
    throw 'Unable to resolve the Git common directory for evidence placement.'
}
$GitCommonDirectory = if ([IO.Path]::IsPathRooted($GitCommonRaw)) {
    [IO.Path]::GetFullPath($GitCommonRaw)
} else {
    [IO.Path]::GetFullPath((Join-Path $RepositoryRoot $GitCommonRaw))
}
$WorkspaceRoot = Split-Path -Parent $GitCommonDirectory
$Timestamp = [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ')
$EvidenceDirectory = Join-Path $WorkspaceRoot ".tmp\db-verifications\$Timestamp"
$SourceSchemaDump = Join-Path $EvidenceDirectory 'source-schema.sql'
$TargetSchemaDump = Join-Path $EvidenceDirectory 'target-schema.sql'
$SourceDataDump = Join-Path $EvidenceDirectory 'source-data.sql'
$TargetDataDump = Join-Path $EvidenceDirectory 'target-data.sql'
$SourceDataAfterDump = Join-Path $EvidenceDirectory 'source-data-after.sql'
$TargetDataAfterDump = Join-Path $EvidenceDirectory 'target-data-after.sql'
$EvidencePath = Join-Path $EvidenceDirectory 'evidence.json'

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

    $arguments = @(@(Get-ConnectionArguments), '--batch', '--raw', '--skip-column-names')
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
        [Parameter(Mandatory = $true)][ValidateSet('schema', 'data')][string]$Kind
    )

    $arguments = @(
        @(Get-ConnectionArguments),
        '--single-transaction',
        '--quick',
        '--hex-blob',
        '--set-gtid-purged=OFF',
        '--skip-comments',
        '--compact'
    )
    if ($Kind -eq 'schema') {
        $arguments += @('--no-data', '--routines', '--events', '--triggers')
    } else {
        $arguments += @('--no-create-info', '--skip-triggers', '--order-by-primary')
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

function Get-StringSha256 {
    param([Parameter(Mandatory = $true)][string]$Value)

    $algorithm = [Security.Cryptography.SHA256]::Create()
    try {
        $hashBytes = $algorithm.ComputeHash([Text.Encoding]::UTF8.GetBytes($Value))
        return -join ($hashBytes | ForEach-Object { $_.ToString('x2') })
    } finally {
        $algorithm.Dispose()
    }
}

function Get-CanonicalSchemaSha256 {
    param([Parameter(Mandatory = $true)][string]$Path)

    $schema = Get-Content -Raw -LiteralPath $Path
    $canonical = [regex]::Replace(
        $schema,
        ' CHARACTER SET [A-Za-z0-9_]+(?= COLLATE [A-Za-z0-9_]+)',
        '',
        [Text.RegularExpressions.RegexOptions]::CultureInvariant
    )
    return Get-StringSha256 -Value $canonical
}

function Get-BaseTables {
    param([Parameter(Mandatory = $true)][string]$Database)

    return @(Invoke-MySqlLines -Sql "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$Database' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")
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
        $counts[$table] = [int64](Invoke-MySqlScalar -Sql "SELECT COUNT(*) FROM ``$Database``.``$table``")
    }
    return $counts
}

function Get-SchemaObjectCounts {
    param([Parameter(Mandatory = $true)][string]$Database)

    return [ordered]@{
        base_tables = [int64](Invoke-MySqlScalar -Sql "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$Database' AND TABLE_TYPE='BASE TABLE'")
        views = [int64](Invoke-MySqlScalar -Sql "SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA='$Database'")
        triggers = [int64](Invoke-MySqlScalar -Sql "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$Database'")
        routines = [int64](Invoke-MySqlScalar -Sql "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='$Database'")
        events = [int64](Invoke-MySqlScalar -Sql "SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA='$Database'")
    }
}

function Assert-SameJson {
    param(
        [Parameter(Mandatory = $true)]$Expected,
        [Parameter(Mandatory = $true)]$Actual,
        [Parameter(Mandatory = $true)][string]$Message
    )

    if ((ConvertTo-Json $Expected -Depth 10 -Compress) -cne (ConvertTo-Json $Actual -Depth 10 -Compress)) {
        throw $Message
    }
}

if ($DbHost -ne '127.0.0.1' -or $DbPort -ne '3306') {
    throw 'Verifier is not pinned to the approved local MySQL endpoint.'
}
if ($SourceDatabase -ne 'talenthub_local' -or $TargetDatabase -ne 'talenthub') {
    throw 'Verifier database names do not match the approved migration.'
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

    foreach ($database in @($SourceDatabase, $TargetDatabase)) {
        $exists = [int](Invoke-MySqlScalar -Sql "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$database'")
        if ($exists -ne 1) {
            throw "Required database does not exist exactly once: $database"
        }
    }

    $sourceDefaults = @(Invoke-MySqlLines -Sql "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$SourceDatabase'")
    $targetDefaults = @(Invoke-MySqlLines -Sql "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$TargetDatabase'")
    Assert-SameJson -Expected $sourceDefaults -Actual $targetDefaults -Message 'Database charset or collation defaults differ.'

    $sourceTables = @(Get-BaseTables -Database $SourceDatabase)
    $targetTables = @(Get-BaseTables -Database $TargetDatabase)
    if ($sourceTables.Count -eq 0) {
        throw 'Source database has no base tables.'
    }
    Assert-SameJson -Expected $sourceTables -Actual $targetTables -Message 'Base-table inventories differ.'

    $sourceCounts = Get-ExactRowCounts -Database $SourceDatabase -Tables $sourceTables
    $targetCounts = Get-ExactRowCounts -Database $TargetDatabase -Tables $targetTables
    Assert-SameJson -Expected $sourceCounts -Actual $targetCounts -Message 'Exact row counts differ.'

    $sourceMigrations = @(Invoke-MySqlLines -Sql 'SELECT * FROM schema_migrations ORDER BY version' -Database $SourceDatabase)
    $targetMigrations = @(Invoke-MySqlLines -Sql 'SELECT * FROM schema_migrations ORDER BY version' -Database $TargetDatabase)
    Assert-SameJson -Expected $sourceMigrations -Actual $targetMigrations -Message 'Migration registries differ.'

    $sourceSchemaObjects = Get-SchemaObjectCounts -Database $SourceDatabase
    $targetSchemaObjects = Get-SchemaObjectCounts -Database $TargetDatabase
    Assert-SameJson -Expected $sourceSchemaObjects -Actual $targetSchemaObjects -Message 'Schema-object counts differ.'

    New-Item -ItemType Directory -Path $EvidenceDirectory -Force | Out-Null
    Invoke-LogicalDump -Database $SourceDatabase -OutputPath $SourceSchemaDump -Kind schema
    Invoke-LogicalDump -Database $TargetDatabase -OutputPath $TargetSchemaDump -Kind schema
    $sourceSchemaHash = Get-CanonicalSchemaSha256 -Path $SourceSchemaDump
    $targetSchemaHash = Get-CanonicalSchemaSha256 -Path $TargetSchemaDump
    if ($sourceSchemaHash -cne $targetSchemaHash) {
        throw 'Canonical schema hashes differ.'
    }

    Invoke-LogicalDump -Database $SourceDatabase -OutputPath $SourceDataDump -Kind data
    Invoke-LogicalDump -Database $TargetDatabase -OutputPath $TargetDataDump -Kind data
    $sourceDataHash = (Get-FileHash -LiteralPath $SourceDataDump -Algorithm SHA256).Hash.ToLowerInvariant()
    $targetDataHash = (Get-FileHash -LiteralPath $TargetDataDump -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($sourceDataHash -cne $targetDataHash) {
        throw 'Deterministic data hashes differ.'
    }

    Invoke-LogicalDump -Database $SourceDatabase -OutputPath $SourceDataAfterDump -Kind data
    Invoke-LogicalDump -Database $TargetDatabase -OutputPath $TargetDataAfterDump -Kind data
    $sourceDataAfterHash = (Get-FileHash -LiteralPath $SourceDataAfterDump -Algorithm SHA256).Hash.ToLowerInvariant()
    $targetDataAfterHash = (Get-FileHash -LiteralPath $TargetDataAfterDump -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($sourceDataHash -cne $sourceDataAfterHash -or $targetDataHash -cne $targetDataAfterHash) {
        throw 'A database changed during verification; evidence is refused.'
    }

    $sourceCountsAfter = Get-ExactRowCounts -Database $SourceDatabase -Tables $sourceTables
    $targetCountsAfter = Get-ExactRowCounts -Database $TargetDatabase -Tables $targetTables
    Assert-SameJson -Expected $sourceCounts -Actual $sourceCountsAfter -Message 'Source row counts changed during verification.'
    Assert-SameJson -Expected $targetCounts -Actual $targetCountsAfter -Message 'Target row counts changed during verification.'

    $businessTables = @(
        'users', 'student_profiles', 'test_attempts', 'test_results',
        'learner_assessment_answers', 'student_skills',
        'learner_ai_consent_events', 'learner_recommendation_runs',
        'learner_recommendation_items', 'learner_recommendation_evidence',
        'learner_ai_evaluation_runs'
    )
    $businessCounts = [ordered]@{}
    foreach ($table in $businessTables) {
        if (-not $sourceCounts.Contains($table)) {
            throw "Required Student/AI table is missing: $table"
        }
        $businessCounts[$table] = $sourceCounts[$table]
    }

    $evidence = [ordered]@{
        host = $DbHost
        port = [int]$DbPort
        source_database = $SourceDatabase
        target_database = $TargetDatabase
        source_unchanged = $true
        target_unchanged = $true
        database_defaults_match = $true
        table_count = $sourceTables.Count
        source_table_inventory_sha256 = Get-StringSha256 -Value (ConvertTo-Json $sourceTables -Compress)
        source_row_counts_sha256 = Get-StringSha256 -Value (ConvertTo-Json $sourceCounts -Compress)
        exact_row_counts_match = $true
        migration_registry_match = $true
        migration_registry_sha256 = Get-StringSha256 -Value (ConvertTo-Json $sourceMigrations -Compress)
        schema_objects_match = $true
        schema_object_counts = $sourceSchemaObjects
        source_schema_sha256 = $sourceSchemaHash
        target_schema_sha256 = $targetSchemaHash
        source_schema_dump_sha256 = (Get-FileHash -LiteralPath $SourceSchemaDump -Algorithm SHA256).Hash.ToLowerInvariant()
        target_schema_dump_sha256 = (Get-FileHash -LiteralPath $TargetSchemaDump -Algorithm SHA256).Hash.ToLowerInvariant()
        source_data_sha256 = $sourceDataHash
        target_data_sha256 = $targetDataHash
        student_ai_counts = $businessCounts
        evidence_directory = $EvidenceDirectory
        verified_at_utc = [DateTime]::UtcNow.ToString('o')
    }
    $evidence | ConvertTo-Json -Depth 10 | Set-Content -LiteralPath $EvidencePath -Encoding utf8NoBOM

    Write-Host '[OK] Existing local TalentHub primary clone verified read-only.' -ForegroundColor Green
    Write-Host "  Source: $DbHost`:$DbPort/$SourceDatabase (unchanged)"
    Write-Host "  Target: $DbHost`:$DbPort/$TargetDatabase (unchanged)"
    Write-Host "  Tables: $($sourceTables.Count); schema, rows, migrations, data: MATCH"
    Write-Host "  Evidence: $EvidencePath"
} catch {
    Write-Error $_
    exit 1
} finally {
    if ($null -eq $PreviousMysqlPwd) {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    } else {
        $env:MYSQL_PWD = $PreviousMysqlPwd
    }
}
