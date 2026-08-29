<?php

declare(strict_types=1);

function roadmap_render_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

$page = (string) file_get_contents(dirname(__DIR__) . '/app/learner/ai-recommendations.php');
$client = (string) file_get_contents(dirname(__DIR__) . '/assets/js/learner-ai-roadmap.js');
$recommendationClient = (string) file_get_contents(dirname(__DIR__) . '/assets/js/learner-recommendations.js');
$opportunitySource = (string) file_get_contents(dirname(__DIR__) . '/app/learner/ai/Sources/Database/DatabaseOpportunitySource.php');

roadmap_render_assert(str_contains($page, 'AI GỢI Ý'), 'page uses the approved AI title');
roadmap_render_assert(str_contains($page, 'LỘ TRÌNH PHÁT TRIỂN 90 NGÀY'), '90-day roadmap is the dominant region');
roadmap_render_assert(str_contains($page, 'Hướng phát triển ưu tiên'), 'primary direction region exists');
foreach ([
    'Hoạt động, dự án và cơ hội dành cho bạn',
    'Huy hiệu &amp; chứng chỉ phù hợp',
    'CỘNG ĐỒNG &amp; NHÓM HỌC TẬP',
    'Nhóm phù hợp',
    'Dữ liệu AI đã sử dụng',
    'Thông tin kỹ thuật',
    'Gợi ý này hữu ích với bạn chứ?',
] as $removedCopy) {
    roadmap_render_assert(!str_contains($page, $removedCopy), "roadmap page removes secondary copy: {$removedCopy}");
}
foreach (['data-ai-page', 'data-ai-group-matches', 'data-roadmap-evidence>', 'data-roadmap-engine>', 'data-roadmap-feedback '] as $removedHook) {
    roadmap_render_assert(!str_contains($page, $removedHook), "roadmap page removes secondary hook: {$removedHook}");
}
roadmap_render_assert(!str_contains($page, 'learner-recommendations.js') && !str_contains($page, 'learner-ai-groups.js'), 'roadmap page does not load secondary recommendation bundles');
roadmap_render_assert(str_contains($recommendationClient, 'learner-ai-result-list__empty'), 'empty recommendation results render an explicit user-facing state');
roadmap_render_assert(str_contains($recommendationClient, 'learner-ai-result-group__count') && str_contains($recommendationClient, 'dataset.aiItemType'), 'recommendation cards expose semantic grouping and type metadata');
roadmap_render_assert(str_contains($recommendationClient, 'learner-ai-result__source-facts') && str_contains($recommendationClient, 'Xem trong Hệ sinh thái'), 'enterprise-backed recommendation cards expose canonical source facts and ecosystem navigation');
roadmap_render_assert(str_contains($recommendationClient, 'catalog?.safe_value?.title'), 'published project and opportunity cards display the canonical database title instead of a model-invented title');
roadmap_render_assert(str_contains($opportunitySource, '/app/learner/ecosystem.php?tab=opportunities&focus=') && str_contains($opportunitySource, "'opportunity_id' =>"), 'enterprise recommendation evidence deep-links to its canonical ecosystem record');
roadmap_render_assert(str_contains($client, 'textContent'), 'untrusted model strings use textContent');
roadmap_render_assert(!str_contains($client . $recommendationClient, 'innerHTML'), 'renderers never assign HTML from model output');
roadmap_render_assert(!str_contains($page . $client . $recommendationClient, 'TALENTHUB_AI_API_KEY'), 'API key is never rendered');
roadmap_render_assert(!str_contains($page . $client, 'input_hash'), 'input hash is never rendered');
roadmap_render_assert(!str_contains($client, "'ready_rule'"), 'strict roadmap client must not recognize ready_rule as a renderable state');
roadmap_render_assert(!str_contains($client, "'fallback_rule'"), 'strict roadmap client must not recognize fallback_rule as a renderable state');
roadmap_render_assert(!str_contains($client, "'rule_fallback'"), 'strict roadmap client must not recognize rule_fallback as a renderable state');
roadmap_render_assert(!str_contains($client, "'fallback-rule'"), 'strict roadmap client must not present a rule fallback state');
roadmap_render_assert(!str_contains($page, 'data-roadmap-fallback'), 'strict roadmap page must not render the legacy fallback region');
roadmap_render_assert(!str_contains($page . $client, 'Gợi ý dự phòng theo quy tắc'), 'strict roadmap must not present rule fallback copy');

echo "learner_ai_recommendation_render_test: OK\n";
