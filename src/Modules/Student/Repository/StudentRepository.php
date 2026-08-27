<?php
declare(strict_types=1);
namespace TalentHub\Modules\Student\Repository;
require_once dirname(__DIR__, 4) . '/app/learner/ai/Queue/TransactionalAiOutboxPublisher.php';

use PDO;
use TalentHub\Learner\Ai\Queue\TransactionalAiOutboxPublisher;

final class StudentRepository
{
    private ?bool $hasProfileTimestamps = null;

    public function __construct(private readonly PDO $pdo) {}

    public function findByUserId(string $userId): ?array
    {
        $timestamps = $this->profileHasTimestamps()
            ? 'sp.createdAt,sp.updatedAt'
            : 'u.createdAt AS createdAt,u.createdAt AS updatedAt';

        $hasDetails = $this->hasProfileDetailsTable();
        $detailFields = $hasDetails
            ? 'spd.location,spd.bio,spd.avatarUrl,spd.headline'
            : 'NULL AS location,NULL AS bio,NULL AS avatarUrl,NULL AS headline';
        $detailJoin = $hasDetails
            ? 'LEFT JOIN student_profile_details spd ON spd.studentId=sp.id'
            : '';

        $statement = $this->pdo->prepare(
            'SELECT sp.id,sp.userId,u.email,u.fullName,sp.classId,c.name AS className,c.gradeLevel,c.academicYear,c.schoolId,COALESCE(s.name, \'Trường học\') AS schoolName,sp.dateOfBirth,sp.phone,sp.studyStatus,' . $timestamps . ',' . $detailFields . ' FROM student_profiles sp JOIN users u ON u.id=sp.userId LEFT JOIN classes c ON c.id=sp.classId LEFT JOIN schools s ON s.id=c.schoolId ' . $detailJoin . ' WHERE sp.userId=? LIMIT 1'
        );
        $statement->execute([$userId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function update(
        string $userId,
        string $fullName,
        string $dateOfBirth,
        string $phone,
        ?string $location = null,
        ?string $bio = null,
        ?string $avatarUrl = null,
        ?string $headline = null
    ): void {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('UPDATE users SET fullName=? WHERE id=?');
            $statement->execute([$fullName, $userId]);

            $statement = $this->pdo->prepare('UPDATE student_profiles SET dateOfBirth=?,phone=? WHERE userId=?');
            $statement->execute([$dateOfBirth, $phone, $userId]);

            if ($this->hasProfileDetailsTable()) {
                $studentStatement = $this->pdo->prepare('SELECT id FROM student_profiles WHERE userId=? LIMIT 1');
                $studentStatement->execute([$userId]);
                $studentId = $studentStatement->fetchColumn();
                if (is_string($studentId) && $studentId !== '') {
                    $isSqlite = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
                    if ($isSqlite) {
                        $upsert = $this->pdo->prepare(<<<'SQL'
                            INSERT INTO student_profile_details (studentId, location, bio, avatarUrl, headline, createdAt, updatedAt)
                            VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'))
                            ON CONFLICT(studentId) DO UPDATE SET
                              location=excluded.location,
                              bio=excluded.bio,
                              avatarUrl=excluded.avatarUrl,
                              headline=excluded.headline,
                              updatedAt=datetime('now')
                        SQL
                        );
                    } else {
                        $upsert = $this->pdo->prepare(<<<'SQL'
                            INSERT INTO student_profile_details (studentId, location, bio, avatarUrl, headline, createdAt, updatedAt)
                            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP(6), CURRENT_TIMESTAMP(6))
                            ON DUPLICATE KEY UPDATE
                              location=VALUES(location),
                              bio=VALUES(bio),
                              avatarUrl=VALUES(avatarUrl),
                              headline=VALUES(headline),
                              updatedAt=CURRENT_TIMESTAMP(6)
                        SQL
                        );
                    }
                    $upsert->execute([$studentId, $location, $bio, $avatarUrl, $headline]);
                }
            }

            $studentStatement = $this->pdo->prepare('SELECT id FROM student_profiles WHERE userId=? LIMIT 1');
            $studentStatement->execute([$userId]);
            $affectedStudentId=$studentStatement->fetchColumn();
            if(is_string($affectedStudentId)&&$affectedStudentId!=='') TransactionalAiOutboxPublisher::publish($this->pdo,'student_profile',$affectedStudentId,TransactionalAiOutboxPublisher::version(),[$affectedStudentId],'profile.updated');
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function hasProfileDetailsTable(): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name='student_profile_details'");
            $stmt->execute();
            return (bool) $stmt->fetchColumn();
        }

        $statement = $this->pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='student_profile_details'");
        return (bool) ($statement ? $statement->fetchColumn() : false);
    }

    private function profileHasTimestamps(): bool
    {
        if ($this->hasProfileTimestamps !== null) {
            return $this->hasProfileTimestamps;
        }

        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $columns = $this->pdo->query('PRAGMA table_info(student_profiles)')->fetchAll();
            $names = array_map(static fn(array $column): string => strtolower((string) $column['name']), $columns);
            return $this->hasProfileTimestamps = in_array('createdat', $names, true)
                && in_array('updatedat', $names, true);
        }

        $statement = $this->pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='student_profiles' AND column_name IN ('createdAt','updatedAt')");
        return $this->hasProfileTimestamps = (int) $statement->fetchColumn() === 2;
    }
}
