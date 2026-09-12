<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\System;

use PDO;

final class MultidisciplinarySkillCatalogSeeder
{
    /** @var array<string, array{name: string, category: string}> */
    private const CATALOG = [
        'python' => ['name' => 'Lập trình Python', 'category' => 'technical'],
        'javascript' => ['name' => 'JavaScript', 'category' => 'technical'],
        'sql' => ['name' => 'SQL', 'category' => 'technical'],
        'problem_solving' => ['name' => 'Giải quyết vấn đề', 'category' => 'technical'],
        'entrepreneurship' => ['name' => 'Khởi nghiệp', 'category' => 'business'],
        'business_analysis' => ['name' => 'Phân tích nghiệp vụ', 'category' => 'business'],
        'digital_marketing' => ['name' => 'Digital Marketing', 'category' => 'marketing'],
        'content_marketing' => ['name' => 'Content Marketing', 'category' => 'marketing'],
        'ui_ux_design' => ['name' => 'Thiết kế UI/UX', 'category' => 'creative'],
        'creative_design' => ['name' => 'Thiết kế sáng tạo', 'category' => 'creative'],
        'communication' => ['name' => 'Giao tiếp', 'category' => 'soft'],
        'teamwork' => ['name' => 'Làm việc nhóm', 'category' => 'soft'],
        'leadership' => ['name' => 'Lãnh đạo', 'category' => 'soft'],
        'data_analysis' => ['name' => 'Phân tích dữ liệu', 'category' => 'data'],
        'statistical_analysis' => ['name' => 'Phân tích thống kê', 'category' => 'data'],
        'research' => ['name' => 'Nghiên cứu', 'category' => 'academic'],
        'academic_writing' => ['name' => 'Viết học thuật', 'category' => 'academic'],
        'financial_analysis' => ['name' => 'Phân tích tài chính', 'category' => 'finance'],
        'accounting' => ['name' => 'Kế toán', 'category' => 'finance'],
        'piano' => ['name' => 'Piano', 'category' => 'music'],
        'music_theory' => ['name' => 'Lý thuyết âm nhạc', 'category' => 'music'],
        'drawing' => ['name' => 'Vẽ', 'category' => 'arts'],
        'visual_arts' => ['name' => 'Nghệ thuật thị giác', 'category' => 'arts'],
    ];

    public function run(PDO $pdo): void
    {
        $select = $pdo->prepare('SELECT id FROM skills WHERE code = ? LIMIT 1');
        $insert = $pdo->prepare(
            "INSERT INTO skills (id, code, name, category, status, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );

        foreach (self::CATALOG as $code => $definition) {
            $select->execute([$code]);
            $existing = $select->fetchColumn();
            if (is_string($existing) && $existing !== '') {
                continue;
            }
            $insert->execute([
                self::stableId('catalog-skill:' . $code),
                $code,
                $definition['name'],
                $definition['category'],
            ]);
        }
    }

    private static function stableId(string $name): string
    {
        $hash = sha1($name, true);
        $hash[6] = chr((ord($hash[6]) & 0x0f) | 0x50);
        $hash[8] = chr((ord($hash[8]) & 0x3f) | 0x80);
        $hex = bin2hex(substr($hash, 0, 16));

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
