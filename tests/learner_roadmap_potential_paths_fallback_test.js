'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');
const { buildRoadmapViewModel } = require('../assets/js/learner-ai-roadmap.js');

test('uses potential_paths when provided in payload', () => {
    const payload = {
        potential_paths: [
            { label: 'Kỹ sư Dữ liệu', evidence_ref_ids: ['assessment_1', 'skill_sql'] },
            { label: 'Chuyên viên AI', evidence_ref_ids: ['activity_hackathon'] },
        ],
        alternative_directions: [
            { label: 'Quản lý Sản phẩm', rationale: 'Phù hợp kỹ năng mềm' },
        ],
        insights: [
            { category: 'potential', title: 'Phát triển Cloud', summary: 'Có tiềm năng' },
        ],
    };
    const vm = buildRoadmapViewModel(payload);
    assert.deepEqual(vm.potentialPaths, [
        { label: 'Kỹ sư Dữ liệu', evidence_ref_ids: ['assessment_1', 'skill_sql'] },
        { label: 'Chuyên viên AI', evidence_ref_ids: ['activity_hackathon'] },
    ]);
});

test('falls back to alternative_directions when potential_paths is empty and inherits roadmap evidence', () => {
    const payload = {
        evidence: ['evidence-001', 'evidence-002', 'evidence-003'],
        potential_paths: [],
        alternative_directions: [
            {
                label: 'Quản lý Sản phẩm Kỹ thuật',
                rationale: 'Tận dụng tốt kỹ năng lập trình và tư duy tổ chức',
            },
            {
                label: 'Chuyên gia Phân tích Dữ liệu',
                rationale: 'Phù hợp thế mạnh tư duy logic và toán học',
            },
            {
                label: 'Kỹ sư An ninh Mạng',
                rationale: 'Thừa một hướng vì tối đa chỉ lấy 2',
            },
        ],
    };
    const vm = buildRoadmapViewModel(payload);
    assert.deepEqual(vm.potentialPaths, [
        {
            label: 'Quản lý Sản phẩm Kỹ thuật: Tận dụng tốt kỹ năng lập trình và tư duy tổ chức',
            evidence_ref_ids: ['evidence-001', 'evidence-002', 'evidence-003'],
        },
        {
            label: 'Chuyên gia Phân tích Dữ liệu: Phù hợp thế mạnh tư duy logic và toán học',
            evidence_ref_ids: ['evidence-001', 'evidence-002', 'evidence-003'],
        },
    ]);
});

test('preserves explicit evidence_ref_ids on alternative_directions when present', () => {
    const payload = {
        evidence: ['evidence-001', 'evidence-002', 'evidence-003'],
        potential_paths: [],
        alternative_directions: [
            {
                label: 'Quản lý Sản phẩm Kỹ thuật',
                rationale: 'Tận dụng tốt kỹ năng lập trình',
                evidence_ref_ids: ['custom-skill-pm'],
            },
        ],
    };
    const vm = buildRoadmapViewModel(payload);
    assert.deepEqual(vm.potentialPaths, [
        {
            label: 'Quản lý Sản phẩm Kỹ thuật: Tận dụng tốt kỹ năng lập trình',
            evidence_ref_ids: ['custom-skill-pm'],
        },
    ]);
});

test('formats label without colon when alternative_directions item has no rationale', () => {
    const payload = {
        potential_paths: [],
        alternative_directions: [
            { label: 'Kỹ sư DevOps', evidence_ref_ids: ['skill_docker'] },
        ],
    };
    const vm = buildRoadmapViewModel(payload);
    assert.deepEqual(vm.potentialPaths, [
        {
            label: 'Kỹ sư DevOps',
            evidence_ref_ids: ['skill_docker'],
        },
    ]);
});

test('guards against leading colon when label is missing and trailing colon when rationale is missing', () => {
    const payloadDirections = {
        evidence: ['evidence-001'],
        potential_paths: [],
        alternative_directions: [
            {
                label: '',
                rationale: 'Chỉ có lý do định hướng',
            },
            {
                label: '   ',
                rationale: 'Lý do khi label chỉ có khoảng trắng',
            },
            {
                label: 'Chỉ có nhãn định hướng',
                rationale: '',
            },
        ],
    };
    const vmDirections = buildRoadmapViewModel(payloadDirections);
    assert.deepEqual(vmDirections.potentialPaths, [
        {
            label: 'Chỉ có lý do định hướng',
            evidence_ref_ids: ['evidence-001'],
        },
        {
            label: 'Lý do khi label chỉ có khoảng trắng',
            evidence_ref_ids: ['evidence-001'],
        },
    ]);

    const payloadInsights = {
        evidence: ['evidence-002'],
        potential_paths: [],
        alternative_directions: [],
        insights: [
            {
                category: 'potential',
                title: '',
                summary: 'Chỉ có tóm tắt tiềm năng',
            },
            {
                category: 'potential',
                title: 'Chỉ có tiêu đề tiềm năng',
                summary: '',
            },
        ],
    };
    const vmInsights = buildRoadmapViewModel(payloadInsights);
    assert.deepEqual(vmInsights.potentialPaths, [
        {
            label: 'Chỉ có tóm tắt tiềm năng',
            evidence_ref_ids: ['evidence-002'],
        },
        {
            label: 'Chỉ có tiêu đề tiềm năng',
            evidence_ref_ids: ['evidence-002'],
        },
    ]);
});

