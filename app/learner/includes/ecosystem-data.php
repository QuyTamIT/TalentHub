<?php
/**
 * Learner ecosystem read model.
 *
 * Authenticated runtime reads canonical TalentHub repositories only. Mock
 * adapters below are isolated to the explicit APP_ENV=test fixture path.
 */

require_once dirname(__DIR__) . '/data/bootstrap.php';

if (!function_exists('learner_ecosystem_http_url')) {
    function learner_ecosystem_http_url(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $url = trim($value);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}

if (!function_exists('learner_ecosystem_partner_uses_default')) {
    function learner_ecosystem_partner_uses_default(array $partner, string $field): bool
    {
        $notes = is_array($partner['data_notes'] ?? null) ? $partner['data_notes'] : [];
        foreach ($notes as $note) {
            if (is_string($note) && str_starts_with($note, "ecosystem_partner.{$field} uses a safe compatibility default")) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('learner_ecosystem_partner_list')) {
    function learner_ecosystem_partner_list(array $partner, string $field): array
    {
        if (learner_ecosystem_partner_uses_default($partner, $field)) {
            return [];
        }

        $value = $partner[$field] ?? null;
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '', $value),
            static fn (string $item): bool => $item !== '' && $item !== 'Chưa cập nhật'
        ));
    }
}

if (!function_exists('learner_ecosystem_partner_has_value')) {
    function learner_ecosystem_partner_has_value(array $partner, string $field): bool
    {
        if (learner_ecosystem_partner_uses_default($partner, $field)) {
            return false;
        }

        $value = $partner[$field] ?? null;
        if (is_array($value)) {
            return learner_ecosystem_partner_list($partner, $field) !== [];
        }
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || in_array($value, [
                '#',
                'Chưa cập nhật',
                'Thông tin giới thiệu chưa có trong schema hiện tại.',
            ], true)) {
                return false;
            }
            if ($field === 'website') {
                return learner_ecosystem_http_url($value) !== null;
            }
            return true;
        }

        return is_int($value) || is_float($value) || $value === true;
    }
}

if (!function_exists('learner_ecosystem_enterprise_posts')) {
    function learner_ecosystem_enterprise_posts(): array
    {
        static $posts = null;
        if (is_array($posts)) {
            return $posts;
        }

        if (is_array($GLOBALS['mockInternships'] ?? null)) {
            $posts = $GLOBALS['mockInternships'];
            return $posts;
        }

        $mockInternships = [];
        require_once dirname(__DIR__, 2) . '/enterprise/includes/internships-data.php';
        $posts = is_array($mockInternships) ? $mockInternships : [];
        $GLOBALS['mockInternships'] = $posts;

        return $posts;
    }
}

if (!function_exists('learner_ecosystem_mock_enterprises')) {
    function learner_ecosystem_mock_enterprises(): array
    {
        $visiblePosts = array_values(array_filter(
            learner_ecosystem_enterprise_posts(),
            static fn (array $post): bool => ($post['status'] ?? '') !== 'draft'
        ));
        $activePosts = array_values(array_filter(
            $visiblePosts,
            static fn (array $post): bool => ($post['status'] ?? '') === 'active'
        ));

        return [[
            'id' => 'fpt-software',
            'type' => 'enterprise',
            'name' => 'FPT Software',
            'short_name' => 'FPT',
            'logo_text' => 'FPT',
            'verified' => true,
            'source' => 'enterprise_mock',
            'industry' => 'Công nghệ thông tin',
            'location' => 'Hà Nội',
            'address' => 'FPT Tower, số 10 Phạm Văn Bạch, Cầu Giấy, Hà Nội',
            'website' => 'https://fptsoftware.com',
            'email' => 'internship@fpt.com',
            'phone' => '024 7300 7300',
            'size' => '30.000+ nhân sự',
            'founded' => '1999',
            'description' => 'FPT Software là doanh nghiệp công nghệ toàn cầu, cung cấp dịch vụ chuyển đổi số, phát triển phần mềm và giải pháp trí tuệ nhân tạo cho khách hàng tại nhiều quốc gia.',
            'highlights' => ['Môi trường công nghệ toàn cầu', 'Chương trình mentor cho sinh viên', 'Cơ hội trở thành nhân viên chính thức'],
            'opportunity_count' => count($activePosts),
            'total_post_count' => count($visiblePosts),
        ]];
    }
}

