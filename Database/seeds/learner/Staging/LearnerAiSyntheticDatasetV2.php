<?php

declare(strict_types=1);

namespace TalentHub\Learner\Seeds\Staging;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class LearnerAiSyntheticDatasetV2
{
    public const RESERVED_PREFIX = '00000000-0000-4000-8000-';
    public const TEST_ID = self::RESERVED_PREFIX . '000000000060';
    public const VERSION_ID = self::RESERVED_PREFIX . '000000001130';
    public const VERSION = '2.0.0';
    public const SCORING_VERSION = 'pilot-riasec-2';
    public const POLICY_VERSION = 'pilot-ai-policy-2';
    public const RECENT_SUBMITTED_AT = '2026-08-10 09:30:00.000000';
    public const STALE_SUBMITTED_AT = '2024-01-15 09:00:00.000000';

    private const PROFILES = [
        'R' => ['R' => 95, 'I' => 80, 'A' => 60, 'S' => 55, 'E' => 70, 'C' => 65],
        'I' => ['R' => 80, 'I' => 95, 'A' => 65, 'S' => 55, 'E' => 60, 'C' => 70],
        'A' => ['R' => 75, 'I' => 70, 'A' => 95, 'S' => 65, 'E' => 60, 'C' => 55],
        'S' => ['R' => 70, 'I' => 75, 'A' => 65, 'S' => 95, 'E' => 60, 'C' => 55],
        'E' => ['R' => 75, 'I' => 70, 'A' => 60, 'S' => 65, 'E' => 95, 'C' => 55],
        'C' => ['R' => 70, 'I' => 75, 'A' => 55, 'S' => 60, 'E' => 65, 'C' => 95],
    ];

    /** @var array<int, array{0:string, 1:string, 2:string, 3:list<string>}> */
    private const SCENARIOS = [
        101 => ['R', 'complete', 'ready', []],
        102 => ['R', 'complete', 'ready', []],
        103 => ['R', 'complete', 'ready', []],
        104 => ['R', 'one_skill', 'insufficient_data', ['skills']],
        105 => ['I', 'complete', 'ready', []],
        106 => ['I', 'complete', 'ready', []],
        107 => ['I', 'complete', 'ready', []],
        108 => ['I', 'no_experience', 'insufficient_data', ['experience']],
        109 => ['A', 'complete', 'ready', []],
        110 => ['A', 'complete', 'ready', []],
        111 => ['A', 'complete', 'ready', []],
        112 => ['A', 'stale_assessment', 'insufficient_data', ['assessment']],
        113 => ['S', 'complete', 'ready', []],
        114 => ['S', 'complete', 'ready', []],
        115 => ['S', 'complete', 'ready', []],
        116 => ['S', 'draft_evaluation', 'insufficient_data', ['evaluations']],
        117 => ['E', 'complete', 'ready', []],
        118 => ['E', 'complete', 'ready', []],
        119 => ['E', 'complete', 'ready', []],
        120 => ['E', 'revoked_evaluation', 'consent_required', []],
        121 => ['C', 'complete', 'ready', []],
        122 => ['C', 'complete', 'ready', []],
        123 => ['C', 'complete', 'ready', []],
        124 => ['C', 'missing_activity_consent', 'consent_required', []],
    ];

    private const QUESTION_TEXT = [
        'R1' => 'Synthetic realistic-interest question.',
        'R2' => 'Tôi thích lắp ráp một mô hình từ các bộ phận có sẵn.',
        'R3' => 'Tôi hứng thú khi thử dụng cụ để tạo ra một sản phẩm nhỏ.',
        'R4' => 'Tôi muốn thực hành quy trình an toàn trong một xưởng mô phỏng.',
        'I1' => 'Synthetic investigative-interest question.',
        'I2' => 'Tôi thích đặt giả thuyết rồi kiểm tra bằng dữ liệu giả lập.',
        'I3' => 'Tôi muốn phân tích nguyên nhân của một kết quả bất thường.',
        'I4' => 'Tôi thấy hứng thú khi so sánh nhiều cách giải một vấn đề.',
        'A1' => 'Synthetic artistic-interest question.',
        'A2' => 'Tôi thích tạo bố cục hình ảnh cho một câu chuyện giả tưởng.',
        'A3' => 'Tôi muốn thử nhiều cách diễn đạt cho cùng một ý tưởng.',
        'A4' => 'Tôi hứng thú khi biến một chủ đề thành sản phẩm sáng tạo.',
        'S1' => 'Tôi thích hướng dẫn bạn khác hoàn thành một nhiệm vụ mới.',
        'S2' => 'Tôi muốn lắng nghe và giúp một nhóm thống nhất cách làm.',
        'S3' => 'Tôi thấy có động lực khi hỗ trợ người khác tiến bộ.',
        'S4' => 'Tôi hứng thú với vai trò điều phối một buổi học nhóm.',
        'E1' => 'Tôi thích trình bày một ý tưởng để thuyết phục nhóm thử nghiệm.',
        'E2' => 'Tôi muốn chủ động tổ chức nguồn lực cho một dự án nhỏ.',
        'E3' => 'Tôi hứng thú khi đặt mục tiêu và theo dõi tiến độ của nhóm.',
        'E4' => 'Tôi thích đề xuất một hướng đi khi nhóm cần quyết định.',
        'C1' => 'Tôi thích sắp xếp dữ liệu theo một cấu trúc rõ ràng.',
        'C2' => 'Tôi muốn kiểm tra chi tiết để phát hiện sai lệch trong bảng số liệu.',
        'C3' => 'Tôi hứng thú với việc chuẩn hóa các bước của một quy trình.',
        'C4' => 'Tôi thích hoàn thành công việc theo tiêu chí và thứ tự xác định.',
    ];

    /** @var array<string, list<array{0:string, 1:string, 2:int, 3:string}>> */
    private const SKILLS = [
        'R' => [['iot', 'IoT Fundamentals', 50, 'technology'], ['prototyping', 'Prototype Practice', 1001, 'technology']],
        'I' => [['python', 'Python Fundamentals', 51, 'technology'], ['data_analysis', 'Synthetic Data Analysis', 1002, 'technology']],
        'A' => [['visual_design', 'Visual Design Practice', 1003, 'creative'], ['storytelling', 'Digital Storytelling', 1004, 'creative']],
        'S' => [['peer_mentoring', 'Peer Mentoring', 1005, 'community'], ['facilitation', 'Group Facilitation', 1006, 'community']],
        'E' => [['pitching', 'Idea Pitching', 1007, 'entrepreneurship'], ['initiative', 'Project Initiative', 1008, 'entrepreneurship']],
        'C' => [['spreadsheet', 'Spreadsheet Accuracy', 1009, 'operations'], ['quality_control', 'Quality Control Practice', 1010, 'operations']],
    ];

