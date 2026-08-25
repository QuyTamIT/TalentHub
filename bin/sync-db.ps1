<#
.SYNOPSIS
    Sync the TalentHub primary local MySQL database from the canonical
    migrations + seeds in the repo.

.DESCRIPTION
    This script is the TL;DR of docs/SYNC_DB.md. It runs:

      1. CREATE DATABASE IF NOT EXISTS talenthub + talenthub_test
      2. php bin/migrate.php migrate  (applies all 7 migrations)
      3. php bin/seed.php             (RBAC: 4 roles, 84 perms, 99 mappings)
      4. php bin/seed.php --demo      (only if -WithDemo switch)
      5. Prints the final verification SELECTs so you can eyeball counts.

    It assumes:
      - Laragon is running (Apache + MySQL both green)
      - The Apache SetEnv block for DB_* is already configured
        (see docs/SYNC_DB.md step 2). Otherwise pass -AppEnv to override.
      - MySQL client is on PATH (Laragon adds it by default)
      - PHP is on PATH (Laragon adds it by default)

.PARAMETER DbUser
    MySQL user. Defaults to 'root' (Laragon default, password empty).

.PARAMETER DbPassword
    MySQL password. Defaults to empty string (Laragon default).

.PARAMETER DbHost
    MySQL host. Must be exactly '127.0.0.1'.

.PARAMETER DbPort
    MySQL port. Must be exactly '3306'.

.PARAMETER WithDemo
    Also runs `php bin/seed.php --demo` to populate the THPT Nguyễn Trãi
    school + 4 classes + 4 teachers + 8 students.

.PARAMETER SkipMigrate
    Skip the migration step (use when DB already has 7/7 applied and you
    just want to re-seed).

.PARAMETER AppEnv
    Override APP_ENV. Defaults to 'local'.

.EXAMPLE
    .\bin\sync-db.ps1
    Creates DBs, migrates, seeds RBAC.

.EXAMPLE
    .\bin\sync-db.ps1 -WithDemo
    Same as above, plus populates demo school.

.EXAMPLE
    .\bin\sync-db.ps1 -DbUser myuser -DbPassword 'secret'
    Use a non-root MySQL user.

.NOTES
    File:     TalentHub/bin/sync-db.ps1
    Author:   Generated from .cursor/plans/huong_dan_sync_db_cho_team_*
    See:      docs/SYNC_DB.md for the full step-by-step explanation.
#>

[CmdletBinding()]
param(
    [string]$DbUser     = 'root',
    [string]$DbPassword = '',
    [string]$DbHost     = '127.0.0.1',
    [string]$DbPort     = '3306',
    [switch]$WithDemo,
    [switch]$SkipMigrate,
    [string]$AppEnv     = 'local'
)

$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RepoRoot  = Resolve-Path (Join-Path $ScriptDir '..')
$PrimaryDatabase = 'talenthub'
Set-Location -LiteralPath $RepoRoot

# Ensure the script is run from inside the TalentHub folder.
if (-not (Test-Path (Join-Path $RepoRoot 'bin/migrate.php'))) {
    throw "bin/migrate.php not found in $RepoRoot. Run this script from TalentHub/bin/."
}
if ($DbHost -ne '127.0.0.1' -or $DbPort -ne '3306') {
    throw 'sync-db.ps1 is restricted to local MySQL at 127.0.0.1:3306.'
}
if ($AppEnv -ne 'local') {
    throw 'sync-db.ps1 requires APP_ENV=local.'
}
if ($DbUser -notmatch '^[A-Za-z0-9_]+$') {
    throw 'DbUser contains unsupported characters.'
}

function Write-Step($msg) {
    Write-Host ''
    Write-Host "==> $msg" -ForegroundColor Cyan
}

function Invoke-Mysql {
    param([string]$Sql)
    & mysql --user=$DbUser --host=$DbHost --port=$DbPort -e $Sql
    if ($LASTEXITCODE -ne 0) {
        throw "mysql command failed with exit code $LASTEXITCODE."
    }
}