if (!function_exists('learner_ecosystem_mock_schools')) {
    function learner_ecosystem_mock_schools(): array
    {
        return [
            [
                'id' => 'dai-hoc-bach-khoa-ha-noi',
                'type' => 'school',
                'name' => 'Đại học Bách khoa Hà Nội',
                'short_name' => 'HUST',
                'logo_text' => 'HUST',
                'verified' => true,
                'source' => 'learner_demo',
                'school_type' => 'Đại học công lập',
                'location' => 'Hà Nội',
                'address' => 'Số 1 Đại Cồ Việt, Hai Bà Trưng, Hà Nội',
                'website' => 'https://hust.edu.vn',
                'email' => 'tuyensinh@hust.edu.vn',
                'phone' => '024 3869 4242',
                'description' => 'Cơ sở đào tạo đa ngành định hướng nghiên cứu, nổi bật trong các lĩnh vực kỹ thuật, công nghệ và đổi mới sáng tạo.',
                'programs' => ['Khoa học máy tính', 'Kỹ thuật máy tính', 'Tự động hóa', 'Kỹ thuật điện tử - viễn thông'],
                'facilities' => ['Phòng thí nghiệm chuyên ngành', 'Không gian sáng tạo sinh viên', 'Thư viện số'],
                'events' => ['Ngày hội trải nghiệm công nghệ', 'Tư vấn tuyển sinh trực tuyến'],
                'opportunity_count' => 2,
            ],
            [
                'id' => 'dai-hoc-fpt',
                'type' => 'school',
                'name' => 'Trường Đại học FPT',
                'short_name' => 'FPTU',
                'logo_text' => 'FPTU',
                'verified' => true,
                'source' => 'learner_demo',
                'school_type' => 'Đại học tư thục',
                'location' => 'Hà Nội',
                'address' => 'Khu Công nghệ cao Hòa Lạc, Thạch Thất, Hà Nội',
                'website' => 'https://daihoc.fpt.edu.vn',
                'email' => 'daihocfpt@fpt.edu.vn',
                'phone' => '024 7300 5588',
                'description' => 'Môi trường đào tạo gắn với thực tiễn doanh nghiệp, chú trọng công nghệ, ngoại ngữ và trải nghiệm toàn cầu.',
                'programs' => ['Kỹ thuật phần mềm', 'Trí tuệ nhân tạo', 'Thiết kế mỹ thuật số', 'Digital Marketing'],
                'facilities' => ['Campus công nghệ', 'Không gian dự án', 'Khu thể thao sinh viên'],
                'events' => ['Open Day 2026', 'Trải nghiệm một ngày làm sinh viên'],
                'opportunity_count' => 1,
            ],
            [
                'id' => 'dai-hoc-kinh-te-quoc-dan',
                'type' => 'school',
                'name' => 'Đại học Kinh tế Quốc dân',
                'short_name' => 'NEU',
                'logo_text' => 'NEU',
                'verified' => true,
                'source' => 'learner_demo',
                'school_type' => 'Đại học công lập',
                'location' => 'Hà Nội',
                'address' => '207 Giải Phóng, Hai Bà Trưng, Hà Nội',
                'website' => 'https://neu.edu.vn',
                'email' => 'tuyensinh@neu.edu.vn',
                'phone' => '024 3628 0280',
                'description' => 'Cơ sở đào tạo trọng điểm về kinh tế, quản lý và quản trị kinh doanh, có mạng lưới hợp tác doanh nghiệp rộng.',
                'programs' => ['Quản trị kinh doanh', 'Kinh doanh quốc tế', 'Thương mại điện tử', 'Khoa học dữ liệu trong kinh tế'],
                'facilities' => ['Giảng đường thông minh', 'Thư viện Phạm Văn Đồng', 'Trung tâm khởi nghiệp'],
                'events' => ['Ngày hội tư vấn tuyển sinh', 'Cuộc thi ý tưởng khởi nghiệp'],
                'opportunity_count' => 1,
            ],
        ];
    }
}

