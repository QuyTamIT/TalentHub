<?php
require __DIR__ . '/bin/bootstrap.php';
$config=require __DIR__ . '/config/database.php';
$pdo=(new TalentHub\Database\Connection($config))->connect();
foreach(['student@talenthub.local','sv.fpt.an@talenthub.vn'] as $email){$s=$pdo->prepare('SELECT u.id,u.email,u.fullName,r.code AS role,sp.id AS studentId FROM users u LEFT JOIN roles r ON r.id=u.roleId LEFT JOIN student_profiles sp ON sp.userId=u.id WHERE u.email=:email');$s->execute(['email'=>$email]);echo json_encode($s->fetch(PDO::FETCH_ASSOC),JSON_UNESCAPED_UNICODE),PHP_EOL;}
