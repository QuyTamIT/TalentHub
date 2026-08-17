<?php

declare(strict_types=1);

use TalentHub\Learner\Seeds\Staging\LearnerAiSyntheticDatasetV2;

function v2_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

function v2_assert_throws(callable $fn, string $expectedMessagePart): void
{
    try {
        $fn();
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(), $expectedMessagePart)) {
            throw new RuntimeException("Expected exception containing '{$expectedMessagePart}', got: '{$e->getMessage()}'");
        }
        return;
    } catch (Throwable $e) {
        throw new RuntimeException("Expected RuntimeException, got " . get_class($e) . ": " . $e->getMessage());
    }
    throw new RuntimeException("Expected RuntimeException containing '{$expectedMessagePart}', but no exception was thrown.");
}

$root = dirname(__DIR__);
$datasetFile = $root . '/Database/seeds/learner/Staging/LearnerAiSyntheticDatasetV2.php';
v2_contract_assert(is_file($datasetFile), 'V2 dataset class exists');
require_once $datasetFile;

LearnerAiSyntheticDatasetV2::validate();
$participants = LearnerAiSyntheticDatasetV2::participants();
$questions = LearnerAiSyntheticDatasetV2::questions();
$rows = LearnerAiSyntheticDatasetV2::rows();

// 1. Basic counts and distributions
v2_contract_assert(count($participants) === 24, 'exactly 24 participants');
v2_contract_assert(count(array_unique(array_column($participants, 'student_id'))) === 24, 'participant IDs are distinct');
v2_contract_assert(array_count_values(array_column($participants, 'primary')) === ['R' => 4, 'I' => 4, 'A' => 4, 'S' => 4, 'E' => 4, 'C' => 4], 'RIASEC is balanced');
v2_contract_assert(array_count_values(array_column($participants, 'expected_state')) === ['ready' => 18, 'insufficient_data' => 4, 'consent_required' => 2], 'state matrix is exact');
v2_contract_assert(count($questions) === 24, 'exactly 24 questions');
v2_contract_assert(array_count_values(array_column($questions, 'dimension')) === ['R' => 4, 'I' => 4, 'A' => 4, 'S' => 4, 'E' => 4, 'C' => 4], 'four questions per dimension');
v2_contract_assert(count($rows) === 1116, 'V2 declares the fixed 1116-row contract');
v2_contract_assert(preg_match('/^[a-f0-9]{64}$/', LearnerAiSyntheticDatasetV2::contentHash()) === 1, 'content fingerprint is SHA-256');

// 2. Exact row-family counts
$tableCounts = [];
foreach ($rows as $row) {
    $tableCounts[$row['table']] = ($tableCounts[$row['table']] ?? 0) + 1;
}

$expectedFamilyCounts = [
    'users' => 22,
    'student_profiles' => 22,
    'skills' => 10,
    'student_skills' => 66,
    'learner_skill_evidence' => 66,
    'test_questions' => 21,
    'learner_assessment_versions' => 1,
    'learner_assessment_question_versions' => 24,
    'test_attempts' => 24,
    'learner_assessment_attempt_metadata' => 24,
    'learner_assessment_answers' => 576,
    'test_results' => 24,
    'activities' => 11,
    'activity_qr_tokens' => 11,
    'activity_registrations' => 24,
    'checkins' => 23,
    'experience_logs' => 23,
    'assessments' => 24,
    'assessment_scores' => 24,
    'learner_ai_consent_events' => 96,
];
ksort($tableCounts);
ksort($expectedFamilyCounts);
v2_contract_assert($tableCounts === $expectedFamilyCounts, 'each row-family count is exact');

// Group rows by table for structured validation
$rowsByTable = [];
$rowKeys = [];
$rowsById = [];
foreach ($rows as $row) {
    $key = $row['table'] . "\0" . $row['id'];
    v2_contract_assert(!isset($rowKeys[$key]), 'table/id pairs are unique: ' . $row['table'] . '.' . $row['id']);
    $rowKeys[$key] = true;
    $rowsByTable[$row['table']][$row['id']] = $row['values'];
    $rowsById[$row['id']] = $row;
    v2_contract_assert(($row['values']['id'] ?? null) === $row['id'], 'row id is declared in values');

    // 3. Email and password safety
    foreach ($row['values'] as $col => $value) {
        if (is_string($value) && str_contains($value, '@')) {
            v2_contract_assert(preg_match('/@(?:[A-Za-z0-9-]+\.)*example$/', $value) === 1, 'email-like values use .example only: ' . $value);
        }
        if ($col === 'passwordHash') {
            v2_contract_assert($value === '!synthetic-disabled-login-v2!', 'password placeholder is fixed non-login hash');
        }
    }
}

// 4. Exact DATETIME(6) round-trip validation
$datetimeColumns = [
    'createdAt', 'updatedAt', 'startAt', 'endAt', 'validFrom', 'validUntil',
    'checkedInAt', 'confirmedAt', 'registeredAt', 'publishedAt', 'startedAt',
    'submittedAt', 'expiresAt', 'answeredAt', 'observedAt', 'verifiedAt', 'occurredAt', 'lastLoginAt'
];

