$ErrorActionPreference = 'Stop'

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$dashboardPath = Join-Path $repositoryRoot 'app/learner/index.php'
$studentDataPath = Join-Path $repositoryRoot 'app/learner/includes/student-data.php'
$stylesheetPath = Join-Path $repositoryRoot 'assets/css/learner.css'
$heroImagePath = Join-Path $repositoryRoot 'assets/images/learner/learner-journey-hero-v3.png'

$dashboard = Get-Content -LiteralPath $dashboardPath -Raw -Encoding UTF8
$studentData = Get-Content -LiteralPath $studentDataPath -Raw -Encoding UTF8
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

Assert-Contract (Test-Path -LiteralPath $heroImagePath -PathType Leaf) 'Refined hero illustration asset must exist in the workspace.'
Assert-Contract ($dashboard.Contains('data-dashboard-journey')) 'Dashboard must expose the journey semantic hook.'
Assert-Contract ($dashboard.Contains('learner-welcome__status')) 'Hero must show assessment progress status.'
Assert-Contract ($dashboard.Contains('bài đánh giá đã hoàn thành')) 'Hero must describe completed assessment progress.'
Assert-Contract ($dashboard.Contains('learner-journey-hero-v3.png')) 'Hero must use the checkerboard-free journey illustration asset.'
Assert-Contract ($dashboard.Contains('learner-welcome__image')) 'Hero illustration must be rendered as a dedicated image element.'
Assert-Contract ($dashboard.Contains('Xem lộ trình AI')) 'Completed AI analysis must link to the AI journey.'
Assert-Contract ($dashboard.Contains('$dashboardAssessmentUnavailable')) 'Dashboard must distinguish an assessment service outage from real 0/4 progress.'
Assert-Contract ($dashboard.Contains('Dữ liệu đánh giá tạm thời chưa tải được')) 'Hero must expose a truthful assessment unavailable state.'
Assert-Contract ($dashboard.Contains('Gợi ý AI tạm thời chưa tải được')) 'AI card must expose a truthful service unavailable state.'
Assert-Contract ($dashboard.Contains('Trạng thái thành tích tạm thời chưa tải được')) 'Credential heading must not treat a service outage as real 0/4 progress.'
Assert-Contract (-not $dashboard.Contains('64h giờ')) 'Experience copy must not duplicate the hour unit.'
Assert-Contract (-not $dashboard.Contains('Xếp hạng lớp')) 'Dashboard markup must not expose an unsupported class rank.'
$databaseDashboardMatch = [regex]::Match(
    $studentData,
    '(?s)if\s*\(\$isDatabaseMode\)\s*\{(.*?)\}\s*else\s*\{\s*\$dashboardKpis'
)
Assert-Contract ($databaseDashboardMatch.Success) 'Database dashboard branch must remain discoverable.'
$databaseDashboard = $databaseDashboardMatch.Groups[1].Value
Assert-Contract ($databaseDashboard.Contains("'id' => 'competency'")) 'Database dashboard must expose the competency KPI.'
Assert-Contract ($databaseDashboard.Contains("'id' => 'experience'")) 'Database dashboard must expose the verified experience KPI.'
Assert-Contract ($databaseDashboard.Contains("'id' => 'badges'")) 'Database dashboard must expose the awarded badge KPI.'
Assert-Contract ($databaseDashboard.Contains('$verifiedSkillScores')) 'Competency score must prefer verified database skill scores.'
Assert-Contract ($databaseDashboard.Contains('$allSkillScores')) 'Competency score must have a database-skill fallback.'
Assert-Contract ($databaseDashboard.Contains("'Chưa có dữ liệu'")) 'Missing skills must not become a fabricated competency score.'
Assert-Contract ($databaseDashboard.Contains('$skillStatus !== ''active''')) 'Inactive skills must not affect the competency score.'
Assert-Contract ($databaseDashboard.Contains('$verificationStatus === ''rejected''')) 'Rejected skills must not affect the competency score.'
Assert-Contract (-not $databaseDashboard.Contains("'92/100'")) 'Database competency score must not use the mock score.'
Assert-Contract (-not $databaseDashboard.Contains("'64h'")) 'Database experience KPI must not use mock hours.'
Assert-Contract (-not $dashboard.Contains('$dashboardKpis[2]')) 'Dashboard must not depend on KPI array position.'
Assert-Contract ($dashboard.Contains('array_slice($skills, 0, 4)')) 'Dashboard must show at most four skills.'
Assert-Contract ($dashboard.Contains('array_slice(learner_activity_catalog(), 0, 3)')) 'Dashboard must show at most three repository activities.'
Assert-Contract ($dashboard.Contains('<strong><?= learner_escape($skill[''name'']); ?></strong>')) 'Skill rows must prefer the database display name instead of the technical code.'
foreach ($hook in @(
    'data-dashboard-kpis',
    'data-dashboard-skills',
    'data-dashboard-ai-summary',
    'data-ai-strengths',
    'data-ai-improvements'
)) {
    Assert-Contract ($dashboard.Contains($hook)) "Dashboard must expose semantic hook '$hook'."
}
Assert-Contract ($dashboard.Contains('/100')) 'Skill scores must be rendered on the 100-point scale.'
Assert-Contract ($dashboard.Contains('$dashboardAiStrengths')) 'AI strengths must come from the capability profile.'
Assert-Contract ($dashboard.Contains('$dashboardAiImprovements')) 'AI improvements must come from the capability profile.'
Assert-Contract ($studentData.Contains("['roadmap_analysis']")) 'Dashboard data must expose the completed roadmap analysis when the standalone capability-profile table is unavailable.'
Assert-Contract ($studentData.Contains("['talent_map']")) 'Dashboard skill fallback must use the stored AI talent map.'
Assert-Contract ($studentData.Contains('$score <= 1')) 'Stored zero-to-one AI scores must be normalized onto the 100-point display scale.'
Assert-Contract ($dashboard.Contains('$dashboardAiProfile')) 'AI summary must select the best available real analysis profile.'
Assert-Contract ($dashboard.Contains("['executive_summary']")) 'AI summary must render the stored student assessment overview.'
Assert-Contract (-not $dashboard.Contains('Năng khiếu nổi bật: IoT &amp; Drone')) 'Dashboard must not contain fabricated AI copy.'
Assert-Contract ($dashboard.Contains('data-dashboard-upcoming-activities')) 'Dashboard must expose one upcoming-activities section.'
Assert-Contract ($dashboard.Contains('Hoạt động sắp diễn ra')) 'Dashboard must use the approved upcoming activity heading.'
Assert-Contract ($dashboard.Contains('activity-detail.php?id=')) 'Activity items must link to the real detail route.'
Assert-Contract ($dashboard.Contains('learner_activity_catalog()')) 'Upcoming activities must come from the learner activity repository.'
Assert-Contract (-not $dashboard.Contains('Hoạt động đã xác nhận')) 'Confirmed activity history must not occupy a second dashboard card.'
Assert-Contract (-not $dashboard.Contains('learner-activity-card__cover')) 'Compact dashboard activity items must not render large cover images.'
$activityPosition = $dashboard.IndexOf('data-dashboard-upcoming-activities')
$credentialPosition = $dashboard.IndexOf('id="dashboard-school-credential-title"')
Assert-Contract ($activityPosition -ge 0 -and $credentialPosition -gt $activityPosition) 'Upcoming activities must appear before the secondary credential section.'

