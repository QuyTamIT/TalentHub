<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bin/bootstrap.php';

use TalentHub\Modules\Teacher\Repository\TeacherGradingRepository;
use TalentHub\Modules\Teacher\Service\TeacherGradingService;
use TalentHub\Support\Uuid;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
};

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec(<<<'SQL'
CREATE TABLE users (
    id TEXT PRIMARY KEY,
    fullName TEXT NOT NULL,
    email TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active'
);
CREATE TABLE schools (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE teacher_profiles (
    id TEXT PRIMARY KEY,
    userId TEXT NOT NULL,
    schoolId TEXT NOT NULL,
    isSchoolAdmin INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE classes (
    id TEXT PRIMARY KEY,
    schoolId TEXT NOT NULL,
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active'
);
CREATE TABLE teacher_class_assignments (
    teacherId TEXT NOT NULL,
    classId TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active'
);
CREATE TABLE student_profiles (
    id TEXT PRIMARY KEY,
    userId TEXT NOT NULL,
    classId TEXT NOT NULL,
    studyStatus TEXT NOT NULL DEFAULT 'active'
);
CREATE TABLE assessment_criteria (
    id TEXT PRIMARY KEY,
    code TEXT NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    minScore REAL NOT NULL DEFAULT 0,
    maxScore REAL NOT NULL,
    displayOrder INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'active'
);
CREATE TABLE assessments (
    id TEXT PRIMARY KEY,
    teacherId TEXT NOT NULL,
    studentId TEXT NOT NULL,
    activityId TEXT NULL,
    classId TEXT NULL,
    projectId TEXT NULL,
    overallScore REAL NULL,
    comment TEXT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    publishedAt TEXT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE assessment_scores (
    id TEXT PRIMARY KEY,
    assessmentId TEXT NOT NULL,
    criteriaId TEXT NOT NULL,
    score REAL NOT NULL,
    UNIQUE(assessmentId, criteriaId)
);
CREATE TABLE skills (
    id TEXT PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    category TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE student_skills (
    id TEXT PRIMARY KEY,
    studentId TEXT NOT NULL,
    skillId TEXT NOT NULL,
    levelScore REAL NOT NULL,
    sourceType TEXT NOT NULL,
    verificationStatus TEXT NOT NULL DEFAULT 'self_declared',
    verifiedAt TEXT NULL,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(studentId, skillId, sourceType)
);
CREATE TABLE learner_evaluations (
    id TEXT PRIMARY KEY,
    seriesId TEXT NOT NULL,
    revision INTEGER NOT NULL DEFAULT 1,
    studentId TEXT NOT NULL,
    teacherId TEXT NOT NULL,
    legacyAssessmentId TEXT NULL,
    contextType TEXT NOT NULL DEFAULT 'general',
    contextId TEXT NULL,
    overallScore REAL NULL,
    comment TEXT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    publishedAt TEXT NULL,
    actorUserId TEXT NOT NULL,
    eventKey TEXT NULL UNIQUE,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE learner_evaluation_items (
    id TEXT PRIMARY KEY,
    evaluationId TEXT NOT NULL,
    itemKind TEXT NOT NULL,
    itemCode TEXT NOT NULL,
    skillId TEXT NULL,
    label TEXT NOT NULL,
    score REAL NULL,
    maxScore REAL NULL,
    confirmed INTEGER NOT NULL DEFAULT 0,
    comment TEXT NULL,
    createdAt TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(evaluationId, itemKind, itemCode)
);
SQL);

$ids = [
    'school' => Uuid::v4(),
    'otherSchool' => Uuid::v4(),
    'teacherUser' => Uuid::v4(),
    'teacher' => Uuid::v4(),
    'studentUser' => Uuid::v4(),
    'student' => Uuid::v4(),
    'assignedClass' => Uuid::v4(),
    'otherClass' => Uuid::v4(),
    'foreignClass' => Uuid::v4(),
    'criterion' => Uuid::v4(),
    'python' => Uuid::v4(),
];

$pdo->prepare('INSERT INTO schools (id, name) VALUES (?, ?)')->execute([$ids['school'], 'School A']);
$pdo->prepare('INSERT INTO schools (id, name) VALUES (?, ?)')->execute([$ids['otherSchool'], 'School B']);
$pdo->prepare('INSERT INTO users (id, fullName, email) VALUES (?, ?, ?)')->execute([$ids['teacherUser'], 'Teacher A', 'teacher@test']);
$pdo->prepare('INSERT INTO users (id, fullName, email) VALUES (?, ?, ?)')->execute([$ids['studentUser'], 'Student A', 'student@test']);
$pdo->prepare('INSERT INTO teacher_profiles (id, userId, schoolId) VALUES (?, ?, ?)')->execute([$ids['teacher'], $ids['teacherUser'], $ids['school']]);
$pdo->prepare('INSERT INTO classes (id, schoolId, name, status) VALUES (?, ?, ?, ?)')->execute([$ids['assignedClass'], $ids['school'], '12A', 'active']);
$pdo->prepare('INSERT INTO classes (id, schoolId, name, status) VALUES (?, ?, ?, ?)')->execute([$ids['otherClass'], $ids['school'], '12B', 'active']);
$pdo->prepare('INSERT INTO classes (id, schoolId, name, status) VALUES (?, ?, ?, ?)')->execute([$ids['foreignClass'], $ids['otherSchool'], '12C', 'active']);
$pdo->prepare('INSERT INTO teacher_class_assignments (teacherId, classId, status) VALUES (?, ?, ?)')->execute([$ids['teacher'], $ids['assignedClass'], 'active']);
$pdo->prepare('INSERT INTO student_profiles (id, userId, classId) VALUES (?, ?, ?)')->execute([$ids['student'], $ids['studentUser'], $ids['assignedClass']]);
$pdo->prepare('INSERT INTO assessment_criteria (id, code, name, minScore, maxScore, status) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$ids['criterion'], 'knowledge', 'Kiến thức', 0, 10, 'active']);
$pdo->prepare('INSERT INTO skills (id, code, name, category, status) VALUES (?, ?, ?, ?, ?)')
    ->execute([$ids['python'], 'python', 'Lập trình Python', 'technical', 'active']);

$repository = new TeacherGradingRepository($pdo);
$service = new TeacherGradingService($repository);

$classes = $repository->classes($ids['teacher']);
$assert(count($classes) === 2, 'classes() returns every active class of the school');
$assert((int) $classes[0]['isAssigned'] === 1, 'assigned class is listed first');
$assert($classes[0]['id'] === $ids['assignedClass'], 'assigned class id matches');
$assert($repository->contextForTeacher($ids['teacher'], 'class', $ids['otherClass']) !== null, 'same-school unassigned class is in scope');
$assert($repository->contextForTeacher($ids['teacher'], 'class', $ids['foreignClass']) === null, 'foreign school class stays forbidden');
$assert($repository->studentInContext($ids['teacher'], 'class', $ids['assignedClass'], $ids['student']) === true, 'student belongs to selected class of same school');

$page = $service->pageData($ids['teacherUser'], $ids['assignedClass'], '', 'class');
$assert(isset($page['availableSkills']), 'pageData exposes availableSkills');
$assert(count($page['availableSkills']) === 1, 'active catalog skill is listed');
$assert(isset($page['students'][0]['studentSkills']), 'each student carries current skills');

$service->save($ids['teacherUser'], [
    'mode' => 'class',
    'contextId' => $ids['assignedClass'],
    'studentId' => $ids['student'],
    'expectedVersion' => '0',
    'overallScore' => '82.50',
    'comment' => 'Rubric plus skills',
    'assessmentStatus' => 'published',
    'criteria' => [$ids['criterion'] => '8.00'],
    'skills' => [
        ['skillId' => $ids['python'], 'score' => '88.5', 'category' => 'technical'],
        ['skillName' => 'Piano Performance', 'score' => 71, 'category' => 'music'],
    ],
]);

$assessment = $pdo->query('SELECT * FROM assessments')->fetch();
$assert(is_array($assessment), 'assessment row exists');
$assert((string) $assessment['status'] === 'published', 'assessment published');
$assert((float) $assessment['overallScore'] === 82.5, 'overall score stored');

$teacherSkills = $pdo->prepare("SELECT ss.*, s.code FROM student_skills ss JOIN skills s ON s.id = ss.skillId WHERE ss.studentId = ? AND ss.sourceType = 'teacher' ORDER BY s.code");
$teacherSkills->execute([$ids['student']]);
$rows = $teacherSkills->fetchAll();
$assert(count($rows) === 2, 'two teacher-verified student skills');
$assert((string) $rows[0]['verificationStatus'] === 'verified' && $rows[0]['verifiedAt'] !== null, 'python skill is verified');
$assert((string) $rows[1]['code'] === 'python', 'existing skill reused');
$assert((float) $rows[1]['levelScore'] === 88.5, 'existing skill score stored');
$assert((string) $rows[0]['code'] === 'piano_performance', 'new skill code generated');
$assert((float) $rows[0]['levelScore'] === 71.0, 'new skill score stored');

$newSkill = $pdo->query("SELECT category, status FROM skills WHERE code = 'piano_performance'")->fetch();
$assert(is_array($newSkill) && $newSkill['category'] === 'music' && $newSkill['status'] === 'active', 'new skill inserted into catalog');

$items = $pdo->query("SELECT itemKind, skillId, score, maxScore, confirmed FROM learner_evaluation_items ORDER BY itemCode")->fetchAll();
$assert(count($items) === 2, 'evaluation items cover both skills');
foreach ($items as $item) {
    $assert($item['itemKind'] === 'skill', 'itemKind is skill');
    $assert((int) $item['confirmed'] === 1, 'item is confirmed');
    $assert((float) $item['maxScore'] === 100.0, 'maxScore is 100');
}

$refreshed = $service->pageData($ids['teacherUser'], $ids['assignedClass'], '', 'class');
$assert(count($refreshed['students'][0]['studentSkills']) === 2, 'saved skills round-trip into the grading form');

echo "teacher_grading_school_skills_test: PASS\n";
