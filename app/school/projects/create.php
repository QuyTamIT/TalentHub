<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/bin/bootstrap.php';
require dirname(__DIR__, 3) . '/src/Bootstrap/SchoolAppContext.php';

use TalentHub\Bootstrap\SchoolAppContext;
use TalentHub\Http\ApiException;
use TalentHub\Support\Id\RequestId;

$context = (new SchoolAppContext())->boot();
$service = $context['projects'];
$dashboard = $context['service'];
$session = $context['session'];
$userId = (string) $context['user']['id'];
$error = null;

$form = [
    'title' => '',
    'category' => 'Công nghệ thông tin',
    'topic' => '',
    'mentorTeacherId' => '',
    'authorIds' => [],
    'description' => '',
    'fundingGoal' => '',
    'startAt' => '',
    'endAt' => '',
    'status' => 'draft',
];

$statusLabels = [
    'draft' => 'Bản nháp',
    'in_progress' => 'Đang thực hiện',
    'completed' => 'Đã hoàn thành',
    'archived' => 'Lưu trữ',
];

$categoryOptions = [
    'Công nghệ thông tin',
    'Kinh tế - Quản trị',
    'Kỹ thuật - Robot',
    'Nông nghiệp công nghệ cao',
    'Nghệ thuật & Thiết kế',
    'general',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $session->assertCsrf(isset($_POST['csrfToken']) ? (string) $_POST['csrfToken'] : null);

    $rawFundingGoal = $_POST['fundingGoal'] ?? null;
    $fundingGoal = null;
    if ($rawFundingGoal !== null && trim((string) $rawFundingGoal) !== '') {
        $cleanGoal = preg_replace('/[^\d]/', '', (string) $rawFundingGoal);
        $fundingGoal = $cleanGoal !== '' ? $cleanGoal : null;
    }

    $authorIds = $_POST['authorIds'] ?? [];
    if (!is_array($authorIds)) {
        $authorIds = [];
    }

    $form = [
        'title' => (string) ($_POST['title'] ?? ''),
        'category' => (string) ($_POST['category'] ?? 'general'),
        'topic' => (string) ($_POST['topic'] ?? ''),
        'mentorTeacherId' => (string) ($_POST['mentorTeacherId'] ?? ''),
        'authorIds' => array_values(array_map('strval', $authorIds)),
        'description' => (string) ($_POST['description'] ?? ''),
        'fundingGoal' => (string) ($_POST['fundingGoal'] ?? ''),
        'startAt' => (string) ($_POST['startAt'] ?? ''),
        'endAt' => (string) ($_POST['endAt'] ?? ''),
        'status' => (string) ($_POST['status'] ?? 'draft'),
    ];

    try {
        $startAt = trim($form['startAt']);
        $endAt = trim($form['endAt']);
        if ($startAt !== '' && $endAt !== '' && $endAt < $startAt) {
            throw new ApiException(422, 'VALIDATION_FAILED', 'Ngày kết thúc phải bằng hoặc sau ngày bắt đầu.');
        }

        $created = $service->createProject($userId, [
            'title' => $form['title'],
            'category' => $form['category'] !== '' ? $form['category'] : 'general',
            'topic' => $form['topic'],
            'mentorTeacherId' => $form['mentorTeacherId'] !== '' ? $form['mentorTeacherId'] : null,
            'authorIds' => $form['authorIds'],
            'description' => $form['description'],
            'fundingGoal' => $fundingGoal,
            'startAt' => $startAt !== '' ? $startAt : null,
            'endAt' => $endAt !== '' ? $endAt : null,
            'status' => $form['status'],
        ], RequestId::generate());

        $_SESSION['school_project_flash'] = 'Đã tạo dự án mới thành công.';
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Location: ' . app_href('/app/school/projects/detail.php?id=' . urlencode((string) $created['id'])), true, 303);
        exit;
    } catch (ApiException $exception) {
        $error = $exception->getMessage();
    } catch (Throwable $exception) {
        $error = 'Không thể tạo dự án: ' . $exception->getMessage();
    }
}

$teachers = $dashboard->teachers($userId, 100, 0);
$students = $dashboard->students($userId, 1000, 0);

