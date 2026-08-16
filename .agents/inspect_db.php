<?php
function test_app_href(string $absolutePath): string {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $appRootFs  = str_replace('\\', '/', dirname(__DIR__));
    $docRootFs  = str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));

    if ($docRootFs !== '' && stripos($appRootFs, $docRootFs) === 0) {
        $sub = substr($appRootFs, strlen($docRootFs));
        $trimmed = trim(str_replace('\\', '/', $sub), '/');
        $basePrefix = $trimmed !== '' ? ('/' . $trimmed) : '';
    } elseif ($scriptName !== '' && stripos($scriptName, '/TalentHub') !== false) {
        $basePrefix = '/TalentHub';
    } elseif (isset($_SERVER['REQUEST_URI']) && stripos((string)$_SERVER['REQUEST_URI'], '/TalentHub') !== false) {
        $basePrefix = '/TalentHub';
    } else {
        $basePrefix = '';
    }

    $path = '/' . ltrim($absolutePath, '/');
    if ($basePrefix !== '' && str_starts_with($path, $basePrefix . '/')) {
        return $path;
    }

    return $basePrefix . $path;
}

$_SERVER['DOCUMENT_ROOT'] = 'C:/laragon/www';
$_SERVER['SCRIPT_NAME'] = '/TalentHub/app/enterprise/talents/detail.php';
$_SERVER['REQUEST_URI'] = '/TalentHub/app/enterprise/talents/detail.php?id=1';

echo "Tổng quan: " . test_app_href('/app/enterprise/index.php') . "\n";
echo "Tìm nhân tài: " . test_app_href('/app/enterprise/talents.php') . "\n";
echo "Tuyển thực tập: " . test_app_href('/app/enterprise/internships/') . "\n";
echo "Tài trợ dự án: " . test_app_href('/app/enterprise/sponsorships/') . "\n";
echo "Phân tích tuyển dụng: " . test_app_href('/app/enterprise/analytics.php') . "\n";
echo "Hồ sơ doanh nghiệp: " . test_app_href('/app/enterprise/profile.php') . "\n";
echo "Candidate detail query: " . test_app_href('/app/enterprise/talents/detail.php?id=1') . "\n";
echo "Role selection: " . test_app_href('/role-selection.php') . "\n";
echo "Logout: " . test_app_href('/logout.php') . "\n";



echo "=== Roles Table ===\n";
try {
    $r = $pdo->query('SELECT * FROM roles')->fetchAll(PDO::FETCH_ASSOC);
    print_r($r);
} catch (Throwable $e) {
    echo "Roles query error: " . $e->getMessage() . "\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS roles (id CHAR(36) NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(500) NULL, isSystem TINYINT UNSIGNED NOT NULL DEFAULT 1, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY(id), UNIQUE KEY uq_roles_code(code), CONSTRAINT chk_roles_is_system CHECK(isSystem IN(0,1))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (id CHAR(36) NOT NULL, code VARCHAR(150) NOT NULL, description VARCHAR(500) NULL, createdAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), updatedAt DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6), PRIMARY KEY(id), UNIQUE KEY uq_permissions_code(code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS role_permissions (roleId CHAR(36) NOT NULL, permissionId CHAR(36) NOT NULL, PRIMARY KEY(roleId,permissionId), KEY idx_role_permissions_perm(permissionId), CONSTRAINT fk_role_permissions_role FOREIGN KEY(roleId) REFERENCES roles(id) ON DELETE CASCADE, CONSTRAINT fk_role_permissions_permission FOREIGN KEY(permissionId) REFERENCES permissions(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Created missing roles/permissions tables!\n";
}

$hasRoleId = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='roleId'")->fetchColumn() === 1;
if (!$hasRoleId) {
    echo "Adding roleId column to users...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN roleId CHAR(36) NULL AFTER id");
    $pdo->exec("UPDATE users u JOIN roles r ON r.code = u.roles SET u.roleId = r.id");
    echo "Added roleId column to users!\n";
}

echo "=== Enterprises Columns ===\n";
print_r($pdo->query('DESCRIBE enterprises')->fetchAll(PDO::FETCH_ASSOC));

$entCols = $pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='enterprises'")->fetchAll(PDO::FETCH_COLUMN);
$neededCols = [
    'companySize' => 'ALTER TABLE enterprises ADD COLUMN companySize VARCHAR(100) NULL AFTER industry',
    'foundedYear' => 'ALTER TABLE enterprises ADD COLUMN foundedYear SMALLINT UNSIGNED NULL AFTER companySize',
    'taxCode'     => 'ALTER TABLE enterprises ADD COLUMN taxCode VARCHAR(50) NULL AFTER website',
    'description' => 'ALTER TABLE enterprises ADD COLUMN description TEXT NULL AFTER foundedYear',
    'email'       => 'ALTER TABLE enterprises ADD COLUMN email VARCHAR(255) NULL AFTER description',
    'phone'       => 'ALTER TABLE enterprises ADD COLUMN phone VARCHAR(30) NULL AFTER email',
    'website'     => 'ALTER TABLE enterprises ADD COLUMN website VARCHAR(500) NULL AFTER phone',
    'address'     => 'ALTER TABLE enterprises ADD COLUMN address VARCHAR(500) NULL AFTER taxCode',
];
foreach ($neededCols as $col => $ddl) {
    if (!in_array($col, $entCols, true)) {
        echo "Adding column {$col} to enterprises...\n";
        $pdo->exec($ddl);
    }
}
echo "=== classes Columns ===\n";
print_r($pdo->query('DESCRIBE classes')->fetchAll(PDO::FETCH_ASSOC));

echo "=== Students in Database ===\n";
$stmt = $pdo->query('SELECT sp.id AS studentProfileId, u.id AS userId, u.fullName, u.email, sp.dateOfBirth, sp.phone, sp.studyStatus, c.name AS className, c.gradeLevel, s.name AS schoolName, s.level AS schoolLevel FROM student_profiles sp JOIN users u ON u.id = sp.userId LEFT JOIN classes c ON c.id = sp.classId LEFT JOIN schools s ON s.id = c.schoolId');
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($students);