foreach ($rows as $row) {
    foreach ($row['values'] as $col => $value) {
        if ($value === null) {
            continue;
        }
        if ($col === 'dateOfBirth') {
            v2_contract_assert(is_string($value) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value) === 1, 'dateOfBirth is Y-m-d: ' . $value);
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value, new DateTimeZone('UTC'));
            v2_contract_assert($parsed !== false && $parsed->format('Y-m-d') === $value, 'dateOfBirth round-trip exact');
            continue;
        }
        if (in_array($col, $datetimeColumns, true)) {
            v2_contract_assert(is_string($value), 'datetime value is string in ' . $row['table'] . '.' . $col);
            v2_contract_assert(preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}$/', $value) === 1, 'datetime matches DATETIME(6) format in ' . $row['table'] . '.' . $col . ': ' . $value);

            // Extract seconds and check bounds 00-59
            $parts = explode(' ', $value);
            $timeParts = explode(':', $parts[1]);
            $hour = (int) $timeParts[0];
            $minute = (int) $timeParts[1];
            $second = (float) $timeParts[2];
            v2_contract_assert($hour >= 0 && $hour <= 23, 'hour in 00-23 in ' . $value);
            v2_contract_assert($minute >= 0 && $minute <= 59, 'minute in 00-59 in ' . $value);
            v2_contract_assert($second >= 0.0 && $second < 60.0, 'second in 00-59.999999 in ' . $value);

            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $value, new DateTimeZone('UTC'));
            v2_contract_assert($parsed !== false, 'datetime parsed successfully in ' . $row['table'] . '.' . $col . ': ' . $value);
            $errors = DateTimeImmutable::getLastErrors();
            v2_contract_assert($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0), 'datetime has no warnings/errors in ' . $value);
            v2_contract_assert($parsed->format('Y-m-d H:i:s.u') === $value, 'datetime round-trip exact in ' . $row['table'] . '.' . $col . ': ' . $value);
        }
    }
}

// 5. Parent / FK Closure Validation
$v1RoleLearner = '00000000-0000-4000-8000-000000000001';
$v1School = '00000000-0000-4000-8000-000000000010';
$v1Class = '00000000-0000-4000-8000-000000000011';
$v1TeacherProfile = '00000000-0000-4000-8000-000000000021';
$v1CriterionPresentation = '00000000-0000-4000-8000-000000000040';
$v1SkillIot = '00000000-0000-4000-8000-000000000050';
$v1SkillPython = '00000000-0000-4000-8000-000000000051';
$v1TestHolland = '00000000-0000-4000-8000-000000000060';
$v1Questions = [
    '00000000-0000-4000-8000-000000000061' => true,
    '00000000-0000-4000-8000-000000000062' => true,
    '00000000-0000-4000-8000-000000000063' => true,
];
$v1Activity = '00000000-0000-4000-8000-000000000030';
$v1Qr = '00000000-0000-4000-8000-000000000031';

$allStudentIds = array_fill_keys(LearnerAiSyntheticDatasetV2::studentIds(), true);
$allSkillIds = array_merge([$v1SkillIot => true, $v1SkillPython => true], array_fill_keys(array_keys($rowsByTable['skills'] ?? []), true));
$allQuestionIds = array_merge($v1Questions, array_fill_keys(array_keys($rowsByTable['test_questions'] ?? []), true));
$allActivityIds = array_merge([$v1Activity => true], array_fill_keys(array_keys($rowsByTable['activities'] ?? []), true));
$allQrIds = array_merge([$v1Qr => true], array_fill_keys(array_keys($rowsByTable['activity_qr_tokens'] ?? []), true));

foreach ($rowsByTable['users'] ?? [] as $id => $u) {
    v2_contract_assert($u['roleId'] === $v1RoleLearner, 'user roleId matches V1 learner role');
    v2_contract_assert(isset($allStudentIds[$id]), 'user id in student IDs');
}

foreach ($rowsByTable['student_profiles'] ?? [] as $id => $sp) {
    v2_contract_assert($sp['userId'] === $id, 'student profile userId matches id');
    v2_contract_assert($sp['classId'] === $v1Class, 'student profile classId matches V1 class');
}

foreach ($rowsByTable['student_skills'] ?? [] as $id => $ss) {
    v2_contract_assert(isset($allStudentIds[$ss['studentId']]), 'student_skills studentId is valid');
    v2_contract_assert(isset($allSkillIds[$ss['skillId']]), 'student_skills skillId is valid');
}

foreach ($rowsByTable['learner_skill_evidence'] ?? [] as $id => $se) {
    v2_contract_assert(isset($rowsByTable['student_skills'][$se['studentSkillId']]), 'evidence references existing student_skills');
}

foreach ($rowsByTable['test_questions'] ?? [] as $id => $tq) {
    v2_contract_assert($tq['testId'] === $v1TestHolland, 'test_questions testId matches V1 Holland test');
}

foreach ($rowsByTable['learner_assessment_versions'] ?? [] as $id => $lav) {
    v2_contract_assert($lav['testId'] === $v1TestHolland, 'version testId matches V1 Holland test');
}

