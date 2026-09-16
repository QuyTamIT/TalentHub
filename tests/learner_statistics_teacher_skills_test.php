<?php
declare(strict_types=1);
require __DIR__ . '/teacher_evaluation_workflow_sync_test.php';

use TalentHub\Learner\Data\Database\DatabaseStatisticsRepository;
use TalentHub\Learner\Data\Service\ScoreViewer;
use TalentHub\Learner\Data\Service\StatisticsService;
use TalentHub\Learner\Data\Contracts\StatisticsRepository;

$statistics = new DatabaseStatisticsRepository($db, new ScoreViewer('student',$ids['u1']));
$db->exec("INSERT INTO skill_groups VALUES ('ai_ml','AI / Machine Learning','active',3),('data','Data','active',4),('english','English','active',5),('soft','Soft skills','active',6)");
$insert=$db->prepare('INSERT INTO assessment_skill_group_scores VALUES (?,?,?)');
foreach (['ai_ml'=>95,'data'=>85.25,'english'=>0,'soft'=>70] as $code=>$score) $insert->execute([$first['id'],$code,$score]);
$rows = $statistics->skillCompetencies($ids['st1']);
scoreCheck(count($rows) === 6, 'Statistics must include all individual and group skills, beyond the dashboard top four');
scoreCheck($rows[0]['name'] === 'AI / Machine Learning' && $rows[0]['score'] === 95.0, 'New teacher group is sorted by latest score');
scoreCheck($rows[5]['score'] === 0.0, 'Zero-scored group remains visible');

// Isolate unrelated statistics while exercising the real repository and public service mapping.
$fixture = new class($statistics) implements StatisticsRepository {
    public function __construct(private DatabaseStatisticsRepository $scores) {}
    public function skillCompetencies(string $id): array { return $this->scores->skillCompetencies($id); }
    public function lifetimeFacts(string $id): array { return ['confirmed_experience_hours'=>0,'attended_activity_count'=>0,'submitted_assessment_type_count'=>0,'published_teacher_evaluation_count'=>1]; }
    public function periodStatistics(string $id, DateTimeImmutable $from, DateTimeImmutable $to): array {return ['hours'=>0,'activities'=>0,'assessments'=>0,'evaluations'=>1,'badges'=>0,'experience_buckets'=>[],'category_distribution'=>[]];}
    public function checkinStreakDays(string $id, ?DateTimeImmutable $now = null): int {return 0;}
    public function psychometricResults(string $id): array {return [];}
    public function latestPublishedEvaluation(string $id): array {return ['total_score'=>75.25,'comment'=>'Review','published_at'=>'2026-09-16 00:00:00.000000','criteria'=>[]];}
    public function projectStatistics(string $id): array {return ['total'=>0,'completed'=>0,'in_progress'=>0,'leader_roles'=>0,'featured'=>[]];}
};
$service=new StatisticsService($fixture);
foreach (StatisticsService::ALLOWED_PERIODS as $period) {
    $cards=$service->forStudentPeriod($ids['st1'],$period)['skills'];
    scoreCheck(count($cards)===6, 'Changing the statistics period must not truncate current skills');
    scoreCheck($cards[1]['score']===85.25, 'Statistics preserves exact teacher points');
    scoreCheck($cards[0]['category']==='skill_group', 'Group remains distinguished from individual skill');
}
$db->prepare('UPDATE assessment_skill_group_scores SET score=10 WHERE assessmentId=? AND groupCode=?')->execute([$first['id'],'ai_ml']);
$rows=$statistics->skillCompetencies($ids['st1']);
scoreCheck($rows[0]['name']==='Data' && array_column($rows,'score','name')['AI / Machine Learning']===10.0, 'Lower regrade immediately replaces older score');
$db->prepare('DELETE FROM assessment_skill_group_scores WHERE assessmentId=? AND groupCode=?')->execute([$first['id'],'ai_ml']);
scoreCheck(count($statistics->skillCompetencies($ids['st1']))===5, 'Removed group disappears from statistics');
$denied = new DatabaseStatisticsRepository($db, new ScoreViewer('student','u2'));
scoreCheck($denied->skillCompetencies($ids['st1'])===[], 'Other learner cannot read these scores');
echo "learner_statistics_teacher_skills_test: OK\n";
