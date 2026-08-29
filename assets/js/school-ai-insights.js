(function schoolAiInsights(global) {
  'use strict';

  const STATE_COPY = {
    ready_model: 'Phân tích AI đã sẵn sàng.',
    stale_model: 'Đang hiển thị phân tích AI gần nhất trong khi hệ thống cập nhật.',
    pending: 'AI đang tổng hợp dữ liệu nhóm.',
    insufficient_data: 'Chưa đủ quy mô nhóm để tạo phân tích AI.',
    provider_unavailable: 'AI tạm thời không khả dụng. Vui lòng thử lại sau.'
  };

  async function load(root, fetcher = global.fetch) {
    const state = root.querySelector('[data-school-ai-state]');
    const content = root.querySelector('[data-school-ai-content]');
    const summary = root.querySelector('[data-school-ai-summary]');
    const priorities = root.querySelector('[data-school-ai-priorities]');
    const cohorts = root.querySelector('[data-school-ai-cohorts]');
    const freshness = root.querySelector('[data-school-ai-freshness]');
    const modelVersion = root.querySelector('[data-school-ai-model-version]');
    const generatedAt = root.querySelector('[data-school-ai-generated-at]');
    const provenance = root.querySelector('[data-school-ai-provenance]');

    try {
      const response = await fetcher('/api/v1/schools/me/ai-insights', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      });
      if (!response.ok) {
        throw new Error('unavailable');
      }
      const envelope = await response.json();
      const payload = envelope?.data ?? envelope;
      const stateKey = payload?.state;

      if (state) {
        state.textContent = STATE_COPY[stateKey] || STATE_COPY.provider_unavailable;
      }

      if (!['ready_model', 'stale_model'].includes(stateKey)) {
        if (content) {
          content.hidden = true;
        }
        return;
      }

      if (summary) {
        summary.textContent = String(payload?.explanation?.summary || '');
      }

      if (priorities) {
        priorities.replaceChildren();
        const items = Array.isArray(payload?.explanation?.priorities) ? payload.explanation.priorities : [];
        for (const text of items) {
          const li = document.createElement('li');
          li.textContent = String(text);
          priorities.appendChild(li);
        }
      }

      if (cohorts) {
        cohorts.replaceChildren();
        const cohortList = Array.isArray(payload?.aggregate?.cohorts) ? payload.aggregate.cohorts : [];
        for (const cohort of cohortList) {
          const item = document.createElement('p');
          const count = Number(cohort.student_count || 0);
          const trendsCount = Array.isArray(cohort.trend_signals) ? cohort.trend_signals.length : 0;
          item.textContent = `${String(cohort.label || cohort.cohort_key || 'Nhóm')} · ${count} học sinh · ${trendsCount} xu hướng`;
          cohorts.appendChild(item);
        }
      }

      if (freshness) {
        freshness.textContent = payload.freshness_status === 'stale' ? 'Trạng thái: Bản lưu tạm (stale)' : 'Trạng thái: Mới nhất';
      }

      if (modelVersion) {
        modelVersion.textContent = payload.model_version ? ` · Mô hình: ${payload.model_version}` : '';
      }

      if (generatedAt) {
        generatedAt.textContent = payload.generated_at ? ` · Tạo lúc: ${payload.generated_at}` : '';
      }

      if (provenance) {
        const minCohort = payload?.aggregate?.minimum_cohort || 5;
        const originLabel = payload.analysis_origin === 'model' ? 'Gemini trên dữ liệu tổng hợp' : 'Dữ liệu tổng hợp';
        provenance.textContent = `Nguồn: ${originLabel} · Ngưỡng bảo mật tối thiểu: ${minCohort} học sinh`;
      }

      if (content) {
        content.hidden = false;
      }
    } catch {
      if (state) {
        state.textContent = STATE_COPY.provider_unavailable;
      }
      if (content) {
        content.hidden = true;
      }
    }
  }

  function boot() {
    const root = global.document?.querySelector('[data-school-ai-insight]');
    if (root) {
      load(root);
    }
  }

  const api = { load };
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  if (global.document) {
    if (global.document.readyState === 'loading') {
      global.document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
      boot();
    }
  }
})(typeof window !== 'undefined' ? window : globalThis);
