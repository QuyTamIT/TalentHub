<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Persistence;

use PDO;
use RuntimeException;
use TalentHub\Learner\Ai\Domain\RecommendationContext;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\JobMatchAnalysis;
use TalentHub\Learner\Ai\Matching\JobMatchResult;
use TalentHub\Learner\Ai\Matching\OpportunityCandidate;

final class DatabaseJobMatchRepository implements JobMatchRepository
{
    private const CAPABILITY = 'job_match';
    /** @var \Closure():string */ private readonly \Closure $clock;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $providerVersion,
        private readonly string $modelVersion,
        private readonly string $promptVersion,
        ?callable $clock = null,
    ) {
        $this->clock = $clock === null ? static fn (): string => gmdate('Y-m-d\TH:i:s.uP') : \Closure::fromCallable($clock);
    }

    public function latestValid(string $studentId, array $activeCatalogIds): ?array
    {
        $active = array_fill_keys(array_values(array_filter($activeCatalogIds, 'is_string')), true);
        $query = $this->pdo->prepare("SELECT id FROM learner_recommendation_runs WHERE studentId=:student AND capability=:capability AND status='completed' ORDER BY createdAt DESC,id DESC");
        $query->execute(['student' => $studentId, 'capability' => self::CAPABILITY]);
        foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $runId) {
            $run = $this->readRun($studentId, (string) $runId);
            if ($run === null || $run['items'] === [] || count($run['items']) > 10) continue;
            $state = (string) ($run['state'] ?? 'ready_model');
            $isNoMatch = $state === 'no_matching_jobs';
            if (!in_array($state, ['ready_model', 'no_matching_jobs'], true) || ($isNoMatch && count($run['items']) !== 1)) continue;
            $ranks = [];
            $valid = true;
            foreach ($run['items'] as $item) {
                $id = (string) ($item['catalogId'] ?? '');
                $rank = filter_var($item['rankPosition'] ?? null, FILTER_VALIDATE_INT);
                $structured = filter_var($item['structuredScore'] ?? null, FILTER_VALIDATE_INT);
                $match = filter_var($item['matchScore'] ?? null, FILTER_VALIDATE_INT);
                $scoreOutsideState = $isNoMatch ? ($match < 0 || $match >= 40) : ($match < 40 || $match > 100);
                if (!isset($active[$id]) || $rank === false || $structured === false || $match === false || $structured !== $match
                    || $scoreOutsideState || $item['geminiScore'] !== null) { $valid = false; break; }
                $ranks[] = $rank;
            }
            sort($ranks);
            if ($valid && $ranks === range(1, count($run['items']))) return $run;
        }
        return null;
    }

    public function createPendingRun(string $studentId, RecommendationInput $input, RecommendationContext $context): array
    {
        $studentId = self::required($studentId, 'Job match student id is required.');
        $idempotency = self::required((string) $context->idempotencyKey(), 'Job match idempotency key is required.');
        $existing = $this->byIdempotency($studentId, $idempotency);
        if ($existing !== null) { $existing['reused'] = true; return $existing; }
        return $this->transaction(function () use ($studentId, $input, $context, $idempotency): array {
            $snapshot = $this->pdo->prepare('SELECT id FROM learner_recommendation_input_snapshots WHERE studentId=:student AND contentHash=:hash LIMIT 1');
            $snapshot->execute(['student' => $studentId, 'hash' => $input->contentHash()]);
            $snapshotId = $snapshot->fetchColumn();
            if ($snapshotId === false) {
                $snapshotId = self::uuid();
                $this->pdo->prepare('INSERT INTO learner_recommendation_input_snapshots (id,studentId,schemaVersion,contentHash,consentScopesJson,qualityFlagsJson,payloadJson,sourceUpdatedAt,createdAt) VALUES (?,?,?,?,?,?,?,?,?)')->execute([
                    $snapshotId, $studentId, $input->schemaVersion(), $input->contentHash(), self::json($context->allowedScopes()), self::json($input->qualityFlags()), self::json($input->payload()), self::json($input->sourceUpdatedAt()), $this->now(),
                ]);
                $insertEvidence = $this->pdo->prepare('INSERT INTO learner_recommendation_snapshot_evidence (id,snapshotId,sourceType,sourceId,observedAt,safeValueJson,createdAt) VALUES (?,?,?,?,?,?,?)');
                $normalized = [];
                foreach ($input->evidenceReferences() as $ref) {
                    $logicalType = trim((string) ($ref['source_type'] ?? ''));
                    $type = EvidenceSourceTypeNormalizer::canonical($logicalType);
                    $id = (string) ($ref['source_id'] ?? '');
                    if ($type === '' || $id === '' || !is_array($ref['safe_value'] ?? null)) continue;
                    $key = $type . ':' . $id;
                    $candidate = [
                        'logicalType' => $logicalType,
                        'sourceType' => $type,
                        'sourceId' => $id,
                        'observedAt' => $this->databaseTimestamp($ref['observed_at'] ?? null),
                        'safeValueJson' => self::json($ref['safe_value']),
                    ];
                    if (isset($normalized[$key])) {
                        $normalized[$key] = EvidenceSourceTypeNormalizer::preferSnapshotEvidence($normalized[$key], $candidate);
                    } else {
                        $normalized[$key] = $candidate;
                    }
                }
                ksort($normalized, SORT_STRING);
                foreach ($normalized as $evidence) {
                    $insertEvidence->execute([self::uuid(), $snapshotId, $evidence['sourceType'], $evidence['sourceId'], $evidence['observedAt'], $evidence['safeValueJson'], $this->now()]);
                }
            }
            $runId = self::uuid();
            $now = $this->now();
            $this->pdo->prepare("INSERT INTO learner_recommendation_runs (id,studentId,snapshotId,idempotencyKey,engineType,status,ruleVersion,provider,modelVersion,promptVersion,fallbackReason,safeErrorCode,capability,startedAt,completedAt,createdAt) VALUES (?,?,?,?, 'rule','pending','job-match-pending-1.0.0',NULL,NULL,NULL,NULL,NULL,?,?,NULL,?)")->execute([
                $runId, $studentId, $snapshotId, $idempotency, self::CAPABILITY, $now, $now,
            ]);
            $this->audit($runId, $studentId, $context->requestId() ?? self::uuid(), 'job_match_run_created', 'pending', ['capability' => self::CAPABILITY]);
            return ['runId' => $runId, 'snapshotId' => $snapshotId, 'studentId' => $studentId, 'idempotencyKey' => $idempotency, 'status' => 'pending', 'reused' => false];
        });
    }

    public function completeRun(string $studentId, string $runId, array $records, array $runAnalysis = []): array
    {
        if ($records === [] || count($records) > 10) throw new \InvalidArgumentException('Job match completion requires one to ten records.');
        $resultState = (string) ($runAnalysis['state'] ?? 'ready_model');
        if (!in_array($resultState, ['ready_model', 'no_matching_jobs'], true) || ($resultState === 'no_matching_jobs' && count($records) !== 1)) {
            throw new \InvalidArgumentException('Job match completion state is invalid.');
        }
        return $this->transaction(function () use ($studentId, $runId, $records, $runAnalysis, $resultState): array {
            $owned = $this->ownedPending($studentId, $runId);
            $snapshotId = (string) $owned['snapshotId'];
            $now = $this->now();
            $seen = [];
            foreach ($records as $offset => $record) {
                $candidate = $record['candidate'] ?? null; $match = $record['match'] ?? null; $analysis = $record['analysis'] ?? null;
                if (!$candidate instanceof OpportunityCandidate || !$match instanceof JobMatchResult || !$analysis instanceof JobMatchAnalysis || $analysis->catalogId() !== $candidate->catalogId()) {
                    throw new \InvalidArgumentException('Job match completion record is malformed.');
                }
                $id = $candidate->catalogId();
                $totalScore = $match->score()->totalScore();
                $scoreOutsideState = $resultState === 'no_matching_jobs' ? $totalScore >= 40 : $totalScore < 40;
                if (isset($seen[$id]) || $scoreOutsideState) throw new \InvalidArgumentException('Job match completion contains a duplicate or a score inconsistent with its state.');
                $seen[$id] = true;
                $itemId = self::uuid(); $rank = $offset + 1; $score = $match->score()->totalScore();
                $payload = $candidate->providerPayload();
                $itemAnalysis = [
                    'analysis' => $analysis->toArray(), 'score_breakdown' => $match->score()->breakdown(),
                    'role' => ['code' => $match->role()->code(), 'title' => $match->role()->title()],
                    'skill_gap' => is_array($record['skill_gap'] ?? null) ? $record['skill_gap'] : [],
                    'recommended_activities' => is_array($record['activities'] ?? null) ? $record['activities'] : [],
                    'enterprise_id' => $candidate->enterpriseId(), 'provider_name' => $candidate->providerName(),
                ];
                $this->pdo->prepare("INSERT INTO learner_recommendation_items (id,runId,itemType,title,summary,priority,confidenceBand,actionJson,lifecycleStatus,catalogId,rankPosition,structuredScore,geminiScore,matchScore,analysisJson,createdAt) VALUES (?,?,'activity',?,?,?,?,?,'active',?,?,?,NULL,?,?,?)")->execute([
                    $itemId, $runId, $candidate->title(), (string) ($payload['summary'] ?: $candidate->title()), $rank, self::confidence($score), self::json(['catalog_id' => $id, 'url' => $candidate->canonicalUrl()]), $id, $rank, $score, $score, self::json($itemAnalysis), $now,
                ]);
                $linkedEvidence = [];
                foreach ($analysis->evidenceRefIds() as $ref) {
                    [$sourceType, $sourceId] = array_pad(explode(':', $ref, 2), 2, '');
                    $canonicalEvidenceKey = EvidenceSourceTypeNormalizer::canonical($sourceType) . ':' . $sourceId;
                    if (isset($linkedEvidence[$canonicalEvidenceKey])) continue;
                    $linkedEvidence[$canonicalEvidenceKey] = true;
                    $this->linkEvidence($snapshotId, $itemId, $ref, $now);
                }
            }
            $this->pdo->prepare("UPDATE learner_recommendation_items SET lifecycleStatus='superseded' WHERE lifecycleStatus='active' AND runId IN (SELECT id FROM learner_recommendation_runs WHERE studentId=? AND capability=? AND id<>?)")->execute([$studentId, self::CAPABILITY, $runId]);
            $runAnalysis = array_merge($runAnalysis, ['state' => $resultState, 'analysis_origin' => 'gemini', 'score_origin' => 'deterministic_40_35_25']);
            $update = $this->pdo->prepare("UPDATE learner_recommendation_runs SET engineType='model',status='completed',ruleVersion=NULL,provider=?,modelVersion=?,promptVersion=?,analysisJson=?,completedAt=? WHERE id=? AND studentId=? AND capability=? AND status='pending'");
            $update->execute([$this->providerVersion, $this->modelVersion, $this->promptVersion, self::json($runAnalysis), $now, $runId, $studentId, self::CAPABILITY]);
            if ($update->rowCount() !== 1) throw new RuntimeException('Job match run is not pending.');
            $this->audit($runId, $studentId, self::uuid(), 'job_match_completed', 'completed', ['response_hash' => hash('sha256', self::json($runAnalysis))]);
            return $this->readRun($studentId, $runId) ?? throw new RuntimeException('Completed job match run not found.');
        });
    }

    public function failRun(string $studentId, string $runId, string $safeCode): void
    {
        $this->ownedPending($studentId, $runId);
        $update = $this->pdo->prepare("UPDATE learner_recommendation_runs SET status='failed',safeErrorCode=?,completedAt=? WHERE id=? AND studentId=? AND capability=? AND status='pending'");
        $update->execute([self::required($safeCode, 'Safe code is required.'), $this->now(), $runId, $studentId, self::CAPABILITY]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Job match run is not pending.');
    }

    /** @return array<string,mixed>|null */
    private function readRun(string $studentId, string $runId): ?array
    {
        $q = $this->pdo->prepare('SELECT * FROM learner_recommendation_runs WHERE id=? AND studentId=? AND capability=?');
        $q->execute([$runId, $studentId, self::CAPABILITY]); $run = $q->fetch(PDO::FETCH_ASSOC); if ($run === false) return null;
        $items = $this->pdo->prepare("SELECT * FROM learner_recommendation_items WHERE runId=? AND lifecycleStatus='active' ORDER BY rankPosition,id");
        $items->execute([$runId]); $run['items'] = $items->fetchAll(PDO::FETCH_ASSOC);
        foreach ($run['items'] as &$item) { $item['analysis'] = self::decode($item['analysisJson'] ?? null); $item['action'] = self::decode($item['actionJson'] ?? null); }
        unset($item); $run['analysis'] = self::decode($run['analysisJson'] ?? null); $run['runId'] = $run['id']; unset($run['id']);
        $run['state'] = (string) ($run['analysis']['state'] ?? 'ready_model');
        return $run;
    }

    private function linkEvidence(string $snapshotId, string $itemId, string $ref, string $now): void
    {
        [$type, $sourceId] = array_pad(explode(':', $ref, 2), 2, '');
        $q = $this->pdo->prepare('SELECT * FROM learner_recommendation_snapshot_evidence WHERE snapshotId=? AND sourceType=? AND sourceId=? LIMIT 1');
        $row = false;
        foreach (EvidenceSourceTypeNormalizer::lookupTypes($type) as $lookupType) {
            $q->execute([$snapshotId, $lookupType, $sourceId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) break;
        }
        if ($row === false) throw new RuntimeException('Job match evidence is not part of the snapshot: ' . $ref);
        $this->pdo->prepare('INSERT INTO learner_recommendation_evidence (id,itemId,snapshotEvidenceId,sourceType,sourceId,observedAt,contributionLabel,safeValueJson,createdAt) VALUES (?,?,?,?,?,?,?,?,?)')->execute([
            self::uuid(), $itemId, $row['id'], $row['sourceType'], $row['sourceId'], $row['observedAt'], 'job_match_evidence', $row['safeValueJson'], $now,
        ]);
    }

    /** @return array<string,mixed> */ private function ownedPending(string $studentId, string $runId): array { $q=$this->pdo->prepare("SELECT id,snapshotId,status FROM learner_recommendation_runs WHERE id=? AND studentId=? AND capability=?"); $q->execute([$runId,$studentId,self::CAPABILITY]); $r=$q->fetch(PDO::FETCH_ASSOC); if($r===false||$r['status']!=='pending') throw new RuntimeException('Job match run not found or not pending.'); return $r; }
    /** @return array<string,mixed>|null */ private function byIdempotency(string $studentId,string $key):?array{$q=$this->pdo->prepare('SELECT id AS runId,snapshotId,studentId,idempotencyKey,status FROM learner_recommendation_runs WHERE studentId=? AND idempotencyKey=? AND capability=?');$q->execute([$studentId,$key,self::CAPABILITY]);$r=$q->fetch(PDO::FETCH_ASSOC);return $r===false?null:$r;}
    private function audit(string $runId,string $studentId,string $requestId,string $action,string $status,array $meta):void{$this->pdo->prepare('INSERT INTO learner_recommendation_audit_events (id,runId,studentId,requestId,actorType,action,engineMetadataJson,status,createdAt) VALUES (?,?,?,?,?,?,?,?,?)')->execute([self::uuid(),$runId,$studentId,$requestId,'system',$action,self::json($meta),$status,$this->now()]);}
    private function now():string{return (new \DateTimeImmutable(($this->clock)()))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');}
    private function databaseTimestamp(mixed $value):?string{if($value===null||$value==='')return null;if(!is_string($value))throw new \InvalidArgumentException('Evidence timestamp must be a string or null.');try{return(new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');}catch(\Throwable $e){throw new \InvalidArgumentException('Evidence timestamp must be UTC-compatible.',0,$e);}}
    private function transaction(callable $fn):mixed{$own=!$this->pdo->inTransaction();if($own)$this->pdo->beginTransaction();try{$v=$fn();if($own)$this->pdo->commit();return $v;}catch(\Throwable $e){if($own&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}}
    private static function confidence(int $score):string{return $score>=80?'high':($score>=60?'medium':'low');}
    private static function required(string $v,string $m):string{$v=trim($v);if($v==='')throw new \InvalidArgumentException($m);return $v;}
    private static function json(mixed $v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    /** @return array<string,mixed> */ private static function decode(mixed $v):array{if(!is_string($v)||$v==='')return[];$d=json_decode($v,true);return is_array($d)?$d:[];}
    private static function uuid():string{$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);}
}
