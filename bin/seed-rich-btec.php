<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use TalentHub\Config\Environment;
use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

$config = require dirname(__DIR__) . '/config/database.php';
$pdo = (new Connection($config))->connect();
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

$password = (string) (Environment::optional('TALENTHUB_TEST_PASSWORD') ?: 'TestPassword12');
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$now = gmdate('Y-m-d H:i:s.u');
$only = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--only=')) {
        $only = substr($arg, 7);
    }
}

function richId(string $suffix): string
{
    return sprintf('25000000-0000-4000-8000-%012d', (int) $suffix);
}

function phaseEnabled(?string $only, string $phase): bool
{
    return $only === null || $only === 'all' || $only === $phase;
}

$roles = [];
foreach ($pdo->query('SELECT id, code FROM roles') as $row) {
    $roles[(string) $row['code']] = (string) $row['id'];
}
foreach (['student', 'teacher', 'school', 'enterprise'] as $need) {
    if (!isset($roles[$need])) {
        fwrite(STDERR, "Missing role: {$need}\n");
        exit(1);
    }
}

$school = $pdo->query('SELECT id, name FROM schools WHERE status = \'active\' ORDER BY createdAt ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$school) {
    fwrite(STDERR, "No active school.\n");
    exit(1);
}
$schoolId = (string) $school['id'];
$schoolAdminUserId = (string) $pdo->query(
    "SELECT u.id FROM users u
     INNER JOIN school_members sm ON sm.userId = u.id
     WHERE sm.schoolId = " . $pdo->quote($schoolId) . " AND sm.memberRole IN ('admin','owner','school_admin')
     LIMIT 1"
)->fetchColumn();
if ($schoolAdminUserId === '') {
    $schoolAdminUserId = (string) $pdo->query(
        "SELECT id FROM users WHERE email = 'test.school@talenthub.local' LIMIT 1"
    )->fetchColumn();
}

$existingTeacherId = (string) $pdo->query(
    "SELECT tp.id FROM teacher_profiles tp WHERE tp.schoolId = " . $pdo->quote($schoolId) . " ORDER BY tp.createdAt ASC LIMIT 1"
)->fetchColumn();

echo "======================================================================\n";
echo " SEED RICH BTEC FPT CẦN THƠ\n";
echo " School ID: {$schoolId}\n";
echo " Password : {$password}\n";
echo "======================================================================\n\n";

$classes = [
    ['id' => '496621e6-e62a-47d0-b8a4-8413882108bd', 'name' => 'Công nghệ thông tin K50', 'grade' => 'Năm 2', 'year' => '2024-2028'],
    ['id' => '24000000-0000-4000-8000-00000000d003', 'name' => 'Thiết kế Đồ họa K50', 'grade' => 'Năm 3', 'year' => '2023-2027'],
    ['id' => richId('101'), 'name' => 'Digital Marketing K49', 'grade' => 'Năm 3', 'year' => '2023-2027'],
    ['id' => richId('102'), 'name' => 'Quản trị Kinh doanh K48', 'grade' => 'Năm 4', 'year' => '2022-2026'],
    ['id' => richId('103'), 'name' => 'Khoa học Dữ liệu K50', 'grade' => 'Năm 2', 'year' => '2024-2028'],
    ['id' => richId('104'), 'name' => 'Logistics & Chuỗi cung ứng K49', 'grade' => 'Năm 3', 'year' => '2023-2027'],
];

