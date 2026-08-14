<?php
declare(strict_types=1);
namespace TalentHub\Modules\Teacher\Repository;

use PDO;

final class TeacherRepository
{
    public function __construct(private readonly PDO $pdo) {}
    /** @return array<string,mixed>|null */
    public function findByUserId(string $userId): ?array
    {
        $s=$this->pdo->prepare('SELECT tp.id,tp.userId,tp.schoolId,tp.isSchoolAdmin,tp.phone,tp.specialization,tp.bio,tp.createdAt,tp.updatedAt,u.email,u.fullName,s.name AS schoolName FROM teacher_profiles tp JOIN users u ON u.id=tp.userId JOIN schools s ON s.id=tp.schoolId WHERE tp.userId=? LIMIT 1');
        $s->execute([$userId]);$row=$s->fetch();return is_array($row)?$row:null;
    }
    public function update(string $userId,string $fullName,?string $phone,?string $specialization,?string $bio): void
    {
        $this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare('UPDATE users SET fullName=? WHERE id=?');$s->execute([$fullName,$userId]);
            $s=$this->pdo->prepare('UPDATE teacher_profiles SET phone=?,specialization=?,bio=? WHERE userId=?');$s->execute([$phone,$specialization,$bio,$userId]);
            if($s->rowCount()===0&&$this->findByUserId($userId)===null){throw new \RuntimeException('Teacher profile missing.');}
            $this->pdo->commit();
        }catch(\Throwable $e){if($this->pdo->inTransaction()){$this->pdo->rollBack();}throw $e;}
    }

    /** @return array{totalStudents:int,totalClasses:int} */
    public function dashboardMetrics(string $userId): array
    {
        $s=$this->pdo->prepare('SELECT COUNT(DISTINCT sp.id) AS totalStudents,COUNT(DISTINCT c.id) AS totalClasses FROM teacher_profiles tp LEFT JOIN classes c ON c.schoolId=tp.schoolId AND c.status=\'active\' LEFT JOIN student_profiles sp ON sp.classId=c.id AND sp.studyStatus=\'active\' WHERE tp.userId=?');
        $s->execute([$userId]);$row=$s->fetch()?:[];return ['totalStudents'=>(int)($row['totalStudents']??0),'totalClasses'=>(int)($row['totalClasses']??0)];
    }
}