foreach ($rowsByTable['learner_assessment_question_versions'] ?? [] as $id => $laqv) {
    v2_contract_assert($laqv['versionId'] === LearnerAiSyntheticDatasetV2::VERSION_ID, 'question version matches V2 versionId');
    v2_contract_assert(isset($allQuestionIds[$laqv['questionId']]), 'question version references valid question');
}

foreach ($rowsByTable['test_attempts'] ?? [] as $id => $ta) {
    v2_contract_assert($ta['testId'] === $v1TestHolland, 'attempt testId matches V1 Holland test');
    v2_contract_assert(isset($allStudentIds[$ta['studentId']]), 'attempt studentId is valid');
}

foreach ($rowsByTable['learner_assessment_attempt_metadata'] ?? [] as $id => $laam) {
    v2_contract_assert(isset($rowsByTable['test_attempts'][$laam['attemptId']]), 'metadata attemptId references existing attempt');
    v2_contract_assert($laam['versionId'] === LearnerAiSyntheticDatasetV2::VERSION_ID, 'metadata versionId matches V2');
}

foreach ($rowsByTable['learner_assessment_answers'] ?? [] as $id => $laa) {
    v2_contract_assert(isset($rowsByTable['test_attempts'][$laa['attemptId']]), 'answer attemptId references existing attempt');
    v2_contract_assert(isset($allQuestionIds[$laa['questionId']]), 'answer questionId is valid');
}

foreach ($rowsByTable['test_results'] ?? [] as $id => $tr) {
    v2_contract_assert(isset($rowsByTable['test_attempts'][$tr['attemptId']]), 'result attemptId references existing attempt');
}

foreach ($rowsByTable['activities'] ?? [] as $id => $act) {
    v2_contract_assert($act['schoolId'] === $v1School, 'activity schoolId matches V1 school');
    v2_contract_assert($act['createdByTeacherId'] === $v1TeacherProfile, 'activity teacher matches V1 teacher profile');
}

foreach ($rowsByTable['activity_qr_tokens'] ?? [] as $id => $qr) {
    v2_contract_assert(isset($allActivityIds[$qr['activityId']]), 'qr token references valid activity');
}

foreach ($rowsByTable['activity_registrations'] ?? [] as $id => $ar) {
    v2_contract_assert(isset($allActivityIds[$ar['activityId']]), 'registration references valid activity');
    v2_contract_assert(isset($allStudentIds[$ar['studentId']]), 'registration references valid student');
}

foreach ($rowsByTable['checkins'] ?? [] as $id => $chk) {
    v2_contract_assert(isset($rowsByTable['activity_registrations'][$chk['registrationId']]), 'checkin references valid registration');
    v2_contract_assert(isset($allQrIds[$chk['qrTokenId']]), 'checkin references valid qr token');
}

foreach ($rowsByTable['experience_logs'] ?? [] as $id => $el) {
    v2_contract_assert(isset($allStudentIds[$el['studentId']]), 'experience_log references valid student');
    v2_contract_assert(isset($allActivityIds[$el['activityId']]), 'experience_log references valid activity');
    v2_contract_assert(isset($rowsByTable['checkins'][$el['checkinId']]), 'experience_log references valid checkin');
}

foreach ($rowsByTable['assessments'] ?? [] as $id => $ass) {
    v2_contract_assert($ass['teacherId'] === $v1TeacherProfile, 'assessment teacher matches V1 teacher profile');
    v2_contract_assert(isset($allStudentIds[$ass['studentId']]), 'assessment references valid student');
    v2_contract_assert(isset($allActivityIds[$ass['activityId']]), 'assessment references valid activity');
}

foreach ($rowsByTable['assessment_scores'] ?? [] as $id => $as) {
    v2_contract_assert(isset($rowsByTable['assessments'][$as['assessmentId']]), 'assessment score references valid assessment');
    v2_contract_assert($as['criteriaId'] === $v1CriterionPresentation, 'assessment score criteria matches V1 presentation');
}

foreach ($rowsByTable['learner_ai_consent_events'] ?? [] as $id => $ce) {
    v2_contract_assert(isset($allStudentIds[$ce['studentId']]), 'consent event references valid student');
}

// 6. Assessment Consistency & Chronology (including Learner 112)
$answersByAttempt = [];
foreach ($rowsByTable['learner_assessment_answers'] ?? [] as $id => $ans) {
    $answersByAttempt[$ans['attemptId']][] = $ans;
}

$metadataByAttempt = [];
foreach ($rowsByTable['learner_assessment_attempt_metadata'] ?? [] as $id => $meta) {
    $metadataByAttempt[$meta['attemptId']] = $meta;
}

$resultsByAttempt = [];
foreach ($rowsByTable['test_results'] ?? [] as $id => $res) {
    $resultsByAttempt[$res['attemptId']] = $res;
}

// Map question id to dimension
$questionDimensionMap = [];
foreach ($questions as $q) {
    $questionDimensionMap[$q['id']] = $q['dimension'];
}

