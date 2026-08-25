<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$pages = [
    'account.php',
    'class-edit.php',
    'reports.php',
    'settings.php',
    'student-edit.php',
    'teacher-edit.php',
    'teachers.php',
    'partnerships.php',
    'projects.php',
    'internships.php',
];

$firstMutation = [
    'account.php' => 'changePassword(',
    'class-edit.php' => 'archiveClass(',
    'reports.php' => 'generateReport(',
    'settings.php' => 'uploadLogo(',
    'student-edit.php' => 'createStudent(',
    'teacher-edit.php' => 'updateTeacherProfile(',
    'teachers.php' => 'inviteTeacher(',
    'partnerships.php' => 'reviewPartnership(',
    'projects.php' => 'createProject(',
    'internships.php' => 'assignInternshipMentor(',
];

foreach ($pages as $page) {
    $path = dirname(__DIR__) . '/app/school/' . $page;
    $contents = file_get_contents($path);
    $assert($contents !== false, "Unable to read {$page}.");

    $formCount = preg_match_all('/<form\\s+method=["\\\']post["\\\']/', $contents);
    $tokenCount = substr_count($contents, 'name="csrfToken"');
    $assert($formCount === $tokenCount, "Every POST form in {$page} must include a CSRF field.");
    $assert(substr_count($contents, 'assertCsrf(') >= 1, "{$page} must validate CSRF before writing.");
    $csrfPosition = strpos($contents, 'assertCsrf(');
    $mutationPosition = strpos($contents, $firstMutation[$page]);
    $assert($csrfPosition !== false && $mutationPosition !== false && $csrfPosition < $mutationPosition,
        "{$page} must validate CSRF before its first mutation.");
}

$teacherEdit = file_get_contents(dirname(__DIR__) . '/app/school/teacher-edit.php');
$assert($teacherEdit !== false, 'Unable to read teacher-edit.php.');
$assert(!str_contains($teacherEdit, 'UPDATE teacher_profiles'), 'Teacher edit page must not write directly to PDO.');
$assert(str_contains($teacherEdit, 'updateTeacherProfile('), 'Teacher edit page must use the school service.');

$service = file_get_contents(dirname(__DIR__) . '/src/Modules/School/Service/SchoolDashboardService.php');
$assert($service !== false, 'Unable to read SchoolDashboardService.php.');
$assert(str_contains($service, 'public function updateTeacherProfile('), 'School service must own teacher profile updates.');
$assert(str_contains($service, '$this->guardWrite($userId, $school[\'id\']);'), 'Teacher profile updates must require school write access.');

$repository = file_get_contents(dirname(__DIR__) . '/src/Modules/School/Repository/SchoolRepository.php');
$assert($repository !== false, 'Unable to read SchoolRepository.php.');
$assert(str_contains($repository, 'WHERE id = :id AND schoolId = :schoolId'), 'Teacher profile update must be scoped to the current school.');
$assert(str_contains($service, "'TEACHER_PROFILE_UPDATE'"), 'Teacher profile update must create an audit event.');

echo "school_portal_post_csrf_contract_test: OK\n";
