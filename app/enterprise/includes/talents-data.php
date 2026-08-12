<?php
/**
 * TalentHub Enterprise - Talent Mock Data Provider
 * 
 * Note for Developers:
 * - This mock data simulates join results from student_profiles, users, schools,
 *   classes, skills, student_skills, experience_logs, and privacy_consents.
 * - Do NOT expose personal email or phone numbers per privacy guidelines.
 * - When database/API is integrated, replace this array with SQL query or API service.
 */

$mockTalents = [
    [
        'id' => 1,
        'name' => 'Nguyễn Văn An',
        'avatar_initials' => 'AN',
        'school' => 'Đại học Bách Khoa Hà Nội',
        'education_level' => 'Đại học',
        'class_year' => 'Năm 4',
        'major_field' => 'Công nghệ Thông tin',
        'talent_score' => 95,
        'match_score' => 95,
        'experience_hours' => 120,
        'internship_status' => 'ready_now', // Sẵn sàng ngay
        'internship_status_label' => 'Sẵn sàng thực tập ngay',
        'skills' => ['React', 'Node.js', 'TypeScript', 'UI/UX', 'Communication', 'REST API'],
        'updated_at' => '2026-08-10',
        'saved' => true
    ],
    [
        'id' => 2,
        'name' => 'Lê Thị Bích Ngọc',
        'avatar_initials' => 'BN',
        'school' => 'Đại học Quốc Gia TP.HCM',
        'education_level' => 'Đại học',
        'class_year' => 'Năm 3',
        'major_field' => 'Khoa học Dữ liệu & AI',
        'talent_score' => 92,
        'match_score' => 92,
        'experience_hours' => 95,
        'internship_status' => 'ready_now',
        'internship_status_label' => 'Sẵn sàng thực tập ngay',
        'skills' => ['Python', 'PyTorch', 'SQL', 'Data Analytics', 'Leadership', 'TensorFlow'],
        'updated_at' => '2026-08-11',
        'saved' => false
    ],
    [
        'id' => 3,
        'name' => 'Trần Minh Đức',
        'avatar_initials' => 'MĐ',
        'school' => 'Đại học FPT',
        'education_level' => 'Đại học',
        'class_year' => 'Năm 4',
        'major_field' => 'Kỹ thuật Phần mềm',
        'talent_score' => 88,
        'match_score' => 88,
        'experience_hours' => 150,
        'internship_status' => 'ready_1_3m', // Trong 1-3 tháng
        'internship_status_label' => 'Sẵn sàng từ T9/2026',
        'skills' => ['PHP', 'Laravel', 'MySQL', 'Docker', 'Vue.js', 'Communication'],
        'updated_at' => '2026-08-09',
        'saved' => true
    ],
    [
        'id' => 4,
        'name' => 'Phạm Hoàng Nam',
        'avatar_initials' => 'HN',
        'school' => 'THPT chuyên Hà Nội - Amsterdam',
        'education_level' => 'THPT',
        'class_year' => 'Lớp 12',
        'major_field' => 'Công nghệ Thông tin',
        'match_score' => 89,
        'experience_hours' => 80,
        'internship_status' => 'ready_1_3m',
        'internship_status_label' => 'Sẵn sàng từ T10/2026',
        'skills' => ['C++', 'Python', 'Giải thuật', 'AI/ML', 'Leadership'],
        'updated_at' => '2026-08-12',
        'saved' => false
    ],
    [
        'id' => 5,
        'name' => 'Vũ Mai Phương',
        'avatar_initials' => 'MP',
        'school' => 'Đại học Công nghệ - ĐHQGHN',
        'education_level' => 'Đại học',
        'class_year' => 'Năm 3',
        'major_field' => 'Thiết kế Đồ họa & UI/UX',
        'match_score' => 88,
        'experience_hours' => 110,
        'internship_status' => 'ready_now',
        'internship_status_label' => 'Sẵn sàng thực tập ngay',
        'skills' => ['Figma', 'UI/UX Design', 'Photoshop', 'Prototyping', 'User Research', 'Communication'],
        'updated_at' => '2026-08-07',
        'saved' => false
    ],
    [
        'id' => 6,
        'name' => 'Đỗ Quang Huy',
        'avatar_initials' => 'QH',
        'school' => 'Cao đẳng Kỹ thuật Cao Thắng',
        'education_level' => 'Cao đẳng',
        'class_year' => 'Năm 3',
        'major_field' => 'Kỹ thuật Phần mềm',
        'match_score' => 85,
        'experience_hours' => 160,
        'internship_status' => 'ready_now',
        'internship_status_label' => 'Sẵn sàng thực tập ngay',
        'skills' => ['React Native', 'JavaScript', 'Firebase', 'Mobile App', 'Leadership'],
        'updated_at' => '2026-08-08',
        'saved' => true
    ],
    [
        'id' => 7,
        'name' => 'Hoàng Kim Liên',
        'avatar_initials' => 'KL',
        'school' => 'Đại học Kinh tế Quốc dân',
        'education_level' => 'Đại học',
        'class_year' => 'Năm 4',
        'major_field' => 'Marketing Số & SEO',
        'match_score' => 83,
        'experience_hours' => 130,
        'internship_status' => 'ready_now',
        'internship_status_label' => 'Sẵn sàng thực tập ngay',
        'skills' => ['Digital Marketing', 'SEO', 'Google Analytics', 'Content Marketing', 'Social Ads', 'Communication'],
        'updated_at' => '2026-08-06',
        'saved' => false
    ],
    [
        'id' => 8,
        'name' => 'Ngô Tấn Phát',
        'avatar_initials' => 'TP',
        'school' => 'THPT Lê Hồng Phong TP.HCM',
        'education_level' => 'THPT',
        'class_year' => 'Lớp 11',
        'major_field' => 'Khoa học Dữ liệu & AI',
        'match_score' => 81,
        'experience_hours' => 65,
        'internship_status' => 'not_ready', // Chưa sẵn sàng
        'internship_status_label' => 'Chưa sẵn sàng thực tập',
        'skills' => ['Python', 'Pandas', 'Matplotlib', 'Math', 'Data Analysis'],
        'updated_at' => '2026-08-05',
        'saved' => false
    ],
    [
        'id' => 9,
        'name' => 'Bùi Thanh Hà',
        'avatar_initials' => 'TH',
        'school' => 'Đại học Bách Khoa Hà Nội',
        'education_level' => 'Đại học',
        'class_year' => 'Năm 2',
        'major_field' => 'Công nghệ Thông tin',
        'match_score' => 78,
        'experience_hours' => 90,
        'internship_status' => 'ready_1_3m',
        'internship_status_label' => 'Sẵn sàng từ T9/2026',
        'skills' => ['Java', 'Spring Boot', 'PostgreSQL', 'Git', 'Communication'],
        'updated_at' => '2026-08-04',
        'saved' => false
    ],
    [
        'id' => 10,
        'name' => 'Dương Quốc Bảo',
        'avatar_initials' => 'QB',
        'school' => 'Đại học FPT',
        'education_level' => 'Đại học',
        'class_year' => 'Đã tốt nghiệp',
        'major_field' => 'Kỹ thuật Phần mềm',
        'match_score' => 94,
        'experience_hours' => 210,
        'internship_status' => 'ready_now',
        'internship_status_label' => 'Sẵn sàng làm chính thức / thực tập',
        'skills' => ['C#', '.NET Core', 'SQL Server', 'Azure', 'Microservices', 'Leadership'],
        'updated_at' => '2026-08-12',
        'saved' => true
    ],
    [
        'id' => 11,
        'name' => 'Đinh Ngọc Anh',
        'avatar_initials' => 'NA',
        'school' => 'THCS Nguyễn Du',
        'education_level' => 'THCS',
        'class_year' => 'Lớp 9',
        'major_field' => 'Công nghệ Thông tin',
        'match_score' => 72,
        'experience_hours' => 45,
        'internship_status' => 'not_ready',
        'internship_status_label' => 'Tham gia trải nghiệm dự án',
        'skills' => ['Scratch', 'Python Cơ bản', 'HTML/CSS', 'Communication'],
        'updated_at' => '2026-08-02',
        'saved' => false
    ],
    [
        'id' => 12,
        'name' => 'Trịnh Thành Long',
        'avatar_initials' => 'TL',
        'school' => 'Đại học Quốc Gia TP.HCM',
        'education_level' => 'Đại học',
        'class_year' => 'Năm 3',
        'major_field' => 'An toàn Thông tin',
        'match_score' => 90,
        'experience_hours' => 140,
        'internship_status' => 'ready_now',
        'internship_status_label' => 'Sẵn sàng thực tập ngay',
        'skills' => ['Network Security', 'Linux', 'Ethical Hacking', 'Python', 'Wireshark', 'Leadership'],
        'updated_at' => '2026-08-03',
        'saved' => false
    ]
];

// Helper to filter unique values for UI dropdowns
$schoolsList = array_unique(array_column($mockTalents, 'school'));
sort($schoolsList);

$majorFieldsList = array_unique(array_column($mockTalents, 'major_field'));
sort($majorFieldsList);

$skillsSet = [];
foreach ($mockTalents as $t) {
    foreach ($t['skills'] as $s) {
        $skillsSet[$s] = true;
    }
}
$skillsList = array_keys($skillsSet);
sort($skillsList);
