<?php

declare(strict_types=1);

$renderTestCompleted = false;
register_shutdown_function(static function () use (&$renderTestCompleted): void {
    if (!$renderTestCompleted) {
        fwrite(STDERR, "Assertion failed: talent passport render test exited before completing its assertions\n");
        exit(1);
    }
});

$root = dirname(__DIR__);
require_once $root . '/app/learner/data/bootstrap.php';

function passport_render_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

/**
 * Render learner page in database mode with in-memory SQLite fixture
 */
function render_learner_page_with_pdo(string $path, PDO $pdo, string $studentId, array $query = []): string
{
    $_GET = $query;
    putenv('APP_ENV=local');
    putenv('TALENTHUB_LEARNER_SOURCE=database');
    $_ENV['APP_ENV'] = 'local';
    $_ENV['TALENTHUB_LEARNER_SOURCE'] = 'database';
    $_SERVER['APP_ENV'] = 'local';
    $_SERVER['TALENTHUB_LEARNER_SOURCE'] = 'database';

    learner_configure_data([
        'source' => 'database',
        'pdo' => $pdo,
        'student_id' => $studentId,
    ]);

    $studentDataPath = dirname(__DIR__) . '/app/learner/includes/student-data.php';
    $source = file_get_contents($studentDataPath);
    $source = preg_replace("~require_once\s+__DIR__\s*\.\s*'/auth-guard\.php';\s*~", '', $source, 1);
    // Mock the student app context for in-memory test harness
    $source = preg_replace(
        "~\\\$context\s*=\s*\(new\s+\\\\TalentHub\\\\Bootstrap\\\\StudentAppContext\(\)\)->boot\(\);~",
        "\$context = ['student' => ['id' => '{$studentId}', 'fullName' => 'Database Learner', 'className' => '12A1', 'schoolName' => 'THPT Chuyên', 'email' => 'learner@example.com', 'location' => 'Hà Nội', 'studyStatus' => 'studying'], 'dashboard' => ['streak_days' => 7, 'experience_hours' => 12.5], 'csrfToken' => 'test-csrf-token', 'user' => ['id' => '{$studentId}', 'fullName' => 'Database Learner'], 'pdo' => \$pdo];",
        $source,
        1
    );
    $source = str_replace('__DIR__', var_export(dirname($studentDataPath), true), $source);

    eval('?>' . $source);
    $dataVars = get_defined_vars();

    $pageSource = file_get_contents($path);
    $pageSource = preg_replace("~require\s+__DIR__\s*\.\s*'/includes/student-data\.php';\s*~", '', $pageSource, 1);
    $pageSource = str_replace('__DIR__', var_export(dirname($path), true), $pageSource);

    extract($dataVars, EXTR_SKIP);

    set_error_handler(
        static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
    );
    ob_start();
    try {
        eval('?>' . $pageSource);
        return (string) ob_get_clean();
    } finally {
        restore_error_handler();
    }
}

/**
 * Render learner page in explicit mock mode
 */
function render_learner_page_mock(string $path, array $query = []): string
{
    $_GET = $query;
    putenv('APP_ENV=test');
    putenv('TALENTHUB_LEARNER_SOURCE=mock');
    $_ENV['APP_ENV'] = 'test';
    $_ENV['TALENTHUB_LEARNER_SOURCE'] = 'mock';
    $_SERVER['APP_ENV'] = 'test';
    $_SERVER['TALENTHUB_LEARNER_SOURCE'] = 'mock';

    learner_configure_data([
        'source' => 'mock',
    ]);

    $studentDataPath = dirname(__DIR__) . '/app/learner/includes/student-data.php';
    $source = file_get_contents($studentDataPath);
    $source = preg_replace("~require_once\s+__DIR__\s*\.\s*'/auth-guard\.php';\s*~", '', $source, 1);
    $source = str_replace('__DIR__', var_export(dirname($studentDataPath), true), $source);

    eval('?>' . $source);
    $dataVars = get_defined_vars();

    $pageSource = file_get_contents($path);
    $pageSource = preg_replace("~require\s+__DIR__\s*\.\s*'/includes/student-data\.php';\s*~", '', $pageSource, 1);
    $pageSource = str_replace('__DIR__', var_export(dirname($path), true), $pageSource);

    extract($dataVars, EXTR_SKIP);

    set_error_handler(
        static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
    );
    ob_start();
    try {
        eval('?>' . $pageSource);
        return (string) ob_get_clean();
    } finally {
        restore_error_handler();
    }
}