if (!function_exists('learner_ecosystem_normalize_internship')) {
    function learner_ecosystem_normalize_internship(array $post): array
    {
        return [
            'id' => $post['id'],
            'type' => 'internship',
            'source' => 'enterprise_mock',
            'partner_id' => 'fpt-software',
            'partner_type' => 'enterprise',
            'partner_name' => 'FPT Software',
            'title' => $post['title'],
            'field' => $post['field'],
            'status' => $post['status'],
            'status_label' => $post['status_label'],
            'created_at' => $post['created_at'],
            'deadline' => $post['deadline'],
            'slots' => $post['slots'],
            'applicant_count' => $post['applicant_count'],
            'work_type' => $post['work_type'],
            'duration' => $post['duration'],
            'education_level' => $post['education_level'],
            'description' => $post['description'],
            'skills' => $post['skills'],
            'benefits' => $post['benefits'],
            'location' => 'Hà Nội',
            'requirements' => ['Chủ động học hỏi và có tinh thần trách nhiệm', 'Có thể tham gia đúng thời lượng của chương trình', 'Đính kèm hồ sơ năng lực hoặc CV khi ứng tuyển'],
        ];
    }
}

if (!function_exists('learner_ecosystem_mock_school_opportunities')) {
    function learner_ecosystem_mock_school_opportunities(): array
    {
        return [
            [
                'id' => 'hust-open-day-2026',
                'type' => 'school-event',
                'source' => 'learner_demo',
                'partner_id' => 'dai-hoc-bach-khoa-ha-noi',
                'partner_type' => 'school',
                'partner_name' => 'Đại học Bách khoa Hà Nội',
                'title' => 'Ngày hội trải nghiệm công nghệ 2026',
                'field' => 'Kỹ thuật - Công nghệ',
                'status' => 'active',
                'status_label' => 'Đang mở đăng ký',
                'created_at' => '2026-08-01',
                'deadline' => '2026-09-15',
                'slots' => 500,
                'applicant_count' => 286,
                'work_type' => 'Trực tiếp',
                'duration' => '1 ngày',
                'education_level' => 'Học sinh THPT',
                'description' => 'Tham quan phòng thí nghiệm, gặp gỡ giảng viên và trải nghiệm các hoạt động công nghệ dành cho học sinh THPT.',
                'skills' => ['Trải nghiệm ngành học', 'Tư vấn tuyển sinh', 'Tham quan campus'],
                'benefits' => 'Miễn phí tham dự và được tư vấn lộ trình ngành học phù hợp.',
                'location' => 'Hà Nội',
                'requirements' => ['Đang là học sinh THPT', 'Đăng ký thông tin trước thời hạn'],
            ],
            [
                'id' => 'hust-scholarship-2026',
                'type' => 'scholarship',
                'source' => 'learner_demo',
                'partner_id' => 'dai-hoc-bach-khoa-ha-noi',
                'partner_type' => 'school',
                'partner_name' => 'Đại học Bách khoa Hà Nội',
                'title' => 'Học bổng tài năng công nghệ 2026',
                'field' => 'Học bổng',
                'status' => 'active',
                'status_label' => 'Đang nhận hồ sơ',
                'created_at' => '2026-08-10',
                'deadline' => '2026-10-01',
                'slots' => 40,
                'applicant_count' => 68,
                'work_type' => 'Nộp hồ sơ trực tuyến',
                'duration' => 'Theo năm học',
                'education_level' => 'Học sinh lớp 12',
                'description' => 'Chương trình học bổng dành cho học sinh có thành tích nổi bật trong các cuộc thi khoa học kỹ thuật và công nghệ.',
                'skills' => ['Thành tích học tập', 'Dự án khoa học', 'Hoạt động cộng đồng'],
                'benefits' => 'Hỗ trợ từ 50% đến 100% học phí năm đầu tiên.',
                'location' => 'Trực tuyến',
                'requirements' => ['Học lực giỏi', 'Có thành tích hoặc dự án công nghệ nổi bật'],
            ],
            [
                'id' => 'fptu-open-day-2026',
                'type' => 'school-event',
                'source' => 'learner_demo',
                'partner_id' => 'dai-hoc-fpt',
                'partner_type' => 'school',
                'partner_name' => 'Trường Đại học FPT',
                'title' => 'Open Day — Một ngày làm sinh viên FPTU',
                'field' => 'Trải nghiệm trường học',
                'status' => 'active',
                'status_label' => 'Đang mở đăng ký',
                'created_at' => '2026-08-08',
                'deadline' => '2026-09-05',
                'slots' => 300,
                'applicant_count' => 181,
                'work_type' => 'Trực tiếp',
                'duration' => '1 ngày',
                'education_level' => 'Học sinh THPT',
                'description' => 'Trải nghiệm lớp học, campus, câu lạc bộ và nhận tư vấn về các chương trình đào tạo tại FPTU.',
                'skills' => ['Trải nghiệm campus', 'Định hướng nghề nghiệp'],
                'benefits' => 'Miễn phí tham gia và nhận bộ tài liệu định hướng ngành học.',
                'location' => 'Hòa Lạc, Hà Nội',
                'requirements' => ['Học sinh THPT', 'Đăng ký trước thời hạn'],
            ],
            [
                'id' => 'neu-startup-day-2026',
                'type' => 'school-event',
                'source' => 'learner_demo',
                'partner_id' => 'dai-hoc-kinh-te-quoc-dan',
                'partner_type' => 'school',
                'partner_name' => 'Đại học Kinh tế Quốc dân',
                'title' => 'Ngày hội ý tưởng khởi nghiệp học sinh 2026',
                'field' => 'Kinh doanh - Khởi nghiệp',
                'status' => 'active',
                'status_label' => 'Đang mở đăng ký',
                'created_at' => '2026-08-11',
                'deadline' => '2026-09-20',
                'slots' => 150,
                'applicant_count' => 73,
                'work_type' => 'Trực tiếp',
                'duration' => '1 ngày',
                'education_level' => 'Học sinh THPT',
                'description' => 'Sân chơi phát triển ý tưởng kinh doanh, gặp cố vấn và khám phá các ngành đào tạo kinh tế - quản trị.',
                'skills' => ['Tư duy kinh doanh', 'Thuyết trình', 'Làm việc nhóm'],
                'benefits' => 'Nhận phản hồi từ cố vấn và giấy chứng nhận tham dự.',
                'location' => 'Hà Nội',
                'requirements' => ['Có ý tưởng hoặc quan tâm đến khởi nghiệp', 'Đăng ký theo cá nhân hoặc đội nhóm'],
            ],
        ];
    }
}

