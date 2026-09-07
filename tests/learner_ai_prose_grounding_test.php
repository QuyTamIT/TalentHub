<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/learner/ai/bootstrap.php';

use TalentHub\Learner\Ai\Grounding\GroundedProseGuard;

$failures = [];
$assert = static function (bool $ok, string $message) use (&$failures): void { if (!$ok) $failures[] = $message; };
$rejects = static function (callable $fn, string $message) use ($assert): void {
    try { $fn(); $assert(false, $message); } catch (InvalidArgumentException) {}
};
if (!class_exists(GroundedProseGuard::class)) {
    fwrite(STDERR, "FAIL: evidence-aware prose guard is missing\n"); exit(1);
}
$guard = new GroundedProseGuard();
$facts = [
    ['code'=>'sql','label'=>'SQL','current_score'=>45,'target_score'=>75,'is_met'=>false],
    ['code'=>'python','label'=>'Python','current_score'=>85,'target_score'=>70,'is_met'=>true],
];
$rejects(fn () => $guard->assertText('Kỹ năng SQL của bạn đạt 95 điểm và đã vượt yêu cầu 75 điểm.', $facts), 'Reject invented SQL measurements.');
$rejects(fn () => $guard->assertText('SQL của bạn đã vượt ngưỡng yêu cầu của vị trí.', $facts), 'Reject reversed skill attainment without a numeric score.');
$rejects(fn () => $guard->assertText('Bạn đã hoàn thành 12 dự án tại doanh nghiệp trong năm qua.', $facts), 'Reject invented project history.');
$rejects(fn () => $guard->assertText('Bạn từng thực tập tại Công ty ABC và nhận được đánh giá xuất sắc.', $facts), 'Reject invented employers and past evaluations.');
$rejects(fn () => $guard->assertText('Hãy tiếp tục học vì bạn đã hoàn thành 12 dự án.', $facts), 'Advice cannot smuggle an invented past fact.');
$rejects(fn () => $guard->assertText('Bạn có ba năm kinh nghiệm và sở hữu chứng chỉ quốc tế.', $facts), 'Reject unquantified biography and qualifications.');
$rejects(fn () => $guard->assertText('Bạn có thể xem khóa học tại https://fabricated.example/course.', $facts), 'Model prose cannot invent resource links.');
$rejects(fn () => $guard->assertText('SQL đạt 85 điểm.', $facts), 'A number belonging to another skill is not valid evidence.');
$rejects(fn () => $guard->assertText('Điểm SQL của bạn đang đạt mức 95.', $facts), 'Reject score phrased without trailing unit.');
$rejects(fn () => $guard->assertText('SQL hiện ở mức 95/100.', $facts), 'Reject fraction score.');
$rejects(fn () => $guard->assertText('SQL hiện đạt 45.5 điểm.', $facts), 'Do not round fractional invented scores.');
$rejects(fn () => $guard->assertText('SQL cần cải thiện khoảng chênh lệch 15 điểm để đạt yêu cầu 75.', $facts), 'Reject invented numeric gap.');
$rejects(fn () => $guard->assertText('Bạn sẽ được tuyển dụng sau khóa học.', $facts), 'Reject guaranteed outcomes.');
$rejects(fn () => $guard->assertText('Bạn mắc chứng rối loạn chú ý.', $facts), 'Reject diagnostic claims.');
$rejects(fn () => $guard->assertText('Điểm SQL 45 cho thấy bạn đã nắm được các thao tác JOIN nâng cao.', $facts), 'A scalar skill score does not prove mastery of subtopics.');
$rejects(fn () => $guard->assertText('SQL là 95 điểm nhưng bạn nên luyện tập để tiến bộ.', $facts), 'Advice at the end cannot excuse an invented measured fact.');
$rejects(fn () => $guard->assertText('Bạn hiện có 95 điểm SQL.', $facts), 'Score before a skill name must be validated.');
$rejects(fn () => $guard->assertText('SQL đạt yêu cầu nhưng bạn nên luyện tập thêm.', $facts), 'Advice cannot excuse false attainment.');
foreach ([
    'SQL hiện đạt 45 điểm, thấp hơn mức yêu cầu 75 điểm.',
    'Điểm SQL của bạn đang đạt mức 45.',
    'Yêu cầu SQL là 75 điểm trong khi SQL hiện tại là 45 điểm.',
    'SQL hiện tại là 45 điểm, mục tiêu tuần này là 3 bài tập.',
    'Kỹ năng SQL là 45, chưa đạt ngưỡng yêu cầu 75 với khoảng cách còn thiếu là 30 điểm.',
    'Yêu cầu từ tin tuyển dụng đặt ngưỡng mục tiêu cho kỹ năng SQL là 75, đồng nghĩa với việc bạn cần cải thiện khoảng chênh lệch 30 điểm để đáp ứng công việc.',
    'Python đã vượt ngưỡng yêu cầu; SQL còn thấp hơn ngưỡng và cần được củng cố.',
    'Vị trí Python hiện chưa phù hợp hoàn toàn vì tổng mức sẵn sàng còn dưới ngưỡng đề xuất.',
    'Hãy viết 12 truy vấn SQL trong 60 phút; lưu kết quả và tự đối chiếu với yêu cầu của từng bài.',
    'Mục tiêu đề xuất là hoàn thành 2 dự án nhỏ trong 30 ngày, sau đó nhờ giáo viên góp ý.',
    'Chưa có đủ bằng chứng để kết luận về kinh nghiệm làm việc của bạn.',
] as $text) {
    try { $guard->assertText($text, $facts); } catch (Throwable $e) { $assert(false, 'Legitimate guidance rejected: '.$text.' / '.$e->getMessage()); }
}
$rejects(fn () => $guard->assertTree(['strengths'=>[['text'=>'Bạn đã hoàn thành 12 dự án.','evidence_ref_ids'=>['evidence-001']]]], $facts), 'Extended roadmap fields must also be checked.');
if ($failures !== []) { fwrite(STDERR,"FAIL\n- ".implode("\n- ",$failures)."\n"); exit(1); }
echo "learner_ai_prose_grounding_test: OK\n";
