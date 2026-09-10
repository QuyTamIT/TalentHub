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

test('falls back to alternative_directions when potential_paths is empty', () => {
    const payload = {
        potential_paths: [],
        alternative_directions: [
            {
                label: 'Quản lý Sản phẩm Kỹ thuật',
                rationale: 'Tận dụng tốt kỹ năng lập trình và tư duy tổ chức',
                evidence_ref_ids: ['skill_pm', 'activity_agile'],
            },
            {
                label: 'Chuyên gia Phân tích Dữ liệu',
                rationale: 'Phù hợp thế mạnh tư duy logic và toán học',
                evidence_ref_ids: ['assessment_logic'],
            },
            {
                label: 'Kỹ sư An ninh Mạng',
                rationale: 'Thừa một hướng vì tối đa chỉ lấy 2',
                evidence_ref_ids: ['cert_security'],
            },
        ],
    };
    const vm = buildRoadmapViewModel(payload);
    assert.deepEqual(vm.potentialPaths, [
        {
            label: 'Quản lý Sản phẩm Kỹ thuật: Tận dụng tốt kỹ năng lập trình và tư duy tổ chức',
            evidence_ref_ids: ['skill_pm', 'activity_agile'],
        },
        {
            label: 'Chuyên gia Phân tích Dữ liệu: Phù hợp thế mạnh tư duy logic và toán học',
            evidence_ref_ids: ['assessment_logic'],
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

test('falls back to potential insights when both potential_paths and alternative_directions are empty', () => {
    const payload = {
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
                evidence_ref_ids: ['project_backend'],
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
            evidence_ref_ids: ['project_backend'],
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
