<?php
declare(strict_types=1);

use TalentHub\Learner\Assessment\Scoring\DiscScorer;
use TalentHub\Learner\Assessment\Scoring\HollandScorer;
use TalentHub\Learner\Assessment\Scoring\MbtiScorer;
use TalentHub\Learner\Assessment\Scoring\MultipleIntelligenceScorer;
use TalentHub\Learner\Assessment\Scoring\ScorerRegistry;
use TalentHub\Support\Uuid;

/** @var PDO $pdo */
/** @var string $schoolId */
/** @var string $now */
/** @var string $password */
/** @var array<int,array<string,mixed>> $studentIds */
/** @var array<int|string,string> $teacherIds */
/** @var list<array<string,mixed>> $classes */
/** @var array<string,array<string,mixed>> $assessmentProfiles */
/** @var string|null $only */
/** @var string $schoolAdminUserId */
/** @var array<string,string> $roles */

function phaseOk(?string $only, string $phase): bool
{
    return $only === null || $only === 'all' || $only === $phase;
}

function buildBiasedAnswers(string $code, array $questions, array $bias): array
{
    $answers = [];
    if (str_starts_with($code, 'holland_')) {
        $primary = (string) $bias['primary'];
        $secondary = $bias['secondary'] ?? [];
        foreach ($questions as $q) {
            preg_match('/\A([RIASEC])(?::([+-]))?\z/i', (string) $q['dimensionCode'], $m);
            $dim = strtoupper($m[1] ?? 'R');
            $reversed = ($m[2] ?? '+') === '-';
            $base = $dim === $primary ? 5 : (in_array($dim, $secondary, true) ? 4 : 2);
            $answers[$q['qid']] = $reversed ? (6 - $base) : $base;
        }
        return $answers;
    }
    if (str_starts_with($code, 'mbti_')) {
        $axisPrefs = $bias['axis'];
        foreach ($questions as $q) {
            preg_match('/\A(EI|SN|TF|JP):([EISNTFJP])\z/i', (string) $q['dimensionCode'], $m);
            $axis = strtoupper($m[1] ?? 'EI');
            $pole = strtoupper($m[2] ?? 'E');
            $preferred = $axisPrefs[$axis] ?? $pole;
            $answers[$q['qid']] = ($pole === $preferred) ? 5 : 2;
        }
        return $answers;
    }
    if (str_starts_with($code, 'disc_')) {
        $primary = (string) $bias['primary'];
        $secondary = $bias['secondary'] ?? [];
        foreach ($questions as $q) {
            preg_match('/\A([DISC])(?::([+-]))?\z/i', (string) $q['dimensionCode'], $m);
            $dim = strtoupper($m[1] ?? 'D');
            $reversed = ($m[2] ?? '+') === '-';
            $base = $dim === $primary ? 5 : (in_array($dim, $secondary, true) ? 4 : 2);
            $answers[$q['qid']] = $reversed ? (6 - $base) : $base;
        }
        return $answers;
    }
    $primary = (string) $bias['primary'];
    $secondary = $bias['secondary'] ?? [];
    foreach ($questions as $q) {
        preg_match('/\A(LING|LOGI|SPAT|BODY|MUSIC|INTER|INTRA|NAT)(?::([+-]))?\z/i', (string) $q['dimensionCode'], $m);
        $dim = strtoupper($m[1] ?? 'LING');
        $reversed = ($m[2] ?? '+') === '-';
        $base = $dim === $primary ? 5 : (in_array($dim, $secondary, true) ? 4 : 2);
        $answers[$q['qid']] = $reversed ? (6 - $base) : $base;
    }
    return $answers;
}

