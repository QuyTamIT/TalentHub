<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bin/bootstrap.php';
require dirname(__DIR__, 2) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;
use TalentHub\Support\Id\RequestId;

$context = (new SchoolAppContext())->boot();
$service = $context['credentials']; $session = $context['session']; $permissions = $context['permissions'];
$userId = (string) $context['user']['id']; $permissions->require($userId, 'school_credential.manage_own');
$flash = null; $error = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);
        $permissions->require($userId, 'school_credential.manage_own');
        $requestId = RequestId::make(null); $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create_badge') {
            $service->createBadge($userId, ['code'=>$_POST['code']??'','name'=>$_POST['name']??'','category'=>$_POST['category']??'school','description'=>$_POST['description']??'','criteria'=>$_POST['criteria']??'{}','level'=>$_POST['level']??1,'status'=>'active'], $requestId); $flash='Đã tạo badge trường.';
        } elseif ($action === 'create_certificate') {
            $service->createCertificateCatalog($userId, ['code'=>$_POST['code']??'','name'=>$_POST['name']??'','description'=>$_POST['description']??'','issuerName'=>$_POST['issuerName']??$context['school']['name'],'criteria'=>$_POST['criteria']??'{}','recommendationProfile'=>[],'recommendationEnabled'=>false,'status'=>'active'], $requestId); $flash='Đã tạo catalog chứng chỉ.';
        } elseif ($action === 'award_badge') {
            $service->awardBadge($userId,(string)($_POST['badgeId']??''),(string)($_POST['studentId']??''),['note'=>(string)($_POST['evidence']??'')],$requestId);$flash='Đã cấp badge cho học viên.';
        } elseif ($action === 'issue_certificate') {
            $service->issueCertificate($userId,(string)($_POST['catalogId']??''),(string)($_POST['studentId']??''),['note'=>(string)($_POST['evidence']??'')],$requestId);$flash='Đã cấp chứng chỉ cho học viên.';
        } elseif ($action === 'revoke_certificate') {
            $service->revokeCertificate($userId,(string)($_POST['awardId']??''),(string)($_POST['reason']??''),$requestId);$flash='Đã thu hồi chứng chỉ.';
        } else throw new ApiException(422,'VALIDATION_FAILED','Thao tác credential không hợp lệ.');
    } catch (ApiException $exception) { $error=$exception->getMessage(); }
    catch (Throwable $exception) { $error='Không thể cập nhật credential: '.$exception->getMessage(); }
}
$data=$service->dashboard($userId);$schoolInfo=['name'=>$context['school']['name'],'logo_initials'=>mb_substr($context['school']['name'],0,2),'level'=>$context['school']['level']??'','district'=>$context['school']['address']??'','academic_year'=>$context['school']['academicYear']??''];$currentRoute='/app/school/credentials.php';$pageTitle='Chứng nhận Nhà trường';
ob_start();
?>
<?php $pageDescription='Tạo catalog, cấp badge/chứng chỉ chính thức và thu hồi chứng chỉ theo đúng phạm vi trường.';include __DIR__.'/includes/page-banner.php'; ?>
<?php if($flash):?><div class="school-flash school-flash--success"><?=htmlspecialchars($flash);?></div><?php endif;?><?php if($error):?><div class="school-flash school-flash--error"><?=htmlspecialchars($error);?></div><?php endif;?>
<div class="school-grid-2col">
<section class="school-section-box"><h2 class="school-section-box__title">Tạo badge trường</h2><form method="post" class="school-form"><input type="hidden" name="csrfToken" value="<?=htmlspecialchars($session->csrfToken(),ENT_QUOTES,'UTF-8');?>"><input type="hidden" name="action" value="create_badge"><div class="school-form__grid"><label class="school-form__field"><span>Mã</span><input name="code" required></label><label class="school-form__field"><span>Tên</span><input name="name" required></label><label class="school-form__field"><span>Danh mục</span><input name="category" value="school" required></label><label class="school-form__field"><span>Cấp độ</span><input name="level" type="number" value="1" min="1" max="100"></label><label class="school-form__field school-form__field--full"><span>Mô tả</span><textarea name="description" required></textarea></label><label class="school-form__field school-form__field--full"><span>Tiêu chí JSON</span><textarea name="criteria">{}</textarea></label></div><button class="btn btn-primary">Tạo badge</button></form></section>
<section class="school-section-box"><h2 class="school-section-box__title">Tạo chứng chỉ trường</h2><form method="post" class="school-form"><input type="hidden" name="csrfToken" value="<?=htmlspecialchars($session->csrfToken(),ENT_QUOTES,'UTF-8');?>"><input type="hidden" name="action" value="create_certificate"><div class="school-form__grid"><label class="school-form__field"><span>Mã</span><input name="code" required></label><label class="school-form__field"><span>Tên</span><input name="name" required></label><label class="school-form__field"><span>Đơn vị cấp</span><input name="issuerName" value="<?=htmlspecialchars((string)$context['school']['name']);?>" required></label><label class="school-form__field school-form__field--full"><span>Mô tả</span><textarea name="description" required></textarea></label><label class="school-form__field school-form__field--full"><span>Tiêu chí JSON</span><textarea name="criteria">{}</textarea></label></div><button class="btn btn-primary">Tạo chứng chỉ</button></form></section>
</div>
<section class="school-section-box" style="margin-top:1rem"><h2 class="school-section-box__title">Cấp credential</h2><div class="school-grid-2col"><form method="post" class="school-form"><input type="hidden" name="csrfToken" value="<?=htmlspecialchars($session->csrfToken(),ENT_QUOTES,'UTF-8');?>"><input type="hidden" name="action" value="award_badge"><label class="school-form__field"><span>Badge</span><select name="badgeId"><?php foreach($data['badges'] as $item):?><option value="<?=htmlspecialchars((string)$item['id']);?>"><?=htmlspecialchars((string)$item['name']);?></option><?php endforeach;?></select></label><label class="school-form__field"><span>Học viên</span><select name="studentId"><?php foreach($data['students'] as $student):?><option value="<?=htmlspecialchars((string)$student['id']);?>"><?=htmlspecialchars((string)$student['fullName']);?></option><?php endforeach;?></select></label><label class="school-form__field"><span>Minh chứng</span><input name="evidence"></label><button class="btn btn-primary">Cấp badge</button></form><form method="post" class="school-form"><input type="hidden" name="csrfToken" value="<?=htmlspecialchars($session->csrfToken(),ENT_QUOTES,'UTF-8');?>"><input type="hidden" name="action" value="issue_certificate"><label class="school-form__field"><span>Chứng chỉ</span><select name="catalogId"><?php foreach($data['certificates'] as $item):?><option value="<?=htmlspecialchars((string)$item['id']);?>"><?=htmlspecialchars((string)$item['name']);?></option><?php endforeach;?></select></label><label class="school-form__field"><span>Học viên</span><select name="studentId"><?php foreach($data['students'] as $student):?><option value="<?=htmlspecialchars((string)$student['id']);?>"><?=htmlspecialchars((string)$student['fullName']);?></option><?php endforeach;?></select></label><label class="school-form__field"><span>Minh chứng</span><input name="evidence"></label><button class="btn btn-primary">Cấp chứng chỉ</button></form></div></section>
<section class="school-section-box" style="margin-top:1rem"><h2 class="school-section-box__title">Chứng chỉ đã cấp</h2><?php if($data['awards']===[]):?><p>Chưa có chứng chỉ được cấp.</p><?php else:?><table class="school-class-table"><thead><tr><th>Học viên</th><th>Chứng chỉ</th><th>Ngày cấp</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody><?php foreach($data['awards'] as $award):?><tr><td><?=htmlspecialchars((string)$award['studentName']);?></td><td><?=htmlspecialchars((string)$award['name']);?></td><td><?=htmlspecialchars((string)$award['issuedAt']);?> UTC</td><td><?=htmlspecialchars((string)$award['status']);?></td><td><?php if($award['status']==='issued'):?><form method="post"><input type="hidden" name="csrfToken" value="<?=htmlspecialchars($session->csrfToken(),ENT_QUOTES,'UTF-8');?>"><input type="hidden" name="action" value="revoke_certificate"><input type="hidden" name="awardId" value="<?=htmlspecialchars((string)$award['id']);?>"><input name="reason" required maxlength="1000" placeholder="Lý do thu hồi"><button class="btn btn-outline btn-sm">Thu hồi</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table><?php endif;?></section>
<?php $pageBody=ob_get_clean();$extraStyles='';require __DIR__.'/includes/layout.php';
