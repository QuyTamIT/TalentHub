<?php
declare(strict_types=1);

use TalentHub\Database\Migration\AbstractMigration;
use TalentHub\Database\Migration\MigrationContext;

return new class extends AbstractMigration {
    public function description(): string { return 'Add shared skill groups and independent teacher assessment group scores'; }
    public function isReversible(): bool { return false; }
    public function preflight(MigrationContext $context): void
    {
        $context->assertTableExists('skills');
        $context->assertTableExists('assessments');
    }
    public function up(MigrationContext $context): void
    {
        $pdo = $context->pdo();
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS skill_groups (
    code VARCHAR(48) NOT NULL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    displayOrder INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    CONSTRAINT chk_skill_groups_status CHECK (status IN ('active', 'inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS skill_group_members (
    groupCode VARCHAR(48) NOT NULL,
    skillId CHAR(36) NOT NULL,
    PRIMARY KEY (groupCode, skillId),
    CONSTRAINT fk_skill_group_members_group FOREIGN KEY (groupCode) REFERENCES skill_groups(code),
    CONSTRAINT fk_skill_group_members_skill FOREIGN KEY (skillId) REFERENCES skills(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS assessment_skill_group_scores (
    assessmentId CHAR(36) NOT NULL,
    groupCode VARCHAR(48) NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    PRIMARY KEY (assessmentId, groupCode),
    CONSTRAINT fk_assessment_group_scores_assessment FOREIGN KEY (assessmentId) REFERENCES assessments(id),
    CONSTRAINT fk_assessment_group_scores_group FOREIGN KEY (groupCode) REFERENCES skill_groups(code),
    CONSTRAINT chk_assessment_group_scores_range CHECK (score >= 0 AND score <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        // Explicit taxonomy only; membership never propagates scores or creates catalog skills.
        // Missing skill codes are skipped. Runtime reads the database, not this seed list.
        $groups = [
            'backend' => ['Lập trình Backend', 'php java python nodejs springboot api_development rest_api mysql sql database_design docker git algorithms software_testing'],
            'frontend' => ['Lập trình Frontend', 'html css html_css javascript typescript react vuejs dart flutter swift git software_testing'],
            'ai_ml' => ['AI / Machine Learning', 'ai_machine_learning machine_learning computer_vision generative_ai langchain mlops nlp prompt_engineering pytorch python'],
            'data_bi' => ['Data & BI', 'data_analysis statistical_analysis sql mysql database_design excel_advanced ops_analytics power_bi powerbi spreadsheet tableau research'],
            'english' => ['Tiếng Anh', 'toeic_800 toeic_850 english ielts'],
            'ui_ux' => ['Thiết kế UI/UX', 'ui_ux_design creative_design'],
            'marketing' => ['Marketing', 'digital_marketing content_marketing brand_management content_creator facebook_ads google_analytics market_analysis roi_analysis seo storytelling video_editing'],
            'soft_skills' => ['Kỹ năng mềm', 'communication leadership presentation_skills problem_solving teamwork'],
            'iot_embedded' => ['IoT / Embedded', 'iot embedded embedded_systems arduino'],
            'security' => ['An toàn thông tin', 'cyber_security cybersecurity information_security'],
            'business' => ['Quản trị / Kinh doanh', 'entrepreneurship business_analysis cost_accounting financial_reporting order_opt warehouse_mgmt financial_analysis accounting'],
            'physical' => ['Thể thao / Thể chất', 'sports_discipline'],
        ];
        $groupInsert = $pdo->prepare('INSERT INTO skill_groups (code,name,displayOrder) VALUES (?,?,?) ON DUPLICATE KEY UPDATE code=skill_groups.code');
        $memberInsert = $pdo->prepare('INSERT INTO skill_group_members (groupCode,skillId) SELECT ?,id FROM skills WHERE code=? ON DUPLICATE KEY UPDATE skillId=skill_group_members.skillId');
        $pdo->beginTransaction();
        try {
            $order = 0;
            foreach ($groups as $code => [$name, $members]) {
                $groupInsert->execute([$code, $name, ++$order]);
                foreach (explode(' ', $members) as $skillCode) $memberInsert->execute([$code, $skillCode]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    public function down(MigrationContext $context): void
    {
        throw new RuntimeException('Forward-only: preserve group grading data.');
    }
};
