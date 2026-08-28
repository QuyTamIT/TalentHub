(function () {
    'use strict';

    function initGroupMatching() {
        var container = document.querySelector('[data-ai-group-matches]');
        if (!container) {
            return;
        }

        var listContainer = container.querySelector('[data-group-matches-container]');
        if (!listContainer) {
            return;
        }

        function getCsrfToken() {
            var boot = document.getElementById('learner-session-boot');
            if (boot) {
                try {
                    var data = JSON.parse(boot.textContent || '{}');
                    if (data.csrfToken) {
                        return data.csrfToken;
                    }
                } catch (e) {
                    // Ignore parse error
                }
            }
            var meta = document.querySelector('meta[name="csrf-token"]') || document.querySelector('meta[name="csrfToken"]');
            return meta ? meta.getAttribute('content') : '';
        }

        function renderLoading() {
            listContainer.textContent = '';
            var loading = document.createElement('div');
            loading.className = 'learner-group-matches-state';
            var spinner = document.createElement('span');
            spinner.className = 'learner-ai-loading__spinner';
            spinner.setAttribute('aria-hidden', 'true');
            var text = document.createElement('p');
            text.textContent = 'Đang tìm kiếm nhóm và cộng đồng phù hợp với bạn...';
            loading.appendChild(spinner);
            loading.appendChild(text);
            listContainer.appendChild(loading);
        }

        function renderConsentRequired() {
            listContainer.textContent = '';
            var box = document.createElement('div');
            box.className = 'learner-group-matches-state';
            var title = document.createElement('p');
            title.textContent = 'Cần quyền sử dụng dữ liệu hoạt động để đề xuất nhóm học tập.';
            var link = document.createElement('a');
            link.className = 'learner-btn learner-btn--primary';
            link.href = 'profile.php';
            link.textContent = 'Quản lý quyền dữ liệu';
            box.appendChild(title);
            box.appendChild(link);
            listContainer.appendChild(box);
        }

        function renderEmpty(message) {
            listContainer.textContent = '';
            var box = document.createElement('div');
            box.className = 'learner-group-matches-state';
            var text = document.createElement('p');
            text.textContent = message || 'Chưa có nhóm hoặc cộng đồng phù hợp với hồ sơ hiện tại.';
            box.appendChild(text);
            listContainer.appendChild(box);
        }

        function renderItems(items) {
            listContainer.textContent = '';
            if (!Array.isArray(items) || items.length === 0) {
                renderEmpty();
                return;
            }

            var grid = document.createElement('div');
            grid.className = 'learner-group-matches-grid';

            items.forEach(function (item) {
                var card = document.createElement('article');
                card.className = 'learner-card learner-group-card';
                card.setAttribute('data-catalog-id', item.catalog_id || '');

                var header = document.createElement('div');
                header.className = 'learner-group-card__header';

                var badge = document.createElement('span');
                badge.className = 'learner-group-card__badge';
                badge.textContent = item.item_type === 'community' ? 'Cộng đồng' : 'Nhóm học tập';
                header.appendChild(badge);

                var score = document.createElement('span');
                score.className = 'learner-group-card__score';
                score.textContent = 'Độ phù hợp ' + (item.score || 0) + '%';
                header.appendChild(score);

                card.appendChild(header);

                var title = document.createElement('h3');
                title.className = 'learner-group-card__title';
                title.textContent = item.title || '';
                card.appendChild(title);

                if (item.summary) {
                    var desc = document.createElement('p');
                    desc.className = 'learner-group-card__summary';
                    desc.textContent = item.summary;
                    card.appendChild(desc);
                }

                if (Array.isArray(item.evidence) && item.evidence.length > 0) {
                    var evidenceList = document.createElement('div');
                    evidenceList.className = 'learner-group-card__evidence';
                    item.evidence.forEach(function (ev) {
                        var chip = document.createElement('span');
                        chip.className = 'learner-group-card__chip';
                        var typeLabel = ev.source_type === 'skill' ? 'Kỹ năng' :
                            (ev.source_type === 'assessment' ? 'Định hướng' :
                            (ev.source_type === 'roadmap' ? 'Mục tiêu' :
                            (ev.source_type === 'student_profile' ? 'Khối lớp' : 'Lịch trình')));
                        chip.textContent = typeLabel;
                        evidenceList.appendChild(chip);
                    });
                    card.appendChild(evidenceList);
                }

                var meta = document.createElement('div');
                meta.className = 'learner-group-card__meta';
                if (item.availability && typeof item.availability.remaining === 'number') {
                    var cap = document.createElement('span');
                    cap.textContent = 'Còn ' + item.availability.remaining + ' chỗ';
                    meta.appendChild(cap);
                }
                card.appendChild(meta);

                var actionBtn = document.createElement('button');
                actionBtn.className = 'learner-btn learner-btn--primary learner-group-card__action';
                actionBtn.type = 'button';
                var actionData = item.action || {};
                var actionType = actionData.type || 'join_group';
                actionBtn.textContent = actionType === 'join_group' ? 'Tham gia nhóm' : 'Xem chi tiết';

                actionBtn.addEventListener('click', function () {
                    actionBtn.disabled = true;
                    actionBtn.textContent = 'Đang xử lý...';

                    var csrfToken = getCsrfToken();
                    var idempotencyKey = 'grp-' + Date.now() + '-' + Math.random().toString(36).substring(2, 9);

                    fetch('/app/learner/api/v1/ai-group-matches.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken,
                            'X-Idempotency-Key': idempotencyKey,
                        },
                        body: JSON.stringify({
                            catalog_id: item.catalog_id,
                            action: actionType,
                        }),
                    })
                    .then(function (res) {
                        return res.json();
                    })
                    .then(function (json) {
                        if (json && json.data) {
                            var data = json.data;
                            if ((data.state === 'action_ready' || data.state === 'catalog_opened') && data.url) {
                                if (data.url.startsWith('/') && !data.url.startsWith('//')) {
                                    window.location.href = data.url;
                                    return;
                                }
                            }
                            if (data.state === 'join_unavailable') {
                                actionBtn.textContent = 'Không khả dụng';
                                actionBtn.disabled = true;
                                return;
                            }
                        }
                        actionBtn.textContent = 'Thử lại';
                        actionBtn.disabled = false;
                    })
                    .catch(function () {
                        actionBtn.textContent = 'Thử lại';
                        actionBtn.disabled = false;
                    });
                });

                card.appendChild(actionBtn);
                grid.appendChild(card);
            });

            listContainer.appendChild(grid);
        }

        renderLoading();

        fetch('/app/learner/api/v1/ai-group-matches.php?limit=6', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
            },
        })
        .then(function (res) {
            return res.json();
        })
        .then(function (json) {
            if (!json || !json.data) {
                renderEmpty('Không thể tải gợi ý nhóm lúc này.');
                return;
            }
            var data = json.data;
            if (data.state === 'consent_required') {
                renderConsentRequired();
            } else if (data.state === 'data_insufficient') {
                renderEmpty('Chưa đủ dữ liệu đánh giá hoặc kỹ năng để tìm nhóm phù hợp.');
            } else {
                renderItems(data.items || []);
            }
        })
        .catch(function () {
            renderEmpty('Dịch vụ ghép nhóm tạm thời không khả dụng.');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGroupMatching);
    } else {
        initGroupMatching();
    }
})();