foreach ($rowsByTable['test_attempts'] ?? [] as $attemptId => $attempt) {
    $studentId = $attempt['studentId'];
    $studentSeq = (int) substr($studentId, -3);

    $attemptAnswers = $answersByAttempt[$attemptId] ?? [];
    v2_contract_assert(count($attemptAnswers) === 24, 'attempt has exactly 24 answers');

    $startedAt = $attempt['startedAt'];
    $submittedAt = $attempt['submittedAt'];
    v2_contract_assert($startedAt <= $submittedAt, 'attempt startedAt <= submittedAt');

    $attemptQuestionIds = [];
    $dimensionAnswerSums = ['R' => 0, 'I' => 0, 'A' => 0, 'S' => 0, 'E' => 0, 'C' => 0];
    $rawAnswerList = [];

    foreach ($attemptAnswers as $ans) {
        $qId = $ans['questionId'];
        v2_contract_assert(!isset($attemptQuestionIds[$qId]), 'question appears once per attempt');
        $attemptQuestionIds[$qId] = true;

        $answeredAt = $ans['answeredAt'];
        v2_contract_assert($startedAt <= $answeredAt && $answeredAt <= $submittedAt, 'attempt chronology: startedAt <= answeredAt <= submittedAt for student ' . $studentSeq . ' (' . $startedAt . ' <= ' . $answeredAt . ' <= ' . $submittedAt . ')');

        $decoded = json_decode($ans['answerJson'], true);
        v2_contract_assert(is_array($decoded) && isset($decoded['value']), 'answerJson has value');
        $val = (int) $decoded['value'];
        v2_contract_assert($val >= 1 && $val <= 5, 'answer value in 1..5');

        $dim = $questionDimensionMap[$qId] ?? null;
        v2_contract_assert($dim !== null, 'question dimension resolved');
        $dimensionAnswerSums[$dim] += $val;
        $rawAnswerList[] = ['question_id' => $qId, 'value' => $val];
    }

    // Metadata validation
    $meta = $metadataByAttempt[$attemptId] ?? null;
    v2_contract_assert($meta !== null, 'metadata exists for attempt');
    v2_contract_assert($meta['submittedAt'] === $submittedAt, 'metadata submittedAt matches attempt submittedAt');
    $canonicalJson = json_encode($rawAnswerList, JSON_THROW_ON_ERROR);
    $expectedInputHash = hash('sha256', 'pilot-riasec-2:' . $studentId . ':' . $canonicalJson);
    v2_contract_assert($meta['inputHash'] === $expectedInputHash, 'metadata inputHash matches canonical answers hash');

    // Result validation
    $res = $resultsByAttempt[$attemptId] ?? null;
    v2_contract_assert($res !== null, 'result exists for attempt');
    $dimScores = json_decode($res['dimensionScoresJson'], true);
    v2_contract_assert(is_array($dimScores), 'dimensionScoresJson is valid JSON');

    foreach ($dimensionAnswerSums as $dim => $sum) {
        v2_contract_assert(($sum * 5) === ($dimScores[$dim] ?? null), 'sum of 4 answers * 5 equals dimensionScores for ' . $dim);
    }

    $sortedScores = $dimScores;
    arsort($sortedScores, SORT_NUMERIC);
    $top3 = implode('', array_slice(array_keys($sortedScores), 0, 3));
    v2_contract_assert($res['resultCode'] === $top3, 'resultCode matches top 3 dimensions in descending order');

    if ($studentSeq === 112) {
        v2_contract_assert($submittedAt === '2024-01-15 09:00:00.000000', 'learner 112 submittedAt is historical 2024-01-15');
        v2_contract_assert(str_starts_with($startedAt, '2024-01-15'), 'learner 112 startedAt in 2024');
    }
}

// 7. Effective skill consistency
$effectiveSkillsByStudent = [];
// Add V1 skills for 101 and 102
$effectiveSkillsByStudent['00000000-0000-4000-8000-000000000101']['iot'] = true;
$effectiveSkillsByStudent['00000000-0000-4000-8000-000000000101']['python'] = true;
$effectiveSkillsByStudent['00000000-0000-4000-8000-000000000102']['iot'] = true;
$effectiveSkillsByStudent['00000000-0000-4000-8000-000000000102']['python'] = true;

// Map skill id to skill code
$skillCodeMap = [
    $v1SkillIot => 'iot',
    $v1SkillPython => 'python',
];
foreach ($rowsByTable['skills'] ?? [] as $sId => $sk) {
    $skillCodeMap[$sId] = $sk['code'];
}

foreach ($rowsByTable['student_skills'] ?? [] as $ssId => $ss) {
    if ($ss['verificationStatus'] === 'verified') {
        $code = $skillCodeMap[$ss['skillId']] ?? '';
        $effectiveSkillsByStudent[$ss['studentId']][$code] = true;
    }
}

foreach ($participants as $p) {
    $sId = $p['student_id'];
    $sSeq = $p['sequence'];
    $effectiveSkills = $effectiveSkillsByStudent[$sId] ?? [];

    if ($p['expected_state'] === 'ready') {
        v2_contract_assert(count($effectiveSkills) >= 2, 'ready learner ' . $sSeq . ' has >= 2 effective skills');
        v2_contract_assert(isset($effectiveSkills['iot']), 'ready learner ' . $sSeq . ' has verified IoT skill');
    }
    if ($sSeq === 104) {
        v2_contract_assert(count($effectiveSkills) === 1 && isset($effectiveSkills['iot']), 'learner 104 has exactly 1 skill (IoT)');
    }
}

