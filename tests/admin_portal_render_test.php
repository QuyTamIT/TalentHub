<?php
declare(strict_types=1);
require dirname(__DIR__).'/bin/bootstrap.php';
require dirname(__DIR__).'/Database/seeds/System/RolePermissionSeeder.php';
use TalentHub\Database\Seeds\System\RolePermissionSeeder;
use TalentHub\Rbac\RoleCodes;
$root=dirname(__DIR__);$page=(string)file_get_contents($root.'/app/admin/index.php');$css=(string)file_get_contents($root.'/assets/css/admin.css');$js=(string)file_get_contents($root.'/assets/js/admin.js');
$assert=static function(bool $ok,string $message):void{if(!$ok){throw new RuntimeException($message);}};
foreach(['id="main-content"','aria-label="Điều hướng quản trị"','/assets/css/home.css','data-command-dialog','data-sidebar-toggle','data-admin-logout','Connected · dữ liệu thật','data-module-view','data-action-dialog','data-account-dialog','data-account-form','data-role-distribution','data-dashboard-organizations','data-organization-decision','Cần xử lý ngay','Người dùng theo vai trò','Audit stream'] as $marker){$assert(str_contains($page,$marker),"Admin page missing marker: {$marker}");}
foreach([':focus-visible','prefers-reduced-motion','@media (max-width:860px)','--primary:','.sidebar'] as $marker){$assert(str_contains($css,$marker),"Admin CSS missing marker: {$marker}");}
$assert(str_contains($js,"root.dataset.theme = 'light'"),'Admin Console must force the light theme.');
$assert(!str_contains($page,'data-theme-toggle'),'Admin Console must not expose a dark-mode toggle.');
$assert(str_contains($css,'.sidebar{background:#fff'),'Admin sidebar must use a white surface.');
$assert(str_contains($css,'--primary:#f97316'),'Admin must use the shared TalentHub orange primary token.');
$assert(str_contains($css,'Be Vietnam Pro'),'Admin must use the shared TalentHub typeface.');
foreach(['showModal()','data-org-filter','event.metaKey || event.ctrlKey','/admin/users/',"editing?'PATCH':'POST'","isDelete?'DELETE':'PATCH'",'X-CSRF-Token','/auth/logout'] as $marker){$assert(str_contains($js,$marker),"Admin JS missing marker: {$marker}");}
$assert(str_contains($page,'PortalGuard::requireRole(RoleCodes::PLATFORM_ADMIN'),'Admin page must require the platform_admin role.');
$seeder=new RolePermissionSeeder();$matrix=$seeder->expectedPermissionsByRole();
$assert(RoleCodes::PLATFORM_ADMIN==='platform_admin','Canonical admin role code mismatch.');
$assert(($seeder->expectedCounts())===['roles'=>5,'permissions'=>120,'mappings'=>144],'Admin RBAC seed counts mismatch.');
$assert(in_array('admin.dashboard.read',$matrix['platform_admin']??[],true),'Admin dashboard permission is missing.');
$assert(!preg_match('/[😀-🙏]/u',$page),'Admin navigation must not use emoji icons');
echo "admin_portal_render_test: OK\n";
