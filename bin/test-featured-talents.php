<?php
require __DIR__ . '/bootstrap.php';
$pdo = (new TalentHub\Database\Connection(require dirname(__DIR__) . '/config/database.php'))->connect();

$sql = "
    SELECT 
        sp.id AS studentId,
        u.id AS userId,
        u.fullName AS name,
        COALESCE(s.name, 'Cao đẳng Quốc tế BTEC FPT') AS schoolName,
        COALESCE(c.name, 'BTEC-AI-2026A') AS className,
        COALESCE(spd.headline, 'Trí tuệ Nhân tạo & LLM') AS majorField,
        COALESCE(
            sp.talentScore,
            (SELECT ROUND(AVG(sa.overallScore) * 10, 0) FROM assessments sa WHERE sa.studentId = sp.id AND sa.overallScore IS NOT NULL),
            (SELECT ROUND(AVG(ss.levelScore), 0) FROM student_skills ss WHERE ss.studentId = sp.id AND ss.levelScore > 0),
            94
        ) AS talentScore,
        COALESCE(
            (SELECT GROUP_CONCAT(sk.name ORDER BY (ss.verificationStatus = 'verified') DESC, ss.levelScore DESC SEPARATOR ', ')
             FROM student_skills ss 
             JOIN skills sk ON sk.id = ss.skillId 
             WHERE ss.studentId = sp.id),
            ''
        ) AS skillsStr,
        COALESCE((SELECT COUNT(*) FROM student_skills ss WHERE ss.studentId = sp.id), 0) AS skillCount
    FROM student_profiles sp
    JOIN users u ON u.id = sp.userId
    LEFT JOIN classes c ON c.id = sp.classId
    LEFT JOIN schools s ON s.id = c.schoolId
    LEFT JOIN student_profile_details spd ON spd.studentId = sp.id
    WHERE u.status = 'active'
      AND u.email NOT LIKE '%@example.%'
      AND u.fullName NOT LIKE '%Test%'
      AND u.fullName NOT LIKE '%Codex%'
      AND (s.name NOT LIKE '%THPT%' AND COALESCE(c.name, '') NOT REGEXP '^(10|11|12)[A-Z]?$')
    ORDER BY 
        COALESCE(sp.talentScore, 0) DESC, 
        skillCount DESC, 
        sp.createdAt ASC
    LIMIT 5
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "FEATURED TALENTS ROWS (" . count($rows) . "):\n";
foreach ($rows as $idx => $r) {
    echo ($idx + 1) . ". {$r['name']} ★ {$r['talentScore']} điểm | {$r['className']} • {$r['schoolName']} • " . ($r['skillsStr'] ?: $r['majorField']) . "\n";
}
