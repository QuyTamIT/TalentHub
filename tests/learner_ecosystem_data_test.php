<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$providerPath = $root . '/app/learner/includes/ecosystem-data.php';
$enterpriseFixturePath = $root . '/app/enterprise/includes/internships-data.php';

if (!is_file($providerPath)) {
    fwrite(STDERR, "Missing learner ecosystem data provider.\n");
    exit(1);
}

require_once $root . '/app/learner/data/bootstrap.php';

$databasePdo = new PDO('sqlite::memory:');
$databasePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$databasePdo->exec('CREATE TABLE schools (id TEXT PRIMARY KEY, name TEXT, status TEXT, logoUrl TEXT, address TEXT, phone TEXT, email TEXT, website TEXT, level TEXT, studentCount INTEGER, teacherCount INTEGER, academicYear TEXT)');
$databasePdo->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, schoolId TEXT NOT NULL)');
$databasePdo->exec('CREATE TABLE student_profiles (id TEXT PRIMARY KEY, userId TEXT NOT NULL, classId TEXT NOT NULL)');
$databasePdo->exec('CREATE TABLE activities (id TEXT PRIMARY KEY, schoolId TEXT, createdByTeacherId TEXT, title TEXT, category TEXT, startAt TEXT, endAt TEXT, capacity INTEGER, status TEXT, createdAt TEXT)');
$databasePdo->exec('CREATE TABLE activity_details (activityId TEXT PRIMARY KEY, audienceScope TEXT NOT NULL)');
$databasePdo->exec('CREATE TABLE activity_registration_policies (activityId TEXT PRIMARY KEY, registrationOpensAt TEXT, registrationClosesAt TEXT, cancellationClosesAt TEXT, approvalMode TEXT)');
$databasePdo->exec('CREATE TABLE activity_experience_policies (activityId TEXT PRIMARY KEY, confirmedHours REAL NOT NULL)');
$databasePdo->exec('CREATE TABLE activity_registrations (id TEXT PRIMARY KEY, activityId TEXT, studentId TEXT, status TEXT)');
$activityInsert = $databasePdo->prepare('INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, capacity, status, createdAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
$schoolActivityId = '10000000-0000-4000-8000-000000000001';
$schoolId = '20000000-0000-4000-8000-000000000001';
$otherSchoolId = '20000000-0000-4000-8000-000000000002';
$teacherId = '30000000-0000-4000-8000-000000000001';
$studentId = '00000000-0000-4000-8000-000000000001';
$classId = '40000000-0000-4000-8000-000000000001';
$schoolInsert = $databasePdo->prepare('INSERT INTO schools (id, name, status, studentCount, teacherCount, academicYear) VALUES (?, ?, ?, ?, ?, ?)');
$schoolInsert->execute([$schoolId, 'Visible School', 'active', 10, 5, '2025-2026']);
$schoolInsert->execute([$otherSchoolId, 'Other Active School', 'active', 10, 5, '2025-2026']);
$databasePdo->prepare('INSERT INTO classes (id,schoolId) VALUES (?,?)')->execute([$classId, $schoolId]);
$databasePdo->prepare('INSERT INTO student_profiles (id,userId,classId) VALUES (?,?,?)')->execute([$studentId, '50000000-0000-4000-8000-000000000001', $classId]);
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$timestamp = static fn (DateTimeImmutable $time): string => $time->format('Y-m-d H:i:s');
$openStart = $now->modify('+2 days');
$openEnd = $now->modify('+2 days +2 hours');
$policyInsert = $databasePdo->prepare('INSERT INTO activity_registration_policies VALUES (?,?,?,?,?)');
$detailInsert = $databasePdo->prepare("INSERT INTO activity_details VALUES (?, 'school_only')");
$experienceInsert = $databasePdo->prepare('INSERT INTO activity_experience_policies VALUES (?,?)');
foreach ([
    [$schoolActivityId, $schoolId, 'Published School Activity', $openStart, $openEnd, 2, 'published', $now->modify('-1 day'), $now->modify('+1 day')],
    ['10000000-0000-4000-8000-000000000002', $schoolId, 'Ongoing School Activity', $now->modify('-1 hour'), $now->modify('+1 hour'), 30, 'ongoing', $now->modify('-1 day'), $now->modify('+1 day')],
    ['10000000-0000-4000-8000-000000000003', $schoolId, 'Completed School Activity', $now->modify('-3 days'), $now->modify('-2 days'), 30, 'completed', $now->modify('-7 days'), $now->modify('-4 days')],
    ['10000000-0000-4000-8000-000000000004', $schoolId, 'Closed School Activity', $openStart, $openEnd, 30, 'published', $now->modify('-2 days'), $now->modify('-1 second')],
    ['10000000-0000-4000-8000-000000000005', $schoolId, 'Full School Activity', $openStart, $openEnd, 1, 'published', $now->modify('-1 day'), $now->modify('+1 day')],
    ['10000000-0000-4000-8000-000000000006', $otherSchoolId, 'Other School Activity', $openStart, $openEnd, 30, 'published', $now->modify('-1 day'), $now->modify('+1 day')],
] as [$id, $activitySchoolId, $title, $startAt, $endAt, $capacity, $status, $opensAt, $closesAt]) {
    $activityInsert->execute([$id, $activitySchoolId, $teacherId, $title, 'career_technical', $timestamp($startAt), $timestamp($endAt), $capacity, $status, $timestamp($now->modify('-7 days'))]);
    $detailInsert->execute([$id]);
    $policyInsert->execute([$id, $timestamp($opensAt), $timestamp($closesAt), $timestamp($closesAt), 'automatic']);
    $experienceInsert->execute([$id, 3.0]);
}
$databasePdo->prepare("INSERT INTO activity_registrations (id,activityId,studentId,status) VALUES (?,?,?, 'approved')")->execute([
    '60000000-0000-4000-8000-000000000001', '10000000-0000-4000-8000-000000000005', '70000000-0000-4000-8000-000000000001',
]);
learner_configure_data([
    'source' => 'database',
    'pdo' => $databasePdo,
    'student_id' => $studentId,
]);

require_once $providerPath;

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}

