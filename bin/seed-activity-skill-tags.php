<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

$tables = $pdo->query("SHOW TABLES LIKE 'activity_skill_tags'")->fetchAll();
if ($tables === []) {
    fwrite(STDERR, "Table activity_skill_tags missing. Run migration first.\n");
    exit(1);
}

$aliases = [
    'python' => 'python',
    'ai' => 'ai_machine_learning',
    'machine learning' => 'machine_learning',
    'làm việc nhóm' => 'teamwork',
    'lam viec nhom' => 'teamwork',
    'teamwork' => 'teamwork',
    'react' => 'react',
    'api' => 'api_development',
    'rest api' => 'rest_api',
    'git' => 'git',
    'figma' => 'figma',
    'ui/ux' => 'ui_ux_design',
    'uiux' => 'ui_ux_design',
    'thiết kế ui/ux' => 'ui_ux_design',
    'thiet ke ui/ux' => 'ui_ux_design',
    'research' => 'research',
    'nghiên cứu' => 'research',
    'photoshop' => 'photoshop',
    'adobe photoshop' => 'photoshop',
    'illustrator' => 'illustrator',
    'adobe illustrator' => 'illustrator',
    'brand' => 'brand_management',
    'thương hiệu' => 'brand_management',
    'seo' => 'seo',
    'ads' => 'facebook_ads',
    'facebook ads' => 'facebook_ads',
    'content' => 'content_creator',
    'content marketing' => 'content_marketing',
    'social' => 'content_marketing',
    'pitching' => 'presentation_skills',
    'khởi nghiệp' => 'entrepreneurship',
    'khoi nghiep' => 'entrepreneurship',
    'power bi' => 'power_bi',
    'powerbi' => 'powerbi',
    'excel' => 'excel_advanced',
    'analytics' => 'data_analysis',
    'phân tích' => 'data_analysis',
    'logistics' => 'warehouse_mgmt',
    'ops' => 'ops_analytics',
    'bền vững' => 'problem_solving',
    'thuyết trình' => 'presentation_skills',
    'thuyet trinh' => 'presentation_skills',
    'phản biện' => 'communication',
    'networking' => 'teamwork',
    'cv' => 'communication',
    'interview' => 'presentation_skills',
    'giao tiếp' => 'communication',
    'giao tiep' => 'communication',
    'video' => 'video_editing',
    'storytelling' => 'storytelling',
    'pandas' => 'python',
    'data' => 'data_analysis',
    'kho vận' => 'warehouse_mgmt',
    'kho van' => 'warehouse_mgmt',
    'tối ưu' => 'order_opt',
    'toi uu' => 'order_opt',
    'leadership' => 'leadership',
    'lãnh đạo' => 'leadership',
    'community' => 'leadership',
    'lập trình frontend' => 'react',
    'lap trinh frontend' => 'react',
    'frontend' => 'react',
    'html & css' => 'html_css',
    'html/css' => 'html_css',
    'html' => 'html',
    'css' => 'css',
    'javascript' => 'javascript',
    'javascript cơ bản' => 'javascript',
    'js' => 'javascript',
];

$categoryDefaults = [
    'career_technical' => ['python', 'git', 'problem_solving'],
    'career_business' => ['digital_marketing', 'entrepreneurship', 'communication'],
    'career_arts' => ['ui_ux_design', 'content_creator', 'creative_design'],
    'career_sports_academic' => ['leadership', 'communication', 'teamwork'],
];

$skills = $pdo->query("SELECT id, code, name, category FROM skills WHERE status='active'")->fetchAll(PDO::FETCH_ASSOC);
$byCode = [];
$byNormName = [];
foreach ($skills as $skill) {
    $code = strtolower(trim((string) $skill['code']));
    $byCode[$code] = (string) $skill['id'];
    $byNormName[normalizeSkillLabel((string) $skill['name'])] = (string) $skill['id'];
    $byNormName[normalizeSkillLabel($code)] = (string) $skill['id'];
}

