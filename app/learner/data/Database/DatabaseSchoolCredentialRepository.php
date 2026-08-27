<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Database;

use TalentHub\Learner\Data\Contracts\SchoolCredentialRepository;
use TalentHub\Learner\Data\Support\Uuid;

final class DatabaseSchoolCredentialRepository extends AbstractDatabaseRepository implements SchoolCredentialRepository
{
    public function studentContext(string $studentId): ?array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $row = $this->fetchOne('studentContext', <<<'SQL'
SELECT sp.id AS student_id, s.id AS school_id, s.name AS school_name, c.gradeLevel AS grade_level
FROM student_profiles sp
INNER JOIN classes c ON c.id = sp.classId
INNER JOIN schools s ON s.id = c.schoolId
WHERE sp.id = :student_id
LIMIT 1
SQL, ['student_id' => $studentId]);
        if ($row === null) {
            return null;
        }
        return [
            'student_id' => (string) $row['student_id'],
            'school_id' => (string) $row['school_id'],
            'school_name' => (string) $row['school_name'],
            'grade_level' => is_numeric($row['grade_level'] ?? null) ? (int) $row['grade_level'] : null,
        ];
    }

    public function latestAssessmentProfile(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $rows = $this->fetchAll('latestAssessmentProfile', <<<'SQL'
SELECT tt.type AS test_type, tr.resultCode AS result_code, tr.dimensionScoresJson AS dimension_scores, ta.submittedAt AS submitted_at
FROM test_attempts ta
INNER JOIN talent_tests tt ON tt.id = ta.testId
INNER JOIN test_results tr ON tr.attemptId = ta.id
WHERE ta.studentId = :student_id AND ta.status = 'submitted'
  AND tt.type IN ('holland','mbti','disc','multiple_intelligence')
ORDER BY ta.submittedAt DESC, tr.createdAt DESC, tr.id DESC
SQL, ['student_id' => $studentId]);

        $profile = ['holland' => [], 'multiple_intelligence' => [], 'disc' => [], 'mbti' => [], 'completed_families' => []];
        $seen = [];
        foreach ($rows as $row) {
            $type = (string) ($row['test_type'] ?? '');
            if ($type === '' || isset($seen[$type])) {
                continue;
            }
            $seen[$type] = true;
            $profile['completed_families'][] = $type;
            $scores = $this->decodeJson($row['dimension_scores'] ?? null, 'dimensionScoresJson');
            if ($type === 'holland' || $type === 'disc') {
                $profile[$type] = $this->numericScores($scores);
            } elseif ($type === 'mbti') {
                $code = strtoupper(trim((string) ($row['result_code'] ?? '')));
                $profile['mbti'] = $code === '' ? [] : [$code => 100.0];
            } else {
                $profile['multiple_intelligence'] = $this->multipleIntelligenceScores($scores);
            }
        }
        sort($profile['completed_families'], SORT_STRING);
        return $profile;
    }

    public function verifiedSkillProfile(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $rows = $this->fetchAll('verifiedSkillProfile', <<<'SQL'
SELECT s.code, MAX(ss.levelScore) AS level_score
FROM student_skills ss
INNER JOIN skills s ON s.id = ss.skillId AND s.status = 'active'
WHERE ss.studentId = :student_id AND ss.verificationStatus = 'verified'
GROUP BY s.code
ORDER BY s.code
SQL, ['student_id' => $studentId]);
        $result = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            if ($code !== '') {
                $result[$code] = max(0.0, min(100.0, (float) ($row['level_score'] ?? 0)));
            }
        }
        return $result;
    }

    public function credentialCatalog(string $schoolId): array
    {
        $schoolId = Uuid::normalizeDatabase($schoolId, 'school_id');
        $badges = $this->fetchAll('schoolBadgeCatalog', <<<'SQL'
SELECT b.id, b.code, b.name, b.category, b.description, b.iconUrl AS icon_url, b.level,
       b.recommendationProfile AS recommendation_profile, b.recommendationEnabled AS recommendation_enabled,
       r.thresholdCriteria AS eligibility_criteria
FROM badges b
LEFT JOIN badge_rule_definitions r ON r.badgeId = b.id AND r.isActive = 1
WHERE b.schoolId = :school_id AND b.status = 'active'
ORDER BY b.level, b.createdAt, b.code
SQL, ['school_id' => $schoolId]);
        $result = [];
        foreach ($badges as $index => $row) {
            $result[] = [
                'kind' => 'badge', 'id' => (string) $row['id'], 'code' => (string) $row['code'],
                'name' => (string) $row['name'], 'category' => (string) $row['category'],
                'description' => (string) $row['description'], 'icon_key' => 'award',
                'level' => (int) $row['level'],
                'eligibility_criteria' => $this->decodeJson($row['eligibility_criteria'] ?? null, 'thresholdCriteria'),
                'recommendation_profile' => $this->decodeJson($row['recommendation_profile'] ?? null, 'recommendationProfile'),
                'recommendation_enabled' => (bool) $row['recommendation_enabled'], 'fallback_order' => $index,
            ];
        }

        $certificates = $this->fetchAll('schoolCertificateCatalog', <<<'SQL'
SELECT id, code, name, description, issuerName AS issuer_name, iconKey AS icon_key,
       eligibilityCriteria AS eligibility_criteria, recommendationProfile AS recommendation_profile,
       recommendationEnabled AS recommendation_enabled
FROM school_certificate_catalog
WHERE schoolId = :school_id AND status = 'active'
ORDER BY createdAt, code
SQL, ['school_id' => $schoolId]);
        foreach ($certificates as $index => $row) {
            $result[] = [
                'kind' => 'certificate', 'id' => (string) $row['id'], 'code' => (string) $row['code'],
                'name' => (string) $row['name'], 'description' => (string) $row['description'],
                'issuer_name' => (string) $row['issuer_name'], 'icon_key' => (string) $row['icon_key'],
                'eligibility_criteria' => $this->decodeJson($row['eligibility_criteria'] ?? null, 'eligibilityCriteria'),
                'recommendation_profile' => $this->decodeJson($row['recommendation_profile'] ?? null, 'recommendationProfile'),
                'recommendation_enabled' => (bool) $row['recommendation_enabled'], 'fallback_order' => $index,
            ];
        }
        return $result;
    }

    public function issuedSchoolCertificates(string $studentId): array
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $rows = $this->fetchAll('issuedSchoolCertificates', <<<'SQL'
SELECT ssc.id AS award_id, ssc.certificateCatalogId AS catalog_id, ssc.status, ssc.issuedAt AS issued_at,
       ssc.evidenceContext AS evidence_context
FROM student_school_certificates ssc
WHERE ssc.studentId = :student_id
ORDER BY ssc.issuedAt DESC, ssc.id DESC
SQL, ['student_id' => $studentId]);
        foreach ($rows as &$row) {
            $row['evidence_context'] = $this->decodeJson($row['evidence_context'] ?? null, 'evidenceContext');
        }
        unset($row);
        return $rows;
    }

    public function hasCompletedRoadmap(string $studentId): bool
    {
        $studentId = Uuid::normalizeDatabase($studentId, 'student_id');
        $row = $this->fetchOne('hasCompletedRoadmap', "SELECT 1 AS present FROM learner_ai_roadmaps WHERE studentId = :student_id AND status = 'active' LIMIT 1", ['student_id' => $studentId]);
        return $row !== null;
    }

    /** @param array<string,mixed> $scores @return array<string,float> */
    private function numericScores(array $scores): array
    {
        $result = [];
        foreach ($scores as $key => $value) {
            if (is_numeric($value)) {
                $result[strtoupper((string) $key)] = max(0.0, min(100.0, (float) $value));
            }
        }
        return $result;
    }

    /** @param array<string,mixed> $scores @return array<string,float> */
    private function multipleIntelligenceScores(array $scores): array
    {
        $mapping = ['LOGI' => 'logical', 'SPAT' => 'spatial', 'LING' => 'linguistic', 'INTER' => 'interpersonal', 'INTRA' => 'intrapersonal', 'BODY' => 'bodily', 'MUSIC' => 'musical', 'NAT' => 'naturalist'];
        $result = [];
        foreach ($scores as $key => $value) {
            $name = $mapping[strtoupper((string) $key)] ?? strtolower((string) $key);
            if (is_numeric($value)) {
                $result[$name] = max(0.0, min(100.0, (float) $value));
            }
        }
        return $result;
    }
}
