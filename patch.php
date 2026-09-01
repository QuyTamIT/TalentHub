<?php
$file = 'app/enterprise/talents/detail.php';
$content = file_get_contents($file);

$search = '<?php foreach ($talent[\'projects\'] as $proj): ?>
                                                    <div class="ent-passport-project-card__header"';
$replace = '<?php foreach ($talent[\'projects\'] as $proj): ?>
                                                <div class="ent-passport-project-card">
                                                    <div class="ent-passport-project-card__header"';

if (strpos($content, $search) !== false) {
    $content = str_replace($search, $replace, $content);
    file_put_contents($file, $content);
    echo "Fixed.";
} else {
    echo "Not found.";
}
