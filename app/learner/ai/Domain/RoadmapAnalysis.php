<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Domain;

final class RoadmapAnalysis
{
    public const CONTRACT_VERSION = 'learner-roadmap-1.0.0';

    /** @var list<RoadmapDirection> */
    private readonly array $alternativeDirections;
    /** @var list<RoadmapInsight> */
    private readonly array $insights;
    /** @var list<RoadmapPhase> */
    private readonly array $phases;
    /** @var list<string> */
    private readonly array $recommendedActivitySourceIds;
    /** @var array<string,string|null> */
    private readonly array $engineMetadata;

    /**
     * @param list<RoadmapDirection> $alternativeDirections
     * @param list<RoadmapInsight> $insights
     * @param list<RoadmapPhase> $phases
     * @param list<string> $recommendedActivitySourceIds
     * @param array<string,string|null> $engineMetadata
     */
    public function __construct(
        private readonly string $origin,
        private readonly string $executiveSummary,
        private readonly RoadmapDirection $primaryDirection,
        array $alternativeDirections,
        array $insights,
        array $phases,
        private readonly string $confidenceBand,
        array $recommendedActivitySourceIds,
        array $engineMetadata,
    ) {
        if (!in_array($origin, ['model', 'rule_fallback'], true)) {
            throw new \InvalidArgumentException('Roadmap analysis origin is invalid.');
        }
        if (trim($executiveSummary) === '' || !in_array($confidenceBand, ['low', 'medium', 'high'], true)) {
            throw new \InvalidArgumentException('Roadmap analysis summary or confidence is invalid.');
        }
        if (count($alternativeDirections) !== 2 || count($insights) !== 3 || count($phases) !== 3) {
            throw new \InvalidArgumentException('Roadmap analysis shape is invalid.');
        }
        foreach ($alternativeDirections as $direction) {
            if (!$direction instanceof RoadmapDirection) {
                throw new \InvalidArgumentException('Roadmap alternative directions are invalid.');
            }
        }
        foreach ($insights as $insight) {
            if (!$insight instanceof RoadmapInsight) {
                throw new \InvalidArgumentException('Roadmap insights are invalid.');
            }
        }
        foreach ($phases as $phase) {
            if (!$phase instanceof RoadmapPhase) {
                throw new \InvalidArgumentException('Roadmap phases are invalid.');
            }
        }
        $provider = trim((string) ($engineMetadata['provider'] ?? ''));
        $model = trim((string) ($engineMetadata['model_version'] ?? ''));
        $prompt = trim((string) ($engineMetadata['prompt_version'] ?? ''));
        $rule = trim((string) ($engineMetadata['rule_version'] ?? ''));
        if ($origin === 'model' && ($provider === '' || $model === '' || $prompt === '' || $rule !== '')) {
            throw new \InvalidArgumentException('Roadmap model metadata is required.');
        }
        if ($origin === 'rule_fallback' && ($rule === '' || $provider !== '' || $model !== '' || $prompt !== '')) {
            throw new \InvalidArgumentException('Roadmap fallback metadata is required.');
        }
        $this->alternativeDirections = array_values($alternativeDirections);
        $this->insights = array_values($insights);
        $this->phases = array_values($phases);
        $this->recommendedActivitySourceIds = array_values($recommendedActivitySourceIds);
        $this->engineMetadata = [
            'provider' => $provider === '' ? null : $provider,
            'model_version' => $model === '' ? null : $model,
            'prompt_version' => $prompt === '' ? null : $prompt,
            'rule_version' => $rule === '' ? null : $rule,
        ];
    }

    public function origin(): string { return $this->origin; }
    public function executiveSummary(): string { return $this->executiveSummary; }
    public function primaryDirection(): RoadmapDirection { return $this->primaryDirection; }
    /** @return list<RoadmapDirection> */ public function alternativeDirections(): array { return $this->alternativeDirections; }
    /** @return list<RoadmapInsight> */ public function insights(): array { return $this->insights; }
    /** @return list<RoadmapPhase> */ public function phases(): array { return $this->phases; }
    public function confidenceBand(): string { return $this->confidenceBand; }
    /** @return list<string> */ public function recommendedActivitySourceIds(): array { return $this->recommendedActivitySourceIds; }
    /** @return array<string,string|null> */ public function engineMetadata(): array { return $this->engineMetadata; }

    /** @return list<string> */
    public function evidenceReferenceIds(): array
    {
        $references = [];
        foreach ($this->insights as $insight) {
            foreach ($insight->evidenceReferenceIds() as $reference) $references[$reference] = true;
        }
        foreach ($this->phases as $phase) {
            foreach ($phase->evidenceReferenceIds() as $reference) $references[$reference] = true;
            foreach ($phase->tasks() as $task) {
                foreach ($task->evidenceReferenceIds() as $reference) $references[$reference] = true;
            }
        }
        $result = array_keys($references);
        sort($result, SORT_STRING);
        return $result;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'contract_version' => self::CONTRACT_VERSION,
            'analysis_origin' => $this->origin,
            'executive_summary' => $this->executiveSummary,
            'confidence_band' => $this->confidenceBand,
            'primary_direction' => $this->primaryDirection->toArray(),
            'alternative_directions' => array_map(static fn (RoadmapDirection $direction): array => $direction->toArray(), $this->alternativeDirections),
            'insights' => array_map(static fn (RoadmapInsight $insight): array => $insight->toArray(), $this->insights),
            'phases' => array_map(static fn (RoadmapPhase $phase): array => $phase->toArray(), $this->phases),
            'recommended_activity_source_ids' => $this->recommendedActivitySourceIds,
            'engine' => $this->engineMetadata,
        ];
    }
}
