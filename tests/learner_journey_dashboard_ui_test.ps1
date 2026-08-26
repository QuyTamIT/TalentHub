$ErrorActionPreference = 'Stop'

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$dashboardPath = Join-Path $repositoryRoot 'app/learner/index.php'
$studentDataPath = Join-Path $repositoryRoot 'app/learner/includes/student-data.php'
$stylesheetPath = Join-Path $repositoryRoot 'assets/css/learner.css'
$heroImagePath = Join-Path $repositoryRoot 'assets/images/learner/learner-journey-hero-v3.png'

$dashboard = Get-Content -LiteralPath $dashboardPath -Raw
$studentData = Get-Content -LiteralPath $studentDataPath -Raw
$stylesheet = Get-Content -LiteralPath $stylesheetPath -Raw

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
Assert-Contract ($dashboard.Contains('learner-kpi-card__verified')) 'Database KPIs must show their verified state.'
Assert-Contract ($dashboard.Contains('learner-skill-row__meta')) 'Skill rows must include level and verification metadata.'
Assert-Contract ($dashboard.Contains('learner-journey-hero-v3.png')) 'Hero must use the checkerboard-free journey illustration asset.'
Assert-Contract ($dashboard.Contains('learner-welcome__image')) 'Hero illustration must be rendered as a dedicated image element.'
Assert-Contract ($dashboard.Contains('Xem lộ trình AI')) 'Completed AI analysis must link to the AI journey.'
Assert-Contract ($dashboard.Contains('$dashboardAssessmentUnavailable')) 'Dashboard must distinguish an assessment service outage from real 0/4 progress.'
Assert-Contract ($dashboard.Contains('Dữ liệu đánh giá tạm thời chưa tải được')) 'Hero must expose a truthful assessment unavailable state.'
Assert-Contract ($dashboard.Contains('Gợi ý AI tạm thời chưa tải được')) 'AI card must expose a truthful service unavailable state.'
Assert-Contract ($dashboard.Contains('Trạng thái thành tích tạm thời chưa tải được')) 'Credential heading must not treat a service outage as real 0/4 progress.'
Assert-Contract (-not $dashboard.Contains('64h giờ')) 'Experience copy must not duplicate the hour unit.'
Assert-Contract (-not $dashboard.Contains('Điểm năng lực')) 'Dashboard markup must not expose an unsupported competency KPI.'
Assert-Contract (-not $dashboard.Contains('Xếp hạng lớp')) 'Dashboard markup must not expose an unsupported class rank.'

$mockDashboardMatch = [regex]::Match(
    $studentData,
    '(?s)\}\s*else\s*\{\s*\$dashboardKpis\s*=\s*\[(.*?)\];\s*\$profileKpis'
)
Assert-Contract ($mockDashboardMatch.Success) 'Mock dashboard KPI block must remain discoverable.'
$mockDashboard = $mockDashboardMatch.Groups[1].Value
foreach ($label in @('Cấp độ hiện tại', 'Huy hiệu đạt được', 'Giờ trải nghiệm', 'Hoạt động đã tham gia')) {
    Assert-Contract ($mockDashboard.Contains($label)) "Mock dashboard KPI block must contain '$label'."
}
Assert-Contract (-not $mockDashboard.Contains('Điểm năng lực')) 'Mock dashboard must not use the unsupported competency KPI.'
Assert-Contract (-not $mockDashboard.Contains('Xếp hạng lớp')) 'Mock dashboard must not use the unsupported class rank.'

Assert-Contract ($stylesheet.Contains('Learner journey dashboard refresh')) 'Stylesheet must contain the scoped dashboard refresh block.'
Assert-Contract ($stylesheet.Contains('.learner-page-overview .learner-welcome__status')) 'Assessment status must be styled within the dashboard scope.'
Assert-Contract ($stylesheet.Contains('.learner-page-overview .learner-kpi-card__verified')) 'Verified KPI state must be styled within the dashboard scope.'
Assert-Contract ($stylesheet.Contains('.learner-page-overview .learner-skill-row__meta')) 'Skill metadata must be styled within the dashboard scope.'
Assert-Contract ($stylesheet.Contains('.learner-page-overview .learner-welcome__image')) 'Hero image must have a dedicated responsive style.'
Assert-Contract ($stylesheet.Contains('linear-gradient(135deg, var(--surface)')) 'Hero must use a light layered background that blends with the illustration.'
$dashboardStyleBlock = $stylesheet.Substring($stylesheet.IndexOf('Learner journey dashboard refresh'))
Assert-Contract (-not $dashboardStyleBlock.Contains('var(--success)')) 'Dashboard refresh must not use the undefined success token.'
Assert-Contract ($dashboardStyleBlock.Contains('var(--accent)')) 'Verified UI must use the approved accent green token.'
Assert-Contract ($stylesheet.Contains('@media (max-width: 1100px)')) 'Dashboard must keep a tablet breakpoint.'
Assert-Contract ($stylesheet.Contains('@media (max-width: 720px)')) 'Dashboard must keep a mobile breakpoint.'
Assert-Contract ($stylesheet.Contains('@media (max-width: 480px)')) 'Dashboard must keep a narrow mobile breakpoint.'

& git -C $repositoryRoot check-ignore --quiet -- $PSCommandPath
Assert-Contract ($LASTEXITCODE -ne 0) 'Journey contract test must not be ignored by Git.'

Write-Output 'PASS: learner journey dashboard UI contract'