// 8. Edge Scenarios Validation
// 108: has registration but no checkin and no experience_log
$reg108 = null;
foreach ($rowsByTable['activity_registrations'] ?? [] as $arId => $ar) {
    if ($ar['studentId'] === '00000000-0000-4000-8000-000000000108') {
        $reg108 = $arId;
    }
}
v2_contract_assert($reg108 !== null, 'learner 108 has activity registration');
foreach ($rowsByTable['checkins'] ?? [] as $chk) {
    v2_contract_assert($chk['registrationId'] !== $reg108, 'learner 108 has no checkin');
}
foreach ($rowsByTable['experience_logs'] ?? [] as $el) {
    v2_contract_assert($el['studentId'] !== '00000000-0000-4000-8000-000000000108', 'learner 108 has no experience log');
}

// 116: evaluation is draft, publishedAt=null, overallScore=null
$eval116 = null;
foreach ($rowsByTable['assessments'] ?? [] as $ass) {
    if ($ass['studentId'] === '00000000-0000-4000-8000-000000000116') {
        $eval116 = $ass;
    }
}
v2_contract_assert($eval116 !== null && $eval116['status'] === 'draft' && $eval116['publishedAt'] === null && $eval116['overallScore'] === null, 'learner 116 has draft unpublished evaluation');

// 120: evaluation grant then revoke; revoke is later
$consents120 = [];
foreach ($rowsByTable['learner_ai_consent_events'] ?? [] as $ce) {
    if ($ce['studentId'] === '00000000-0000-4000-8000-000000000120' && $ce['scope'] === 'evaluation') {
        $consents120[] = $ce;
    }
}
v2_contract_assert(count($consents120) === 2, 'learner 120 has 2 evaluation consent events');
v2_contract_assert($consents120[0]['action'] === 'granted' && $consents120[1]['action'] === 'revoked', 'learner 120 has grant then revoke');
v2_contract_assert($consents120[1]['occurredAt'] > $consents120[0]['occurredAt'], 'learner 120 revoke occurred after grant');

// 124: no activity consent grant
foreach ($rowsByTable['learner_ai_consent_events'] ?? [] as $ce) {
    if ($ce['studentId'] === '00000000-0000-4000-8000-000000000124') {
        v2_contract_assert($ce['scope'] !== 'activity', 'learner 124 lacks activity consent');
    }
}

// 9. QR & Security Safety
$qrHashes = [];
foreach ($rowsByTable['activity_qr_tokens'] ?? [] as $qr) {
    $hash = $qr['tokenHash'];
    v2_contract_assert(preg_match('/^[a-f0-9]{64}$/', $hash) === 1, 'qr tokenHash is lowercase hex SHA-256: ' . $hash);
    v2_contract_assert(!isset($qrHashes[$hash]), 'qr tokenHash is unique: ' . $hash);
    $qrHashes[$hash] = true;
}

// 10. Data Diversity Validation
$hoursValues = array_unique(array_column($rowsByTable['experience_logs'] ?? [], 'hours'));
v2_contract_assert(count($hoursValues) >= 5, 'at least 5 distinct experience hours values, got ' . count($hoursValues));
foreach ($hoursValues as $h) {
    v2_contract_assert(in_array($h, ['2.50', '3.00', '3.50', '4.00', '4.50', '5.00', '5.50', '6.00', '6.50'], true), 'hours value in allowed set: ' . $h);
}

$overallScores = array_filter(array_column($rowsByTable['assessments'] ?? [], 'overallScore'), static fn ($v) => $v !== null);
v2_contract_assert(count(array_unique($overallScores)) >= 5, 'at least 5 distinct published overall scores, got ' . count(array_unique($overallScores)));
foreach ($overallScores as $os) {
    $val = (float) $os;
    v2_contract_assert($val >= 72.0 && $val <= 94.0, 'overall score in 72..94: ' . $os);
}

$presentationScores = array_column($rowsByTable['assessment_scores'] ?? [], 'score');
$lowPres = array_filter($presentationScores, static fn ($s) => (float) $s < 60.0);
$highPres = array_filter($presentationScores, static fn ($s) => (float) $s >= 60.0);
v2_contract_assert(count($lowPres) > 0, 'contains presentation scores < 60');
v2_contract_assert(count($highPres) > 0, 'contains presentation scores >= 60');
v2_contract_assert(count(array_unique($presentationScores)) >= 4, 'at least 4 distinct presentation scores');

// Learner 101 presentation score is 55.00
$ass101Id = null;
foreach ($rowsByTable['assessments'] ?? [] as $aId => $ass) {
    if ($ass['studentId'] === '00000000-0000-4000-8000-000000000101') {
        $ass101Id = $aId;
    }
}
$score101 = null;
foreach ($rowsByTable['assessment_scores'] ?? [] as $as) {
    if ($as['assessmentId'] === $ass101Id) {
        $score101 = $as['score'];
    }
}
v2_contract_assert($score101 === '55.00', 'learner 101 V2 presentation score is 55.00');

// 11. Negative Tests for Runtime Validator
// Negative test 1: row count !== 1116
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, array_slice($rows, 0, 1115)),
    'exactly 1116 rows required'
);

