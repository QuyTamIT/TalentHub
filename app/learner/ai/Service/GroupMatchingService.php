<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Service;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use TalentHub\Learner\Ai\Consent\ConsentPolicy;
use TalentHub\Learner\Ai\Observability\AiMetricsCollector;
use TalentHub\Learner\Ai\Snapshot\RecommendationSnapshotBuilder;
use TalentHub\Learner\Ai\Sources\Database\DatabaseCatalogSource;
use TalentHub\Learner\Assessment\Service\EducationBandResolver;
use Throwable;

final class GroupMatchingService
{
    private const ALLOWED_ITEM_TYPES = ['group', 'community'];
    private const ALLOWED_MATCH_PROFILE_KEYS = [
        'skill_codes',
        'assessment_directions',
        'goal_codes',
        'education_bands',
        'schedule_slots',
    ];
    private const ALLOWED_ASSESSMENT_FAMILIES = ['holland', 'mbti', 'disc', 'multiple_intelligence'];
    private const ALLOWED_ACTIONS = ['join_group', 'open_catalog_item'];

    private readonly DateTimeImmutable $clock;

    public function __construct(
        private readonly PDO $pdo,
        private readonly DatabaseCatalogSource $catalogSource,
        private readonly ConsentPolicy $consentPolicy,
        private readonly RecommendationSnapshotBuilder $snapshotBuilder,
        private readonly EducationBandResolver $educationBandResolver,
        private readonly ?AiMetricsCollector $metrics = null,
        ?DateTimeImmutable $clock = null,
    ) {
        $this->clock = ($clock ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Matches eligible groups and communities for the student using database evidence.
     *
     * @return list<array<string,mixed>>
     */
    public function match(string $studentId, int $limit = 10): array
    {
        $studentId = trim($studentId);
        if ($studentId === '') {
            return [];
        }

        $scopes = $this->consentPolicy->allowedScopes($studentId);
        // Hard filter: activity consent is mandatory for group matching
        if (!in_array('activity', $scopes, true)) {
            return [];
        }

        $snapshot = $this->snapshotBuilder->build($studentId, $scopes);
        $payload = $snapshot->payload();

        // Extract student profile & dimensions
        $studentSkills = $this->extractStudentSkills($payload, $scopes);
        $studentAssessments = $this->extractStudentAssessments($payload, $scopes);
        $studentGoals = $this->extractStudentGoals($studentId, $payload);
        $studentEducationBand = $this->resolveStudentEducationBand($studentId);
        $studentSchedule = $this->extractStudentSchedule($studentId, $payload);

        $catalogItems = $this->catalogSource->readForStudent($studentId);
        $matchedCandidates = [];

        foreach ($catalogItems as $candidate) {
            $itemType = (string) ($candidate['item_type'] ?? '');
            if (!in_array($itemType, self::ALLOWED_ITEM_TYPES, true)) {
                continue;
            }

            // Verify publish status and availability
            if (($candidate['publish_status'] ?? '') !== 'published') {
                continue;
            }
            $deadline = $candidate['deadline_at'] ?? null;
            if ($deadline !== null && $deadline <= $this->clock->format('Y-m-d\\TH:i:s.uP')) {
                continue;
            }
            $remaining = (int) ($candidate['availability']['remaining'] ?? 0);
            if ($remaining <= 0) {
                continue;
            }

            // Check protected traits in action / eligibility
            $action = $candidate['action'] ?? [];
            $eligibility = $candidate['eligibility'] ?? [];
            if (DatabaseCatalogSource::containsProtectedTraits($action) || DatabaseCatalogSource::containsProtectedTraits($eligibility)) {
                continue;
            }

            // Parse and validate match_profile
            $matchProfile = $this->extractAndValidateMatchProfile($action);
            if ($matchProfile === null) {
                continue;
            }

            // Hard filter: education band eligibility if specified
            if (!empty($matchProfile['education_bands'])) {
                if ($studentEducationBand === null || !in_array($studentEducationBand, $matchProfile['education_bands'], true)) {
                    continue;
                }
            }

            // Calculate match across the 5 dimensions
            $matchResult = $this->evaluateCandidateMatch(
                $candidate,
                $matchProfile,
                $scopes,
                $studentSkills,
                $studentAssessments,
                $studentGoals,
                $studentEducationBand,
                $studentSchedule,
            );

            if ($matchResult === null) {
                continue;
            }

            $matchedCandidates[] = $matchResult;
        }

        // Stable sort: Score DESC, Deadline ASC (nulls last), Catalog ID ASC
        usort($matchedCandidates, static function (array $a, array $b): int {
            if ($b['score'] !== $a['score']) {
                return $b['score'] <=> $a['score'];
            }
            $aDeadline = $a['deadline_at'] ?? '9999-12-31T23:59:59Z';
            $bDeadline = $b['deadline_at'] ?? '9999-12-31T23:59:59Z';
            if ($aDeadline !== $bDeadline) {
                return $aDeadline <=> $bDeadline;
            }
            return strcmp((string) $a['catalog_id'], (string) $b['catalog_id']);
        });

        return array_slice($matchedCandidates, 0, max(1, min(10, $limit)));
    }

    /**
     * Resolves an executable browser action for the given group/catalog item after revalidating availability.
     *
     * @return array{state:string,catalog_id:string,action:string,url:?string}
     */
    public function resolveAction(string $studentId, string $catalogId, string $action): array
    {
        $studentId = trim($studentId);
        $catalogId = trim($catalogId);
        $action = trim($action);

        if ($studentId === '' || $catalogId === '' || !in_array($action, self::ALLOWED_ACTIONS, true)) {
            return ['state' => 'join_unavailable', 'catalog_id' => $catalogId, 'action' => $action, 'url' => null];
        }

        $scopes = $this->consentPolicy->allowedScopes($studentId);
        if (!in_array('activity', $scopes, true)) {
            return ['state' => 'join_unavailable', 'catalog_id' => $catalogId, 'action' => $action, 'url' => null];
        }

        try {
            $statement = $this->pdo->prepare(
                'SELECT catalog_id, item_type, category, title, summary, publish_status, deadline_at, eligibility_json, capacity, enrolled_count, url, action_json, school_id, tenant_id FROM learner_ai_catalog_items WHERE catalog_id = :id'
            );
            $statement->execute(['id' => $catalogId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return ['state' => 'join_unavailable', 'catalog_id' => $catalogId, 'action' => $action, 'url' => null];
        }

        if (!is_array($row)) {
            return ['state' => 'join_unavailable', 'catalog_id' => $catalogId, 'action' => $action, 'url' => null];
        }

        if (($row['publish_status'] ?? '') !== 'published') {
            return ['state' => 'join_unavailable', 'catalog_id' => $catalogId, 'action' => $action, 'url' => null];
        }

        if ((int) ($row['capacity'] ?? 0) <= (int) ($row['enrolled_count'] ?? 0)) {
            return ['state' => 'join_unavailable', 'catalog_id' => $catalogId, 'action' => $action, 'url' => null];
        }

        $deadline = $row['deadline_at'] ?? null;
        $nowUtc = $this->clock->format('Y-m-d\\TH:i:s.uP');
        if ($deadline !== null && $deadline !== '' && $deadline <= $nowUtc) {
            return ['state' => 'join_unavailable', 'catalog_id' => $catalogId, 'action' => $action, 'url' => null];
        }

        // Protected traits rejection
        $eligibility = json_decode((string) ($row['eligibility_json'] ?? '{}'), true);
        $actionJson = json_decode((string) ($row['action_json'] ?? '{}'), true);
        if (DatabaseCatalogSource::containsProtectedTraits($eligibility) || DatabaseCatalogSource::containsProtectedTraits($actionJson)) {
            return ['state' => 'join_unavailable', 'catalog_id' => $catalogId, 'action' => $action, 'url' => null];
        }

        // Student school/tenant verification
        try {
            $studentStmt = $this->pdo->prepare('SELECT sp.id, sp.classId, sp.tenantId, c.schoolId FROM student_profiles sp LEFT JOIN classes c ON c.id = sp.classId WHERE sp.id = :id');
            $studentStmt->execute(['id' => $studentId]);
            $studentRow = $studentStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($studentRow)) {
                $schoolId = (string) ($studentRow['schoolId'] ?? '');
                $tenantId = trim((string) ($studentRow['tenantId'] ?? ''));
                if ($tenantId === '') $tenantId = $schoolId;
                if (($row['school_id'] ?? null) !== null && (string) $row['school_id'] !== '' && !hash_equals((string) $row['school_id'], $schoolId)) {
                    return ['state' => 'join_unavailable', 'catalog_id' => $catalogId, 'action' => $action, 'url' => null];
                }
                if (($row['tenant_id'] ?? null) !== null && (string) $row['tenant_id'] !== '' && !hash_equals((string) $row['tenant_id'], $tenantId)) {
                    return ['state' => 'join_unavailable', 'catalog_id' => $catalogId, 'action' => $action, 'url' => null];
                }
            }
        } catch (Throwable) {
            // Ignore DB error and proceed with safe fallback URL validation
        }

        $rawUrl = trim((string) ($row['url'] ?? ''));
        $safeUrl = null;
        if ($rawUrl !== '' && str_starts_with($rawUrl, '/') && !str_starts_with($rawUrl, '//')) {
            $safeUrl = $rawUrl;
        } else {
            $safeUrl = '/app/learner/groups.php?id=' . rawurlencode($catalogId);
        }

        $state = match ($action) {
            'join_group' => 'action_ready',
            'open_catalog_item' => 'catalog_opened',
            default => 'join_unavailable',
        };

        return [
            'state' => $state,
            'catalog_id' => $catalogId,
            'action' => $action,
            'url' => $safeUrl,
        ];
    }

    /**
     * @param array<string,mixed> $candidate
     * @param array<string,mixed> $matchProfile
     * @param list<string> $scopes
     * @param list<string> $studentSkills
     * @param list<array<string,mixed>> $studentAssessments
     * @param list<string> $studentGoals
     * @param list<array<string,mixed>> $studentSchedule
     * @return array<string,mixed>|null
     */
    private function evaluateCandidateMatch(
        array $candidate,
        array $matchProfile,
        array $scopes,
        array $studentSkills,
        array $studentAssessments,
        array $studentGoals,
        ?string $studentEducationBand,
        array $studentSchedule,
    ): ?array {
        $applicableWeight = 0;
        $earnedWeight = 0.0;
        $evidence = [];
        $dimensionMatches = [];

        // Dimension 1: Verified Skill Overlap (Max: 30)
        if (!empty($matchProfile['skill_codes']) && in_array('skills', $scopes, true) && !empty($studentSkills)) {
            $applicableWeight += 30;
            $candidateSkills = array_map('strtolower', $matchProfile['skill_codes']);
            $overlap = array_values(array_intersect($candidateSkills, $studentSkills));
            if (!empty($overlap)) {
                $ratio = count($overlap) / count($candidateSkills);
                $earnedWeight += 30.0 * $ratio;
                $dimensionMatches['skill'] = true;
                $evidence[] = [
                    'source_type' => 'skill',
                    'source_id' => implode(',', $overlap),
                    'observed_at' => $this->clock->format('Y-m-d\\TH:i:s.uP'),
                    'safe_value' => ['matched_skills' => $overlap],
                ];
            }
        }

        // Dimension 2: Assessment Direction Overlap (Max: 25)
        if (!empty($matchProfile['assessment_directions']) && in_array('assessment', $scopes, true) && !empty($studentAssessments)) {
            $candidateDirections = $matchProfile['assessment_directions'];
            $specifiedFamilies = array_keys($candidateDirections);
            $matchedFamilies = [];

            foreach ($specifiedFamilies as $family) {
                if (!is_string($family) || !in_array(strtolower($family), self::ALLOWED_ASSESSMENT_FAMILIES, true)) {
                    continue;
                }
                $directions = is_array($candidateDirections[$family]) ? $candidateDirections[$family] : [];
                if ($this->matchAssessmentFamily($family, $directions, $studentAssessments)) {
                    $matchedFamilies[$family] = $directions;
                }
            }

            if (!empty($specifiedFamilies)) {
                $applicableWeight += 25;
                if (!empty($matchedFamilies)) {
                    $ratio = count($matchedFamilies) / count($specifiedFamilies);
                    $earnedWeight += 25.0 * $ratio;
                    $dimensionMatches['assessment'] = true;
                    $evidence[] = [
                        'source_type' => 'assessment',
                        'source_id' => implode(',', array_keys($matchedFamilies)),
                        'observed_at' => $this->clock->format('Y-m-d\\TH:i:s.uP'),
                        'safe_value' => ['matched_families' => array_keys($matchedFamilies)],
                    ];
                }
            }
        }

        // Dimension 3: Goal / Roadmap Overlap (Max: 20)
        if (!empty($matchProfile['goal_codes']) && !empty($studentGoals)) {
            $applicableWeight += 20;
            $candidateGoals = array_map('strtolower', $matchProfile['goal_codes']);
            $overlap = array_values(array_intersect($candidateGoals, $studentGoals));
            if (!empty($overlap)) {
                $ratio = count($overlap) / count($candidateGoals);
                $earnedWeight += 20.0 * $ratio;
                $dimensionMatches['goal'] = true;
                $evidence[] = [
                    'source_type' => 'roadmap',
                    'source_id' => implode(',', $overlap),
                    'observed_at' => $this->clock->format('Y-m-d\\TH:i:s.uP'),
                    'safe_value' => ['matched_goals' => $overlap],
                ];
            }
        }

        // Dimension 4: Education Band (Max: 15)
        if (!empty($matchProfile['education_bands']) && $studentEducationBand !== null) {
            $applicableWeight += 15;
            if (in_array($studentEducationBand, $matchProfile['education_bands'], true)) {
                $earnedWeight += 15.0;
                $dimensionMatches['education_band'] = true;
                $evidence[] = [
                    'source_type' => 'student_profile',
                    'source_id' => $studentEducationBand,
                    'observed_at' => $this->clock->format('Y-m-d\\TH:i:s.uP'),
                    'safe_value' => ['education_band' => $studentEducationBand],
                ];
            }
        }

        // Dimension 5: Schedule Compatibility (Max: 10)
        if (!empty($matchProfile['schedule_slots'])) {
            $applicableWeight += 10;
            $hasConflict = $this->hasScheduleConflict($matchProfile['schedule_slots'], $studentSchedule);
            if (!$hasConflict) {
                $earnedWeight += 10.0;
                $dimensionMatches['schedule'] = true;
                $evidence[] = [
                    'source_type' => 'activity_experience',
                    'source_id' => 'schedule_slots',
                    'observed_at' => $this->clock->format('Y-m-d\\TH:i:s.uP'),
                    'safe_value' => ['compatible_slots' => $matchProfile['schedule_slots']],
                ];
            }
        }

        // Requirement: at least 2 matching dimensions, including skill or assessment
        $matchCount = count($dimensionMatches);
        $hasSkillOrAssessment = ($dimensionMatches['skill'] ?? false) || ($dimensionMatches['assessment'] ?? false);
        if ($matchCount < 2 || !$hasSkillOrAssessment || $applicableWeight === 0) {
            return null;
        }

        $score = (int) max(0, min(100, (int) round(100.0 * $earnedWeight / $applicableWeight)));
        $confidenceBand = $score >= 80 ? 'high' : ($score >= 50 ? 'medium' : 'low');

        $rawAction = $candidate['action'] ?? [];
        $actionType = in_array($rawAction['type'] ?? '', self::ALLOWED_ACTIONS, true)
            ? $rawAction['type']
            : 'join_group';

        $cleanAction = [
            'type' => $actionType,
            'catalog_id' => (string) $candidate['catalog_id'],
        ];

        return [
            'catalog_id' => (string) $candidate['catalog_id'],
            'item_type' => (string) $candidate['item_type'],
            'category' => (string) ($candidate['category'] ?? ''),
            'title' => (string) ($candidate['title'] ?? ''),
            'summary' => (string) ($candidate['summary'] ?? ''),
            'score' => $score,
            'confidence_band' => $confidenceBand,
            'analysis_origin' => 'evidence_match',
            'evidence' => $evidence,
            'availability' => $candidate['availability'] ?? ['capacity' => 0, 'enrolled' => 0, 'remaining' => 0],
            'deadline_at' => $candidate['deadline_at'] ?? null,
            'url' => (string) ($candidate['url'] ?? ''),
            'action' => $cleanAction,
        ];
    }

    /** @param array<string,mixed> $action @return array<string,mixed>|null */
    private function extractAndValidateMatchProfile(array $action): ?array
    {
        $profile = $action['match_profile'] ?? null;
        if (!is_array($profile) || $profile === []) {
            return null;
        }

        // Validate unknown keys
        foreach (array_keys($profile) as $key) {
            if (!is_string($key) || !in_array($key, self::ALLOWED_MATCH_PROFILE_KEYS, true)) {
                return null;
            }
        }

        $result = [];

        // Validate skill_codes: max 20, matching \A[a-z0-9][a-z0-9._:-]{0,63}\z
        if (isset($profile['skill_codes'])) {
            if (!is_array($profile['skill_codes']) || count($profile['skill_codes']) > 20) {
                return null;
            }
            $skills = [];
            foreach ($profile['skill_codes'] as $code) {
                if (!is_string($code) || preg_match('/\A[a-z0-9][a-z0-9._:-]{0,63}\z/i', trim($code)) !== 1) {
                    return null;
                }
                $skills[] = strtolower(trim($code));
            }
            $result['skill_codes'] = array_values(array_unique($skills));
        }

        // Validate assessment_directions: max 12 per family
        if (isset($profile['assessment_directions'])) {
            if (!is_array($profile['assessment_directions'])) {
                return null;
            }
            $directions = [];
            foreach ($profile['assessment_directions'] as $family => $values) {
                if (!is_string($family) || !in_array(strtolower($family), self::ALLOWED_ASSESSMENT_FAMILIES, true)) {
                    return null;
                }
                if (!is_array($values) || count($values) > 12) {
                    return null;
                }
                $cleanValues = [];
                foreach ($values as $val) {
                    if (!is_string($val) || trim($val) === '') {
                        return null;
                    }
                    $cleanValues[] = trim($val);
                }
                $directions[strtolower($family)] = array_values(array_unique($cleanValues));
            }
            $result['assessment_directions'] = $directions;
        }

        // Validate goal_codes: max 10, matching \A[a-z0-9][a-z0-9._:-]{0,63}\z
        if (isset($profile['goal_codes'])) {
            if (!is_array($profile['goal_codes']) || count($profile['goal_codes']) > 10) {
                return null;
            }
            $goals = [];
            foreach ($profile['goal_codes'] as $goal) {
                if (!is_string($goal) || preg_match('/\A[a-z0-9][a-z0-9._:-]{0,63}\z/i', trim($goal)) !== 1) {
                    return null;
                }
                $goals[] = strtolower(trim($goal));
            }
            $result['goal_codes'] = array_values(array_unique($goals));
        }

        // Validate education_bands: max 3
        if (isset($profile['education_bands'])) {
            if (!is_array($profile['education_bands']) || count($profile['education_bands']) > 3) {
                return null;
            }
            $bands = [];
            foreach ($profile['education_bands'] as $band) {
                if (!is_string($band) || !in_array(strtolower(trim($band)), ['primary', 'middle', 'high', 'college'], true)) {
                    return null;
                }
                $bands[] = strtolower(trim($band));
            }
            $result['education_bands'] = array_values(array_unique($bands));
        }

        // Validate schedule_slots: max 14, weekday 1..7, start < end, 24-hour HH:MM
        if (isset($profile['schedule_slots'])) {
            if (!is_array($profile['schedule_slots']) || count($profile['schedule_slots']) > 14) {
                return null;
            }
            $slots = [];
            foreach ($profile['schedule_slots'] as $slot) {
                if (!is_array($slot)) {
                    return null;
                }
                $weekday = $slot['weekday'] ?? null;
                $start = $slot['start'] ?? null;
                $end = $slot['end'] ?? null;
                if (!is_int($weekday) || $weekday < 1 || $weekday > 7) {
                    return null;
                }
                if (!is_string($start) || !is_string($end)) {
                    return null;
                }
                if (preg_match('/\A([01][0-9]|2[0-3]):[0-5][0-9]\z/', $start) !== 1 || preg_match('/\A([01][0-9]|2[0-3]):[0-5][0-9]\z/', $end) !== 1) {
                    return null;
                }
                if ($start >= $end) {
                    return null;
                }
                $slots[] = ['weekday' => $weekday, 'start' => $start, 'end' => $end];
            }
            $result['schedule_slots'] = $slots;
        }

        return $result;
    }

    /** @param list<string> $candidateDirections @param list<array<string,mixed>> $studentAssessments */
    private function matchAssessmentFamily(string $family, array $candidateDirections, array $studentAssessments): bool
    {
        $family = strtolower($family);
        $matchedAssessment = null;
        foreach ($studentAssessments as $assessment) {
            if (strtolower((string) ($assessment['test_type'] ?? '')) === $family) {
                $matchedAssessment = $assessment;
                break;
            }
        }
        if ($matchedAssessment === null) {
            return false;
        }

        $scores = is_array($matchedAssessment['dimension_scores'] ?? null) ? $matchedAssessment['dimension_scores'] : [];
        $resultCode = strtoupper(trim((string) ($matchedAssessment['result_code'] ?? '')));
        $candidateValues = array_map(static fn ($v): string => strtoupper(trim((string) $v)), $candidateDirections);

        if ($family === 'holland') {
            if ($resultCode !== '') {
                foreach ($candidateValues as $val) {
                    if (str_contains($resultCode, $val)) return true;
                }
            }
            if (!empty($scores)) {
                arsort($scores);
                $topCodes = array_slice(array_keys($scores), 0, 3);
                foreach ($candidateValues as $val) {
                    if (in_array(strtoupper((string) $val), array_map('strtoupper', $topCodes), true)) return true;
                }
            }
            return false;
        }

        if ($family === 'mbti') {
            if ($resultCode !== '' && in_array($resultCode, $candidateValues, true)) {
                return true;
            }
            if ($resultCode !== '') {
                $matchCount = 0;
                foreach ($candidateValues as $val) {
                    if (strlen($val) === 1 && str_contains($resultCode, $val)) $matchCount++;
                    if (strlen($val) === 4 && $val === $resultCode) return true;
                }
                if ($matchCount >= 2) return true;
            }
            return false;
        }

        if ($family === 'disc') {
            if ($resultCode !== '' && in_array($resultCode, $candidateValues, true)) return true;
            if (!empty($scores)) {
                arsort($scores);
                $topCodes = array_slice(array_keys($scores), 0, 2);
                foreach ($candidateValues as $val) {
                    if (in_array(strtoupper((string) $val), array_map('strtoupper', $topCodes), true)) return true;
                }
            }
            return false;
        }

        if ($family === 'multiple_intelligence') {
            $candidateLower = array_map('strtolower', $candidateDirections);
            if (!empty($scores)) {
                arsort($scores);
                $topCodes = array_slice(array_keys($scores), 0, 4);
                foreach ($candidateLower as $val) {
                    if (in_array(strtolower((string) $val), array_map('strtolower', $topCodes), true)) return true;
                }
            }
            return false;
        }

        return false;
    }

    /** @param list<array<string,mixed>> $candidateSlots @param list<array<string,mixed>> $studentSchedule */
    private function hasScheduleConflict(array $candidateSlots, array $studentSchedule): bool
    {
        foreach ($studentSchedule as $act) {
            $startStr = $act['start_at'] ?? $act['startAt'] ?? null;
            $endStr = $act['end_at'] ?? $act['endAt'] ?? null;
            if (!is_string($startStr) || !is_string($endStr)) {
                continue;
            }
            try {
                $startDt = new DateTimeImmutable($startStr, new DateTimeZone('UTC'));
                $endDt = new DateTimeImmutable($endStr, new DateTimeZone('UTC'));
                $weekday = (int) $startDt->format('N');
                $startTime = $startDt->format('H:i');
                $endTime = $endDt->format('H:i');

                foreach ($candidateSlots as $slot) {
                    $slotWeekday = (int) ($slot['weekday'] ?? 0);
                    $slotStart = (string) ($slot['start'] ?? '');
                    $slotEnd = (string) ($slot['end'] ?? '');
                    if ($slotWeekday === $weekday) {
                        if (max($slotStart, $startTime) < min($slotEnd, $endTime)) {
                            return true;
                        }
                    }
                }
            } catch (Throwable) {
                continue;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $payload @param list<string> $scopes @return list<string> */
    private function extractStudentSkills(array $payload, array $scopes): array
    {
        if (!in_array('skills', $scopes, true)) {
            return [];
        }
        $skills = [];
        $rawSkills = $payload['skills'] ?? [];
        if (is_array($rawSkills)) {
            foreach ($rawSkills as $item) {
                $code = is_string($item['code'] ?? null) ? trim((string) $item['code']) : '';
                if ($code !== '') {
                    $skills[] = strtolower($code);
                }
            }
        }
        return array_values(array_unique($skills));
    }

    /** @param array<string,mixed> $payload @param list<string> $scopes @return list<array<string,mixed>> */
    private function extractStudentAssessments(array $payload, array $scopes): array
    {
        if (!in_array('assessment', $scopes, true)) {
            return [];
        }
        $assessments = [];
        $raw = $payload['assessments'] ?? [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (is_array($item)) {
                    $assessments[] = $item;
                }
            }
        }
        return $assessments;
    }

    /** @param array<string,mixed> $payload @return list<string> */
    private function extractStudentGoals(string $studentId, array $payload): array
    {
        $goals = [];
        // Extract from snapshot profile
        $profile = $payload['profile'] ?? [];
        if (is_array($profile)) {
            foreach (['career_group', 'focus_areas', 'target_field'] as $key) {
                $val = $profile[$key] ?? null;
                if (is_string($val) && trim($val) !== '') {
                    $goals[] = strtolower(trim($val));
                }
            }
        }

        // Extract from latest roadmap in database
        try {
            $statement = $this->pdo->prepare('SELECT primaryDirectionJson, alternativeDirectionsJson FROM learner_ai_roadmaps WHERE studentId = :studentId AND status = :status ORDER BY versionNumber DESC LIMIT 1');
            $statement->execute(['studentId' => $studentId, 'status' => 'ready']);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $primary = json_decode((string) ($row['primaryDirectionJson'] ?? '{}'), true);
                if (is_array($primary) && is_string($primary['code'] ?? null)) {
                    $goals[] = strtolower(trim((string) $primary['code']));
                }
                $alternatives = json_decode((string) ($row['alternativeDirectionsJson'] ?? '[]'), true);
                if (is_array($alternatives)) {
                    foreach ($alternatives as $alt) {
                        if (is_array($alt) && is_string($alt['code'] ?? null)) {
                            $goals[] = strtolower(trim((string) $alt['code']));
                        }
                    }
                }
            }
        } catch (Throwable) {
            // Ignore if roadmap table not present
        }

        return array_values(array_unique(array_filter($goals)));
    }

    private function resolveStudentEducationBand(string $studentId): ?string
    {
        try {
            return $this->educationBandResolver->resolve($studentId, null);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $payload @return list<array<string,mixed>> */
    private function extractStudentSchedule(string $studentId, array $payload): array
    {
        $activities = [];
        $raw = $payload['activities'] ?? [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (is_array($item)) {
                    $activities[] = $item;
                }
            }
        }
        try {
            $regColumns = $this->columnsFor('activity_registrations');
            if (!empty($regColumns)) {
                $actCol = in_array('activityId', $regColumns, true) ? 'activityId' : (in_array('activity_id', $regColumns, true) ? 'activity_id' : null);
                $stuCol = in_array('studentId', $regColumns, true) ? 'studentId' : (in_array('student_id', $regColumns, true) ? 'student_id' : null);

                $actColumns = $this->columnsFor('activities');
                $startCol = in_array('startAt', $actColumns, true) ? 'startAt' : (in_array('start_at', $actColumns, true) ? 'start_at' : null);
                $endCol = in_array('endAt', $actColumns, true) ? 'endAt' : (in_array('end_at', $actColumns, true) ? 'end_at' : null);

                if ($actCol !== null && $stuCol !== null && $startCol !== null && $endCol !== null) {
                    $stmt = $this->pdo->prepare(
                        "SELECT a.id, a.{$startCol} AS startAt, a.{$endCol} AS endAt "
                        . "FROM activity_registrations ar "
                        . "INNER JOIN activities a ON a.id = ar.{$actCol} "
                        . "WHERE ar.{$stuCol} = :studentId "
                        . "AND ar.status IN ('registered', 'confirmed', 'attended')"
                    );
                    $stmt->execute(['studentId' => $studentId]);
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $activities[] = $row;
                    }
                }
            }
        } catch (Throwable) {
        }
        return $activities;
    }

    /** @return list<string> */
    private function columnsFor(string $table): array
    {
        try {
            $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql = $driver === 'sqlite' ? "PRAGMA table_info({$table})" : "SHOW COLUMNS FROM `{$table}`";
            $rows = $this->pdo->query($sql)?->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_values(array_filter(array_map(static fn (array $row): mixed => $row['name'] ?? $row['Field'] ?? null, $rows), 'is_string'));
        } catch (Throwable) {
            return [];
        }
    }
}
