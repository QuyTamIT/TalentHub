<?php
declare(strict_types=1);

namespace TalentHub\Modules\Employer;

use PDO;
use TalentHub\Database\Connection;
use TalentHub\Support\Uuid;

/**
 * TalentHub - Employer Candidate Service
 * Handles candidate talent queries, multi-criteria filtering, and profile retrieval for employers.
 */
class CandidateService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo !== null) {
            $this->pdo = $pdo;
        } else {
            $config = require dirname(__DIR__, 3) . '/config/database.php';
            $this->pdo = (new Connection($config))->connect();
        }
    }

    /**
     * Search and list candidates with flexible, dynamic filtering.
     *
     * @param array{
     *   education_level?: string,
     *   school_id?: string,
     *   school?: string,
     *   skill_tag?: string,
     *   skill?: string,
     *   search?: string,
     *   keyword?: string,
     *   q?: string,
     *   sort?: string,
     *   limit?: int,
     *   offset?: int
     * } $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function searchCandidates(array $filters = []): array
    {
        $conditions = ["u.status = 'active'"];
        $params = [];

        // 1. Filter: Education Level / Bậc học (THPT, THCS, Cao đẳng, Đại học)
        $educationLevel = trim((string) ($filters['education_level'] ?? $filters['educationLevel'] ?? $filters['level'] ?? ''));
        if (!empty($educationLevel) && $educationLevel !== 'all') {
            if (strcasecmp($educationLevel, 'THPT') === 0 || stripos($educationLevel, 'Phổ thông') !== false) {
                $conditions[] = "(s.level LIKE '%Trung học Phổ thông%' OR s.level LIKE '%THPT%' OR s.name LIKE '%THPT%' OR c.name REGEXP '^(10|11|12)[A-Za-z0-9_-]*')";
            } elseif (strcasecmp($educationLevel, 'THCS') === 0 || stripos($educationLevel, 'Cơ sở') !== false) {
                $conditions[] = "(s.level LIKE '%Trung học Cơ sở%' OR s.level LIKE '%THCS%' OR s.name LIKE '%THCS%' OR c.name REGEXP '^(6|7|8|9)[A-Za-z0-9_-]*')";
            } elseif (strcasecmp($educationLevel, 'Cao đẳng') === 0 || stripos($educationLevel, 'Cao đẳng') !== false) {
                $conditions[] = "(s.level LIKE '%Cao đẳng%' OR s.name LIKE '%Cao đẳng%' OR s.name LIKE '%BTEC%')";
            } elseif (strcasecmp($educationLevel, 'Đại học') === 0 || stripos($educationLevel, 'Đại học') !== false) {
                $conditions[] = "(s.level LIKE '%Đại học%' OR s.name LIKE '%Đại học%')";
            } else {
                $conditions[] = "(s.level LIKE :edu1 OR sp.studyStatus LIKE :edu2 OR c.name LIKE :edu3 OR s.name LIKE :edu4)";
                $params['edu1'] = '%' . $educationLevel . '%';
                $params['edu2'] = '%' . $educationLevel . '%';
                $params['edu3'] = '%' . $educationLevel . '%';
                $params['edu4'] = '%' . $educationLevel . '%';
            }
        }

        // 2. Filter: School (ID or Name)
        $schoolId = trim((string) ($filters['school_id'] ?? $filters['school'] ?? ''));
        if (!empty($schoolId) && $schoolId !== 'all') {
            if (Uuid::isValid($schoolId)) {
                $conditions[] = "s.id = :school_id";
                $params['school_id'] = $schoolId;
            } else {
                $conditions[] = "(s.id = :school_id OR s.name LIKE :school_name)";
                $params['school_id'] = $schoolId;
                $params['school_name'] = '%' . $schoolId . '%';
            }
        }

        // 3. Filter: Skill Tag
        $skillTag = trim((string) ($filters['skill_tag'] ?? $filters['skill'] ?? ''));
        if (!empty($skillTag) && $skillTag !== 'all') {
            $conditions[] = "EXISTS (
                SELECT 1 FROM student_skills ss
                JOIN skills ON ss.skillId = skills.id
                WHERE ss.studentId = sp.id
                  AND (skills.name LIKE :skill OR skills.code LIKE :skillCode)
            )";
            $params['skill'] = '%' . $skillTag . '%';
            $params['skillCode'] = '%' . $skillTag . '%';
        }

        // 4. Filter: Search Query (Name, Headline, Bio, School, Class, Skills)
        $search = trim((string) ($filters['search'] ?? $filters['keyword'] ?? $filters['q'] ?? ''));
        if (!empty($search) && $search !== 'all') {
            $searchWildcard = '%' . $search . '%';
            $conditions[] = "(
                u.fullName LIKE :q1
                OR spd.headline LIKE :q2
                OR spd.bio LIKE :q3
                OR s.name LIKE :q4
                OR c.name LIKE :q5
                OR EXISTS (
                    SELECT 1 FROM student_skills ssk
                    JOIN skills skk ON ssk.skillId = skk.id
                    WHERE ssk.studentId = sp.id
                      AND (skk.name LIKE :qSkill OR skk.code LIKE :qSkillCode)
                )
            )";
            $params['q1'] = $searchWildcard;
            $params['q2'] = $searchWildcard;
            $params['q3'] = $searchWildcard;
            $params['q4'] = $searchWildcard;
            $params['q5'] = $searchWildcard;
            $params['qSkill'] = $searchWildcard;
            $params['qSkillCode'] = $searchWildcard;
        }

        // 5. Filter: Major / Domain / Lĩnh vực năng lực
        $majorField = trim((string) ($filters['major_field'] ?? $filters['field'] ?? $filters['major'] ?? $filters['domain'] ?? ''));
        if (!empty($majorField) && $majorField !== 'all') {
            if (stripos($majorField, 'AI') !== false || stripos($majorField, 'dữ liệu') !== false || stripos($majorField, 'Data') !== false || stripos($majorField, 'Trí tuệ Nhân tạo') !== false) {
                $conditions[] = "(
                    spd.headline LIKE '%AI%'
                    OR spd.headline LIKE '%Trí tuệ Nhân tạo%'
                    OR spd.headline LIKE '%Data%'
                    OR spd.headline LIKE '%Machine Learning%'
                    OR spd.bio LIKE '%AI%'
                    OR spd.bio LIKE '%Trí tuệ Nhân tạo%'
                    OR c.name LIKE '%AI%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = sp.id
                          AND skills.name IN ('Python', 'Machine Learning', 'AI / Machine Learning', 'PyTorch', 'Computer Vision', 'Phân tích dữ liệu', 'LangChain', 'Prompt Engineering')
                    )
                )";
            } elseif (stripos($majorField, 'Marketing') !== false || stripos($majorField, 'Kinh doanh') !== false || stripos($majorField, 'QTKD') !== false || stripos($majorField, 'TMĐT') !== false) {
                $conditions[] = "(
                    spd.headline LIKE '%Marketing%'
                    OR spd.headline LIKE '%Kinh doanh%'
                    OR spd.headline LIKE '%Quản trị%'
                    OR spd.bio LIKE '%Marketing%'
                    OR spd.bio LIKE '%Kinh doanh%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = sp.id
                          AND skills.name IN ('Digital Marketing', 'Sáng tạo nội dung', 'Nghiên cứu thị trường', 'SEO', 'Google Analytics', 'Khởi nghiệp & Quản trị', 'Quản trị Kinh doanh')
                    )
                )";
            } elseif (stripos($majorField, 'Logistics') !== false || stripos($majorField, 'kho vận') !== false || stripos($majorField, 'cung ứng') !== false) {
                $conditions[] = "(
                    spd.headline LIKE '%Logistics%'
                    OR spd.headline LIKE '%Kho vận%'
                    OR spd.headline LIKE '%Chuỗi cung ứng%'
                    OR spd.bio LIKE '%Logistics%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = sp.id
                          AND skills.name IN ('Quản trị kho vận', 'Logistics', 'Tối ưu hóa đơn hàng', 'Phân tích dữ liệu vận hành')
                    )
                )";
            } elseif (stripos($majorField, 'Tài chính') !== false || stripos($majorField, 'Kế toán') !== false || stripos($majorField, 'Ngân hàng') !== false) {
                $conditions[] = "(
                    spd.headline LIKE '%Tài chính%'
                    OR spd.headline LIKE '%Kế toán%'
                    OR spd.headline LIKE '%Ngân hàng%'
                    OR spd.bio LIKE '%Tài chính%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = sp.id
                          AND skills.name IN ('Tài chính', 'Kế toán', 'PowerBI', 'Excel nâng cao')
                    )
                )";
            } elseif (stripos($majorField, 'An toàn') !== false || stripos($majorField, 'Security') !== false || stripos($majorField, 'Bảo mật') !== false) {
                $conditions[] = "(
                    spd.headline LIKE '%An toàn%'
                    OR spd.headline LIKE '%Security%'
                    OR spd.headline LIKE '%Bảo mật%'
                    OR spd.bio LIKE '%Security%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = sp.id
                          AND skills.name IN ('An toàn thông tin', 'Cyber Security', 'Network Security')
                    )
                )";
            } elseif (stripos($majorField, 'Công nghệ') !== false || stripos($majorField, 'Phần mềm') !== false || stripos($majorField, 'Web') !== false || stripos($majorField, 'Lập trình') !== false) {
                $conditions[] = "(
                    spd.headline LIKE '%Công nghệ%'
                    OR spd.headline LIKE '%Phần mềm%'
                    OR spd.headline LIKE '%Lập trình%'
                    OR spd.headline LIKE '%Web%'
                    OR spd.headline LIKE '%AI%'
                    OR spd.bio LIKE '%Công nghệ%'
                    OR c.name LIKE '%BTEC%'
                    OR EXISTS (
                        SELECT 1 FROM student_skills ss
                        JOIN skills ON ss.skillId = skills.id
                        WHERE ss.studentId = sp.id
                          AND skills.name IN ('React', 'Node.js', 'Python', 'TypeScript', 'JavaScript', 'HTML', 'CSS', 'Java', 'PHP', 'Docker', 'Git', 'REST API', 'MySQL', 'AI / Machine Learning')
                    )
                )";
            } else {
                $conditions[] = "(spd.headline LIKE :maj1 OR spd.bio LIKE :maj2 OR c.name LIKE :maj3)";
                $params['maj1'] = '%' . $majorField . '%';
                $params['maj2'] = '%' . $majorField . '%';
                $params['maj3'] = '%' . $majorField . '%';
            }
        }

        $whereSql = implode(' AND ', $conditions);

        $sql = <<<SQL
            SELECT
                sp.id AS studentId,
                u.id AS userId,
                u.fullName AS displayName,
                u.email,
                sp.phone,
                s.id AS schoolId,
                s.name AS schoolName,
                c.id AS classId,
                c.name AS className,
                sp.studyStatus,
                spd.location,
                spd.headline,
                spd.bio,
                spd.avatarUrl,
                COALESCE(
                    sp.talentScore,
                    (SELECT ROUND(AVG(ss.levelScore), 0) FROM student_skills ss WHERE ss.studentId = sp.id AND ss.levelScore > 0),
                    85
                ) AS talentScore,
                COUNT(DISTINCT studentSkill.id) AS skillCount,
                COUNT(DISTINCT CASE WHEN studentSkill.verificationStatus = 'verified' THEN studentSkill.id END) AS verifiedSkillCount
            FROM student_profiles sp
            INNER JOIN users u ON u.id = sp.userId
            LEFT JOIN classes c ON c.id = sp.classId
            LEFT JOIN schools s ON s.id = c.schoolId
            LEFT JOIN student_profile_details spd ON spd.studentId = sp.id
            LEFT JOIN student_skills studentSkill ON studentSkill.studentId = sp.id
            WHERE {$whereSql}
            GROUP BY sp.id, u.id, u.fullName, u.email, sp.phone, s.id, s.name, c.id, c.name, sp.studyStatus,
                     spd.location, spd.headline, spd.bio, spd.avatarUrl
            ORDER BY talentScore DESC, verifiedSkillCount DESC, sp.createdAt DESC
        SQL;

        $limitClause = '';
        if (isset($filters['limit']) && is_numeric($filters['limit']) && (int) $filters['limit'] > 0) {
            $limit = (int) $filters['limit'];
            $offset = isset($filters['offset']) && is_numeric($filters['offset']) ? max(0, (int) $filters['offset']) : 0;
            $limitClause = " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $this->pdo->prepare("{$sql}{$limitClause}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $stId = (string) $row['studentId'];
            $skillsStmt = $this->pdo->prepare("
                SELECT s.name
                FROM student_skills ss
                JOIN skills s ON s.id = ss.skillId
                WHERE ss.studentId = ?
                ORDER BY ss.levelScore DESC, s.name ASC
            ");
            $skillsStmt->execute([$stId]);
            $skills = $skillsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            $schoolName = (string) ($row['schoolName'] ?? '');
            $className = (string) ($row['className'] ?? '');
            $eduLevel = 'Sinh viên';
            if (stripos($schoolName, 'THPT') !== false || preg_match('/^(10|11|12)/', $className)) {
                $eduLevel = 'THPT';
            } elseif (stripos($schoolName, 'THCS') !== false || preg_match('/^(6|7|8|9)/', $className)) {
                $eduLevel = 'THCS';
            } elseif (stripos($schoolName, 'Cao đẳng') !== false || stripos($schoolName, 'BTEC') !== false) {
                $eduLevel = 'Cao đẳng';
            } elseif (stripos($schoolName, 'Đại học') !== false) {
                $eduLevel = 'Đại học';
            }

            $score = (int) round((float) $row['talentScore']);

            $items[] = [
                'id' => $stId,
                'studentId' => $stId,
                'userId' => (string) $row['userId'],
                'name' => (string) $row['displayName'],
                'displayName' => (string) $row['displayName'],
                'school' => $schoolName,
                'schoolName' => $schoolName,
                'class' => $className,
                'className' => $className,
                'education_level' => $eduLevel,
                'location' => (string) ($row['location'] ?? 'Hà Nội'),
                'headline' => (string) ($row['headline'] ?? ''),
                'bio' => (string) ($row['bio'] ?? ''),
                'talentScore' => min(100, max(60, $score)),
                'skills' => $skills,
                'verifiedSkills' => $skills,
                'skillCount' => count($skills),
                'internship_status_label' => 'Sẵn sàng thực tập',
            ];
        }

        return [
            'items' => $items,
            'total' => count($items),
        ];
    }

    /**
     * Alias for searchCandidates.
     */
    public function listCandidates(array $filters = []): array
    {
        return $this->searchCandidates($filters);
    }

    /**
     * Get candidate detail by student profile ID or user ID.
     */
    public function getCandidateById(string $studentId): ?array
    {
        $res = $this->searchCandidates(['search' => $studentId]);
        if (!empty($res['items'])) {
            return $res['items'][0];
        }
        $stmt = $this->pdo->prepare("SELECT id FROM student_profiles WHERE id = ? OR userId = ? LIMIT 1");
        $stmt->execute([$studentId, $studentId]);
        $resolvedId = $stmt->fetchColumn();
        if ($resolvedId) {
            $all = $this->searchCandidates();
            foreach ($all['items'] as $item) {
                if ($item['id'] === $resolvedId) {
                    return $item;
                }
            }
        }
        return null;
    }

    /**
     * Check if a student already has an active application / offer at the specified position / enterprise.
     *
     * @param string $studentId Student profile ID or user ID
     * @param string $jobPositionId Internship post ID (or job position ID)
     * @param string|null $companyId Enterprise ID (optional)
     * @return array<string, mixed>|null Returns existing active application row or null
     */
    public function getActiveApplication(string $studentId, string $jobPositionId, ?string $companyId = null): ?array
    {
        $sql = "
            SELECT ia.id, ia.postId, ia.studentId, ia.status, ia.message, ia.appliedAt, ia.createdAt, ia.updatedAt,
                   ip.title AS postTitle, ip.enterpriseId
            FROM internship_applications ia
            INNER JOIN internship_posts ip ON ip.id = ia.postId
            WHERE (ia.studentId = :student_id OR ia.studentId IN (SELECT sp.id FROM student_profiles sp WHERE sp.userId = :student_id_alt))
              AND ia.postId = :job_position_id
              AND (:company_id IS NULL OR ip.enterpriseId = :company_id_alt)
              AND ia.status NOT IN ('rejected', 'declined', 'withdrawn', 'cancelled')
            ORDER BY ia.updatedAt DESC, ia.appliedAt DESC
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'student_id' => $studentId,
            'student_id_alt' => $studentId,
            'job_position_id' => $jobPositionId,
            'company_id' => $companyId,
            'company_id_alt' => $companyId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * Check boolean existence of active application.
     */
    public function hasActiveApplication(string $studentId, string $jobPositionId, ?string $companyId = null): bool
    {
        return $this->getActiveApplication($studentId, $jobPositionId, $companyId) !== null;
    }

    /**
     * Create or send an internship offer/invitation with strict anti-duplicate constraint.
     *
     * @param string $studentId
     * @param string $jobPositionId
     * @param string|null $companyId
     * @param string $message
     * @return array{success: bool, applicationId: string, status: string, isNew: bool, message: string}
     * @throws \RuntimeException If an active conflicting application is already in progress/accepted
     */
    public function sendOffer(string $studentId, string $jobPositionId, ?string $companyId = null, string $message = ''): array
    {
        // 1. Resolve student profile
        $stStmt = $this->pdo->prepare("SELECT sp.id, sp.userId FROM student_profiles sp WHERE sp.id = ? OR sp.userId = ? LIMIT 1");
        $stStmt->execute([$studentId, $studentId]);
        $st = $stStmt->fetch(PDO::FETCH_ASSOC);
        if (!$st) {
            throw new \RuntimeException("Không tìm thấy hồ sơ sinh viên.");
        }
        $resolvedStudentId = (string) $st['id'];

        // 2. Check active application constraint
        $activeApp = $this->getActiveApplication($resolvedStudentId, $jobPositionId, $companyId);
        if ($activeApp !== null) {
            $status = (string) $activeApp['status'];
            if (in_array($status, ['accepted', 'hired', 'interview', 'interviewing', 'reviewing'], true)) {
                $statusLabel = match($status) {
                    'accepted', 'hired' => 'Đã tiếp nhận thực tập',
                    'interview', 'interviewing' => 'Đang trong quá trình phỏng vấn',
                    'reviewing' => 'Đang được xét duyệt hồ sơ',
                    default => 'Đang hoạt động'
                };
                throw new \RuntimeException("Sinh viên đã có đơn đang hoạt động ({$statusLabel}) tại vị trí này. Không thể tạo trùng lặp.");
            }

            // If existing status is 'invited' or 'submitted', update invitation message
            $upd = $this->pdo->prepare("UPDATE internship_applications SET message = :msg, updatedAt = NOW() WHERE id = :id");
            $upd->execute(['msg' => $message, 'id' => $activeApp['id']]);

            return [
                'success' => true,
                'applicationId' => (string) $activeApp['id'],
                'status' => $status,
                'isNew' => false,
                'message' => 'Đã cập nhật lời mời thực tập cho sinh viên.',
            ];
        }

        // 3. Create new application with status 'invited' (or re-activate if previously declined/withdrawn)
        $newId = Uuid::v4();
        $ins = $this->pdo->prepare("
            INSERT INTO internship_applications (id, postId, studentId, status, message, appliedAt, createdAt, updatedAt)
            VALUES (?, ?, ?, 'invited', ?, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                status = 'invited',
                message = VALUES(message),
                updatedAt = NOW()
        ");
        $ins->execute([$newId, $jobPositionId, $resolvedStudentId, $message]);

        return [
            'success' => true,
            'applicationId' => $newId,
            'status' => 'invited',
            'isNew' => true,
            'message' => 'Đã gửi lời mời thực tập thành công.',
        ];
    }
}
