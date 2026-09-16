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
<section class="school-section-box"><h2 class="school-section-box__title">Tạo badge trường</h2><form method="post" class="school-form" data-credential-form="badge"><input type="hidden" name="csrfToken" value="<?=htmlspecialchars($session->csrfToken(),ENT_QUOTES,'UTF-8');?>"><input type="hidden" name="action" value="create_badge"><input type="hidden" name="code" value="" required data-credential-code><div class="school-form__grid school-form__grid--2col"><label class="school-form__field school-form__field--full"><span>Mã hệ thống <em style="color:#64748B;font-style:normal;">(tự sinh)</em></span><div style="display:flex;gap:0.5rem;align-items:center;"><code data-credential-preview style="flex:1;padding:0.5rem 0.75rem;background:#F1F5F9;border:1px solid #CBD5E1;border-radius:6px;font-family:'JetBrains Mono','SF Mono',monospace;font-size:0.875rem;color:#0F172A;letter-spacing:0.025em;"></code><button type="button" class="btn btn-outline btn-sm" data-credential-regen style="flex-shrink:0;display:inline-flex;align-items:center;gap:0.25rem;" title="Tạo mã khác"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>Tạo mã khác</button></div></label><label class="school-form__field"><span>Tên</span><input name="name" required></label><label class="school-form__field"><span>Danh mục</span><input name="category" value="school" required></label><label class="school-form__field"><span>Cấp độ</span><input name="level" type="number" value="1" min="1" max="100"></label><label class="school-form__field school-form__field--full"><span>Mô tả</span><textarea name="description" required></textarea></label></div><div class="school-form__actions"><button class="btn btn-primary">Tạo badge</button></div></form></section>
<section class="school-section-box"><h2 class="school-section-box__title">Tạo chứng chỉ trường</h2><form method="post" class="school-form" data-credential-form="certificate"><input type="hidden" name="csrfToken" value="<?=htmlspecialchars($session->csrfToken(),ENT_QUOTES,'UTF-8');?>"><input type="hidden" name="action" value="create_certificate"><input type="hidden" name="code" value="" required data-credential-code><div class="school-form__grid school-form__grid--2col"><label class="school-form__field school-form__field--full"><span>Mã hệ thống <em style="color:#64748B;font-style:normal;">(tự sinh)</em></span><div style="display:flex;gap:0.5rem;align-items:center;"><code data-credential-preview style="flex:1;padding:0.5rem 0.75rem;background:#F1F5F9;border:1px solid #CBD5E1;border-radius:6px;font-family:'JetBrains Mono','SF Mono',monospace;font-size:0.875rem;color:#0F172A;letter-spacing:0.025em;"></code><button type="button" class="btn btn-outline btn-sm" data-credential-regen style="flex-shrink:0;display:inline-flex;align-items:center;gap:0.25rem;" title="Tạo mã khác"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>Tạo mã khác</button></div></label><label class="school-form__field"><span>Tên</span><input name="name" required></label><label class="school-form__field school-form__field--full"><span>Đơn vị cấp</span><input name="issuerName" value="<?=htmlspecialchars((string)$context['school']['name']);?>" required></label><label class="school-form__field school-form__field--full"><span>Mô tả</span><textarea name="description" required></textarea></label></div><div class="school-form__actions"><button class="btn btn-primary">Tạo chứng chỉ</button></div></form></section>
</div>
<script>
(function () {
    var ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';
    function gen(prefix) {
        var out = '';
        var buf = new Uint8Array(8);
        if (window.crypto && window.crypto.getRandomValues) {
            window.crypto.getRandomValues(buf);
        } else {
            for (var i = 0; i < 8; i++) { buf[i] = Math.floor(Math.random() * 256); }
        }
        for (var j = 0; j < 8; j++) { out += ALPHABET.charAt(buf[j] % ALPHABET.length); }
        return prefix + '-' + out;
    }
    function sync(form) {
        var prefix = form.getAttribute('data-credential-form') === 'certificate' ? 'cert' : 'badge';
        var hidden = form.querySelector('[data-credential-code]');
        var preview = form.querySelector('[data-credential-preview]');
        var code = gen(prefix);
        hidden.value = code;
        preview.textContent = code;
    }
    document.querySelectorAll('[data-credential-form]').forEach(function (form) {
        sync(form);
        var regen = form.querySelector('[data-credential-regen]');
        if (regen) { regen.addEventListener('click', function () { sync(form); }); }
    });
})();
</script>
<section class="school-section-box school-stack"><h2 class="school-section-box__title">Cấp credential</h2><div class="school-grid-2col"><form method="post" class="school-form"><input type="hidden" name="csrfToken" value="<?=htmlspecialchars($session->csrfToken(),ENT_QUOTES,'UTF-8');?>"><input type="hidden" name="action" value="award_badge"><div class="school-form__grid school-form__grid--2col"><label class="school-form__field"><span>Badge</span><select name="badgeId"><?php foreach($data['badges'] as $item):?><option value="<?=htmlspecialchars((string)$item['id']);?>"><?=htmlspecialchars((string)$item['name']);?></option><?php endforeach;?></select></label><label class="school-form__field"><span>Học viên</span><select name="studentId"><?php foreach($data['students'] as $student):?><option value="<?=htmlspecialchars((string)$student['id']);?>"><?=htmlspecialchars((string)$student['fullName']);?></option><?php endforeach;?></select></label><label class="school-form__field school-form__field--full"><span>Minh chứng</span><input name="evidence"></label></div><div class="school-form__actions"><button class="btn btn-primary btn-sm">Cấp badge</button></div></form><form method="post" class="school-form"><input type="hidden" name="csrfToken" value="<?=htmlspecialchars($session->csrfToken(),ENT_QUOTES,'UTF-8');?>"><input type="hidden" name="action" value="issue_certificate"><div class="school-form__grid school-form__grid--2col"><label class="school-form__field"><span>Chứng chỉ</span><select name="catalogId"><?php foreach($data['certificates'] as $item):?><option value="<?=htmlspecialchars((string)$item['id']);?>"><?=htmlspecialchars((string)$item['name']);?></option><?php endforeach;?></select></label><label class="school-form__field"><span>Học viên</span><select name="studentId"><?php foreach($data['students'] as $student):?><option value="<?=htmlspecialchars((string)$student['id']);?>"><?=htmlspecialchars((string)$student['fullName']);?></option><?php endforeach;?></select></label><label class="school-form__field school-form__field--full"><span>Minh chứng</span><input name="evidence"></label></div><div class="school-form__actions"><button class="btn btn-primary btn-sm">Cấp chứng chỉ</button></div></form></div></section>
<section class="school-section-box school-stack"><h2 class="school-section-box__title">Chứng chỉ đã cấp</h2><?php if($data['awards']===[]):?><p>Chưa có chứng chỉ được cấp.</p><?php else:?><table class="school-class-table"><thead><tr><th>Học viên</th><th>Chứng chỉ</th><th>Ngày cấp</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody><?php foreach($data['awards'] as $award):
    // Map trạng thái raw → label tiếng Việt + style chip
    $rawStatus = (string) $award['status'];
    $statusMap = [
        'issued'    => ['label' => 'Đã cấp',       'class' => 'school-status-chip--active'],
        'revoked'   => ['label' => 'Đã thu hồi',   'class' => 'school-status-chip--revoked'],
        'pending'   => ['label' => 'Chờ cấp',      'class' => 'school-status-chip--pending'],
        'expired'   => ['label' => 'Hết hạn',      'class' => 'school-status-chip--expired'],
        'draft'     => ['label' => 'Bản nháp',      'class' => 'school-status-chip--draft'],
    ];
    $statusInfo = $statusMap[$rawStatus] ?? ['label' => ucfirst($rawStatus), 'class' => 'school-status-chip--muted'];
    $isRevokable = ($rawStatus === 'issued');
?><tr><td><?=htmlspecialchars((string)$award['studentName']);?></td><td><?=htmlspecialchars((string)$award['name']);?></td><td><?=htmlspecialchars((string)$award['issuedAt']);?> UTC</td><td><span class="school-status-chip <?=$statusInfo['class'];?>"><?=htmlspecialchars($statusInfo['label']);?></span></td><td><?php if($isRevokable):?><form method="post" style="display:flex;gap:0.5rem;align-items:center;"><input type="hidden" name="csrfToken" value="<?=htmlspecialchars($session->csrfToken(),ENT_QUOTES,'UTF-8');?>"><input type="hidden" name="action" value="revoke_certificate"><input type="hidden" name="awardId" value="<?=htmlspecialchars((string)$award['id']);?>"><input name="reason" required maxlength="1000" placeholder="Lý do thu hồi" style="flex:1;min-width:0;"><button class="btn btn-outline btn-sm" style="color:#B91C1C;border-color:#FCA5A5;display:inline-flex;align-items:center;gap:0.25rem;flex-shrink:0;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>Thu hồi</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table><?php endif;?></section>
<?php $pageBody=ob_get_clean();$extraStyles='';require __DIR__.'/includes/layout.php';
