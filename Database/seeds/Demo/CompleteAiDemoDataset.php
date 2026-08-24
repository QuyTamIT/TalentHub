<?php

declare(strict_types=1);

namespace TalentHub\Database\Seeds\Demo;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class CompleteAiDemoDataset
{
    private const THPT_STUDENTS = [
        ['student_id' => '20000000-0000-4000-8000-000000000060', 'email' => 'hs.minh@talenthub.vn',  'name' => 'Nguyễn Văn Minh'],
        ['student_id' => '20000000-0000-4000-8000-000000000061', 'email' => 'hs.ha@talenthub.vn',    'name' => 'Trần Thu Hà'],
        ['student_id' => '20000000-0000-4000-8000-000000000062', 'email' => 'hs.nam@talenthub.vn',   'name' => 'Lê Hoàng Nam'],
        ['student_id' => '20000000-0000-4000-8000-000000000063', 'email' => 'hs.lan@talenthub.vn',   'name' => 'Phạm Thị Lan'],
        ['student_id' => '20000000-0000-4000-8000-000000000064', 'email' => 'hs.bao@talenthub.vn',   'name' => 'Đỗ Quốc Bảo'],
        ['student_id' => '20000000-0000-4000-8000-000000000065', 'email' => 'hs.tuyet@talenthub.vn', 'name' => 'Võ Thị Tuyết'],
        ['student_id' => '20000000-0000-4000-8000-000000000066', 'email' => 'hs.khoi@talenthub.vn',  'name' => 'Hoàng Minh Khôi'],
        ['student_id' => '20000000-0000-4000-8000-000000000067', 'email' => 'hs.truc@talenthub.vn',  'name' => 'Phan Thanh Trúc'],
        ['student_id' => '20000000-0000-4000-8000-000000000068', 'email' => 'hs.khanh@talenthub.vn', 'name' => 'Đinh Gia Khánh'],
        ['student_id' => '20000000-0000-4000-8000-000000000069', 'email' => 'hs.linh@talenthub.vn',  'name' => 'Ngô Phương Linh'],
        ['student_id' => '20000000-0000-4000-8000-00000000006a', 'email' => 'hs.duyen@talenthub.vn', 'name' => 'Trương Mỹ Duyên'],
    ];

    private const FPT_STUDENTS = [
        ['key' => 'an',    'name' => 'Nguyễn Hoài An',   'email' => 'sv.fpt.an@talenthub.vn',    'year' => 1, 'dob' => '2007-03-14'],
        ['key' => 'bao',   'name' => 'Trần Gia Bảo',     'email' => 'sv.fpt.bao@talenthub.vn',   'year' => 1, 'dob' => '2007-08-22'],
        ['key' => 'chau',  'name' => 'Lê Minh Châu',     'email' => 'sv.fpt.chau@talenthub.vn',  'year' => 2, 'dob' => '2006-01-30'],
        ['key' => 'duy',   'name' => 'Phạm Đức Duy',     'email' => 'sv.fpt.duy@talenthub.vn',   'year' => 2, 'dob' => '2006-11-09'],
        ['key' => 'linh',  'name' => 'Võ Khánh Linh',    'email' => 'sv.fpt.linh@talenthub.vn',  'year' => 3, 'dob' => '2005-05-17'],
        ['key' => 'minh',  'name' => 'Đỗ Quang Minh',    'email' => 'sv.fpt.minh@talenthub.vn',  'year' => 3, 'dob' => '2005-09-02'],
        ['key' => 'nguyen','name' => 'Bùi Thảo Nguyên',  'email' => 'sv.fpt.nguyen@talenthub.vn','year' => 4, 'dob' => '2004-04-25'],
        ['key' => 'quang', 'name' => 'Hoàng Nhật Quang', 'email' => 'sv.fpt.quang@talenthub.vn', 'year' => 4, 'dob' => '2004-12-11'],
    ];