// 1. Setup SQLite database with canonical student facts
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, fullName TEXT, email TEXT, status TEXT)');
$pdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT, status TEXT)');
$pdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT, name TEXT, gradeLevel TEXT, academicYear TEXT, status TEXT)');
$pdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT, classId TEXT, studyStatus TEXT)');
$pdo->exec('CREATE TABLE teacher_profiles (id TEXT PRIMARY KEY, userId TEXT, schoolId TEXT, isSchoolAdmin INT)');
$pdo->exec('CREATE TABLE skills (id TEXT PRIMARY KEY, code TEXT, name TEXT, category TEXT, status TEXT)');
$pdo->exec('CREATE TABLE student_skills (studentId TEXT, skillId TEXT, levelScore REAL, sourceType TEXT, verificationStatus TEXT, verifiedAt TEXT)');
$pdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT, createdByTeacherId TEXT, title TEXT, category TEXT, startAt TEXT, endAt TEXT, capacity INTEGER, status TEXT, createdAt TEXT)');
$pdo->exec('CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, audienceScope TEXT, displayCategory TEXT, filterCategory TEXT, locationName TEXT, coverImageUrl TEXT, coverImageAlt TEXT)');
$pdo->exec('CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT, registrationClosesAt TEXT, cancellationClosesAt TEXT, approvalMode TEXT)');
$pdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT, registeredAt TEXT)');
$pdo->exec('CREATE TABLE checkins (id TEXT PRIMARY KEY, registrationId TEXT, qrSessionId TEXT, status TEXT, checkedInAt TEXT, confirmedAt TEXT)');
$pdo->exec('CREATE TABLE experience_logs (id TEXT PRIMARY KEY, studentId TEXT, activityId TEXT, checkinId TEXT, hours REAL, status TEXT, confirmedAt TEXT)');
$pdo->exec('CREATE TABLE talent_tests (id TEXT PRIMARY KEY, code TEXT, name TEXT, type TEXT, status TEXT)');
$pdo->exec('CREATE TABLE test_attempts (id TEXT PRIMARY KEY, testId TEXT, studentId TEXT, status TEXT, startedAt TEXT, submittedAt TEXT)');
$pdo->exec('CREATE TABLE test_results (attemptId TEXT, resultCode TEXT, summary TEXT, dimensionScoresJson TEXT, scoringVersion TEXT, createdAt TEXT)');
$pdo->exec('CREATE TABLE assessments (id TEXT PRIMARY KEY, teacherId TEXT, studentId TEXT, activityId TEXT, overallScore REAL, comment TEXT, status TEXT, publishedAt TEXT, version INT)');
$pdo->exec('CREATE TABLE assessment_scores (assessmentId TEXT, criteriaId TEXT, score REAL)');
$pdo->exec('CREATE TABLE assessment_criteria (id TEXT PRIMARY KEY, code TEXT, name TEXT, minScore REAL, maxScore REAL, displayOrder INT, status TEXT)');

$studentId = '0191316b-1000-4000-8000-000000000001';
$userId = '0191316b-2000-4000-8000-000000000001';
$teacherUser = '0191316b-2000-4000-8000-000000000003';
$teacherProfile = '0191316b-3000-4000-8000-000000000003';
$schoolId = '0191316b-4000-4000-8000-000000000001';
$classId = '0191316b-5000-4000-8000-000000000001';
$openActivityId = '0191316b-6000-4000-8000-000000000001';
$openActivityIdTwo = '0191316b-6000-4000-8000-000000000005';
$openActivityIdThree = '0191316b-6000-4000-8000-000000000006';
$registeredFutureActivityId = '0191316b-6000-4000-8000-000000000007';
$confirmedActivityId = '0191316b-6000-4000-8000-000000000002';
$fallbackActivityId = '0191316b-6000-4000-8000-000000000003';
$noShowActivityId = '0191316b-6000-4000-8000-000000000004';

