<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bin/bootstrap.php';
require_once __DIR__ . '/learner_evidence_backed_scores_test.php';
require_once dirname(__DIR__) . '/app/learner/data/ReadModel/PassportCvViewModel.php';
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Data\Service\ScoreViewer;
use TalentHub\Learner\Data\Service\EvidenceBackedScoreService;
use TalentHub\Learner\Data\ReadModel\PassportCvViewModel;
use TalentHub\Learner\Ai\Service\JobMatchingService;

$failures = [];
$check = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$run = static function (string $name, callable $test) use (&$failures): void {
    try { $test(); echo "PASS {$name}\n"; }
    catch (Throwable $e) { $failures[] = $name . ': ' . $e->getMessage(); echo 'FAIL ' . end($failures) . "\n"; }
};

$run('canonical scored skills survive CV filtering, including zero', static function () use ($check): void {
    $pdo = scoreFixture();
    $scores = (new EvidenceBackedScoreService($pdo))->forStudent('st1', new ScoreViewer('student', 'u1'));
    $skills = [];
    foreach ($scores['skills'] as $skill) {
        if ($skill['state'] !== 'scored') continue;
        $skills[] = array_merge($skill, ['level_score' => $skill['score'], 'score_state' => 'scored',
            'skill_status' => 'active', 'verification_status' => 'verified', 'verified_at' => $skill['assessed_at']]);
    }
    $cv = PassportCvViewModel::build(['skills' => $skills], 'review');
    $check(count($cv['skills']) === 1 && $cv['skills'][0]['score'] === 0, 'CV discarded an official zero score');
});

$run('cached gap keeps known score when target is unknown', static function () use ($check): void {
    $method = new ReflectionMethod(JobMatchingService::class, 'sanitizeSkillGap');
    $gap = $method->invoke(null, ['skills_missing' => [[
        'code'=>'php', 'label'=>'PHP', 'current_score'=>80, 'target_score'=>null, 'gap_score'=>null,
        'impact'=>'Ngưỡng yêu cầu chưa được nguồn công bố; cần đối chiếu thêm.',
    ]]]);
    $check($gap['skills_missing'][0]['impact'] === 'Vị trí chưa công bố mức yêu cầu.', 'Known skill was relabeled missing');
});

$run('share viewer validates consent, scopes student and rechecks revocation', static function () use ($check): void {
    $pdo = scoreFixture();
    $pdo->exec("CREATE TABLE privacy_consents (id TEXT, studentId TEXT, scope TEXT, isGranted INTEGER, revokedAt TEXT);
        CREATE TABLE student_profile_shares (id TEXT, studentId TEXT, consentId TEXT, tokenHash TEXT, sharedFieldsJson TEXT, expiresAt TEXT, revokedAt TEXT)");
    $token = str_repeat('a', 64);
    $pdo->exec("INSERT INTO privacy_consents VALUES ('consent','st1','profile_share',1,NULL)");
    $pdo->prepare("INSERT INTO student_profile_shares VALUES ('share','st1','consent',?,'[\"skills\"]','2999-01-01',NULL)")->execute([hash('sha256',$token)]);
    $viewer = ScoreViewer::fromShareToken($pdo, $token);
    $service = new EvidenceBackedScoreService($pdo);
    $result = $service->forStudent('st1', $viewer);
    $check($result['summary']['score'] === 0.0, 'Valid share cannot read official scores');
    $check(!$viewer->canViewAssessorName() && !$viewer->canViewTestAnswers(), 'Guest received private capabilities');
    $check($result['teacher_context_assessments'] === [], 'Guest received private teacher assessments');
    foreach ($result['skills'] as $skill) $check(empty($skill['evaluator']), 'Guest received assessor name');
    $check(scoreDenied(fn() => $service->forStudent('st2', $viewer)), 'Share crossed student boundary');
    $pdo->exec("UPDATE privacy_consents SET isGranted=0");
    $check(scoreDenied(fn() => $service->forStudent('st1', $viewer)), 'Revoked consent remains usable');
    $pdo->exec("UPDATE privacy_consents SET isGranted=1; UPDATE student_profile_shares SET expiresAt='2000-01-01'");
    $check(scoreDenied(fn() => $service->forStudent('st1', $viewer)), 'Expired share remains usable');
    $check(scoreDenied(fn() => ScoreViewer::fromShareToken($pdo, str_repeat('b',64))), 'Invalid token accepted');
});

$run('publishing cannot omit active rubric', static function () use ($check): void {
    require __DIR__ . '/teacher_grading_school_skills_test.php';
    $current = $pdo->query('SELECT * FROM assessments')->fetch();
    $input = ['mode'=>'class','contextId'=>$ids['assignedClass'],'studentId'=>$ids['student'],
        'assessmentId'=>$current['id'],'expectedVersion'=>(string)$current['version'],
        'overallScore'=>'99','assessmentStatus'=>'published','criteria'=>[],'skills'=>[]];
    $rejected = false;
    try { $service->save($ids['teacherUser'], $input); }
    catch (\TalentHub\Http\ApiException $e) { $rejected = true; }
    $check($rejected, 'Empty rubric published arbitrary overallScore=99');
    $check((float)$pdo->query('SELECT overallScore FROM assessments')->fetchColumn() === 82.5, 'Rejected publication changed saved score');
});

if ($failures) exit(1);
echo "merge_readiness_regression_test: OK\n";
