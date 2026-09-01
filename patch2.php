<?php
$file = 'app/enterprise/talents/detail.php';
$content = file_get_contents($file);

$content = preg_replace(
    '/(<\?php foreach \(\$talent\[\'projects\'\] as \$proj\): \?>)\s*(<div class="ent-passport-project-card__header")/s',
    "$1\n                                                <div class=\"ent-passport-project-card\">\n                                                    $2",
    $content
);

file_put_contents($file, $content);
echo "Fixed regex.";