// Negative test 2: wrong row-family count (replace 1 checkin with 1 extra skill)
$mutatedFamilyRows = $rows;
foreach ($mutatedFamilyRows as $idx => $r) {
    if ($r['table'] === 'checkins') {
        $mutatedFamilyRows[$idx] = [
            'table' => 'skills',
            'id' => '00000000-0000-4000-8000-000000001099',
            'values' => [
                'id' => '00000000-0000-4000-8000-000000001099',
                'code' => 'extra_skill',
                'name' => 'Extra Skill',
                'category' => 'technology',
                'status' => 'active',
                'createdAt' => '2026-08-16 00:00:00.000000',
                'updatedAt' => '2026-08-16 00:00:00.000000',
            ],
        ];
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedFamilyRows),
    'row-family counts mismatch'
);

// Negative test 3: duplicate table/id
$mutatedDupRows = $rows;
$mutatedDupRows[1] = $mutatedDupRows[0];
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedDupRows),
    'duplicate row key'
);

// Negative test 4: invalid UUID prefix
$mutatedUuidRows = $rows;
$mutatedUuidRows[0]['id'] = '11111111-2222-3333-4444-555555555555';
$mutatedUuidRows[0]['values']['id'] = '11111111-2222-3333-4444-555555555555';
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedUuidRows),
    'reserved prefix'
);

// Negative test 5: values.id mismatch
$mutatedValuesIdRows = $rows;
$mutatedValuesIdRows[0]['values']['id'] = '00000000-0000-4000-8000-000000000999';
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedValuesIdRows),
    'row values id mismatch'
);

// Negative test 6: real email domain
$mutatedEmailRows = $rows;
$mutatedEmailRows[0]['values']['email'] = 'test@gmail.com';
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedEmailRows),
    'invalid email domain'
);

// Negative test 7: password placeholder mismatch
$mutatedPassRows = $rows;
$mutatedPassRows[0]['values']['passwordHash'] = 'secretpassword';
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedPassRows),
    'password placeholder'
);

// Negative test 8: invalid DATETIME(6) seconds overflow
$mutatedTimeRows = $rows;
$mutatedTimeRows[0]['values']['createdAt'] = '2026-08-16 00:00:65.000000';
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedTimeRows),
    'DATETIME(6)'
);

// Negative test 9: broken FK closure (unknown skillId)
$mutatedFkRows = $rows;
foreach ($mutatedFkRows as $idx => $r) {
    if ($r['table'] === 'student_skills') {
        $mutatedFkRows[$idx]['values']['skillId'] = '00000000-0000-4000-8000-000000000999';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedFkRows),
    'skillId'
);

// Negative test 10: ready learner lacking 2 effective skills (set 103 skills to rejected)
$mutatedSkillCountRows = $rows;
foreach ($mutatedSkillCountRows as $idx => $r) {
    if ($r['table'] === 'student_skills' && $r['values']['studentId'] === '00000000-0000-4000-8000-000000000103') {
        $mutatedSkillCountRows[$idx]['values']['verificationStatus'] = 'rejected';
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedSkillCountRows),
    'effective skills'
);

// Negative test 11: ready learner lacking verified IoT (set 105 IoT to pending)
$mutatedIotRows = $rows;
foreach ($mutatedIotRows as $idx => $r) {
    if ($r['table'] === 'student_skills' && $r['values']['studentId'] === '00000000-0000-4000-8000-000000000105' && $r['values']['skillId'] === $v1SkillIot) {
        $mutatedIotRows[$idx]['values']['verificationStatus'] = 'pending';
        $mutatedIotRows[$idx]['values']['verifiedAt'] = null;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedIotRows),
    'IoT'
);

// Negative test 12: learner 104 having 2 skills
$mutated104Rows = $rows;
foreach ($mutated104Rows as $idx => $r) {
    if ($r['table'] === 'student_skills' && $r['values']['studentId'] === '00000000-0000-4000-8000-000000000103' && $r['values']['skillId'] !== $v1SkillIot) {
        $mutated104Rows[$idx]['values']['studentId'] = '00000000-0000-4000-8000-000000000104';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutated104Rows),
    'learner 104'
);

// Negative test 13: learner 108 has check-in
$mutated108Rows = $rows;
foreach ($mutated108Rows as $idx => $r) {
    if ($r['table'] === 'checkins' && $r['id'] === '00000000-0000-4000-8000-000000701101') {
        $mutated108Rows[$idx]['values']['registrationId'] = '00000000-0000-4000-8000-000000700108';
        $mutated108Rows[$idx]['values']['qrTokenId'] = '00000000-0000-4000-8000-000000001042';
    }
    if ($r['table'] === 'experience_logs' && $r['id'] === '00000000-0000-4000-8000-000000702101') {
        $mutated108Rows[$idx]['values']['studentId'] = '00000000-0000-4000-8000-000000000108';
        $mutated108Rows[$idx]['values']['activityId'] = '00000000-0000-4000-8000-000000001022';
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutated108Rows),
    'learner 108'
);

