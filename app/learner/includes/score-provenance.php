<?php

declare(strict_types=1);

/**
 * TalentHub Learner - Score Provenance Component
 * Answers the 5 core transparency questions:
 * 1. Điểm gì (What score)
 * 2. Lấy từ đâu (Source origin)
 * 3. Ai chấm / công thức nào (Evaluator / Formula)
 * 4. Tính khi nào (Calculated date/time)
 * 5. Còn minh chứng hợp lệ không (Evidence validity)
 */

if (!function_exists('learner_format_score_provenance')) {
    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    function learner_format_score_provenance(array $data): array
    {
        $scoreName = (string) ($data['name'] ?? $data['skill_name'] ?? 'Kỹ năng');
        $scoreVal = $data['score'] ?? $data['levelScore'] ?? $data['level_score'] ?? null;
        $maxScore = $data['max_score'] ?? $data['maxScore'] ?? 100;
        $scoreDisplay = ($scoreVal !== null && is_numeric($scoreVal))
            ? "{$scoreVal}/{$maxScore}"
            : 'Chưa có điểm';

        $sourceType = (string) ($data['source_type'] ?? $data['sourceType'] ?? 'manual');
        $sourceOrigin = match ($sourceType) {
            'teacher' => 'Bản đánh giá chuyên môn từ Giảng viên',
            'project_evaluation', 'project' => 'Báo cáo đồ án thực tế đã nghiệm thu',
            'internship_evaluation', 'internship' => 'Đánh giá kỳ thực tập doanh nghiệp',
            'test', 'assessment' => 'Bài kiểm tra / khảo sát tiêu chuẩn trên hệ thống',
            default => 'Hệ thống hồ sơ năng lực học viên',
        };
        $rawSource = $data['source_origin'] ?? $data['source'] ?? null;
        if (!empty($rawSource)) {
            $sourceOrigin = (string) $rawSource;
        }

        $formula = match ($sourceType) {
            'teacher' => 'Giảng viên chấm theo rubric tiêu chí đánh giá',
            'project_evaluation', 'project' => 'Tổng hợp đánh giá đồ án theo trọng số',
            'internship_evaluation', 'internship' => 'Doanh nghiệp và giảng viên hướng dẫn chấm',
            'test', 'assessment' => 'Công thức chuẩn hóa điểm bài kiểm tra: round(100 × (sum(x) − n) / (4 × n))',
            default => 'Tính trung bình các kỹ năng đã được chấm (skill-mean-1.0)',
        };
        $rawFormula = $data['evaluator_formula'] ?? $data['evaluator'] ?? $data['formula'] ?? null;
        if (!empty($rawFormula)) {
            $formula = (string) $rawFormula;
        }

        $calculatedAt = (string) ($data['calculated_at'] ?? $data['timestamp'] ?? $data['updated_at'] ?? $data['verified_at'] ?? $data['updatedAt'] ?? 'Gần đây');
        $rawEvidence = $data['evidence_validity'] ?? $data['evidence'] ?? null;
        $evidenceState = (string) ($data['evidence_status'] ?? $data['verification_status'] ?? 'verified');
        $evidenceValidity = $rawEvidence !== null ? (string) $rawEvidence : match ($evidenceState) {
            'verified', 'active' => 'Minh chứng hợp lệ (Đã duyệt & bảo trợ)',
            'revoked' => 'Minh chứng đã bị thu hồi / không còn hiệu lực',
            'pending' => 'Minh chứng đang chờ xét duyệt',
            default => 'Không yêu cầu minh chứng bổ sung',
        };

        return [
            'score_name' => "{$scoreName} ({$scoreDisplay})",
            'source_origin' => $sourceOrigin,
            'evaluator_formula' => $formula,
            'calculated_at' => $calculatedAt,
            'evidence_validity' => $evidenceValidity,
            'is_valid' => $evidenceState !== 'revoked',
        ];
    }
}

if (!function_exists('learner_render_score_provenance_box')) {
    /**
     * Render the 5-provenance block as HTML.
     *
     * @param array<string, mixed> $data
     * @return string
     */
    function learner_render_score_provenance_box(array $data): string
    {
        $info = learner_format_score_provenance($data);
        $name = htmlspecialchars($info['score_name'], ENT_QUOTES, 'UTF-8');
        $source = htmlspecialchars($info['source_origin'], ENT_QUOTES, 'UTF-8');
        $formula = htmlspecialchars($info['evaluator_formula'], ENT_QUOTES, 'UTF-8');
        $time = htmlspecialchars($info['calculated_at'], ENT_QUOTES, 'UTF-8');
        $validity = htmlspecialchars($info['evidence_validity'], ENT_QUOTES, 'UTF-8');
        $validClass = $info['is_valid'] ? 'provenance-valid' : 'provenance-invalid';

        return <<<HTML
<div class="score-provenance-box {$validClass}" role="region" aria-label="Nguồn và cách tính điểm">
    <div class="score-provenance-header">
        <strong>Nguồn & Cách tính:</strong> <span>{$name}</span>
    </div>
    <ul class="score-provenance-details">
        <li><strong>1. Nguồn gốc:</strong> {$source}</li>
        <li><strong>2. Phương thức / Người chấm:</strong> {$formula}</li>
        <li><strong>3. Thời gian ghi nhận:</strong> {$time}</li>
        <li><strong>4. Tính hợp lệ minh chứng:</strong> {$validity}</li>
    </ul>
</div>
HTML;
    }

if (isset($provenance) && is_array($provenance)) {
    echo learner_render_score_provenance_box($provenance);
} elseif (isset($provenanceData) && is_array($provenanceData)) {
    echo learner_render_score_provenance_box($provenanceData);
}
}
