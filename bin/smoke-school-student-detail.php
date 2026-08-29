<?php
/**
 * Smoke Test: School Student Detail Modal & Interactive Features
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Database\Connection;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();

echo "=======================================================\n";
echo "1. VERIFY STUDENT DETAIL FORWARDER (student-detail.php)\n";
echo "=======================================================\n";
assert(file_exists(dirname(__DIR__) . '/app/school/student-detail.php'), 'student-detail.php must exist');
echo "[PASS] app/school/student-detail.php exists.\n";

echo "\n=======================================================\n";
echo "2. TEST BTEC FPT STUDENTS PAGE & DETAIL MODAL STRUCTURE\n";
echo "=======================================================\n";
$btecUser = $pdo->query("SELECT id, email, fullName, roleId FROM users WHERE email = 'btec@talenthub.local' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assert(!empty($btecUser), 'btec@talenthub.local must exist');

$_SESSION = [];
$_SESSION['user_id'] = $btecUser['id'];
$_SESSION['email'] = $btecUser['email'];
$_SESSION['role'] = 'school';
$_SESSION['logged_in'] = true;
$_SESSION['user'] = [
    'id' => $btecUser['id'],
    'email' => $btecUser['email'],
    'fullName' => $btecUser['fullName'],
    'role' => 'school',
    'status' => 'active',
];

ob_start();
require dirname(__DIR__) . '/app/school/students.php';
$html = ob_get_clean();

// Assert clickable links and buttons
assert(str_contains($html, 'school-student-name-link'), 'Must contain clickable student name links');
assert(str_contains($html, '<span>Chi tiết</span>'), 'Must contain "Chi tiết" buttons');
assert(str_contains($html, 'openStudentDetail('), 'Must call openStudentDetail on click');

// Assert modal markup
assert(str_contains($html, 'id="studentDetailModal"'), 'Must contain studentDetailModal');
assert(str_contains($html, 'id="sdModalTitle"'), 'Must contain sdModalTitle');
assert(str_contains($html, 'id="sd_avatar"'), 'Must contain sd_avatar');
assert(str_contains($html, 'id="sd_code"'), 'Must contain sd_code');
assert(str_contains($html, 'id="sd_headline"'), 'Must contain sd_headline');
assert(str_contains($html, 'id="sd_studyStatus"'), 'Must contain sd_studyStatus');
assert(str_contains($html, 'id="sd_internshipStatus"'), 'Must contain sd_internshipStatus');
assert(str_contains($html, 'id="sd_school"'), 'Must contain sd_school');
assert(str_contains($html, 'id="sd_class"'), 'Must contain sd_class');
assert(str_contains($html, 'id="sd_score"'), 'Must contain sd_score');
assert(str_contains($html, 'id="sd_score_bar"'), 'Must contain sd_score_bar');
assert(str_contains($html, 'id="sd_skills_container"'), 'Must contain sd_skills_container');
assert(str_contains($html, 'id="sd_bio"'), 'Must contain sd_bio');
assert(str_contains($html, 'id="sd_edit_btn"'), 'Must contain sd_edit_btn');

// Assert student payload elements
assert(str_contains($html, 'BTEC-'), 'Must contain student codes with school prefix BTEC-');
assert(str_contains($html, 'talentScore'), 'Must contain talentScore in payload');
assert(str_contains($html, 'skills'), 'Must contain skills in payload');
assert(str_contains($html, 'S\\u1eb5n s\\u00e0ng th\\u1ef1c t\\u1eadp') || str_contains($html, 'Sẵn sàng thực tập'), 'Must contain internship status in payload');

echo "[PASS] BTEC FPT students page rendered with complete detail modal and interactive triggers.\n";

echo "\n=======================================================\n";
echo "3. VERIFY TOP STUDENT WITH DATABASE SKILLS (e.g. Trần Minh Đức)\n";
echo "=======================================================\n";
assert(str_contains($html, 'Trần Minh Đức'), 'Must list Trần Minh Đức');
assert(str_contains($html, 'BTEC-AI-2026A'), 'Must show class BTEC-AI-2026A');
assert(str_contains($html, 'Python') || str_contains($html, 'Machine Learning'), 'Must contain real skills for student');

echo "[PASS] Real database skills and headlines correctly bound to modal payload.\n";

echo "\n=======================================================\n";
echo "ALL STUDENT DETAIL TESTS PASSED SUCCESSFULLY!\n";
echo "=======================================================\n";