if (!function_exists('learner_escape')) {
    function learner_escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

require_once $root . '/app/learner/includes/icons.php';

function render_ecosystem_template(string $path, array $variables): string
{
    $source = (string) file_get_contents($path);
    $bodyStart = strpos($source, '?>');
    assert_true($bodyStart !== false, "production template has an HTML body: {$path}");
    $template = substr($source, $bodyStart + 2);
    $template = preg_replace(
        "~<\\?php include __DIR__ \\. '/includes/(?:sidebar|header)\\.php'; \\?>~",
        '',
        $template
    );
    assert_true(is_string($template), "production template can be prepared: {$path}");

    extract($variables, EXTR_SKIP);
    set_error_handler(
        static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
    );
    ob_start();
    try {
        eval('?>' . $template);
        return (string) ob_get_clean();
    } finally {
        restore_error_handler();
    }
}

function partner_template_variables(array $partner, bool $isDatabaseSource): array
{
    $isEnterprise = ($partner['type'] ?? '') === 'enterprise';
    $partnerHasSchoolType = learner_ecosystem_partner_has_value($partner, 'school_type');
    $partnerWebsiteUrl = learner_ecosystem_partner_has_value($partner, 'website')
        ? learner_ecosystem_http_url($partner['website'] ?? null)
        : null;

    return [
        'partner' => $partner,
        'partnerOpportunities' => [],
        'schoolActivities' => [],
        'isEnterprise' => $isEnterprise,
        'isDatabaseSource' => $isDatabaseSource,
        'partnerVerificationStatus' => (string) ($partner['verification_status'] ?? ''),
        'partnerHasDescription' => learner_ecosystem_partner_has_value($partner, 'description'),
        'partnerHasLocation' => learner_ecosystem_partner_has_value($partner, 'location'),
        'partnerHasIndustry' => learner_ecosystem_partner_has_value($partner, 'industry'),
        'partnerHasSchoolType' => $partnerHasSchoolType,
        'partnerHasSize' => learner_ecosystem_partner_has_value($partner, 'size'),
        'partnerHasFounded' => learner_ecosystem_partner_has_value($partner, 'founded'),
        'partnerHasOpportunityCount' => learner_ecosystem_partner_has_value($partner, 'opportunity_count'),
        'partnerHasAddress' => learner_ecosystem_partner_has_value($partner, 'address'),
        'partnerHasEmail' => learner_ecosystem_partner_has_value($partner, 'email'),
        'partnerHasPhone' => learner_ecosystem_partner_has_value($partner, 'phone'),
        'partnerHasLevel' => learner_ecosystem_partner_has_value($partner, 'level'),
        'partnerHasAcademicYear' => learner_ecosystem_partner_has_value($partner, 'academic_year'),
        'partnerHasStudentCount' => learner_ecosystem_partner_has_value($partner, 'student_count'),
        'partnerHasTeacherCount' => learner_ecosystem_partner_has_value($partner, 'teacher_count'),
        'partnerHighlights' => learner_ecosystem_partner_list($partner, 'highlights'),
        'partnerPrograms' => learner_ecosystem_partner_list($partner, 'programs'),
        'partnerFacilities' => learner_ecosystem_partner_list($partner, 'facilities'),
        'partnerWebsiteUrl' => $partnerWebsiteUrl,
        'partnerTypeLabel' => $isEnterprise
            ? 'Doanh nghiệp'
            : ($partnerHasSchoolType ? trim((string) $partner['school_type']) : 'Trường học'),
    ];
}

$includedFiles = array_map(
    static fn (string $path): string => str_replace('\\', '/', $path),
    get_included_files()
);
$normalizedEnterpriseFixturePath = str_replace('\\', '/', $enterpriseFixturePath);

assert_true(
    !in_array($normalizedEnterpriseFixturePath, $includedFiles, true),
    'database mode does not load the enterprise mock fixture provider'
);
assert_true(
    learner_ecosystem_repository() instanceof \TalentHub\Learner\Data\Database\DatabaseEcosystemRepository,
    'database mode constructs the strict database ecosystem repository'
);
assert_true(
    learner_application_repository() instanceof \TalentHub\Learner\Data\Database\DatabaseApplicationRepository,
    'database mode constructs the strict database application repository'
);
$openSchoolActivities = learner_ecosystem_school_activities($schoolId);
assert_true(count($openSchoolActivities) === 1, 'school ecosystem exposes only an open published activity with remaining capacity');
assert_true(
    array_column($openSchoolActivities, 'id') === [$schoolActivityId],
    'school ecosystem hides ongoing, completed, closed, full, and foreign-school activities'
);
assert_true(
    count(array_filter($openSchoolActivities, static fn (array $item): bool => $item['school_id'] !== $schoolId)) === 0,
    'school activities are scoped to the current student school'
);
assert_true(learner_ecosystem_school_activities($otherSchoolId) === [], 'another school id cannot widen the scoped activity set');
$allOpenSchoolActivities = learner_ecosystem_school_activities();
assert_true(array_column($allOpenSchoolActivities, 'id') === [$schoolActivityId], 'unfiltered ecosystem activities remain scoped to the current student');
assert_true(!in_array('Other School Activity', array_column($allOpenSchoolActivities, 'title'), true), 'foreign activity title is never exposed');
assert_true(
    !in_array($normalizedEnterpriseFixturePath, array_map(
        static fn (string $path): string => str_replace('\\', '/', $path),
        get_included_files()
    ), true),
    'database repository factories do not evaluate mock providers'
);

assert_true(
    function_exists('learner_ecosystem_http_url'),
    'ecosystem exposes a shared http/https URL allowlist helper'
);
foreach (['https://partner.example/path', 'http://partner.example', '  https://partner.example/path  '] as $url) {
    assert_true(
        learner_ecosystem_http_url($url) === trim($url),
        "safe website URL is normalized and retained: {$url}"
    );
}
foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', '/relative', '//partner.example', '#', '   ', null] as $url) {
    assert_true(
        learner_ecosystem_http_url($url) === null,
        'unsafe, relative, placeholder, or empty website URL is rejected'
    );
}