if (!function_exists('learner_ecosystem_mock_opportunities')) {
    function learner_ecosystem_mock_opportunities(): array
    {
        $internships = array_map(
            'learner_ecosystem_normalize_internship',
            array_values(array_filter(
                learner_ecosystem_enterprise_posts(),
                static fn (array $post): bool => ($post['status'] ?? '') !== 'draft'
            ))
        );

        return array_merge($internships, learner_ecosystem_mock_school_opportunities());
    }
}

if (!function_exists('learner_ecosystem_mock_applications')) {
    function learner_ecosystem_mock_applications(): array
    {
        return [
            [
                'id' => 'APP-2026-0812',
                'opportunity_type' => 'internship',
                'opportunity_id' => 1,
                'title' => 'Thực tập sinh Frontend Developer (React / TypeScript)',
                'partner_name' => 'FPT Software',
                'submitted_at' => '12/08/2026',
                'updated_at' => '13/08/2026',
                'status' => 'reviewing',
                'status_label' => 'Đang xem xét',
                'can_withdraw' => true,
                'timeline' => [
                    ['label' => 'Đã nộp hồ sơ', 'date' => '12/08/2026', 'state' => 'complete'],
                    ['label' => 'Doanh nghiệp đang xem xét', 'date' => '13/08/2026', 'state' => 'current'],
                    ['label' => 'Phỏng vấn', 'date' => 'Chờ cập nhật', 'state' => 'pending'],
                    ['label' => 'Kết quả', 'date' => 'Chờ cập nhật', 'state' => 'pending'],
                ],
            ],
            [
                'id' => 'APP-2026-0728',
                'opportunity_type' => 'internship',
                'opportunity_id' => 2,
                'title' => 'Thực tập sinh AI Research & Data Science 2026',
                'partner_name' => 'FPT Software',
                'submitted_at' => '28/07/2026',
                'updated_at' => '05/08/2026',
                'status' => 'interview',
                'status_label' => 'Mời phỏng vấn',
                'can_withdraw' => true,
                'timeline' => [
                    ['label' => 'Đã nộp hồ sơ', 'date' => '28/07/2026', 'state' => 'complete'],
                    ['label' => 'Đã duyệt hồ sơ', 'date' => '02/08/2026', 'state' => 'complete'],
                    ['label' => 'Phỏng vấn trực tuyến', 'date' => '15:00 · 18/08/2026', 'state' => 'current'],
                    ['label' => 'Kết quả', 'date' => 'Chờ cập nhật', 'state' => 'pending'],
                ],
            ],
            [
                'id' => 'APP-2026-0615',
                'opportunity_type' => 'internship',
                'opportunity_id' => 5,
                'title' => 'Thực tập sinh Digital Marketing & Content TalentHub',
                'partner_name' => 'FPT Software',
                'submitted_at' => '15/06/2026',
                'updated_at' => '02/07/2026',
                'status' => 'declined',
                'status_label' => 'Chưa phù hợp',
                'can_withdraw' => false,
                'timeline' => [
                    ['label' => 'Đã nộp hồ sơ', 'date' => '15/06/2026', 'state' => 'complete'],
                    ['label' => 'Đã xem xét', 'date' => '25/06/2026', 'state' => 'complete'],
                    ['label' => 'Kết quả: Chưa phù hợp', 'date' => '02/07/2026', 'state' => 'declined'],
                ],
            ],
        ];
    }
}

