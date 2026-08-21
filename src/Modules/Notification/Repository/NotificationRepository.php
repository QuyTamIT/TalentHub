<?php
declare(strict_types=1);
namespace TalentHub\Modules\Notification\Repository;
use PDO;use TalentHub\Http\CollectionQuery;
final class NotificationRepository
{
 public function __construct(private readonly PDO $pdo){}
 public function list(string $userId,CollectionQuery $q):array{$where='userId=:uid';$p=['uid'=>$userId];if(isset($q->filters['read'])){$where.=' AND isRead=:read';$p['read']=$q->filters['read']==='true'?1:0;}$s=$this->pdo->prepare("SELECT id,notificationType,title,message,relatedEntityType,relatedEntityId,isRead,readAt,createdAt FROM notifications WHERE {$where} ORDER BY createdAt ".strtoupper($q->direction)." LIMIT {$q->limit} OFFSET {$q->offset}");$s->execute($p);return array_values($s->fetchAll());}
 public function markRead(string $userId,string $id):bool{$s=$this->pdo->prepare('UPDATE notifications SET isRead=1,readAt=COALESCE(readAt,UTC_TIMESTAMP(6)) WHERE id=? AND userId=?');$s->execute([$id,$userId]);return $s->rowCount()===1;}
}