assert_true(
    function_exists('learner_ecosystem_partner_has_value'),
    'ecosystem exposes one source-aware optional-value predicate'
);
$databaseSchool = \TalentHub\Learner\Data\ReadModel\EcosystemReadModel::partner([
    'id' => '00000000-0000-4000-8000-000000000010',
    'id_origin' => 'database',
    'type' => 'school',
    'name' => 'Trường dữ liệu thật',
    'status' => 'active',
]);
foreach (['school_type', 'description', 'location', 'programs', 'facilities', 'opportunity_count'] as $field) {
    assert_true(
        !learner_ecosystem_partner_has_value($databaseSchool, $field),
        "database school suppresses source-generated optional field: {$field}"
    );
}
assert_true(learner_ecosystem_partner_has_value($databaseSchool, 'name'), 'database school retains its real name');

$databaseEnterprise = \TalentHub\Learner\Data\ReadModel\EcosystemReadModel::partner([
    'id' => '00000000-0000-4000-8000-000000000020',
    'id_origin' => 'database',
    'type' => 'enterprise',
    'name' => 'Doanh nghiệp dữ liệu thật',
    'status' => 'active',
    'description' => 'Thông tin giới thiệu chưa có trong schema hiện tại.',
    'website' => 'javascript:alert(document.domain)',
    'address' => '   ',
    'email' => "\t",
    'phone' => "\n",
]);
foreach (['description', 'website', 'address', 'email', 'phone', 'size', 'founded', 'highlights', 'opportunity_count'] as $field) {
    assert_true(
        !learner_ecosystem_partner_has_value($databaseEnterprise, $field),
        "database enterprise suppresses unsafe, blank, placeholder, or synthetic field: {$field}"
    );
}

