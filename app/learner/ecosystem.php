<?php
/** TalentHub Learner - Ecosystem hub */
require __DIR__ . '/includes/student-data.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/ecosystem-data.php';

$pageTitle = 'Hệ sinh thái & Cơ hội';
$currentRoute = '/app/learner/ecosystem.php';
$headerSearchLabel = 'Tìm doanh nghiệp, trường học hoặc cơ hội';
$headerSearchPlaceholder = 'Tìm đối tác, ngành học, cơ hội...';
$initialTab = $_GET['tab'] ?? 'enterprises';
$allowedTabs = ['enterprises', 'schools', 'opportunities'];
$initialTab = in_array($initialTab, $allowedTabs, true) ? $initialTab : 'enterprises';
$enterprises = learner_ecosystem_enterprises();
$schools = learner_ecosystem_schools();
$opportunities = learner_ecosystem_opportunities();
$activeOpportunities = array_values(array_filter(
    $opportunities,
    static fn (array $opportunity): bool => ($opportunity['status'] ?? '') === 'active'
));
$applications = learner_ecosystem_applications();

function learner_ecosystem_date(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    return $parsed ? $parsed->format('d/m/Y') : $date;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Khám phá doanh nghiệp, trường học và các cơ hội phù hợp dành cho học sinh, sinh viên trên TalentHub.">
    <title>Hệ sinh thái &amp; Cơ hội | TalentHub</title>
    <link rel="stylesheet" href="../../assets/css/home.css">
    <link rel="stylesheet" href="../../assets/css/learner.css">
</head>
<body class="learner-app learner-page-ecosystem" data-ecosystem-page data-initial-tab="<?= learner_escape($initialTab); ?>">
    <div class="learner-layout">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="learner-main">
            <?php include __DIR__ . '/includes/header.php'; ?>

            <main class="learner-content" id="main-content">
                <section class="learner-ecosystem-hero" aria-labelledby="ecosystem-title">
                    <div>
                        <span class="learner-eyebrow">Kết nối tương lai</span>
                        <h1 id="ecosystem-title">Hệ sinh thái &amp; Cơ hội</h1>
                        <p>Khám phá doanh nghiệp, trường học và những cơ hội học tập — nghề nghiệp phù hợp với hành trình của bạn.</p>
                    </div>
                    <button class="learner-btn learner-btn--secondary" type="button" data-open-modal="learner-application-drawer">
                        <?= learner_icon('clipboard', 18); ?> Hồ sơ đã ứng tuyển
                        <span class="learner-count-badge"><?= count($applications); ?></span>
                    </button>
                </section>

                <nav class="learner-ecosystem-tabs" aria-label="Nội dung hệ sinh thái" role="tablist">
                    <button class="learner-ecosystem-tab" id="tab-enterprises" type="button" role="tab" aria-controls="panel-enterprises" aria-selected="<?= $initialTab === 'enterprises' ? 'true' : 'false'; ?>" data-ecosystem-tab="enterprises">
                        <?= learner_icon('briefcase', 19); ?> Doanh nghiệp
                    </button>
                    <button class="learner-ecosystem-tab" id="tab-schools" type="button" role="tab" aria-controls="panel-schools" aria-selected="<?= $initialTab === 'schools' ? 'true' : 'false'; ?>" data-ecosystem-tab="schools">
                        <?= learner_icon('graduation-cap', 19); ?> Trường học
                    </button>
                    <button class="learner-ecosystem-tab" id="tab-opportunities" type="button" role="tab" aria-controls="panel-opportunities" aria-selected="<?= $initialTab === 'opportunities' ? 'true' : 'false'; ?>" data-ecosystem-tab="opportunities">
                        <?= learner_icon('sparkles', 19); ?> Cơ hội
                        <span class="learner-count-badge"><?= count($activeOpportunities); ?></span>
                    </button>
                </nav>

                <section class="learner-ecosystem-toolbar learner-card" aria-label="Bộ lọc tìm kiếm">
                    <div class="learner-ecosystem-search">
                        <?= learner_icon('search', 19); ?>
                        <label class="learner-visually-hidden" for="ecosystem-local-search">Tìm trong hệ sinh thái</label>
                        <input id="ecosystem-local-search" type="search" placeholder="Nhập tên, lĩnh vực hoặc địa điểm..." data-ecosystem-search>
                    </div>
                    <label class="learner-select-control">
                        <span class="learner-visually-hidden">Lọc theo lĩnh vực</span>
                        <?= learner_icon('filter', 18); ?>
                        <select data-ecosystem-filter="field">
                            <option value="all">Tất cả lĩnh vực</option>
                            <option value="Công nghệ">Công nghệ</option>
                            <option value="AI">AI / Machine Learning</option>
                            <option value="Kinh doanh">Kinh doanh</option>
                            <option value="Học bổng">Học bổng</option>
                        </select>
                    </label>
                    <label class="learner-select-control">
                        <span class="learner-visually-hidden">Lọc theo địa điểm</span>
                        <?= learner_icon('map-pin', 18); ?>
                        <select data-ecosystem-filter="location">
                            <option value="all">Tất cả địa điểm</option>
                            <option value="Hà Nội">Hà Nội</option>
                            <option value="Trực tuyến">Trực tuyến</option>
                        </select>
                    </label>
                </section>

                <section id="panel-enterprises" class="learner-ecosystem-panel" role="tabpanel" aria-labelledby="tab-enterprises" <?= $initialTab !== 'enterprises' ? 'hidden' : ''; ?> data-ecosystem-panel="enterprises">
                    <div class="learner-section-heading learner-ecosystem-panel__heading">
                        <div>
                            <h2>Doanh nghiệp nổi bật</h2>
                            <p>Thông tin và vị trí thực tập được đồng bộ từ dữ liệu doanh nghiệp.</p>
                        </div>
                        <span><?= count($enterprises); ?> doanh nghiệp</span>
                    </div>
                    <div class="learner-partner-grid" data-ecosystem-results>
                        <?php foreach ($enterprises as $enterprise): ?>
                            <article class="learner-partner-card learner-card" data-ecosystem-item data-search="<?= learner_escape($enterprise['name'] . ' ' . $enterprise['industry'] . ' ' . $enterprise['location']); ?>" data-field="<?= learner_escape($enterprise['industry']); ?>" data-location="<?= learner_escape($enterprise['location']); ?>">
                                <div class="learner-partner-card__header">
                                    <span class="learner-partner-logo learner-partner-logo--enterprise"><?= learner_escape($enterprise['logo_text']); ?></span>
                                    <?php if ($enterprise['verified']): ?>
                                        <span class="learner-verified-pill"><?= learner_icon('check', 14); ?> Đã xác minh</span>
                                    <?php endif; ?>
                                </div>
                                <div class="learner-partner-card__body">
                                    <p class="learner-card-kicker">Doanh nghiệp công nghệ</p>
                                    <h3><?= learner_escape($enterprise['name']); ?></h3>
                                    <p><?= learner_escape($enterprise['description']); ?></p>
                                </div>
                                <div class="learner-meta-list">
                                    <span><?= learner_icon('briefcase', 16); ?> <?= learner_escape($enterprise['industry']); ?></span>
                                    <span><?= learner_icon('map-pin', 16); ?> <?= learner_escape($enterprise['location']); ?></span>
                                </div>
                                <div class="learner-partner-card__footer">
                                    <span><strong><?= learner_escape($enterprise['opportunity_count']); ?></strong> cơ hội đang mở</span>
                                    <a class="learner-btn learner-btn--outline" href="partner.php?type=enterprise&amp;id=<?= learner_escape($enterprise['id']); ?>">Xem doanh nghiệp <?= learner_icon('arrow-right', 16); ?></a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="learner-empty-state learner-card" hidden data-ecosystem-empty>
                        <span class="learner-empty-state__icon"><?= learner_icon('search', 24); ?></span>
                        <h2>Chưa tìm thấy doanh nghiệp phù hợp</h2>
                        <p>Thử thay đổi từ khóa hoặc bộ lọc để xem thêm kết quả.</p>
                    </div>
                </section>

                <section id="panel-schools" class="learner-ecosystem-panel" role="tabpanel" aria-labelledby="tab-schools" <?= $initialTab !== 'schools' ? 'hidden' : ''; ?> data-ecosystem-panel="schools">
                    <div class="learner-section-heading learner-ecosystem-panel__heading">
                        <div>
                            <h2>Trường học đối tác</h2>
                            <p>Dữ liệu demo để hoàn thiện luồng; sẵn sàng thay bằng API chính thức.</p>
                        </div>
                        <span><?= count($schools); ?> trường học</span>
                    </div>
                    <div class="learner-partner-grid" data-ecosystem-results>
                        <?php foreach ($schools as $school): ?>
                            <article class="learner-partner-card learner-card" data-ecosystem-item data-search="<?= learner_escape($school['name'] . ' ' . $school['school_type'] . ' ' . implode(' ', $school['programs']) . ' ' . $school['location']); ?>" data-field="<?= learner_escape(implode(' ', $school['programs'])); ?>" data-location="<?= learner_escape($school['location']); ?>">
                                <div class="learner-partner-card__header">
                                    <span class="learner-partner-logo learner-partner-logo--school"><?= learner_escape($school['logo_text']); ?></span>
                                    <span class="learner-demo-pill">Dữ liệu demo</span>
                                </div>
                                <div class="learner-partner-card__body">
                                    <p class="learner-card-kicker"><?= learner_escape($school['school_type']); ?></p>
                                    <h3><?= learner_escape($school['name']); ?></h3>
                                    <p><?= learner_escape($school['description']); ?></p>
                                </div>
                                <div class="learner-chip-list" aria-label="Ngành đào tạo nổi bật">
                                    <?php foreach (array_slice($school['programs'], 0, 3) as $program): ?>
                                        <span><?= learner_escape($program); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <div class="learner-partner-card__footer">
                                    <span><strong><?= learner_escape($school['opportunity_count']); ?></strong> chương trình nổi bật</span>
                                    <a class="learner-btn learner-btn--outline" href="partner.php?type=school&amp;id=<?= learner_escape($school['id']); ?>">Xem trường học <?= learner_icon('arrow-right', 16); ?></a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="learner-empty-state learner-card" hidden data-ecosystem-empty>
                        <span class="learner-empty-state__icon"><?= learner_icon('search', 24); ?></span>
                        <h2>Chưa tìm thấy trường học phù hợp</h2>
                        <p>Thử thay đổi từ khóa hoặc bộ lọc để xem thêm kết quả.</p>
                    </div>
                </section>

                <section id="panel-opportunities" class="learner-ecosystem-panel" role="tabpanel" aria-labelledby="tab-opportunities" <?= $initialTab !== 'opportunities' ? 'hidden' : ''; ?> data-ecosystem-panel="opportunities">
                    <div class="learner-section-heading learner-ecosystem-panel__heading">
                        <div>
                            <h2>Cơ hội dành cho bạn</h2>
                            <p>Thực tập doanh nghiệp, học bổng và hoạt động trải nghiệm trường học.</p>
                        </div>
                        <span><?= count($activeOpportunities); ?> cơ hội đang mở</span>
                    </div>
                    <div class="learner-opportunity-grid" data-ecosystem-results>
                        <?php foreach ($activeOpportunities as $opportunity): ?>
                            <article class="learner-opportunity-card learner-card" data-ecosystem-item data-search="<?= learner_escape($opportunity['title'] . ' ' . $opportunity['partner_name'] . ' ' . $opportunity['field'] . ' ' . $opportunity['location']); ?>" data-field="<?= learner_escape($opportunity['field']); ?>" data-location="<?= learner_escape($opportunity['location']); ?>">
                                <div class="learner-opportunity-card__top">
                                    <span class="learner-badge <?= $opportunity['partner_type'] === 'enterprise' ? 'learner-badge--primary' : 'learner-badge--secondary'; ?>">
                                        <?= learner_escape($opportunity['partner_type'] === 'enterprise' ? 'Thực tập' : 'Trường học'); ?>
                                    </span>
                                    <span class="learner-status-dot learner-status-dot--active"><?= learner_escape($opportunity['status_label']); ?></span>
                                </div>
                                <h3><?= learner_escape($opportunity['title']); ?></h3>
                                <a class="learner-opportunity-card__partner" href="partner.php?type=<?= learner_escape($opportunity['partner_type']); ?>&amp;id=<?= learner_escape($opportunity['partner_id']); ?>"><?= learner_escape($opportunity['partner_name']); ?></a>
                                <div class="learner-meta-list">
                                    <span><?= learner_icon('map-pin', 16); ?> <?= learner_escape($opportunity['location']); ?></span>
                                    <span><?= learner_icon('clock', 16); ?> Hạn <?= learner_escape(learner_ecosystem_date($opportunity['deadline'])); ?></span>
                                    <span><?= learner_icon('users', 16); ?> <?= learner_escape($opportunity['slots']); ?> vị trí</span>
                                </div>
                                <div class="learner-chip-list">
                                    <?php foreach (array_slice($opportunity['skills'], 0, 3) as $skill): ?>
                                        <span><?= learner_escape($skill); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <a class="learner-btn learner-btn--primary learner-btn--block" href="opportunity.php?type=<?= learner_escape($opportunity['type']); ?>&amp;id=<?= learner_escape($opportunity['id']); ?>">Xem chi tiết</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="learner-empty-state learner-card" hidden data-ecosystem-empty>
                        <span class="learner-empty-state__icon"><?= learner_icon('search', 24); ?></span>
                        <h2>Chưa tìm thấy cơ hội phù hợp</h2>
                        <p>Thử thay đổi từ khóa hoặc bộ lọc để xem thêm kết quả.</p>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <div class="learner-modal learner-modal--drawer" id="learner-application-drawer" hidden>
        <button class="learner-modal__backdrop" type="button" data-close-modal aria-label="Đóng bảng hồ sơ ứng tuyển"></button>
        <aside class="learner-modal__dialog learner-application-drawer" role="dialog" aria-modal="true" aria-labelledby="application-drawer-title">
            <div class="learner-modal__header">
                <div>
                    <span class="learner-modal__eyebrow">Tiến trình cá nhân</span>
                    <h2 id="application-drawer-title">Hồ sơ đã ứng tuyển</h2>
                    <p>Theo dõi trạng thái mới nhất của từng hồ sơ.</p>
                </div>
                <button class="learner-icon-button" type="button" data-close-modal aria-label="Đóng"><?= learner_icon('x', 21); ?></button>
            </div>

            <label class="learner-application-search">
                <?= learner_icon('search', 18); ?>
                <span class="learner-visually-hidden">Tìm hồ sơ ứng tuyển</span>
                <input type="search" placeholder="Tìm tên cơ hội hoặc doanh nghiệp..." data-application-search>
            </label>
            <div class="learner-application-filters" role="group" aria-label="Lọc trạng thái hồ sơ">
                <button type="button" aria-pressed="true" data-application-filter="all">Tất cả</button>
                <button type="button" aria-pressed="false" data-application-filter="reviewing">Đang xem xét</button>
                <button type="button" aria-pressed="false" data-application-filter="interview">Phỏng vấn</button>
                <button type="button" aria-pressed="false" data-application-filter="declined">Đã kết thúc</button>
            </div>

            <div class="learner-application-list" data-application-list>
                <?php foreach ($applications as $index => $application): ?>
                    <article class="learner-application-item" data-application-item data-status="<?= learner_escape($application['status']); ?>" data-search="<?= learner_escape($application['title'] . ' ' . $application['partner_name']); ?>">
                        <button class="learner-application-item__summary" type="button" aria-expanded="<?= $index === 0 ? 'true' : 'false'; ?>" data-application-toggle>
                            <span>
                                <small><?= learner_escape($application['partner_name']); ?></small>
                                <strong><?= learner_escape($application['title']); ?></strong>
                                <em>Gửi ngày <?= learner_escape($application['submitted_at']); ?></em>
                            </span>
                            <span class="learner-application-status learner-application-status--<?= learner_escape($application['status']); ?>"><?= learner_escape($application['status_label']); ?></span>
                            <?= learner_icon('chevron-down', 18); ?>
                        </button>
                        <div class="learner-application-item__details" <?= $index !== 0 ? 'hidden' : ''; ?> data-application-details>
                            <ol class="learner-application-timeline">
                                <?php foreach ($application['timeline'] as $timelineItem): ?>
                                    <li class="is-<?= learner_escape($timelineItem['state']); ?>">
                                        <span aria-hidden="true"></span>
                                        <div><strong><?= learner_escape($timelineItem['label']); ?></strong><small><?= learner_escape($timelineItem['date']); ?></small></div>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            <div class="learner-application-item__actions">
                                <a class="learner-btn learner-btn--outline" href="opportunity.php?type=<?= learner_escape($application['opportunity_type']); ?>&amp;id=<?= learner_escape($application['opportunity_id']); ?>">Xem cơ hội</a>
                                <?php if ($application['can_withdraw']): ?>
                                    <button class="learner-text-button learner-text-button--danger" type="button" data-withdraw-application>Rút hồ sơ</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="learner-application-empty" hidden data-application-empty>
                <?= learner_icon('file-text', 30); ?>
                <strong>Không có hồ sơ phù hợp</strong>
                <span>Thử chọn trạng thái hoặc từ khóa khác.</span>
            </div>
        </aside>
    </div>

    <script src="../../assets/js/learner.js"></script>
</body>
</html>