    /** @var list<array{0:string, 1:int, 2:string, 3:string}> */
    private const ACTIVITIES = [
        ['R', 30, 'Synthetic Technical Workshop', 'technology'],
        ['R', 1021, 'Synthetic Prototype Lab', 'technical_lab'],
        ['I', 1022, 'Synthetic Data Investigation Lab', 'technical_lab'],
        ['I', 1023, 'Synthetic Python Data Challenge', 'technology'],
        ['A', 1024, 'Synthetic Visual Design Studio', 'creative_studio'],
        ['A', 1025, 'Synthetic Digital Storytelling Studio', 'creative_studio'],
        ['S', 1026, 'Synthetic Peer Mentoring Circle', 'community'],
        ['S', 1027, 'Synthetic Facilitation Practice', 'community'],
        ['E', 1028, 'Synthetic Student Pitch Lab', 'innovation_lab'],
        ['E', 1029, 'Synthetic Initiative Sprint', 'entrepreneurship'],
        ['C', 1030, 'Synthetic Spreadsheet Accuracy Lab', 'technical_lab'],
        ['C', 1031, 'Synthetic Quality Control Simulation', 'operations'],
    ];

    /** @return list<string> */
    public static function studentIds(): array
    {
        $ids = [];
        foreach (array_keys(self::SCENARIOS) as $sequence) {
            $ids[] = self::studentId($sequence);
        }
        return $ids;
    }

    /** @return array<string, string> */
    public static function expectedStates(): array
    {
        $states = [];
        foreach (self::SCENARIOS as $sequence => $data) {
            $states[self::studentId($sequence)] = $data[2];
        }
        return $states;
    }

    /** @return list<array{sequence:int, student_id:string, primary:string, scenario:string, expected_state:string, expected_missing:list<string>, scores:array<string,int>}> */
    public static function participants(): array
    {
        $participants = [];
        foreach (self::SCENARIOS as $sequence => [$primary, $scenario, $expectedState, $expectedMissing]) {
            $participants[] = [
                'sequence' => $sequence,
                'student_id' => self::studentId($sequence),
                'primary' => $primary,
                'scenario' => $scenario,
                'expected_state' => $expectedState,
                'expected_missing' => $expectedMissing,
                'scores' => self::PROFILES[$primary],
            ];
        }
        return $participants;
    }

    /** @return list<array{id:string, code:string, dimension:string, content:string, is_v1:bool}> */
    public static function questions(): array
    {
        $questions = [];
        $dimensions = ['R', 'I', 'A', 'S', 'E', 'C'];
        $newQuestionSeq = 1101;

        foreach ($dimensions as $dimension) {
            for ($num = 1; $num <= 4; $num++) {
                $code = $dimension . $num;
                $content = self::QUESTION_TEXT[$code] ?? '';
                $isV1 = in_array($code, ['R1', 'I1', 'A1'], true);
                $id = match ($code) {
                    'R1' => self::id(61),
                    'I1' => self::id(62),
                    'A1' => self::id(63),
                    default => self::id($newQuestionSeq++),
                };
                $questions[] = [
                    'id' => $id,
                    'code' => $code,
                    'dimension' => $dimension,
                    'content' => $content,
                    'is_v1' => $isV1,
                ];
            }
        }

        return $questions;
    }

    /** @return list<string> */
    public static function touchedTables(): array
    {
        $tables = [];
        foreach (self::rows() as $row) {
            $tables[$row['table']] = true;
        }
        $list = array_keys($tables);
        sort($list, SORT_STRING);
        return $list;
    }