$schoolInfo = [
    'name' => $context['school']['name'],
    'logo_initials' => mb_substr($context['school']['name'], 0, 2),
    'level' => $context['school']['level'] ?? '',
    'district' => $context['school']['address'] ?? '',
    'academic_year' => $context['school']['academicYear'] ?? '',
];

$currentRoute = '/app/school/projects/';
$pageTitle = 'Tạo dự án mới';
$pageDescription = 'Nhập thông tin dự án, phân công giảng viên hướng dẫn và nhóm tác giả.';
$pageActions = '<a class="btn btn-outline" href="' . htmlspecialchars(app_href('/app/school/projects/'), ENT_QUOTES, 'UTF-8') . '">← Danh sách</a>';

ob_start();
include dirname(__DIR__) . '/includes/page-banner.php';
?>

<?php if ($error): ?>
    <div class="school-flash school-flash--error">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        <?= htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<section class="school-section-box form-section">
    <form method="post" class="project-form" action="">
        <input type="hidden" name="csrfToken" value="<?= htmlspecialchars($session->csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

        <div class="school-form-group">
            <h3 class="school-form-section-title">1. Thông tin chung</h3>
            <div class="school-form__grid">
                <label class="school-form__field school-form__field--full">
                    <span>Tên dự án <span style="color:red">*</span></span>
                    <input name="title" maxlength="255" placeholder="Nhập tên dự án sáng tạo / nghiên cứu..." required value="<?= htmlspecialchars($form['title']); ?>">
                </label>
                <label class="school-form__field">
                    <span>Lĩnh vực / Danh mục</span>
                    <select name="category" class="typeui-select">
                        <?php foreach ($categoryOptions as $opt): ?>
                            <?php $label = $opt === 'general' ? 'Khác (General)' : $opt; ?>
                            <option value="<?= htmlspecialchars($opt); ?>" <?= $form['category'] === $opt ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="school-form__field">
                    <span>Đề tài cụ thể</span>
                    <input name="topic" maxlength="255" placeholder="Ví dụ: Ứng dụng AI vào nông nghiệp..." value="<?= htmlspecialchars($form['topic']); ?>">
                </label>
            </div>
        </div>

        <div class="school-form-group">
            <h3 class="school-form-section-title">2. Nhân sự tham gia</h3>
            <div class="school-form__grid">
                <label class="school-form__field">
                    <span>Giảng viên hướng dẫn</span>
                    <select name="mentorTeacherId" class="school-select-enhanced typeui-select">
                        <option value="">-- Chọn giảng viên --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= htmlspecialchars((string) $teacher['id']); ?>" <?= $form['mentorTeacherId'] === (string) $teacher['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars((string) $teacher['fullName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="school-form__field">
                    <span>Nhóm tác giả (Sinh viên)</span>
                    <div class="school-author-box">
                        <input type="text" id="authorSearch" class="school-author-search-input" placeholder="Tìm mã SV, họ tên, lớp..." oninput="filterAuthorSelect()" autocomplete="off">
                        <select name="authorIds[]" id="authorSelect" multiple size="8" class="school-author-select">
                            <?php foreach ($students as $student): ?>
                                <?php
                                $studentId = (string) $student['id'];
                                $studentCode = 'SV-' . strtoupper(substr($studentId, 0, 8));
                                $selected = in_array($studentId, $form['authorIds'], true);
                                ?>
                                <option value="<?= htmlspecialchars($studentId); ?>"
                                        data-search="<?= htmlspecialchars(strtolower($studentCode . ' ' . (string) $student['fullName'] . ' ' . (string) ($student['className'] ?? ''))); ?>"
                                        <?= $selected ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars("[{$studentCode}] " . (string) $student['fullName'] . ' - ' . (string) ($student['className'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="school-author-hint" id="authorHint">
                            Giữ <kbd>Ctrl</kbd> (Windows) / <kbd>⌘ Cmd</kbd> (Mac) để chọn nhiều sinh viên.
                            <span id="authorCount"></span>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <div class="school-form-group">
            <h3 class="school-form-section-title">3. Triển khai &amp; Kêu gọi tài trợ</h3>
            <div class="school-form__grid">
                <label class="school-form__field">
                    <span>Mục tiêu tài trợ (VND)</span>
                    <div class="school-input-icon">
                        <span class="school-input-icon__prefix">₫</span>
                        <input name="fundingGoal" id="fundingGoalInput" type="text" inputmode="numeric" autocomplete="off" placeholder="VD: 10.000.000" value="<?= htmlspecialchars($form['fundingGoal']); ?>" oninput="handleCurrencyInput(this, event)">
                    </div>
                </label>
                <label class="school-form__field">
                    <span>Trạng thái dự án</span>
                    <select name="status" class="typeui-select typeui-select--status">
                        <?php foreach ($statusLabels as $value => $label): ?>
                            <option value="<?= $value; ?>" <?= $form['status'] === $value ? 'selected' : ''; ?>><?= htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="school-form__field">
                    <span>Ngày bắt đầu</span>
                    <input name="startAt" type="date" value="<?= htmlspecialchars($form['startAt']); ?>">
                </label>
                <label class="school-form__field">
                    <span>Ngày kết thúc dự kiến</span>
                    <input name="endAt" type="date" value="<?= htmlspecialchars($form['endAt']); ?>">
                </label>
                <label class="school-form__field school-form__field--full">
                    <span>Mô tả dự án &amp; Tầm nhìn</span>
                    <textarea name="description" rows="5" maxlength="5000" placeholder="Trình bày ngắn gọn về mục tiêu, giải pháp và giá trị mang lại của dự án..."><?= htmlspecialchars($form['description']); ?></textarea>
                </label>
            </div>
        </div>

        <div class="school-form-actions-bar">
            <a class="btn btn-outline" href="<?= htmlspecialchars(app_href('/app/school/projects/'), ENT_QUOTES, 'UTF-8'); ?>">Hủy</a>
            <button class="btn btn-primary school-btn-lg" type="submit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>
                Tạo dự án
            </button>
        </div>
    </form>
</section>

<?php
$pageBody = ob_get_clean();
$extraStyles = '';
$extraScripts = <<<'HTML'
<script>
function filterAuthorSelect() {
    const input = document.getElementById('authorSearch');
    const select = document.getElementById('authorSelect');
    if (!input || !select) return;
    const filter = input.value.toLowerCase().trim();
    const options = select.querySelectorAll('option');
    let visibleCount = 0;
    options.forEach(opt => {
        const searchText = opt.dataset.search || opt.textContent.toLowerCase();
        if (filter === '' || searchText.includes(filter)) {
            opt.style.display = '';
            visibleCount++;
        } else {
            opt.style.display = 'none';
        }
    });
    const countEl = document.getElementById('authorCount');
    if (countEl) countEl.textContent = ' | Hiển thị ' + visibleCount + '/' + options.length + ' sinh viên';
}

function formatVND(val) {
    if (val === null || val === undefined || val === '') return '';
    const clean = val.toString().replace(/\D/g, '');
    if (!clean) return '';
    return clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function handleCurrencyInput(input, event) {
    if (event && event.isComposing) return;
    const val = input.value;
    const rawDigits = val.replace(/\D/g, '');
    if (!rawDigits) {
        if (input.value !== '') input.value = '';
        return;
    }
    const formatted = formatVND(rawDigits);
    if (val === formatted) return;
    const cursorPos = input.selectionStart || 0;
    const digitsBeforeCursor = val.slice(0, cursorPos).replace(/\D/g, '').length;
    input.value = formatted;
    if (input.setSelectionRange) {
        let newPos = 0;
        let count = 0;
        while (newPos < formatted.length && count < digitsBeforeCursor) {
            if (/\d/.test(formatted[newPos])) count++;
            newPos++;
        }
        input.setSelectionRange(newPos, newPos);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const goalInput = document.getElementById('fundingGoalInput');
    if (goalInput && goalInput.value) goalInput.value = formatVND(goalInput.value);
    document.addEventListener('compositionend', (e) => {
        if (e.target && e.target.id === 'fundingGoalInput') handleCurrencyInput(e.target, e);
    });
    document.addEventListener('blur', (e) => {
        if (e.target && e.target.id === 'fundingGoalInput') handleCurrencyInput(e.target, e);
    }, true);
});
</script>
HTML;
require dirname(__DIR__) . '/includes/layout.php';
