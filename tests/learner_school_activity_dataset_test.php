<?php

declare(strict_types=1);

use TalentHub\Learner\Seeds\Activity\SchoolActivityCatalogDataset;

$path = dirname(__DIR__) . '/Database/seeds/learner/Activity/SchoolActivityCatalogDataset.php';
if (!is_file($path)) {
    fwrite(STDERR, "learner_school_activity_dataset_test: RED\n- Task 5 dataset class is missing.\n");
    exit(1);
}

require_once $path;

/** @var list<string> $failures */
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$records = SchoolActivityCatalogDataset::records();
$assert(count($records) === 17, 'Dataset declares exactly 17 activity metadata records.');

$expected = [
    ['10000000-0000-4000-8000-000000000001', 'TalentHub Test School', '00000000-0000-4000-8000-000000000302', 'Workshop Lập trình Python Ứng dụng', 'career_technical', 'published', 25, '10000000-0000-4000-8000-000000000022', 'automatic'],
    ['10000000-0000-4000-8000-000000000001', 'TalentHub Test School', '31000000-0000-4000-8000-000000000001', 'STEM Robotics: Chế tạo Robot Tự hành', 'career_technical', 'published', 30, '10000000-0000-4000-8000-000000000022', 'automatic'],
    ['10000000-0000-4000-8000-000000000001', 'TalentHub Test School', '31000000-0000-4000-8000-000000000002', 'Digital Marketing cho Dự án Học đường', 'career_business', 'published', 30, '10000000-0000-4000-8000-000000000022', 'automatic'],
    ['10000000-0000-4000-8000-000000000001', 'TalentHub Test School', '31000000-0000-4000-8000-000000000003', 'Creative Studio: Thiết kế Thương hiệu Cá nhân', 'career_arts', 'published', 25, '10000000-0000-4000-8000-000000000022', 'automatic'],
    ['10000000-0000-4000-8000-000000000001', 'TalentHub Test School', '31000000-0000-4000-8000-000000000004', 'Dự án Cộng đồng Trường học Xanh', 'career_sports_academic', 'published', 35, '10000000-0000-4000-8000-000000000022', 'teacher_review'],
    ['20000000-0000-4000-8000-000000000001', 'THPT Nguyễn Trãi', '21000000-04ed-44b5-82fd-0db8f8fd3b05', 'Dự án Robot cứu hộ', 'career_technical', 'completed', 30, '20000000-0000-4000-8000-000000000050', null],
    ['20000000-0000-4000-8000-000000000001', 'THPT Nguyễn Trãi', '21000000-8e2d-4dae-8d47-ea4ac11c3dc3', 'Thử thách Doanh nhân trẻ', 'career_business', 'published', 30, '20000000-0000-4000-8000-000000000052', 'automatic'],
    ['20000000-0000-4000-8000-000000000001', 'THPT Nguyễn Trãi', '31000000-0000-4000-8000-000000000005', 'Python & Robot Lab Nguyễn Trãi', 'career_technical', 'published', 30, '20000000-0000-4000-8000-000000000052', 'automatic'],
    ['20000000-0000-4000-8000-000000000001', 'THPT Nguyễn Trãi', '31000000-0000-4000-8000-000000000006', 'Thiết kế Poster Truyền thông Học đường', 'career_arts', 'published', 25, '20000000-0000-4000-8000-000000000052', 'automatic'],
    ['20000000-0000-4000-8000-000000000001', 'THPT Nguyễn Trãi', '31000000-0000-4000-8000-000000000007', 'Chiến dịch Xanh hóa Sân trường', 'career_sports_academic', 'published', 35, '20000000-0000-4000-8000-000000000052', 'automatic'],
    ['20000000-0000-4000-8000-000000000001', 'THPT Nguyễn Trãi', '31000000-0000-4000-8000-000000000008', 'Hùng biện Ý tưởng Khởi nghiệp Trẻ', 'career_business', 'published', 30, '20000000-0000-4000-8000-000000000052', 'teacher_review'],
    ['22000000-b512-4ede-852b-f4a508f3e837', 'Đại học FPT', '22000000-e945-49ac-857c-af53ffef54f0', 'FPTU Hackathon vì cộng đồng', 'career_technical', 'completed', 30, '22000000-0b50-4a89-89bc-52db15918c03', null],
    ['22000000-b512-4ede-852b-f4a508f3e837', 'Đại học FPT', '22000000-b817-48d3-8ab2-6b7dc54cd16e', 'FPTU Music Studio Showcase', 'career_arts', 'published', 30, '22000000-dc34-49ed-81d4-78446b313553', 'automatic'],
    ['22000000-b512-4ede-852b-f4a508f3e837', 'Đại học FPT', '31000000-0000-4000-8000-000000000009', 'AI Hacklab: Ứng dụng Trí tuệ Nhân tạo', 'career_technical', 'published', 30, '22000000-dc34-49ed-81d4-78446b313553', 'automatic'],
    ['22000000-b512-4ede-852b-f4a508f3e837', 'Đại học FPT', '31000000-0000-4000-8000-000000000010', 'Product Sprint: Xây dựng Sản phẩm Số', 'career_business', 'published', 30, '22000000-dc34-49ed-81d4-78446b313553', 'automatic'],
    ['22000000-b512-4ede-852b-f4a508f3e837', 'Đại học FPT', '31000000-0000-4000-8000-000000000011', 'Green Campus: Sáng kiến Bền vững', 'career_sports_academic', 'published', 35, '22000000-dc34-49ed-81d4-78446b313553', 'automatic'],
    ['22000000-b512-4ede-852b-f4a508f3e837', 'Đại học FPT', '31000000-0000-4000-8000-000000000012', 'Startup Demo Day FPT University', 'career_business', 'published', 40, '22000000-dc34-49ed-81d4-78446b313553', 'teacher_review'],
];

