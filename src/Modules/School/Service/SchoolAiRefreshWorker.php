<?php
declare(strict_types=1);
namespace TalentHub\Modules\School\Service;
use TalentHub\Modules\School\Repository\DatabaseSchoolAiRefreshJobRepository;
final class SchoolAiRefreshWorker{public function __construct(private readonly DatabaseSchoolAiRefreshJobRepository $queue,private readonly SchoolAiInsightService $service){}public function runOnce():bool{$job=$this->queue->claim();if($job===null)return false;try{$this->service->refreshForSchool((string)$job['school_id'],(string)$job['aggregate_hash']);$this->queue->complete((int)$job['id']);}catch(\Throwable){$this->queue->fail((int)$job['id']);}return true;}}
