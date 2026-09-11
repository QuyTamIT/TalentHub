<?php
declare(strict_types=1);

require __DIR__ . '/../bin/bootstrap.php';
require __DIR__ . '/../src/Database/Connection.php';

use TalentHub\Database\Connection;

$config = require __DIR__ . '/../config/database.php';
$conn = new Connection($config);
$pdo = $conn->connect();

echo "=== Test: classes.gradeLevel state ===\n";
$rows = $pdo->query("SELECT c.id, c.name, c.gradeLevel, c.status, s.name AS schoolName, s.level AS schoolLevel
                      FROM classes c JOIN schools s ON s.id = c.schoolId
                      ORDER BY s.name, c.gradeLevel")
    ->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    echo sprintf(
        "  [%s] school=%s | class=%s | gradeLevel=%s | status=%s\n",
        $r['schoolLevel'] ?? '-',
        $r['schoolName'],
        $r['name'],
        $r['gradeLevel'],
        $r['status']
    );
}

echo "\n=== Test: detect tier for each school ===\n";
$rows = $pdo->query("SELECT id, name, level FROM schools")
    ->fetchAll(PDO::FETCH_ASSOC);

$svc = new \TalentHub\Modules\School\Service\SchoolDashboardService(
    new \TalentHub\Modules\School\Repository\SchoolRepository($pdo),
    $pdo
);

foreach ($rows as $r) {
    $tier = $svc->detectSchoolTier($r);
    $opts = $svc->gradeOptionsForSchool($r);
    echo sprintf("  %s (level=%s) → tier=%s, options=%s\n",
        $r['name'], $r['level'] ?? '-', $tier, json_encode($opts));
}

echo "\n=== Test: validateGradeLevel ===\n";
// THCS test
$schoolThcs = ['name' => 'THCS Test', 'level' => 'Trung học Cơ sở Nguyễn Trãi'];
try {
    $r = $svc->validateGradeLevel('7', $schoolThcs);
    echo "  THCS grade=7 → OK ($r)\n";
} catch (Throwable $e) {
    echo "  THCS grade=7 → ERR: " . $e->getMessage() . "\n";
}
try {
    $r = $svc->validateGradeLevel('10', $schoolThcs);
    echo "  THCS grade=10 → should be rejected: got $r\n";
} catch (Throwable $e) {
    echo "  THCS grade=10 → REJECTED (good): " . $e->getMessage() . "\n";
}

// THPT test
$schoolThpt = ['name' => 'THPT Test', 'level' => 'Trung học Phổ thông Nguyễn Huệ'];
try {
    $r = $svc->validateGradeLevel('11', $schoolThpt);
    echo "  THPT grade=11 → OK ($r)\n";
} catch (Throwable $e) {
    echo "  THPT grade=11 → ERR: " . $e->getMessage() . "\n";
}
try {
    $r = $svc->validateGradeLevel('6', $schoolThpt);
    echo "  THPT grade=6 → should be rejected: got $r\n";
} catch (Throwable $e) {
    echo "  THPT grade=6 → REJECTED (good): " . $e->getMessage() . "\n";
}

// College test
$schoolCollege = ['name' => 'BTEC FPT', 'level' => 'Cao đẳng quốc tế'];
try {
    $r = $svc->validateGradeLevel('Năm 1', $schoolCollege);
    echo "  College grade='Năm 1' → OK ($r)\n";
} catch (Throwable $e) {
    echo "  College grade='Năm 1' → ERR: " . $e->getMessage() . "\n";
}
try {
    $r = $svc->validateGradeLevel('K24-CNTT', $schoolCollege);
    echo "  College grade='K24-CNTT' → OK ($r)\n";
} catch (Throwable $e) {
    echo "  College grade='K24-CNTT' → ERR: " . $e->getMessage() . "\n";
}
try {
    $r = $svc->validateGradeLevel('', $schoolCollege);
    echo "  College grade='' → should be rejected: got $r\n";
} catch (Throwable $e) {
    echo "  College grade='' → REJECTED (good): " . $e->getMessage() . "\n";
}

echo "\n=== Test: buildGradeLabel ===\n";
$tests = [
    ['grade' => '6', 'tier' => 'thcs', 'expected' => 'Khối 6'],
    ['grade' => '10', 'tier' => 'thpt', 'expected' => 'Khối 10'],
    ['grade' => 'Năm 1', 'tier' => 'college', 'expected' => 'Năm 1'],
    ['grade' => 'K24-CNTT', 'tier' => 'college', 'expected' => 'K24-CNTT'],
    ['grade' => '10', 'tier' => 'unknown', 'expected' => 'Khối 10'],
    ['grade' => 'Năm 2', 'tier' => 'unknown', 'expected' => 'Năm 2'],
];
foreach ($tests as $t) {
    $r = $svc->buildGradeLabel($t['grade'], $t['tier']);
    $pass = $r === $t['expected'] ? '[OK]' : '[FAIL]';
    echo sprintf("  %s buildGradeLabel(%s, %s) = %s (expected: %s)\n",
        $pass, $t['grade'], $t['tier'], $r, $t['expected']);
}