$pdo->exec("INSERT INTO users VALUES ('{$userId}', 'Database Learner', 'learner@example.com', 'active')");
$pdo->exec("INSERT INTO users VALUES ('{$teacherUser}', 'Teacher Minh', 'minh@example.com', 'active')");
$pdo->exec("INSERT INTO schools VALUES ('{$schoolId}', 'THPT Chuyên', 'active')");
$pdo->exec("INSERT INTO classes VALUES ('{$classId}', '{$schoolId}', '12A1', '12', '2025-2026', 'active')");
$pdo->exec("INSERT INTO student_profiles VALUES ('{$studentId}', '{$userId}', '{$classId}', 'studying')");
$pdo->exec("INSERT INTO teacher_profiles VALUES ('{$teacherProfile}', '{$teacherUser}', '{$schoolId}', 0)");
$pdo->exec("INSERT INTO skills VALUES ('sk-1', 'PY', 'Python', 'technical', 'active')");
$pdo->exec("INSERT INTO student_skills VALUES ('{$studentId}', 'sk-1', 88.0, 'assessment', 'verified', '2026-08-01 10:00:00')");
$pdo->exec("INSERT INTO activities VALUES ('{$confirmedActivityId}', '{$schoolId}', '{$teacherProfile}', 'Robotics Verified', 'career_technical', '2026-08-10 08:00:00', '2026-08-10 12:00:00', 30, 'completed', '2026-08-01 00:00:00')");
$pdo->exec("INSERT INTO activities VALUES ('{$fallbackActivityId}', '{$schoolId}', '{$teacherProfile}', 'Business Metadata Fallback', 'career_business', '2026-08-11 08:00:00', '2026-08-11 12:00:00', 30, 'completed', '2026-08-01 00:00:00')");
$pdo->exec("INSERT INTO activities VALUES ('{$noShowActivityId}', '{$schoolId}', '{$teacherProfile}', 'No Show Secret Workshop', 'workshop', '2026-08-12 08:00:00', '2026-08-12 12:00:00', 30, 'completed', '2026-08-01 00:00:00')");
$pdo->exec("INSERT INTO activities VALUES ('{$openActivityId}', '{$schoolId}', '{$teacherProfile}', 'Robotics Open Day', 'career_technical', '2026-09-10 08:00:00', '2026-09-10 12:00:00', 30, 'published', '2026-08-01 00:00:00')");
$pdo->exec("INSERT INTO activities VALUES ('{$openActivityIdTwo}', '{$schoolId}', '{$teacherProfile}', 'Business Future Lab', 'career_business', '2026-09-11 08:00:00', '2026-09-11 12:00:00', 30, 'published', '2026-08-01 00:00:00')");
$pdo->exec("INSERT INTO activities VALUES ('{$openActivityIdThree}', '{$schoolId}', '{$teacherProfile}', 'Creative Future Studio', 'career_arts', '2026-09-12 08:00:00', '2026-09-12 12:00:00', 30, 'published', '2026-08-01 00:00:00')");
$pdo->exec("INSERT INTO activities VALUES ('{$registeredFutureActivityId}', '{$schoolId}', '{$teacherProfile}', 'Approved Future Hidden', 'career_technical', '2026-09-13 08:00:00', '2026-09-13 12:00:00', 30, 'published', '2026-08-01 00:00:00')");
$pdo->exec("INSERT INTO activity_details VALUES ('{$confirmedActivityId}', 'school_only', 'Kỹ thuật', 'Kỹ thuật', 'Phòng Robotics B305', 'assets/activities/covers/talenthub-stem-robotics.webp', 'Sinh viên thực hành robotics tại phòng B305')");
$pdo->exec("INSERT INTO activity_details VALUES ('{$fallbackActivityId}', 'school_only', NULL, NULL, NULL, 'javascript:alert(1)', NULL)");
$pdo->exec("INSERT INTO activity_details VALUES ('{$noShowActivityId}', 'school_only', 'workshop', 'workshop', 'Phòng không được lộ', '../secret.webp', 'No-show không được render')");
$pdo->exec("INSERT INTO activity_details VALUES ('{$openActivityId}', 'school_only', 'Kỹ thuật', 'Kỹ thuật', 'Phòng Robotics', 'assets/activities/covers/talenthub-stem-robotics.webp', 'Học sinh thực hành robotics')");
$pdo->exec("INSERT INTO activity_details VALUES ('{$openActivityIdTwo}', 'school_only', 'Kinh doanh', 'Kinh doanh', 'Phòng Khởi nghiệp', 'assets/activities/covers/fpt-startup-demo-day.webp', 'Sinh viên thực hành ý tưởng kinh doanh')");
$pdo->exec("INSERT INTO activity_details VALUES ('{$openActivityIdThree}', 'school_only', 'Sáng tạo', 'Sáng tạo', 'Studio Sáng tạo', 'assets/activities/covers/talenthub-creative-studio.webp', 'Sinh viên thực hành thiết kế sáng tạo')");
$pdo->exec("INSERT INTO activity_details VALUES ('{$registeredFutureActivityId}', 'school_only', 'Kỹ thuật', 'Kỹ thuật', 'Phòng đã đăng ký', 'assets/activities/covers/talenthub-stem-robotics.webp', 'Hoạt động đã đăng ký')");
$pdo->exec("INSERT INTO activity_registration_policies VALUES ('{$openActivityId}', '2026-08-01 00:00:00', '2026-09-01 00:00:00', '2026-09-02 00:00:00', 'automatic')");
$pdo->exec("INSERT INTO activity_registration_policies VALUES ('{$openActivityIdTwo}', '2026-08-01 00:00:00', '2026-09-02 00:00:00', '2026-09-03 00:00:00', 'automatic')");
$pdo->exec("INSERT INTO activity_registration_policies VALUES ('{$openActivityIdThree}', '2026-08-01 00:00:00', '2026-09-03 00:00:00', '2026-09-04 00:00:00', 'automatic')");
$pdo->exec("INSERT INTO activity_registration_policies VALUES ('{$registeredFutureActivityId}', '2026-08-01 00:00:00', '2026-09-04 00:00:00', '2026-09-05 00:00:00', 'automatic')");
$pdo->exec("INSERT INTO activity_registrations VALUES ('reg-1', '{$confirmedActivityId}', '{$studentId}', 'attended', '2026-08-01 09:00:00')");
$pdo->exec("INSERT INTO activity_registrations VALUES ('reg-fallback', '{$fallbackActivityId}', '{$studentId}', 'attended', '2026-08-02 09:00:00')");
$pdo->exec("INSERT INTO activity_registrations VALUES ('reg-no-show', '{$noShowActivityId}', '{$studentId}', 'no_show', '2026-08-03 09:00:00')");
$pdo->exec("INSERT INTO activity_registrations VALUES ('reg-future-approved', '{$registeredFutureActivityId}', '{$studentId}', 'approved', '2026-08-04 09:00:00')");
$pdo->exec("INSERT INTO checkins VALUES ('chk-1', 'reg-1', 'qr-1', 'confirmed', '2026-08-10 08:05:00', '2026-08-10 08:10:00')");
$pdo->exec("INSERT INTO checkins VALUES ('chk-fallback', 'reg-fallback', 'qr-2', 'confirmed', '2026-08-11 08:05:00', '2026-08-11 08:10:00')");
$pdo->exec("INSERT INTO checkins VALUES ('chk-no-show', 'reg-no-show', 'qr-3', 'confirmed', '2026-08-12 08:05:00', '2026-08-12 08:10:00')");
$pdo->exec("INSERT INTO experience_logs VALUES ('exp-1', '{$studentId}', '{$confirmedActivityId}', 'chk-1', 12.5, 'confirmed', '2026-08-10 12:00:00')");
$pdo->exec("INSERT INTO experience_logs VALUES ('exp-fallback', '{$studentId}', '{$fallbackActivityId}', 'chk-fallback', 0.0, 'confirmed', '2026-08-11 12:00:00')");
$pdo->exec("INSERT INTO experience_logs VALUES ('exp-no-show', '{$studentId}', '{$noShowActivityId}', 'chk-no-show', 7.0, 'confirmed', '2026-08-12 12:00:00')");

