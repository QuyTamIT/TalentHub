<?php
declare(strict_types=1);
namespace TalentHub\Modules\Student\Repository;

use PDO;

final class StudentRepository
{
    private ?bool $hasProfileTimestamps = null;

    public function __construct(private readonly PDO $pdo) {}

    public function findByUserId(string $userId): ?array
    {
        $timestamps = $this->profileHasTimestamps()
            ? 'sp.createdAt,sp.updatedAt'
            : 'u.createdAt AS createdAt,u.createdAt AS updatedAt';
        $statement=$this->pdo->prepare('SELECT sp.id,sp.userId,u.email,u.fullName,sp.classId,c.name AS className,c.gradeLevel,c.academicYear,c.schoolId,s.name AS schoolName,sp.dateOfBirth,sp.phone,sp.studyStatus,'.$timestamps.' FROM student_profiles sp JOIN users u ON u.id=sp.userId JOIN classes c ON c.id=sp.classId JOIN schools s ON s.id=c.schoolId WHERE sp.userId=? LIMIT 1');
        $statement->execute([$userId]);$row=$statement->fetch();return is_array($row)?$row:null;
    }

    public function update(string $userId,string $fullName,string $dateOfBirth,string $phone): void
    {
        $this->pdo->beginTransaction();
        try{
            $statement=$this->pdo->prepare('UPDATE users SET fullName=? WHERE id=?');$statement->execute([$fullName,$userId]);
            $statement=$this->pdo->prepare('UPDATE student_profiles SET dateOfBirth=?,phone=? WHERE userId=?');$statement->execute([$dateOfBirth,$phone,$userId]);
            $this->pdo->commit();
        }catch(\Throwable $exception){if($this->pdo->inTransaction()){$this->pdo->rollBack();}throw $exception;}
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