    /** @return list<array{table:string, id:string, values:array<string, scalar|null>}> */
    public static function rows(): array
    {
        $created = '2026-08-16 00:00:00.000000';
        $updated = '2026-08-16 00:00:00.000000';

        $roleLearner = self::id(1);
        $school = self::id(10);
        $class = self::id(11);
        $teacher = self::id(21);
        $criterionPresentation = self::id(40);

        $rows = [];

        // 1. users and student_profiles for 103..124 (22 learners)
        for ($sequence = 103; $sequence <= 124; $sequence++) {
            $studentId = self::studentId($sequence);
            $rows[] = self::row('users', [
                'id' => $studentId,
                'roleId' => $roleLearner,
                'email' => 'pilot-learner-' . $sequence . '@synthetic.example',
                'passwordHash' => '!synthetic-disabled-login-v2!',
                'fullName' => 'Synthetic Learner ' . $sequence,
                'status' => 'active',
                'lastLoginAt' => null,
                'createdAt' => $created,
                'updatedAt' => $updated,
            ]);
            $rows[] = self::row('student_profiles', [
                'id' => $studentId,
                'userId' => $studentId,
                'classId' => $class,
                'dateOfBirth' => sprintf('2010-01-%02d', $sequence - 100),
                'phone' => sprintf('+0000000%d', $sequence),
                'studyStatus' => 'active',
                'createdAt' => $created,
                'updatedAt' => $updated,
            ]);
        }

        // 2. 10 new skills (1001..1010)
        foreach (self::SKILLS as $dimensionSkills) {
            foreach ($dimensionSkills as [$code, $name, $seq, $category]) {
                if ($seq >= 1001) {
                    $rows[] = self::row('skills', [
                        'id' => self::id($seq),
                        'code' => $code,
                        'name' => $name,
                        'category' => $category,
                        'status' => 'active',
                        'createdAt' => $created,
                        'updatedAt' => $updated,
                    ]);
                }
            }
        }

        // 3. student_skills (66 rows) and learner_skill_evidence (66 rows)
        $studentSkillIndex = 1;
        $evidenceIndex = 1;
        foreach (self::SCENARIOS as $sequence => [$primary, $scenario]) {
            $studentId = self::studentId($sequence);

            /** @var list<array{0:string, 1:int}> $assignedSkills */
            $assignedSkills = match ($sequence) {
                101, 102 => [
                    ['prototyping', 1001],
                ],
                103 => [
                    ['iot', 50],
                    ['prototyping', 1001],
                    ['python', 51],
                ],
                104 => [
                    ['iot', 50],
                ],
                default => [
                    ['iot', 50],
                    [self::SKILLS[$primary][0][0], self::SKILLS[$primary][0][2]],
                    [self::SKILLS[$primary][1][0], self::SKILLS[$primary][1][2]],
                ],
            };

            foreach ($assignedSkills as [$skillCode, $skillSeq]) {
                $studentSkillId = self::id(200000 + $studentSkillIndex++);
                $evidenceId = self::id(300000 + $evidenceIndex++);

                $rows[] = self::row('student_skills', [
                    'id' => $studentSkillId,
                    'studentId' => $studentId,
                    'skillId' => self::id($skillSeq),
                    'levelScore' => '85.00',
                    'sourceType' => 'teacher',
                    'verificationStatus' => 'verified',
                    'verifiedAt' => '2026-08-05 10:00:00.000000',
                    'createdAt' => $created,
                    'updatedAt' => $updated,
                ]);

                $rows[] = self::row('learner_skill_evidence', [
                    'id' => $evidenceId,
                    'studentSkillId' => $studentSkillId,
                    'evidenceType' => 'teacher_assessment',
                    'evidenceRef' => 'synthetic:assessment:' . $sequence . ':' . $skillCode,
                    'verificationStatus' => 'verified',
                    'observedAt' => '2026-08-05 10:00:00.000000',
                    'createdAt' => $created,
                ]);
            }
        }

        // 4. 21 new test_questions (1101..1121)
        $allQuestions = self::questions();
        foreach ($allQuestions as $question) {
            if (!$question['is_v1']) {
                $rows[] = self::row('test_questions', [
                    'id' => $question['id'],
                    'testId' => self::TEST_ID,
                    'code' => $question['code'],
                    'content' => $question['content'],
                    'optionsJson' => '{"min":1,"max":5}',
                    'status' => 'published',
                    'createdAt' => $created,
                    'updatedAt' => $updated,
                ]);
            }
        }

        // 5. learner_assessment_versions (1 row: 1130)
        $rows[] = self::row('learner_assessment_versions', [
            'id' => self::VERSION_ID,
            'testId' => self::TEST_ID,
            'version' => self::VERSION,
            'scoringVersion' => self::SCORING_VERSION,
            'schemaHash' => hash('sha256', 'pilot-riasec-v2'),
            'status' => 'published',
            'publishedAt' => '2026-08-05 11:00:00.000000',
            'createdAt' => $created,
        ]);

        // 6. learner_assessment_question_versions (24 rows: 1131..1154)
        foreach ($allQuestions as $index => $question) {
            $position = $index + 1;
            $rows[] = self::row('learner_assessment_question_versions', [
                'id' => self::id(1130 + $position),
                'versionId' => self::VERSION_ID,
                'questionId' => $question['id'],
                'position' => $position,
                'dimensionCode' => $question['dimension'],
                'required' => 1,
                'createdAt' => $created,
            ]);
        }

        // 7. test_attempts (24 rows), metadata (24 rows), answers (576 rows), test_results (24 rows)
        $answerIndex = 1;
        foreach (self::SCENARIOS as $sequence => [$primary]) {
            $studentId = self::studentId($sequence);
            $attemptId = self::id(400000 + $sequence);
            $metadataId = self::id(401000 + $sequence);
            $resultId = self::id(600000 + $sequence);

            $scores = self::PROFILES[$primary];

            if ($sequence === 112) {
                // Historical backfill learner: assessment submitted in 2024 (>365 days ago)
                $startedAt = '2024-01-15 08:30:00.000000';
                $submittedAt = self::STALE_SUBMITTED_AT;
            } else {
                $offset = $sequence - 101;
                $startedAt = sprintf('2026-08-10 09:00:%02d.000000', $offset);
                $submittedAt = sprintf('2026-08-10 09:30:%02d.000000', $offset);
            }

            $rows[] = self::row('test_attempts', [
                'id' => $attemptId,
                'testId' => self::TEST_ID,
                'studentId' => $studentId,
                'status' => 'submitted',
                'startedAt' => $startedAt,
                'submittedAt' => $submittedAt,
                'createdAt' => $created,
                'updatedAt' => $updated,
            ]);

            // Calculate answers for 24 questions
            $answersData = [];
            $dimensions = ['R', 'I', 'A', 'S', 'E', 'C'];
            $questionMap = [];
            foreach ($allQuestions as $q) {
                $questionMap[$q['code']] = $q['id'];
            }

            $attemptAnswerRows = [];
            $questionNum = 0;
            foreach ($dimensions as $dimension) {
                $dimScore = $scores[$dimension];
                $targetSum = (int) ($dimScore / 5);
                $base = intdiv($targetSum, 4);
                $remainder = $targetSum % 4;

                for ($k = 1; $k <= 4; $k++) {
                    $questionNum++;
                    $val = $base + ($k - 1 < $remainder ? 1 : 0);
                    $code = $dimension . $k;
                    $qId = $questionMap[$code];
                    $answerId = self::id(500000 + $answerIndex++);

                    // Chronology: startedAt <= answeredAt <= submittedAt
                    if ($sequence === 112) {
                        $answeredAt = sprintf('2024-01-15 08:%02d:%02d.000000', 31 + intdiv($questionNum, 2), ($questionNum % 2) * 30);
                    } else {
                        $answeredAt = sprintf('2026-08-10 09:%02d:%02d.000000', 1 + intdiv($questionNum, 2), ($questionNum % 2) * 30);
                    }

                    $answersData[] = ['question_id' => $qId, 'value' => $val];

                    $attemptAnswerRows[] = self::row('learner_assessment_answers', [
                        'id' => $answerId,
                        'attemptId' => $attemptId,
                        'questionId' => $qId,
                        'answerJson' => json_encode(['value' => $val], JSON_THROW_ON_ERROR),
                        'answeredAt' => $answeredAt,
                    ]);
                }
            }

            $canonicalAnswersJson = json_encode($answersData, JSON_THROW_ON_ERROR);
            $inputHash = hash('sha256', 'pilot-riasec-2:' . $studentId . ':' . $canonicalAnswersJson);

            $rows[] = self::row('learner_assessment_attempt_metadata', [
                'id' => $metadataId,
                'attemptId' => $attemptId,
                'versionId' => self::VERSION_ID,
                'status' => 'submitted',
                'expiresAt' => null,
                'submittedAt' => $submittedAt,
                'inputHash' => $inputHash,
                'createdAt' => $created,
                'updatedAt' => $updated,
            ]);

            foreach ($attemptAnswerRows as $answerRow) {
                $rows[] = $answerRow;
            }

            // Top 3 dimensions in descending score order
            $sortedScores = $scores;
            arsort($sortedScores, SORT_NUMERIC);
            $top3 = implode('', array_slice(array_keys($sortedScores), 0, 3));

            $rows[] = self::row('test_results', [
                'id' => $resultId,
                'attemptId' => $attemptId,
                'resultCode' => $top3,
                'summary' => 'Synthetic RIASEC V2 result for archetype ' . $primary . '.',
                'dimensionScoresJson' => json_encode($scores, JSON_THROW_ON_ERROR),
                'scoringVersion' => self::SCORING_VERSION,
                'createdAt' => $created,
            ]);
        }

        // 8. 11 new activities (1021..1031) and 11 QR tokens (1041..1051)
        foreach (self::ACTIVITIES as [$dim, $actSeq, $title, $category]) {
            if ($actSeq >= 1021) {
                $actId = self::id($actSeq);
                $qrId = self::id(1041 + ($actSeq - 1021));

                $rows[] = self::row('activities', [
                    'id' => $actId,
                    'schoolId' => $school,
                    'createdByTeacherId' => $teacher,
                    'title' => $title,
                    'category' => $category,
                    'startAt' => '2026-08-06 08:00:00.000000',
                    'endAt' => '2026-08-06 12:00:00.000000',
                    'capacity' => 25,
                    'status' => 'published',
                    'createdAt' => $created,
                    'updatedAt' => $updated,
                ]);

                $rows[] = self::row('activity_qr_tokens', [
                    'id' => $qrId,
                    'activityId' => $actId,
                    'tokenHash' => hash('sha256', 'synthetic-ai-v2-activity-' . $actSeq),
                    'validFrom' => '2026-08-06 07:00:00.000000',
                    'validUntil' => '2026-08-06 13:00:00.000000',
                    'status' => 'active',
                    'createdAt' => $created,
                ]);
            }
        }

        // 9. activity_registrations (24 rows), checkins (23 rows), experience_logs (23 rows)
        $hoursPool = ['2.50', '3.00', '3.50', '4.00', '4.50', '5.00', '5.50', '6.00', '6.50'];

        foreach (self::SCENARIOS as $sequence => [$primary]) {
            $studentId = self::studentId($sequence);
            $regId = self::id(700000 + $sequence);
            $checkinId = self::id(701000 + $sequence);
            $expId = self::id(702000 + $sequence);

            $activitySeq = match ($sequence) {
                101, 102 => 1021,
                103 => 30,
                104 => 1021,
                default => match ($primary) {
                    'R' => 1021,
                    'I' => 1022,
                    'A' => 1024,
                    'S' => 1026,
                    'E' => 1028,
                    'C' => 1030,
                },
            };

            $activityId = self::id($activitySeq);
            $qrTokenId = $activitySeq === 30 ? self::id(31) : self::id(1041 + ($activitySeq - 1021));

            $rows[] = self::row('activity_registrations', [
                'id' => $regId,
                'activityId' => $activityId,
                'studentId' => $studentId,
                'status' => 'attended',
                'registeredAt' => '2026-08-04 09:00:00.000000',
                'updatedAt' => '2026-08-04 09:00:00.000000',
            ]);

            if ($sequence !== 108) {
                $hours = $hoursPool[($sequence - 101) % count($hoursPool)];

                $rows[] = self::row('checkins', [
                    'id' => $checkinId,
                    'registrationId' => $regId,
                    'qrTokenId' => $qrTokenId,
                    'status' => 'confirmed',
                    'checkedInAt' => '2026-08-06 08:30:00.000000',
                    'confirmedAt' => '2026-08-06 08:35:00.000000',
                    'createdAt' => $created,
                ]);

                $rows[] = self::row('experience_logs', [
                    'id' => $expId,
                    'studentId' => $studentId,
                    'activityId' => $activityId,
                    'checkinId' => $checkinId,
                    'hours' => $hours,
                    'status' => 'confirmed',
                    'auditReason' => 'Synthetic V2 confirmed attendance.',
                    'confirmedAt' => '2026-08-06 12:00:00.000000',
                    'createdAt' => $created,
                ]);
            }
        }

        // 10. assessments (24 rows) and assessment_scores (24 rows)
        $overallScorePool = ['76.00', '82.00', '88.00', '91.00', '74.00', '85.00', '79.00', '94.00', '72.00'];
        $presentationPool = ['58.00', '62.00', '68.00', '75.00', '82.00', '55.00'];

        foreach (self::SCENARIOS as $sequence => [$primary]) {
            $studentId = self::studentId($sequence);
            $assessmentId = self::id(800000 + $sequence);
            $scoreId = self::id(801000 + $sequence);

            $activitySeq = match ($sequence) {
                101, 102 => 1021,
                103 => 30,
                104 => 1021,
                default => match ($primary) {
                    'R' => 1021,
                    'I' => 1022,
                    'A' => 1024,
                    'S' => 1026,
                    'E' => 1028,
                    'C' => 1030,
                },
            };
            $activityId = self::id($activitySeq);

            $isDraft = ($sequence === 116);
            $status = $isDraft ? 'draft' : 'published';
            $overallScore = $isDraft ? null : $overallScorePool[($sequence - 101) % count($overallScorePool)];
            $publishedAt = $isDraft ? null : '2026-08-07 09:00:00.000000';

            $rows[] = self::row('assessments', [
                'id' => $assessmentId,
                'teacherId' => $teacher,
                'studentId' => $studentId,
                'activityId' => $activityId,
                'overallScore' => $overallScore,
                'comment' => 'Synthetic teacher evaluation.',
                'status' => $status,
                'publishedAt' => $publishedAt,
                'version' => 1,
                'createdAt' => $created,
                'updatedAt' => $updated,
            ]);

            // Presentation score
            $scoreValue = match ($sequence) {
                101 => '55.00',
                default => $presentationPool[($sequence - 101) % count($presentationPool)],
            };

            $rows[] = self::row('assessment_scores', [
                'id' => $scoreId,
                'assessmentId' => $assessmentId,
                'criteriaId' => $criterionPresentation,
                'score' => $scoreValue,
                'createdAt' => $created,
                'updatedAt' => $updated,
            ]);
        }

        // 11. learner_ai_consent_events (96 rows)
        $consentBase = new DateTimeImmutable('2026-08-08 09:00:00.000000', new DateTimeZone('UTC'));
        $consentEventIndex = 1;
        $consentRequestIndex = 1;

        foreach (self::SCENARIOS as $sequence => [$primary, $scenario]) {
            $studentId = self::studentId($sequence);

            $scopes = match ($sequence) {
                124 => ['assessment', 'skills', 'evaluation'],
                default => ['assessment', 'skills', 'activity', 'evaluation'],
            };

            foreach ($scopes as $scope) {
                $eventId = self::id(900000 + $consentEventIndex);
                $requestId = self::id(910000 + $consentRequestIndex++);
                $occurredAt = $consentBase->modify('+' . $consentEventIndex . ' seconds')->format('Y-m-d H:i:s.u');
                $consentEventIndex++;

                $rows[] = self::row('learner_ai_consent_events', [
                    'id' => $eventId,
                    'studentId' => $studentId,
                    'scope' => $scope,
                    'action' => 'granted',
                    'policyVersion' => self::POLICY_VERSION,
                    'occurredAt' => $occurredAt,
                    'requestId' => $requestId,
                ]);
            }

            if ($sequence === 120) {
                $eventId = self::id(900000 + $consentEventIndex);
                $requestId = self::id(910000 + $consentRequestIndex++);
                // Revoke occurs 1 hour later (3600 seconds), strictly after the grant
                $occurredAt = $consentBase->modify('+3600 seconds')->format('Y-m-d H:i:s.u');
                $consentEventIndex++;

                $rows[] = self::row('learner_ai_consent_events', [
                    'id' => $eventId,
                    'studentId' => $studentId,
                    'scope' => 'evaluation',
                    'action' => 'revoked',
                    'policyVersion' => self::POLICY_VERSION,
                    'occurredAt' => $occurredAt,
                    'requestId' => $requestId,
                ]);
            }
        }

        return $rows;
    }

