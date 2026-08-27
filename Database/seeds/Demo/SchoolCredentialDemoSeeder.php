<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\Demo;

use PDO;
use RuntimeException;
use Throwable;

final class SchoolCredentialDemoSeeder
{
    public function run(PDO $pdo, string $environment): void
    {
        if (!in_array(strtolower($environment), ['local', 'development', 'test', 'testing'], true)) {
            throw new RuntimeException('School credential demo seed is forbidden outside local/test environments.');
        }

        $schools = $pdo->query("SELECT id, name FROM schools WHERE status = 'active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($schools) || $schools === []) {
            throw new RuntimeException('No active schools available for credential seed.');
        }

        $pdo->beginTransaction();
        try {
            foreach ($schools as $school) {
                $schoolId = (string) $school['id'];
                $schoolName = (string) $school['name'];
                $dataset = SchoolCredentialDemoDataset::forSchool($schoolId, $schoolName);
                $this->upsertBadges($pdo, $schoolId, $dataset['badges']);
                $this->upsertCertificates($pdo, $schoolId, $dataset['certificates']);
                $this->issueDemoCertificate($pdo, $schoolId, $dataset['certificates'][0]['id']);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param list<array<string,mixed>> $badges */
    private function upsertBadges(PDO $pdo, string $schoolId, array $badges): void
    {
        $statement = $pdo->prepare(<<<'SQL'
INSERT INTO badges (id, schoolId, code, name, category, description, iconUrl, recommendationProfile, recommendationEnabled, level, status)
VALUES (:id, :schoolId, :code, :name, :category, :description, NULL, :profile, :recommended, :level, 'active')
ON DUPLICATE KEY UPDATE
  schoolId = VALUES(schoolId), name = VALUES(name), category = VALUES(category), description = VALUES(description),
  recommendationProfile = VALUES(recommendationProfile), recommendationEnabled = VALUES(recommendationEnabled), level = VALUES(level), status = 'active'
SQL);
        $ruleStatement = $pdo->prepare(<<<'SQL'
INSERT INTO badge_rule_definitions (id, badgeId, ruleType, thresholdCriteria, version, isActive)
VALUES (:id, :badgeId, 'threshold', :criteria, 1, 1)
ON DUPLICATE KEY UPDATE thresholdCriteria = VALUES(thresholdCriteria), isActive = 1
SQL);

        foreach ($badges as $badge) {
            $profile = json_encode($badge['recommendation_profile'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $statement->execute([
                'id' => $badge['id'], 'schoolId' => $schoolId, 'code' => $badge['code'], 'name' => $badge['name'],
                'category' => $badge['category'], 'description' => $badge['description'], 'profile' => $profile,
                'recommended' => $badge['recommendation_enabled'] ? 1 : 0, 'level' => $badge['level'],
            ]);
            if ($badge['rule'] !== null) {
                $rule = $badge['rule'];
                $criteria = json_encode(['fact' => $rule['fact'], 'operator' => 'gte', 'value' => $rule['value']], JSON_THROW_ON_ERROR);
                $ruleStatement->execute(['id' => $rule['id'], 'badgeId' => $badge['id'], 'criteria' => $criteria]);
            }
        }
    }

    /** @param list<array<string,mixed>> $certificates */
    private function upsertCertificates(PDO $pdo, string $schoolId, array $certificates): void
    {
        $statement = $pdo->prepare(<<<'SQL'
INSERT INTO school_certificate_catalog (id, schoolId, code, name, description, issuerName, iconKey, eligibilityCriteria, recommendationProfile, recommendationEnabled, status)
VALUES (:id, :schoolId, :code, :name, :description, :issuerName, :iconKey, :criteria, :profile, :recommended, 'active')
ON DUPLICATE KEY UPDATE
  schoolId = VALUES(schoolId), name = VALUES(name), description = VALUES(description), issuerName = VALUES(issuerName),
  iconKey = VALUES(iconKey), eligibilityCriteria = VALUES(eligibilityCriteria), recommendationProfile = VALUES(recommendationProfile),
  recommendationEnabled = VALUES(recommendationEnabled), status = 'active'
SQL);
        foreach ($certificates as $certificate) {
            $statement->execute([
                'id' => $certificate['id'], 'schoolId' => $schoolId, 'code' => $certificate['code'], 'name' => $certificate['name'],
                'description' => $certificate['description'], 'issuerName' => $certificate['issuer_name'], 'iconKey' => $certificate['icon_key'],
                'criteria' => json_encode($certificate['eligibility_criteria'], JSON_THROW_ON_ERROR),
                'profile' => json_encode($certificate['recommendation_profile'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'recommended' => $certificate['recommendation_enabled'] ? 1 : 0,
            ]);
        }
    }

    private function issueDemoCertificate(PDO $pdo, string $schoolId, string $catalogId): void
    {
        $studentStatement = $pdo->prepare(<<<'SQL'
SELECT sp.id
FROM student_profiles sp
INNER JOIN classes c ON c.id = sp.classId
WHERE c.schoolId = :schoolId AND sp.studyStatus = 'active'
ORDER BY sp.createdAt ASC, sp.id ASC
LIMIT 1
SQL);
        $studentStatement->execute(['schoolId' => $schoolId]);
        $studentId = $studentStatement->fetchColumn();
        if (!is_string($studentId)) {
            return;
        }

        $insert = $pdo->prepare(<<<'SQL'
INSERT INTO student_school_certificates (id, studentId, certificateCatalogId, status, issuedAt, issuedBy, evidenceContext)
VALUES (:id, :studentId, :catalogId, 'issued', UTC_TIMESTAMP(6), NULL, :context)
ON DUPLICATE KEY UPDATE status = 'issued'
SQL);
        $insert->execute([
            'id' => SchoolCredentialDemoDataset::uuid($studentId, 'issued-certificate', $catalogId),
            'studentId' => $studentId,
            'catalogId' => $catalogId,
            'context' => json_encode(['source' => 'demo_seed', 'schoolId' => $schoolId], JSON_THROW_ON_ERROR),
        ]);
    }
}