$byId = [];
foreach ($records as $record) {
    $id = $record['activity']['id'] ?? null;
    if (is_string($id)) {
        $byId[$id] = $record;
    }
}

$categoryLabels = [
    'career_technical' => 'Kỹ thuật',
    'career_business' => 'Kinh doanh',
    'career_arts' => 'Sáng tạo',
    'career_sports_academic' => 'Cộng đồng',
];
$legacyDurations = [
    '00000000-0000-4000-8000-000000000302' => 8.0,
    '21000000-04ed-44b5-82fd-0db8f8fd3b05' => 33.0,
    '21000000-8e2d-4dae-8d47-ea4ac11c3dc3' => 33.0,
    '22000000-e945-49ac-857c-af53ffef54f0' => 57.0,
    '22000000-b817-48d3-8ab2-6b7dc54cd16e' => 9.0,
];

foreach ($expected as [$schoolId, $schoolName, $id, $title, $category, $status, $capacity, $teacherId, $approvalMode]) {
    $record = $byId[$id] ?? null;
    $assert(is_array($record), "Dataset preserves the expected activity ID {$id}.");
    if (!is_array($record)) {
        continue;
    }
    $assert(($record['school_id'] ?? null) === $schoolId && ($record['school_name'] ?? null) === $schoolName, "{$id} belongs to its exact school.");
    $activity = $record['activity'] ?? [];
    $assert(($activity['title'] ?? null) === $title && ($activity['category'] ?? null) === $category && ($activity['status'] ?? null) === $status && ($activity['capacity'] ?? null) === $capacity, "{$id} preserves its title, category, status, and capacity.");
    if (array_key_exists($id, $legacyDurations)) {
        $assert((float) ($activity['duration_hours'] ?? 0) === $legacyDurations[$id], "{$id} preserves its legacy activity duration.");
    }
    $details = $record['details'] ?? [];
    $assert(($details['responsibleTeacherId'] ?? null) === $teacherId, "{$id} references its approved responsible teacher.");
    foreach (['audienceScope', 'displayCategory', 'filterCategory', 'summary', 'description', 'experienceHighlights', 'skillTags', 'eligibilityRules', 'benefitItems', 'locationName', 'locationAddress', 'deliveryMode', 'organizerName', 'organizerContact', 'organizerEmail', 'organizerPhone', 'coverImageUrl', 'coverImageAlt', 'feeAmount', 'currency', 'targetAudience', 'certificateLabel'] as $field) {
        $assert(array_key_exists($field, $details), "{$id} declares required details field {$field}.");
    }
    $expectedLabel = $categoryLabels[$category] ?? null;
    $assert(($details['displayCategory'] ?? null) === $expectedLabel && ($details['filterCategory'] ?? null) === $expectedLabel, "{$id} exposes the exact Vietnamese category label in display and filter metadata.");
    $assert(($details['audienceScope'] ?? null) === 'school_only' && ($details['deliveryMode'] ?? null) === 'in_person' && ($details['feeAmount'] ?? null) === 0 && ($details['currency'] ?? null) === 'VND', "{$id} declares its school-only, in-person, free VND details.");
    $assert(is_string($details['coverImageUrl'] ?? null) && str_starts_with($details['coverImageUrl'], '/app/learner/assets/activities/covers/') && str_ends_with($details['coverImageUrl'], '.webp'), "{$id} uses a local WebP cover path.");
    $assert(is_string($details['coverImageAlt'] ?? null) && trim($details['coverImageAlt']) !== '', "{$id} declares descriptive cover alt text.");
    $policy = $record['policy'] ?? null;
    if ($status === 'completed') {
        $assert($policy === null, "{$id} is historical and has no registration or experience policy.");
    } else {
        $assert(is_array($policy) && ($policy['approvalMode'] ?? null) === $approvalMode, "{$id} declares its expected open approval policy.");
        $assert(is_array($policy) && (int) ($policy['registration_close_offset_hours'] ?? 0) > 0 && (float) ($policy['confirmedHours'] ?? 0) >= 0.5 && (float) ($policy['confirmedHours'] ?? 0) <= 24, "{$id} declares a valid close window and confirmed-hour policy.");
        $assert((int) ($activity['start_offset_days'] ?? 0) > 0 && (float) ($activity['duration_hours'] ?? 0) > 0, "{$id} uses future relative timing and a positive duration.");
    }
}