$teachers = [
    ['key' => 201, 'email' => 'gv.hung.cn@btec.local', 'name' => 'ThS. Nguyễn Văn Hùng', 'phone' => '0901234501', 'spec' => 'Kỹ thuật phần mềm & AI', 'bio' => 'Thạc sĩ KHMT, 10 năm giảng dạy lập trình và AI ứng dụng.'],
    ['key' => 202, 'email' => 'gv.thao.design@btec.local', 'name' => 'ThS. Đỗ Phương Thảo', 'phone' => '0901234505', 'spec' => 'UI/UX & Thiết kế đồ họa', 'bio' => 'Thạc sĩ Thiết kế Tương tác, chuyên Design System và brand identity.'],
    ['key' => 203, 'email' => 'gv.lan.mkt@btec.local', 'name' => 'ThS. Lê Thị Mai Lan', 'phone' => '0901234503', 'spec' => 'Digital Marketing & Content', 'bio' => 'Chuyên gia digital campaign, SEO/SEM và content strategy.'],
    ['key' => 204, 'email' => 'gv.bao.biz@btec.local', 'name' => 'TS. Phạm Quốc Bảo', 'phone' => '0901234504', 'spec' => 'Quản trị kinh doanh & Khởi nghiệp', 'bio' => 'Tiến sĩ QTKD, cố vấn startup và mô hình kinh doanh.'],
    ['key' => 205, 'email' => 'gv.nam.data@btec.local', 'name' => 'TS. Trần Hoàng Nam', 'phone' => '0901234502', 'spec' => 'Khoa học dữ liệu & ML', 'bio' => 'Tiến sĩ Khoa học Dữ liệu, chuyên Big Data và Machine Learning.'],
];

