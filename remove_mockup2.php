<?php
$file = 'app/enterprise/talents/detail.php';
$content = file_get_contents($file);

// 1. Remove mock projects
$mockProjectsRegex = "/\s*if \(empty\(\\$normalizedProjects\)\) \{\s*\\$normalizedProjects\[\] = \[\s*'name' => 'Ứng dụng AI phân loại rác & Tái chế thông minh',.*?\];\s*\}/s";
$content = preg_replace($mockProjectsRegex, '', $content);

// 2. Remove mock experiences
$mockExpRegex = "/\s*if \(empty\(\\$experienceLogs\)\) \{\s*\\$experienceLogs = \[\s*\[\s*'title' => 'IoT Lab.*?\];\s*\}/s";
$content = preg_replace($mockExpRegex, '', $content);

// 3. Add else state for experience
$content = preg_replace(
    '/(<\?php foreach \(\$talent\[\'experience_logs\'\] as \$exp\): \?>.*?<\?php endforeach; \?>)\s*(<\?php endif; \?>)/s',
    "$1\n                                        <?php else: ?>\n                                            <div style=\"padding: 2.5rem 1rem; text-align: center; color: var(--text-muted); background: var(--background); border-radius: 8px; border: 1px dashed var(--border);\">\n                                                <svg width=\"32\" height=\"32\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\" style=\"margin: 0 auto 0.5rem; opacity: 0.5;\">\n                                                    <circle cx=\"12\" cy=\"12\" r=\"10\"></circle>\n                                                    <polyline points=\"12 6 12 12 16 14\"></polyline>\n                                                </svg>\n                                                <p style=\"margin: 0; font-size: 0.9375rem;\">Chưa có dữ liệu kinh nghiệm thực án.</p>\n                                            </div>\n                                        $2",
    $content
);

// 4. Add else state for projects
$content = preg_replace(
    '/(<\?php foreach \(\$talent\[\'projects\'\] as \$proj\): \?>.*?<\?php endforeach; \?>)\s*(<\?php endif; \?>)/s',
    "$1\n                                        <?php else: ?>\n                                            <div style=\"padding: 2.5rem 1rem; text-align: center; color: var(--text-muted); background: var(--background); border-radius: 8px; border: 1px dashed var(--border);\">\n                                                <svg width=\"32\" height=\"32\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.5\" style=\"margin: 0 auto 0.5rem; opacity: 0.5;\">\n                                                    <path d=\"M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z\"></path>\n                                                </svg>\n                                                <p style=\"margin: 0; font-size: 0.9375rem;\">Chưa có dự án nổi bật nào được cập nhật.</p>\n                                            </div>\n                                        $2",
    $content
);

file_put_contents($file, $content);
echo "Mock data removed and empty states added precisely.";
