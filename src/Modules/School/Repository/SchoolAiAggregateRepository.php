<?php
declare(strict_types=1);
namespace TalentHub\Modules\School\Repository;

use PDO;

final class SchoolAiAggregateRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array<string,mixed> */
    public function aggregate(string $schoolId, int $minimumCohort = 5, ?string $retentionCutoff = null): array
    {
        if ($minimumCohort < 3) throw new \InvalidArgumentException('School AI minimum cohort must be at least three.');
        $cutoff = $retentionCutoff ?? gmdate('Y-m-d H:i:s', time() - 31536000);
        $eventConsent = $this->tableExists('learner_ai_consent_events');
        $consentCondition = $eventConsent
            ? "AND (SELECT COUNT(DISTINCT latest.scope) FROM learner_ai_consent_events latest WHERE latest.studentId=sp.id AND latest.action='granted' AND latest.scope IN ('assessment','skills','activity','evaluation') AND NOT EXISTS (SELECT 1 FROM learner_ai_consent_events newer WHERE newer.studentId=latest.studentId AND newer.scope=latest.scope AND (newer.occurredAt>latest.occurredAt OR (newer.occurredAt=latest.occurredAt AND newer.requestId>latest.requestId))))=4"
            : "AND NOT EXISTS (SELECT 1 FROM privacy_consents pc WHERE pc.studentId=sp.id AND pc.scope IN ('assessment','skills','activity','evaluation') AND (pc.isGranted=0 OR pc.revokedAt IS NOT NULL)) AND (SELECT COUNT(DISTINCT granted.scope) FROM privacy_consents granted WHERE granted.studentId=sp.id AND granted.scope IN ('assessment','skills','activity','evaluation') AND granted.isGranted=1 AND granted.revokedAt IS NULL)=4";
        $statement = $this->pdo->prepare(
            "SELECT c.id AS class_id,c.name AS class_name,c.gradeLevel AS grade_level,p.student_id,p.talent_map_json,p.trend_signals_json,p.evidence_json,p.generated_at,p.status "
            . "FROM learner_ai_capability_profiles p JOIN student_profiles sp ON sp.id=p.student_id JOIN classes c ON c.id=sp.classId "
            . "WHERE c.schoolId=:school AND sp.studyStatus='active' AND p.superseded_at IS NULL AND p.generated_at>=:cutoff AND p.status IN ('ready_model','stale_model') "
            . $consentCondition . ' '
            . 'ORDER BY c.gradeLevel,c.id,p.student_id'
        );
        $statement->execute(['school' => $schoolId, 'cutoff' => $cutoff]);
        $groups = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!$this->evidenceFresh((string) ($row['evidence_json'] ?? ''), $cutoff)) continue;
            $classKey = 'class:' . $row['class_id'];
            $gradeKey = 'grade:' . $row['grade_level'];
            foreach ([[$classKey, 'class', (string) $row['class_name']], [$gradeKey, 'grade', (string) $row['grade_level']], ['school:' . $schoolId, 'school', 'Toàn trường']] as [$key, $level, $label]) {
                $groups[$key] ??= ['cohort_key' => $key, 'level' => $level, 'label' => $label, 'student_count' => 0, 'talent_totals' => [], 'talent_counts' => [], 'trend_counts' => [], 'stale_count' => 0];
                $groups[$key]['student_count']++;
                if ((string) $row['status'] === 'stale_model') $groups[$key]['stale_count']++;
                $seenTalents = [];
                foreach ($this->json((string) $row['talent_map_json']) as $talent) {
                    $field = $this->safeLabel((string) ($talent['field'] ?? $talent['label'] ?? ''));
                    $score = is_numeric($talent['score'] ?? null) ? (float) $talent['score'] : null;
                    if ($field !== '' && $score !== null) {
                        $groups[$key]['talent_totals'][$field] = ($groups[$key]['talent_totals'][$field] ?? 0.0) + $score;
                        if (!isset($seenTalents[$field])) {
                            $groups[$key]['talent_counts'][$field] = ($groups[$key]['talent_counts'][$field] ?? 0) + 1;
                            $seenTalents[$field] = true;
                        }
                    }
                }
                $seenTrends = [];
                foreach ($this->json((string) $row['trend_signals_json']) as $trend) {
                    $labelKey = $this->safeLabel((string) ($trend['label'] ?? ''));
                    if ($labelKey !== '' && !isset($seenTrends[$labelKey])) {
                        $groups[$key]['trend_counts'][$labelKey] = ($groups[$key]['trend_counts'][$labelKey] ?? 0) + 1;
                        $seenTrends[$labelKey] = true;
                    }
                }
            }
        }
        $visible = [];
        $suppressed = 0;
        foreach ($groups as $group) {
            if ($group['student_count'] < $minimumCohort) { $suppressed++; continue; }
            $talents = [];
            foreach ($group['talent_totals'] as $field => $total) {
                if (($group['talent_counts'][$field] ?? 0) < $minimumCohort) continue;
                $talents[] = ['field' => $field, 'average_score' => round($total / $group['talent_counts'][$field], 1)];
            }
            usort($talents, static fn (array $a, array $b): int => [$b['average_score'], $a['field']] <=> [$a['average_score'], $b['field']]);
            $trends = [];
            foreach ($group['trend_counts'] as $label => $count) {
                if ($count < $minimumCohort) continue;
                $trends[] = ['label' => $label, 'count' => $count, 'confidence' => round($count / $group['student_count'], 2)];
            }
            usort($trends, static fn (array $a, array $b): int => [$b['count'], $a['label']] <=> [$a['count'], $b['label']]);
            unset($group['talent_totals'], $group['talent_counts'], $group['trend_counts']);
            $group['talent_distribution'] = $talents;
            $group['trend_signals'] = $trends;
            $visible[] = $group;
        }
        return ['school_id' => $schoolId, 'minimum_cohort' => $minimumCohort, 'cohorts' => $visible, 'suppressed_cohort_count' => $suppressed, 'generated_at' => gmdate('c')];
    }

    /** @return list<array<string,mixed>> */
    private function json(string $value): array { try { $decoded=json_decode($value,true,64,JSON_THROW_ON_ERROR);return is_array($decoded)?array_values(array_filter($decoded,'is_array')):[]; } catch(\Throwable){return [];} }
    private function safeLabel(string $value): string { $value=trim(preg_replace('/\s+/u',' ',$value)??'');if($value===''||mb_strlen($value)>120||preg_match('/@|\b(?:student|học sinh|email|phone|điện thoại|sdt|cccd|giới tính|tôn giáo|dân tộc|khuyết tật)\b/i',$value))return '';return $value; }
    private function evidenceFresh(string $value,string $cutoff): bool { if(trim($value)==='')return true;try{$items=$this->json($value);if($items===[])return true;$hasTimestamp=false;foreach($items as $item){foreach(['observed_at','updated_at','generated_at','occurred_at'] as $key){$timestamp=$item[$key]??null;if(is_string($timestamp)&&$timestamp!==''){$hasTimestamp=true;if($timestamp>=$cutoff)return true;}}}return !$hasTimestamp; }catch(\Throwable){return false;} }
    private function tableExists(string $table):bool{try{$this->pdo->query('SELECT 1 FROM '.$table.' LIMIT 1');return true;}catch(\Throwable){return false;}}
}