$mockDashboardMatch = [regex]::Match(
    $studentData,
    '(?s)\}\s*else\s*\{\s*\$dashboardKpis\s*=\s*\[(.*?)\];\s*\$profileKpis'
)
Assert-Contract ($mockDashboardMatch.Success) 'Mock dashboard KPI block must remain discoverable.'
$mockDashboard = $mockDashboardMatch.Groups[1].Value
foreach ($label in @('Điểm năng lực', 'Giờ trải nghiệm', 'Huy hiệu đạt được')) {
    Assert-Contract ($mockDashboard.Contains($label)) "Mock dashboard KPI block must contain '$label'."
}
Assert-Contract (-not $mockDashboard.Contains('Xếp hạng lớp')) 'Mock dashboard must not use the unsupported class rank.'

Assert-Contract ($stylesheet.Contains('Learner journey dashboard refresh')) 'Stylesheet must contain the scoped dashboard refresh block.'
Assert-Contract ($stylesheet.Contains('.learner-page-overview .learner-welcome__status')) 'Assessment status must be styled within the dashboard scope.'
Assert-Contract ($stylesheet.Contains('.learner-page-overview .learner-kpi-card__verified')) 'Verified KPI state must be styled within the dashboard scope.'
Assert-Contract ($stylesheet.Contains('.learner-page-overview .learner-skill-row__meta')) 'Skill metadata must be styled within the dashboard scope.'
Assert-Contract ($stylesheet.Contains('.learner-page-overview .learner-welcome__image')) 'Hero image must have a dedicated responsive style.'
Assert-Contract ($stylesheet.Contains('linear-gradient(135deg, var(--surface)')) 'Hero must use a light layered background that blends with the illustration.'
foreach ($selector in @(
    '.learner-page-overview .learner-progress-kpis',
    '.learner-page-overview .learner-progress-dashboard-grid',
    '.learner-page-overview .learner-progress-skill',
    '.learner-page-overview .learner-progress-ai',
    '.learner-page-overview .learner-progress-activity-list',
    '.learner-page-overview .learner-progress-empty'
)) {
    Assert-Contract ($stylesheet.Contains($selector)) "Stylesheet must define '$selector'."
}
$dashboardStyleBlock = $stylesheet.Substring($stylesheet.IndexOf('Learner journey dashboard refresh'))
Assert-Contract (-not $dashboardStyleBlock.Contains('var(--success)')) 'Dashboard refresh must not use the undefined success token.'
Assert-Contract ($dashboardStyleBlock.Contains('var(--accent)')) 'Verified UI must use the approved accent green token.'
Assert-Contract ($stylesheet.Contains('@media (max-width: 1100px)')) 'Dashboard must keep a tablet breakpoint.'
Assert-Contract ($stylesheet.Contains('@media (max-width: 720px)')) 'Dashboard must keep a mobile breakpoint.'
Assert-Contract ($stylesheet.Contains('@media (max-width: 480px)')) 'Dashboard must keep a narrow mobile breakpoint.'

& git -C $repositoryRoot check-ignore --quiet -- $PSCommandPath
Assert-Contract ($LASTEXITCODE -ne 0) 'Journey contract test must not be ignored by Git.'

Write-Output 'PASS: learner journey dashboard UI contract'