$oldIds = [
    '00000000-0000-4000-8000-000000000302',
    '21000000-04ed-44b5-82fd-0db8f8fd3b05',
    '21000000-8e2d-4dae-8d47-ea4ac11c3dc3',
    '22000000-e945-49ac-857c-af53ffef54f0',
    '22000000-b817-48d3-8ab2-6b7dc54cd16e',
];
$newIds = array_values(array_diff(array_keys($byId), $oldIds));
$assert(count($newIds) === 12 && count(array_unique($newIds)) === 12, 'Dataset defines exactly 12 unique new activity IDs without colliding with preserved IDs.');
foreach ($newIds as $id) {
    $assert((bool) preg_match('/^31000000-[0-9a-f]{4}-4[0-9a-f]{3}-8[0-9a-f]{3}-[0-9a-f]{12}$/D', $id), "New ID {$id} is a stable RFC 4122 version-4 UUID in the reserved namespace.");
}

$open = array_values(array_filter($records, static fn (array $record): bool => ($record['activity']['status'] ?? null) !== 'completed'));
$history = array_values(array_filter($records, static fn (array $record): bool => ($record['activity']['status'] ?? null) === 'completed'));
$assert(count($open) === 15 && count($history) === 2, 'Dataset contains 15 open activities and 2 historical completed activities.');
foreach (['TalentHub Test School', 'THPT Nguyễn Trãi', 'Đại học FPT'] as $schoolName) {
    $schoolOpen = array_values(array_filter($open, static fn (array $record): bool => ($record['school_name'] ?? null) === $schoolName));
    $automatic = array_filter($schoolOpen, static fn (array $record): bool => ($record['policy']['approvalMode'] ?? null) === 'automatic');
    $review = array_filter($schoolOpen, static fn (array $record): bool => ($record['policy']['approvalMode'] ?? null) === 'teacher_review');
    $assert(count($schoolOpen) === 5 && count($automatic) === 4 && count($review) === 1, "{$schoolName} has five open activities: four automatic and one teacher-review.");
}

foreach ($records as $record) {
    $details = $record['details'];
    $schoolName = $record['school_name'];
    if ($schoolName === 'THPT Nguyễn Trãi') {
        $assert(($details['organizerEmail'] ?? null) === 'c3-nguyentrai@hcm.edu.vn' && ($details['organizerPhone'] ?? null) === '028-3863-1234' && ($details['organizerContact'] ?? null) === 'Liên hệ THPT Nguyễn Trãi: c3-nguyentrai@hcm.edu.vn, 028-3863-1234', 'Nguyễn Trãi uses its verified school contact only.');
    } else {
        $assert(($details['organizerEmail'] ?? null) === null && ($details['organizerPhone'] ?? null) === null && ($details['organizerContact'] ?? null) === 'Liên hệ đơn vị tổ chức', "{$schoolName} exposes no invented or demo contact details.");
    }
}

