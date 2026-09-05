<?php
$file = 'app/enterprise/talents/detail.php';
$content = file_get_contents($file);

// 1. Remove mock projects from PHP
$mockProjPHP = <<<EOT
    if (empty(\$normalizedProjects)) {
        \$normalizedProjects[] = [
            'name' => 'Ứng dụng AI phân loại rác & Tái chế thông minh',
            'description' => 'Mô hình Computer Vision nhận diện tự động phân loại rác thải, áp dụng deep learning YOLOv8.',
            'role' => 'Lập trình viên & Kỹ sư AI',
            'category' => 'AI & Phần mềm',
            'result' => 'Đang phát triển',
            'technologies' => ['Python', 'PyTorch', 'OpenCV', 'REST API']
        ];
    }
EOT;

$content = str_replace(str_replace("\r\n", "\n", $mockProjPHP), '', str_replace("\r\n", "\n", $content));

// 2. Remove mock experiences from PHP
$mockExpPHP = <<<EOT
    if (empty(\$experienceLogs)) {
        \$experienceLogs = [
            [
                'title' => 'IoT Lab - Cảm biến thông minh & AI Nhúng',
                'role' => 'Lập trình viên & Kỹ sư nhúng',
                'duration' => '08/2026',
                'hours' => 24,
                'description' => 'Xưởng thực hành lập trình vi điều khiển ESP32 và tích hợp mô hình AI nhận diện tại Phòng B305 - BTEC FPT.',
            ],
            [
                'title' => 'Hackathon Sáng tạo Trẻ BTEC FPT 2026',
                'role' => 'Trưởng nhóm phát triển AI',
                'duration' => '07/2026',
                'hours' => 36,
                'description' => 'Phát triển nguyên mẫu hệ thống nhận diện và phân loại rác thải tự động đạt giải Nhì chung cuộc.',
            ]
        ];
    }
EOT;

$content = str_replace(str_replace("\r\n", "\n", $mockExpPHP), '', $content);

// 3. Add Empty State for Experience HTML
$expHtmlFind = <<<EOT
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </section>

                                <!-- 5. Dự án nổi bật (Featured Projects) -->
EOT;

$expHtmlReplace = <<<EOT
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div style="padding: 2.5rem 1rem; text-align: center; color: var(--text-muted); background: var(--background); border-radius: 8px; border: 1px dashed var(--border);">
                                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 0.5rem; opacity: 0.5;">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <polyline points="12 6 12 12 16 14"></polyline>
                                                </svg>
                                                <p style="margin: 0; font-size: 0.9375rem;">Chưa có dữ liệu kinh nghiệm thực án.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </section>

                                <!-- 5. Dự án nổi bật (Featured Projects) -->
EOT;

$content = str_replace(str_replace("\r\n", "\n", $expHtmlFind), str_replace("\r\n", "\n", $expHtmlReplace), $content);


// 4. Add Empty State for Projects HTML
$projHtmlFind = <<<EOT
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </section>


                            </div>

                            <!-- Right Column Sidebar -->
EOT;

$projHtmlReplace = <<<EOT
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div style="padding: 2.5rem 1rem; text-align: center; color: var(--text-muted); background: var(--background); border-radius: 8px; border: 1px dashed var(--border);">
                                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin: 0 auto 0.5rem; opacity: 0.5;">
                                                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                                                </svg>
                                                <p style="margin: 0; font-size: 0.9375rem;">Chưa có dự án nổi bật nào được cập nhật.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </section>


                            </div>

                            <!-- Right Column Sidebar -->
EOT;

$content = str_replace(str_replace("\r\n", "\n", $projHtmlFind), str_replace("\r\n", "\n", $projHtmlReplace), $content);

file_put_contents($file, $content);
echo "Successfully patched mockups and added empty states.";