$students = [
    ['n' => 1,  'email' => 'sv.an.cn@btec.local', 'name' => 'Nguyễn Hoài An', 'class' => 0, 'dob' => '2005-03-14', 'phone' => '0911000001', 'track' => 'it', 'headline' => 'Sinh viên CNTT – Fullstack & Cloud', 'skills' => [['python',75],['react',70],['problem_solving',78],['teamwork',80],['communication',72]]],
    ['n' => 2,  'email' => 'sv.bao.cn@btec.local', 'name' => 'Trần Gia Bảo', 'class' => 0, 'dob' => '2005-08-22', 'phone' => '0911000002', 'track' => 'it', 'headline' => 'Sinh viên CNTT – Backend & DevOps', 'skills' => [['python',82],['react',55],['problem_solving',85],['teamwork',70],['software_testing',68]]],
    ['n' => 3,  'email' => 'sv.duy.cn@btec.local', 'name' => 'Phạm Đức Duy', 'class' => 0, 'dob' => '2004-11-09', 'phone' => '0911000003', 'track' => 'it', 'headline' => 'Sinh viên CNTT – Mobile & Product', 'skills' => [['react',78],['problem_solving',74],['communication',76],['teamwork',82],['ui_ux_design',60]]],
    ['n' => 4,  'email' => 'sv.linh.design@btec.local', 'name' => 'Võ Khánh Linh', 'class' => 1, 'dob' => '2004-05-17', 'phone' => '0911000004', 'track' => 'design', 'headline' => 'Thiết kế Đồ họa – Brand & Visual', 'skills' => [['photoshop',88],['illustrator',85],['figma',80],['creative_design',84],['brand_management',70],['communication',75]]],
    ['n' => 5,  'email' => 'sv.minh.design@btec.local', 'name' => 'Đỗ Quang Minh', 'class' => 1, 'dob' => '2004-09-02', 'phone' => '0911000005', 'track' => 'design', 'headline' => 'UI/UX Designer – Product Experience', 'skills' => [['figma',90],['ui_ux_design',88],['creative_design',80],['storytelling',72],['communication',78],['teamwork',76]]],
    ['n' => 6,  'email' => 'sv.nguyen.design@btec.local', 'name' => 'Bùi Thảo Nguyên', 'class' => 1, 'dob' => '2003-04-25', 'phone' => '0911000006', 'track' => 'design', 'headline' => 'Motion & Digital Content Design', 'skills' => [['video_editing',86],['photoshop',78],['content_creator',82],['storytelling',80],['creative_design',75]]],
    ['n' => 7,  'email' => 'sv.ha.mkt@btec.local', 'name' => 'Trần Thu Hà', 'class' => 2, 'dob' => '2004-02-18', 'phone' => '0911000007', 'track' => 'marketing', 'headline' => 'Digital Marketing – Performance Ads', 'skills' => [['digital_marketing',88],['seo',80],['market_analysis',75],['content_creator',78],['communication',85],['presentation_skills',82]]],
    ['n' => 8,  'email' => 'sv.nam.mkt@btec.local', 'name' => 'Lê Hoàng Nam', 'class' => 2, 'dob' => '2004-07-30', 'phone' => '0911000008', 'track' => 'marketing', 'headline' => 'Content & Social Media Strategist', 'skills' => [['content_creator',90],['digital_marketing',82],['storytelling',85],['video_editing',70],['brand_management',74],['communication',88]]],
    ['n' => 9,  'email' => 'sv.lan.mkt@btec.local', 'name' => 'Phạm Thị Lan', 'class' => 2, 'dob' => '2003-12-05', 'phone' => '0911000009', 'track' => 'marketing', 'headline' => 'Brand Marketing & Campaign Planner', 'skills' => [['brand_management',86],['digital_marketing',80],['market_analysis',78],['presentation_skills',84],['teamwork',80]]],
    ['n' => 10, 'email' => 'sv.quang.biz@btec.local', 'name' => 'Hoàng Nhật Quang', 'class' => 3, 'dob' => '2003-12-11', 'phone' => '0911000010', 'track' => 'business', 'headline' => 'QTKD – Khởi nghiệp & Product', 'skills' => [['entrepreneurship',88],['market_analysis',80],['presentation_skills',85],['leadership',78],['communication',82],['excel_advanced',70]]],
    ['n' => 11, 'email' => 'sv.truc.biz@btec.local', 'name' => 'Phan Thanh Trúc', 'class' => 3, 'dob' => '2003-06-21', 'phone' => '0911000011', 'track' => 'business', 'headline' => 'QTKD – Tài chính doanh nghiệp', 'skills' => [['financial_reporting',86],['cost_accounting',82],['excel_advanced',88],['market_analysis',72],['presentation_skills',75]]],
    ['n' => 12, 'email' => 'sv.khanh.biz@btec.local', 'name' => 'Đinh Gia Khánh', 'class' => 3, 'dob' => '2002-10-08', 'phone' => '0911000012', 'track' => 'business', 'headline' => 'QTKD – Vận hành & Quản trị dự án', 'skills' => [['entrepreneurship',75],['teamwork',88],['leadership',84],['ops_analytics',70],['communication',80]]],
    ['n' => 13, 'email' => 'sv.khoi.data@btec.local', 'name' => 'Hoàng Minh Khôi', 'class' => 4, 'dob' => '2005-01-19', 'phone' => '0911000013', 'track' => 'data', 'headline' => 'Data Analyst – Business Intelligence', 'skills' => [['power_bi',88],['excel_advanced',90],['statistical_analysis',82],['python',75],['market_analysis',70],['communication',72]]],
    ['n' => 14, 'email' => 'sv.tuyet.data@btec.local', 'name' => 'Võ Thị Tuyết', 'class' => 4, 'dob' => '2005-04-03', 'phone' => '0911000014', 'track' => 'data', 'headline' => 'Data Science – ML ứng dụng', 'skills' => [['python',90],['statistical_analysis',86],['power_bi',70],['problem_solving',88],['research',78]]],
    ['n' => 15, 'email' => 'sv.linh.data@btec.local', 'name' => 'Ngô Phương Linh', 'class' => 4, 'dob' => '2004-09-27', 'phone' => '0911000015', 'track' => 'data', 'headline' => 'Data Analyst – Marketing Analytics', 'skills' => [['power_bi',84],['tableau',78],['digital_marketing',68],['market_analysis',80],['excel_advanced',85]]],
    ['n' => 16, 'email' => 'sv.duyen.log@btec.local', 'name' => 'Trương Mỹ Duyên', 'class' => 5, 'dob' => '2004-11-15', 'phone' => '0911000016', 'track' => 'logistics', 'headline' => 'Logistics – Warehouse & Fulfillment', 'skills' => [['warehouse_mgmt',88],['order_opt',82],['excel_advanced',80],['ops_analytics',75],['teamwork',84]]],
    ['n' => 17, 'email' => 'sv.bao.log@btec.local', 'name' => 'Đỗ Quốc Bảo', 'class' => 5, 'dob' => '2004-03-08', 'phone' => '0911000017', 'track' => 'logistics', 'headline' => 'Supply Chain Analyst', 'skills' => [['ops_analytics',86],['warehouse_mgmt',78],['order_opt',80],['excel_advanced',84],['problem_solving',82]]],
    ['n' => 18, 'email' => 'sv.ha.log@btec.local', 'name' => 'Nguyễn Minh Hà', 'class' => 5, 'dob' => '2003-08-14', 'phone' => '0911000018', 'track' => 'logistics', 'headline' => 'Logistics Coordinator – Import/Export', 'skills' => [['warehouse_mgmt',80],['communication',86],['toeic_800',78],['order_opt',74],['teamwork',82]]],
    ['n' => 19, 'email' => 'sv.tam.ai@btec.local', 'name' => 'Lê Quý Tam', 'class' => 0, 'dob' => '2005-01-20', 'phone' => '0911000019', 'track' => 'ai', 'headline' => 'AI Engineer – Computer Vision & NLP', 'skills' => [['python',92],['problem_solving',90],['research',85],['statistical_analysis',80],['communication',70],['teamwork',75]]],
    ['n' => 20, 'email' => 'sv.yen.ux@btec.local', 'name' => 'Phạm Hải Yến', 'class' => 1, 'dob' => '2004-06-30', 'phone' => '0911000020', 'track' => 'design', 'headline' => 'Product Designer – UX Research', 'skills' => [['ui_ux_design',90],['figma',88],['storytelling',78],['communication',84],['research',76],['creative_design',82]]],
];

