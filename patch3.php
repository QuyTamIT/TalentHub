<?php
$file = 'app/enterprise/talents/detail.php';
$content = file_get_contents($file);

$search = <<<EOT
                                            <?php foreach (\$talent['projects'] as \$proj): ?>
                                                    <div class="ent-passport-project-card__header" style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;">
                                                        <h4 class="ent-passport-project-card__title" style="margin: 0;"><?= htmlspecialchars(\$proj['name']); ?></h4>
EOT;

$replace = <<<EOT
                                            <?php foreach (\$talent['projects'] as \$proj): ?>
                                                <div class="ent-passport-project-card">
                                                    <div class="ent-passport-project-card__header" style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap;">
                                                        <h4 class="ent-passport-project-card__title" style="margin: 0;"><?= htmlspecialchars(\$proj['name']); ?></h4>
EOT;

if (strpos($content, $search) !== false) {
    $content = str_replace($search, $replace, $content);
    file_put_contents($file, $content);
    echo "Fixed.";
} else {
    // Try with normalized line endings
    $searchLF = str_replace("\r\n", "\n", $search);
    $contentLF = str_replace("\r\n", "\n", $content);
    if (strpos($contentLF, $searchLF) !== false) {
        $contentLF = str_replace($searchLF, $replace, $contentLF);
        file_put_contents($file, $contentLF);
        echo "Fixed (LF).";
    } else {
        echo "Not found.";
    }
}