if (!function_exists('learner_ecosystem_partner')) {
    function learner_ecosystem_partner(string $type, string $id): ?array
    {
        return \TalentHub\Learner\Data\ReadModel\EcosystemReadModel::resolvePartner(
            learner_ecosystem_repository(),
            $type,
            $id
        );
    }
}

if (!function_exists('learner_ecosystem_opportunity')) {
    function learner_ecosystem_opportunity(string $type, string|int $id): ?array
    {
        return \TalentHub\Learner\Data\ReadModel\EcosystemReadModel::resolveOpportunity(
            learner_ecosystem_repository(),
            $type,
            (string) $id
        );
    }
}

if (!function_exists('learner_ecosystem_partner_opportunities')) {
    function learner_ecosystem_partner_opportunities(string $partnerId, bool $activeOnly = false): array
    {
        return \TalentHub\Learner\Data\ReadModel\EcosystemReadModel::opportunities(
            learner_ecosystem_repository()->opportunitiesForPartner($partnerId, $activeOnly)
        );
    }
}

if (!function_exists('learner_ecosystem_repository')) {
    function learner_ecosystem_repository(): \TalentHub\Learner\Data\Contracts\EcosystemRepository
    {
        $factory = learner_repository_factory();
        if ($factory->source() === 'database') {
            return $factory->ecosystem();
        }

        return $factory->ecosystem(
            array_merge(learner_ecosystem_mock_enterprises(), learner_ecosystem_mock_schools()),
            learner_ecosystem_mock_opportunities()
        );
    }
}