// 2. Test Database Mode Rendering
$dashboardHtml = render_learner_page_with_pdo($root . '/app/learner/index.php', $pdo, $studentId);
$profileHtml = render_learner_page_with_pdo($root . '/app/learner/profile.php', $pdo, $studentId);
$badgesHtml = render_learner_page_with_pdo($root . '/app/learner/badges.php', $pdo, $studentId);
$statisticsHtml = render_learner_page_with_pdo($root . '/app/learner/statistics.php', $pdo, $studentId);

// Assert canonical student name is rendered
passport_render_assert(str_contains($dashboardHtml, 'Database Learner'), 'canonical Student renders in dashboard');
passport_render_assert(str_contains($profileHtml, 'Database Learner'), 'canonical Student renders in profile');

// Assert truthful metrics & no fabricated comparison
passport_render_assert(!str_contains($dashboardHtml, 'vượt 28%'), 'fabricated comparison is absent from dashboard');
passport_render_assert(str_contains($dashboardHtml, '12.5h'), 'truthful confirmed experience hours rendered on dashboard');
passport_render_assert(str_contains($dashboardHtml, 'Python'), 'canonical skill rendered on dashboard');
passport_render_assert(str_contains($dashboardHtml, 'Robotics Verified'), 'canonical confirmed activity renders on dashboard');
passport_render_assert(str_contains($dashboardHtml, 'Kỹ thuật'), 'confirmed activity uses Vietnamese display category');
passport_render_assert(!str_contains($dashboardHtml, 'career_technical'), 'dashboard never renders the raw career category');
passport_render_assert(!preg_match('/learner-badge[^>]*>\s*workshop\s*</i', $dashboardHtml), 'dashboard never renders a raw workshop badge');
passport_render_assert(str_contains($dashboardHtml, 'Phòng Robotics B305'), 'confirmed activity renders its real location');
passport_render_assert(!str_contains($dashboardHtml, 'Địa điểm chưa có dữ liệu'), 'dashboard does not invent a location');
passport_render_assert(str_contains($dashboardHtml, 'assets/activities/covers/talenthub-stem-robotics.webp'), 'confirmed activity renders its validated local cover');
passport_render_assert(str_contains($dashboardHtml, 'Sinh viên thực hành robotics tại phòng B305'), 'confirmed activity renders meaningful cover alt text');
passport_render_assert(str_contains($dashboardHtml, '10/08/2026'), 'confirmed activity renders a Vietnamese activity date');
passport_render_assert(str_contains($dashboardHtml, 'activity-detail.php?id=' . $confirmedActivityId), 'confirmed card links to the exact activity detail');
passport_render_assert(str_contains($dashboardHtml, 'Business Metadata Fallback'), 'confirmed metadata compatibility row remains visible');
passport_render_assert(str_contains($dashboardHtml, 'Kinh doanh'), 'missing display category falls back through the canonical Vietnamese map');
passport_render_assert(str_contains($dashboardHtml, 'assets/activities/illustrations/hero-detail.svg'), 'unsafe confirmed cover falls back to the approved local illustration');
passport_render_assert(!str_contains($dashboardHtml, 'javascript:alert(1)'), 'unsafe cover never reaches rendered HTML');
passport_render_assert(!str_contains($dashboardHtml, 'No Show Secret Workshop'), 'no_show activity is excluded from confirmed attendance cards');
passport_render_assert(!str_contains($dashboardHtml, 'Phòng không được lộ'), 'no_show metadata does not leak into confirmed cards');
$dashboardDom = new DOMDocument();
libxml_use_internal_errors(true);
$dashboardDom->loadHTML('<?xml encoding="utf-8" ?>' . $dashboardHtml, LIBXML_NOERROR | LIBXML_NOWARNING);
$renderedIds = [];
foreach ($dashboardDom->getElementsByTagName('*') as $element) {
    if ($element->hasAttribute('id')) $renderedIds[] = $element->getAttribute('id');
}
passport_render_assert(count($renderedIds) === count(array_unique($renderedIds)), 'rendered dashboard has no duplicate IDs');
passport_render_assert(str_contains($dashboardHtml, 'Robotics Open Day'), 'scoped discovery activity renders on dashboard');
passport_render_assert(str_contains($dashboardHtml, 'Business Future Lab'), 'second scoped discovery activity renders on dashboard');
passport_render_assert(str_contains($dashboardHtml, 'Creative Future Studio'), 'third scoped discovery activity renders on dashboard');
passport_render_assert(!str_contains($dashboardHtml, 'Approved Future Hidden'), 'approved future activity is excluded from Dashboard upcoming');
passport_render_assert(str_contains($dashboardHtml, 'activity-detail.php?id=' . $openActivityId), 'discovery card links to its scoped detail route');
passport_render_assert(str_contains($dashboardHtml, 'Phòng Robotics'), 'discovery card renders the activity detail location');
passport_render_assert(str_contains($dashboardHtml, 'Hoạt động đã xác nhận'), 'database activity section is labelled truthfully');
passport_render_assert(str_contains($dashboardHtml, 'Hoạt động đang mở cho bạn'), 'database discovery section is labelled separately');
passport_render_assert(!str_contains($dashboardHtml, 'data-register-activity'), 'database dashboard never renders the mock-only fake registration button');
passport_render_assert(str_contains($dashboardHtml, '>Xem chi tiết</a>'), 'database dashboard routes activity actions to the server-backed detail flow');
passport_render_assert(str_contains($dashboardHtml, 'Gợi ý AI chưa có dữ liệu'), 'database dashboard renders a truthful AI unavailable state');
foreach (['IoT Lab — Cảm biến thông minh', 'Drone Workshop', 'Năng khiếu nổi bật: IoT &amp; Drone', '64/100 giờ'] as $demoSentinel) {
    passport_render_assert(!str_contains($dashboardHtml, $demoSentinel), "database dashboard excludes demo sentinel {$demoSentinel}");
}
passport_render_assert(str_contains($dashboardHtml, 'Cấp độ hiện tại'), 'database dashboard labels the real level KPI');
passport_render_assert(str_contains($dashboardHtml, 'Innovator'), '12.5 confirmed hours derives the real Innovator level');
passport_render_assert(!str_contains($dashboardHtml, 'Xếp hạng lớp'), 'database dashboard removes the unsupported class-rank concept');
passport_render_assert(!str_contains($dashboardHtml, 'Điểm năng lực'), 'database dashboard removes the unsupported competency-score concept');

