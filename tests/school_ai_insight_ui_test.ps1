$ErrorActionPreference = 'Stop'

$page = Get-Content 'app/school/analytics.php' -Raw
$js = Get-Content 'assets/js/school-ai-insights.js' -Raw

# Rule fallback check
if ($js -match 'ready_rule|analytics deterministic') {
    throw 'School AI UI must not render rule fallback'
}

# Canonical state copy check
$requiredStates = @('ready_model', 'stale_model', 'pending', 'insufficient_data', 'provider_unavailable')
foreach ($st in $requiredStates) {
    if ($js -notmatch $st) {
        throw "Missing canonical state copy for: $st"
    }
}

# Safe DOM rendering check
if ($js -match 'innerHTML') {
    throw 'School AI API data must use safe DOM rendering'
}

# Versioned asset check
if ($page -notmatch 'school-ai-insights\.js\?v=') {
    throw 'School AI asset must be versioned'
}

# Semantic hook checks in page
$requiredHooks = @(
    'data-school-ai-insight',
    'data-school-ai-state',
    'data-school-ai-content',
    'data-school-ai-summary',
    'data-school-ai-priorities',
    'data-school-ai-cohorts',
    'data-school-ai-freshness',
    'data-school-ai-model-version',
    'data-school-ai-generated-at',
    'data-school-ai-provenance'
)
foreach ($hook in $requiredHooks) {
    if ($page -notmatch [regex]::Escape($hook)) {
        throw "Missing semantic hook in analytics.php: $hook"
    }
    if ($js -notmatch [regex]::Escape($hook)) {
        throw "Missing semantic hook selector in school-ai-insights.js: $hook"
    }
}

Write-Host "school_ai_insight_ui_test.ps1: OK"