$requiredStringDetails = [
    'audienceScope', 'displayCategory', 'filterCategory', 'summary', 'description',
    'locationName', 'locationAddress', 'deliveryMode', 'organizerName', 'organizerContact',
    'coverImageUrl', 'coverImageAlt', 'currency', 'targetAudience', 'certificateLabel',
];
$requiredListDetails = ['experienceHighlights', 'skillTags', 'eligibilityRules', 'benefitItems'];
$nonEmptyStringList = static function (mixed $value): bool {
    if (!is_array($value) || $value === []) {
        return false;
    }
    foreach ($value as $item) {
        if (!is_string($item) || trim($item) === '') {
            return false;
        }
    }
    return true;
};
foreach ($records as $record) {
    $id = (string) ($record['activity']['id'] ?? 'unknown');
    $details = $record['details'] ?? [];
    foreach ($requiredStringDetails as $field) {
        $assert(is_string($details[$field] ?? null) && trim($details[$field]) !== '', "{$id} has non-empty string metadata {$field}.");
    }
    foreach ($requiredListDetails as $field) {
        $value = $details[$field] ?? null;
        $assert($nonEmptyStringList($value), "{$id} has a non-empty string-list {$field}.");
    }
}

$policies = array_values(array_filter($records, static fn (array $record): bool => is_array($record['policy'] ?? null)));
$confirmedHours = array_values(array_filter($policies, static fn (array $record): bool => array_key_exists('confirmedHours', $record['policy']) && $record['policy']['confirmedHours'] !== null));
$assert(count($policies) === 15, 'Exactly 15 activity records declare non-null policies.');
$assert(count($confirmedHours) === 15, 'Exactly 15 policies declare confirmed hours.');

$utcNow = new DateTimeImmutable('2026-08-25 00:00:00', new DateTimeZone('UTC'));
foreach ($records as $record) {
    $id = (string) ($record['activity']['id'] ?? 'unknown');
    $activity = $record['activity'] ?? [];
    $startOffset = $activity['start_offset_days'] ?? null;
    $durationHours = $activity['duration_hours'] ?? null;
    $assert(is_int($startOffset) && is_numeric($durationHours) && (float) $durationHours > 0, "{$id} has numeric offset and positive duration metadata.");
    if (!is_int($startOffset) || !is_numeric($durationHours) || (float) $durationHours <= 0) {
        continue;
    }
    $startAt = $utcNow->modify(sprintf('%+d days', $startOffset));
    $endAt = $startAt->modify('+' . (int) round((float) $durationHours * 3600) . ' seconds');
    $assert($endAt > $startAt, "{$id} has an end time after its start time.");

    if (($activity['status'] ?? null) === 'completed') {
        $assert($startAt < $utcNow && $endAt < $utcNow, "{$id} completed window is wholly in the past.");
        continue;
    }

    $policy = $record['policy'] ?? null;
    if (!is_array($policy)) {
        continue;
    }
    $openOffset = $policy['registration_open_offset_days'] ?? null;
    $closeOffset = $policy['registration_close_offset_hours'] ?? null;
    $assert(is_int($openOffset) && is_int($closeOffset) && $closeOffset > 0, "{$id} has integer registration window offsets.");
    if (!is_int($openOffset) || !is_int($closeOffset) || $closeOffset <= 0) {
        continue;
    }
    $registrationOpensAt = $utcNow->modify(sprintf('%+d days', $openOffset));
    $registrationClosesAt = $startAt->modify('-' . $closeOffset . ' hours');
    $assert($registrationOpensAt <= $utcNow, "{$id} registration is open by the fixed UTC reference time.");
    $assert($registrationClosesAt > $utcNow && $registrationClosesAt < $startAt, "{$id} registration closes after now and before the activity starts.");
}

$openCoverUrls = array_map(static fn (array $record): mixed => $record['details']['coverImageUrl'] ?? null, $open);
$assert(count($openCoverUrls) === 15 && count(array_unique($openCoverUrls, SORT_STRING)) === 15, 'The 15 open activities reference 15 distinct covers.');

