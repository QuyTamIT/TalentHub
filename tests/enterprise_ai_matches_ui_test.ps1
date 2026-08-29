$ErrorActionPreference = 'Stop'

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$pagePath = Join-Path $repositoryRoot 'app/enterprise/talents.php'
$scriptPath = Join-Path $repositoryRoot 'assets/js/talent-search.js'

$page = Get-Content -LiteralPath $pagePath -Raw -Encoding UTF8
$js = Get-Content -LiteralPath $scriptPath -Raw -Encoding UTF8

function Assert-Contract {
    param(
        [Parameter(Mandatory)]
        [bool] $Condition,

        [Parameter(Mandatory)]
        [string] $Message
    )

    if (-not $Condition) {
        throw "FAIL: $Message"
    }
}

# 1. Semantic UI Hooks in talents.php
Assert-Contract ($page.Contains('data-enterprise-ai-matcher')) 'Page must contain data-enterprise-ai-matcher container'
Assert-Contract ($page.Contains('data-enterprise-ai-job')) 'Page must contain data-enterprise-ai-job select hook'
Assert-Contract ($page.Contains('data-enterprise-ai-run')) 'Page must contain data-enterprise-ai-run button hook'
Assert-Contract ($page.Contains('data-enterprise-ai-state')) 'Page must contain data-enterprise-ai-state hook'
Assert-Contract ($page.Contains('data-enterprise-ai-results')) 'Page must contain data-enterprise-ai-results container'
Assert-Contract ($page.Contains('data-enterprise-ai-freshness')) 'Page must contain data-enterprise-ai-freshness hook'
Assert-Contract ($page.Contains('data-enterprise-ai-provenance')) 'Page must contain data-enterprise-ai-provenance hook'
Assert-Contract ($page.Contains('Tìm nhân tài bằng AI')) 'Button or header copy must contain "Tìm nhân tài bằng AI"'

# 2. JS API call and Safe Rendering
Assert-Contract ($js.Contains('/ai-matches')) 'talent-search.js must call /ai-matches endpoint'
Assert-Contract ($js.Contains('X-Idempotency-Key') -or $js.Contains('x-idempotency-key')) 'talent-search.js must send X-Idempotency-Key header'
Assert-Contract ($js.Contains('ready_model')) 'talent-search.js must handle ready_model state'
Assert-Contract ($js.Contains('stale_model')) 'talent-search.js must handle stale_model state'
Assert-Contract ($js.Contains('provider_unavailable')) 'talent-search.js must handle provider_unavailable state'
Assert-Contract ($js.Contains('no_candidates')) 'talent-search.js must handle no_candidates state'

# 3. No Fake Score Fallback or InnerHTML
Assert-Contract (-not ($js -match '\|\|\s*85')) 'talent-search.js must not contain || 85 fake score fallback'
Assert-Contract (-not ($js -match 'ready_rule|provider_outage_deterministic')) 'talent-search.js must not contain rule fallback states'
Assert-Contract (-not ($js.Contains('innerHTML'))) 'talent-search.js must use safe DOM manipulation (no innerHTML)'

Write-Output 'PASS: enterprise ai matches UI contract'