    private const FPT_TEACHERS = [
        ['key' => 'son',  'name' => 'Nguyễn Minh Sơn', 'email' => 'gv.fpt.son@talenthub.vn',  'specialization' => 'Kỹ thuật phần mềm'],
        ['key' => 'thao', 'name' => 'Trần Thu Thảo',   'email' => 'gv.fpt.thao@talenthub.vn', 'specialization' => 'Trí tuệ nhân tạo'],
        ['key' => 'viet', 'name' => 'Lê Quốc Việt',    'email' => 'gv.fpt.viet@talenthub.vn', 'specialization' => 'Khởi nghiệp'],
        ['key' => 'yen',  'name' => 'Phạm Hải Yến',    'email' => 'gv.fpt.yen@talenthub.vn',  'specialization' => 'Thiết kế trải nghiệm'],
    ];

    private const THPT_ACTIVITIES = [
        ['robotics', 'Dự án Robot cứu hộ', 'career_technical', 'completed', -75, -74],
        ['stem-lab', 'Phòng thí nghiệm STEM mở', 'career_technical', 'ongoing', -1, 1],
        ['young-business', 'Thử thách Doanh nhân trẻ', 'career_business', 'published', 14, 15],
        ['finance', 'Ngày hội Tài chính học đường', 'career_business', 'completed', -60, -60],
        ['design', 'Triển lãm Thiết kế sáng tạo', 'career_arts', 'ongoing', -1, 2],
        ['music', 'Workshop Sáng tác âm nhạc', 'career_arts', 'published', 21, 21],
        ['football', 'Giải bóng đá Nguyễn Trãi', 'career_sports_academic', 'completed', -45, -43],
        ['debate', 'Câu lạc bộ Tranh biện học thuật', 'career_sports_academic', 'ongoing', -2, 2],
        ['science', 'Cuộc thi Nghiên cứu khoa học', 'career_technical', 'published', 30, 31],
        ['volunteer', 'Dự án Tình nguyện cộng đồng', 'career_sports_academic', 'completed', -30, -29],
    ];

    private const FPT_ACTIVITIES = [
        ['hackathon', 'FPTU Hackathon vì cộng đồng', 'career_technical', 'completed', -70, -68],
        ['ai-club', 'Câu lạc bộ Trí tuệ nhân tạo', 'career_technical', 'ongoing', -2, 2],
        ['startup', 'Vườn ươm Khởi nghiệp sinh viên', 'career_business', 'published', 12, 30],
        ['marketing', 'Dự án Digital Marketing thực chiến', 'career_business', 'completed', -55, -50],
        ['ux', 'UX Design Challenge', 'career_arts', 'ongoing', -1, 1],
        ['music-studio', 'FPTU Music Studio Showcase', 'career_arts', 'published', 20, 20],
        ['vovinam', 'Giải Vovinam sinh viên', 'career_sports_academic', 'completed', -40, -39],
        ['research', 'Hội nghị Nghiên cứu sinh viên', 'career_sports_academic', 'published', 35, 36],
    ];

    private const SKILL_CODES = ['python', 'data_analysis', 'communication', 'teamwork', 'leadership', 'creative_design', 'problem_solving', 'entrepreneurship', 'research', 'sports_discipline'];

    public static function uuid(string $owner, string $kind, string $key): string
    {
        $prefix = match ($owner) {
            'thpt' => '21000000',
            'fpt' => '22000000',
            default => throw new InvalidArgumentException('Unknown demo owner.'),
        };
        $hex = substr(hash('sha256', "talenthub-complete-demo-v1\0{$owner}\0{$kind}\0{$key}"), 0, 24);
        return sprintf(
            '%s-%s-4%s-8%s-%s',
            $prefix,
            substr($hex, 0, 4),
            substr($hex, 4, 3),
            substr($hex, 7, 3),
            substr($hex, 10, 12),
        );
    }

