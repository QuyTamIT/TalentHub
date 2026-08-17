<?php
declare(strict_types=1);
namespace TalentHub\Tests\Integration;

use PDO;
use RuntimeException;
use TalentHub\Auth\Repository\AuthRepository;
use TalentHub\Auth\Service\AuthService;
use TalentHub\Auth\Session\SessionManager;
use TalentHub\Bootstrap\Application;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;
use TalentHub\Database\Seeds\Testing\MinimalAuthRbacSeeder;
use TalentHub\Http\ApiException;
use TalentHub\Http\Request;
use TalentHub\Modules\Business\Repository\BusinessRepository;
use TalentHub\Modules\Business\Service\BusinessProfileService;
use TalentHub\Rbac\Service\PermissionService;

final class EnterpriseProfileTest
{
    public function run(PDO $pdo): array
    {
        $results = [];
        $repo = new BusinessRepository($pdo);
        $service = new BusinessProfileService($repo);

        // 1. Fetch business user ID from DB
        $stmt = $pdo->prepare("SELECT u.id, u.email FROM users u JOIN roles r ON r.id = u.roleId WHERE r.code = 'enterprise' LIMIT 1");
        $stmt->execute();
        $businessUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$businessUser) {
            throw new RuntimeException('No business user found in test database.');
        }
        $userId = $businessUser['id'];

        // 2. Test initial profile read
        $profile = $service->get($userId);
        if (empty($profile['id']) || empty($profile['name'])) {
            throw new RuntimeException('Business profile read returned empty profile or name.');
        }
        $results[] = 'Initial profile fetch: OK';

        // 3. Test profile update with full fields
        $updatePayload = [
            'name'        => 'TalentHub Enterprise Solutions',
            'industry'    => 'Công nghệ Thông tin & AI',
            'companySize' => '50 - 200 nhân viên',
            'foundedYear' => 2020,
            'taxCode'     => '0108899776',
            'description' => 'Doanh nghiệp tiên phong ứng dụng trí tuệ nhân tạo trong tuyển dụng và đào tạo tài năng trẻ.',
            'email'       => 'contact@talenthub-solutions.vn',
            'phone'       => '024 3999 8888',
            'website'     => 'https://talenthub-solutions.vn',
            'address'     => 'Tòa nhà Công nghệ, Cầu Giấy, Hà Nội',
            'logoUrl'     => 'https://talenthub-solutions.vn/assets/logo.png',
        ];

        $updated = $service->update($userId, $updatePayload);

        // 4. Verify in-memory return and DB persistence
        if ($updated['name'] !== 'TalentHub Enterprise Solutions') {
            throw new RuntimeException('Updated name does not match expected value.');
        }
        if ($updated['companySize'] !== '50 - 200 nhân viên') {
            throw new RuntimeException('Updated companySize does not match expected value.');
        }
        if ($updated['foundedYear'] !== 2020) {
            throw new RuntimeException('Updated foundedYear does not match expected value.');
        }
        if ($updated['taxCode'] !== '0108899776') {
            throw new RuntimeException('Updated taxCode does not match expected value.');
        }
        if ($updated['email'] !== 'contact@talenthub-solutions.vn') {
            throw new RuntimeException('Updated email does not match expected value.');
        }

        // Direct DB verification
        $directStmt = $pdo->prepare('SELECT name, industry, companySize, foundedYear, taxCode, email, phone, website, address, logoUrl FROM enterprises WHERE id = ?');
        $directStmt->execute([$updated['id']]);
        $dbRow = $directStmt->fetch(PDO::FETCH_ASSOC);

        if ($dbRow['name'] !== 'TalentHub Enterprise Solutions' || (int)$dbRow['foundedYear'] !== 2020 || $dbRow['taxCode'] !== '0108899776') {
            throw new RuntimeException('Direct DB check failed after profile update.');
        }
        $results[] = 'Profile update & DB persistence: OK';

        // 5. Verify dynamic completion calculation
        if ($updated['profileCompletion'] <= 0 || $updated['profileCompletion'] > 100) {
            throw new RuntimeException('Profile completion score out of valid range: ' . $updated['profileCompletion']);
        }
        $results[] = 'Profile completion calculation (' . $updated['profileCompletion'] . '%): OK';

