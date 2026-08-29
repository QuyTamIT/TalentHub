$ErrorActionPreference = 'Stop'

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$pagePath = Join-Path $repositoryRoot 'app/learner/ai-recommendations.php'
$scriptPath = Join-Path $repositoryRoot 'assets/js/learner-ai-groups.js'
$stylesheetPath = Join-Path $repositoryRoot 'assets/css/learner.css'

$page = Get-Content -LiteralPath $pagePath -Raw -Encoding UTF8
$script = Get-Content -LiteralPath $scriptPath -Raw -Encoding UTF8
$stylesheet = Get-Content -LiteralPath $stylesheetPath -Raw -Encoding UTF8

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

# 1. Page Markup Contracts
Assert-Contract ($page.Contains('data-ai-group-matches')) 'ai-recommendations.php must contain data-ai-group-matches container.'
Assert-Contract ($page.Contains('Nhóm phù hợp')) 'ai-recommendations.php must contain approved section heading.'
Assert-Contract ($page.Contains('learner-ai-groups.js')) 'ai-recommendations.php must include learner-ai-groups.js script.'
Assert-Contract ($page.Contains('$assetVersion(''assets/js/learner-ai-groups.js'')')) 'Script must use dynamic asset versioning.'

# 2. JavaScript Security & Rendering Contracts
Assert-Contract ($script.Contains('/app/learner/api/v1/ai-group-matches.php')) 'Script must query the canonical group matches API.'
Assert-Contract ($script.Contains('textContent')) 'Script must use textContent for safe DOM assignment.'
Assert-Contract (-not $script.Contains('innerHTML')) 'Script must not use innerHTML.'
Assert-Contract (-not $script.Contains('outerHTML')) 'Script must not use outerHTML.'
Assert-Contract ($script.Contains('action_ready')) 'Script must handle action_ready state.'
Assert-Contract ($script.Contains('join_unavailable')) 'Script must handle join_unavailable state.'
Assert-Contract (-not $script.Contains('TALENTHUB_AI_API_KEY')) 'Script must not leak secrets.'

# 3. CSS Contracts
Assert-Contract ($stylesheet.Contains('[data-ai-group-matches]') -or $stylesheet.Contains('.learner-group-card')) 'Styles for AI group matches must exist in learner.css.'

# 4. Privacy & Protected Trait Guards
Assert-Contract (-not ($page + $script).Contains('gioi_tinh')) 'Protected traits must not appear in UI code.'
Assert-Contract (-not ($page + $script).Contains('ton_giao')) 'Protected traits must not appear in UI code.'

Write-Output 'PASS: learner ai group matching UI contract'