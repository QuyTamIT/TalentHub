<?php
declare(strict_types=1);
namespace TalentHub\Tests\Integration;

use PDO;
use RuntimeException;
use TalentHub\Database\Migration\MigrationRunner;
use TalentHub\Database\Seeds\System\RolePermissionSeeder;
use TalentHub\Database\Seeds\Testing\MinimalAuthRbacSeeder;
use TalentHub\Tests\Support\SchoolApiFixture;

final class SchoolDashboardApiTest
{
    private const CLASS_ID         = '10000000-0000-4000-8000-000000000002';
    private const TEACHER_PROFILE  = '10000000-0000-4000-8000-000000000022';
    private const STUDENT_PROFILE  = '10000000-0000-4000-8000-000000000021';
    private const UNKNOWN_ID       = '99999999-0000-4000-8000-000000000099';

    /** @return list<string> */
    public function run(PDO $pdo, string $database, MigrationRunner $runner, string $password): array
    {
        if (preg_match('/test/i', $database) !== 1) {
            throw new RuntimeException('School dashboard API test requires DB_DATABASE containing test.');
        }
        if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== $database) {
            throw new RuntimeException('Connected database mismatch.');
        }
        if ((int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn() !== 0) {
            throw new RuntimeException('School dashboard API test requires an empty database.');
        }

        $results = [];
        try {
            $runner->migrate();
            (new RolePermissionSeeder())->run($pdo);
            (new MinimalAuthRbacSeeder())->run($pdo, 'test', $password);
            $results[] = 'baseline + fixture: OK';

            $fixture = new SchoolApiFixture($pdo, $password);
            $school = $fixture->loginAsSchool();
            $results[] = 'school login: OK';

            $this->analytics($fixture, $school, $results);
            $this->classesCrud($fixture, $school, $results);
            $this->teachersCrud($fixture, $school, $results);
            $this->studentsCrud($fixture, $school, $results);

            return $results;
        } finally {
            try {
                $runner->rollback(null, 1);
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    /**
     * @param array{userId:string,csrfToken:string} $school
     * @param list<string> $results
     */
    private function analytics(SchoolApiFixture $fixture, array $school, array &$results): void
    {
        $resp = $fixture->call('GET', '/api/v1/schools/me/analytics');
        $this->assertStatus($resp, 200, 'analytics happy path');
        $this->assertTopLevelDataKeys($resp, ['monthly', 'actions', 'totalEvents']);
        $results[] = 'GET /school analytics: OK';

        $fixture->logout();
        $unauth = $fixture->call('GET', '/api/v1/schools/me/analytics');
        $this->assertStatus($unauth, 401, 'analytics unauth');
        $results[] = 'GET /school analytics (no session): 401';

        $fixture->loginAsTeacher();
        $resp = $fixture->call('GET', '/api/v1/schools/me/analytics');
        $this->assertStatus($resp, 403, 'analytics cross-role');
        $results[] = 'GET /school analytics (teacher role): 403';

        $school = $fixture->loginAsSchool();
    }

    /**
     * @param array{userId:string,csrfToken:string} $school
     * @param list<string> $results
     */
    private function classesCrud(SchoolApiFixture $fixture, array $school, array &$results): void
    {
        $existing = $fixture->call('GET', '/api/v1/schools/me/classes');
        $this->assertStatus($existing, 200, 'classes list');
        $results[] = 'GET /school classes: OK';

        $fixture->logout();
        $unauth = $fixture->call('GET', '/api/v1/schools/me/classes');
        $this->assertStatus($unauth, 401, 'classes list unauth');
        $results[] = 'GET /school classes (no session): 401';

        $school = $fixture->loginAsSchool();

        $create = $fixture->call('POST', '/api/v1/schools/me/classes', [
            'name'         => 'Lớp 11A',
            'gradeLevel'   => 11,
            'academicYear' => '2026-2027',
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($create, 201, 'classes create happy');
        $newId = $create['body']['data']['id'] ?? '';
        if (!is_string($newId) || $newId === '') {
            throw new RuntimeException('Create class response missing id: ' . json_encode($create));
        }
        $results[] = 'POST /school classes: OK';

        $noCsrf = $fixture->call('POST', '/api/v1/schools/me/classes', [
            'name'         => 'No CSRF',
            'gradeLevel'   => 11,
            'academicYear' => '2026-2027',
        ]);
        $this->assertStatus($noCsrf, 403, 'classes create no csrf');
        $results[] = 'POST /school classes (no csrf): 403';

        $bad = $fixture->call('POST', '/api/v1/schools/me/classes', [
            'name'         => 'A',
            'gradeLevel'   => 99,
            'academicYear' => '20',
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($bad, 422, 'classes create validation');
        $results[] = 'POST /school classes (validation): 422';

        $rename = $fixture->call('PATCH', '/api/v1/schools/me/classes/' . $newId, [
            'name' => 'Lớp 11A Đổi tên',
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($rename, 200, 'classes update happy');
        $results[] = 'PATCH /school classes/{id}: OK';

        $archive = $fixture->call('POST', '/api/v1/schools/me/classes/' . $newId . '/archive', null, ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($archive, 200, 'classes archive happy');
        $results[] = 'POST /school classes/{id}/archive: OK';

        $unknown = $fixture->call('PATCH', '/api/v1/schools/me/classes/' . self::UNKNOWN_ID, [
            'name' => 'Không tồn tại',
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($unknown, 404, 'classes update not found');
        $results[] = 'PATCH /school classes/{unknown}: 404';
    }

    /**
     * @param array{userId:string,csrfToken:string} $school
     * @param list<string> $results
     */
    private function teachersCrud(SchoolApiFixture $fixture, array $school, array &$results): void
    {
        $list = $fixture->call('GET', '/api/v1/schools/me/teachers');
        $this->assertStatus($list, 200, 'teachers list');
        $results[] = 'GET /school teachers: OK';

        $fixture->logout();
        $unauth = $fixture->call('GET', '/api/v1/schools/me/teachers');
        $this->assertStatus($unauth, 401, 'teachers list unauth');
        $results[] = 'GET /school teachers (no session): 401';

        $school = $fixture->loginAsSchool();

        $invite = $fixture->call('POST', '/api/v1/schools/me/teachers', [
            'email'         => 'newteacher@test.talenthub.local',
            'fullName'      => 'Nguyễn Văn Mời',
            'isSchoolAdmin' => false,
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($invite, 201, 'teachers invite happy');
        $profileId = $invite['body']['data']['profileId'] ?? '';
        if (!is_string($profileId) || $profileId === '') {
            throw new RuntimeException('Invite teacher missing profileId: ' . json_encode($invite));
        }
        $results[] = 'POST /school teachers: OK';

        $noCsrf = $fixture->call('POST', '/api/v1/schools/me/teachers', [
            'email'    => 'csrf@test.talenthub.local',
            'fullName' => 'CSRF',
        ]);
        $this->assertStatus($noCsrf, 403, 'teachers invite no csrf');
        $results[] = 'POST /school teachers (no csrf): 403';

        $duplicate = $fixture->call('POST', '/api/v1/schools/me/teachers', [
            'email'    => 'newteacher@test.talenthub.local',
            'fullName' => 'Trùng email',
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($duplicate, 422, 'teachers invite duplicate');
        $this->assertErrorCode($duplicate, 'EMAIL_ALREADY_EXISTS');
        $results[] = 'POST /school teachers (duplicate email): 422 EMAIL_ALREADY_EXISTS';

        $invalidEmail = $fixture->call('POST', '/api/v1/schools/me/teachers', [
            'email'    => 'not-an-email',
            'fullName' => 'Email xấu',
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($invalidEmail, 422, 'teachers invite invalid email');
        $results[] = 'POST /school teachers (invalid email): 422';

        $admin = $fixture->call('PATCH', '/api/v1/schools/me/teachers/' . $profileId . '/admin', [
            'isSchoolAdmin' => true,
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($admin, 200, 'teachers toggle admin');
        $results[] = 'PATCH /school teachers/{id}/admin: OK';

        $status = $fixture->call('PATCH', '/api/v1/schools/me/teachers/' . $profileId . '/status', [
            'active' => false,
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($status, 200, 'teachers toggle status');
        $results[] = 'PATCH /school teachers/{id}/status: OK';

        $statusBad = $fixture->call('PATCH', '/api/v1/schools/me/teachers/' . self::UNKNOWN_ID . '/status', [
            'active' => true,
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($statusBad, 404, 'teachers toggle status not found');
        $results[] = 'PATCH /school teachers/{unknown}/status: 404';
    }

    /**
     * @param array{userId:string,csrfToken:string} $school
     * @param list<string> $results
     */
    private function studentsCrud(SchoolApiFixture $fixture, array $school, array &$results): void
    {
        $list = $fixture->call('GET', '/api/v1/schools/me/students');
        $this->assertStatus($list, 200, 'students list');
        $results[] = 'GET /school students: OK';

        $fixture->logout();
        $unauth = $fixture->call('GET', '/api/v1/schools/me/students');
        $this->assertStatus($unauth, 401, 'students list unauth');
        $results[] = 'GET /school students (no session): 401';

        $school = $fixture->loginAsSchool();

        $create = $fixture->call('POST', '/api/v1/schools/me/students', [
            'email'       => 'newstudent@test.talenthub.local',
            'fullName'    => 'Trần Học Sinh',
            'classId'     => self::CLASS_ID,
            'dateOfBirth' => '2010-01-15',
            'phone'       => '0900000999',
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($create, 201, 'students create happy');
        $studentId = $create['body']['data']['id'] ?? '';
        if (!is_string($studentId) || $studentId === '') {
            throw new RuntimeException('Create student missing id: ' . json_encode($create));
        }
        $results[] = 'POST /school students: OK';

        $noCsrf = $fixture->call('POST', '/api/v1/schools/me/students', [
            'email'       => 'csrfstudent@test.talenthub.local',
            'fullName'    => 'CSRF',
            'classId'     => self::CLASS_ID,
            'dateOfBirth' => '2010-01-15',
            'phone'       => '0900000999',
        ]);
        $this->assertStatus($noCsrf, 403, 'students create no csrf');
        $results[] = 'POST /school students (no csrf): 403';

        $bad = $fixture->call('POST', '/api/v1/schools/me/students', [
            'email'       => 'bad@test.talenthub.local',
            'fullName'    => 'B',
            'classId'     => 'not-a-uuid',
            'dateOfBirth' => 'yesterday',
            'phone'       => '1',
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($bad, 422, 'students create validation');
        $results[] = 'POST /school students (validation): 422';

        $wrongClass = $fixture->call('POST', '/api/v1/schools/me/students', [
            'email'       => 'wrongclass@test.talenthub.local',
            'fullName'    => 'Lớp sai',
            'classId'     => self::UNKNOWN_ID,
            'dateOfBirth' => '2010-01-15',
            'phone'       => '0900000999',
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($wrongClass, 422, 'students create wrong class');
        $results[] = 'POST /school students (wrong class): 422';

        $update = $fixture->call('PATCH', '/api/v1/schools/me/students/' . $studentId, [
            'phone' => '0900000998',
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($update, 200, 'students update happy');
        $results[] = 'PATCH /school students/{id}: OK';

        $forbidden = $fixture->call('PATCH', '/api/v1/schools/me/students/' . $studentId, [
            'fullName' => 'Không được update',
        ], ['x-csrf-token' => $school['csrfToken']]);
        $this->assertStatus($forbidden, 422, 'students update forbidden field');
        $results[] = 'PATCH /school students/{id} (forbidden field): 422';
    }

    /**
     * @param array{status:int,body:array<string,mixed>} $resp
     */
    private function assertStatus(array $resp, int $expected, string $label): void
    {
        if ($resp['status'] !== $expected) {
            throw new RuntimeException("[{$label}] expected status {$expected}, got {$resp['status']}: " . json_encode($resp['body']));
        }
    }

    /**
     * @param array{status:int,body:array<string,mixed>} $resp
     * @param list<string> $expectedKeys
     */
    private function assertTopLevelDataKeys(array $resp, array $expectedKeys): void
    {
        $data = $resp['body']['data'] ?? null;
        if (!is_array($data)) {
            throw new RuntimeException('Response data is not an object: ' . json_encode($resp['body']));
        }
        foreach ($expectedKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new RuntimeException("Missing data key '{$key}': " . json_encode($data));
            }
        }
    }

    /**
     * @param array{status:int,body:array<string,mixed>} $resp
     */
    private function assertErrorCode(array $resp, string $expected): void
    {
        $code = $resp['body']['error']['code'] ?? null;
        if ($code !== $expected) {
            throw new RuntimeException("Expected error code '{$expected}', got " . json_encode($code));
        }
    }
}
