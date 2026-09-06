<?php
declare(strict_types=1);

namespace TalentHub\Modules\Student;

use PDO;
use TalentHub\Learner\Api\LearnerApiContext;

/**
 * Service to manage student dashboard data, badge progress calculation, and skills profile.
 */
class DashboardService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Calculate and retrieve realistic badge and credential progress for a student.
     *
     * @return array<string, mixed>
     */
    public function getBadgesAndCredentials(string $studentId): array
    {
        $context = new LearnerApiContext($this->pdo);
        learner_configure_data(['source' => 'database', 'pdo' => $this->pdo]);

        return learner_repository_factory()
            ->schoolCredentialService()
            ->forStudent($studentId);
    }

    /**
     * Get verified skills profile for the student with normalized Vietnamese names and colors.
     *
     * @return list<array<string, mixed>>
     */
    public function getSkillsProfile(string $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.name, s.code, s.category, ss.levelScore as score, ss.verificationStatus
            FROM student_skills ss
            JOIN skills s ON s.id = ss.skillId
            WHERE ss.studentId = ?
            ORDER BY ss.levelScore DESC
        ");
        $stmt->execute([$studentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $skillNameMap = [
            'machine_learning' => 'Học máy (Machine Learning)',
            'ai_machine_learning' => 'Trí tuệ Nhân tạo & ML',
            'ai_ml' => 'Trí tuệ Nhân tạo & ML',
            'data_analysis' => 'Phân tích dữ liệu (Data Analysis)',
            'teamwork' => 'Kỹ năng làm việc nhóm (Teamwork)',
            'python' => 'Lập trình Python',
            'iot' => 'Internet of Things (IoT)',
            'computer_vision' => 'Thị giác máy tính (Computer Vision)',
            'deep_learning' => 'Học sâu (Deep Learning)',
            'pytorch' => 'PyTorch & Deep Learning',
            'docker' => 'Docker & Containerization',
            'git' => 'Quản lý mã nguồn Git',
            'mysql' => 'Cơ sở dữ liệu MySQL',
            'communication' => 'Giao tiếp & Thuyết trình',
            'problem_solving' => 'Giải quyết vấn đề',
            'critical_thinking' => 'Tư duy phản biện',
            'ui_ux' => 'Thiết kế UI/UX',
        ];

        $skills = [];
        foreach ($rows as $r) {
            $rawName = trim((string) ($r['name'] ?? ''));
            $rawCode = trim((string) ($r['code'] ?? ''));
            $codeKey = strtolower($rawCode);
            $nameKey = strtolower($rawName);

            $displayName = $skillNameMap[$codeKey] ?? $skillNameMap[$nameKey] ?? $rawName;
            $score = max(0, min(100, (int) round((float) ($r['score'] ?? 0))));

            $category = strtolower((string) ($r['category'] ?? ''));
            if ($category === '' && in_array($codeKey, ['teamwork', 'communication'], true)) {
                $category = 'soft';
            }

            $tone = match ($category) {
                'technical' => 'primary',
                'soft' => 'success',
                'creative' => 'secondary',
                default => in_array($codeKey, ['teamwork', 'communication'], true) ? 'success' : 'primary',
            };

            $color = match ($tone) {
                'success' => '#10B981',
                'primary' => '#F97316',
                'secondary' => '#6366F1',
                'warning' => '#F59E0B',
                default => '#10B981',
            };

            $skills[] = [
                'name' => $displayName,
                'short_name' => $displayName,
                'score' => $score,
                'level' => $score >= 85 ? 'Rất tốt' : ($score >= 70 ? 'Tốt' : 'Trung bình'),
                'tone' => $tone,
                'color' => $color,
                'verified' => ($r['verificationStatus'] ?? '') === 'verified',
            ];
        }

        return $skills;
    }
}
