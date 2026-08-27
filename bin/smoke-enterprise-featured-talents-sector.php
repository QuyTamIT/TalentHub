<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

echo "====================================================================\n";
echo "   SECTOR-AWARE FEATURED TALENTS ON DASHBOARD VERIFICATION\n";
echo "====================================================================\n\n";

$baseUrl = 'http://localhost/TalentHub';
$allPassed = true;

function assertCheck(string $title, bool $condition, string $details = ''): void {
    global $allPassed;
    if ($condition) {
        echo "  [PASS] $title\n";
    } else {
        echo "  [FAIL] $title\n";
        if ($details) echo "         -> $details\n";
        $allPassed = false;
    }
}

function getDashboardHtml(string $email, string $password): string {
    global $baseUrl;
    $cookieFile = sys_get_temp_dir() . '/cookie_ft_' . md5($email) . '.txt';
    if (file_exists($cookieFile)) unlink($cookieFile);

    // 1. GET login.php
    $ch = curl_init("$baseUrl/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $loginPage = (string) curl_exec($ch);
    curl_close($ch);

    preg_match('/name="csrfToken" value="([^"]+)"/', $loginPage, $m);
    $csrfToken = $m[1] ?? '';

    // 2. POST login.php
    $ch = curl_init("$baseUrl/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'email' => $email,
        'password' => $password,
        'csrfToken' => $csrfToken,
    ]));
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    curl_close($ch);

    // 3. GET /app/enterprise/index.php
    $ch = curl_init("$baseUrl/app/enterprise/index.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $dash = (string) curl_exec($ch);
    curl_close($ch);

    if (file_exists($cookieFile)) unlink($cookieFile);
    return $dash;
}

echo "1. Verifying FPT Software Dashboard Featured Talents...\n";
$fptHtml = getDashboardHtml('fpt@talenthub.local', '123456');

assertCheck("FPT Dashboard contains IT student 'Trần Minh Đức'", str_contains($fptHtml, 'Trần Minh Đức'));
assertCheck("FPT Dashboard contains IT student 'Võ Đức Anh'", str_contains($fptHtml, 'Võ Đức Anh'));
assertCheck("FPT Dashboard contains IT student 'Lê Hoàng Nam'", str_contains($fptHtml, 'Lê Hoàng Nam'));
assertCheck("FPT Dashboard contains IT student 'Nguyễn Văn An'", str_contains($fptHtml, 'Nguyễn Văn An'));
assertCheck("FPT Dashboard does NOT contain Economic student 'Lê Hoàng Yến Nhi'", !str_contains($fptHtml, 'Lê Hoàng Yến Nhi'));
assertCheck("FPT Dashboard does NOT contain Economic student 'Hoàng Thị Mai Linh'", !str_contains($fptHtml, 'Hoàng Thị Mai Linh'));

echo "\n2. Verifying Vinamilk Dashboard Featured Talents...\n";
$vnmHtml = getDashboardHtml('vinamilk@talenthub.local', '123456');

assertCheck("Vinamilk Dashboard contains Business student 'Lê Hoàng Yến Nhi'", str_contains($vnmHtml, 'Lê Hoàng Yến Nhi'));
assertCheck("Vinamilk Dashboard contains Business student 'Hoàng Thị Mai Linh'", str_contains($vnmHtml, 'Hoàng Thị Mai Linh'));
assertCheck("Vinamilk Dashboard contains Marketing student 'Phạm Quốc Bảo'", str_contains($vnmHtml, 'Phạm Quốc Bảo'));
assertCheck("Vinamilk Dashboard does NOT contain IT student 'Trần Minh Đức'", !str_contains($vnmHtml, 'Trần Minh Đức'));
assertCheck("Vinamilk Dashboard does NOT contain IT student 'Võ Đức Anh'", !str_contains($vnmHtml, 'Võ Đức Anh'));

echo "\n====================================================================\n";
if ($allPassed) {
    echo ">>> ALL SECTOR-AWARE FEATURED TALENTS ASSERTIONS PASSED 100%! <<<\n";
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
echo "====================================================================\n\n";
