$ErrorActionPreference = 'Stop'

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$credentialGridPath = Join-Path $repositoryRoot 'app/learner/includes/school-credential-grid.php'
$credentialServicePath = Join-Path $repositoryRoot 'app/learner/data/Service/SchoolCredentialService.php'
$profilePath = Join-Path $repositoryRoot 'app/learner/profile.php'
$badgesPath = Join-Path $repositoryRoot 'app/learner/badges.php'
$dashboardPath = Join-Path $repositoryRoot 'app/learner/index.php'
$aiRecommendationsPath = Join-Path $repositoryRoot 'app/learner/ai-recommendations.php'
$iconsPath = Join-Path $repositoryRoot 'app/learner/includes/icons.php'
$stylesheetPath = Join-Path $repositoryRoot 'assets/css/learner.css'
$tokenStylesheetPath = Join-Path $repositoryRoot 'assets/css/home.css'

$credentialGrid = Get-Content -LiteralPath $credentialGridPath -Raw
$credentialService = Get-Content -LiteralPath $credentialServicePath -Raw
$profile = Get-Content -LiteralPath $profilePath -Raw
$badges = Get-Content -LiteralPath $badgesPath -Raw
$dashboard = Get-Content -LiteralPath $dashboardPath -Raw
$aiRecommendations = Get-Content -LiteralPath $aiRecommendationsPath -Raw
$icons = Get-Content -LiteralPath $iconsPath -Raw
$stylesheet = Get-Content -LiteralPath $stylesheetPath -Raw
$tokenStylesheet = Get-Content -LiteralPath $tokenStylesheetPath -Raw

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

Assert-Contract ($credentialGrid.Contains("`$kind === 'certificate'")) 'School credential rendering must branch on the dynamic kind field.'
Assert-Contract ($credentialGrid.Contains('data-credential-kind=')) 'Every school credential card must expose its kind for UI behavior and diagnostics.'
Assert-Contract ($credentialGrid.Contains('learner-school-credential-grid--certificates')) 'Certificate-only collections must be able to use the compact three-column diploma grid.'
Assert-Contract ($credentialGrid.Contains('learner-school-credential-grid--badges')) 'Badge-only collections must be able to use the compact medal grid.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card--certificate')) 'School certificates must use the Diploma card modifier.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card__diploma-frame')) 'School certificates must render a recognizable diploma frame.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card__diploma-crest')) 'School certificates must lead with a centered school crest.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card__diploma-state')) 'School certificates must present status as a formal diploma heading.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card__diploma-rule')) 'School certificates must include the ornamental divider used by the approved mockup.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card__diploma-summary')) 'Compact school certificates must expose one concise state summary.'
Assert-Contract (-not $credentialGrid.Contains('learner-credential-card__diploma-action')) 'Compact school certificates must not retain the oversized footer action.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card__certificate-seal')) 'Issued school certificates must expose an official seal area.'
Assert-Contract ($credentialGrid.Contains("'success' => 'Đã đạt'")) 'Credential states must use the approved compact collection label for achieved items.'
Assert-Contract ($credentialGrid.Contains("'progress' => 'Đang tiến hành'")) 'Credential states must use the approved compact collection label for active items.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card--badge')) 'School badges must use the Medal card modifier.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card__medal')) 'School badges must render a recognizable medal body.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card__medal-ribbons')) 'School badges must render medal ribbons.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card__progress-ring')) 'Badge progress must use a circular progress treatment.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card__badge-summary')) 'Compact school badges must use one concise footer summary.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card--locked')) 'Locked school credentials must have an explicit visual state.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card__criteria')) 'Dynamic current/target credential progress must remain visible.'
Assert-Contract ($credentialGrid.Contains('learner-credential-card__level')) 'School badge cards must expose the dynamic catalog level.'
Assert-Contract ($credentialService.Contains("'level' => (int) (`$item['level'] ?? 1)")) 'School credential presentation must preserve a badge catalog level.'

Assert-Contract ($profile.Contains('learner-certificate--diploma')) 'External certificates must use the Diploma visual system.'
Assert-Contract ($profile.Contains('learner-certificate__seal')) 'External certificates must expose a verification seal.'
Assert-Contract ($profile.Contains('learner-certificate__meta')) 'External certificates must retain issuer and issue-date metadata.'