$assessmentProfiles = [
    'it' => ['holland' => ['primary' => 'I', 'secondary' => ['R', 'C']], 'mbti' => ['EI' => 'I', 'SN' => 'N', 'TF' => 'T', 'JP' => 'J'], 'disc' => ['primary' => 'C', 'secondary' => ['D']], 'mi' => ['primary' => 'LOGI', 'secondary' => ['SPAT', 'INTRA']]],
    'design' => ['holland' => ['primary' => 'A', 'secondary' => ['S', 'I']], 'mbti' => ['EI' => 'E', 'SN' => 'N', 'TF' => 'F', 'JP' => 'P'], 'disc' => ['primary' => 'I', 'secondary' => ['S']], 'mi' => ['primary' => 'SPAT', 'secondary' => ['LING', 'INTER']]],
    'marketing' => ['holland' => ['primary' => 'E', 'secondary' => ['A', 'S']], 'mbti' => ['EI' => 'E', 'SN' => 'N', 'TF' => 'F', 'JP' => 'P'], 'disc' => ['primary' => 'I', 'secondary' => ['D']], 'mi' => ['primary' => 'INTER', 'secondary' => ['LING', 'SPAT']]],
    'business' => ['holland' => ['primary' => 'E', 'secondary' => ['C', 'S']], 'mbti' => ['EI' => 'E', 'SN' => 'N', 'TF' => 'T', 'JP' => 'J'], 'disc' => ['primary' => 'D', 'secondary' => ['I']], 'mi' => ['primary' => 'INTER', 'secondary' => ['LOGI', 'LING']]],
    'data' => ['holland' => ['primary' => 'I', 'secondary' => ['C', 'R']], 'mbti' => ['EI' => 'I', 'SN' => 'N', 'TF' => 'T', 'JP' => 'J'], 'disc' => ['primary' => 'C', 'secondary' => ['S']], 'mi' => ['primary' => 'LOGI', 'secondary' => ['SPAT', 'INTRA']]],
    'logistics' => ['holland' => ['primary' => 'C', 'secondary' => ['R', 'E']], 'mbti' => ['EI' => 'E', 'SN' => 'S', 'TF' => 'T', 'JP' => 'J'], 'disc' => ['primary' => 'S', 'secondary' => ['C']], 'mi' => ['primary' => 'BODY', 'secondary' => ['LOGI', 'INTER']]],
    'ai' => ['holland' => ['primary' => 'I', 'secondary' => ['R', 'A']], 'mbti' => ['EI' => 'I', 'SN' => 'N', 'TF' => 'T', 'JP' => 'P'], 'disc' => ['primary' => 'C', 'secondary' => ['I']], 'mi' => ['primary' => 'LOGI', 'secondary' => ['SPAT', 'INTRA']]],
];

