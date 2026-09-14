<?php
declare(strict_types=1);

/** Read display facts only for students already returned by the authorized roster. */
function teacherStudentSlideFacts(PDO $pdo, array $rows): array
{
    $ids = array_values(array_unique(array_column($rows, 'studentId')));
    if ($ids === []) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $facts = [];
    $queries = [
        "SELECT sp.id AS studentId, c.name AS className, sp.talentScore FROM student_profiles sp LEFT JOIN classes c ON c.id=sp.classId WHERE sp.id IN ($placeholders)",
        "SELECT sp.id AS studentId, COALESCE(SUM(el.hours),0) AS experienceHours FROM student_profiles sp LEFT JOIN experience_logs el ON el.studentId=sp.id AND el.status='confirmed' WHERE sp.id IN ($placeholders) GROUP BY sp.id",
        "SELECT sp.id AS studentId, COUNT(sb.id) AS badgeCount FROM student_profiles sp LEFT JOIN student_badges sb ON sb.studentId=sp.id WHERE sp.id IN ($placeholders) GROUP BY sp.id",
    ];
    foreach ($queries as $sql) {
        try {
            $statement = $pdo->prepare($sql);
            $statement->execute($ids);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $facts[$row['studentId']] = array_merge($facts[$row['studentId']] ?? [], $row);
            }
        } catch (Throwable) {
            // Older schemas show an unavailable value, never a fabricated score.
        }
    }
    try {
        $statement = $pdo->prepare("SELECT ss.studentId, s.name FROM student_skills ss JOIN skills s ON s.id=ss.skillId WHERE ss.studentId IN ($placeholders) ORDER BY ss.levelScore DESC, s.name ASC");
        $statement->execute($ids);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $facts[$row['studentId']]['skills'][] = $row['name'];
        }
    } catch (Throwable) {}
    return $facts;
}