$ManagedEnvironmentNames = @('MYSQL_PWD', 'APP_ENV', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD')
$PreviousProcessEnvironment = [ordered]@{}
foreach ($name in $ManagedEnvironmentNames) {
    $PreviousProcessEnvironment[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
}

try {
    if ($DbPassword -eq '') {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    } else {
        $env:MYSQL_PWD = $DbPassword
    }
    $env:APP_ENV = $AppEnv
    $env:DB_HOST = $DbHost
    $env:DB_PORT = $DbPort
    $env:DB_DATABASE = $PrimaryDatabase
    $env:DB_USERNAME = $DbUser
    $env:DB_PASSWORD = $DbPassword

    Write-Host "TalentHub DB sync" -ForegroundColor Green
    Write-Host "  Host:     $DbHost`:$DbPort"
    Write-Host "  User:     $DbUser"
    Write-Host "  Repo:     $RepoRoot"
    Write-Host "  Database: $PrimaryDatabase"
    Write-Host "  APP_ENV:  $AppEnv"
    Write-Host "  WithDemo: $WithDemo"
    Write-Host "  SkipMig:  $SkipMigrate"

# ---- Step 1: preflight ----
Write-Step 'Preflight: checking mysql + php on PATH'
foreach ($cmd in @('mysql', 'php')) {
    $which = Get-Command $cmd -ErrorAction SilentlyContinue
    if (-not $which) {
        throw "$cmd not found on PATH. Make sure Laragon is running."
    }
    Write-Host ("  [OK] {0} -> {1}" -f $cmd, $which.Source)
}

# ---- Step 2: ensure databases ----
Write-Step 'Ensuring databases exist (CREATE DATABASE IF NOT EXISTS)'
Invoke-Mysql "CREATE DATABASE IF NOT EXISTS ``$PrimaryDatabase`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
Invoke-Mysql "CREATE DATABASE IF NOT EXISTS talenthub_test  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
Invoke-Mysql "SHOW DATABASES LIKE '$PrimaryDatabase';"
Invoke-Mysql "SHOW DATABASES LIKE 'talenthub_test';"

# ---- Step 3: migrations ----
if (-not $SkipMigrate) {
    Write-Step 'Running migrations (7 files expected)'
    & php bin/migrate.php status
    if ($LASTEXITCODE -ne 0) { throw 'migrate.php status failed' }

    & php bin/migrate.php migrate
    if ($LASTEXITCODE -ne 0) { throw 'migrate.php migrate failed' }

    & php bin/migrate.php status
    if ($LASTEXITCODE -ne 0) { throw 'migrate.php status (post) failed' }
} else {
    Write-Step 'Skipping migrations (-SkipMigrate set)'
}

# ---- Step 4: system seed ----
Write-Step 'Running system seed (RBAC)'
& php bin/seed.php
if ($LASTEXITCODE -ne 0) { throw 'seed.php failed' }

# ---- Step 5: optional demo seed ----
if ($WithDemo) {
    Write-Step 'Running demo school seed'
    & php bin/seed.php --demo
    if ($LASTEXITCODE -ne 0) { throw 'seed.php --demo failed' }
}

# ---- Step 6: verify ----
Write-Step 'Verifying counts (expect: roles=4, perms=84, mappings=99)'
$verify = & mysql --user=$DbUser --host=$DbHost --port=$DbPort --database=$PrimaryDatabase -e @"
SELECT 'roles'    AS metric, COUNT(*) AS value FROM roles
UNION ALL SELECT 'perms',    COUNT(*) FROM permissions
UNION ALL SELECT 'mappings', COUNT(*) FROM role_permissions
UNION ALL SELECT 'schools',  COUNT(*) FROM schools
UNION ALL SELECT 'classes',  COUNT(*) FROM classes
UNION ALL SELECT 'users',    COUNT(*) FROM users;
"@
if ($LASTEXITCODE -ne 0) { throw 'verify SELECT failed' }
$verify | ForEach-Object { Write-Host "  $_" }

Write-Step 'Done.'
if (-not $WithDemo) {
    Write-Host '  Tip: re-run with -WithDemo to populate THPT Nguyễn Trãi.' -ForegroundColor Yellow
}
} finally {
    foreach ($name in $ManagedEnvironmentNames) {
        [Environment]::SetEnvironmentVariable($name, $PreviousProcessEnvironment[$name], 'Process')
    }
}
