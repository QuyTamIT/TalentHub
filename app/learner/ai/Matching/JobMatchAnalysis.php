<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Matching;

final class JobMatchAnalysis
{
    /**
     * @param list<string> $strengthSkillCodes
     * @param list<string> $gapSkillCodes
     * @param list<array{skill_code:string,explanation:string}> $gapExplanations
     * @param list<string> $evidenceRefIds
     */
    public function __construct(
        private readonly string $catalogId,
        private readonly string $analysis,
        private readonly array $strengthSkillCodes,
        private readonly array $gapSkillCodes,
        private readonly array $gapExplanations,
        private readonly array $evidenceRefIds,
    ) {
    }

    public function catalogId(): string { return $this->catalogId; }
    public function analysis(): string { return $this->analysis; }
    /** @return list<string> */ public function strengthSkillCodes(): array { return $this->strengthSkillCodes; }
    /** @return list<string> */ public function gapSkillCodes(): array { return $this->gapSkillCodes; }
    /** @return list<array{skill_code:string,explanation:string}> */ public function gapExplanations(): array { return $this->gapExplanations; }
    /** @return list<string> */ public function evidenceRefIds(): array { return $this->evidenceRefIds; }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'catalog_id' => $this->catalogId,
            'analysis' => $this->analysis,
            'strength_skill_codes' => $this->strengthSkillCodes,
            'gap_skill_codes' => $this->gapSkillCodes,
            'gap_explanations' => $this->gapExplanations,
            'evidence_ref_ids' => $this->evidenceRefIds,
        ];
    }
}