$activities = $pdo->query(
    "SELECT a.id, a.title, a.category, COALESCE(d.skillTags, '[]') AS skillTags
     FROM activities a
     LEFT JOIN activity_details d ON d.activityId = a.id"
)->fetchAll(PDO::FETCH_ASSOC);

$insert = $pdo->prepare(
    'INSERT INTO activity_skill_tags (id, activityId, skillId, createdAt)
     VALUES (:id, :activityId, :skillId, :createdAt)
     ON DUPLICATE KEY UPDATE skillId = VALUES(skillId)'
);
$updateTags = $pdo->prepare(
    'UPDATE activity_details SET skillTags = :skillTags, updatedAt = :updatedAt WHERE activityId = :activityId'
);
$hasDetails = $pdo->prepare('SELECT COUNT(*) FROM activity_details WHERE activityId = :activityId');

$now = gmdate('Y-m-d H:i:s.u');
$updated = 0;
$linked = 0;

$pdo->beginTransaction();
try {
    foreach ($activities as $activity) {
        $activityId = (string) $activity['id'];
        $tags = decodeTags($activity['skillTags'] ?? '[]');
        $skillIds = [];

        foreach ($tags as $tag) {
            $skillId = resolveSkillId($tag, $aliases, $byCode, $byNormName);
            if ($skillId !== null) {
                $skillIds[$skillId] = true;
            }
        }

        if ($skillIds === []) {
            foreach ($categoryDefaults[(string) ($activity['category'] ?? '')] ?? ['teamwork', 'communication'] as $code) {
                if (isset($byCode[$code])) {
                    $skillIds[$byCode[$code]] = true;
                }
            }
        }

        $pdo->prepare('DELETE FROM activity_skill_tags WHERE activityId = ?')->execute([$activityId]);
        $names = [];
        foreach (array_keys($skillIds) as $skillId) {
            $insert->execute([
                'id' => Uuid::v4(),
                'activityId' => $activityId,
                'skillId' => $skillId,
                'createdAt' => $now,
            ]);
            $linked++;
            foreach ($skills as $skill) {
                if ((string) $skill['id'] === $skillId) {
                    $names[] = (string) $skill['name'];
                    break;
                }
            }
        }

        $hasDetails->execute(['activityId' => $activityId]);
        if ((int) $hasDetails->fetchColumn() === 1) {
            $updateTags->execute([
                'skillTags' => json_encode(array_values($names), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updatedAt' => $now,
                'activityId' => $activityId,
            ]);
        }
        $updated++;
        echo sprintf(
            "[OK] %s -> %d skills (%s)\n",
            $activity['title'] ?: $activityId,
            count($skillIds),
            implode(', ', $names)
        );
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}

echo "\nDone. activities={$updated} skill_links={$linked}\n";

function normalizeSkillLabel(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return $value;
}

/** @return list<string> */
function decodeTags(mixed $raw): array
{
    if (is_string($raw)) {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }
        $raw = $decoded;
    }
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $item) {
        if (!is_scalar($item)) {
            continue;
        }
        $text = trim((string) $item);
        if ($text !== '') {
            $out[] = $text;
        }
    }
    return $out;
}

/**
 * @param array<string,string> $aliases
 * @param array<string,string> $byCode
 * @param array<string,string> $byNormName
 */
function resolveSkillId(string $tag, array $aliases, array $byCode, array $byNormName): ?string
{
    $norm = normalizeSkillLabel($tag);
    if (isset($aliases[$norm]) && isset($byCode[$aliases[$norm]])) {
        return $byCode[$aliases[$norm]];
    }
    if (isset($byCode[$norm])) {
        return $byCode[$norm];
    }
    if (isset($byNormName[$norm])) {
        return $byNormName[$norm];
    }
    foreach ($byNormName as $name => $id) {
        if (str_contains($name, $norm) || str_contains($norm, $name)) {
            return $id;
        }
    }
    foreach ($byCode as $code => $id) {
        if (str_contains($code, str_replace([' ', '/', '&'], ['_', '_', ''], $norm))) {
            return $id;
        }
    }
    return null;
}