    /** @return list<array{student_id:string,email:string,name:string,band:string}> */
    public static function learners(): array
    {
        $result = [];
        foreach (self::THPT_STUDENTS as $row) {
            $result[] = ['student_id' => $row['student_id'], 'email' => $row['email'], 'name' => $row['name'], 'band' => 'high'];
        }
        foreach (self::FPT_STUDENTS as $row) {
            $studentId = self::uuid('fpt', 'student-profile', $row['key']);
            $result[] = ['student_id' => $studentId, 'email' => $row['email'], 'name' => $row['name'], 'band' => 'college'];
        }
        return $result;
    }

    /** @return list<array{key:string,name:string,email:string,specialization:string}> */
    public static function fptTeachers(): array
    {
        return self::FPT_TEACHERS;
    }

    /** @return list<array{key:string,email:string,name:string,year:int,dob:string}> */
    public static function fptStudents(): array
    {
        return self::FPT_STUDENTS;
    }

    /** @return list<array{key:string,title:string,category:string,status:string,start_offset:int,end_offset:int,owner:string}> */
    public static function activities(DateTimeImmutable $clock): array
    {
        $result = [];
        foreach (self::THPT_ACTIVITIES as [$key, $title, $category, $status, $startOff, $endOff]) {
            $result[] = ['key' => $key, 'title' => $title, 'category' => $category, 'status' => $status, 'start_offset' => $startOff, 'end_offset' => $endOff, 'owner' => 'thpt'];
        }
        foreach (self::FPT_ACTIVITIES as [$key, $title, $category, $status, $startOff, $endOff]) {
            $result[] = ['key' => $key, 'title' => $title, 'category' => $category, 'status' => $status, 'start_offset' => $startOff, 'end_offset' => $endOff, 'owner' => 'fpt'];
        }
        return $result;
    }

    /** @return array<string,string> */
    public static function heroStudentIds(): array
    {
        return [
            'high' => '20000000-0000-4000-8000-000000000060',
            'college' => self::uuid('fpt', 'student-profile', 'an'),
        ];
    }

    /** @return array<string,list<string>> studentId => codes */
    public static function assessmentPlan(): array
    {
        $highCodes = ['holland_high', 'mbti_high', 'disc_high', 'multiple_intelligence_high'];
        $collegeCodes = ['holland_college', 'mbti_college', 'disc_college', 'multiple_intelligence_college'];
        $learners = self::learners();
        $heroes = self::heroStudentIds();
        $plan = [];
        foreach ($learners as $idx => $learner) {
            $sid = $learner['student_id'];
            $bandCodes = $learner['band'] === 'high' ? $highCodes : $collegeCodes;
            if ($sid === $heroes['high'] || $sid === $heroes['college']) {
                $plan[$sid] = $bandCodes;
                continue;
            }
            // Every learner gets Holland + one secondary (mod 3 over non-Holland codes)
            $secondary = $bandCodes[1 + ($idx % 3)];
            $plan[$sid] = [$bandCodes[0], $secondary];
            sort($plan[$sid]);
        }
        return $plan;
    }

    /** @return array<string,list<string>> studentId => skill codes */
    public static function skillPlan(): array
    {
        $learners = self::learners();
        $heroes = self::heroStudentIds();
        $plan = [];
        foreach ($learners as $idx => $learner) {
            $count = ($learner['student_id'] === $heroes['high'] || $learner['student_id'] === $heroes['college']) ? 5 : (3 + ($idx % 3));
            $offset = $idx % count(self::SKILL_CODES);
            $codes = [];
            for ($i = 0; $i < $count; $i++) {
                $codes[] = self::SKILL_CODES[($offset + $i) % count(self::SKILL_CODES)];
            }
            $plan[$learner['student_id']] = $codes;
        }
        return $plan;
    }

