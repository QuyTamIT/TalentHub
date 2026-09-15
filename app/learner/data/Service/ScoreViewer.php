<?php
declare(strict_types=1);
namespace TalentHub\Learner\Data\Service;

/** Trusted authenticated user identity, never a target student/profile ID from a request. */
final class ScoreViewer
{
    private ?\PDO $shareDatabase = null;
    private ?string $shareToken = null;
    private ?string $passportCode = null;
    public const ROLE_STUDENT = 'student';
    public const ROLE_TEACHER = 'teacher';
    public const ROLE_SCHOOL = 'school';
    public const ROLE_ENTERPRISE = 'enterprise';
    public const ROLE_PUBLIC = 'public';

    public function __construct(
        private readonly string $role = self::ROLE_PUBLIC,
        private readonly ?string $viewerId = null,
        private readonly ?string $schoolId = null
    ) {}

    /** A share is a separate capability, never an impersonated student session. */
    public static function fromShareToken(\PDO $pdo, string $token): self
    {
        $viewer = new self();
        $viewer->shareDatabase = $pdo;
        $viewer->shareToken = trim($token);
        $viewer->sharedProfile($pdo);
        return $viewer;
    }

    public static function fromPassportCode(\PDO $pdo, string $code): self
    {
        $viewer = new self();
        $viewer->shareDatabase = $pdo;
        $viewer->passportCode = trim($code);
        $viewer->sharedProfile($pdo);
        return $viewer;
    }

    public function isShared(): bool { return $this->shareDatabase !== null; }

    /** Revalidate on each read so expiry and revocation apply to existing viewers too.
     * @return array{student_id:string,fields:list<string>}
     */
    public function sharedProfile(\PDO $pdo): array
    {
        if ($pdo !== $this->shareDatabase) throw new \DomainException('SCORE_ACCESS_DENIED');
        if ($this->shareToken !== null) {
            if (!preg_match('/^[a-f0-9]{64}$/iD', $this->shareToken)) throw new \DomainException('SCORE_ACCESS_DENIED');
            $now = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite' ? "datetime('now')" : 'CURRENT_TIMESTAMP(6)';
            $stmt = $pdo->prepare("SELECT s.studentId, s.sharedFieldsJson FROM student_profile_shares s
                JOIN privacy_consents c ON c.id=s.consentId AND c.studentId=s.studentId
                JOIN student_profiles sp ON sp.id=s.studentId JOIN users u ON u.id=sp.userId AND u.status='active'
                WHERE s.tokenHash=? AND s.revokedAt IS NULL AND s.expiresAt>{$now}
                AND c.scope='profile_share' AND c.isGranted=1 AND c.revokedAt IS NULL LIMIT 1");
            $stmt->execute([hash('sha256', $this->shareToken)]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $fields = $row ? json_decode((string)$row['sharedFieldsJson'], true) : null;
            if (!$row || !is_array($fields)) throw new \DomainException('SCORE_ACCESS_DENIED');
            return ['student_id'=>(string)$row['studentId'], 'fields'=>array_values(array_filter($fields, 'is_string'))];
        }
        // Public verification codes are the existing QR policy; no SQL wildcards or ambiguous prefixes.
        if (!preg_match('/^(?:TP-)?([a-f0-9]{6,12})$/iD', $this->passportCode ?? '', $match)) {
            throw new \DomainException('SCORE_ACCESS_DENIED');
        }
        $stmt = $pdo->prepare("SELECT sp.id FROM student_profiles sp JOIN users u ON u.id=sp.userId
            WHERE LOWER(REPLACE(sp.id,'-','')) LIKE ? AND u.status='active' LIMIT 2");
        $stmt->execute([strtolower($match[1]).'%']);
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if (count($ids) !== 1) throw new \DomainException('SCORE_ACCESS_DENIED');
        require_once __DIR__ . '/ProfileSharingService.php';
        return ['student_id'=>(string)$ids[0], 'fields'=>ProfileSharingService::ALLOWED_FIELDS];
    }

    /** SessionManager's authenticated identity; never its demo/fallback identity. */
    public static function fromSession(): self
    {
        $user = $_SESSION['user'] ?? null;
        if (is_array($user)) {
            return new self((string)($user['role'] ?? self::ROLE_PUBLIC), isset($user['id']) ? (string)$user['id'] : null);
        }
        return new self((string)($_SESSION['role'] ?? self::ROLE_PUBLIC), isset($_SESSION['user_id']) ? (string)$_SESSION['user_id'] : null);
    }

    public function role(): string { return $this->role; }
    public function viewerId(): ?string { return $this->viewerId; }
    public function schoolId(): ?string { return $this->schoolId; }
    public function canViewAssessorName(): bool { return !$this->isShared() && $this->canViewScores(); }
    public function canViewTestAnswers(): bool
    {
        return $this->viewerId !== null && trim($this->viewerId) !== ''
            && in_array($this->role, [self::ROLE_STUDENT, self::ROLE_TEACHER], true);
    }
    public function canViewTechnicalDetails(): bool { return !$this->isShared() && $this->canViewScores(); }
    public function canViewScores(): bool
    {
        return $this->isShared() || ($this->viewerId !== null && trim($this->viewerId) !== ''
            && in_array($this->role, [self::ROLE_STUDENT,self::ROLE_TEACHER,self::ROLE_SCHOOL], true));
    }
}
