<?php
declare(strict_types=1);
namespace TalentHub\Tests\Integration;

use PDO;
use RuntimeException;
use TalentHub\Auth\Service\LoginRateLimiter;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Http\ApiException;

final class LoginRateLimitIntegration
{
    /** @return list<string> */
    public function run(PDO $pdo,string $database,MigrationRunner $runner): array
    {
        if(preg_match('/test/i',$database)!==1){throw new RuntimeException('Rate-limit integration requires DB_DATABASE containing test.');}
        if((string)$pdo->query('SELECT DATABASE()')->fetchColumn()!==$database){throw new RuntimeException('Connected database mismatch.');}
        if((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()!==0){throw new RuntimeException('Rate-limit integration requires an empty database.');}
        try{
            $runner->migrate();$limiter=new LoginRateLimiter($pdo);$email='target@example.com';$ip='203.0.113.10';
            for($attempt=1;$attempt<=4;$attempt++){$limiter->assertAllowed($email,$ip);$limiter->recordFailure($email,$ip);}
            $limiter->assertAllowed($email,$ip);$limiter->recordFailure($email,$ip);$this->expectBlocked($limiter,$email,$ip);
            $limiter->clearIdentity($email,$ip);$limiter->assertAllowed($email,$ip);
            for($attempt=5;$attempt<20;$attempt++){$other="attempt{$attempt}@example.com";$limiter->assertAllowed($other,$ip);$limiter->recordFailure($other,$ip);}
            $this->expectBlocked($limiter,'another@example.com',$ip);
            $rows=$pdo->query('SELECT bucketKey FROM auth_rate_limits')->fetchAll(PDO::FETCH_COLUMN);
            foreach($rows as $key){if(!is_string($key)||preg_match('/^[a-f0-9]{64}$/',$key)!==1){throw new RuntimeException('Rate-limit bucket leaked an unhashed identifier.');}}
            return ['persistent identity threshold: OK','Retry-After response metadata: OK','identity clear after success: OK','shared IP threshold: OK','hashed bucket storage: OK'];
        }finally{try{$runner->rollback(null,1);}catch(\Throwable){}}
    }

    private function expectBlocked(LoginRateLimiter $limiter,string $email,string $ip): void
    {
        try{$limiter->assertAllowed($email,$ip);}catch(ApiException $exception){$retry=(int)($exception->headers['Retry-After']??0);if($exception->status===429&&$exception->errorCode==='RATE_LIMIT_EXCEEDED'&&$retry>0){return;}throw $exception;}throw new RuntimeException('Expected login rate limit to block the request.');
    }
}
