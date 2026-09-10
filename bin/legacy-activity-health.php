<?php
declare(strict_types=1);

/**
 * CLI script: reports configuration health for activities that lack the
 * required sub-records (activity_details, activity_registration_policies,
 * activity_experience_policies).
 *
 * Usage:
 *   php bin/legacy-activity-health.php
 *
 * This is read-only. It does NOT backfill or fix anything.
 * It reports the count and IDs of affected activities so the operator
 * can decide how to handle them through the admin UI.
 *
 * Plan requirement: "thêm cảnh báo sức khỏe cấu hình và script báo cáo
 * chỉ đọc, không backfill tự động."
 */

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Bootstrap\Application;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new TalentHub\Database\Connection($config))->connect();

$sql = "
    SELECT
        a.id,
        a.title,
        a.status,
        a.createdByTeacherId,
        a.startAt,
        a.endAt,
        CASE WHEN ad.id IS NULL THEN 0 ELSE 1 END AS has_details,
        CASE WHEN arp.activityId IS NULL THEN 0 ELSE 1 END AS has_reg_policy,
        CASE WHEN aep.activityId IS NULL THEN 0 ELSE 1 END AS has_exp_policy
    FROM activities a
    LEFT JOIN activity_details ad ON ad.activityId = a.id
    LEFT JOIN activity_registration_policies arp ON arp.activityId = a.id
    LEFT JOIN activity_experience_policies aep ON aep.activityId = a.id
    WHERE a.status IN ('draft', 'published', 'ongoing', 'completed')
      AND (ad.id IS NULL OR arp.activityId IS NULL OR aep.activityId IS NULL)
    ORDER BY a.createdAt DESC
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll();

echo "=== Legacy Activity Configuration Health Report ===\n";
echo "Generated at: " . date('Y-m-d H:i:s T') . "\n\n";

if ($rows === []) {
    echo "✅ All active activities have complete configuration.\n";
    exit(0);
}

$total = count($rows);
$missingDetails = 0;
$missingRegPolicy = 0;
$missingExpPolicy = 0;

foreach ($rows as $row) {
    if ((int) $row['has_details'] === 0) $missingDetails++;
    if ((int) $row['has_reg_policy'] === 0) $missingRegPolicy++;
    if ((int) $row['has_exp_policy'] === 0) $missingExpPolicy++;
}

echo "⚠️  Found {$total} activity(ies) with incomplete configuration:\n";
echo "   - Missing activity_details:           {$missingDetails}\n";
echo "   - Missing activity_registration_policies: {$missingRegPolicy}\n";
echo "   - Missing activity_experience_policies:  {$missingExpPolicy}\n\n";

echo "ID | Status | Title | Details | RegPolicy | ExpPolicy\n";
echo str_repeat('-', 120) . "\n";

foreach ($rows as $row) {
    $title = mb_strlen($row['title'] ?? '') > 40
        ? mb_substr($row['title'], 0, 37) . '...'
        : ($row['title'] ?? '(no title)');
    echo sprintf(
        "%s | %-12s | %-40s | %-7s | %-9s | %-9s\n",
        substr($row['id'], 0, 8),
        $row['status'] ?? 'unknown',
        $title,
        (int) $row['has_details'] ? 'yes' : 'NO',
        (int) $row['has_reg_policy'] ? 'yes' : 'NO',
        (int) $row['has_exp_policy'] ? 'yes' : 'NO',
    );
}

echo "\nAffected IDs:\n";
foreach ($rows as $row) {
    echo "  " . $row['id'] . "\n";
}

echo "\n--- End of report ---\n";
echo "This script is read-only. No data has been modified.\n";
exit(0);
