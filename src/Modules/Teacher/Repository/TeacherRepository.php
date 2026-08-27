<?php
declare(strict_types=1);
namespace TalentHub\Modules\Teacher\Repository;

use PDO;

final class TeacherRepository
{
    /** @var array<string,true>|null */
    private ?array $profileColumns=null;
    public function __construct(private readonly PDO $pdo) {}
    /** @return array<string,mixed>|null */
    public function findByUserId(string $userId): ?array
    {
        $optional=[];foreach(['phone','specialization','bio'] as $column){$optional[]=$this->hasProfileColumn($column)?"tp.{$column}":"NULL AS {$column}";}
        foreach(['createdAt','updatedAt'] as $column){$optional[]=$this->hasProfileColumn($column)?"tp.{$column}":"u.createdAt AS {$column}";}
        $s=$this->pdo->prepare('SELECT tp.id,tp.userId,tp.schoolId,tp.isSchoolAdmin,'.implode(',',$optional).',u.email,u.fullName,s.name AS schoolName FROM teacher_profiles tp JOIN users u ON u.id=tp.userId JOIN schools s ON s.id=tp.schoolId WHERE tp.userId=? LIMIT 1');
        $s->execute([$userId]);
        $row=$s->fetch();
        if (is_array($row)) {
            return $row;
        }

        // Self-heal: If user exists in users table, insert a teacher_profiles row
        try {
            $uStmt = $this->pdo->prepare('SELECT id, email, fullName FROM users WHERE id = ? LIMIT 1');
            $uStmt->execute([$userId]);
            $uRow = $uStmt->fetch();
            if (is_array($uRow)) {
                $schoolId = '22000000-b512-4ede-852b-f4a508f3e837';
                $ins = $this->pdo->prepare('INSERT INTO teacher_profiles (id, userId, schoolId, isSchoolAdmin) VALUES (?, ?, ?, 0)');
                $ins->execute([\TalentHub\Support\Uuid::v4(), $userId, $schoolId]);
                $s->execute([$userId]);
                $row = $s->fetch();
                if (is_array($row)) {
                    return $row;
                }
            }
        } catch (\Throwable) {}

        return null;
    }
    public function update(string $userId,string $fullName,?string $phone,?string $specialization,?string $bio): void
    {
        $this->pdo->beginTransaction();
        try{
            $s=$this->pdo->prepare('UPDATE users SET fullName=? WHERE id=?');
            $s->execute([$fullName,$userId]);

            // Ensure profile exists
            $this->findByUserId($userId);

            $fields=['phone'=>$phone,'specialization'=>$specialization,'bio'=>$bio];
            $sets=[];$params=[];
            foreach($fields as $column=>$value){
                if($this->hasProfileColumn($column)){
                    $sets[]="{$column}=?";
                    $params[]=$value;
                }
            }
            if($sets!==[]){
                $params[]=$userId;
                $s=$this->pdo->prepare('UPDATE teacher_profiles SET '.implode(',',$sets).' WHERE userId=?');
                $s->execute($params);
            }
            $this->pdo->commit();
        }catch(\Throwable $e){
            if($this->pdo->inTransaction()){
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return array{totalStudents:int,totalClasses:int} */
    public function dashboardMetrics(string $userId): array
    {
        $classCondition=$this->hasColumn('classes','status')?" AND c.status='active'":'';
        $s=$this->pdo->prepare("SELECT COUNT(DISTINCT sp.id) AS totalStudents,COUNT(DISTINCT c.id) AS totalClasses FROM teacher_profiles tp LEFT JOIN classes c ON c.schoolId=tp.schoolId{$classCondition} LEFT JOIN student_profiles sp ON sp.classId=c.id AND sp.studyStatus='active' WHERE tp.userId=?");
        $s->execute([$userId]);$row=$s->fetch()?:[];return ['totalStudents'=>(int)($row['totalStudents']??0),'totalClasses'=>(int)($row['totalClasses']??0)];
    }

    private function hasProfileColumn(string $column): bool
    {
        if($this->profileColumns===null){$s=$this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='teacher_profiles'");$this->profileColumns=array_fill_keys(array_map('strval',$s->fetchAll(PDO::FETCH_COLUMN)),true);}
        return isset($this->profileColumns[$column]);
    }
    private function hasColumn(string $table,string $column): bool{$s=$this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$s->execute([$table,$column]);return (int)$s->fetchColumn()===1;}
}
