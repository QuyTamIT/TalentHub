<?php
declare(strict_types=1);

$source = (string) file_get_contents(dirname(__DIR__) . '/app/learner/includes/student-data.php');
if (!str_contains($source, "\$dbSkill['evidenceLabel'] ?? \$dbSkill['evidence_label']")) {
    throw new RuntimeException('Student skill presentation must read the repository evidence label.');
}
if (!str_contains($source, "\$score === null && \$evidenceLabel !== ''")) {
    throw new RuntimeException('Scoreless skills must prefer their source-aware evidence label.');
}
echo "learner_scoreless_skill_label_test: OK\n";
