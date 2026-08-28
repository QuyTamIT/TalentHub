<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

echo "Updating badge_rule_definitions...\n";

$badgeRules = [
    'school_da811c4f_badge_profile_complete' => ['fact' => 'submitted_assessment_type_count', 'value' => 4, 'operator' => 'gte'],
    'school_da811c4f_badge_reliable_organizer' => ['fact' => 'attended_activity_count', 'value' => 3, 'operator' => 'gte'],
    'school_da811c4f_badge_project_pioneer' => ['fact' => 'confirmed_experience_hours', 'value' => 20, 'operator' => 'gte'],
    'school_da811c4f_badge_community_connector' => ['fact' => 'attended_activity_count', 'value' => 5, 'operator' => 'gte'],
    'school_da811c4f_badge_creative_ideator' => ['fact' => 'confirmed_experience_hours', 'value' => 12, 'operator' => 'gte'],
    'school_da811c4f_badge_analytical_thinker' => ['fact' => 'confirmed_experience_hours', 'value' => 15, 'operator' => 'gte'],
];

foreach ($badgeRules as $code => $criteria) {
    $stmt = $pdo->prepare("
        UPDATE badge_rule_definitions brd
        JOIN badges b ON b.id = brd.badgeId
        SET brd.thresholdCriteria = :criteria
        WHERE b.code = :code
    ");
    $stmt->execute([
        'criteria' => json_encode($criteria),
        'code' => $code,
    ]);
    echo "Updated rule for {$code}\n";
}

// Remove fake awarded badges from student_badges that did not meet the criteria
echo "Cleaning up premature student_badges records...\n";
$cleanupCodes = [
    'school_da811c4f_badge_reliable_organizer',
    'school_da811c4f_badge_project_pioneer',
    'school_da811c4f_badge_community_connector',
    'school_da811c4f_badge_creative_ideator',
    'school_da811c4f_badge_analytical_thinker',
];
$inClause = "'" . implode("','", $cleanupCodes) . "'";
$pdo->exec("
    DELETE sb FROM student_badges sb
    JOIN badges b ON b.id = sb.badgeId
    WHERE b.code IN ($inClause)
");

echo "Done updating badge rules and cleaning up.\n";