    public static function contentHash(): string
    {
        $rows = self::rows();
        usort($rows, static fn (array $a, array $b): int => [$a['table'], $a['id']] <=> [$b['table'], $b['id']]);

        $canonical = array_map(static fn (array $row): array => [
            'table' => $row['table'],
            'id' => $row['id'],
            'values' => self::canonicalizeValues($row['values']),
        ], $rows);

        $json = json_encode(
            $canonical,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );

        return hash('sha256', $json);
    }

    public static function validate(): void
    {
        self::validateDataset(self::participants(), self::questions(), self::rows());
    }

    /**
     * Pure validation entrypoint to enforce all structural, relational, and scenario invariants.
     *
     * @param list<array{sequence:int, student_id:string, primary:string, scenario:string, expected_state:string, expected_missing:list<string>, scores:array<string,int>}> $participants
     * @param list<array{id:string, code:string, dimension:string, content:string, is_v1:bool}> $questions
     * @param list<array{table:string, id:string, values:array<string, scalar|null>}> $rows
     */
    public static function validateDataset(array $participants, array $questions, array $rows): void
    {
        // 1. Participant count & distribution
        if (count($participants) !== 24) {
            throw new RuntimeException('V2 validation failed: exactly 24 participants required.');
        }

        $studentIds = array_column($participants, 'student_id');
        if (count(array_unique($studentIds)) !== 24) {
            throw new RuntimeException('V2 validation failed: duplicate participant IDs.');
        }

        $primaries = array_count_values(array_column($participants, 'primary'));
        if ($primaries !== ['R' => 4, 'I' => 4, 'A' => 4, 'S' => 4, 'E' => 4, 'C' => 4]) {
            throw new RuntimeException('V2 validation failed: RIASEC archetype imbalance.');
        }

        $states = array_count_values(array_column($participants, 'expected_state'));
        if ($states !== ['ready' => 18, 'insufficient_data' => 4, 'consent_required' => 2]) {
            throw new RuntimeException('V2 validation failed: expected state counts mismatch.');
        }

        // 2. Question count & distribution
        if (count($questions) !== 24) {
            throw new RuntimeException('V2 validation failed: exactly 24 questions required.');
        }

        $questionDimensions = array_count_values(array_column($questions, 'dimension'));
        if ($questionDimensions !== ['R' => 4, 'I' => 4, 'A' => 4, 'S' => 4, 'E' => 4, 'C' => 4]) {
            throw new RuntimeException('V2 validation failed: four questions per dimension required.');
        }

        // 3. Row count & row-family counts
        if (count($rows) !== 1116) {
            throw new RuntimeException('V2 validation failed: exactly 1116 rows required, got ' . count($rows));
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

        $actualFamilyCounts = [];
        $rowsByTable = [];
        $keys = [];
        $datetimeColumns = [
            'createdAt', 'updatedAt', 'startAt', 'endAt', 'validFrom', 'validUntil',
            'checkedInAt', 'confirmedAt', 'registeredAt', 'publishedAt', 'startedAt',
            'submittedAt', 'expiresAt', 'answeredAt', 'observedAt', 'verifiedAt', 'occurredAt', 'lastLoginAt'
        ];

        foreach ($rows as $row) {
            $table = $row['table'] ?? '';
            $id = $row['id'] ?? '';
            $values = $row['values'] ?? [];

            $actualFamilyCounts[$table] = ($actualFamilyCounts[$table] ?? 0) + 1;
            $rowsByTable[$table][$id] = $values;

            $key = $table . "\0" . $id;
            if (isset($keys[$key])) {
                throw new RuntimeException('V2 validation failed: duplicate row key ' . $table . '.' . $id);
            }
            $keys[$key] = true;

            if (preg_match('/^00000000-0000-4000-8000-[0-9]{12}$/', $id) !== 1) {
                throw new RuntimeException('V2 validation failed: row id outside reserved prefix: ' . $id);
            }

            if (($values['id'] ?? null) !== $id) {
                throw new RuntimeException('V2 validation failed: row values id mismatch for ' . $table . '.' . $id);
            }

            // Values validation (emails, passwords, datetimes)
            foreach ($values as $column => $value) {
                if ($value === null) {
                    continue;
                }
                if ($column === 'passwordHash' && $value !== '!synthetic-disabled-login-v2!') {
                    throw new RuntimeException('V2 validation failed: password placeholder mismatch in ' . $table . '.' . $id);
                }
                if ($column === 'dateOfBirth') {
                    if (!is_string($value) || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value) !== 1) {
                        throw new RuntimeException('V2 validation failed: dateOfBirth invalid format in ' . $id);
                    }
                    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value, new DateTimeZone('UTC'));
                    if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
                        throw new RuntimeException('V2 validation failed: dateOfBirth round-trip mismatch in ' . $id);
                    }
                    continue;
                }
                if (in_array($column, $datetimeColumns, true)) {
                    if (!is_string($value) || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\.[0-9]{6}$/', $value) !== 1) {
                        throw new RuntimeException('V2 validation failed: invalid DATETIME(6) format in ' . $table . '.' . $column . ': ' . $value);
                    }

                    $parts = explode(' ', $value);
                    $timeParts = explode(':', $parts[1] ?? '');
                    $hour = (int) ($timeParts[0] ?? -1);
                    $minute = (int) ($timeParts[1] ?? -1);
                    $second = (float) ($timeParts[2] ?? -1);
                    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0.0 || $second >= 60.0) {
                        throw new RuntimeException('V2 validation failed: invalid DATETIME(6) hour/minute/second in ' . $value);
                    }

                    $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.u', $value, new DateTimeZone('UTC'));
                    if ($parsed === false || $parsed->format('Y-m-d H:i:s.u') !== $value) {
                        throw new RuntimeException('V2 validation failed: DATETIME(6) round-trip mismatch in ' . $table . '.' . $column . ': ' . $value);
                    }
                    $errors = DateTimeImmutable::getLastErrors();
                    if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                        throw new RuntimeException('V2 validation failed: DATETIME(6) has parse errors in ' . $value);
                    }
                }
                if (is_string($value) && str_contains($value, '@')) {
                    if (preg_match('/@(?:[A-Za-z0-9-]+\.)*example$/', $value) !== 1) {
                        throw new RuntimeException('V2 validation failed: invalid email domain in ' . $table . '.' . $column . ': ' . $value);
                    }
                }
            }
        }

        ksort($actualFamilyCounts);
        ksort($expectedFamilyCounts);
        if ($actualFamilyCounts !== $expectedFamilyCounts) {
            throw new RuntimeException('V2 validation failed: exact row-family counts mismatch.');
        }

        // 4. Score profiles validation
        foreach (self::PROFILES as $primary => $scores) {
            $topDimension = null;
            $topScore = -1;
            $secondScore = -1;
            foreach ($scores as $dim => $score) {
                if ($score % 5 !== 0) {
                    throw new RuntimeException('V2 validation failed: score not multiple of 5 for ' . $primary . '.' . $dim);
                }
                if ($score > $topScore) {
                    $secondScore = $topScore;
                    $topScore = $score;
                    $topDimension = $dim;
                } elseif ($score > $secondScore) {
                    $secondScore = $score;
                }
            }
            if ($topDimension !== $primary || $topScore === $secondScore) {
                throw new RuntimeException('V2 validation failed: primary archetype score must be strictly highest.');
            }
            if ($scores['R'] < 70 || $scores['I'] < 70) {
                throw new RuntimeException('V2 validation failed: R and I must be >= 70 for baseline rule match.');
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

        $allStudentIds = array_fill_keys(self::studentIds(), true);
        $allSkillIds = array_merge([$v1SkillIot => true, $v1SkillPython => true], array_fill_keys(array_keys($rowsByTable['skills'] ?? []), true));
        $allQuestionIds = array_merge($v1Questions, array_fill_keys(array_keys($rowsByTable['test_questions'] ?? []), true));
        $allActivityIds = array_merge([$v1Activity => true], array_fill_keys(array_keys($rowsByTable['activities'] ?? []), true));
        $allQrIds = array_merge([$v1Qr => true], array_fill_keys(array_keys($rowsByTable['activity_qr_tokens'] ?? []), true));

        foreach ($rowsByTable['users'] ?? [] as $id => $u) {
            if ($u['roleId'] !== $v1RoleLearner || !isset($allStudentIds[$id])) {
                throw new RuntimeException('V2 validation failed: invalid user roleId or studentId in ' . $id);
            }
        }

        foreach ($rowsByTable['student_profiles'] ?? [] as $id => $sp) {
            if ($sp['userId'] !== $id || $sp['classId'] !== $v1Class) {
                throw new RuntimeException('V2 validation failed: invalid student profile in ' . $id);
            }
        }

        foreach ($rowsByTable['student_skills'] ?? [] as $id => $ss) {
            if (!isset($allStudentIds[$ss['studentId']]) || !isset($allSkillIds[$ss['skillId']])) {
                throw new RuntimeException('V2 validation failed: invalid studentId or skillId in student_skills ' . $id);
            }
        }

        foreach ($rowsByTable['learner_skill_evidence'] ?? [] as $id => $se) {
            if (!isset($rowsByTable['student_skills'][$se['studentSkillId']])) {
                throw new RuntimeException('V2 validation failed: missing studentSkillId in evidence ' . $id);
            }
        }

        foreach ($rowsByTable['test_questions'] ?? [] as $id => $tq) {
            if ($tq['testId'] !== $v1TestHolland) {
                throw new RuntimeException('V2 validation failed: invalid testId in test_questions ' . $id);
            }
        }

        foreach ($rowsByTable['learner_assessment_versions'] ?? [] as $id => $lav) {
            if ($lav['testId'] !== $v1TestHolland) {
                throw new RuntimeException('V2 validation failed: invalid testId in versions ' . $id);
            }
        }

        foreach ($rowsByTable['learner_assessment_question_versions'] ?? [] as $id => $laqv) {
            if ($laqv['versionId'] !== self::VERSION_ID || !isset($allQuestionIds[$laqv['questionId']])) {
                throw new RuntimeException('V2 validation failed: invalid versionId or questionId in question_versions ' . $id);
            }
        }

        foreach ($rowsByTable['test_attempts'] ?? [] as $id => $ta) {
            if ($ta['testId'] !== $v1TestHolland || !isset($allStudentIds[$ta['studentId']])) {
                throw new RuntimeException('V2 validation failed: invalid testId or studentId in test_attempts ' . $id);
            }
        }

        foreach ($rowsByTable['learner_assessment_attempt_metadata'] ?? [] as $id => $laam) {
            if (!isset($rowsByTable['test_attempts'][$laam['attemptId']]) || $laam['versionId'] !== self::VERSION_ID) {
                throw new RuntimeException('V2 validation failed: invalid attemptId or versionId in metadata ' . $id);
            }
        }

        foreach ($rowsByTable['learner_assessment_answers'] ?? [] as $id => $laa) {
            if (!isset($rowsByTable['test_attempts'][$laa['attemptId']]) || !isset($allQuestionIds[$laa['questionId']])) {
                throw new RuntimeException('V2 validation failed: invalid attemptId or questionId in answers ' . $id);
            }
        }

        foreach ($rowsByTable['test_results'] ?? [] as $id => $tr) {
            if (!isset($rowsByTable['test_attempts'][$tr['attemptId']])) {
                throw new RuntimeException('V2 validation failed: invalid attemptId in test_results ' . $id);
            }
        }

        foreach ($rowsByTable['activities'] ?? [] as $id => $act) {
            if ($act['schoolId'] !== $v1School || $act['createdByTeacherId'] !== $v1TeacherProfile) {
                throw new RuntimeException('V2 validation failed: invalid schoolId or teacherId in activities ' . $id);
            }
        }

        $allowedQrColumns = ['activityId', 'createdAt', 'id', 'status', 'tokenHash', 'validFrom', 'validUntil'];
        $seenQrHashes = [];
        foreach ($rowsByTable['activity_qr_tokens'] ?? [] as $id => $qr) {
            $actualQrColumns = array_keys($qr);
            sort($actualQrColumns, SORT_STRING);
            if ($actualQrColumns !== $allowedQrColumns) {
                throw new RuntimeException('V2 validation failed: unexpected QR columns in ' . $id);
            }
            if (!isset($allActivityIds[$qr['activityId']])) {
                throw new RuntimeException('V2 validation failed: invalid activityId in qr_tokens ' . $id);
            }
            $hash = (string) $qr['tokenHash'];
            if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
                throw new RuntimeException('V2 validation failed: invalid tokenHash format in qr ' . $id);
            }
            if (isset($seenQrHashes[$hash])) {
                throw new RuntimeException('V2 validation failed: duplicate tokenHash in qr ' . $id);
            }
            $seenQrHashes[$hash] = true;
        }

        foreach ($rowsByTable['activity_registrations'] ?? [] as $id => $ar) {
            if (!isset($allActivityIds[$ar['activityId']]) || !isset($allStudentIds[$ar['studentId']])) {
                throw new RuntimeException('V2 validation failed: invalid activityId or studentId in registrations ' . $id);
            }
        }

        // Map QR tokens to activity
        $qrActivityMap = [$v1Qr => $v1Activity];
        foreach ($rowsByTable['activity_qr_tokens'] ?? [] as $qrId => $qr) {
            $qrActivityMap[$qrId] = $qr['activityId'];
        }

        foreach ($rowsByTable['checkins'] ?? [] as $id => $chk) {
            $regId = (string) $chk['registrationId'];
            $qrTokenId = (string) $chk['qrTokenId'];
            if (!isset($rowsByTable['activity_registrations'][$regId]) || !isset($allQrIds[$qrTokenId])) {
                throw new RuntimeException('V2 validation failed: invalid registrationId or qrTokenId in checkins ' . $id);
            }
            $regActivity = $rowsByTable['activity_registrations'][$regId]['activityId'] ?? null;
            $qrActivity = $qrActivityMap[$qrTokenId] ?? null;
            if ($regActivity === null || $qrActivity === null || $regActivity !== $qrActivity) {
                throw new RuntimeException('V2 validation failed: registration / check-in / QR activity mismatch in checkin ' . $id);
            }
        }

        foreach ($rowsByTable['experience_logs'] ?? [] as $id => $el) {
            $chkId = (string) $el['checkinId'];
            if (!isset($allStudentIds[$el['studentId']]) || !isset($allActivityIds[$el['activityId']]) || !isset($rowsByTable['checkins'][$chkId])) {
                throw new RuntimeException('V2 validation failed: invalid studentId, activityId, or checkinId in experience_logs ' . $id);
            }
            $chk = $rowsByTable['checkins'][$chkId];
            $reg = $rowsByTable['activity_registrations'][$chk['registrationId']] ?? [];
            if ($reg['studentId'] !== $el['studentId'] || $reg['activityId'] !== $el['activityId']) {
                throw new RuntimeException('V2 validation failed: experience mismatch with registration via checkin in ' . $id);
            }
        }

        // Map registrations per student
        $studentRegistrations = [];
        foreach ($rowsByTable['activity_registrations'] ?? [] as $ar) {
            $studentRegistrations[$ar['studentId']][$ar['activityId']] = true;
        }

        foreach ($rowsByTable['assessments'] ?? [] as $id => $ass) {
            if ($ass['teacherId'] !== $v1TeacherProfile || !isset($allStudentIds[$ass['studentId']]) || !isset($allActivityIds[$ass['activityId']])) {
                throw new RuntimeException('V2 validation failed: invalid teacherId, studentId, or activityId in assessments ' . $id);
            }
            if (!isset($studentRegistrations[$ass['studentId']][$ass['activityId']])) {
                throw new RuntimeException('V2 validation failed: assessment registration mismatch in ' . $id);
            }
        }

        foreach ($rowsByTable['assessment_scores'] ?? [] as $id => $as) {
            if (!isset($rowsByTable['assessments'][$as['assessmentId']]) || $as['criteriaId'] !== $v1CriterionPresentation) {
                throw new RuntimeException('V2 validation failed: invalid assessmentId or criteriaId in assessment_scores ' . $id);
            }
        }

        foreach ($rowsByTable['learner_ai_consent_events'] ?? [] as $id => $ce) {
            if (!isset($allStudentIds[$ce['studentId']])) {
                throw new RuntimeException('V2 validation failed: invalid studentId in consent_events ' . $id);
            }
        }

        // 6. Assessment Consistency & Chronology
        $questionDimMap = [];
        foreach ($questions as $q) {
            $questionDimMap[$q['id']] = $q['dimension'];
        }

        $answersByAttempt = [];
        foreach ($rowsByTable['learner_assessment_answers'] ?? [] as $ans) {
            $answersByAttempt[$ans['attemptId']][] = $ans;
        }

        foreach ($rowsByTable['test_attempts'] ?? [] as $attemptId => $attempt) {
            $studentId = $attempt['studentId'];
            $studentSeq = (int) substr($studentId, -3);
            $startedAt = $attempt['startedAt'];
            $submittedAt = $attempt['submittedAt'];

            if ($startedAt > $submittedAt) {
                throw new RuntimeException('V2 validation failed: attempt startedAt > submittedAt in ' . $attemptId);
            }

            $attemptAnswers = $answersByAttempt[$attemptId] ?? [];
            if (count($attemptAnswers) !== 24) {
                throw new RuntimeException('V2 validation failed: attempt does not have exactly 24 answers in ' . $attemptId);
            }

            $seenQuestions = [];
            $dimensionSums = ['R' => 0, 'I' => 0, 'A' => 0, 'S' => 0, 'E' => 0, 'C' => 0];
            $rawAnswers = [];

            foreach ($attemptAnswers as $ans) {
                $qId = $ans['questionId'];
                if (isset($seenQuestions[$qId])) {
                    throw new RuntimeException('V2 validation failed: duplicate question in attempt ' . $attemptId);
                }
                $seenQuestions[$qId] = true;

                $answeredAt = $ans['answeredAt'];
                if ($answeredAt < $startedAt || $answeredAt > $submittedAt) {
                    throw new RuntimeException('V2 validation failed: attempt chronology violation in answer ' . $ans['id'] . ' for student ' . $studentSeq);
                }

                $decoded = json_decode((string) $ans['answerJson'], true);
                if (!is_array($decoded) || !isset($decoded['value'])) {
                    throw new RuntimeException('V2 validation failed: invalid answerJson in ' . $ans['id']);
                }
                $val = (int) $decoded['value'];
                if ($val < 1 || $val > 5) {
                    throw new RuntimeException('V2 validation failed: answer value not in 1..5 in ' . $ans['id']);
                }

                $dim = $questionDimMap[$qId] ?? null;
                if ($dim === null) {
                    throw new RuntimeException('V2 validation failed: unknown question dimension in ' . $qId);
                }
                $dimensionSums[$dim] += $val;
                $rawAnswers[] = ['question_id' => $qId, 'value' => $val];
            }

            $meta = null;
            foreach ($rowsByTable['learner_assessment_attempt_metadata'] ?? [] as $m) {
                if ($m['attemptId'] === $attemptId) {
                    $meta = $m;
                    break;
                }
            }
            if ($meta === null || $meta['submittedAt'] !== $submittedAt) {
                throw new RuntimeException('V2 validation failed: metadata submittedAt mismatch in attempt ' . $attemptId);
            }
            $expectedInputHash = hash('sha256', 'pilot-riasec-2:' . $studentId . ':' . json_encode($rawAnswers, JSON_THROW_ON_ERROR));
            if ($meta['inputHash'] !== $expectedInputHash) {
                throw new RuntimeException('V2 validation failed: metadata inputHash mismatch in attempt ' . $attemptId);
            }

            $res = null;
            foreach ($rowsByTable['test_results'] ?? [] as $r) {
                if ($r['attemptId'] === $attemptId) {
                    $res = $r;
                    break;
                }
            }
            if ($res === null) {
                throw new RuntimeException('V2 validation failed: missing test_result for attempt ' . $attemptId);
            }
            $scores = json_decode((string) $res['dimensionScoresJson'], true);
            if (!is_array($scores)) {
                throw new RuntimeException('V2 validation failed: invalid dimensionScoresJson in attempt ' . $attemptId);
            }
            foreach ($dimensionSums as $dim => $sum) {
                if (($sum * 5) !== ($scores[$dim] ?? null)) {
                    throw new RuntimeException('V2 validation failed: answers sum * 5 does not match dimensionScores for ' . $dim . ' in attempt ' . $attemptId);
                }
            }
            $sorted = $scores;
            arsort($sorted, SORT_NUMERIC);
            $top3 = implode('', array_slice(array_keys($sorted), 0, 3));
            if ($res['resultCode'] !== $top3) {
                throw new RuntimeException('V2 validation failed: resultCode does not match top 3 dimensions in attempt ' . $attemptId);
            }
        }

        // 7. Effective Skills & Scenario Consistency
        $skillCodeMap = [$v1SkillIot => 'iot', $v1SkillPython => 'python'];
        foreach ($rowsByTable['skills'] ?? [] as $sId => $sk) {
            $skillCodeMap[$sId] = $sk['code'];
        }

        $effectiveSkills = [
            '00000000-0000-4000-8000-000000000101' => ['iot' => true, 'python' => true],
            '00000000-0000-4000-8000-000000000102' => ['iot' => true, 'python' => true],
        ];

        $verifiedEvidenceByStudentSkill = [];
        foreach ($rowsByTable['learner_skill_evidence'] ?? [] as $evidence) {
            if ($evidence['verificationStatus'] === 'verified') {
                $verifiedEvidenceByStudentSkill[$evidence['studentSkillId']] = true;
            }
        }

        foreach ($rowsByTable['student_skills'] ?? [] as $studentSkillId => $ss) {
            if ($ss['verificationStatus'] === 'verified') {
                if (!isset($verifiedEvidenceByStudentSkill[$studentSkillId])) {
                    throw new RuntimeException('V2 validation failed: verified student skill lacks verified evidence in ' . $studentSkillId);
                }
                $code = $skillCodeMap[$ss['skillId']] ?? '';
                if ($code !== '') {
                    $effectiveSkills[$ss['studentId']][$code] = true;
                }
            }
        }

        $latestConsentByStudentScope = [];
        foreach ($rowsByTable['learner_ai_consent_events'] ?? [] as $ce) {
            $studentId = (string) $ce['studentId'];
            $scope = (string) $ce['scope'];
            $current = $latestConsentByStudentScope[$studentId][$scope] ?? null;
            if (
                $current === null
                || [(string) $ce['occurredAt'], (string) $ce['requestId']]
                    > [(string) $current['occurredAt'], (string) $current['requestId']]
            ) {
                $latestConsentByStudentScope[$studentId][$scope] = $ce;
            }
        }

        foreach ($participants as $p) {
            $sId = $p['student_id'];
            $seq = $p['sequence'];
            $skills = $effectiveSkills[$sId] ?? [];

            if ($p['expected_state'] === 'ready') {
                if (count($skills) < 2) {
                    throw new RuntimeException('V2 validation failed: ready learner ' . $seq . ' lacks >= 2 effective skills.');
                }
                if (!isset($skills['iot'])) {
                    throw new RuntimeException('V2 validation failed: ready learner ' . $seq . ' lacks verified IoT skill.');
                }
            }
            if ($seq === 104) {
                if (count($skills) !== 1 || !isset($skills['iot'])) {
                    throw new RuntimeException('V2 validation failed: learner 104 must have exactly 1 effective skill (IoT).');
                }
            }
            if ($seq === 108) {
                foreach ($rowsByTable['checkins'] ?? [] as $chk) {
                    $reg = $rowsByTable['activity_registrations'][$chk['registrationId']] ?? [];
                    if ($reg['studentId'] === $sId) {
                        throw new RuntimeException('V2 validation failed: learner 108 must not have confirmed checkin.');
                    }
                }
                foreach ($rowsByTable['experience_logs'] ?? [] as $el) {
                    if ($el['studentId'] === $sId) {
                        throw new RuntimeException('V2 validation failed: learner 108 must not have experience log.');
                    }
                }
            }
            if ($seq === 112) {
                foreach ($rowsByTable['test_attempts'] ?? [] as $ta) {
                    if ($ta['studentId'] === $sId && $ta['submittedAt'] !== self::STALE_SUBMITTED_AT) {
                        throw new RuntimeException('V2 validation failed: learner 112 assessment must be stale at ' . self::STALE_SUBMITTED_AT);
                    }
                }
            }
            if ($seq === 116) {
                foreach ($rowsByTable['assessments'] ?? [] as $ass) {
                    if ($ass['studentId'] === $sId && ($ass['status'] !== 'draft' || $ass['publishedAt'] !== null || $ass['overallScore'] !== null)) {
                        throw new RuntimeException('V2 validation failed: learner 116 evaluation must be draft with null publishedAt and null overallScore.');
                    }
                }
            }
            if ($seq === 120) {
                $evalConsents = [];
                foreach ($rowsByTable['learner_ai_consent_events'] ?? [] as $ce) {
                    if ($ce['studentId'] === $sId && $ce['scope'] === 'evaluation') {
                        $evalConsents[] = $ce;
                    }
                }
                usort(
                    $evalConsents,
                    static fn (array $left, array $right): int => [
                        (string) $left['occurredAt'],
                        (string) $left['requestId'],
                    ] <=> [
                        (string) $right['occurredAt'],
                        (string) $right['requestId'],
                    ]
                );
                if (count($evalConsents) !== 2 || $evalConsents[0]['action'] !== 'granted' || $evalConsents[1]['action'] !== 'revoked' || $evalConsents[1]['occurredAt'] <= $evalConsents[0]['occurredAt']) {
                    throw new RuntimeException('V2 validation failed: learner 120 must have evaluation grant followed by revoke as latest event.');
                }
            }
            if ($seq === 124) {
                foreach ($rowsByTable['learner_ai_consent_events'] ?? [] as $ce) {
                    if ($ce['studentId'] === $sId && $ce['scope'] === 'activity') {
                        throw new RuntimeException('V2 validation failed: learner 124 must lack activity consent.');
                    }
                }
            }
            if ($p['expected_state'] === 'ready') {
                foreach (['assessment', 'skills', 'activity', 'evaluation'] as $reqScope) {
                    $latestConsent = $latestConsentByStudentScope[$sId][$reqScope] ?? null;
                    if ($latestConsent === null || $latestConsent['action'] !== 'granted') {
                        throw new RuntimeException('V2 validation failed: ready learner ' . $seq . ' missing consent grant because latest consent is not granted for ' . $reqScope);
                    }
                }
            }
        }
    }

    private static function id(int $sequence): string
    {
        return self::RESERVED_PREFIX . str_pad((string) $sequence, 12, '0', STR_PAD_LEFT);
    }

    private static function studentId(int $sequence): string
    {
        return self::id($sequence);
    }

    /**
     * @param array<string, scalar|null> $values
     * @return array{table:string, id:string, values:array<string, scalar|null>}
     */
    private static function row(string $table, array $values): array
    {
        $id = (string) ($values['id'] ?? '');
        if (preg_match('/^00000000-0000-4000-8000-[0-9]{12}$/', $id) !== 1) {
            throw new RuntimeException('V2 row id is outside the reserved synthetic namespace.');
        }
        return ['table' => $table, 'id' => $id, 'values' => $values];
    }

    private static function canonicalizeValues(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(self::canonicalizeValues(...), $value);
        }
        ksort($value, SORT_STRING);
        return array_map(self::canonicalizeValues(...), $value);
    }
}
