<?php
declare(strict_types=1);
namespace TalentHub\Learner\Ai\Service;

use Closure;
use PDO;
use TalentHub\Learner\Ai\Domain\RecommendationInput;
use TalentHub\Learner\Ai\Matching\ActivityMatch;
use TalentHub\Learner\Ai\Matching\LearnerOpportunityProfile;
use TalentHub\Learner\Ai\Sources\Database\DatabaseActivityCandidateSource;

final class ActivityMatchService
{
    public const VERSION = 'activity-model-v4';
    public const DEVELOPMENT_THRESHOLD = 70;
    public function __construct(private readonly PDO $pdo, private readonly Closure $snapshot, private readonly Closure $scopes, private readonly ?\TalentHub\Learner\Ai\Model\ModelActivityMatchEngine $engine = null, private readonly string $modelVersion = '') {}
    public function latest(string $studentId): array { return $this->resolve($studentId, false); }
    public function generate(string $studentId): array { return $this->resolve($studentId, true); }

    private function profile(RecommendationInput $input): LearnerOpportunityProfile
    {
        return LearnerOpportunityProfile::fromInput($input);
    }

    private function resolve(string $studentId, bool $generate): array
    {
        if (array_diff(['skills','assessment','activity','evaluation'], ($this->scopes)($studentId)) !== []) return ['state'=>'consent_required','items'=>[]];
        $input = ($this->snapshot)($studentId);
        $profile = $this->profile($input);
        if ($profile->skills() === []) return ['state'=>'insufficient_data','items'=>[]];
        $candidates = (new DatabaseActivityCandidateSource($this->pdo))->candidates($studentId);
        if ($candidates === []) return ['state'=>'no_matches','items'=>[]];
        $hash = $this->hash($input, $candidates);
        $query = $this->pdo->prepare('SELECT inputHash,payloadJson FROM learner_activity_match_runs WHERE studentId=? ORDER BY createdAt DESC,id DESC LIMIT 1');
        $query->execute([$studentId]);
        $saved = $query->fetch(PDO::FETCH_ASSOC);
        if ($saved && hash_equals($saved['inputHash'], $hash)) return json_decode($saved['payloadJson'], true, 512, JSON_THROW_ON_ERROR);
        if (!$generate) {
            if (!$saved) return ['state'=>'not_generated','items'=>[]];
            $result = json_decode($saved['payloadJson'], true, 512, JSON_THROW_ON_ERROR);
            // Never show a closed or newly inaccessible activity from a cached result.
            $ids = array_column(array_map(static fn ($c) => $c->toArray(), $candidates), 'activity_id');
            $result['items'] = array_values(array_filter($result['items'], static fn ($item) => in_array($item['activity_id'], $ids, true)));
            $result['state'] = 'stale_model';
            return $result;
        }
        $items = [];
        foreach ($candidates as $candidate) {
            $develop = [];
            foreach ($candidate->skills as $code) {
                $score = $profile->skillScore($code);
                if ($score === null) {
                    continue;
                }
                if ($score < self::DEVELOPMENT_THRESHOLD) {
                    $develop[] = $code;
                }
            }
            if ($develop === []) {
                continue;
            }
            $total = 0;
            foreach ($develop as $code) {
                $score = $profile->skillScore($code);
                $total += 30 + 60 * (100 - max(0, min(100, (int) $score))) / 100
                    + (in_array($code, $profile->confirmedExperienceTags(), true) ? 10 : 0);
            }
            $fit = (int) round($total / count($develop));
            $labels = [];
            foreach ($develop as $code) {
                $labels[] = $code . ' (' . $profile->skillScore($code) . '/100)';
            }
            $reasons = [
                'Cần cải thiện các kỹ năng đang dưới ngưỡng gợi ý ' . self::DEVELOPMENT_THRESHOLD . '/100: ' . implode(', ', $labels) . '.',
                'Hoạt động trùng các kỹ năng đang cần phát triển: ' . implode(', ', $develop) . '.',
            ];
            $items[] = (new ActivityMatch($candidate, $fit, $reasons, $develop))->toArray();
        }
        usort($items, static fn ($a,$b) => ($b['score'] <=> $a['score']) ?: strcmp($a['activity_id'],$b['activity_id']));
        $items = array_slice($items, 0, 3);
        if ($items !== []) {
            if ($this->engine === null) throw new \RuntimeException('activity_provider_not_configured');
            $items = $this->engine->generate($input, $items);
        }
        // Re-read after the provider call. Never persist a result built on a superseded snapshot.
        if (array_diff(['skills','assessment','activity','evaluation'], ($this->scopes)($studentId)) !== []) return ['state'=>'consent_required','items'=>[]];
        $currentInput = ($this->snapshot)($studentId);
        $currentCandidates = (new DatabaseActivityCandidateSource($this->pdo))->candidates($studentId);
        if (!hash_equals($hash, $this->hash($currentInput, $currentCandidates))) return ['state'=>'stale_model','items'=>[]];
        $result = ['state'=>$items === [] ? 'no_matches' : 'completed','engine'=>$items === [] ? 'eligibility_rules' : 'model','score_version'=>self::VERSION,'input_hash'=>$hash,'generated_at'=>gmdate('c'),'items'=>$items];
        $insert = $this->pdo->prepare('INSERT INTO learner_activity_match_runs (id,studentId,inputHash,payloadJson,createdAt) VALUES (?,?,?,?,?)');
        $runId = sprintf('%016x', (int)(microtime(true) * 1000000)) . bin2hex(random_bytes(8));
        $insert->execute([$runId, $studentId, $hash, json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), gmdate('Y-m-d H:i:s')]);
        return $result;
    }

    private function hash(RecommendationInput $input, array $candidates): string
    {
        return hash('sha256', json_encode([self::VERSION, $this->modelVersion, $input->contentHash(), array_map(static fn ($c) => $c->toArray(), $candidates)], JSON_THROW_ON_ERROR));
    }
}
