<?php
$file = 'app/enterprise/talents/detail.php';
$content = file_get_contents($file);

// Extract Section 3 (Skills)
preg_match('/([ \t]*)<!-- 3\. Kỹ năng.*?<\/section>\r?\n/s', $content, $match3);
$section3 = $match3[0];
$content = str_replace($section3, '', $content);

// Extract Section 6 (Certificates)
preg_match('/([ \t]*)<!-- 6\. Chứng chỉ.*?<\/section>\r?\n/s', $content, $match6);
$section6 = $match6[0];
$content = str_replace($section6, '', $content);

// Insert into Right Column Sidebar
// The right column starts with:
// <!-- Right Column Sidebar (Readiness Summary & Privacy Card) -->
// <aside class="ent-passport-sidebar">
//     
//     <!-- 7. Internship Readiness Summary Widget -->

$sidebarMarker = '<!-- Right Column Sidebar (Readiness Summary & Privacy Card) -->
                            <aside class="ent-passport-sidebar">
                                
                                <!-- 7. Internship Readiness Summary Widget -->';

$sidebarReplacement = '<!-- Right Column Sidebar -->
                            <aside class="ent-passport-sidebar">
                                
                                <!-- 7. Internship Readiness Summary Widget -->';

$content = str_replace($sidebarMarker, $sidebarReplacement, $content);

// The privacy card is at the end of the sidebar:
// <!-- Privacy Protection Notice Card -->

$privacyMarker = '                                <!-- Privacy Protection Notice Card -->';

// We want the sidebar to be:
// 1. Tóm tắt
// 2. Kỹ năng (Section 3)
// 3. Chứng chỉ (Section 6)
// 4. Privacy Card

$insertBeforePrivacy = rtrim($section3) . "\n\n" . rtrim($section6) . "\n\n" . $privacyMarker;

$content = str_replace($privacyMarker, $insertBeforePrivacy, $content);

file_put_contents($file, $content);
echo "Reorganized layout successfully.\n";
