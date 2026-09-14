<?php
declare(strict_types=1);
namespace TalentHub\Modules\School\Service;

use Closure;
use TalentHub\Learner\Ai\Config\RecommendationConfig;
use TalentHub\Learner\Ai\Provider\CircuitBreaker;
use TalentHub\Learner\Ai\Provider\RetryPolicy;

final class SchoolAiGeminiExplainer
{
    /** @var Closure(string,array<string,string>,string,int):array<string,mixed> */ private readonly Closure $transport;
    /** @var Closure(int):void */ private readonly Closure $sleeper;
    public function __construct(private readonly RecommendationConfig $config,?callable $transport=null,private readonly ?RetryPolicy $retry=null,private readonly ?CircuitBreaker $circuit=null,?callable $sleeper=null)
    { $this->transport=Closure::fromCallable($transport??[$this,'http']);$this->sleeper=Closure::fromCallable($sleeper??static function(int $milliseconds):void{if($milliseconds>0)usleep($milliseconds*1000);}); }
    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function __invoke(array $payload):array
    {
        if(!$this->config->enabled()||$this->config->apiUrl()===null||$this->config->apiKey()===null)throw new \RuntimeException('School AI provider unavailable.');
        $circuit=$this->circuit??new CircuitBreaker();if(!$circuit->allow())throw new \RuntimeException('School AI circuit open.');$retry=$this->retry??new RetryPolicy($this->config->maxAttempts());
        $body=json_encode(['systemInstruction'=>['parts'=>[['text'=>implode("\n",$payload['instructions']??[])]]],'contents'=>[['role'=>'user','parts'=>[['text'=>json_encode(['aggregate_evidence'=>$payload['aggregate_evidence']??[],'response_schema'=>['summary'=>'string','priorities'=>['string'],'confidence'=>'low|medium|high']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]]]],'generationConfig'=>['responseMimeType'=>'application/json']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);if(strlen($body)>100000)throw new \RuntimeException('School AI payload too large.');
        for ($attempt = 1; $attempt <= $this->config->maxAttempts(); $attempt++) {
            try {
                $response = ($this->transport)((string) $this->config->apiUrl(), ['Content-Type' => 'application/json', 'x-goog-api-key' => (string) $this->config->apiKey()], $body, $this->config->timeoutSeconds());
                $status = (int) ($response['status'] ?? 0);
                $this->logGeminiInteraction('Tạo bảng phân tích (School AI Insight)', $body, $response['body'] ?? '', $status);
                if (strlen((string) ($response['body'] ?? '')) > 200000) throw new \RuntimeException('School AI response too large.');
                if ($status === 200) {
                    $decoded = json_decode((string) ($response['body'] ?? ''), true, 64, JSON_THROW_ON_ERROR);
                    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    $result = is_string($text) ? json_decode($text, true, 32, JSON_THROW_ON_ERROR) : null;
                    if (is_array($result)) {
                        $circuit->recordSuccess();
                        return $result;
                    }
                    break;
                }
                if (!$retry->shouldRetry($status, null, $attempt)) break;
                $retryAfter = (int) ($response['headers']['retry-after'] ?? 0);
                ($this->sleeper)($retry->delayMs($attempt, $retryAfter > 0 ? $retryAfter : null));
            } catch (\JsonException) {
                break;
            } catch (\Throwable $e) {
                $this->logGeminiInteraction('Tạo bảng phân tích (School AI Insight)', $body, 'CURL/Network Error: ' . $e->getMessage(), 0);
                if (!$retry->shouldRetry(0, 'network', $attempt)) break;
                ($this->sleeper)($retry->delayMs($attempt));
            }
        }
        $circuit->recordFailure();
        throw new \RuntimeException('School AI provider unavailable.');
    }
    /** @param array<string,string> $headers @return array{status:int,body:string} */
    private function http(string $url,array $headers,string $body,int $timeout):array{$ch=curl_init($url);if($ch===false)throw new \RuntimeException('curl');$formatted=[];foreach($headers as $k=>$v)$formatted[]="{$k}: {$v}";curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$formatted,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>$timeout,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);$response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$retryAfter=defined('CURLINFO_RETRY_AFTER')?(int)curl_getinfo($ch,CURLINFO_RETRY_AFTER):0;curl_close($ch);return ['status'=>$status,'headers'=>$retryAfter>0?['retry-after'=>(string)$retryAfter]:[],'body'=>is_string($response)?$response:''];}

    private function logGeminiInteraction(string $action, mixed $requestBody, mixed $responseBody, int $status = 200): void
    {
        try {
            $root = dirname(__DIR__, 4);
            $logDir = $root . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            $timestamp = date('Y-m-d H:i:s');

            if (is_string($requestBody)) {
                $decodedReq = json_decode($requestBody, true);
                $reqText = ($decodedReq !== null)
                    ? json_encode($decodedReq, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : $requestBody;
            } else {
                $reqText = json_encode($requestBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if (is_string($responseBody)) {
                $decodedRes = json_decode($responseBody, true);
                $resText = ($decodedRes !== null)
                    ? json_encode($decodedRes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : $responseBody;
            } else {
                $resText = json_encode($responseBody, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $logEntry = "======================================================================\n"
                      . "[{$timestamp}] HÀNH ĐỘNG: {$action} | HTTP STATUS: {$status}\n"
                      . "======================================================================\n"
                      . "PROMPT TRUYỀN LÊN GEMINI:\n"
                      . $reqText . "\n\n"
                      . "TOÀN BỘ RESPONSE TỪ GEMINI:\n"
                      . $resText . "\n\n";

            @file_put_contents($logDir . '/gemini_response.log', $logEntry, FILE_APPEND | LOCK_EX);
            @file_put_contents($root . '/response.log', $logEntry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Logging should not break execution
        }
    }
}

