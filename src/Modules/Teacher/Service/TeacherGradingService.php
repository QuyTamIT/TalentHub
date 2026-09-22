<?php

declare(strict_types=1);

namespace TalentHub\Modules\Teacher\Service;

require_once __DIR__ . '/RubricScoreCalculator.php';

use TalentHub\Http\ApiException;
use TalentHub\Modules\Teacher\Exception\TeacherGradingConflictException;
use TalentHub\Modules\Teacher\Repository\TeacherGradingRepository;
use TalentHub\Support\Id\RequestId;
use TalentHub\Support\Uuid;

final class TeacherGradingService
{
    private const ASSESSMENT_STATUSES = ['draft', 'published'];

    public function __construct(private readonly TeacherGradingRepository $repository) {}

    /** @return array<string,mixed> */
    public function pageData(string $userId, ?string $contextId, string $search = '', string $mode = 'activity'): array
    {
        $mode = $this->mode($mode);
        $teacher = $this->teacher($userId);
        $teacherId = (string) $teacher['id'];
        $contexts = match ($mode) {
            'class' => $this->repository->classes($teacherId),
            'project' => $this->repository->projects($teacherId),
            'activity' => $this->repository->activities($teacherId),
        };
        $selected = null;
        $students = [];
        if ($contextId !== null && $contextId !== '') {
            $this->assertUuid($contextId, 'contextId');
            $selected = $this->repository->contextForTeacher($teacherId, $mode, $contextId);
            if ($selected === null) throw new ApiException(403, 'FORBIDDEN', 'Bạn không được phân công chấm trong ngữ cảnh này.');
            $students = $this->repository->studentsForContext($teacherId,$mode,$contextId,$this->search($search));
            $skillMap = $this->repository->studentSkillsByStudentIds(array_column($students, 'studentId'));
            foreach ($students as &$student) {
                $student['studentSkills'] = $skillMap[(string) $student['studentId']] ?? [];
            }
            unset($student);
        }
        return [
            'teacher'=>$teacher, 'mode'=>$mode, 'contexts'=>$contexts, 'selectedContext'=>$selected,
            'classes'=>$mode==='class' ? $contexts : [], 'projects'=>$mode==='project' ? $contexts : [],
            'activities'=>$mode==='activity' ? $contexts : [], 'selectedActivity'=>$mode==='activity' ? $selected : null,
            'students'=>$students, 'criteria'=>$selected !== null ? $this->repository->activeCriteria() : [],
            'availableSkills'=>$mode === 'activity' && $selected !== null && is_string($contextId) && $contextId !== ''
                ? $this->repository->skillsForActivity($contextId)
                : $this->repository->activeSkills(),
            'skillCategories'=>TeacherGradingRepository::SKILL_CATEGORIES,
        ];
    }

