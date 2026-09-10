<?php
declare(strict_types=1);

use TalentHub\Learner\Data\Migrations\ForwardMigrationDefinition;
use TalentHub\Learner\Data\Migrations\LearnerForwardMigration;

return new ForwardMigrationDefinition(
    '021_create_learner_portfolio_reports',
    'Create learner portfolio reports and immutable evidence history',
    __FILE__,
    hash_file('sha256', __FILE__),
    new class implements LearnerForwardMigration {
        public function version(): string { return '021_create_learner_portfolio_reports'; }
        public function description(): string { return 'Create learner portfolio reports and immutable evidence history'; }
        public function statements(string $driver): array
        {
            $sqlite = strtolower($driver) === 'sqlite';
            $id = $sqlite ? 'TEXT' : 'CHAR(36)';
            $text = $sqlite ? 'TEXT' : 'LONGTEXT';
            $engine = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
            $timestamp = $sqlite ? 'TEXT' : 'DATETIME(6)';
            $tables = [];
            foreach (['project_submissions' => 'projectId', 'learner_internship_reports' => 'applicationId'] as $table => $context) {
                $hours = $sqlite ? 'REAL' : 'DECIMAL(8,2)';
                $tables[] = "CREATE TABLE {$table} (id {$id} NOT NULL PRIMARY KEY, studentId {$id} NOT NULL, {$context} {$id} NOT NULL, version INTEGER NOT NULL, revision INTEGER NOT NULL, status VARCHAR(32) NOT NULL, notes {$text} NOT NULL, repositoryUrl VARCHAR(1000) NULL, demoUrl VARCHAR(1000) NULL, startDate DATE NULL, endDate DATE NULL, hours {$hours} NULL, stage VARCHAR(32) NULL, submittedAt {$timestamp} NULL, reviewedAt {$timestamp} NULL, reviewedByUserId {$id} NULL, feedback VARCHAR(2000) NULL, updatedAt {$timestamp} NOT NULL, UNIQUE(studentId, {$context}), CHECK(version >= 1), CHECK(revision >= 1), CHECK(status IN ('draft','submitted','changes_requested','verified','revoked')), CHECK(stage IS NULL OR stage IN ('active','completed')), CHECK(hours IS NULL OR (hours >= 0 AND hours <= 10000))){$engine}";
                $tables[] = "CREATE INDEX idx_{$table}_student_status ON {$table} (studentId,status)";
            }
            $tables[] = "CREATE TABLE learner_portfolio_history (id {$id} NOT NULL PRIMARY KEY, kind VARCHAR(16) NOT NULL, reportId {$id} NOT NULL, version INTEGER NOT NULL, status VARCHAR(32) NOT NULL, actorUserId {$id} NOT NULL, snapshotJson {$text} NOT NULL, createdAt {$timestamp} NOT NULL, UNIQUE(kind,reportId,version), CHECK(kind IN ('project','internship'))){$engine}";
            $tables[] = 'CREATE INDEX idx_portfolio_history_report ON learner_portfolio_history (kind,reportId,version)';
            $tables[] = "CREATE TABLE learner_portfolio_skills (kind VARCHAR(16) NOT NULL, reportId {$id} NOT NULL, skillId {$id} NOT NULL, PRIMARY KEY(kind,reportId,skillId), CHECK(kind IN ('project','internship'))){$engine}";
            $tables[] = 'CREATE INDEX idx_portfolio_skills_skill ON learner_portfolio_skills (skillId)';
            return $tables;
        }
        public function expectedSchema(): array
        {
            $reportColumns = ['id','studentId','version','revision','status','notes','repositoryUrl','demoUrl','startDate','endDate','hours','stage','submittedAt','reviewedAt','reviewedByUserId','feedback','updatedAt'];
            return [
                'project_submissions'=>['columns'=>array_merge($reportColumns,['projectId']),'indexes'=>['idx_project_submissions_student_status']],
                'learner_internship_reports'=>['columns'=>array_merge($reportColumns,['applicationId']),'indexes'=>['idx_learner_internship_reports_student_status']],
                'learner_portfolio_history'=>['columns'=>['id','kind','reportId','version','status','actorUserId','snapshotJson','createdAt'],'indexes'=>['idx_portfolio_history_report']],
                'learner_portfolio_skills'=>['columns'=>['kind','reportId','skillId'],'indexes'=>['idx_portfolio_skills_skill']],
            ];
        }
    }
);
