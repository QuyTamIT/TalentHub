<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$pdo = (new Connection(require dirname(__DIR__) . '/config/database.php'))->connect();
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

$schoolId = '0f27d873-3c2e-44b8-9e28-9c3aa4f2bed2';
$teacherId = '25000000-0000-4000-8000-000000002301';
$teacherStmt = $pdo->prepare('SELECT userId FROM teacher_profiles WHERE id = ?');
$teacherStmt->execute([$teacherId]);
$approverUserId = (string) ($teacherStmt->fetchColumn() ?: '');
if ($approverUserId === '') {
    fwrite(STDERR, "Teacher userId not found\n");
    exit(1);
}
$activityId = '25000000-0000-4000-8000-000000007099';
$now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
$start = (new DateTimeImmutable('+14 days', new DateTimeZone('UTC')))->setTime(8, 0);
$end = $start->modify('+8 hours');
$regDeadline = $start->modify('-2 days');
$cancelDeadline = $start->modify('-1 day');
$deadlineAt = $end->format('Y-m-d H:i:s');

$skillCodes = [
    'sql' => 'SQL',
    'react' => 'React',
    'nodejs' => 'Node.js',
    'ui_ux_design' => 'Thiết kế UI/UX',
    'teamwork' => 'Làm việc nhóm',
    'problem_solving' => 'Giải quyết vấn đề',
    'git' => 'Git',
    'python' => 'Python',
];

