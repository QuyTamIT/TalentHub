<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Provider;
final class ProviderRetryAfterException extends \RuntimeException
{
 public function __construct(private readonly string $safeCategory,private readonly int $retryAfterSeconds){parent::__construct($safeCategory);}
 public function safeCategory():string{return $this->safeCategory;}
 public function retryAfterSeconds():int{return max(0,min(86400,$this->retryAfterSeconds));}
}
