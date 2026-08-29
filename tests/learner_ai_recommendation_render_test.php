<?php

declare(strict_types=1);

function roadmap_render_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: ' . $message);
}

$page = (string) file_get_contents(dirname(__DIR__) . '/app/learner/ai-recommendations.php');
$client = (string) file_get_contents(dirname(__DIR__) . '/assets/js/learner-ai-roadmap.js');
$recommendationClient = (string) file_get_contents(dirname(__DIR__) . '/assets/js/learner-recommendations.js');

roadmap_render_assert(str_contains($page, 'AI GỢI Ý'), 'page uses the approved AI title');
roadmap_render_assert(str_contains($page, 'LỘ TRÌNH PHÁT TRIỂN 90 NGÀY'), '90-day roadmap is the dominant region');
roadmap_render_assert(str_contains($page, 'Hướng phát triển ưu tiên'), 'primary direction region exists');
roadmap_render_assert(str_contains($page, 'Dữ liệu AI đã sử dụng'), 'evidence region exists');
roadmap_render_assert(str_contains($page, 'Thông tin kỹ thuật'), 'technical provenance is collapsed');
roadmap_render_assert(str_contains($page, '<details'), 'evidence and engine metadata use native collapsed disclosure');
roadmap_render_assert(str_contains($page, 'learner-recommendations.js') && str_contains($page, 'data-ai-result-list'), 'live catalog recommendation renderer is mounted beside the roadmap');
roadmap_render_assert(str_contains($recommendationClient, 'learner-ai-result-list__empty'), 'empty recommendation results render an explicit user-facing state');
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