Assert-Contract ($badges.Contains('learner-badge-card__medal')) 'Global badges must use the Medal visual system.'
Assert-Contract ($badges.Contains('learner-badge-card__ribbons')) 'Global badge medals must include ribbons.'
Assert-Contract ($badges.Contains('learner-badge-card__criteria')) 'Global badges must retain current/target progress.'
Assert-Contract ($badges.Contains('learner-badge-card__compact-state')) 'Global badges must use the compact state label from the approved mockup.'
Assert-Contract ($badges.Contains('learner-badge-card__compact-summary')) 'Global badges must use one concise footer summary.'
Assert-Contract ($badges.Contains("'level' => `$p['badgeLevel']")) 'Global badge view models must preserve the dynamic catalog level.'
Assert-Contract ($badges.Contains("'Đã đạt'")) 'Global badge cards must use the approved achieved label.'
Assert-Contract ($badges.Contains("'Đang tiến hành'")) 'Global badge cards must use the approved active label.'
Assert-Contract ($badges.Contains("'Chưa mở khóa'")) 'Global badge cards must use the approved locked label.'
Assert-Contract ($badges.Contains("match ((string) (`$badge['status'] ?? 'locked'))")) 'Global badge state labels must be normalized from status instead of preserving legacy labels.'
Assert-Contract ($badges.Contains("`$schoolCredentialData['certificates'] ?? []")) 'The Badges page certificate area must render dynamic school certificates, not only link away from the page.'
Assert-Contract ($badges.Contains('href="#school-certificates-title"')) 'The Badges page certificate shortcut must target its on-page Diploma section.'

foreach ($credentialPage in @{
    'badges.php' = $badges
    'profile.php' = $profile
    'index.php' = $dashboard
    'ai-recommendations.php' = $aiRecommendations
}.GetEnumerator()) {
    Assert-Contract ($credentialPage.Value.Contains('home.css?v=<?= filemtime(')) "$($credentialPage.Key) must cache-bust the shared design tokens."
    Assert-Contract ($credentialPage.Value.Contains('learner.css?v=<?= filemtime(')) "$($credentialPage.Key) must cache-bust the credential stylesheet."
}

Assert-Contract ($icons.Contains("'lock' =>")) 'The learner icon whitelist must provide a lock icon for locked credentials.'
Assert-Contract ($icons.Contains("'shield-check' =>")) 'The learner icon whitelist must provide a verification seal icon.'

foreach ($selector in @(
    '.learner-credential-card--certificate',
    '.learner-credential-card__diploma-frame',
    '.learner-credential-card__diploma-crest',
    '.learner-credential-card__diploma-state',
    '.learner-credential-card__diploma-rule',
    '.learner-credential-card__diploma-summary',
    '.learner-credential-card__certificate-seal',
    '.learner-credential-card--badge',
    '.learner-credential-card__medal',
    '.learner-credential-card__medal-ribbons',
    '.learner-credential-card__progress-ring',
    '.learner-certificate--diploma',
    '.learner-badge-card__medal',
    '.learner-badge-card__ribbons'
)) {
    Assert-Contract ($stylesheet.Contains($selector)) "Stylesheet must define '$selector'."
}

Assert-Contract ($stylesheet.Contains('Credential cards: Diploma certificates and Medal badges')) 'Credential refresh styles must live in a named, maintainable section.'
Assert-Contract ($stylesheet.Contains('Compact collection card sizing')) 'The approved compact mockup must have an explicit, maintainable sizing layer.'
Assert-Contract ($stylesheet.Contains('Compact medal state colors')) 'Compact medal colors must follow the approved orange, blue, and grayscale state system.'
Assert-Contract ($stylesheet -match '(?s)\.learner-credential-card--certificate\s*\{[^}]*min-height:\s*290px') 'School certificate cards must use the approved compact height.'
Assert-Contract ($stylesheet -match '(?s)\.learner-credential-card--badge\s*\{[^}]*min-height:\s*300px') 'School badge cards must use the approved compact height.'
Assert-Contract ($stylesheet -match '(?s)\.learner-badge-card\s*\{[^}]*min-height:\s*300px') 'System badge cards must use the approved compact height.'
Assert-Contract ($stylesheet -match '(?s)\.learner-credential-card__progress-ring,\s*\.learner-badge-card__ring\s*\{[^}]*width:\s*112px') 'Medal progress rings must be reduced to the compact mockup size.'
Assert-Contract ($stylesheet.Contains('font-family: var(--font-primary)')) 'Credential cards must use the approved Be Vietnam Pro font token.'
Assert-Contract ($stylesheet.Contains('@media (max-width: 720px)')) 'Credential cards must participate in the existing mobile breakpoint.'
Assert-Contract ($tokenStylesheet.Contains("--font-primary: 'Be Vietnam Pro', sans-serif;")) 'The shared design system must use Be Vietnam Pro.'
Assert-Contract ($tokenStylesheet.Contains('--text-secondary: #64748B;')) 'The shared secondary text token must match the approved palette.'
Assert-Contract ($tokenStylesheet.Contains('--success: #16A34A;')) 'The shared design system must expose the approved success token.'
Assert-Contract ($tokenStylesheet.Contains('--warning: #F59E0B;')) 'The shared design system must expose the approved warning token.'
Assert-Contract ($tokenStylesheet.Contains('--danger: #DC2626;')) 'The shared design system must expose the approved danger token.'

& git -C $repositoryRoot check-ignore --quiet -- $PSCommandPath
Assert-Contract ($LASTEXITCODE -ne 0) 'Credential UI contract test must not be ignored by Git.'

Write-Output 'PASS: learner credential card UI contract'
