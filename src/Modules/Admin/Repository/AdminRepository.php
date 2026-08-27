<?php
declare(strict_types=1);

namespace TalentHub\Modules\Admin\Repository;

use PDO;
use RuntimeException;
use TalentHub\Support\Uuid;

final class AdminRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<string,mixed> */
    public function dashboard(): array
    {
        $queue=[];
        $pendingOrganizations=0;foreach($this->organizations() as $organization){if(in_array(strtolower((string)$organization['verificationStatus']),['pending','inactive'],true)){$pendingOrganizations++;}}
        if($pendingOrganizations>0){$queue[]=['type'=>'organizations','severity'=>'high','title'=>'Tổ chức chờ xác minh','count'=>$pendingOrganizations,'detail'=>'Nhà trường hoặc doanh nghiệp cần duyệt','owner'=>'Xác minh'];$pendingOrganizations=0;}
        if($pendingOrganizations>0){$queue[]=['type'=>'organizations','severity'=>'high','title'=>'Tổ chức chờ xác minh','count'=>$pendingOrganizations,'detail'=>'School/Enterprise cáº§n duyệt','owner'=>'Verification'];}
        $suspended=$this->countWhere('users',"status IN ('suspended','disabled','pending')");if($suspended>0){$queue[]=['type'=>'users','severity'=>'medium','title'=>'Tài khoản cần xử lý','count'=>$suspended,'detail'=>'Chờ duyệt, tạm khóa hoặc vô hiệu hóa','owner'=>'Quản lý tài khoản'];$suspended=0;}
        $payments=$this->countWhere('payment_orders',"paymentStatus='pending'");if($payments>0){$queue[]=['type'=>'payments','severity'=>'critical','title'=>'Payment order đang chờ','count'=>$payments,'detail'=>'Cần kiểm tra đối soát','owner'=>'Finance Ops'];}
        $applications=$this->countWhere('internship_applications',"status IN ('submitted','pending','applied')");if($applications>0){$queue[]=['type'=>'applications','severity'=>'medium','title'=>'Ứng tuyển chưa review','count'=>$applications,'detail'=>'Hồ sơ đang trong hàng đợi','owner'=>'Partner Ops'];}
        $legacy=$this->columnExists('users','roles');
        $roleSql=$legacy?'SELECT roles AS role,COUNT(*) AS total FROM users GROUP BY roles':'SELECT r.code AS role,COUNT(*) AS total FROM users u JOIN roles r ON r.id=u.roleId GROUP BY r.code';
        $usersByRole=[];foreach($this->pdo->query($roleSql)->fetchAll(PDO::FETCH_ASSOC) as $row){$usersByRole[(string)$row['role']]=(int)$row['total'];}
        $recentOrganizations=array_slice($this->organizations(),0,6);
        $recentAudits=array_slice($this->audits(),0,6);
        return [
            'users'=>(int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'activeUsers'=>(int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
            'suspendedUsers'=>(int)$this->pdo->query("SELECT COUNT(*) FROM users WHERE status<>'active'")->fetchColumn(),
            'schools'=>$this->countTable('schools'),
            'enterprises'=>$this->countTable('enterprises'),
            'activities'=>$this->countTable('activities'),
            'applications'=>$this->countTable('internship_applications'),
            'pendingPayments'=>$this->countWhere('payment_orders',"paymentStatus='pending'"),
            'auditEvents'=>$this->countTable('audit_logs'),
            'queue'=>$queue,
            'usersByRole'=>$usersByRole,
            'recentOrganizations'=>$recentOrganizations,
            'recentAudits'=>$recentAudits,
            'generatedAt'=>gmdate(DATE_ATOM),
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function createUser(string $actorId,array $input,string $requestId): array
    {
        $email=strtolower(trim((string)($input['email']??'')));$fullName=trim((string)($input['fullName']??''));$role=strtolower(trim((string)($input['role']??'')));$password=(string)($input['password']??'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($fullName)<2||!in_array($role,['student','teacher','school','enterprise','platform_admin'],true)||strlen($password)<12){throw new RuntimeException('Email, họ tên, vai trò hoặc mật khẩu không hợp lệ.');}
        $duplicate=$this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');$duplicate->execute([$email]);if((int)$duplicate->fetchColumn()>0){throw new RuntimeException('Email đã tồn tại.');}
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($fullName)<2||!in_array($role,['student','teacher','school','enterprise','platform_admin'],true)||strlen($password)<12){throw new RuntimeException('Email, há» tên, vai trò hoặc máº­t kháº©u không há»£p lá»‡.');}
        $exists=$this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');$exists->execute([$email]);if((int)$exists->fetchColumn()>0){throw new RuntimeException('Email đã tá»“n táº¡i.');}
        $id=Uuid::v4();$hash=password_hash($password,PASSWORD_DEFAULT);$legacy=$this->columnExists('users','roles');$this->pdo->beginTransaction();try{if($legacy){$this->pdo->prepare("INSERT INTO users(id,email,passwordHash,fullName,roles,status) VALUES(?,?,?,?,?,'active')")->execute([$id,$email,$hash,$fullName,$role]);}else{$roleId=$this->roleId($role);$this->pdo->prepare("INSERT INTO users(id,roleId,email,passwordHash,fullName,status) VALUES(?,?,?,?,?,'active')")->execute([$id,$roleId,$email,$hash,$fullName]);}$this->audit($actorId,'admin.user_created','user',$id,$requestId,['email'=>$email,'role'=>$role]);$this->pdo->commit();return ['id'=>$id,'email'=>$email,'fullName'=>$fullName,'role'=>$role,'status'=>'active'];}catch(\Throwable $e){if($this->pdo->inTransaction()){$this->pdo->rollBack();}throw $e;}
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function updateUser(string $actorId,string $userId,array $input,string $requestId): array
    {
        $email=strtolower(trim((string)($input['email']??'')));$fullName=trim((string)($input['fullName']??''));$role=strtolower(trim((string)($input['role']??'')));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($fullName)<2||!in_array($role,['student','teacher','school','enterprise','platform_admin'],true)){throw new RuntimeException('Dữ liệu tài khoản không hợp lệ.');}
        if($actorId===$userId&&$role!=='platform_admin'){throw new RuntimeException('Admin không thể tự gỡ quyền quản trị.');}
        $duplicate=$this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email=? AND id<>?');$duplicate->execute([$email,$userId]);if((int)$duplicate->fetchColumn()>0){throw new RuntimeException('Email đã được tài khoản khác sử dụng.');}
        $found=$this->pdo->prepare('SELECT COUNT(*) FROM users WHERE id=?');$found->execute([$userId]);if((int)$found->fetchColumn()===0){throw new RuntimeException('Không tìm thấy tài khoản.');}
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($fullName)<2||!in_array($role,['student','teacher','school','enterprise','platform_admin'],true)){throw new RuntimeException('Dữ liệu tài khoản không há»£p lá»‡.');}
        if($actorId===$userId&&$role!=='platform_admin'){throw new RuntimeException('Admin không thá»ƒ tá»± gá»¡ quyền quáº£n trá»‹.');}
        $legacy=$this->columnExists('users','roles');$this->pdo->beginTransaction();try{$s=$this->pdo->prepare('SELECT email,fullName,'.($legacy?'roles':'roleId').' AS role FROM users WHERE id=? FOR UPDATE');$s->execute([$userId]);$before=$s->fetch(PDO::FETCH_ASSOC);if(!$before){throw new RuntimeException('Không tìm tháº¥y tài khoản.');}if($legacy){$this->pdo->prepare('UPDATE users SET email=?,fullName=?,roles=? WHERE id=?')->execute([$email,$fullName,$role,$userId]);}else{$this->pdo->prepare('UPDATE users SET email=?,fullName=?,roleId=? WHERE id=?')->execute([$email,$fullName,$this->roleId($role),$userId]);}$this->audit($actorId,'admin.user_updated','user',$userId,$requestId,['before'=>$before,'after'=>['email'=>$email,'fullName'=>$fullName,'role'=>$role]]);$this->pdo->commit();return ['id'=>$userId,'email'=>$email,'fullName'=>$fullName,'role'=>$role];}catch(\Throwable $e){if($this->pdo->inTransaction()){$this->pdo->rollBack();}throw $e;}
    }

    /** @return array<string,mixed> */
    public function deleteUser(string $actorId,string $userId,string $reason,string $requestId): array
    {
        if($actorId===$userId){throw new RuntimeException('Admin không thể tự vô hiệu hóa tài khoản đang đăng nhập.');}
        $found=$this->pdo->prepare('SELECT COUNT(*) FROM users WHERE id=?');$found->execute([$userId]);if((int)$found->fetchColumn()===0){throw new RuntimeException('Không tìm thấy tài khoản.');}
        if($actorId===$userId){throw new RuntimeException('Admin không thá»ƒ tá»± xóa tài khoản.');}$this->assertReason($reason);$this->pdo->beginTransaction();try{$s=$this->pdo->prepare('SELECT status FROM users WHERE id=? FOR UPDATE');$s->execute([$userId]);$before=$s->fetchColumn();if(!is_string($before)){throw new RuntimeException('Không tìm tháº¥y tài khoản.');}$this->pdo->prepare("UPDATE users SET status='disabled' WHERE id=?")->execute([$userId]);$this->audit($actorId,'admin.user_deleted','user',$userId,$requestId,['before'=>$before,'after'=>'disabled','reason'=>$reason,'deletionMode'=>'soft']);$this->pdo->commit();return ['id'=>$userId,'status'=>'disabled','deleted'=>true,'mode'=>'soft'];}catch(\Throwable $e){if($this->pdo->inTransaction()){$this->pdo->rollBack();}throw $e;}
    }

    /** @return list<array<string,mixed>> */
    public function users(string $search='', string $role='', string $status=''): array
    {
        $legacy=$this->columnExists('users','roles');
        $sql=$legacy
            ? 'SELECT id,email,fullName,roles AS role,status,createdAt,NULL AS lastLoginAt FROM users WHERE 1=1'
            : 'SELECT u.id,u.email,u.fullName,r.code AS role,u.status,u.createdAt,u.lastLoginAt FROM users u JOIN roles r ON r.id=u.roleId WHERE 1=1';
        $params=[];
        if($search!==''){$sql.=' AND (email LIKE ? OR fullName LIKE ? OR id LIKE ?)';$needle='%'.$search.'%';$params=[$needle,$needle,$needle];}
        if($role!==''){$sql.=$legacy?' AND roles=?':' AND r.code=?';$params[]=$role;}
        if($status!==''){$sql.=$legacy?' AND status=?':' AND u.status=?';$params[]=$status;}
        $sql.=$legacy?' ORDER BY createdAt DESC LIMIT 200':' ORDER BY u.createdAt DESC LIMIT 200';
        $statement=$this->pdo->prepare($sql);$statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function organizations(string $type=''): array
    {
        $rows=[];
        if($this->tableExists('organization_registration_requests')){
            $this->pdo->exec("DELETE FROM organization_registration_requests WHERE status='pending' AND expiresAt<=UTC_TIMESTAMP(6)");
            $params=[];$where="status='pending' AND expiresAt>UTC_TIMESTAMP(6)";
            if(in_array($type,['school','enterprise'],true)){$where.=' AND type=?';$params[]=$type;}
            $request=$this->pdo->prepare("SELECT id,organizationName AS name,type,'pending' AS status,'pending' AS verificationStatus,createdAt,expiresAt,email,1 AS registrationRequest FROM organization_registration_requests WHERE {$where} ORDER BY createdAt DESC LIMIT 200");
            $request->execute($params);$rows=$request->fetchAll(PDO::FETCH_ASSOC);
        }
        if(($type===''||$type==='school')&&$this->tableExists('schools')){
            $columns=$this->columns('schools');
            $verification=in_array('verificationStatus',$columns,true)?'verificationStatus':(in_array('status',$columns,true)?'status':"'unknown'");
            $created=in_array('createdAt',$columns,true)?'createdAt':'NULL';
            $order=in_array('createdAt',$columns,true)?'createdAt DESC':'name';$rows=array_merge($rows,$this->pdo->query("SELECT id,name,'school' AS type,status,{$verification} AS verificationStatus,{$created} AS createdAt FROM schools ORDER BY {$order} LIMIT 200")->fetchAll(PDO::FETCH_ASSOC));
        }
        if(($type===''||$type==='enterprise')&&$this->tableExists('enterprises')){
            $columns=$this->columns('enterprises');
            $verification=in_array('verificationStatus',$columns,true)?'verificationStatus':(in_array('status',$columns,true)?'status':"'unknown'");
            $created=in_array('createdAt',$columns,true)?'createdAt':'NULL';
            $order=in_array('createdAt',$columns,true)?'createdAt DESC':'name';$rows=array_merge($rows,$this->pdo->query("SELECT id,name,'enterprise' AS type,status,{$verification} AS verificationStatus,{$created} AS createdAt FROM enterprises ORDER BY {$order} LIMIT 200")->fetchAll(PDO::FETCH_ASSOC));
        }
        return $rows;
    }

    /** @return list<array<string,mixed>> */
    public function audits(string $search=''): array
    {
        $columns=$this->columns('audit_logs');
        $request=in_array('requestId',$columns,true)?'requestId':'NULL AS requestId';
        $params=[];$where='';
        if($search!==''){$where=' WHERE action LIKE ? OR entityId LIKE ? OR userId LIKE ?';$needle='%'.$search.'%';$params=[$needle,$needle,$needle];}
        $statement=$this->pdo->prepare("SELECT id,userId,action,entityType,entityId,{$request},createdAt FROM audit_logs{$where} ORDER BY createdAt DESC LIMIT 250");
        $statement->execute($params);return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed> */
    public function rbac(): array
    {
        if($this->columnExists('roles','code')){
            $roles=$this->pdo->query('SELECT id,code,name,description FROM roles ORDER BY code')->fetchAll(PDO::FETCH_ASSOC);
            $mappings=$this->pdo->query('SELECT r.code AS role,p.code AS permission FROM role_permissions rp JOIN roles r ON r.id=rp.roleId JOIN permissions p ON p.id=rp.permissionId ORDER BY r.code,p.code')->fetchAll(PDO::FETCH_ASSOC);
        }else{
            $roles=$this->pdo->query('SELECT id,name AS code,name,description FROM roles ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
            $mappings=$this->pdo->query('SELECT r.name AS role,p.name AS permission FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id ORDER BY r.name,p.name')->fetchAll(PDO::FETCH_ASSOC);
        }
        return ['roles'=>$roles,'mappings'=>$mappings];
    }

    /** @return array<string,mixed> */
    public function system(): array
    {
        $migrationCount=$this->tableExists('schema_migrations')?(int)$this->pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn():0;
        return ['database'=>(string)$this->pdo->query('SELECT DATABASE()')->fetchColumn(),'databaseVersion'=>(string)$this->pdo->query('SELECT VERSION()')->fetchColumn(),'migrationCount'=>$migrationCount,'tableCount'=>(int)$this->pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn(),'serverTime'=>(string)$this->pdo->query('SELECT UTC_TIMESTAMP()')->fetchColumn(),'phpVersion'=>PHP_VERSION];
    }

    /** @return list<array<string,mixed>> */
    public function resource(string $resource): array
    {
        $catalog=[
            'activities'=>['activities',['id','title','category','status','startAt','endAt']],
            'applications'=>['internship_applications',['id','postId','studentId','status','matchScore','appliedAt','reviewedAt']],
            'payments'=>['payment_orders',['id','orderId','amount','currency','paymentStatus','provider','createdAt']],
            'notifications'=>['notifications',['id','userId','title','notificationStatus','deliveryChannel','isRead','createdAt']],
        ];
        if(!isset($catalog[$resource])){throw new RuntimeException('Tài nguyên Admin không hợp lệ.');}
        [$table,$wanted]=$catalog[$resource];if(!$this->tableExists($table)){return [];}$available=$this->columns($table);$selected=array_values(array_intersect($wanted,$available));if($selected===[]){return [];}
        $order=in_array('createdAt',$available,true)?'createdAt DESC':$selected[0].' DESC';
        return $this->pdo->query('SELECT '.implode(',',$selected)." FROM {$table} ORDER BY {$order} LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed> */
    public function setUserStatus(string $actorId,string $userId,string $status,string $reason,string $requestId): array
    {
        if(!in_array($status,['active','suspended'],true)){throw new RuntimeException('Trạng thái tài khoản không hợp lệ.');}
        if($actorId===$userId&&$status==='suspended'){throw new RuntimeException('Admin không thể tự đình chỉ tài khoản đang đăng nhập.');}
        $this->assertReason($reason);$this->pdo->beginTransaction();
        try{$s=$this->pdo->prepare('SELECT status FROM users WHERE id=? FOR UPDATE');$s->execute([$userId]);$before=$s->fetchColumn();if(!is_string($before)){throw new RuntimeException('Không tìm thấy tài khoản.');}$this->pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status,$userId]);$this->audit($actorId,'admin.user_status_changed','user',$userId,$requestId,['before'=>$before,'after'=>$status,'reason'=>$reason]);$this->pdo->commit();return ['id'=>$userId,'status'=>$status];}catch(\Throwable $e){if($this->pdo->inTransaction()){$this->pdo->rollBack();}throw $e;}
    }

    /** @return array<string,mixed> */
    public function verifyOrganization(string $actorId,string $type,string $id,string $decision,string $reason,string $requestId): array
    {
        if(!in_array($type,['school','enterprise'],true)||!in_array($decision,['verified','rejected','pending'],true)){throw new RuntimeException('Quyết định xác minh không hợp lệ.');}
        if($this->tableExists('organization_registration_requests')){$request=$this->pdo->prepare('SELECT COUNT(*) FROM organization_registration_requests WHERE id=? AND type=?');$request->execute([$id,$type]);if((int)$request->fetchColumn()>0){return $this->reviewOrganizationRegistration($actorId,$type,$id,$decision,$reason,$requestId);}}
        $this->assertReason($reason);$table=$type==='school'?'schools':'enterprises';$columns=$this->columns($table);$hasVerification=in_array('verificationStatus',$columns,true);$field=$hasVerification?'verificationStatus':'status';$storedDecision=$hasVerification?$decision:match($decision){'verified'=>'active','rejected'=>'suspended',default=>'inactive'};
        $emailSelect=in_array('email',$columns,true)?',email':'';$this->pdo->beginTransaction();try{$s=$this->pdo->prepare("SELECT {$field}{$emailSelect} FROM {$table} WHERE id=? FOR UPDATE");$s->execute([$id]);$organization=$s->fetch(PDO::FETCH_ASSOC);if(!$organization){throw new RuntimeException('Không tìm thấy tổ chức.');}$before=(string)$organization[$field];$sets=["{$field}=?"];$params=[$storedDecision];if(in_array('verificationNote',$columns,true)){$sets[]='verificationNote=?';$params[]=$reason;}if(in_array('verifiedBy',$columns,true)){$sets[]='verifiedBy=?';$params[]=$decision==='verified'?$actorId:null;}if(in_array('verifiedAt',$columns,true)){$sets[]='verifiedAt=?';$params[]=$decision==='verified'?gmdate('Y-m-d H:i:s'):null;}$params[]=$id;$this->pdo->prepare("UPDATE {$table} SET ".implode(',',$sets).' WHERE id=?')->execute($params);
            $userId=null;$memberTable=$type==='school'?'school_members':'enterprise_members';$foreignKey=$type==='school'?'schoolId':'enterpriseId';if($this->tableExists($memberTable)){$member=$this->pdo->prepare("SELECT userId FROM {$memberTable} WHERE {$foreignKey}=? ORDER BY id LIMIT 1");$member->execute([$id]);$candidate=$member->fetchColumn();if(is_string($candidate)){$userId=$candidate;}}if($userId===null&&!empty($organization['email'])){$candidate=$this->pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');$candidate->execute([(string)$organization['email']]);$found=$candidate->fetchColumn();if(is_string($found)){$userId=$found;}}if($userId!==null){$userStatus=match($decision){'verified'=>'active','rejected'=>'disabled',default=>'pending'};$this->pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$userStatus,$userId]);}
            $this->audit($actorId,'admin.organization_verification_changed',$type,$id,$requestId,['before'=>$before,'after'=>$decision,'reason'=>$reason]);$this->pdo->commit();return ['id'=>$id,'type'=>$type,'verificationStatus'=>$decision];}catch(\Throwable $e){if($this->pdo->inTransaction()){$this->pdo->rollBack();}throw $e;}
    }

    /** @return array<string,mixed> */
    private function reviewOrganizationRegistration(string $actorId,string $type,string $id,string $decision,string $reason,string $requestId): array
    {
        if(!in_array($decision,['verified','rejected'],true)){throw new RuntimeException('Yêu cầu đăng ký chỉ có thể được duyệt hoặc từ chối.');}
        $this->assertReason($reason);$this->pdo->beginTransaction();
        try{
            $statement=$this->pdo->prepare('SELECT * FROM organization_registration_requests WHERE id=? AND type=? FOR UPDATE');$statement->execute([$id,$type]);$request=$statement->fetch(PDO::FETCH_ASSOC);
            if(!$request||$request['status']!=='pending'){throw new RuntimeException('Yêu cầu đăng ký không còn ở trạng thái chờ xử lý.');}
            if(strtotime((string)$request['expiresAt'])<=time()){$this->pdo->prepare('DELETE FROM organization_registration_requests WHERE id=?')->execute([$id]);$this->pdo->commit();throw new RuntimeException('Yêu cầu đăng ký đã hết hạn sau 3 ngày và đã được xóa.');}
            if($decision==='rejected'){$this->pdo->prepare("UPDATE organization_registration_requests SET status='rejected',passwordHash='',reviewedAt=UTC_TIMESTAMP(6),reviewedBy=?,reviewNote=? WHERE id=?")->execute([$actorId,$reason,$id]);$this->audit($actorId,'admin.organization_registration_rejected','organization_registration_request',$id,$requestId,['type'=>$type,'reason'=>$reason]);$this->pdo->commit();return ['id'=>$id,'type'=>$type,'verificationStatus'=>'rejected','accountCreated'=>false];}
            $duplicate=$this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');$duplicate->execute([(string)$request['email']]);if((int)$duplicate->fetchColumn()>0){throw new RuntimeException('Email của yêu cầu đã được một tài khoản khác sử dụng.');}
            $userId=Uuid::v4();$organizationId=Uuid::v4();$legacy=$this->columnExists('users','roles');
            if($legacy){$this->pdo->prepare("INSERT INTO users(id,email,passwordHash,fullName,roles,status) VALUES(?,?,?,?,?,'active')")->execute([$userId,$request['email'],$request['passwordHash'],$request['fullName'],$type]);}
            else{$this->pdo->prepare("INSERT INTO users(id,roleId,email,passwordHash,fullName,status) VALUES(?,?,?,?,?,'active')")->execute([$userId,$this->roleId($type),$request['email'],$request['passwordHash'],$request['fullName']]);}
            $table=$type==='school'?'schools':'enterprises';$columns=$this->columns($table);$all=['id'=>$organizationId,'name'=>$request['organizationName'],'status'=>'active','email'=>$request['email'],'phone'=>$request['phone'],'address'=>$request['address'],'verificationStatus'=>'verified','verificationNote'=>$reason,'verifiedAt'=>gmdate('Y-m-d H:i:s'),'verifiedBy'=>$actorId];$organization=array_intersect_key($all,array_flip($columns));$names=array_keys($organization);
            $this->pdo->prepare('INSERT INTO '.$table.'('.implode(',',$names).') VALUES('.implode(',',array_fill(0,count($names),'?')).')')->execute(array_values($organization));
            $memberTable=$type==='school'?'school_members':'enterprise_members';$foreignKey=$type==='school'?'schoolId':'enterpriseId';if($this->tableExists($memberTable)){$memberColumns=$this->columns($memberTable);$roleColumn=in_array('memberRole',$memberColumns,true)?'memberRole':'role';$this->pdo->prepare("INSERT INTO {$memberTable}(id,{$foreignKey},userId,{$roleColumn}) VALUES(?,?,?,'admin')")->execute([Uuid::v4(),$organizationId,$userId]);}
            $this->pdo->prepare("UPDATE organization_registration_requests SET status='approved',passwordHash='',reviewedAt=UTC_TIMESTAMP(6),reviewedBy=?,reviewNote=?,createdUserId=?,createdOrganizationId=? WHERE id=?")->execute([$actorId,$reason,$userId,$organizationId,$id]);
            $this->audit($actorId,'admin.organization_registration_approved','organization_registration_request',$id,$requestId,['type'=>$type,'userId'=>$userId,'organizationId'=>$organizationId]);
            $this->pdo->commit();return ['id'=>$organizationId,'requestId'=>$id,'type'=>$type,'verificationStatus'=>'verified','accountCreated'=>true,'userId'=>$userId];
        }catch(\Throwable $exception){if($this->pdo->inTransaction()){$this->pdo->rollBack();}throw $exception;}
    }

    /** @param array<string,mixed> $metadata */
    private function audit(string $actorId,string $action,string $entityType,string $entityId,string $requestId,array $metadata): void
    {
        if($this->columnExists('audit_logs','requestId')){$this->pdo->prepare('INSERT INTO audit_logs(id,userId,action,entityType,entityId,requestId,metadata) VALUES(?,?,?,?,?,?,?)')->execute([Uuid::v4(),$actorId,$action,$entityType,$entityId,$requestId,json_encode($metadata,JSON_THROW_ON_ERROR)]);return;}
        $this->pdo->prepare('INSERT INTO audit_logs(id,userId,action,entityType,entityId) VALUES(?,?,?,?,?)')->execute([Uuid::v4(),$actorId,$action,$entityType,$entityId]);
    }

    private function assertReason(string $reason): void{if(mb_strlen(trim($reason))<5){throw new RuntimeException('Lý do phải có ít nhất 5 ký tự.');}}
    private function countTable(string $table): int{return $this->tableExists($table)?(int)$this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn():0;}
    private function countWhere(string $table,string $where): int{return $this->tableExists($table)?(int)$this->pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn():0;}
    private function tableExists(string $table): bool{$s=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');$s->execute([$table]);return (int)$s->fetchColumn()===1;}
    private function columnExists(string $table,string $column): bool{return in_array($column,$this->columns($table),true);}
    /** @return list<string> */
    private function columns(string $table): array{$s=$this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? ORDER BY ordinal_position');$s->execute([$table]);return $s->fetchAll(PDO::FETCH_COLUMN);}
    private function roleId(string $role): string{$s=$this->pdo->prepare('SELECT id FROM roles WHERE code=?');$s->execute([$role]);$id=$s->fetchColumn();if(!is_string($id)){throw new RuntimeException('Vai trò chưa được seed.');}return $id;}
}
