<?php
declare(strict_types=1);
namespace TalentHub\Auth\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Http\ApiException;

final class LoginRateLimiter
{
    private const WINDOW_SECONDS=300;
    private const BLOCK_SECONDS=900;
    private const IDENTITY_LIMIT=5;
    private const IP_LIMIT=20;
    private ?bool $storageAvailable=null;

    public function __construct(private readonly PDO $pdo) {}

    public function assertAllowed(string $email,?string $ip): void
    {
        if(!$this->hasStorage()){return;}
        $keys=array_values($this->keys($email,$ip));$placeholders=implode(',',array_fill(0,count($keys),'?'));
        $statement=$this->pdo->prepare("SELECT blockedUntil FROM auth_rate_limits WHERE bucketKey IN ({$placeholders}) AND blockedUntil>UTC_TIMESTAMP(6) ORDER BY blockedUntil DESC LIMIT 1");$statement->execute($keys);$blocked=$statement->fetchColumn();
        if(is_string($blocked)){$retry=max(1,strtotime($blocked.' UTC')-time());throw new ApiException(429,'RATE_LIMIT_EXCEEDED','Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau.',[],['Retry-After'=>(string)$retry]);}
    }

    public function recordFailure(string $email,?string $ip): void
    {
        if(!$this->hasStorage()){return;}
        $this->pdo->exec('DELETE FROM auth_rate_limits WHERE updatedAt<UTC_TIMESTAMP(6)-INTERVAL 30 DAY LIMIT 100');
        foreach($this->keys($email,$ip) as $scope=>$key){$this->increment($key,$scope,$scope==='identity'?self::IDENTITY_LIMIT:self::IP_LIMIT);}
    }

    public function clearIdentity(string $email,?string $ip): void
    {
        if(!$this->hasStorage()){return;}
        $statement=$this->pdo->prepare('DELETE FROM auth_rate_limits WHERE bucketKey=?');$statement->execute([$this->keys($email,$ip)['identity']]);
    }

    /** @return array{identity:string,ip?:string} */
    private function keys(string $email,?string $ip): array
    {
        $normalizedEmail=strtolower(trim($email));$normalizedIp=trim((string)$ip);$keys=['identity'=>hash('sha256','login:identity:'.$normalizedEmail.'|'.$normalizedIp)];
        if($normalizedIp!==''){$keys['ip']=hash('sha256','login:ip:'.$normalizedIp);}return $keys;
    }

    private function increment(string $key,string $scope,int $limit): void
    {
        $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$this->pdo->beginTransaction();
        try{
            $statement=$this->pdo->prepare('INSERT INTO auth_rate_limits(bucketKey,scope,failureCount,windowStartedAt) VALUES(?,?,0,UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE bucketKey=VALUES(bucketKey)');$statement->execute([$key,$scope]);
            $statement=$this->pdo->prepare('SELECT failureCount,windowStartedAt,blockedUntil FROM auth_rate_limits WHERE bucketKey=? FOR UPDATE');$statement->execute([$key]);$row=$statement->fetch();
            $windowStart=new DateTimeImmutable((string)$row['windowStartedAt'],new DateTimeZone('UTC'));$count=$now->getTimestamp()-$windowStart->getTimestamp()>=self::WINDOW_SECONDS?1:(int)$row['failureCount']+1;
            $window=$count===1?$now->format('Y-m-d H:i:s.u'):(string)$row['windowStartedAt'];$blocked=$count>=$limit?$now->modify('+'.self::BLOCK_SECONDS.' seconds')->format('Y-m-d H:i:s.u'):null;
            $statement=$this->pdo->prepare('UPDATE auth_rate_limits SET failureCount=?,windowStartedAt=?,blockedUntil=? WHERE bucketKey=?');$statement->execute([$count,$window,$blocked,$key]);$this->pdo->commit();
        }catch(\Throwable $exception){if($this->pdo->inTransaction()){$this->pdo->rollBack();}throw $exception;}
    }

    private function hasStorage(): bool
    {
        if($this->storageAvailable!==null){return $this->storageAvailable;}
        $statement=$this->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='auth_rate_limits'");
        return $this->storageAvailable=(int)$statement->fetchColumn()===1;
    }
}
