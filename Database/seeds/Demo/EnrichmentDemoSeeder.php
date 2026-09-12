<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\Demo;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

/**
 * Enrichment seeder to provide realistic demo data for:
 * - Activity details (activity_details)
 * - Student badges (student_badges)
 * - Internship applications (internship_applications)
 * - AI Roadmaps, phases and tasks (learner_ai_roadmaps, phases, tasks)
 *
 * Idempotent and safe to run multiple times.
 */
final class EnrichmentDemoSeeder
{
    public function run(PDO $pdo): void
    {
        $this->seedActivityDetails($pdo);
        $this->seedStudentBadges($pdo);
        $this->seedInternshipApplications($pdo);
        $this->seedRoadmaps($pdo);
    }

    private function seedActivityDetails(PDO $pdo): void
    {
        $activities = $pdo->query(
            "SELECT a.id, a.title, a.category, a.schoolId, a.createdByTeacherId, s.name as schoolName, s.address as schoolAddress
             FROM activities a
             JOIN schools s ON s.id = a.schoolId"
        )->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(
            "INSERT INTO activity_details (
                activityId, responsibleTeacherId, audienceScope, displayCategory, filterCategory,
                summary, description, experienceHighlights, skillTags, eligibilityRules,
                benefitItems, locationName, locationAddress, deliveryMode, onlineMeetingUrl,
                organizerName, organizerContact, organizerEmail, organizerPhone, coverImageUrl,
                coverImageAlt, feeAmount, currency, targetAudience, certificateLabel, createdAt, updatedAt
            ) VALUES (
                :activityId, :responsibleTeacherId, 'school_only', :displayCategory, :filterCategory,
                :summary, :description, :experienceHighlights, :skillTags, :eligibilityRules,
                :benefitItems, :locationName, :locationAddress, 'in_person', NULL,
                :organizerName, :organizerContact, :organizerEmail, :organizerPhone, :coverImageUrl,
                :coverImageAlt, 0.00, 'VND', :targetAudience, :certificateLabel, NOW(6), NOW(6)
            ) ON DUPLICATE KEY UPDATE summary=VALUES(summary), description=VALUES(description)"
        );