learner_configure_data([
    'source' => 'mock',
    'pdo' => null,
    'student_id' => null,
]);

$enterprises = learner_ecosystem_enterprises();
$schools = learner_ecosystem_schools();
$opportunities = learner_ecosystem_opportunities();
$applications = learner_ecosystem_applications();

assert_true(learner_ecosystem_school_activities() === [], 'mock mode keeps school activities empty without a database-scoped student');
assert_true(count($enterprises) >= 1, 'at least one enterprise is available');
assert_true(count($schools) >= 3, 'school demo data is available');
assert_true(count($applications) >= 3, 'application demo states are available');
assert_true(
    in_array($normalizedEnterpriseFixturePath, array_map(
        static fn (string $path): string => str_replace('\\', '/', $path),
        get_included_files()
    ), true),
    'explicit mock mode still loads the legacy enterprise fixture provider lazily'
);

$fpt = learner_ecosystem_partner('enterprise', 'fpt-software');
assert_true($fpt !== null, 'FPT Software is available through the learner adapter');
assert_true(($fpt['source'] ?? '') === 'enterprise_mock', 'enterprise source remains traceable');
foreach (['description', 'website', 'address', 'email', 'phone', 'size', 'founded', 'highlights', 'opportunity_count'] as $field) {
    assert_true(
        learner_ecosystem_partner_has_value($fpt, $field),
        "explicit mock mode retains populated legacy field: {$field}"
    );
}
assert_true(
    learner_ecosystem_http_url($fpt['website']) === $fpt['website'],
    'explicit mock mode retains its safe legacy website URL'
);

$statuses = [];
foreach ($opportunities as $opportunity) {
    $statuses[] = $opportunity['status'] ?? '';
    assert_true(($opportunity['status'] ?? '') !== 'draft', 'draft enterprise posts are not exposed');
}

assert_true(in_array('active', $statuses, true), 'active opportunities are exposed');
assert_true(in_array('closed', $statuses, true), 'closed opportunities remain available for history');

$frontend = learner_ecosystem_opportunity('internship', 1);
assert_true($frontend !== null, 'enterprise internship id is preserved');
assert_true(($frontend['partner_id'] ?? '') === 'fpt-software', 'internship maps to its enterprise');
assert_true(($frontend['source'] ?? '') === 'enterprise_mock', 'internship source remains traceable');
assert_true(($frontend['title'] ?? '') === 'Thực tập sinh Frontend Developer (React / TypeScript)', 'enterprise mock title is reused without mutation');

assert_true(learner_ecosystem_partner('school', 'missing-school') === null, 'unknown partner returns null');
assert_true(learner_ecosystem_opportunity('internship', 9999) === null, 'unknown opportunity returns null');

$unsafePartnerHtml = render_ecosystem_template(
    $root . '/app/learner/partner.php',
    partner_template_variables($databaseEnterprise, true)
);
assert_true(!str_contains($unsafePartnerHtml, 'javascript:'), 'production partner view drops a stored javascript website URL');
assert_true(!str_contains($unsafePartnerHtml, 'data:text/html'), 'production partner view drops a stored data URL');
assert_true(!str_contains($unsafePartnerHtml, 'Thông tin giới thiệu chưa có trong schema hiện tại.'), 'production partner view suppresses generated intro copy');
assert_true(!str_contains($unsafePartnerHtml, 'Chưa cập nhật'), 'production partner view suppresses compatibility placeholders');
assert_true(!str_contains($unsafePartnerHtml, 'Vị trí đang mở'), 'production partner view suppresses a synthetic opportunity count');

$mockPartnerHtml = render_ecosystem_template(
    $root . '/app/learner/partner.php',
    partner_template_variables($fpt, false)
);
assert_true(substr_count($mockPartnerHtml, 'https://fptsoftware.com') === 2, 'production partner view retains both safe mock website links');
assert_true(str_contains($mockPartnerHtml, '30.000+ nhân sự'), 'production partner view retains real mock optional facts');

