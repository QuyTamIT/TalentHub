<?php
declare(strict_types=1);
namespace TalentHub\Modules\Student\Repository;

use PDO;

final class StudentRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findByUserId(string $userId): ?array
    {
        $statement=$this->pdo->prepare('SELECT sp.id,sp.userId,u.email,u.fullName,sp.classId,c.name AS className,c.gradeLevel,c.academicYear,c.schoolId,s.name AS schoolName,sp.dateOfBirth,sp.phone,sp.studyStatus,sp.createdAt,sp.updatedAt FROM student_profiles sp JOIN users u ON u.id=sp.userId JOIN classes c ON c.id=sp.classId JOIN schools s ON s.id=c.schoolId WHERE sp.userId=? LIMIT 1');
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
}
