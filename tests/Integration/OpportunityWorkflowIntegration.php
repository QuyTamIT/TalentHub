<?php
declare(strict_types=1);
namespace TalentHub\Tests\Integration;
use PDO;use RuntimeException;use TalentHub\Http\CollectionQuery;use TalentHub\Http\Request;use TalentHub\Modules\Business\Repository\BusinessWorkflowRepository;use TalentHub\Modules\Business\Service\BusinessWorkflowService;use TalentHub\Modules\Notification\Repository\NotificationRepository;use TalentHub\Modules\Notification\Service\NotificationService;use TalentHub\Support\Uuid;
final class OpportunityWorkflowIntegration
{
 public function run(PDO $pdo):array
 {
  $database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();if(!str_contains(strtolower($database),'test')){throw new RuntimeException('Opportunity integration requires a test database.');}
  $ids=[];foreach(['student','enterprise'] as $role){$s=$pdo->prepare('SELECT u.id FROM users u JOIN roles r ON r.id=u.roleId WHERE r.code=? LIMIT 1');$s->execute([$role]);$ids[$role]=(string)$s->fetchColumn();if($ids[$role]===''){throw new RuntimeException("Missing {$role} fixture user");}}
  $schoolId=(string)$pdo->query('SELECT id FROM schools LIMIT 1')->fetchColumn();$projectId=Uuid::v4();$pdo->prepare("INSERT INTO projects(id,schoolId,title,summary,status,fundingGoal) VALUES(?,?,?,'Integration project','open',1000000)")->execute([$projectId,$schoolId,'Opportunity Integration']);
  $repo=new BusinessWorkflowRepository($pdo);$service=new BusinessWorkflowService($repo);$q=CollectionQuery::fromRequest(new Request('GET','/',[],'',[],[]),['createdAt','title','deadline']);
  $post=$service->createPost($ids['enterprise'],['title'=>'Backend Intern','description'=>'Integration','location'=>'HCM','workMode'=>'hybrid','openings'=>2,'deadline'=>date('Y-m-d',strtotime('+30 days'))],'req-workflow-create');
  $service->transitionPost($ids['enterprise'],(string)$post['id'],'publish','req-workflow-publish');
  if(count($service->publicPosts($q))<1){throw new RuntimeException('Published post not listed');}
  $application=$service->apply($ids['student'],(string)$post['id'],['cvUrl'=>'/storage/cv/integration.pdf'],'req-workflow-apply');
  $service->review($ids['enterprise'],(string)$application['id'],['status'=>'shortlisted','reviewerNote'=>'Qualified'],'req-workflow-review');
  $sponsorship=$service->sponsor($ids['enterprise'],['projectId'=>$projectId,'amount'=>'500000','currency'=>'VND','note'=>'Pilot'],'req-workflow-sponsor');
  $payment=$service->createPayment($ids['enterprise'],['sponsorshipId'=>$sponsorship['id'],'provider'=>'manual'],'req-workflow-payment');
  if($payment['status']!=='pending'||count($service->payments($ids['enterprise']))<1){throw new RuntimeException('Payment workflow mismatch');}
  $notificationId=Uuid::v4();$pdo->prepare("INSERT INTO notifications(id,userId,notificationType,title,message) VALUES(?,?,?,?,?)")->execute([$notificationId,$ids['student'],'application','Status','Shortlisted']);
  $notifications=new NotificationService(new NotificationRepository($pdo));$nq=CollectionQuery::fromRequest(new Request('GET','/',[],'',[],['read'=>'false']),['createdAt'],['read'=>['true','false']]);
  if(count($notifications->list($ids['student'],$nq))<1){throw new RuntimeException('Notification list mismatch');}$notifications->markRead($ids['student'],$notificationId);
  return ['internship create/publish/apply/review: OK','sponsorship/payment transaction: OK','notification ownership/read: OK'];
 }
}