$skillIds = [];
$placeholders = implode(',', array_fill(0, count($skillCodes), '?'));
$stmt = $pdo->prepare("SELECT id, code FROM skills WHERE status='active' AND LOWER(code) IN ({$placeholders})");
$stmt->execute(array_keys($skillCodes));
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $skillIds[strtolower((string) $row['code'])] = (string) $row['id'];
}
$missing = array_diff(array_keys($skillCodes), array_keys($skillIds));
if ($missing !== []) {
    fwrite(STDERR, 'Missing skills: ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

$title = 'Full-stack Sprint: React, Node.js & SQL cho hồ sơ nghề';
$summary = 'Workshop thực chiến giúp bạn bổ sung SQL, React, Node.js, UI/UX, Git, Python cùng kỹ năng teamwork và problem solving.';
$description = 'Trong một sprint ngắn, nhóm học viên xây dựng mini web app end-to-end: thiết kế UI/UX, lập trình frontend React, API Node.js, truy vấn SQL, quản lý mã nguồn bằng Git và xử lý bài toán thực tế bằng Python. Kết thúc buổi, mỗi nhóm trình bày sản phẩm và phản hồi cải thiện hồ sơ kỹ năng.';
$highlights = json_encode([
    'Thiết kế wireframe UI/UX và luồng người dùng',
    'Xây dựng giao diện React và API Node.js',
    'Thiết kế schema SQL, thao tác CRUD',
    'Làm việc nhóm với Git và giải quyết sự cố kỹ thuật',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$skillTags = json_encode(array_values($skillCodes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$benefits = json_encode([
    'Bổ sung kỹ năng còn thiếu trên hồ sơ ứng tuyển',
    'Có sản phẩm demo để đưa vào portfolio',
    'Nhận phản hồi trực tiếp từ giảng viên',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$pdo->beginTransaction();
try {
    $pdo->prepare(
        "INSERT INTO activities (
            id, schoolId, createdByTeacherId, title, category, startAt, endAt,
            registration_deadline, cancel_deadline, capacity, status, visibility,
            approvalStatus, approvalRequestedAt, approvedAt, approvedBy, createdAt, updatedAt
         ) VALUES (
            ?, ?, ?, ?, 'career_technical', ?, ?,
            ?, ?, 40, 'published', 'school_only',
            'approved', ?, ?, ?, ?, ?
         )
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            category = VALUES(category),
            startAt = VALUES(startAt),
            endAt = VALUES(endAt),
            registration_deadline = VALUES(registration_deadline),
            cancel_deadline = VALUES(cancel_deadline),
            status = 'published',
            approvalStatus = 'approved',
            updatedAt = VALUES(updatedAt)"
    )->execute([
        $activityId,
        $schoolId,
        $teacherId,
        $title,
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $regDeadline->format('Y-m-d H:i:s'),
        $cancelDeadline->format('Y-m-d H:i:s'),
        $now,
        $now,
        $approverUserId,
        $now,
        $now,
    ]);

    $pdo->prepare(
        "INSERT INTO activity_details (
            activityId, responsibleTeacherId, audienceScope, displayCategory, filterCategory,
            summary, description, experienceHighlights, skillTags, eligibilityRules, benefitItems,
            locationName, locationAddress, deliveryMode, onlineMeetingUrl,
            organizerName, organizerContact, organizerEmail, organizerPhone,
            coverImageUrl, coverImageAlt, feeAmount, currency, targetAudience, certificateLabel,
            createdAt, updatedAt
         ) VALUES (
            ?, ?, 'school_only', 'Kỹ thuật', 'career_technical',
            ?, ?, ?, '[]', ?, ?,
            'Phòng Lab Phần mềm BTEC', '160 Nguyễn Văn Cừ nối dài, Cần Thơ', 'in_person', NULL,
            'BTEC FPT Cần Thơ', 'Ban Công tác Sinh viên', 'btec.cantho@talenthub.local', '0292 7300 558',
            '/app/learner/assets/activities/covers/default.webp', ?, 0, 'VND',
            'Sinh viên BTEC FPT Cần Thơ', 'Chứng nhận tham gia Full-stack Sprint',
            ?, ?
         )
         ON DUPLICATE KEY UPDATE
            summary = VALUES(summary),
            description = VALUES(description),
            experienceHighlights = VALUES(experienceHighlights),
            skillTags = VALUES(skillTags),
            benefitItems = VALUES(benefitItems),
            updatedAt = VALUES(updatedAt)"
    )->execute([
        $activityId,
        $teacherId,
        $summary,
        $description,
        $highlights,
        $benefits,
        $skillTags,
        $title,
        $now,
        $now,
    ]);

    $pdo->prepare('UPDATE activity_details SET skillTags = ? WHERE activityId = ?')->execute([$skillTags, $activityId]);

    $pdo->prepare('DELETE FROM activity_skill_tags WHERE activityId = ?')->execute([$activityId]);
    $insTag = $pdo->prepare('INSERT INTO activity_skill_tags (id, activityId, skillId, createdAt) VALUES (?, ?, ?, ?)');
    foreach ($skillIds as $code => $skillId) {
        $insTag->execute([Uuid::v4(), $activityId, $skillId, $now]);
    }

    $requiredSkills = [];
    $learningOutcomes = [];
    foreach ($skillCodes as $code => $label) {
        $requiredSkills[] = ['code' => $code, 'minimum_score' => 40, 'label' => $label];
        $learningOutcomes[] = ['code' => $code, 'label' => $label];
    }

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
    $pdo->prepare(
        "INSERT INTO learner_ai_catalog_items (
            catalog_id, item_type, category, title, summary, publish_status, deadline_at,
            eligibility_json, capacity, enrolled_count, url, action_json, school_id, tenant_id,
            updated_at, provider_name, location, difficulty,
            required_skills_json, learning_outcomes_json, education_bands_json
         ) VALUES (
            :catalog_id, 'workshop', 'career_technical', :title, :summary, 'published', :deadline_at,
            '{}', 40, 0, :url, :action_json, :school_id, :tenant_id,
            :updated_at, :provider_name, :location, 'introductory',
            :required_skills_json, :learning_outcomes_json, :education_bands_json
         )
         ON DUPLICATE KEY UPDATE
            item_type = VALUES(item_type),
            category = VALUES(category),
            title = VALUES(title),
            summary = VALUES(summary),
            publish_status = 'published',
            deadline_at = VALUES(deadline_at),
            eligibility_json = '{}',
            capacity = VALUES(capacity),
            enrolled_count = VALUES(enrolled_count),
            url = VALUES(url),
            action_json = VALUES(action_json),
            school_id = VALUES(school_id),
            tenant_id = VALUES(tenant_id),
            updated_at = VALUES(updated_at),
            provider_name = VALUES(provider_name),
            location = VALUES(location),
            difficulty = VALUES(difficulty),
            required_skills_json = VALUES(required_skills_json),
            learning_outcomes_json = VALUES(learning_outcomes_json),
            education_bands_json = VALUES(education_bands_json)"
    )->execute([
        'catalog_id' => $activityId,
        'title' => $title,
        'summary' => $summary,
        'deadline_at' => $deadlineAt,
        'url' => '/app/learner/activity-detail.php?id=' . $activityId,
        'action_json' => json_encode(['type' => 'view_activity', 'activity_id' => $activityId], $jsonFlags),
        'school_id' => $schoolId,
        'tenant_id' => $schoolId,
        'updated_at' => $now,
        'provider_name' => 'BTEC FPT Cần Thơ',
        'location' => 'Phòng Lab Phần mềm BTEC',
        'required_skills_json' => json_encode($requiredSkills, $jsonFlags),
        'learning_outcomes_json' => json_encode($learningOutcomes, $jsonFlags),
        'education_bands_json' => json_encode(['college'], $jsonFlags),
    ]);

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

echo "Created activity {$activityId}\n";
echo "Title: {$title}\n";
echo "Skills: " . implode(', ', array_keys($skillCodes)) . "\n";
echo "URL: /app/learner/activity-detail.php?id={$activityId}\n";