if (!function_exists('learner_application_repository')) {
    function learner_application_repository(): \TalentHub\Learner\Data\Contracts\ApplicationRepository
    {
        $factory = learner_repository_factory();
        if ($factory->source() === 'database') {
            return $factory->application();
        }

        $opportunities = learner_ecosystem_mock_opportunities();
        $applications = array_map(
            static function (array $application) use ($opportunities): array {
                $application['student_id'] = 'student-demo-001';
                foreach ($opportunities as $opportunity) {
                    if (($opportunity['type'] ?? '') !== ($application['opportunity_type'] ?? '')
                        || (string) ($opportunity['id'] ?? '') !== (string) ($application['opportunity_id'] ?? '')) {
                        continue;
                    }

                    if (($opportunity['partner_type'] ?? '') === 'enterprise') {
                        $application['enterprise_id'] = $opportunity['partner_id'];
                    } elseif (($opportunity['partner_type'] ?? '') === 'school') {
                        $application['school_id'] = $opportunity['partner_id'];
                    }
                    break;
                }

                return $application;
            },
            learner_ecosystem_mock_applications()
        );

        return $factory->application($applications);
    }
}

if (!function_exists('learner_ecosystem_enterprises')) {
    function learner_ecosystem_enterprises(): array
    {
        return \TalentHub\Learner\Data\ReadModel\EcosystemReadModel::partners(
            learner_ecosystem_repository()->partners('enterprise')
        );
    }
}

if (!function_exists('learner_ecosystem_schools')) {
    function learner_ecosystem_schools(): array
    {
        return \TalentHub\Learner\Data\ReadModel\EcosystemReadModel::partners(
            learner_ecosystem_repository()->partners('school')
        );
    }
}

if (!function_exists('learner_ecosystem_opportunities')) {
    function learner_ecosystem_opportunities(): array
    {
        return \TalentHub\Learner\Data\ReadModel\EcosystemReadModel::opportunities(
            learner_ecosystem_repository()->opportunities()
        );
    }
}

if (!function_exists('learner_ecosystem_school_activities')) {
    function learner_ecosystem_school_activities(?string $schoolId = null): array
    {
        $factory = learner_repository_factory();
        if ($factory->source() !== 'database') {
            return [];
        }

        $activities = \TalentHub\Learner\Data\ReadModel\ActivityReadModel::activities(
            $factory->activity()->discoverForStudent(
                learner_current_student_id(),
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            )
        );

        return array_values(array_filter(
            $activities,
            static function (array $activity) use ($schoolId): bool {
                $activitySchoolId = (string) ($activity['school_id'] ?? '');

                return $schoolId === null || $activitySchoolId === $schoolId;
            }
        ));
    }
}

if (!function_exists('learner_ecosystem_applications')) {
    function learner_ecosystem_applications(): array
    {
        return \TalentHub\Learner\Data\ReadModel\ApplicationReadModel::applications(
            learner_application_repository()->forStudent(learner_current_student_id())
        );
    }
}

if (!function_exists('learner_ecosystem_can_apply')) {
    function learner_ecosystem_can_apply(array $opportunity, ?string $today = null): bool
    {
        $today ??= date('Y-m-d');

        return ($opportunity['status'] ?? '') === 'active'
            && ($opportunity['deadline'] ?? '') >= $today;
    }
}