    private function mode(mixed $mode): string
    {
        if (!is_string($mode) || !in_array($mode, ['class','project','activity'], true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Ngữ cảnh chấm điểm không hợp lệ.');
        }
        return $mode;
    }

    /** @param array<string,mixed> $input */
    public function save(string $userId, array $input, ?string $requestId = null): string
    {
        $teacher = $this->teacher($userId);
        $teacherId = (string) $teacher['id'];
        $mode = $this->mode($input['mode'] ?? 'activity');
        $column = \TalentHub\Modules\Teacher\Repository\TeacherAssessmentScope::column($mode);
        $activityId = $this->id($input['contextId'] ?? $input[$column] ?? null, 'contextId');
        foreach (['activityId','classId','projectId'] as $field) {
            if (isset($input[$field]) && $input[$field] !== '' && ($field !== $column || $input[$field] !== $activityId)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Chỉ được chọn một ngữ cảnh đánh giá.');
            }
        }
        $studentId = $this->id($input['studentId'] ?? null, 'studentId');
        $assessmentId = $this->assessmentId($input['assessmentId'] ?? null);
        $expectedVersion = $this->version($input['expectedVersion'] ?? null);
        if (!$this->repository->studentInContext($teacherId,$mode,$activityId,$studentId)) {
            throw new ApiException(403, 'FORBIDDEN', 'Sinh viên hoặc ngữ cảnh nằm ngoài phạm vi được phân công.');
        }
        if ($expectedVersion === 0 && $assessmentId !== null) {
            throw new TeacherGradingConflictException('A new assessment cannot carry an existing assessment id.');
        }
        if ($expectedVersion > 0 && $assessmentId === null) {
            throw new TeacherGradingConflictException('An existing assessment requires its id.');
        }

        $status = $this->status($input['assessmentStatus'] ?? null);
        $overallScore = $this->score($input['overallScore'] ?? null, 0.0, 100.0, 'overallScore', true);
        $comment = $this->comment($input['comment'] ?? null);

        $criteria = $this->repository->activeCriteria();
        $criteriaById = [];
        foreach ($criteria as $criterion) {
            $criteriaById[(string) $criterion['id']] = $criterion;
        }

        $criteriaInput = $input['criteria'] ?? [];
        if (!is_array($criteriaInput)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Điểm tiêu chí không hợp lệ.');
        }

        $criteriaScores = [];
        foreach ($criteriaInput as $criteriaId => $value) {
            if (!is_string($criteriaId) || !isset($criteriaById[$criteriaId])) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Tiêu chí không hợp lệ hoặc không còn active.');
            }
            $criterion = $criteriaById[$criteriaId];
            $score = $this->score(
                $value,
                (float) $criterion['minScore'],
                (float) $criterion['maxScore'],
                'criteria[' . $criteriaId . ']',
                true
            );
            if ($score !== null) {
                $criteriaScores[] = ['criteriaId' => $criteriaId, 'score' => $score];
            }
        }

        $scoreMethod = 'teacher_direct';
        $formulaVersion = null;
        $calculationJson = null;

        if ($status === 'published' && $criteriaById !== [] && count($criteriaScores) !== count($criteriaById)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Assessment đã công bố phải có điểm cho toàn bộ tiêu chí đang active.');
        }

        $hasNonZeroMin = false;
        foreach ($criteriaScores as $cs) {
            $cDef = $criteriaById[$cs['criteriaId']];
            if ((float) ($cDef['minScore'] ?? 0.0) !== 0.0) {
                $hasNonZeroMin = true;
                break;
            }
        }

        if ($criteriaScores !== [] && !$hasNonZeroMin) {
            if ($status === 'published' && ($criteriaById === [] || count($criteriaScores) !== count($criteriaById))) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Assessment đã công bố phải có điểm cho toàn bộ tiêu chí đang active.');
            }

            try {
                $calculator = new RubricScoreCalculator();
                $rubricCriteria = [];
                foreach ($criteriaScores as $cs) {
                    $cDef = $criteriaById[$cs['criteriaId']];
                    $rubricCriteria[] = [
                        'id' => $cs['criteriaId'],
                        'code' => (string) ($cDef['code'] ?? $cs['criteriaId']),
                        'name' => (string) ($cDef['name'] ?? $cs['criteriaId']),
                        'score' => (float) $cs['score'],
                        'min' => (float) ($cDef['minScore'] ?? 0.0),
                        'max' => (float) ($cDef['maxScore'] ?? 100.0),
                        'weight' => (float) ($cDef['weight'] ?? 1.0),
                    ];
                }

                $calcResult = $calculator->calculate($rubricCriteria);
                // Strict rule: Rubric calculation takes absolute precedence over client overallScore
                $overallScore = (string) $calcResult['score'];
                $scoreMethod = RubricScoreCalculator::SCORE_METHOD;
                $formulaVersion = RubricScoreCalculator::FORMULA_VERSION;
                $calculationJson = json_encode($calcResult['calculation'], JSON_UNESCAPED_UNICODE);
            } catch (\InvalidArgumentException $e) {
                throw new ApiException(422, 'VALIDATION_FAILED', $e->getMessage(), 0, $e);
            }
        } else {
            if ($status === 'published' && $overallScore === null) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Assessment đã công bố phải có overallScore.');
            }
        }

        $groupScores = array_key_exists('skillGroups', $input) ? $this->skillGroupsInput($input['skillGroups']) : null;
        if ($groupScores !== null && $mode !== 'activity') {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Nhóm kỹ năng chỉ hỗ trợ chấm Activity.');
        }
        if ($groupScores !== null && array_key_exists('skills', $input)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Không gửi đồng thời skills và skillGroups.');
        }

        $skillsInput = $this->skillsInput($input['skills'] ?? []);
        if ($mode === 'activity' && $skillsInput !== []) {
            $this->assertActivitySkills($activityId, $skillsInput);
        }
        if ($mode === 'activity' && $groupScores === null && $skillsInput === [] && $status === 'published') {
            $assigned = $this->repository->skillsForActivity($activityId);
            if ($assigned !== []) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Vui lòng chấm ít nhất một kỹ năng đã gắn với hoạt động.');
            }
        }

        $this->repository->saveAssessment(
            $teacherId,
            $studentId,
            $activityId,
            $assessmentId,
            $expectedVersion,
            $overallScore,
            $comment,
            $status,
            $status === 'published' ? gmdate('Y-m-d H:i:s.u') : null,
            $criteriaScores,
            $userId,
            substr($requestId ?? RequestId::make(null), 0, 26),
            $mode,
            $skillsInput,
            $scoreMethod,
            $formulaVersion,
            $calculationJson,
            $groupScores
        );

        return $activityId;
    }

    /** @param list<array{skillId:string,score:string}> $skillsInput */
    private function assertActivitySkills(string $activityId, array $skillsInput): void
    {
        $allowed = [];
        foreach ($this->repository->skillsForActivity($activityId) as $skill) {
            $allowed[(string) $skill['id']] = true;
        }
        if ($allowed === []) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Hoạt động chưa gắn kỹ năng. Hãy chỉnh sửa hoạt động trước khi chấm.');
        }
        foreach ($skillsInput as $item) {
            if (!isset($allowed[$item['skillId']])) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Chỉ được chấm các kỹ năng đã gắn với hoạt động.');
            }
        }
    }

    /** @return array{id:string,status:string,version:int} */
    public function publish(string $teacherUserId, string $assessmentId, int $expectedVersion, string $requestId): array
    {
        $this->assertUuid($assessmentId, 'assessmentId');
        if ($expectedVersion < 1) throw new ApiException(422, 'VALIDATION_FAILED', 'expectedVersion phải lớn hơn 0.');
        $assessment = $this->repository->draftAssessmentForTeacherUser($teacherUserId, $assessmentId);
        if ($assessment === null) throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy assessment thuộc Giáo viên hiện tại.');
        if (($assessment['status'] ?? null) !== 'draft') throw new TeacherGradingConflictException('Published assessments are immutable.');
        if ((int) ($assessment['version'] ?? 0) !== $expectedVersion) throw new TeacherGradingConflictException('Assessment version no longer matches.');
        $this->save($teacherUserId, [
            'mode' => $assessment['mode'],
            'contextId' => $assessment['contextId'],
            'studentId' => (string) $assessment['studentId'],
            'assessmentId' => $assessmentId,
            'expectedVersion' => (string) $expectedVersion,
            'assessmentStatus' => 'published',
            'overallScore' => (string) ($assessment['overallScore'] ?? ''),
            'comment' => $assessment['comment'],
            'criteria' => $assessment['criteria'] ?? [],
            ...(array_key_exists('skillGroups', $assessment)
                ? ['skillGroups' => $assessment['skillGroups']]
                : ['skills' => $assessment['skills'] ?? []]),
        ], $requestId);
        return ['id' => $assessmentId, 'status' => 'published', 'version' => $expectedVersion + 1];
    }

    /** @return array<string,mixed> */
    private function teacher(string $userId): array
    {
        $teacher = $this->repository->findTeacherByUserId($userId);
        if ($teacher === null) {
            throw new ApiException(404, 'RESOURCE_NOT_FOUND', 'Không tìm thấy hồ sơ giáo viên.');
        }

        return $teacher;
    }

    private function id(mixed $value, string $field): string
    {
        if (!is_string($value) || !Uuid::isValid($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', $field . ' không hợp lệ.');
        }

        return strtolower($value);
    }

    private function assertUuid(string $value, string $field): void
    {
        if (!Uuid::isValid($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', $field . ' không hợp lệ.');
        }
    }

    private function assessmentId(mixed $value): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if (!is_string($value) || !Uuid::isValid($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'assessmentId không hợp lệ.');
        }

        return strtolower($value);
    }

    private function version(mixed $value): int
    {
        if (!is_string($value) || !preg_match('/^\d+$/', $value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'expectedVersion phải là số nguyên không âm.');
        }

        $version = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($version === false) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'expectedVersion không hợp lệ.');
        }

        return (int) $version;
    }

    private function status(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, self::ASSESSMENT_STATUSES, true)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'assessment status không hợp lệ.');
        }

        return $value;
    }

    private function score(mixed $value, float $min, float $max, string $field, bool $nullable): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            if ($nullable) {
                return null;
            }
            throw new ApiException(422, 'VALIDATION_FAILED', $field . ' là bắt buộc.');
        }
        if (!is_string($value) || !preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', $field . ' phải là số tối đa 2 chữ số thập phân.');
        }

        $numeric = (float) $value;
        if ($numeric < $min || $numeric > $max) {
            throw new ApiException(422, 'VALIDATION_FAILED', $field . " phải nằm trong [{$min}, {$max}].");
        }

        return number_format($numeric, 2, '.', '');
    }

    private function comment(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Nhận xét không hợp lệ.');
        }

        $comment = trim($value);
        if (mb_strlen($comment) > 1000) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Nhận xét không được vượt quá 1000 ký tự.');
        }

        return $comment === '' ? null : $comment;
    }

    /**
     * @return list<array{skillId:string,score:string}>
     */
    private function skillsInput(mixed $value): array
    {
        if (!is_array($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Danh sách kỹ năng không hợp lệ.');
        }

        $skills = [];
        $seen = [];
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'skills[' . $index . '] không hợp lệ.');
            }
            $skillId = $item['skillId'] ?? null;
            if (!is_string($skillId) || !Uuid::isValid(trim($skillId))) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'skills[' . $index . '].skillId không hợp lệ.');
            }
            $skillId = strtolower(trim($skillId));
            if (isset($seen[$skillId])) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Không được chọn trùng kỹ năng trong một đánh giá.');
            }
            $seen[$skillId] = true;
            $scoreValue = $item['score'] ?? null;
            if (is_int($scoreValue) || is_float($scoreValue)) {
                $scoreValue = (string) $scoreValue;
            }
            $score = $this->score($scoreValue, 0.0, 100.0, 'skills[' . $index . '].score', false);
            if ($score === null) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'skills[' . $index . '].score là bắt buộc.');
            }
            $skills[] = ['skillId' => $skillId, 'score' => $score];
        }

        return $skills;
    }

    /** @return list<array{groupCode:string,score:string}> */
    private function skillGroupsInput(mixed $value): array
    {
        if (!is_array($value)) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Danh sách nhóm kỹ năng không hợp lệ.');
        }
        $groups = [];
        $seen = [];
        foreach ($value as $item) {
            $code = is_array($item) ? ($item['groupCode'] ?? null) : null;
            if (!is_string($code) || !preg_match('/^[a-z][a-z0-9_]{0,47}$/D', $code) || isset($seen[$code])) {
                throw new ApiException(422, 'VALIDATION_FAILED', 'Nhóm kỹ năng không hợp lệ hoặc bị trùng.');
            }
            $score = $item['score'] ?? null;
            if (is_int($score) || is_float($score)) $score = (string) $score;
            $groups[] = ['groupCode' => $code, 'score' => $this->score($score, 0, 100, 'Điểm nhóm kỹ năng', false)];
            $seen[$code] = true;
        }
        return $groups;
    }

    private function search(string $value): string
    {
        return mb_substr(trim($value), 0, 100);
    }
    /** @param list<array<string,mixed>> $students @param list<array<string,mixed>> $scores */
    private function studentsWithCriteria(array $students, array $scores): array
    {
        $scoreMap = [];
        foreach ($scores as $score) {
            $scoreMap[(string) $score['studentId']][(string) $score['criteriaId']] = (string) $score['score'];
        }

        foreach ($students as &$student) {
            $student['criteriaScores'] = $scoreMap[(string) $student['studentId']] ?? [];
        }
        unset($student);

        return $students;
    }
}