// Negative test 14: learner 112 assessment not stale (submitted in 2026)
$mutated112StaleRows = $rows;
foreach ($mutated112StaleRows as $idx => $r) {
    if ($r['table'] === 'test_attempts' && $r['values']['studentId'] === '00000000-0000-4000-8000-000000000112') {
        $mutated112StaleRows[$idx]['values']['submittedAt'] = '2026-08-10 09:30:00.000000';
    }
    if ($r['table'] === 'learner_assessment_attempt_metadata' && $r['id'] === '00000000-0000-4000-8000-000000401112') {
        $mutated112StaleRows[$idx]['values']['submittedAt'] = '2026-08-10 09:30:00.000000';
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutated112StaleRows),
    'learner 112'
);

// Negative test 15: chronology violation (answeredAt after submittedAt)
$mutatedChronologyRows = $rows;
foreach ($mutatedChronologyRows as $idx => $r) {
    if ($r['table'] === 'learner_assessment_answers') {
        $mutatedChronologyRows[$idx]['values']['answeredAt'] = '2026-08-10 10:00:00.000000';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedChronologyRows),
    'chronology'
);

// Negative test 16: learner 116 published
$mutated116Rows = $rows;
foreach ($mutated116Rows as $idx => $r) {
    if ($r['table'] === 'assessments' && $r['values']['studentId'] === '00000000-0000-4000-8000-000000000116') {
        $mutated116Rows[$idx]['values']['status'] = 'published';
        $mutated116Rows[$idx]['values']['publishedAt'] = '2026-08-07 09:00:00.000000';
        $mutated116Rows[$idx]['values']['overallScore'] = '80.00';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutated116Rows),
    'learner 116'
);

// Negative test 17: learner 120 revoke missing
$mutated120Rows = $rows;
foreach ($mutated120Rows as $idx => $r) {
    if ($r['table'] === 'learner_ai_consent_events' && $r['values']['studentId'] === '00000000-0000-4000-8000-000000000120' && $r['values']['action'] === 'revoked') {
        $mutated120Rows[$idx]['values']['action'] = 'granted';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutated120Rows),
    'learner 120'
);

// Negative test 18: learner 124 has activity grant
$mutated124Rows = $rows;
foreach ($mutated124Rows as $idx => $r) {
    if ($r['table'] === 'learner_ai_consent_events' && $r['values']['studentId'] === '00000000-0000-4000-8000-000000000124') {
        $mutated124Rows[$idx]['values']['scope'] = 'activity';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutated124Rows),
    'learner 124'
);

// Negative test 19: tokenHash not lowercase hex 64
$mutatedQrRows = $rows;
foreach ($mutatedQrRows as $idx => $r) {
    if ($r['table'] === 'activity_qr_tokens') {
        $mutatedQrRows[$idx]['values']['tokenHash'] = 'INVALID_TOKEN_HASH_UPPERCASE';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedQrRows),
    'tokenHash'
);

// Negative test 20: registration / check-in / QR activity mismatch
$mutatedActivityMismatchRows = $rows;
foreach ($mutatedActivityMismatchRows as $idx => $r) {
    if ($r['table'] === 'checkins') {
        $mutatedActivityMismatchRows[$idx]['values']['qrTokenId'] = '00000000-0000-4000-8000-000000001051';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedActivityMismatchRows),
    'activity mismatch'
);

// Negative test 21: assessment answer sum mismatch
$mutatedAnswerSumRows = $rows;
foreach ($mutatedAnswerSumRows as $idx => $r) {
    if ($r['table'] === 'test_results' && $r['id'] === '00000000-0000-4000-8000-000000600101') {
        $mutatedAnswerSumRows[$idx]['values']['dimensionScoresJson'] = '{"R":50,"I":80,"A":60,"S":55,"E":70,"C":65}';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedAnswerSumRows),
    'dimensionScores'
);

// Negative test 22: inputHash mismatch
$mutatedInputHashRows = $rows;
foreach ($mutatedInputHashRows as $idx => $r) {
    if ($r['table'] === 'learner_assessment_attempt_metadata') {
        $mutatedInputHashRows[$idx]['values']['inputHash'] = '0000000000000000000000000000000000000000000000000000000000000000';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedInputHashRows),
    'inputHash'
);

// Negative test 23: experience student/activity mismatch
$mutatedExpMismatchRows = $rows;
foreach ($mutatedExpMismatchRows as $idx => $r) {
    if ($r['table'] === 'experience_logs') {
        $mutatedExpMismatchRows[$idx]['values']['activityId'] = '00000000-0000-4000-8000-000000001031';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedExpMismatchRows),
    'experience mismatch'
);

// Negative test 24: assessment registration mismatch
$mutatedAssMismatchRows = $rows;
foreach ($mutatedAssMismatchRows as $idx => $r) {
    if ($r['table'] === 'assessments' && $r['values']['studentId'] === '00000000-0000-4000-8000-000000000101') {
        $mutatedAssMismatchRows[$idx]['values']['activityId'] = '00000000-0000-4000-8000-000000001031';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedAssMismatchRows),
    'assessment registration mismatch'
);