$teacherIds = [];
$studentIds = [];
$classIds = array_column($classes, 'id');

try {
    $pdo->beginTransaction();

    if (phaseEnabled($only, 'foundation')) {
        echo "[1] Foundation: school / classes / teachers / students\n";

        $pdo->prepare(
            "UPDATE schools SET name = ?, address = ?, phone = ?, email = ?, website = ?, level = ?,
             academicYear = '2025-2026', status = 'active', verificationStatus = 'verified', updatedAt = ?
             WHERE id = ?"
        )->execute([
            'Cao đẳng Quốc tế BTEC FPT Cần Thơ',
            '160 Nguyễn Văn Cừ nối dài, An Bình, Ninh Kiều, Cần Thơ',
            '0292 7300 558',
            'btec.cantho@talenthub.local',
            'https://btec.fpt.edu.vn',
            'Cao đẳng',
            $now,
            $schoolId,
        ]);
        echo "  School → Cao đẳng Quốc tế BTEC FPT Cần Thơ\n";

        if ($schoolAdminUserId !== '') {
            $pdo->prepare("UPDATE users SET fullName = ?, updatedAt = ? WHERE id = ?")->execute([
                'Ban Giám hiệu BTEC FPT Cần Thơ',
                $now,
                $schoolAdminUserId,
            ]);
        }

        $pdo->exec(
            "DELETE tp FROM teacher_profiles tp
             INNER JOIN users u ON u.id = tp.userId
             INNER JOIN roles r ON r.id = u.roleId
             WHERE tp.schoolId = " . $pdo->quote($schoolId) . " AND r.code = 'student'"
        );

        $upsertClass = $pdo->prepare(
            "INSERT INTO classes (id, schoolId, name, gradeLevel, academicYear, status, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, 'active', ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), gradeLevel = VALUES(gradeLevel),
               academicYear = VALUES(academicYear), status = 'active', updatedAt = VALUES(updatedAt)"
        );
        foreach ($classes as $c) {
            $upsertClass->execute([$c['id'], $schoolId, $c['name'], $c['grade'], $c['year'], $now, $now]);
            echo "  Class: {$c['name']}\n";
        }

        $upsertUser = $pdo->prepare(
            "INSERT INTO users (id, roleId, email, passwordHash, fullName, status, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, 'active', ?, ?)
             ON DUPLICATE KEY UPDATE roleId = VALUES(roleId), passwordHash = VALUES(passwordHash),
               fullName = VALUES(fullName), status = 'active', updatedAt = VALUES(updatedAt)"
        );
        $upsertTeacher = $pdo->prepare(
            "INSERT INTO teacher_profiles (id, userId, schoolId, isSchoolAdmin, phone, specialization, bio, createdAt, updatedAt)
             VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE schoolId = VALUES(schoolId), phone = VALUES(phone),
               specialization = VALUES(specialization), bio = VALUES(bio), updatedAt = VALUES(updatedAt)"
        );
        $upsertMember = $pdo->prepare(
            "INSERT INTO school_members (id, schoolId, userId, memberRole, createdAt, updatedAt)
             VALUES (?, ?, ?, 'member', ?, ?)
             ON DUPLICATE KEY UPDATE schoolId = VALUES(schoolId), memberRole = 'member', updatedAt = VALUES(updatedAt)"
        );

        foreach ($teachers as $i => $t) {
            $userId = richId((string) (2000 + $t['key']));
            $profileId = richId((string) (2100 + $t['key']));
            $memberId = richId((string) (2200 + $t['key']));
            $existing = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $existing->execute([$t['email']]);
            $found = $existing->fetchColumn();
            if (is_string($found) && $found !== '') {
                $userId = $found;
                $oldProfile = $pdo->prepare('SELECT id FROM teacher_profiles WHERE userId = ? LIMIT 1');
                $oldProfile->execute([$userId]);
                $op = $oldProfile->fetchColumn();
                if (is_string($op) && $op !== '') {
                    $profileId = $op;
                }
            }
            $upsertUser->execute([$userId, $roles['teacher'], $t['email'], $passwordHash, $t['name'], $now, $now]);
            $upsertTeacher->execute([$profileId, $userId, $schoolId, $t['phone'], $t['spec'], $t['bio'], $now, $now]);
            $upsertMember->execute([$memberId, $schoolId, $userId, $now, $now]);
            $teacherIds[$i] = $profileId;
            echo "  Teacher: {$t['name']} <{$t['email']}>\n";
        }
        if ($existingTeacherId !== '') {
            $teacherIds['legacy'] = $existingTeacherId;
            $pdo->prepare("UPDATE teacher_profiles SET specialization = ?, bio = ?, updatedAt = ? WHERE id = ?")->execute([
                'Công nghệ thông tin & Lập trình Web',
                'Giảng viên CNTT BTEC FPT Cần Thơ.',
                $now,
                $existingTeacherId,
            ]);
            $pdo->prepare(
                'UPDATE users u INNER JOIN teacher_profiles tp ON tp.userId = u.id SET u.fullName = ?, u.updatedAt = ? WHERE tp.id = ?'
            )->execute(['ThS. Nguyễn Văn Bình', $now, $existingTeacherId]);
        }

        $homeroomMap = [
            $classes[0]['id'] => $teacherIds[0] ?? $existingTeacherId,
            $classes[1]['id'] => $teacherIds[1] ?? $existingTeacherId,
            $classes[2]['id'] => $teacherIds[2] ?? $existingTeacherId,
            $classes[3]['id'] => $teacherIds[3] ?? $existingTeacherId,
            $classes[4]['id'] => $teacherIds[4] ?? $existingTeacherId,
            $classes[5]['id'] => $teacherIds[3] ?? $existingTeacherId,
        ];
        $setHomeroom = $pdo->prepare('UPDATE classes SET homeroomTeacherId = ?, updatedAt = ? WHERE id = ?');
        foreach ($homeroomMap as $cid => $tid) {
            if ($tid) {
                $setHomeroom->execute([$tid, $now, $cid]);
            }
        }

        $findSkill = $pdo->prepare('SELECT id FROM skills WHERE code = ? LIMIT 1');
        $upsertSkill = $pdo->prepare(
            "INSERT INTO skills (id, code, name, category, status, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, 'active', ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category), status = 'active', updatedAt = VALUES(updatedAt)"
        );
        $skillCatalog = [
            'python' => ['Python', 'technical'],
            'react' => ['React', 'technical'],
            'problem_solving' => ['Giải quyết vấn đề', 'technical'],
            'teamwork' => ['Làm việc nhóm', 'soft'],
            'communication' => ['Giao tiếp & Thuyết trình', 'soft'],
            'software_testing' => ['Kiểm thử phần mềm', 'technical'],
            'ui_ux_design' => ['Thiết kế UI/UX', 'creative'],
            'photoshop' => ['Adobe Photoshop', 'creative'],
            'illustrator' => ['Adobe Illustrator', 'creative'],
            'figma' => ['Figma', 'creative'],
            'creative_design' => ['Thiết kế sáng tạo & UI/UX', 'creative'],
            'brand_management' => ['Quản trị thương hiệu', 'business'],
            'video_editing' => ['Video Editing', 'creative'],
            'content_creator' => ['Sáng tạo nội dung', 'creative'],
            'storytelling' => ['Digital Storytelling', 'creative'],
            'digital_marketing' => ['Digital Marketing', 'business'],
            'seo' => ['SEO', 'business'],
            'market_analysis' => ['Phân tích thị trường', 'business'],
            'presentation_skills' => ['Kỹ năng thuyết trình', 'soft'],
            'entrepreneurship' => ['Khởi nghiệp & Quản trị', 'business'],
            'leadership' => ['Lãnh đạo', 'soft'],
            'excel_advanced' => ['Excel nâng cao', 'technical'],
            'financial_reporting' => ['Lập báo cáo tài chính', 'business'],
            'cost_accounting' => ['Kế toán chi phí', 'business'],
            'ops_analytics' => ['Phân tích dữ liệu vận hành', 'technical'],
            'power_bi' => ['Power BI', 'data'],
            'statistical_analysis' => ['Phân tích thống kê', 'data'],
            'tableau' => ['Tableau', 'data'],
            'research' => ['Nghiên cứu khoa học', 'academic'],
            'warehouse_mgmt' => ['Quản lý kho vận', 'business'],
            'order_opt' => ['Tối ưu hóa đơn hàng', 'business'],
            'toeic_800' => ['Tiếng Anh TOEIC 800', 'academic'],
        ];
        $skillIds = [];
        $si = 0;
        foreach ($skillCatalog as $code => [$name, $cat]) {
            $findSkill->execute([$code]);
            $sid = $findSkill->fetchColumn();
            if (!is_string($sid) || $sid === '') {
                $sid = richId((string) (3000 + $si));
                $upsertSkill->execute([$sid, $code, $name, $cat, $now, $now]);
            }
            $skillIds[$code] = $sid;
            $si++;
        }

        $upsertStudent = $pdo->prepare(
            "INSERT INTO student_profiles (id, userId, classId, dateOfBirth, phone, studyStatus, talentScore, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?)
             ON DUPLICATE KEY UPDATE classId = VALUES(classId), dateOfBirth = VALUES(dateOfBirth), phone = VALUES(phone),
               studyStatus = 'active', talentScore = VALUES(talentScore), updatedAt = VALUES(updatedAt)"
        );
        $upsertDetails = $pdo->prepare(
            "INSERT INTO student_profile_details (studentId, location, bio, avatarUrl, headline, createdAt, updatedAt)
             VALUES (?, 'Cần Thơ', ?, NULL, ?, ?, ?)
             ON DUPLICATE KEY UPDATE location = 'Cần Thơ', bio = VALUES(bio), headline = VALUES(headline), updatedAt = VALUES(updatedAt)"
        );
        $delSkills = $pdo->prepare('DELETE FROM student_skills WHERE studentId = ?');
        $insSkill = $pdo->prepare(
            "INSERT INTO student_skills (id, studentId, skillId, levelScore, sourceType, verificationStatus, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, 'self_declared', 'self_declared', ?, ?)"
        );

        foreach ($students as $s) {
            $userId = richId((string) (4000 + $s['n']));
            $studentId = richId((string) (5000 + $s['n']));
            $memberId = richId((string) (6000 + $s['n']));
            $existing = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $existing->execute([$s['email']]);
            $found = $existing->fetchColumn();
            if (is_string($found) && $found !== '') {
                $userId = $found;
                $old = $pdo->prepare('SELECT id FROM student_profiles WHERE userId = ? LIMIT 1');
                $old->execute([$userId]);
                $osp = $old->fetchColumn();
                if (is_string($osp) && $osp !== '') {
                    $studentId = $osp;
                }
            }
            $classId = $classes[$s['class']]['id'];
            $talent = match ($s['track']) {
                'ai' => 92.5,
                'design', 'data' => 88.0,
                'marketing', 'business' => 84.0,
                default => 80.0,
            };
            $upsertUser->execute([$userId, $roles['student'], $s['email'], $passwordHash, $s['name'], $now, $now]);
            $upsertStudent->execute([$studentId, $userId, $classId, $s['dob'], $s['phone'], $talent, $now, $now]);
            $bio = "Sinh viên {$s['headline']} tại Cao đẳng Quốc tế BTEC FPT Cần Thơ. Hồ sơ demo đa ngành để thể hiện tiềm năng matching & AI roadmap.";
            $upsertDetails->execute([$studentId, $bio, $s['headline'], $now, $now]);
            $upsertMember->execute([$memberId, $schoolId, $userId, $now, $now]);
            $delSkills->execute([$studentId]);
            foreach ($s['skills'] as [$code, $level]) {
                if (!isset($skillIds[$code])) {
                    continue;
                }
                $insSkill->execute([Uuid::v4(), $studentId, $skillIds[$code], $level, $now, $now]);
            }
            $studentIds[$s['n']] = ['id' => $studentId, 'userId' => $userId, 'track' => $s['track'], 'email' => $s['email'], 'name' => $s['name'], 'classId' => $classId];
            echo "  Student: {$s['name']} <{$s['email']}> [{$s['track']}]\n";
        }

        $pdo->prepare(
            "UPDATE schools SET studentCount = (SELECT COUNT(*) FROM student_profiles sp INNER JOIN classes c ON c.id = sp.classId WHERE c.schoolId = ?),
             teacherCount = (SELECT COUNT(*) FROM teacher_profiles WHERE schoolId = ?), updatedAt = ? WHERE id = ?"
        )->execute([$schoolId, $schoolId, $now, $schoolId]);

        $pdo->prepare(
            "UPDATE school_certificate_catalog SET issuerName = 'Cao đẳng Quốc tế BTEC FPT Cần Thơ', updatedAt = ? WHERE schoolId = ?"
        )->execute([$now, $schoolId]);
    }

    if (phaseEnabled($only, 'foundation') || $studentIds === []) {
        if ($studentIds === []) {
            foreach ($students as $s) {
                $st = $pdo->prepare(
                    'SELECT sp.id, sp.userId FROM student_profiles sp INNER JOIN users u ON u.id = sp.userId WHERE u.email = ? LIMIT 1'
                );
                $st->execute([$s['email']]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $studentIds[$s['n']] = [
                        'id' => (string) $row['id'],
                        'userId' => (string) $row['userId'],
                        'track' => $s['track'],
                        'email' => $s['email'],
                        'name' => $s['name'],
                        'classId' => $classes[$s['class']]['id'],
                    ];
                }
            }
        }
        if ($teacherIds === []) {
            foreach ($teachers as $i => $t) {
                $tp = $pdo->prepare(
                    'SELECT tp.id FROM teacher_profiles tp INNER JOIN users u ON u.id = tp.userId WHERE u.email = ? LIMIT 1'
                );
                $tp->execute([$t['email']]);
                $id = $tp->fetchColumn();
                if (is_string($id)) {
                    $teacherIds[$i] = $id;
                }
            }
            if ($existingTeacherId !== '') {
                $teacherIds['legacy'] = $existingTeacherId;
            }
        }
    }

    $pdo->commit();
    echo "\n[OK] Foundation committed\n\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, '[FAIL foundation] ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}

require __DIR__ . '/seed-rich-btec-enrichment.php';