// Assert skill scores and aria-valuenow do not exceed 100
preg_match_all('/aria-valuenow="([^"]+)"/', $dashboardHtml, $dashboardValuenowMatches);
foreach ($dashboardValuenowMatches[1] as $vnow) {
    passport_render_assert(is_numeric($vnow) && (float) $vnow <= 100.0 && (float) $vnow >= 0.0, "dashboard aria-valuenow {$vnow} <= 100");
}
preg_match_all('/aria-valuenow="([^"]+)"/', $profileHtml, $profileValuenowMatches);
foreach ($profileValuenowMatches[1] as $vnow) {
    passport_render_assert(is_numeric($vnow) && (float) $vnow <= 100.0 && (float) $vnow >= 0.0, "profile aria-valuenow {$vnow} <= 100");
}
passport_render_assert(str_contains($dashboardHtml, '88/100'), 'skill score 88/100 rendered accurately');
passport_render_assert(str_contains($profileHtml, '88'), 'skill score 88 rendered on profile');

// Assert explicit empty states in database mode (no demo data)
passport_render_assert(str_contains($profileHtml, 'Chưa có chứng chỉ'), 'certificate empty state is explicit');
passport_render_assert(str_contains($profileHtml, 'Chưa có dự án'), 'project empty state is explicit');
passport_render_assert(!str_contains($profileHtml, 'Google IT Automation'), 'demo certificate is absent from database profile');
passport_render_assert(!str_contains($profileHtml, 'Smart Garden IoT'), 'demo project is absent from database profile');
passport_render_assert(!str_contains($profileHtml, 'IELTS 7.5'), 'demo IELTS certificate is absent from database profile');
passport_render_assert(!str_contains($profileHtml, 'nguyen-van-a'), 'demo public share URL is absent from database profile');
passport_render_assert(str_contains($badgesHtml, 'Chưa có dữ liệu huy hiệu và cấp độ'), 'badges database page renders an explicit unavailable state');
passport_render_assert(str_contains($statisticsHtml, 'Dữ liệu tổng hợp từ hồ sơ cá nhân của bạn'), 'statistics database page renders an owner-scoped real-data view');
passport_render_assert(!str_contains($badgesHtml, 'Phase 9'), 'badges page does not expose internal roadmap language');
passport_render_assert(!str_contains($statisticsHtml, 'Phase 9'), 'statistics page does not expose internal roadmap language');
foreach (['Người khám phá', '64/100 giờ', '92'] as $demoSentinel) {
    passport_render_assert(!str_contains($badgesHtml, $demoSentinel), "badges database page excludes demo sentinel {$demoSentinel}");
    passport_render_assert(!str_contains($statisticsHtml, $demoSentinel), "statistics database page excludes demo sentinel {$demoSentinel}");
}

