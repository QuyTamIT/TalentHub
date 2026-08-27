<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

echo "====================================================================\n";
echo "   ENTERPRISE MULTI-TENANT LOGIN & COMPLETE ISOLATION VERIFICATION\n";
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

function testLoginAndAllPages(string $email, string $password, string $expectedName, string $unexpectedName, array $expectedKeywords = []): void {
    global $baseUrl;

    $cookieFile = sys_get_temp_dir() . '/cookie_' . md5($email) . '.txt';
    if (file_exists($cookieFile)) unlink($cookieFile);

    // 1. GET login page to get CSRF token
    $ch = curl_init("$baseUrl/login.php");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $loginPage = (string) curl_exec($ch);
    curl_close($ch);

    $csrfToken = '';
    if (preg_match('/name="csrfToken" value="([^"]+)"/', $loginPage, $m)) {
        $csrfToken = $m[1];
    }

    // 2. POST login credentials
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
    $response = (string) curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    assertCheck("Login POST HTTP code 200 for $email", $httpCode === 200, "Got HTTP $httpCode");

    $pagesToTest = [
        'Dashboard' => '/app/enterprise/index.php',
        'Profile' => '/app/enterprise/profile.php',
        'Talent Search' => '/app/enterprise/talents.php',
        'Internships' => '/app/enterprise/internships/index.php',
        'Applicants' => '/app/enterprise/internships/applicants.php',
        'Analytics' => '/app/enterprise/analytics.php',
        'Sponsorships' => '/app/enterprise/sponsorships/index.php',
    ];

    foreach ($pagesToTest as $pageName => $pageUrl) {
        $ch = curl_init("$baseUrl$pageUrl");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $html = (string) curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        assertCheck("$pageName for $email loaded HTTP $code", $code === 200, "HTTP code was $code");
        assertCheck("$pageName contains '$expectedName'", str_contains($html, $expectedName), "Did not find expected name");
        assertCheck("$pageName does NOT contain '$unexpectedName'", !str_contains($html, $unexpectedName), "Found unexpected company name");
    }

    if (file_exists($cookieFile)) unlink($cookieFile);
}

echo "1. Testing FPT Software Full Enterprise Portal (fpt@talenthub.local)...\n";
testLoginAndAllPages(
    'fpt@talenthub.local',
    '123456',
    'Công ty TNHH Phần mềm FPT',
    'Công ty Cổ phần Sữa Việt Nam'
);

echo "\n2. Testing Vinamilk Full Enterprise Portal (vinamilk@talenthub.local)...\n";
testLoginAndAllPages(
    'vinamilk@talenthub.local',
    '123456',
    'Công ty Cổ phần Sữa Việt Nam (Vinamilk)',
    'Công ty TNHH Phần mềm FPT'
);

echo "\n====================================================================\n";
if ($allPassed) {
    echo ">>> ALL MULTI-TENANT ISOLATION ASSERTIONS PASSED 100%! <<<\n";
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
echo "====================================================================\n\n";