    /** @return list<array{key:string,activity_key:string,student_id:string,status:string,owner:string}> */
    public static function registrationPlan(): array
    {
        $activities = self::activities(new DateTimeImmutable('2026-08-20 00:00:00.000000', new DateTimeZone('UTC')));
        $thptActivities = array_values(array_filter($activities, static fn (array $a): bool => $a['owner'] === 'thpt'));
        $fptActivities = array_values(array_filter($activities, static fn (array $a): bool => $a['owner'] === 'fpt'));
        $learners = self::learners();
        $thptLearners = array_values(array_filter($learners, static fn (array $l): bool => $l['band'] === 'high'));
        $fptLearners = array_values(array_filter($learners, static fn (array $l): bool => $l['band'] === 'college'));
        $heroes = self::heroStudentIds();

        // Organize activities: completed indices are the attended targets.
        // From plan: robotics/finance/football/volunteer for THPT, hackathon/marketing/vovinam for FPT
        // But we derive completed activities generically.
        $thptCompleted = array_values(array_filter($thptActivities, static fn (array $a): bool => $a['status'] === 'completed'));
        $fptCompleted = array_values(array_filter($fptActivities, static fn (array $a): bool => $a['status'] === 'completed'));

        $result = [];
        foreach ([['thpt', $thptActivities, $thptCompleted, $thptLearners], ['fpt', $fptActivities, $fptCompleted, $fptLearners]] as [$owner, $orgActivities, $completedActivities, $orgLearners]) {
            // Hero gets 2 attended on first 2 completed activities
            $heroId = $owner === 'thpt' ? $heroes['high'] : $heroes['college'];
            // Attended distribution: 10 total per org, split 5/5 across first 2 completed
            // We need exactly 10 attended registrations per org.
            $attendedPairs = [];
            // Hero's 2 attended
            $attendedPairs[] = [$completedActivities[0]['key'], $heroId];
            $attendedPairs[] = [$completedActivities[1]['key'], $heroId];
            // Remaining 8 attended: distribute round-robin over non-hero learners, avoiding duplicates
            $nonHero = array_values(array_filter($orgLearners, static fn (array $l): bool => $l['student_id'] !== $heroId));
            $activityIdx = 0;
            $learnerIdx = 0;
            $used = [];
            foreach ($attendedPairs as [$ak, $sid]) {
                $used[$ak . '|' . $sid] = true;
            }
            $needed = 8;
            // Alternate between the two completed activities
            while ($needed > 0) {
                $targetActivity = $completedActivities[$activityIdx % 2];
                $candidate = $nonHero[$learnerIdx % count($nonHero)];
                $key = $targetActivity['key'] . '|' . $candidate['student_id'];
                if (!isset($used[$key])) {
                    $attendedPairs[] = [$targetActivity['key'], $candidate['student_id']];
                    $used[$key] = true;
                    $needed--;
                }
                $learnerIdx++;
                // Switch activity every time to balance 5/5
                if (count(array_filter($attendedPairs, static fn (array $p): bool => $p[0] === $completedActivities[0]['key'])) >= 5) {
                    $activityIdx = 1;
                } elseif (count(array_filter($attendedPairs, static fn (array $p): bool => $p[0] === $completedActivities[1]['key'])) >= 5) {
                    $activityIdx = 0;
                } else {
                    $activityIdx = ($activityIdx + 1) % 2;
                }
                // Safety: advance learner index to avoid infinite loop
                if ($learnerIdx > 100) {
                    break;
                }
            }
            // Rebalance to exact 5/5 if needed
            $countA0 = count(array_filter($attendedPairs, static fn (array $p): bool => $p[0] === $completedActivities[0]['key']));
            $countA1 = count($attendedPairs) - $countA0;
            // Adjust if not 5/5
            if ($countA0 !== 5 || $countA1 !== 5) {
                // Rebuild deterministically: first 5 on activity 0, next 5 on activity 1
                $allLearnersOrdered = array_merge([['student_id' => $heroId]], $nonHero);
                $attendedPairs = [];
                for ($i = 0; $i < 5; $i++) {
                    $attendedPairs[] = [$completedActivities[0]['key'], $allLearnersOrdered[$i % count($allLearnersOrdered)]['student_id']];
                }
                for ($i = 0; $i < 5; $i++) {
                    // For second activity, shift by 1 to avoid exact duplicates with first set where possible
                    $learner = $allLearnersOrdered[($i + 1) % count($allLearnersOrdered)];
                    // Ensure no duplicate pair
                    $attempts = 0;
                    while (isset($used[$completedActivities[1]['key'] . '|' . $learner['student_id']]) && $attempts < count($allLearnersOrdered)) {
                        $learner = $allLearnersOrdered[($i + 1 + $attempts) % count($allLearnersOrdered)];
                        $attempts++;
                    }
                    $attendedPairs[] = [$completedActivities[1]['key'], $learner['student_id']];
                }
                // Deduplicate by key
                $deduped = [];
                $seen = [];
                foreach ($attendedPairs as $pair) {
                    $k = $pair[0] . '|' . $pair[1];
                    if (!isset($seen[$k])) {
                        $seen[$k] = true;
                        $deduped[] = $pair;
                    }
                }
                $attendedPairs = $deduped;
                // If still not 10, fill remaining
                while (count($attendedPairs) < 10) {
                    foreach ($nonHero as $learner) {
                        foreach ($completedActivities as $act) {
                            $k = $act['key'] . '|' . $learner['student_id'];
                            if (!isset($seen[$k]) && count($attendedPairs) < 10) {
                                $seen[$k] = true;
                                $attendedPairs[] = [$act['key'], $learner['student_id']];
                            }
                        }
                    }
                    break;
                }
            }

            // Approved: 4, Pending: 3, Cancelled: 3
            // Approved/Pending/Cancelled must not duplicate attended pairs and must stay within org.
            $remainingActivities = $orgActivities;
            $usedPairs = [];
            foreach ($attendedPairs as [$ak, $sid]) {
                $usedPairs[$ak . '|' . $sid] = true;
            }
            $otherStatuses = array_merge(
                array_fill(0, 4, 'approved'),
                array_fill(0, 3, 'pending'),
                array_fill(0, 3, 'cancelled'),
            );
            $otherPairs = [];
            $allOrgLearners = $orgLearners;
            usort($remainingActivities, static fn (array $a, array $b): int => $a['key'] <=> $b['key']);
            // Build all free pairs deterministically, then take first N per status.
            $freePairs = [];
            foreach ($remainingActivities as $act) {
                foreach ($allOrgLearners as $learner) {
                    $key = $act['key'] . '|' . $learner['student_id'];
                    if (!isset($usedPairs[$key])) {
                        $freePairs[] = $key;
                    }
                }
            }
            sort($freePairs, SORT_STRING);
            $cursor = 0;
            foreach ($otherStatuses as $status) {
                if ($cursor >= count($freePairs)) {
                    throw new \RuntimeException('Not enough free pairs for status ' . $status);
                }
                $pairKey = $freePairs[$cursor++];
                $otherPairs[$pairKey] = $status;
            }

            // Build final list with keys
            $idx = 0;
            foreach ($attendedPairs as [$activityKey, $studentId]) {
                $result[] = ['key' => $owner . '-attended-' . $idx++, 'activity_key' => $activityKey, 'student_id' => $studentId, 'status' => 'attended', 'owner' => $owner];
            }
            foreach ($otherPairs as $pairKey => $status) {
                [$activityKey, $studentId] = explode('|', $pairKey, 2);
                $result[] = ['key' => $owner . '-' . $status . '-' . $idx++, 'activity_key' => $activityKey, 'student_id' => $studentId, 'status' => $status, 'owner' => $owner];
            }
        }

        // Ensure exactly 40 and sorted deterministically
        usort($result, static fn (array $a, array $b): int => [$a['owner'], $a['status'], $a['activity_key'], $a['student_id']] <=> [$b['owner'], $b['status'], $b['activity_key'], $b['student_id']]);
        // Re-key deterministically
        foreach ($result as $i => &$row) {
            $row['key'] = sprintf('%s-reg-%02d', $row['owner'], $i);
        }
        unset($row);

        return $result;
    }

    /** @return array<string,int> */
    public static function expectedMinimums(): array
    {
        return [
            'learners' => 19,
            'activities' => 18,
            'registrations' => 40,
            'checkins' => 20,
            'experiences' => 20,
            'published_evaluations' => 20,
            'consent_events' => 76,
        ];
    }
}