function seedStudentAssessments(PDO $pdo, string $studentId, array $profile, ScorerRegistry $registry): void
{
    $catalogs = [
        'holland_college' => $profile['holland'],
        'mbti_college' => ['axis' => $profile['mbti']],
        'disc_college' => $profile['disc'],
        'multiple_intelligence_college' => $profile['mi'],
    ];

    $attemptIds = $pdo->prepare('SELECT id FROM test_attempts WHERE studentId = ?');
    $attemptIds->execute([$studentId]);
    foreach ($attemptIds->fetchAll(PDO::FETCH_COLUMN) as $oldId) {
        $pdo->prepare('DELETE FROM learner_assessment_answers WHERE attemptId = ?')->execute([$oldId]);
        $pdo->prepare('DELETE FROM learner_assessment_attempt_metadata WHERE attemptId = ?')->execute([$oldId]);
        $pdo->prepare('DELETE FROM test_results WHERE attemptId = ?')->execute([$oldId]);
    }
    $pdo->prepare('DELETE FROM test_attempts WHERE studentId = ?')->execute([$studentId]);

    $submittedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    $startedAt = (new DateTimeImmutable($submittedAt, new DateTimeZone('UTC')))->modify('-40 minutes')->format('Y-m-d H:i:s.u');

    foreach ($catalogs as $code => $bias) {
        $meta = $pdo->query(
            "SELECT t.id AS testId, t.type, v.id AS versionId, v.version, v.scoringVersion, v.schemaHash
             FROM talent_tests t
             INNER JOIN learner_assessment_versions v ON v.testId = t.id
             WHERE t.code = " . $pdo->quote($code) . " AND v.status = 'published' LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        if (!$meta) {
            throw new RuntimeException("Missing catalog {$code}");
        }
        $questions = $pdo->query(
            "SELECT qv.questionId AS qid, qv.position, qv.dimensionCode, qv.required
             FROM learner_assessment_question_versions qv
             WHERE qv.versionId = " . $pdo->quote($meta['versionId']) . ' ORDER BY qv.position'
        )->fetchAll(PDO::FETCH_ASSOC);
        $answers = buildBiasedAnswers($code, $questions, $bias);
        $scorerQuestions = array_map(static fn (array $q): array => [
            'question_id' => $q['qid'],
            'dimension_code' => $q['dimensionCode'],
            'required' => (int) $q['required'],
        ], $questions);
        $scored = $registry->forVersion((string) $meta['scoringVersion'])->score($scorerQuestions, $answers)->toArray();
        ksort($answers, SORT_STRING);
        $inputHash = hash('sha256', json_encode([
            'assessment_version' => $meta['version'],
            'scoring_version' => $meta['scoringVersion'],
            'schema_hash' => $meta['schemaHash'],
            'answers' => $answers,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $attemptId = Uuid::v4();
        $pdo->prepare(
            "INSERT INTO test_attempts (id, testId, studentId, status, startedAt, submittedAt, createdAt, updatedAt)
             VALUES (?, ?, ?, 'submitted', ?, ?, ?, ?)"
        )->execute([$attemptId, $meta['testId'], $studentId, $startedAt, $submittedAt, $submittedAt, $submittedAt]);
        $pdo->prepare(
            "INSERT INTO learner_assessment_attempt_metadata
             (id, attemptId, versionId, status, expiresAt, submittedAt, inputHash, createdAt, updatedAt)
             VALUES (?, ?, ?, 'submitted', NULL, ?, ?, ?, ?)"
        )->execute([Uuid::v4(), $attemptId, $meta['versionId'], $submittedAt, $inputHash, $submittedAt, $submittedAt]);
        $insertAnswer = $pdo->prepare(
            'INSERT INTO learner_assessment_answers (id, attemptId, questionId, answerJson, answeredAt) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($answers as $qid => $value) {
            $insertAnswer->execute([Uuid::v4(), $attemptId, $qid, json_encode($value, JSON_THROW_ON_ERROR), $submittedAt]);
        }
        $pdo->prepare(
            'INSERT INTO test_results (id, attemptId, resultCode, summary, dimensionScoresJson, scoringVersion, createdAt)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            Uuid::v4(),
            $attemptId,
            $scored['result_code'],
            $scored['summary'],
            json_encode($scored['dimension_scores'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            $meta['scoringVersion'],
            $submittedAt,
        ]);
    }
}

function grantAiConsents(PDO $pdo, string $studentId, string $now): void
{
    $scopes = ['assessment', 'skills', 'activity', 'evaluation', 'profile_share', 'application_profile_share', 'enterprise_talent_discovery', 'enterprise_talent_contact'];
    $find = $pdo->prepare('SELECT id FROM privacy_consents WHERE studentId = ? AND scope = ? ORDER BY createdAt DESC LIMIT 1');
    $upd = $pdo->prepare('UPDATE privacy_consents SET isGranted = 1, policyVersion = \'v1\', grantedAt = ?, revokedAt = NULL WHERE id = ?');
    $ins = $pdo->prepare(
        'INSERT INTO privacy_consents (id, studentId, scope, isGranted, policyVersion, grantedAt, revokedAt, createdAt)
         VALUES (?, ?, ?, 1, \'v1\', ?, NULL, ?)'
    );
    foreach ($scopes as $scope) {
        $find->execute([$studentId, $scope]);
        $id = $find->fetchColumn();
        if (is_string($id) && $id !== '') {
            $upd->execute([$now, $id]);
            continue;
        }
        $ins->execute([Uuid::v4(), $studentId, $scope, $now, $now]);
    }
}

require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/AssessmentScorer.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/ScoringResult.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/LikertScore.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/ScorerRegistry.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/HollandScorer.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/MbtiScorer.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/DiscScorer.php';
require_once dirname(__DIR__) . '/app/learner/assessment/Scoring/MultipleIntelligenceScorer.php';

$registry = new ScorerRegistry([
    'holland-riasec-1.0' => new HollandScorer(),
    'mbti-education-1.0' => new MbtiScorer(),
    'disc-education-1.0' => new DiscScorer(),
    'multiple-intelligence-1.0' => new MultipleIntelligenceScorer(),
]);

$primaryTeacher = $teacherIds[0] ?? ($teacherIds['legacy'] ?? null);
if (!$primaryTeacher) {
    $primaryTeacher = (string) $pdo->query(
        'SELECT id FROM teacher_profiles WHERE schoolId = ' . $pdo->quote($schoolId) . ' LIMIT 1'
    )->fetchColumn();
}

try {
    $pdo->beginTransaction();

    if (phaseOk($only, 'assessments')) {
        echo "[2] Assessments 4/4 đa ngành\n";
        $fullSet = [1, 2, 4, 5, 7, 8, 10, 11, 13, 14, 16, 19, 20];
        $partialSet = [3, 9, 12, 15, 17];
        foreach ($fullSet as $n) {
            if (!isset($studentIds[$n])) {
                continue;
            }
            $track = (string) $studentIds[$n]['track'];
            seedStudentAssessments($pdo, (string) $studentIds[$n]['id'], $assessmentProfiles[$track], $registry);
            grantAiConsents($pdo, (string) $studentIds[$n]['id'], $now);
            echo "  4/4 {$studentIds[$n]['name']} [{$track}]\n";
        }
        foreach ($partialSet as $n) {
            if (!isset($studentIds[$n])) {
                continue;
            }
            $track = (string) $studentIds[$n]['track'];
            $profile = $assessmentProfiles[$track];
            $partialProfile = [
                'holland' => $profile['holland'],
                'mbti' => $profile['mbti'],
                'disc' => $profile['disc'],
                'mi' => $profile['mi'],
            ];
            seedStudentAssessments($pdo, (string) $studentIds[$n]['id'], $partialProfile, $registry);
            $attempts = $pdo->prepare('SELECT id FROM test_attempts WHERE studentId = ? ORDER BY createdAt DESC');
            $attempts->execute([(string) $studentIds[$n]['id']]);
            $ids = $attempts->fetchAll(PDO::FETCH_COLUMN);
            foreach (array_slice($ids, 0, 2) as $keepDelete) {
                $pdo->prepare('DELETE FROM learner_assessment_answers WHERE attemptId = ?')->execute([$keepDelete]);
                $pdo->prepare('DELETE FROM learner_assessment_attempt_metadata WHERE attemptId = ?')->execute([$keepDelete]);
                $pdo->prepare('DELETE FROM test_results WHERE attemptId = ?')->execute([$keepDelete]);
                $pdo->prepare('DELETE FROM test_attempts WHERE id = ?')->execute([$keepDelete]);
            }
            grantAiConsents($pdo, (string) $studentIds[$n]['id'], $now);
            echo "  2/4 {$studentIds[$n]['name']} [{$track}]\n";
        }
        if (isset($studentIds) === false) {
        }
        $chau = $pdo->query(
            "SELECT sp.id FROM student_profiles sp INNER JOIN users u ON u.id = sp.userId WHERE u.email = 'chau.thietke@talenthub.local' LIMIT 1"
        )->fetchColumn();
        if (is_string($chau) && $chau !== '') {
            seedStudentAssessments($pdo, $chau, $assessmentProfiles['design'], $registry);
            grantAiConsents($pdo, $chau, $now);
            echo "  4/4 Lê Minh Châu [design existing]\n";
        }
    }

    if (phaseOk($only, 'activities')) {
        echo "[3] Activities + registrations\n";
        $activityDefs = [
            ['id' => richId('7001'), 'title' => 'BTEC Hackathon AI vì cộng đồng', 'cat' => 'career_technical', 'display' => 'Kỹ thuật', 'status' => 'completed', 'start' => '-45 days', 'hours' => 48, 'skills' => ['Python', 'AI', 'Làm việc nhóm'], 'loc' => 'Innovation Lab BTEC'],
            ['id' => richId('7002'), 'title' => 'Workshop Fullstack Web thực chiến', 'cat' => 'career_technical', 'display' => 'Kỹ thuật', 'status' => 'ongoing', 'start' => '-1 days', 'hours' => 8, 'skills' => ['React', 'API', 'Git'], 'loc' => 'Phòng máy A201'],
            ['id' => richId('7003'), 'title' => 'UX Design Challenge BTEC', 'cat' => 'career_arts', 'display' => 'Sáng tạo', 'status' => 'ongoing', 'start' => '-2 days', 'hours' => 12, 'skills' => ['Figma', 'UI/UX', 'Research'], 'loc' => 'Design Studio'],
            ['id' => richId('7004'), 'title' => 'Brand Identity Sprint', 'cat' => 'career_arts', 'display' => 'Sáng tạo', 'status' => 'published', 'start' => '+10 days', 'hours' => 6, 'skills' => ['Photoshop', 'Illustrator', 'Brand'], 'loc' => 'Xưởng sáng tạo'],
            ['id' => richId('7005'), 'title' => 'Digital Marketing Bootcamp', 'cat' => 'career_business', 'display' => 'Kinh doanh', 'status' => 'completed', 'start' => '-30 days', 'hours' => 16, 'skills' => ['SEO', 'Ads', 'Content'], 'loc' => 'Hội trường B'],
            ['id' => richId('7006'), 'title' => 'Startup Demo Day Cần Thơ', 'cat' => 'career_business', 'display' => 'Kinh doanh', 'status' => 'published', 'start' => '+21 days', 'hours' => 5, 'skills' => ['Pitching', 'Khởi nghiệp'], 'loc' => 'Hội trường lớn'],
            ['id' => richId('7007'), 'title' => 'Power BI Dashboard Marathon', 'cat' => 'career_technical', 'display' => 'Dữ liệu', 'status' => 'ongoing', 'start' => '-1 days', 'hours' => 10, 'skills' => ['Power BI', 'Excel', 'Analytics'], 'loc' => 'Data Lab'],
            ['id' => richId('7008'), 'title' => 'Chuỗi cung ứng xanh Mekong', 'cat' => 'career_sports_academic', 'display' => 'Logistics', 'status' => 'published', 'start' => '+14 days', 'hours' => 8, 'skills' => ['Logistics', 'Ops', 'Bền vững'], 'loc' => 'Green Hub'],
            ['id' => richId('7009'), 'title' => 'CLB Tranh biện học thuật', 'cat' => 'career_sports_academic', 'display' => 'Kỹ năng mềm', 'status' => 'ongoing', 'start' => '-3 days', 'hours' => 3, 'skills' => ['Thuyết trình', 'Phản biện'], 'loc' => 'Phòng đa năng'],
            ['id' => richId('7010'), 'title' => 'Ngày hội việc làm BTEC × DN', 'cat' => 'career_business', 'display' => 'Kết nối DN', 'status' => 'published', 'start' => '+7 days', 'hours' => 6, 'skills' => ['Networking', 'CV', 'Interview'], 'loc' => 'Sân thể thao'],
            ['id' => richId('7011'), 'title' => 'Motion Design Showcase', 'cat' => 'career_arts', 'display' => 'Sáng tạo', 'status' => 'completed', 'start' => '-20 days', 'hours' => 4, 'skills' => ['Video', 'Storytelling'], 'loc' => 'Media Lab'],
            ['id' => richId('7012'), 'title' => 'Python & Data Wrangling Lab', 'cat' => 'career_technical', 'display' => 'Dữ liệu', 'status' => 'completed', 'start' => '-55 days', 'hours' => 8, 'skills' => ['Python', 'Pandas'], 'loc' => 'Phòng máy B102'],
            ['id' => richId('7013'), 'title' => 'Warehouse Simulation Game', 'cat' => 'career_business', 'display' => 'Logistics', 'status' => 'completed', 'start' => '-40 days', 'hours' => 6, 'skills' => ['Kho vận', 'Tối ưu'], 'loc' => 'Logistics Lab'],
            ['id' => richId('7014'), 'title' => 'Content Creator Camp', 'cat' => 'career_arts', 'display' => 'Marketing', 'status' => 'published', 'start' => '+18 days', 'hours' => 8, 'skills' => ['Content', 'Social'], 'loc' => 'Studio Media'],
            ['id' => richId('7015'), 'title' => 'Volunteer Green Campus', 'cat' => 'career_sports_academic', 'display' => 'Cộng đồng', 'status' => 'completed', 'start' => '-12 days', 'hours' => 5, 'skills' => ['Leadership', 'Community'], 'loc' => 'Khuôn viên trường'],
            ['id' => richId('7016'), 'title' => 'Interview Skills Clinic', 'cat' => 'career_business', 'display' => 'Kỹ năng nghề', 'status' => 'ongoing', 'start' => '0 days', 'hours' => 3, 'skills' => ['Interview', 'Giao tiếp'], 'loc' => 'Career Center'],
        ];

        $insAct = $pdo->prepare(
            "INSERT INTO activities (id, schoolId, createdByTeacherId, title, category, startAt, endAt, registration_deadline, cancel_deadline, capacity, status, visibility, approvalStatus, approvalRequestedAt, approvedAt, approvedBy, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 40, ?, 'school_only', 'approved', ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), category = VALUES(category), startAt = VALUES(startAt), endAt = VALUES(endAt),
               status = VALUES(status), approvalStatus = 'approved', updatedAt = VALUES(updatedAt)"
        );
        $insDetail = $pdo->prepare(
            "INSERT INTO activity_details (activityId, responsibleTeacherId, audienceScope, displayCategory, filterCategory, summary, description, experienceHighlights, skillTags, eligibilityRules, benefitItems, locationName, locationAddress, deliveryMode, onlineMeetingUrl, organizerName, organizerContact, organizerEmail, organizerPhone, coverImageUrl, coverImageAlt, feeAmount, currency, targetAudience, certificateLabel, createdAt, updatedAt)
             VALUES (?, ?, 'school_only', ?, ?, ?, ?, ?, ?, ?, ?, ?, '160 Nguyễn Văn Cừ nối dài, Cần Thơ', 'in_person', NULL, 'BTEC FPT Cần Thơ', 'Ban Công tác Sinh viên', 'btec.cantho@talenthub.local', '0292 7300 558', '/app/learner/assets/activities/covers/default.webp', ?, 0, 'VND', 'Sinh viên BTEC FPT Cần Thơ', ?, ?, ?)
             ON DUPLICATE KEY UPDATE summary = VALUES(summary), description = VALUES(description), displayCategory = VALUES(displayCategory), updatedAt = VALUES(updatedAt)"
        );

        foreach ($activityDefs as $a) {
            $start = (new DateTimeImmutable($a['start'], new DateTimeZone('UTC')))->setTime(8, 0);
            $end = $start->modify('+' . (int) $a['hours'] . ' hours');
            $regDead = $start->modify('-1 day');
            $insAct->execute([
                $a['id'], $schoolId, $primaryTeacher, $a['title'], $a['cat'],
                $start->format('Y-m-d H:i:s.u'), $end->format('Y-m-d H:i:s.u'),
                $regDead->format('Y-m-d H:i:s.u'), $regDead->format('Y-m-d H:i:s.u'),
                $a['status'], $now, $now, $schoolAdminUserId !== '' ? $schoolAdminUserId : null, $now, $now,
            ]);
            $highlights = json_encode(['Thực hành trực tiếp', 'Mentor hỗ trợ', 'Nhận minh chứng trên TalentHub'], JSON_UNESCAPED_UNICODE);
            $skills = json_encode($a['skills'], JSON_UNESCAPED_UNICODE);
            $rules = json_encode(['Sinh viên đang học tại BTEC FPT Cần Thơ'], JSON_UNESCAPED_UNICODE);
            $benefits = json_encode(['Giờ trải nghiệm', 'Chứng nhận tham gia', 'Kết nối doanh nghiệp'], JSON_UNESCAPED_UNICODE);
            $insDetail->execute([
                $a['id'], $primaryTeacher, $a['display'], $a['display'],
                "Hoạt động {$a['title']} dành cho sinh viên BTEC FPT Cần Thơ.",
                "Chương trình {$a['title']} giúp sinh viên rèn kỹ năng đa ngành và tích lũy minh chứng nghề nghiệp.",
                $highlights, $skills, $rules, $benefits, $a['loc'], $a['title'], "Chứng nhận {$a['title']}", $now, $now,
            ]);
            echo "  Activity: {$a['title']} ({$a['status']})\n";
        }

        $insReg = $pdo->prepare(
            "INSERT INTO activity_registrations (id, activityId, studentId, status, registeredAt, updatedAt, cancelledAt, cancellationReason, attendanceResolvedAt, attendanceResolutionReason)
             VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), updatedAt = VALUES(updatedAt), attendanceResolvedAt = VALUES(attendanceResolvedAt)"
        );
        $statusesByTrack = [
            'it' => ['attended', 'approved', 'attended'],
            'design' => ['attended', 'approved', 'attended'],
            'marketing' => ['attended', 'approved', 'waitlisted'],
            'business' => ['attended', 'approved', 'pending'],
            'data' => ['attended', 'approved', 'attended'],
            'logistics' => ['attended', 'approved', 'no_show'],
            'ai' => ['attended', 'attended', 'approved'],
        ];
        $actIds = array_column($activityDefs, 'id');
        foreach ($studentIds as $n => $st) {
            $track = (string) $st['track'];
            $want = $statusesByTrack[$track] ?? ['approved', 'attended'];
            foreach ($want as $i => $status) {
                $actId = $actIds[($n + $i) % count($actIds)];
                $resolved = in_array($status, ['attended', 'no_show'], true) ? $now : null;
                $reason = $status === 'attended' ? 'confirmed_attendance' : ($status === 'no_show' ? 'no_show' : null);
                $insReg->execute([richId((string) (8000 + $n * 10 + $i)), $actId, $st['id'], $status, $now, $now, $resolved, $reason]);
            }
        }
        echo "  Registrations seeded for all students\n";
    }

    if (phaseOk($only, 'portfolio')) {
        echo "[4] Portfolio: projects / certificates / badges\n";
        $projectDefs = [
            ['id' => richId('9001'), 'title' => 'Smart Campus Check-in App', 'cat' => 'technical', 'topic' => 'Web App', 'members' => [1, 2, 19], 'status' => 'completed'],
            ['id' => richId('9002'), 'title' => 'Brand Kit cho startup địa phương', 'cat' => 'creative', 'topic' => 'Brand Identity', 'members' => [4, 5, 20], 'status' => 'in_progress'],
            ['id' => richId('9003'), 'title' => 'Campaign tuyển sinh BTEC 2026', 'cat' => 'business', 'topic' => 'Digital Campaign', 'members' => [7, 8, 9], 'status' => 'completed'],
            ['id' => richId('9004'), 'title' => 'Dashboard bán hàng Mekong Mart', 'cat' => 'data', 'topic' => 'BI Dashboard', 'members' => [13, 14, 15], 'status' => 'in_progress'],
            ['id' => richId('9005'), 'title' => 'Mô phỏng kho Fulfillment Cần Thơ', 'cat' => 'operations', 'topic' => 'Supply Chain', 'members' => [16, 17, 18], 'status' => 'completed'],
            ['id' => richId('9006'), 'title' => 'MVP đặt lịch mentor doanh nghiệp', 'cat' => 'product', 'topic' => 'Startup MVP', 'members' => [10, 3, 11], 'status' => 'in_progress'],
            [
                'id' => richId('9007'),
                'title' => 'Chatbot hỗ trợ học tập nội bộ BTEC',
                'cat' => 'Công nghệ thông tin',
                'topic' => 'AI Chatbot & Web API',
                'members' => [],
                'status' => 'in_progress',
                'desc' => 'Xây dựng chatbot nội bộ hỗ trợ sinh viên tra cứu lộ trình học, lịch hoạt động và FAQ tuyển sinh. Ứng dụng NLP, REST API và dashboard quản trị cho nhà trường.',
                'fundingGoal' => 18000000.00,
                'fundingStatus' => 'open',
            ],
        ];
        $insProj = $pdo->prepare(
            "INSERT INTO projects (id, schoolId, mentorTeacherId, title, category, topic, description, fundingGoal, fundingStatus, fundingReachedAt, projectUrl, startAt, endAt, status, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), category = VALUES(category), topic = VALUES(topic), status = VALUES(status), description = VALUES(description), fundingGoal = VALUES(fundingGoal), fundingStatus = VALUES(fundingStatus), updatedAt = VALUES(updatedAt)"
        );
        $insMember = $pdo->prepare(
            "INSERT INTO project_members (id, projectId, studentId, role, contribution, status, joinedAt, leftAt, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, 'active', ?, NULL, ?, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role), contribution = VALUES(contribution), status = 'active', updatedAt = VALUES(updatedAt)"
        );
        foreach ($projectDefs as $p) {
            $start = (new DateTimeImmutable('-60 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
            $end = $p['status'] === 'completed'
                ? (new DateTimeImmutable('-5 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u')
                : null;
            $desc = (string) ($p['desc'] ?? "Dự án {$p['title']}.");
            $fundingGoal = $p['fundingGoal'] ?? null;
            $fundingStatus = (string) ($p['fundingStatus'] ?? 'not_required');
            $insProj->execute([
                $p['id'], $schoolId, $primaryTeacher, $p['title'], $p['cat'], $p['topic'],
                $desc, $fundingGoal, $fundingStatus,
                $start, $end, $p['status'], $now, $now,
            ]);
            foreach ($p['members'] as $idx => $sn) {
                if (!isset($studentIds[$sn])) {
                    continue;
                }
                $role = $idx === 0 ? 'Leader' : 'Member';
                $insMember->execute([
                    richId((string) (910000 + (int) substr($p['id'], -4) * 100 + $sn)),
                    $p['id'], $studentIds[$sn]['id'], $role,
                    "Đóng góp chuyên môn {$studentIds[$sn]['track']} cho dự án.",
                    $now, $now, $now,
                ]);
            }
            echo "  Project: {$p['title']}\n";
        }

        $certCatalog = [
            ['id' => richId('9201'), 'code' => 'btec-web-dev', 'name' => 'Chứng chỉ Web Development', 'desc' => 'Hoàn thành lộ trình web cơ bản đến nâng cao'],
            ['id' => richId('9202'), 'code' => 'btec-uiux', 'name' => 'Chứng chỉ UI/UX Fundamentals', 'desc' => 'Thiết kế trải nghiệm và prototype Figma'],
            ['id' => richId('9203'), 'code' => 'btec-dgt-mkt', 'name' => 'Chứng chỉ Digital Marketing', 'desc' => 'Chiến dịch số và đo lường hiệu quả'],
            ['id' => richId('9204'), 'code' => 'btec-data', 'name' => 'Chứng chỉ Data Analytics', 'desc' => 'Phân tích dữ liệu với Excel/Power BI'],
            ['id' => richId('9205'), 'code' => 'btec-logistics', 'name' => 'Chứng chỉ Logistics Operations', 'desc' => 'Vận hành kho và chuỗi cung ứng'],
        ];
        $insCertCat = $pdo->prepare(
            "INSERT INTO school_certificate_catalog (id, schoolId, code, name, description, issuerName, iconKey, eligibilityCriteria, recommendationProfile, recommendationEnabled, status, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, 'Cao đẳng Quốc tế BTEC FPT Cần Thơ', 'certificate', '[]', '[]', 1, 'active', ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), issuerName = VALUES(issuerName), status = 'active', updatedAt = VALUES(updatedAt)"
        );
        foreach ($certCatalog as $c) {
            $insCertCat->execute([$c['id'], $schoolId, $c['code'], $c['name'], $c['desc'], $now, $now]);
        }
        $insStuCert = $pdo->prepare(
            "INSERT INTO student_certificates (id, studentId, certificateCatalogId, status, issuedAt, issuedBy, issueSource, reason, activityName, evidenceContext, createdAt, updatedAt)
             VALUES (?, ?, ?, 'issued', ?, ?, 'manual', ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = 'issued', reason = VALUES(reason), evidenceContext = VALUES(evidenceContext), updatedAt = VALUES(updatedAt)"
        );
        $certAssign = [
            1 => 0, 2 => 0, 19 => 0, 4 => 1, 5 => 1, 20 => 1, 7 => 2, 8 => 2, 13 => 3, 14 => 3, 16 => 4, 17 => 4,
        ];
        foreach ($certAssign as $sn => $ci) {
            if (!isset($studentIds[$sn])) {
                continue;
            }
            $insStuCert->execute([
                richId((string) (9300 + $sn)), $studentIds[$sn]['id'], $certCatalog[$ci]['id'], $now,
                $schoolAdminUserId !== '' ? $schoolAdminUserId : $primaryTeacher,
                'Hoàn thành chương trình và đánh giá năng lực tương ứng.',
                $certCatalog[$ci]['name'],
                json_encode(['source' => 'rich_seed'], JSON_UNESCAPED_UNICODE),
                $now, $now,
            ]);
        }

        $badgeCodes = ['first_experience', 'experience_10h', 'active_participant', 'assessment_explorer'];
        $badgeMap = [];
        foreach ($pdo->query('SELECT id, code FROM badges WHERE status = \'active\'') as $b) {
            $badgeMap[(string) $b['code']] = (string) $b['id'];
        }
        $ruleMap = [];
        foreach ($pdo->query('SELECT id, badgeId FROM badge_rule_definitions WHERE isActive = 1') as $r) {
            $ruleMap[(string) $r['badgeId']] = (string) $r['id'];
        }
        $insBadge = $pdo->prepare(
            "INSERT INTO student_badges (id, studentId, badgeId, ruleDefinitionId, awardedAt, awardedBy, awardContext)
             VALUES (?, ?, ?, ?, ?, 'system', ?)"
        );
        $delBadge = $pdo->prepare('DELETE FROM student_badges WHERE studentId = ? AND badgeId = ?');
        foreach ($studentIds as $n => $st) {
            $codes = $n % 2 === 0 ? $badgeCodes : array_slice($badgeCodes, 0, 2);
            if (in_array((int) $n, [1, 4, 7, 10, 13, 16, 19, 20], true)) {
                $codes = $badgeCodes;
            }
            foreach ($codes as $bi => $code) {
                if (!isset($badgeMap[$code])) {
                    continue;
                }
                $bid = $badgeMap[$code];
                $delBadge->execute([$st['id'], $bid]);
                $insBadge->execute([
                    richId((string) (9400 + $n * 10 + $bi)),
                    $st['id'],
                    $bid,
                    $ruleMap[$bid] ?? null,
                    $now,
                    json_encode(['source' => 'rich_seed', 'code' => $code], JSON_UNESCAPED_UNICODE),
                ]);
            }
        }
        echo "  Certificates + badges awarded\n";
    }

    if (phaseOk($only, 'enterprise')) {
        echo "[5+6] Enterprises + posts + applications\n";
        $enterprises = [
            [
                'id' => (string) $pdo->query('SELECT id FROM enterprises ORDER BY createdAt ASC LIMIT 1')->fetchColumn(),
                'name' => 'Công ty TNHH Phần mềm FPT',
                'industry' => 'Công nghệ thông tin & AI',
                'size' => '10,000+ nhân viên',
                'year' => 1999,
                'desc' => 'FPT Software – dịch vụ phần mềm, chuyển đổi số và đào tạo tài năng trẻ.',
                'email' => 'fpt@talenthub.local',
                'phone' => '024 7300 7575',
                'web' => 'https://fptsoftware.com',
                'tax' => '0101234567',
                'addr' => 'Tòa FPT, Duy Tân, Cầu Giấy, Hà Nội',
                'userEmail' => 'test.enterprise@talenthub.local',
                'userName' => 'FPT Software Careers',
            ],
            [
                'id' => richId('10001'),
                'name' => 'Công ty Cổ phần Sữa Việt Nam – Vinamilk',
                'industry' => 'FMCG / Thực phẩm & Đồ uống',
                'size' => '10,000+ nhân viên',
                'year' => 1976,
                'desc' => 'Vinamilk – thương hiệu sữa hàng đầu Việt Nam, mở rộng chuỗi cung ứng và marketing.',
                'email' => 'vinamilk@talenthub.local',
                'phone' => '028 5416 1122',
                'web' => 'https://www.vinamilk.com.vn',
                'tax' => '0300588569',
                'addr' => '10 Tân Trào, Tân Phú, Q7, TP.HCM',
                'userEmail' => 'vinamilk@talenthub.local',
                'userName' => 'Vinamilk Talent Acquisition',
            ],
            [
                'id' => richId('10002'),
                'name' => 'Ngân hàng TMCP Quân đội – MB Bank',
                'industry' => 'Tài chính – Ngân hàng số',
                'size' => '16,000+ nhân viên',
                'year' => 1994,
                'desc' => 'MB Bank – ngân hàng số, phân tích dữ liệu khách hàng và trải nghiệm số.',
                'email' => 'mbbank@talenthub.local',
                'phone' => '024 6266 1088',
                'web' => 'https://www.mbbank.com.vn',
                'tax' => '0100686174',
                'addr' => '21 Cát Linh, Đống Đa, Hà Nội',
                'userEmail' => 'mbbank@talenthub.local',
                'userName' => 'MB Bank Campus Recruiting',
            ],
            [
                'id' => richId('10003'),
                'name' => 'PixelCraft Agency',
                'industry' => 'Thiết kế & Marketing số',
                'size' => '50-200 nhân viên',
                'year' => 2018,
                'desc' => 'Agency thiết kế thương hiệu, UI/UX và content cho SME Đồng bằng sông Cửu Long.',
                'email' => 'pixelcraft@talenthub.local',
                'phone' => '0292 888 9911',
                'web' => 'https://pixelcraft.vn',
                'tax' => '1800998877',
                'addr' => 'Nguyễn Văn Cừ, Ninh Kiều, Cần Thơ',
                'userEmail' => 'pixelcraft@talenthub.local',
                'userName' => 'PixelCraft HR',
            ],
        ];

        $insEnt = $pdo->prepare(
            "INSERT INTO enterprises (id, name, status, logoUrl, industry, companySize, foundedYear, description, email, phone, website, taxCode, address, verificationStatus, verificationNote, verifiedAt, verifiedBy, createdAt, updatedAt)
             VALUES (?, ?, 'active', NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'verified', 'Rich seed', ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), industry = VALUES(industry), companySize = VALUES(companySize),
               foundedYear = VALUES(foundedYear), description = VALUES(description), email = VALUES(email), phone = VALUES(phone),
               website = VALUES(website), address = VALUES(address), status = 'active', verificationStatus = 'verified', updatedAt = VALUES(updatedAt)"
        );
        $insEntUser = $pdo->prepare(
            "INSERT INTO users (id, roleId, email, passwordHash, fullName, status, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, 'active', ?, ?)
             ON DUPLICATE KEY UPDATE passwordHash = VALUES(passwordHash), fullName = VALUES(fullName), status = 'active', updatedAt = VALUES(updatedAt)"
        );
        $insEntMember = $pdo->prepare(
            "INSERT INTO enterprise_members (id, enterpriseId, userId, memberRole, createdAt, updatedAt)
             VALUES (?, ?, ?, 'admin', ?, ?)
             ON DUPLICATE KEY UPDATE enterpriseId = VALUES(enterpriseId), memberRole = 'admin', updatedAt = VALUES(updatedAt)"
        );
        $insPartner = $pdo->prepare(
            "INSERT INTO school_enterprise_partnerships (id, schoolId, enterpriseId, status, requestedByUserId, reviewedByUserId, reviewedAt, createdAt, updatedAt)
             VALUES (?, ?, ?, 'approved', ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = 'approved', updatedAt = VALUES(updatedAt)"
        );

        $entIds = [];
        foreach ($enterprises as $ei => $e) {
            $entId = $e['id'] !== '' && $e['id'] !== false ? (string) $e['id'] : richId((string) (10001 + $ei));
            $insEnt->execute([
                $entId, $e['name'], $e['industry'], $e['size'], $e['year'], $e['desc'],
                $e['email'], $e['phone'], $e['web'], $e['tax'], $e['addr'],
                $now, $schoolAdminUserId !== '' ? $schoolAdminUserId : null, $now, $now,
            ]);
            $userId = richId((string) (11000 + $ei));
            $existingU = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $existingU->execute([$e['userEmail']]);
            $foundU = $existingU->fetchColumn();
            if (is_string($foundU) && $foundU !== '') {
                $userId = $foundU;
            }
            $insEntUser->execute([$userId, $roles['enterprise'], $e['userEmail'], password_hash($password, PASSWORD_DEFAULT), $e['userName'], $now, $now]);
            $insEntMember->execute([richId((string) (12000 + $ei)), $entId, $userId, $now, $now]);
            $insPartner->execute([
                richId((string) (13000 + $ei)), $schoolId, $entId,
                $schoolAdminUserId !== '' ? $schoolAdminUserId : $userId,
                $schoolAdminUserId !== '' ? $schoolAdminUserId : $userId,
                $now, $now, $now,
            ]);
            $entIds[$ei] = ['id' => $entId, 'userId' => $userId];
            echo "  Enterprise: {$e['name']}\n";
        }

        $posts = [
            ['id' => richId('14001'), 'ent' => 0, 'title' => 'Thực tập sinh Backend Java/Spring', 'field' => 'Công nghệ thông tin', 'loc' => 'Hà Nội / Hybrid', 'skills' => ['Java', 'Spring', 'SQL'], 'slots' => 5],
            ['id' => richId('14002'), 'ent' => 0, 'title' => 'Thực tập sinh AI/ML Engineer', 'field' => 'Trí tuệ nhân tạo', 'loc' => 'TP.HCM / Hybrid', 'skills' => ['Python', 'ML', 'NLP'], 'slots' => 3],
            ['id' => richId('14003'), 'ent' => 1, 'title' => 'TTS Digital Marketing FMCG', 'field' => 'Marketing', 'loc' => 'TP.HCM', 'skills' => ['Digital Marketing', 'Content', 'Brand'], 'slots' => 4],
            ['id' => richId('14004'), 'ent' => 1, 'title' => 'TTS Supply Chain Operations', 'field' => 'Logistics', 'loc' => 'Bình Dương', 'skills' => ['Warehouse', 'Excel', 'Ops'], 'slots' => 4],
            ['id' => richId('14005'), 'ent' => 2, 'title' => 'TTS Data Analyst Ngân hàng số', 'field' => 'Phân tích dữ liệu', 'loc' => 'Hà Nội', 'skills' => ['Power BI', 'SQL', 'Excel'], 'slots' => 3],
            ['id' => richId('14006'), 'ent' => 2, 'title' => 'TTS Business Analyst – Digital Banking', 'field' => 'Kinh doanh', 'loc' => 'Hà Nội / Hybrid', 'skills' => ['BA', 'Communication', 'Process'], 'slots' => 3],
            ['id' => richId('14007'), 'ent' => 3, 'title' => 'TTS UI/UX Designer', 'field' => 'Thiết kế', 'loc' => 'Cần Thơ', 'skills' => ['Figma', 'UI/UX', 'Research'], 'slots' => 2],
            ['id' => richId('14008'), 'ent' => 3, 'title' => 'TTS Graphic Designer', 'field' => 'Thiết kế đồ họa', 'loc' => 'Cần Thơ / Remote', 'skills' => ['Photoshop', 'Illustrator', 'Brand'], 'slots' => 2],
            ['id' => richId('14009'), 'ent' => 0, 'title' => 'TTS Frontend React', 'field' => 'Công nghệ thông tin', 'loc' => 'Đà Nẵng / Hybrid', 'skills' => ['React', 'TypeScript', 'CSS'], 'slots' => 4],
            ['id' => richId('14010'), 'ent' => 1, 'title' => 'TTS Trade Marketing', 'field' => 'Marketing', 'loc' => 'Cần Thơ', 'skills' => ['Trade Marketing', 'Excel', 'Presentation'], 'slots' => 3],
            ['id' => richId('14011'), 'ent' => 2, 'title' => 'TTS Risk & Credit Analytics', 'field' => 'Tài chính', 'loc' => 'Hà Nội', 'skills' => ['Excel', 'Analytics', 'Finance'], 'slots' => 2],
            ['id' => richId('14012'), 'ent' => 3, 'title' => 'TTS Content Creator', 'field' => 'Truyền thông', 'loc' => 'Cần Thơ', 'skills' => ['Content', 'Video', 'Social'], 'slots' => 2],
        ];
        $insPost = $pdo->prepare(
            "INSERT INTO internship_posts (id, enterpriseId, title, field, status, audience, location, workType, duration, educationLevel, description, benefits, skillsJson, requirementsJson, slots, deadline, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, 'active', 'partner_schools', ?, 'hybrid', '3-6 tháng', 'Cao đẳng/Đại học', ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE enterpriseId = VALUES(enterpriseId), title = VALUES(title), field = VALUES(field), status = 'active', audience = 'partner_schools', location = VALUES(location), description = VALUES(description), benefits = VALUES(benefits), skillsJson = VALUES(skillsJson), requirementsJson = VALUES(requirementsJson), slots = VALUES(slots), deadline = VALUES(deadline), updatedAt = VALUES(updatedAt)"
        );
        $deadline = (new DateTimeImmutable('+45 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $insTarget = $pdo->prepare(
            "INSERT INTO internship_post_target_schools (postId, schoolId, createdAt)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE createdAt = VALUES(createdAt)"
        );
        foreach ($posts as $p) {
            $skillsJson = json_encode($p['skills'], JSON_UNESCAPED_UNICODE);
            $req = json_encode(['Sinh viên năm 2+', 'Có portfolio hoặc project liên quan'], JSON_UNESCAPED_UNICODE);
            $insPost->execute([
                $p['id'], $entIds[$p['ent']]['id'], $p['title'], $p['field'], $p['loc'],
                "Cơ hội thực tập {$p['title']} dành cho sinh viên BTEC FPT Cần Thơ. Làm việc cùng mentor doanh nghiệp, tham gia dự án .",
                "Mentor 1-1, trợ cấp tháng, cơ hội nhận full-time.",
                $skillsJson, $req, $p['slots'], $deadline, $now, $now,
            ]);
            $insTarget->execute([$p['id'], $schoolId, $now]);
            echo "  Post: {$p['title']}\n";
        }

        $appPlan = [
            [1, 0, 'submitted'], [2, 8, 'reviewing'], [19, 1, 'interview'],
            [4, 7, 'submitted'], [5, 6, 'interview'], [20, 6, 'accepted'],
            [7, 2, 'reviewing'], [8, 11, 'submitted'], [9, 9, 'declined'],
            [10, 5, 'interview'], [11, 10, 'submitted'], [13, 4, 'accepted'],
            [14, 1, 'reviewing'], [16, 3, 'submitted'], [17, 3, 'interview'],
            [3, 8, 'withdrawn'], [15, 4, 'submitted'], [18, 3, 'reviewing'],
        ];
        $insApp = $pdo->prepare(
            "INSERT INTO internship_applications (id, postId, studentId, status, message, reviewerNote, reviewedAt, reviewedBy, appliedAt, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), message = VALUES(message), reviewerNote = VALUES(reviewerNote),
               reviewedAt = VALUES(reviewedAt), updatedAt = VALUES(updatedAt)"
        );
        foreach ($appPlan as $i => [$sn, $pi, $status]) {
            if (!isset($studentIds[$sn], $posts[$pi])) {
                continue;
            }
            $reviewed = in_array($status, ['reviewing', 'interview', 'accepted', 'declined'], true);
            $note = $reviewed ? 'Hồ sơ phù hợp định hướng chuyên môn, tiếp tục quy trình.' : null;
            $reviewer = $reviewed ? $entIds[$posts[$pi]['ent']]['userId'] : null;
            $insApp->execute([
                richId((string) (15000 + $i)),
                $posts[$pi]['id'],
                $studentIds[$sn]['id'],
                $status,
                "Em quan tâm vị trí {$posts[$pi]['title']} và muốn đóng góp bằng thế mạnh {$studentIds[$sn]['track']}.",
                $note,
                $reviewed ? $now : null,
                $reviewer,
                $now, $now, $now,
            ]);
        }
        echo "  Applications lifecycle seeded\n";
    }

    if (phaseOk($only, 'notifications')) {
        echo "[8] Notifications / invitations / profile shares\n";
        $insNotif = $pdo->prepare(
            "INSERT INTO notifications (id, userId, eventKey, notificationType, title, message, deepLink, readAt, createdAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?)"
        );
        foreach ($studentIds as $n => $st) {
            if ($n > 8) {
                continue;
            }
            $insNotif->execute([
                richId((string) (16000 + $n)),
                $st['userId'],
                'activity.reminder',
                'activity',
                'Sắp diễn ra hoạt động mới',
                'Bạn có hoạt động phù hợp với hồ sơ đang mở đăng ký tại BTEC FPT Cần Thơ.',
                '/app/learner/activities.php',
                $now,
            ]);
            $insNotif->execute([
                richId((string) (16100 + $n)),
                $st['userId'],
                'internship.update',
                'internship',
                'Cập nhật hồ sơ ứng tuyển',
                'Doanh nghiệp đã cập nhật trạng thái đơn thực tập của bạn.',
                '/app/learner/opportunity.php',
                $now,
            ]);
        }
        if ($schoolAdminUserId !== '') {
            $insNotif->execute([
                richId('16201'), $schoolAdminUserId, 'partnership.approved', 'partnership',
                'Hợp tác doanh nghiệp đã duyệt', 'Các partnership FPT / Vinamilk / MB / PixelCraft đã sẵn sàng.',
                '/app/school/enterprises.php', $now,
            ]);
        }
        foreach (array_slice($teacherIds, 0, 3, true) as $ti => $tpId) {
            $uid = $pdo->query('SELECT userId FROM teacher_profiles WHERE id = ' . $pdo->quote($tpId))->fetchColumn();
            if (!is_string($uid)) {
                continue;
            }
            $insNotif->execute([
                richId((string) (16300 + (int) $ti)), $uid, 'activity.approval', 'activity',
                'Hoạt động đã được phê duyệt', 'Hoạt động bạn tạo đã được nhà trường phê duyệt.',
                '/app/teacher/activities.php', $now,
            ]);
        }

        if ($schoolAdminUserId !== '') {
            $insInvite = $pdo->prepare(
                "INSERT INTO account_invitations (id, userId, invitedByUserId, schoolId, accountRole, tokenHash, expiresAt, acceptedAt, revokedAt, createdAt)
                 VALUES (?, ?, ?, ?, 'teacher', ?, ?, NULL, NULL, ?)
                 ON DUPLICATE KEY UPDATE expiresAt = VALUES(expiresAt), revokedAt = NULL"
            );
            $pendingUser = richId('17001');
            $pdo->prepare(
                "INSERT INTO users (id, roleId, email, passwordHash, fullName, status, createdAt, updatedAt)
                 VALUES (?, ?, 'gv.pending@btec.local', ?, 'Giảng viên chờ kích hoạt', 'pending', ?, ?)
                 ON DUPLICATE KEY UPDATE status = 'pending', updatedAt = VALUES(updatedAt)"
            )->execute([$pendingUser, $roles['teacher'], password_hash($password, PASSWORD_DEFAULT), $now, $now]);
            $insInvite->execute([
                richId('17002'), $pendingUser, $schoolAdminUserId, $schoolId,
                hash('sha256', 'rich-btec-invite-token'),
                (new DateTimeImmutable('+14 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
                $now,
            ]);
        }

        $shareCols = $pdo->query('SHOW COLUMNS FROM student_profile_shares')->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('tokenHash', $shareCols, true)) {
            $insShare = $pdo->prepare(
                "INSERT INTO student_profile_shares (id, studentId, consentId, tokenHash, sharedFieldsJson, expiresAt, revokedAt, createdAt)
                 VALUES (?, ?, ?, ?, ?, ?, NULL, ?)
                 ON DUPLICATE KEY UPDATE sharedFieldsJson = VALUES(sharedFieldsJson), expiresAt = VALUES(expiresAt), revokedAt = NULL"
            );
            foreach ([1, 5, 7, 13, 19, 20] as $sn) {
                if (!isset($studentIds[$sn])) {
                    continue;
                }
                $consentId = $pdo->prepare(
                    "SELECT id FROM privacy_consents WHERE studentId = ? AND scope = 'profile_share' AND isGranted = 1 LIMIT 1"
                );
                $consentId->execute([$studentIds[$sn]['id']]);
                $cid = $consentId->fetchColumn();
                if (!is_string($cid)) {
                    continue;
                }
                $insShare->execute([
                    richId((string) (18000 + $sn)),
                    $studentIds[$sn]['id'],
                    $cid,
                    hash('sha256', 'share-' . $studentIds[$sn]['id']),
                    json_encode(['headline', 'skills', 'assessments', 'projects', 'badges'], JSON_UNESCAPED_UNICODE),
                    (new DateTimeImmutable('+30 days', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
                    $now,
                ]);
            }
        }
        echo "  Notifications + invites + shares ready\n";
    }

    $pdo->commit();
    echo "\n[OK] Enrichment committed\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, '[FAIL enrichment] ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}

echo "\n======================================================================\n";
echo " TÀI KHOẢN NHANH (password: {$password})\n";
echo "======================================================================\n";
echo " School     : test.school@talenthub.local\n";
echo " Teacher    : test.teacher@talenthub.local | gv.hung.cn@btec.local\n";
echo " Student IT : sv.an.cn@btec.local | sv.tam.ai@btec.local\n";
echo " Design     : sv.minh.design@btec.local | chau.thietke@talenthub.local\n";
echo " Marketing  : sv.ha.mkt@btec.local\n";
echo " Business   : sv.quang.biz@btec.local\n";
echo " Data       : sv.tuyet.data@btec.local\n";
echo " Logistics  : sv.duyen.log@btec.local\n";
echo " Enterprise : test.enterprise@talenthub.local | vinamilk@ | mbbank@ | pixelcraft@\n";
echo "======================================================================\n";
echo " Mục 7 (AI ): php bin/trigger-rich-ai-analysis.php\n";
echo "======================================================================\n";