$projectRoot = dirname(__DIR__);
$coversRoot = $projectRoot . '/app/learner/assets/activities/covers';
$coversRootRealPath = realpath($coversRoot);
$assert(is_string($coversRootRealPath), 'The local activity-covers directory exists.');
$checkedCoverUrls = [];
$coverHashes = [];
foreach ($records as $record) {
    $id = (string) ($record['activity']['id'] ?? 'unknown');
    $coverUrl = $record['details']['coverImageUrl'] ?? null;
    $assert(is_string($coverUrl) && (bool) preg_match('#^/app/learner/assets/activities/covers/[a-z0-9]+(?:-[a-z0-9]+)*\\.webp$#D', $coverUrl), "{$id} cover is a local kebab-case WebP reference.");
    if (!is_string($coverUrl) || isset($checkedCoverUrls[$coverUrl])) {
        continue;
    }
    $checkedCoverUrls[$coverUrl] = true;
    $coverPath = $projectRoot . str_replace('/', DIRECTORY_SEPARATOR, $coverUrl);
    $coverRealPath = realpath($coverPath);
    $assert(is_file($coverPath), "Cover asset exists: {$coverUrl}.");
    if (!is_file($coverPath)) {
        continue;
    }
    $assert(is_string($coverRealPath) && is_string($coversRootRealPath) && str_starts_with($coverRealPath, $coversRootRealPath . DIRECTORY_SEPARATOR), "Cover asset stays inside the local covers directory: {$coverUrl}.");
    $bytes = filesize($coverPath);
    $image = getimagesize($coverPath);
    $coverHashes[] = hash_file('sha256', $coverPath);
    $assert(is_int($bytes) && $bytes >= 1024 && $bytes <= 350 * 1024, "Cover asset is between 1 KiB and 350 KiB: {$coverUrl}.");
    $assert(is_array($image) && ($image[2] ?? null) === IMAGETYPE_WEBP && ($image[0] ?? 0) >= 600 && ($image[1] ?? 0) >= 400, "Cover asset is a WEBP image at least 600x400: {$coverUrl}.");
}
$assert(count($coverHashes) === 15 && count(array_unique($coverHashes, SORT_STRING)) === 15, 'The 15 cover files contain 15 distinct images.');

$illustrationsRoot = $projectRoot . '/app/learner/assets/activities/illustrations';
$illustrationsRootRealPath = realpath($illustrationsRoot);
$heroPaths = [
    '/app/learner/assets/activities/illustrations/hero-discover.svg',
    '/app/learner/assets/activities/illustrations/hero-detail.svg',
    '/app/learner/assets/activities/illustrations/hero-registered.svg',
    '/app/learner/assets/activities/illustrations/hero-history.svg',
];
$heroThemes = ['khám phá', 'chi tiết', 'đã đăng ký', 'lịch sử'];
$heroHashes = [];
foreach ($heroPaths as $heroIndex => $heroUrl) {
    $heroPath = $projectRoot . str_replace('/', DIRECTORY_SEPARATOR, $heroUrl);
    $heroRealPath = realpath($heroPath);
    $assert(is_file($heroPath) && is_readable($heroPath), "Hero illustration exists and is readable: {$heroUrl}.");
    if (!is_file($heroPath) || !is_readable($heroPath)) {
        continue;
    }
    $assert(is_string($heroRealPath) && is_string($illustrationsRootRealPath) && str_starts_with($heroRealPath, $illustrationsRootRealPath . DIRECTORY_SEPARATOR), "Hero illustration stays inside the local illustrations directory: {$heroUrl}.");
    $svg = file_get_contents($heroPath);
    $bytes = filesize($heroPath);
    $parsed = is_string($svg) ? @simplexml_load_string($svg) : false;
    $dimensions = is_string($svg) ? preg_match('/\\bviewBox=["\\\']0\\s+0\\s+(\\d+(?:\\.\\d+)?)\\s+(\\d+(?:\\.\\d+)?)["\\\']/', $svg, $matches) : false;
    $heroHashes[] = hash('sha256', (string) $svg);
    $assert(is_string($svg) && trim($svg) !== '' && is_int($bytes) && $bytes >= 512 && $bytes <= 350 * 1024, "Hero illustration has a sensible readable SVG size: {$heroUrl}.");
    $assert($parsed instanceof SimpleXMLElement && $parsed->getName() === 'svg', "Hero illustration parses as SVG: {$heroUrl}.");
    $assert($dimensions === 1 && (float) $matches[1] >= 600 && (float) $matches[2] >= 400, "Hero illustration has a sensible viewBox of at least 600x400: {$heroUrl}.");
    $assert(is_string($svg) && preg_match('/<title\\b[^>]*>[^<]*' . preg_quote($heroThemes[$heroIndex], '/') . '[^<]*<\\/title>/iu', $svg) === 1 && preg_match('/<desc\\b[^>]*>\\s*[^<]+<\\/desc>/iu', $svg) === 1, "Hero illustration declares an accessible title/description for its required theme: {$heroUrl}.");
}
$assert(count($heroHashes) === 4 && count(array_unique($heroHashes, SORT_STRING)) === 4, 'The four hero illustrations contain distinct artwork.');

if ($failures !== []) {
    fwrite(STDERR, "learner_school_activity_dataset_test: RED\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "learner_school_activity_dataset_test: OK\n";
