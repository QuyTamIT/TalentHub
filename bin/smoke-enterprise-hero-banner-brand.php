<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

echo "====================================================================\n";
echo "   ENTERPRISE HERO BANNER BRAND IDENTITY VERIFICATION\n";
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

function getDashboardBannerHtml(string $email, string $password): string {
    global $baseUrl;
    $cookieFile = sys_get_temp_dir() . '/cookie_hb_' . md5($email) . '.txt';
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

echo "1. Verifying FPT Software Hero Banner Graphic...\n";
$fptHtml = getDashboardBannerHtml('fpt@talenthub.local', '123456');

assertCheck("FPT Hero Banner has 'ent-hero-banner__graphic--fpt'", str_contains($fptHtml, 'ent-hero-banner__graphic--fpt'));
assertCheck("FPT Hero Banner contains 'GLOBAL IT & AI'", str_contains($fptHtml, 'GLOBAL IT & AI'));
assertCheck("FPT Hero Banner contains 'AI & Cloud'", str_contains($fptHtml, 'AI & Cloud'));
assertCheck("FPT Hero Banner contains 'Top Tech Biz'", str_contains($fptHtml, 'Top Tech Biz'));
assertCheck("FPT Hero Banner does NOT contain Vinamilk branding", !str_contains($fptHtml, 'VIETNAM DAIRY PRODUCTS'));

echo "\n2. Verifying Vinamilk Hero Banner Graphic...\n";
$vnmHtml = getDashboardBannerHtml('vinamilk@talenthub.local', '123456');

assertCheck("Vinamilk Hero Banner has 'ent-hero-banner__graphic--vinamilk'", str_contains($vnmHtml, 'ent-hero-banner__graphic--vinamilk'));
assertCheck("Vinamilk Hero Banner contains 'VINAMILK'", str_contains($vnmHtml, 'VINAMILK'));
assertCheck("Vinamilk Hero Banner contains 'VIETNAM DAIRY PRODUCTS'", str_contains($vnmHtml, 'VIETNAM DAIRY PRODUCTS'));
assertCheck("Vinamilk Hero Banner contains 'FMCG Leader'", str_contains($vnmHtml, 'FMCG Leader'));
assertCheck("Vinamilk Hero Banner does NOT contain FPT Software branding", !str_contains($vnmHtml, 'GLOBAL IT & AI'));

echo "\n====================================================================\n";
if ($allPassed) {
    echo ">>> ALL HERO BANNER BRAND IDENTITY TESTS PASSED 100%! <<<\n";
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
echo "====================================================================\n\n";
