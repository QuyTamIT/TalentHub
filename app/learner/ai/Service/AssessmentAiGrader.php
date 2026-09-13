<?php

declare(strict_types=1);

namespace TalentHub\Learner\Ai\Service;

/**
 * Deterministic AI-grade composer for the 4 aptitude tests.
 *
 * Runs at submit time after the algorithmic scorer persisted test_results, producing a
 * per-dimension analysis and an overall summary. The produced payload is stored with
 * gradingStatus = 'ai_graded' so the Teacher can later review / override the scores and
 * promote the attempt to 'teacher_verified' (the official score for School analytics).
 *
 * Best-effort: dimension score values are 0-100 and are expected to already be decoded.
 *
 * @return array{status:string, summary:string, feedback:array<string,string>}
 */
final class AssessmentAiGrader
{
    /**
     * @param string               $testType e.g. holland|mbti|disc|multiple_intelligence
     * @param string               $resultCode
     * @param array<string,int|float> $dimensionScores
     * @param string               $leaderHint Optional primary dimension for phrasing.
     */
    public function grade(string $testType, string $resultCode, array $dimensionScores, string $leaderHint = ''): array
    {
        $typeLabel = $this->testTypeLabel($testType);
        $sorted = $this->ranked($dimensionScores);

        $feedback = [];
        foreach ($sorted as $entry) {
            $code = $entry['code'];
            $value = (int) round((float) $entry['value']);
            $strip = $this->strength($value);
            $feedback[$code] = sprintf(
                '%s ("%s") đạt %d/100 — %s.',
                $this->dimensionLabel($code),
                $strip['band'],
                $value,
                $strip['note']
            );
        }

        $top = array_slice(array_keys($feedback), 0, 3);
        $leader = $leaderHint !== '' ? $leaderHint : (array_key_exists(0, $sorted) ? (string) $sorted[0]['code'] : '');
        $summary = $this->composeSummary($typeLabel, $resultCode, $top, $leader);

        return [
            'status' => 'ai_graded',
            'summary' => $summary,
            'feedback' => $feedback,
        ];
    }

    public function dimensionLabel(string $code): string
    {
        $map = [
            // Holland
            'R' => 'Kỹ thuật (R)', 'I' => 'Nghiên cứu (I)', 'A' => 'Nghệ thuật (A)',
            'S' => 'Xã hội (S)', 'E' => 'Kinh doanh (E)', 'C' => 'Tổ chức (C)',
            // MBTI
            'E' => 'Hướng ngoại (E)', 'I' => 'Hướng nội (I)', 'S' => 'Giác quan (S)',
            'N' => 'Trực giác (N)', 'T' => 'Lý trí (T)', 'F' => 'Cảm xúc (F)',
            'J' => 'Nguyên tắc (J)', 'P' => 'Linh hoạt (P)',
            // DISC
            'D' => 'Thống trị (D)', 'I' => 'Ảnh hưởng (I)', 'S' => 'Kiên định (S)', 'C' => 'Tuân thủ (C)',
            // Multiple intelligence
            'LING' => 'Ngôn ngữ (LING)', 'LOGI' => 'Logic-Toán (LOGI)', 'SPAT' => 'Không gian (SPAT)',
            'MUSIC' => 'Âm nhạc (MUSIC)', 'BODY' => 'Vận động (BODY)', 'INTER' => 'Tương tác (INTER)',
            'INTRA' => 'Nội tâm (INTRA)', 'NAT' => 'Tự nhiên (NAT)',
        ];

        return $map[$code] ?? ('Chiều ' . $code);
    }

    /**
     * @param array<string,int|float> $dimensionScores
     * @return list<array{code:string,value:float}>
     */
    private function ranked(array $dimensionScores): array
    {
        $rows = [];
        foreach ($dimensionScores as $code => $value) {
            $rows[] = ['code' => (string) $code, 'value' => (float) $value];
        }
        usort($rows, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        return $rows;
    }

    /** @return array{band:string,note:string} */
    private function strength(int $value): array
    {
        if ($value >= 80) {
            return ['band' => 'rất nổi trội', 'note' => 'là thế mạnh rõ rệt cần phát huy trong hướng nghiệp'];
        }
        if ($value >= 65) {
            return ['band' => 'khá nổi bật', 'note' => 'cho thấy tiềm năng tốt cần bồi dưỡng thêm'];
        }
        if ($value >= 45) {
            return ['band' => 'trung bình', 'note' => 'đạt mức cơ bản, có thể cải thiện qua luyện tập'];
        }

        return ['band' => 'còn hạn chế', 'note' => 'nên tập trung rèn luyện thêm trong giai đoạn tiếp theo'];
    }

    /** @param list<string> $top */
    private function composeSummary(string $typeLabel, string $resultCode, array $top, string $leader): string
    {
        $lead = $leader !== '' ? $this->dimensionLabel($leader) : $typeLabel;
        $topText = $top !== [] ? implode(', ', $top) : 'các chiều đặc trưng';
        $resultSuffix = '';
        if (trim($resultCode) !== '') {
            $parts = preg_split('/-/', trim($resultCode)) ?: [];
            $joined = implode('-', array_filter($parts, static fn (string $p): bool => $p !== ''));
            if ($joined !== '') {
                $resultSuffix = ' (mã kết quả ' . $joined . ')';
            }
        }

        return sprintf(
            'AI tổng hợp bài %s%s: %s nổi bật ở các chiều %s. Hãy xem nhận xét chi tiết từng chiều để định hướng phát triển phù hợp.',
            $typeLabel,
            $resultSuffix,
            $lead,
            $topText
        );
    }

    private function testTypeLabel(string $testType): string
    {
        return match (strtolower(trim($testType))) {
            'holland' => 'Trắc nghiệm Holland',
            'mbti' => 'Trắc nghiệm MBTI',
            'disc' => 'Trắc nghiệm DISC',
            'multiple_intelligence' => 'Trắc nghiệm Đa trí tuệ',
            default => 'năng lực',
        };
    }
}