<?php

declare(strict_types=1);

namespace TalentHub\Learner\Data\Mock;

use TalentHub\Learner\Data\Contracts\StudentRepository;
use TalentHub\Learner\Data\Enums\StudentStudyStatus;
use TalentHub\Learner\Data\Support\KeyMapper;
use TalentHub\Learner\Data\Support\MockRecordNormalizer;

final class MockStudentRepository implements StudentRepository
{
    private array $students;

    public function __construct(array $students)
    {
        $this->students = array_map([$this, 'normalize'], $students);
    }

    public function findById(string $studentId): ?array
    {
        foreach ($this->students as $student) {
            if (MockRecordNormalizer::matches($student, $studentId)
                || MockRecordNormalizer::matches($student, $studentId, 'student_id')) {
                return $student;
            }
        }

        return null;
    }

    private function normalize(array $student): array
    {
        $student = KeyMapper::toSnake($student);
        if (!isset($student['id']) && isset($student['student_id'])) {
            $student['id'] = $student['student_id'];
        }
        $student = MockRecordNormalizer::primary($student, 'student');
        if (isset($student['legacy_id'])) {
            $student['legacy_student_id'] = $student['legacy_id'];
            $student['student_id'] = $student['id'];
        }
        $student = MockRecordNormalizer::foreign($student, 'school_id', 'school');
        $student = MockRecordNormalizer::foreign($student, 'class_id', 'class');
        $student = MockRecordNormalizer::foreign($student, 'user_id', 'user');
        $student['study_status'] = StudentStudyStatus::normalize($student['study_status'] ?? null)->value;

        return $student;
    }
}