$databaseSchools = array_map(
    static fn (int $index): array => \TalentHub\Learner\Data\ReadModel\EcosystemReadModel::partner([
        'id' => sprintf('00000000-0000-4000-8000-%012d', $index + 1),
        'type' => 'school',
        'name' => 'Trường dữ liệu thật ' . ($index + 1),
        'status' => 'active',
    ]),
    range(0, 2)
);
$ecosystemVariables = [
    'initialTab' => 'schools',
    'enterprises' => [],
    'schools' => $databaseSchools,
    'activeOpportunities' => [],
    'schoolActivities' => [],
    'activeEcosystemCount' => 0,
    'applications' => [],
    'isDatabaseSource' => true,
];
$databaseEcosystemHtml = render_ecosystem_template($root . '/app/learner/ecosystem.php', $ecosystemVariables);
assert_true(substr_count($databaseEcosystemHtml, 'learner-partner-card learner-card') === 3, 'production ecosystem template renders exactly three database school cards');
assert_true(!str_contains($databaseEcosystemHtml, 'Thông tin giới thiệu chưa có trong schema hiện tại.'), 'production school cards suppress generated intro copy');
assert_true(!str_contains($databaseEcosystemHtml, 'Chưa cập nhật'), 'production school cards suppress compatibility placeholders');
assert_true(!str_contains($databaseEcosystemHtml, 'chương trình nổi bật'), 'production school cards suppress synthetic program counts');
assert_true(str_contains($databaseEcosystemHtml, 'Chưa tìm thấy trường học phù hợp'), 'populated school collection keeps filter-empty copy');
assert_true(!str_contains($databaseEcosystemHtml, 'Chưa có trường học đang hoạt động'), 'populated school collection does not claim a source-empty state');

$ecosystemVariables['schools'] = [];
$emptyDatabaseEcosystemHtml = render_ecosystem_template($root . '/app/learner/ecosystem.php', $ecosystemVariables);
assert_true(str_contains($emptyDatabaseEcosystemHtml, 'Chưa có trường học đang hoạt động'), 'empty database school collection renders authoritative source-empty copy');
assert_true(!str_contains($emptyDatabaseEcosystemHtml, 'Chưa tìm thấy trường học phù hợp'), 'database school source-empty omits filter advice');

$populatedDatabaseSchool = \TalentHub\Learner\Data\ReadModel\EcosystemReadModel::partner([
    'id' => $schoolId,
    'id_origin' => 'database',
    'type' => 'school',
    'name' => 'THPT Nguyễn Trãi',
    'status' => 'active',
    'address' => '12 Sư Vạn Hạnh',
    'phone' => '028-3863-1234',
    'email' => 'school@example.test',
    'website' => 'https://school.example.test',
    'level' => 'Trung học Phổ thông',
    'academic_year' => '2025 - 2026',
    'student_count' => 11,
    'teacher_count' => 6,
]);
$schoolPartnerVariables = partner_template_variables($populatedDatabaseSchool, true);
$schoolPartnerVariables['schoolActivities'] = $openSchoolActivities;
$schoolPartnerHtml = render_ecosystem_template($root . '/app/learner/partner.php', $schoolPartnerVariables);
assert_true(str_contains($schoolPartnerHtml, '12 Sư Vạn Hạnh'), 'school detail renders database address');
assert_true(str_contains($schoolPartnerHtml, 'school@example.test'), 'school detail renders database email');
assert_true(str_contains($schoolPartnerHtml, '2025 - 2026'), 'school detail renders database academic year');
assert_true(str_contains($schoolPartnerHtml, 'activity-detail.php?id=' . $schoolActivityId), 'school activity uses the activity route');
assert_true(!str_contains($schoolPartnerHtml, 'opportunity.php?type=activity'), 'school activity never uses internship application route');

$ecosystemVariables['schools'] = [$populatedDatabaseSchool];
$ecosystemVariables['schoolActivities'] = [$openSchoolActivities[0]];
$ecosystemVariables['activeEcosystemCount'] = 1;
$schoolActivityEcosystemHtml = render_ecosystem_template($root . '/app/learner/ecosystem.php', $ecosystemVariables);
assert_true(!str_contains($schoolActivityEcosystemHtml, 'data-ecosystem-item-type="school-activity"'), 'ecosystem opportunities exclude school activity items');
assert_true(!str_contains($schoolActivityEcosystemHtml, 'activity-detail.php?id=' . $schoolActivityId), 'ecosystem opportunities never link to school QR activities');

echo "learner_ecosystem_data_test: OK\n";