        // 6. Test validation failures
        // 6a. Disallowed field
        try {
            $service->update($userId, ['verificationStatus' => 'verified']);
            throw new RuntimeException('Allowed updating restricted field verificationStatus.');
        } catch (ApiException $e) {
            if ($e->errorCode !== 'VALIDATION_FAILED') {
                throw $e;
            }
        }

        // 6b. Invalid email
        try {
            $service->update($userId, ['email' => 'invalid-email-string']);
            throw new RuntimeException('Allowed invalid email format.');
        } catch (ApiException $e) {
            if ($e->errorCode !== 'VALIDATION_FAILED') {
                throw $e;
            }
        }

        // 6c. Invalid founded year
        try {
            $service->update($userId, ['foundedYear' => 1700]);
            throw new RuntimeException('Allowed out-of-range founded year.');
        } catch (ApiException $e) {
            if ($e->errorCode !== 'VALIDATION_FAILED') {
                throw $e;
            }
        }
        $results[] = 'Validation rejection (restricted fields, invalid email, out-of-range year): OK';

        // 8. Test HTTP API router endpoints (GET & PATCH /api/v1/businesses/me)
        $session = new SessionManager(require dirname(__DIR__, 2) . '/config/session.php');
        $session->start();
        $sessionUser = [
            'id'       => $userId,
            'email'    => $businessUser['email'],
            'fullName' => 'Test Business User',
            'role'     => 'enterprise',
            'status'   => 'active',
        ];
        $session->login($sessionUser);
        $csrfToken = $session->csrfToken();

        $app = new Application();
        $router = $app->buildRouter('req-test-123');

        // Test GET /api/v1/businesses/me
        $getReq = new Request(method: 'GET', path: '/api/v1/businesses/me', headers: [], rawBody: '');
        $getRes = $router->dispatch($getReq);
        if ($getRes->status !== 200 || !isset($getRes->payload['data']['name'])) {
            throw new RuntimeException('GET /api/v1/businesses/me router dispatch failed.');
        }

        // Test PATCH /api/v1/businesses/me
        $patchReq = new Request(
            method: 'PATCH',
            path: '/api/v1/businesses/me',
            headers: ['x-csrf-token' => $csrfToken, 'content-type' => 'application/json'],
            rawBody: json_encode([
                'name'        => 'TalentHub Enterprise Solutions V2',
                'companySize' => '500 - 1000 nhân viên',
                'foundedYear' => 2018,
                'phone'       => '024 3999 9999'
            ], JSON_THROW_ON_ERROR)
        );
        $patchRes = $router->dispatch($patchReq);
        if ($patchRes->status !== 200 || $patchRes->payload['data']['name'] !== 'TalentHub Enterprise Solutions V2' || $patchRes->payload['data']['companySize'] !== '500 - 1000 nhân viên') {
            throw new RuntimeException('PATCH /api/v1/businesses/me router dispatch failed.');
        }
        // Test POST /api/v1/businesses/me/logo
        $logoUploadReq = new Request(
            method: 'POST',
            path: '/api/v1/businesses/me/logo',
            headers: ['x-csrf-token' => $csrfToken, 'content-type' => 'application/json'],
            rawBody: json_encode([
                'dataUrl' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
            ], JSON_THROW_ON_ERROR)
        );
        $logoUploadRes = $router->dispatch($logoUploadReq);
        if ($logoUploadRes->status !== 200 || empty($logoUploadRes->payload['data']['logoUrl'])) {
            throw new RuntimeException('POST /api/v1/businesses/me/logo router dispatch failed.');
        }
        $results[] = 'Logo upload API (POST /api/v1/businesses/me/logo): OK';

        // 9. Restore initial profile data so test execution does not overwrite seeded demo state
        $service->update($userId, [
            'name'        => $profile['name'],
            'industry'    => $profile['industry'] ?? null,
            'companySize' => $profile['companySize'] ?? null,
            'foundedYear' => $profile['foundedYear'] ? (int)$profile['foundedYear'] : null,
            'taxCode'     => $profile['taxCode'] ?? null,
            'description' => $profile['description'] ?? null,
            'email'       => $profile['email'] ?? null,
            'phone'       => $profile['phone'] ?? null,
            'website'     => $profile['website'] ?? null,
            'address'     => $profile['address'] ?? null,
            'logoUrl'     => $profile['logoUrl'] ?? null,
        ]);

        return $results;
    }
}