        foreach ($activities as $a) {
            $cat = (string) $a['category'];
            $displayCat = match ($cat) {
                'career_technical' => 'Kỹ thuật & Công nghệ',
                'career_arts' => 'Nghệ thuật & Thiết kế',
                'career_sports_academic' => 'Thể thao & Thể chất',
                default => 'Hoạt động trải nghiệm',
            };

            $highlights = json_encode([
                "Trải nghiệm thực tế với {$a['title']}",
                "Hướng dẫn trực tiếp từ cố vấn chuyên môn",
                "Giao lưu học hỏi cùng bạn bè cùng chí hướng"
            ], JSON_UNESCAPED_UNICODE);

            $skills = json_encode(["Làm việc nhóm", "Giao tiếp", "Tư duy sáng tạo"], JSON_UNESCAPED_UNICODE);
            $rules = json_encode(["Đang theo học tại {$a['schoolName']}", "Đăng ký trước hạn chót"], JSON_UNESCAPED_UNICODE);
            $benefits = json_encode(["Ghi nhận giờ trải nghiệm", "Cấp minh chứng tham gia trên TalentHub"], JSON_UNESCAPED_UNICODE);

            $stmt->execute([
                'activityId' => $a['id'],
                'responsibleTeacherId' => $a['createdByTeacherId'],
                'displayCategory' => $displayCat,
                'filterCategory' => $displayCat,
                'summary' => "Hoạt động {$a['title']} dành cho học sinh, sinh viên {$a['schoolName']}.",
                'description' => "Chương trình {$a['title']} nhằm thúc đẩy tiềm năng, kết nối học viên và trang bị kỹ năng toàn diện thông qua các buổi thực hành chuyên sâu.",
                'experienceHighlights' => $highlights,
                'skillTags' => $skills,
                'eligibilityRules' => $rules,
                'benefitItems' => $benefits,
                'locationName' => "Khuôn viên {$a['schoolName']}",
                'locationAddress' => $a['schoolAddress'] ?: "Hà Nội / TP. Hồ Chí Minh",
                'organizerName' => $a['schoolName'],
                'organizerContact' => "Ban Tổ Chức {$a['schoolName']}",
                'organizerEmail' => "contact@talenthub.vn",
                'organizerPhone' => "1900 8899",
                'coverImageUrl' => "/app/learner/assets/activities/covers/default.webp",
                'coverImageAlt' => "Hình ảnh hoạt động {$a['title']}",
                'targetAudience' => "Học viên thuộc {$a['schoolName']}",
                'certificateLabel' => "Chứng nhận tham gia {$a['title']}",
            ]);
        }
    }


    private function seedStudentBadges(PDO $pdo): void
    {
        $students = $pdo->query(
            "SELECT sp.id FROM student_profiles sp
             JOIN users u ON u.id = sp.userId
             WHERE u.email IN ('hs.minh@talenthub.vn', 'pw.assess.78c4y3myat@talenthub.test')
                OR u.email LIKE 'hs.%@talenthub.vn'
             LIMIT 10"
        )->fetchAll(PDO::FETCH_COLUMN);

        $rules = $pdo->query(
            "SELECT b.id as badgeId, r.id as ruleId
             FROM badges b
             JOIN badge_rule_definitions r ON r.badgeId = b.id
             LIMIT 5"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($students) || empty($rules)) {
            return;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO student_badges (
                id, studentId, badgeId, ruleDefinitionId, awardedAt, awardedBy, awardContext
            ) VALUES (
                :id, :studentId, :badgeId, :ruleId, NOW(6), 'system', :awardContext
            ) ON DUPLICATE KEY UPDATE awardedAt=VALUES(awardedAt)"
        );

        foreach ($students as $studentId) {
            foreach ($rules as $idx => $r) {
                $awardId = sprintf('61000000-0000-4000-8000-%012d', abs(crc32($studentId . $r['badgeId'])));
                $context = json_encode([
                    'fact' => 'completed_demo_criteria',
                    'target' => 1,
                    'current' => 1,
                    'evaluatedAt' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c'),
                    'ruleVersion' => 1,
                    'ruleDefinitionId' => $r['ruleId'],
                ], JSON_UNESCAPED_UNICODE);

                $stmt->execute([
                    'id' => $awardId,
                    'studentId' => $studentId,
                    'badgeId' => $r['badgeId'],
                    'ruleId' => $r['ruleId'],
                    'awardContext' => $context,
                ]);
            }
        }
    }

    private function seedInternshipApplications(PDO $pdo): void
    {
        $students = $pdo->query(
            "SELECT sp.id FROM student_profiles sp
             JOIN users u ON u.id = sp.userId
             WHERE u.email IN ('hs.minh@talenthub.vn', 'pw.assess.78c4y3myat@talenthub.test')
                OR u.email LIKE 'sv.fpt.%@talenthub.vn'
             LIMIT 5"
        )->fetchAll(PDO::FETCH_COLUMN);

        $posts = $pdo->query("SELECT id FROM internship_posts WHERE status = 'open' LIMIT 4")->fetchAll(PDO::FETCH_COLUMN);

        if (empty($students) || empty($posts)) {
            return;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO internship_applications (
                id, postId, studentId, status, message, reviewerNote, reviewedAt, reviewedBy, appliedAt, createdAt, updatedAt
            ) VALUES (
                :id, :postId, :studentId, :status, :message, :reviewerNote, :reviewedAt, NULL, NOW(6), NOW(6), NOW(6)
            ) ON DUPLICATE KEY UPDATE status=VALUES(status)"
        );

        $statuses = ['submitted', 'shortlisted', 'accepted'];
        $i = 1;
        foreach ($students as $studentId) {
            foreach ($posts as $pIdx => $postId) {
                $status = $statuses[($i + $pIdx) % count($statuses)];
                $appId = sprintf('62000000-0000-4000-8000-%012d', $i);
                $stmt->execute([
                    'id' => $appId,
                    'postId' => $postId,
                    'studentId' => $studentId,
                    'status' => $status,
                    'message' => 'Em rất mong muốn được thử sức và học hỏi kinh nghiệm thực tế tại quý công ty.',
                    'reviewerNote' => $status === 'accepted' ? 'Hồ sơ năng lực tốt, phù hợp định hướng dự án.' : null,
                    'reviewedAt' => $status !== 'submitted' ? (new DateTimeImmutable('-2 days'))->format('Y-m-d H:i:s.u') : null,
                ]);
                $i++;
            }
        }
    }

    private function seedRoadmaps(PDO $pdo): void
    {
        $students = $pdo->query(
            "SELECT sp.id, u.fullName FROM student_profiles sp
             JOIN users u ON u.id = sp.userId
             WHERE u.email IN ('hs.minh@talenthub.vn', 'pw.assess.78c4y3myat@talenthub.test')"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Migration 004 alignment: ensure each learner has a snapshot + run
        // that the roadmap ownership trigger can verify against.
        $snapshotStmt = $pdo->prepare(
            "INSERT IGNORE INTO learner_recommendation_input_snapshots (
                id, studentId, schemaVersion, contentHash, consentScopesJson,
                qualityFlagsJson, payloadJson, sourceUpdatedAt, createdAt
            ) VALUES (
                :id, :studentId, :schemaVersion, :contentHash, :consentScopesJson,
                :qualityFlagsJson, :payloadJson, :sourceUpdatedAt, NOW(6)
            )"
        );
        $runStmt = $pdo->prepare(
            "INSERT IGNORE INTO learner_recommendation_runs (
                id, studentId, snapshotId, idempotencyKey, engineType, status,
                ruleVersion, provider, modelVersion, promptVersion, startedAt, completedAt, createdAt
            ) VALUES (
                :id, :studentId, :snapshotId, :idempotencyKey, :engineType, :status,
                :ruleVersion, :provider, :modelVersion, :promptVersion, NOW(6), NOW(6), NOW(6)
            )"
        );
        $auditStmt = $pdo->prepare(
            "INSERT IGNORE INTO learner_recommendation_audit_events (
                id, runId, studentId, requestId, actorType, action, engineMetadataJson, status, createdAt
            ) VALUES (
                :id, :runId, :studentId, :requestId, :actorType, :action, :engineMetadataJson, :status, NOW(6)
            )"
        );

        // Migration 005 alignment: every NOT NULL column is supplied and we use
        // a NOT EXISTS guard instead of ON DUPLICATE KEY UPDATE so that the
        // immutable roadmap trigger never fires on re-runs.
        $roadmapStmt = $pdo->prepare(
            "INSERT INTO learner_ai_roadmaps (
                id, studentId, runId, versionNumber, contractVersion, status,
                executiveSummary, primaryDirectionJson, alternativeDirectionsJson,
                insightsJson, confidenceBand, evidenceSummaryJson,
                providerRequestId, responseHash, generatedAt, createdAt
            ) SELECT
                :id, :studentId, :runId, :versionNumber, :contractVersion, :status,
                :executiveSummary, :primaryDirectionJson, :alternativeDirectionsJson,
                :insightsJson, :confidenceBand, :evidenceSummaryJson,
                :providerRequestId, :responseHash, :generatedAt, NOW(6)
            FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM learner_ai_roadmaps WHERE id = :idGuard)"
        );

        foreach ($students as $idx => $st) {
            $studentId = (string) $st['id'];
            $name = (string) $st['fullName'];
            $snapshotId = sprintf('61000000-0000-4000-8000-%012d', $idx + 1);
            $runId      = sprintf('62000000-0000-4000-8000-%012d', $idx + 1);
            $roadmapId  = sprintf('63000000-0000-4000-8000-%012d', $idx + 1);
            $idempotencyKey = sprintf('enrichment-demo:%s:v1', $studentId);
            $requestId = sprintf('64000000-0000-4000-8000-%012d', $idx + 1);
            $auditId   = sprintf('65000000-0000-4000-8000-%012d', $idx + 1);
            $providerRequestId = sprintf('enrichment-demo-req-%03d', $idx + 1);
            $responseHash = hash('sha256', $studentId . ':' . $roadmapId);
            $contentHash  = hash('sha256', 'enrichment-snapshot:' . $studentId);
            $generatedAt = (new DateTimeImmutable('today', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');

            $primaryDir = json_encode([
                'code' => 'tech_software_engineering',
                'label' => 'Kỹ sư Phát triển Phần mềm & Giải pháp Số',
                'rationale' => 'Thiên hướng mạnh mẽ về tư duy cấu trúc, giải quyết vấn đề và tự học công nghệ mới.',
            ], JSON_UNESCAPED_UNICODE);

            $altDirs = json_encode([[
                'code' => 'data_analytics',
                'label' => 'Phân tích Dữ liệu Ứng dụng',
                'rationale' => 'Khả năng tư duy định lượng và giải quyết vấn đề toán học chặt chẽ.',
            ]], JSON_UNESCAPED_UNICODE);

            $insights = json_encode(['items' => [
                ['category' => 'strength', 'title' => 'Tư duy logic và giải quyết vấn đề vượt trội', 'summary' => 'Thế mạnh thể hiện rõ qua các bài đánh giá.'],
                ['category' => 'improvement', 'title' => 'Tăng cường kinh nghiệm dự án thực tế', 'summary' => 'Nên tham gia thêm dự án nhóm để rèn luyện kỹ năng thực chiến.']
            ]], JSON_UNESCAPED_UNICODE);

            $snapshotStmt->execute([
                'id' => $snapshotId,
                'studentId' => $studentId,
                'schemaVersion' => '2026-09-01',
                'contentHash' => $contentHash,
                'consentScopesJson' => json_encode(['recommendation', 'roadmap'], JSON_UNESCAPED_UNICODE),
                'qualityFlagsJson' => json_encode(['completeness' => 1.0], JSON_UNESCAPED_UNICODE),
                'payloadJson' => json_encode(['source' => 'enrichment_demo'], JSON_UNESCAPED_UNICODE),
                'sourceUpdatedAt' => json_encode($generatedAt, JSON_UNESCAPED_UNICODE),
            ]);

            $runStmt->execute([
                'id' => $runId,
                'studentId' => $studentId,
                'snapshotId' => $snapshotId,
                'idempotencyKey' => $idempotencyKey,
                'engineType' => 'model',
                'status' => 'completed',
                'ruleVersion' => null,
                'provider' => 'gemini',
                'modelVersion' => 'gemini-2.5-flash',
                'promptVersion' => '2026-09-01',
            ]);

            $auditStmt->execute([
                'id' => $auditId,
                'runId' => $runId,
                'studentId' => $studentId,
                'requestId' => $requestId,
                'actorType' => 'system',
                'action' => 'roadmap_generated',
                'engineMetadataJson' => json_encode(['source' => 'enrichment_demo'], JSON_UNESCAPED_UNICODE),
                'status' => 'success',
            ]);

            $roadmapStmt->execute([
                'id' => $roadmapId,
                'studentId' => $studentId,
                'runId' => $runId,
                'versionNumber' => 1,
                'contractVersion' => 'roadmap_v1',
                'status' => 'active',
                'executiveSummary' => "Lộ trình 90 ngày phát triển năng lực toàn diện dành cho {$name}.",
                'primaryDirectionJson' => $primaryDir,
                'alternativeDirectionsJson' => $altDirs,
                'insightsJson' => $insights,
                'confidenceBand' => 'medium',
                'evidenceSummaryJson' => json_encode(['assessment_count' => 4]),
                'providerRequestId' => $providerRequestId,
                'responseHash' => $responseHash,
                'generatedAt' => $generatedAt,
                'idGuard' => $roadmapId,
            ]);

            $this->seedPhasesAndTasks($pdo, $roadmapId, $idx);
        }
    }

    private function seedPhasesAndTasks(PDO $pdo, string $roadmapId, int $idx): void
    {
        // Migration 005 alignment: phases & tasks have immutable-update triggers,
        // so we use an INSERT … SELECT WHERE NOT EXISTS guard instead of ODKU.
        $phaseStmt = $pdo->prepare(
            "INSERT INTO learner_ai_roadmap_phases (
                id, roadmapId, position, startDay, endDay, code, title, goal, skillFocus,
                deliverable, effortLabel, metricLabel, evidenceJson, createdAt
            ) SELECT
                :id, :roadmapId, :position, :startDay, :endDay, :code, :title, :goal, :skillFocus,
                :deliverable, :effortLabel, :metricLabel, '[]', NOW(6)
            FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM learner_ai_roadmap_phases WHERE id = :idGuard)"
        );

        $taskStmt = $pdo->prepare(
            "INSERT INTO learner_ai_roadmap_tasks (
                id, phaseId, position, title, description, estimatedMinutes, actionType, targetType, targetId, evidenceJson, createdAt
            ) SELECT
                :id, :phaseId, :position, :title, :description, :estimatedMinutes, :actionType, NULL, NULL, '[]', NOW(6)
            FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM learner_ai_roadmap_tasks WHERE id = :idGuard)"
        );

        $phases = [
            ['discover', 'Giai đoạn 1: Nền tảng & Khám phá', 0, 30, 'Củng cố kiến thức và hoàn thiện hồ sơ', 'Tư duy logic, Lập trình'],
            ['practice', 'Giai đoạn 2: Thực chiến & Đồ án', 31, 60, 'Tham gia dự án thực hành có cố vấn', 'Làm việc nhóm, Giải quyết vấn đề'],
            ['breakthrough', 'Giai đoạn 3: Bứt phá & Kết nối', 61, 90, 'Hoàn thiện hồ sơ ứng tuyển thực tập', 'Giao tiếp, Phỏng vấn'],
        ];

        foreach ($phases as $pPos => $pData) {
            $phaseId = sprintf('63100000-0000-4000-8000-%012d', ($idx * 3) + $pPos + 1);
            $phaseStmt->execute([
                'id' => $phaseId,
                'roadmapId' => $roadmapId,
                'position' => $pPos + 1,
                'startDay' => $pData[2],
                'endDay' => $pData[3],
                'code' => $pData[0],
                'title' => $pData[1],
                'goal' => $pData[4],
                'skillFocus' => $pData[5],
                'deliverable' => 'Hồ sơ và sản phẩm minh chứng',
                'effortLabel' => '5-7 giờ / tuần',
                'metricLabel' => 'Hoàn thành 100% mục tiêu giai đoạn',
                'idGuard' => $phaseId,
            ]);

            for ($t = 1; $t <= 2; $t++) {
                $taskId = sprintf('63200000-0000-4000-8000-%012d', ($idx * 6) + ($pPos * 2) + $t);
                $taskStmt->execute([
                    'id' => $taskId,
                    'phaseId' => $phaseId,
                    'position' => $t,
                    'title' => "Nhiệm vụ rèn luyện {$t} - {$pData[1]}",
                    'description' => "Thực hiện nhiệm vụ học tập và ứng dụng thực tiễn theo kế hoạch đề ra.",
                    'estimatedMinutes' => 60,
                    'actionType' => 'self_task',
                    'idGuard' => $taskId,
                ]);
            }
        }
    }
}