test('falls back to potential insights when both potential_paths and alternative_directions are empty', () => {
    const payload = {
        evidence: ['evidence-001', 'evidence-002'],
        potential_paths: [],
        alternative_directions: [],
        insights: [
            {
                category: 'strength',
                title: 'Tư duy logic tốt',
                summary: 'Điểm mạnh nổi bật',
                evidence_ref_ids: ['eval_1'],
            },
            {
                category: 'potential',
                title: 'Phát triển năng lực Cloud Computing',
                summary: 'Có thể mở rộng sang mảng AWS / Azure trong 6 tháng tới',
                evidence_ref_ids: ['course_cloud'],
            },
            {
                category: 'potential',
                title: 'Nghiên cứu Kiến trúc Vi dịch vụ',
                summary: 'Phù hợp khi hệ thống cần mở rộng quy mô',
            },
            {
                category: 'potential',
                title: 'Thừa mục thứ 3',
                summary: 'Chỉ lấy 2 mục đầu tiên',
                evidence_ref_ids: ['extra_ref'],
            },
        ],
    };
    const vm = buildRoadmapViewModel(payload);
    assert.deepEqual(vm.potentialPaths, [
        {
            label: 'Phát triển năng lực Cloud Computing: Có thể mở rộng sang mảng AWS / Azure trong 6 tháng tới',
            evidence_ref_ids: ['course_cloud'],
        },
        {
            label: 'Nghiên cứu Kiến trúc Vi dịch vụ: Phù hợp khi hệ thống cần mở rộng quy mô',
            evidence_ref_ids: ['evidence-001', 'evidence-002'],
        },
    ]);
});

test('formats label without colon when potential insight has no summary', () => {
    const payload = {
        potential_paths: [],
        alternative_directions: [],
        insights: [
            {
                category: 'potential',
                title: 'Kỹ thuật Prompt nâng cao',
                evidence_ref_ids: ['ai_lab'],
            },
        ],
    };
    const vm = buildRoadmapViewModel(payload);
    assert.deepEqual(vm.potentialPaths, [
        {
            label: 'Kỹ thuật Prompt nâng cao',
            evidence_ref_ids: ['ai_lab'],
        },
    ]);
});

test('caps defaultEvidence to first 3 string items from payload.evidence', () => {
    const payload = {
        evidence: ['ev-1', 123, 'ev-2', null, 'ev-3', 'ev-4'],
        potential_paths: [],
        alternative_directions: [
            { label: 'Hướng đi 1', rationale: 'Lý do 1' },
        ],
    };
    const vm = buildRoadmapViewModel(payload);
    assert.deepEqual(vm.potentialPaths[0].evidence_ref_ids, ['ev-1', 'ev-2', 'ev-3']);
});

test('handles null and undefined payload gracefully', () => {
    const vmNull = buildRoadmapViewModel(null);
    assert.ok(vmNull);
    assert.deepEqual(vmNull.potentialPaths, []);
    assert.deepEqual(vmNull.phases, []);
    assert.equal(vmNull.currentPhaseIndex, -1);
    assert.equal(vmNull.overallPercent, 0);

    const vmUndefined = buildRoadmapViewModel(undefined);
    assert.ok(vmUndefined);
    assert.deepEqual(vmUndefined.potentialPaths, []);
    assert.deepEqual(vmUndefined.phases, []);
    assert.equal(vmUndefined.currentPhaseIndex, -1);
    assert.equal(vmUndefined.overallPercent, 0);
});

test('returns empty array when all potential sources are empty', () => {
    const payload = {
        potential_paths: [],
        alternative_directions: [],
        insights: [
            { category: 'improvement', title: 'Cần cải thiện giao tiếp' },
        ],
    };
    const vm = buildRoadmapViewModel(payload);
    assert.deepEqual(vm.potentialPaths, []);
});

test('returns empty array when payload has no potential fields at all', () => {
    const payload = {};
    const vm = buildRoadmapViewModel(payload);
    assert.deepEqual(vm.potentialPaths, []);
});