// Negative test 25: ready learner missing consent
$mutatedMissingConsentRows = $rows;
foreach ($mutatedMissingConsentRows as $idx => $r) {
    if ($r['table'] === 'learner_ai_consent_events' && $r['values']['studentId'] === '00000000-0000-4000-8000-000000000101' && $r['values']['scope'] === 'evaluation') {
        $mutatedMissingConsentRows[$idx]['values']['action'] = 'revoked';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedMissingConsentRows),
    'missing consent grant'
);

// Negative test 26: duplicate QR hashes are rejected
$mutatedDuplicateQrRows = $rows;
$firstQrHash = null;
foreach ($mutatedDuplicateQrRows as $idx => $r) {
    if ($r['table'] !== 'activity_qr_tokens') {
        continue;
    }
    if ($firstQrHash === null) {
        $firstQrHash = $r['values']['tokenHash'];
        continue;
    }
    $mutatedDuplicateQrRows[$idx]['values']['tokenHash'] = $firstQrHash;
    break;
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedDuplicateQrRows),
    'duplicate tokenHash'
);

// Negative test 27: QR rows reject undeclared raw-token fields
$mutatedRawQrRows = $rows;
foreach ($mutatedRawQrRows as $idx => $r) {
    if ($r['table'] === 'activity_qr_tokens') {
        $mutatedRawQrRows[$idx]['values']['rawToken'] = 'synthetic-ai-v2-raw-token';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedRawQrRows),
    'unexpected QR columns'
);

// Negative test 28: a later revoke overrides an earlier grant for a ready learner
$mutatedLatestRevokeRows = $rows;
foreach ($mutatedLatestRevokeRows as $idx => $r) {
    if (
        $r['table'] === 'learner_ai_consent_events'
        && $r['values']['studentId'] === '00000000-0000-4000-8000-000000000104'
        && $r['values']['scope'] === 'evaluation'
    ) {
        $mutatedLatestRevokeRows[$idx]['values']['studentId'] = '00000000-0000-4000-8000-000000000101';
        $mutatedLatestRevokeRows[$idx]['values']['action'] = 'revoked';
        $mutatedLatestRevokeRows[$idx]['values']['occurredAt'] = '2026-08-08 11:00:00.000000';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedLatestRevokeRows),
    'latest consent'
);

// Negative test 29: a verified V2 IoT skill requires verified evidence
$mutatedRejectedEvidenceRows = $rows;
$learner103IotStudentSkillId = null;
foreach ($mutatedRejectedEvidenceRows as $r) {
    if (
        $r['table'] === 'student_skills'
        && $r['values']['studentId'] === '00000000-0000-4000-8000-000000000103'
        && $r['values']['skillId'] === $v1SkillIot
    ) {
        $learner103IotStudentSkillId = $r['id'];
        break;
    }
}
v2_contract_assert($learner103IotStudentSkillId !== null, 'learner 103 IoT student_skill exists for evidence mutation');
foreach ($mutatedRejectedEvidenceRows as $idx => $r) {
    if (
        $r['table'] === 'learner_skill_evidence'
        && $r['values']['studentSkillId'] === $learner103IotStudentSkillId
    ) {
        $mutatedRejectedEvidenceRows[$idx]['values']['verificationStatus'] = 'rejected';
        break;
    }
}
v2_assert_throws(
    static fn () => LearnerAiSyntheticDatasetV2::validateDataset($participants, $questions, $mutatedRejectedEvidenceRows),
    'verified evidence'
);

// 12. DCR File Assertions
$source = file_get_contents($datasetFile);
v2_contract_assert(is_string($source), 'dataset source is readable');
foreach (['UPDATE ', 'DELETE ', 'REPLACE ', 'DROP ', 'TRUNCATE ', 'ALTER '] as $forbidden) {
    v2_contract_assert(stripos($source, $forbidden) === false, 'dataset contains no destructive or mutable SQL token: ' . trim($forbidden));
}

$dcrPath = $root . '/docs/superpowers/database-change-requests/2026-08-17-learner-ai-synthetic-dataset-v2.md';
v2_contract_assert(is_file($dcrPath), 'V2 DCR exists');
$dcr = file_get_contents($dcrPath);
v2_contract_assert(is_string($dcr), 'V2 DCR is readable');
v2_contract_assert(str_contains($dcr, '`talenthub_ai_backup_verify_004_20260816`'), 'DCR pins the approved disposable schema');
v2_contract_assert(str_contains($dcr, '`' . LearnerAiSyntheticDatasetV2::contentHash() . '`'), 'DCR records the exact dataset fingerprint');
v2_contract_assert(str_contains($dcr, '1116'), 'DCR records the exact V2 row count');
v2_contract_assert(
    str_contains($dcr, 'PROPOSED — DISPOSABLE SCHEMA ONLY') || str_contains($dcr, 'APPROVED — DISPOSABLE SCHEMA ONLY'),
    'DCR status is valid'
);
v2_contract_assert(
    str_contains($dcr, 'NOT EXECUTED') || str_contains($dcr, 'EXECUTED'),
    'DCR execution status is valid'
);

// DCR contains verbatim question text of all 24 questions
foreach ($questions as $q) {
    v2_contract_assert(str_contains($dcr, $q['content']), 'DCR contains full question text for ' . $q['code']);
}

echo 'learner_ai_synthetic_dataset_v2_contract_test: OK' . PHP_EOL;
