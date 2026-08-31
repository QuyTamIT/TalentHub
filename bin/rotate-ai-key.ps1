[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[A-Za-z_][A-Za-z0-9_]{0,127}$')]
    [string]$SecretName
)

# The secret manager command is deployment-provided. This wrapper accepts only
# the logical secret name; it never accepts, echoes, or logs a key value.
$manager = $env:TALENTHUB_SECRET_MANAGER_COMMAND
if ([string]::IsNullOrWhiteSpace($manager)) {
    throw 'TALENTHUB_SECRET_MANAGER_COMMAND is not configured; refusing to handle a key locally.'
}

$null = & $manager rotate --name $SecretName --non-interactive 2>$null
if ($LASTEXITCODE -ne 0) { throw "Secret manager rotation failed (exit code $LASTEXITCODE)." }
Write-Output "Secret rotation delegated for '$SecretName'. Key material was not handled by this script."