// Assert actions configured in database mode
passport_render_assert(str_contains($profileHtml, 'data-learner-source="database"'), 'database mode set on body');
passport_render_assert(str_contains($profileHtml, 'data-open-modal="learner-share-modal"'), 'share modal triggered on profile');
passport_render_assert(str_contains($profileHtml, 'data-open-modal="learner-edit-modal"'), 'edit modal triggered on profile');
passport_render_assert(str_contains($profileHtml, 'id="learner-share-modal"'), 'share modal present in DOM');
passport_render_assert(str_contains($profileHtml, 'id="learner-edit-modal"'), 'edit modal present in DOM');

// 3. Test Production-path policy: APP_ENV != test and TALENTHUB_LEARNER_SOURCE=database
putenv('APP_ENV=production');
putenv('TALENTHUB_LEARNER_SOURCE=database');
$_ENV['APP_ENV'] = 'production';
$_ENV['TALENTHUB_LEARNER_SOURCE'] = 'database';
$_SERVER['APP_ENV'] = 'production';
$_SERVER['TALENTHUB_LEARNER_SOURCE'] = 'database';

learner_configure_data(['source' => 'mock']);
learner_configure_authenticated_student_context([
    'student' => ['id' => $studentId],
    'pdo' => $pdo,
]);

$factory = learner_repository_factory();
passport_render_assert($factory->source() === 'database', 'Factory source is database in production environment');
$prodPassportRepo = $factory->talentPassport();
passport_render_assert($prodPassportRepo instanceof \TalentHub\Learner\Data\Database\DatabaseTalentPassportRepository, 'DatabaseTalentPassportRepository is instantiated');
$prodAggregate = $prodPassportRepo->aggregateForStudent($studentId);
passport_render_assert($prodAggregate['student']['id'] === $studentId, 'Authenticated student ID is passed to aggregate');
passport_render_assert($prodAggregate['experience']['confirmed_hours'] === 12.5, 'Truthful confirmed hours in aggregate');

// 4. Test Mock Mode Rendering (preserves deterministic fixtures)
$mockDashboardHtml = render_learner_page_mock($root . '/app/learner/index.php');
$mockProfileHtml = render_learner_page_mock($root . '/app/learner/profile.php');

passport_render_assert(str_contains($mockProfileHtml, 'Google IT Automation'), 'mock mode retains mock certificates');
passport_render_assert(str_contains($mockProfileHtml, 'Smart Garden IoT'), 'mock mode retains mock projects');
passport_render_assert(str_contains($mockDashboardHtml, 'vượt 28%'), 'mock mode retains mock copy');

$renderTestCompleted = true;
echo "learner_talent_passport_render_test: OK\n";
