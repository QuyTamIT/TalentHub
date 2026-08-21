<?php
declare(strict_types=1);
namespace TalentHub\Modules\Notification\Service;
use TalentHub\Http\ApiException;use TalentHub\Http\CollectionQuery;use TalentHub\Modules\Notification\Repository\NotificationRepository;
final class NotificationService
{public function __construct(private readonly NotificationRepository $repo){}public function list(string $userId,CollectionQuery $q):array{return $this->repo->list($userId,$q);}public function markRead(string $userId,string $id):array{if(!$this->repo->markRead($userId,$id)){throw new ApiException(404,'RESOURCE_NOT_FOUND','Không tìm thấy thông báo.');}return ['id'=>$id,'isRead'=>true];}}
