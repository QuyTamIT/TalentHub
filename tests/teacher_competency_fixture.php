<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once dirname(__DIR__) . '/app/learner/data/bootstrap.php';

use TalentHub\Database\Connection;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Support\Uuid;

/** Creates a NEW database only. Never accepts an existing database as a write target. */
function competencyFixture(): array
{
    $root = dirname(__DIR__);
    $config = require $root . '/config/database.php';
    if (getenv('RUBRIC_TEST_PORT') !== false) {
        $port = filter_var(getenv('RUBRIC_TEST_PORT'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1024,'max_range'=>65535]]);
        if ($port === false || !is_dir((string)getenv('RUBRIC_TEST_DATADIR'))) throw new RuntimeException('Invalid isolated server configuration.');
        $config = array_replace($config,['host'=>'127.0.0.1','port'=>$port,'username'=>'root','password'=>'']);
    }
    if (!in_array($config['host'], ['127.0.0.1','localhost','::1'], true)) {
        throw new RuntimeException('Disposable tests require a local database server.');
    }
    $name = 'talenthub_rubric_test_' . gmdate('YmdHis') . '_' . bin2hex(random_bytes(4));
    if ($name === $config['database'] || !preg_match('/\Atalenthub_rubric_test_[0-9]{14}_[a-f0-9]{8}\z/', $name)) {
        throw new RuntimeException('Unsafe disposable name.');
    }
    $server = new PDO("mysql:host={$config['host']};port={$config['port']};charset=utf8mb4", $config['username'], $config['password'], $config['options']);
    if (getenv('RUBRIC_TEST_PORT') !== false) {
        $actual = $server->query('SELECT @@datadir')->fetchColumn();
        if (realpath((string)$actual) !== realpath((string)getenv('RUBRIC_TEST_DATADIR'))) throw new RuntimeException('Disposable server data directory mismatch.');
    }
    $s = $server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
    $s->execute([$name]);
    if ((int) $s->fetchColumn() !== 0) throw new RuntimeException('Disposable already exists; refusing reuse.');
    $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $config['database'] = $name;
    $pdo = (new Connection($config))->connect();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== $name) throw new RuntimeException('Wrong selected database.');
    if ((int) $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn() !== 0) {
        throw new RuntimeException('Disposable must start empty.');
    }
    echo "DISPOSABLE verified: {$config['host']}:{$config['port']}/$name; empty schema; fresh random name\n";
    // Load ONLY CREATE TABLE statements from the checked-in baseline. Never import its INSERT data.
    preg_match_all('/^CREATE TABLE `[^`]+` \([\s\S]+?;\s*$/m', (string) file_get_contents($root . '/Database/Talenthub.sql'), $ddl);
    if (count($ddl[0]) < 20) throw new RuntimeException('Baseline DDL extraction failed.');
    $pdo->exec('SET SESSION FOREIGN_KEY_CHECKS=0');
    try {
        foreach ($ddl[0] as $sql) $pdo->exec($sql);
    } finally {
        $pdo->exec('SET SESSION FOREIGN_KEY_CHECKS=1');
    }
    $token = bin2hex(random_bytes(24));
    $pdo->exec('CREATE TABLE competency_disposable_marker (token CHAR(48) PRIMARY KEY)');
    $pdo->prepare('INSERT INTO competency_disposable_marker VALUES (?)')->execute([$token]);
    $insert = static function (string $table, array $row) use ($pdo): void {
        $columns = implode(',', array_keys($row));
        $values = implode(',', array_fill(0,count($row),'?'));
        $pdo->prepare("INSERT INTO $table ($columns) VALUES ($values)")->execute(array_values($row));
    };
    $ids = [];
    foreach (['school','otherSchool','class','otherClass','unassignedClass','teacherRole','studentRole','teacherUser','otherTeacherUser','missingTeacherUser','studentUser','otherStudentUser','secondStudentUser','teacher','otherTeacher','student','otherStudent','secondStudent','project','otherProject','activity','otherActivity','criterion','secondCriterion'] as $key) $ids[$key]=Uuid::v4();
    foreach (['teacher','student'] as $role) $insert('roles',['id'=>$ids[$role.'Role'],'code'=>$role,'name'=>$role]);
    foreach (['teacherUser','otherTeacherUser','missingTeacherUser','studentUser','otherStudentUser','secondStudentUser'] as $key) {
        $role = str_contains(strtolower($key),'teacher') ? 'teacher' : 'student';
        $insert('users',['id'=>$ids[$key],'roleId'=>$ids[$role.'Role'],'email'=>$key.'@rubric.test','passwordHash'=>password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),'fullName'=>'Rubric '.$key,'status'=>'active']);
    }
    foreach (['school','otherSchool'] as $key) $insert('schools',['id'=>$ids[$key],'name'=>'Rubric '.$key]);
    foreach (['class','otherClass','unassignedClass'] as $key) $insert('classes',['id'=>$ids[$key],'schoolId'=>$ids[$key==='otherClass'?'otherSchool':'school'],'name'=>'Rubric '.$key,'gradeLevel'=>12,'academicYear'=>'2026-2027']);
    foreach (['teacher','otherTeacher'] as $key) $insert('teacher_profiles',['id'=>$ids[$key],'userId'=>$ids[$key.'User'],'schoolId'=>$ids[$key==='teacher'?'school':'otherSchool']]);
    foreach (['student','otherStudent','secondStudent'] as $key) $insert('student_profiles',['id'=>$ids[$key],'userId'=>$ids[$key.'User'],'classId'=>$ids[$key==='otherStudent'?'otherClass':'class'],'dateOfBirth'=>'2004-01-01','phone'=>'0000000000','studyStatus'=>'active']);
    foreach (['activity','otherActivity'] as $key) $insert('activities',['id'=>$ids[$key],'schoolId'=>$ids[$key==='activity'?'school':'otherSchool'],'createdByTeacherId'=>$ids[$key==='activity'?'teacher':'otherTeacher'],'title'=>'Rubric '.$key,'category'=>'workshop','startAt'=>'2026-09-01 00:00:00','endAt'=>'2026-09-02 00:00:00','capacity'=>30]);
    foreach (['project','otherProject'] as $key) $insert('projects',['id'=>$ids[$key],'schoolId'=>$ids[$key==='project'?'school':'otherSchool'],'mentorTeacherId'=>$ids[$key==='project'?'teacher':'otherTeacher'],'title'=>'Rubric '.$key,'category'=>'research']);
    foreach (['student','secondStudent'] as $key) {
        $insert('activity_registrations',['id'=>Uuid::v4(),'activityId'=>$ids['activity'],'studentId'=>$ids[$key],'status'=>'approved']);
        $insert('project_members',['id'=>Uuid::v4(),'projectId'=>$ids['project'],'studentId'=>$ids[$key],'status'=>'active']);
    }
    $insert('assessment_criteria',['id'=>$ids['criterion'],'code'=>'rubric_test_knowledge','name'=>'Kiến thức','minScore'=>0,'maxScore'=>10]);
    $insert('assessment_criteria',['id'=>$ids['secondCriterion'],'code'=>'rubric_test_team','name'=>'Làm việc nhóm','minScore'=>1,'maxScore'=>5,'displayOrder'=>2]);
    foreach (['assessment.read_managed','assessment.update_managed','activity.read_managed','activity_registration.read_managed','student_profile.read_own','assessment.read_own','notification.read_own','notification.mark_read_own','notification.manage_preferences_own'] as $code) {
        $id=Uuid::v4();
        $insert('permissions',['id'=>$id,'code'=>$code]);
        $insert('role_permissions',['roleId'=>$ids[str_ends_with($code,'managed')?'teacherRole':'studentRole'],'permissionId'=>$id]);
    }
    // A legacy activity assessment must survive unchanged. Corrupt legacy data blocks preflight.
    $legacyId=Uuid::v4();
    $insert('activity_registrations',['id'=>Uuid::v4(),'activityId'=>$ids['otherActivity'],'studentId'=>$ids['otherStudent'],'status'=>'approved']);
    $insert('assessments',['id'=>$legacyId,'teacherId'=>$ids['otherTeacher'],'studentId'=>$ids['otherStudent'],'activityId'=>$ids['otherActivity'],'overallScore'=>80,'comment'=>'Legacy fixture']);
    $insert('assessment_scores',['id'=>Uuid::v4(),'assessmentId'=>$legacyId,'criteriaId'=>$ids['criterion'],'score'=>0]);
    $legacyBefore=$pdo->query('SELECT * FROM assessments WHERE id='.$pdo->quote($legacyId))->fetch(PDO::FETCH_ASSOC);
    $migrationObject=require $root.'/Database/migrations/20260909000100_decouple_competency_assessments.php';
    $pdo->beginTransaction();
    $pdo->exec('UPDATE assessments SET publishedAt=NOW() WHERE id='.$pdo->quote($legacyId));
    $blocked=false;
    try { $migrationObject->preflight(new TalentHub\Database\Migration\MigrationContext($pdo)); }
    catch (RuntimeException $e) { $blocked=str_contains($e->getMessage(),'invalid assessment lifecycle'); }
    finally { $pdo->rollBack(); }
    if (!$blocked) throw new RuntimeException('Invalid legacy draft did not block migration.');
    $dir = $root . '/.codex_tmp/' . $name;
    mkdir($dir,0777,true);
    $migration = '20260909000100_decouple_competency_assessments.php';
    copy($root.'/Database/migrations/'.$migration, $dir.'/'.$migration);
    // Execute through the real runner in an isolated migration directory; normal runner bookkeeping only.
    $runner = new MigrationRunner($pdo,$dir);
    $runner->migrate();
    $runner->validate();
    $legacyAfter=$pdo->query('SELECT * FROM assessments WHERE id='.$pdo->quote($legacyId))->fetch(PDO::FETCH_ASSOC);
    unset($legacyAfter['classId'],$legacyAfter['projectId']);
    if ($legacyBefore!==$legacyAfter) throw new RuntimeException('Migration changed legacy assessment data.');
    if ($runner->migrate() !== []) throw new RuntimeException('Migration retry was not a no-op.');
    $insert('teacher_class_assignments',['teacherId'=>$ids['teacher'],'classId'=>$ids['class']]);
    $username='rubric_'.bin2hex(random_bytes(6));
    $password=bin2hex(random_bytes(24));
    $server->exec('CREATE USER '.$server->quote($username)."@'127.0.0.1' IDENTIFIED BY ".$server->quote($password));
    $server->exec("GRANT ALL PRIVILEGES ON `$name`.* TO ".$server->quote($username)."@'127.0.0.1'");
    $manifest=['username'=>$username,'password'=>$password,'database' =>$name,'host'=>$config['host'],'port'=>$config['port'],'token'=>$token,'ids'=>$ids];
    file_put_contents($root.'/.codex_tmp/competency-disposable.json',json_encode($manifest,JSON_PRETTY_PRINT));
    return [$pdo,$ids,$manifest,$insert];
}

/** Revalidates selected DB and the per-run marker before a browser fixture can use it. */
function competencyVerify(PDO $pdo,array $manifest): void
{
    if (!preg_match('/\Atalenthub_rubric_test_[0-9]{14}_[a-f0-9]{8}\z/',$manifest['database'] ?? '')
        || $pdo->query('SELECT DATABASE()')->fetchColumn() !== $manifest['database']
        || !hash_equals($manifest['token'],(string)$pdo->query('SELECT token FROM competency_disposable_marker')->fetchColumn())) {
        throw new RuntimeException('Disposable identity verification failed.');
    }
}
