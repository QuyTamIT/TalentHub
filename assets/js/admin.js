(() => {
  'use strict';

  const root = document.documentElement;
  const sidebar = document.querySelector('#admin-sidebar');
  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
  const sidebarClose = document.querySelector('[data-sidebar-close]');
  const commandDialog = document.querySelector('[data-command-dialog]');
  const commandInput = document.querySelector('[data-command-input]');
  const commandResults = document.querySelector('[data-command-results]');
  const commandDefaultMarkup = commandResults?.innerHTML || '';
  const toast = document.querySelector('[data-toast]');
  let lastCommandTrigger = null;
  let toastTimer = null;

  const showToast = (message) => {
    if (!toast) return;
    toast.textContent = message;
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.hidden = true; }, 2800);
  };

  const closeSidebar = () => {
    if (!sidebar || !sidebarToggle || !sidebarClose) return;
    sidebar.classList.remove('is-open');
    sidebarToggle.setAttribute('aria-expanded', 'false');
    sidebarClose.hidden = true;
  };

  sidebarToggle?.addEventListener('click', () => {
    const open = !sidebar?.classList.contains('is-open');
    sidebar?.classList.toggle('is-open', open);
    sidebarToggle.setAttribute('aria-expanded', String(open));
    if (sidebarClose) sidebarClose.hidden = !open;
  });
  sidebarClose?.addEventListener('click', closeSidebar);
  window.addEventListener('resize', () => { if (window.innerWidth > 860) closeSidebar(); });

  root.dataset.theme = 'light';
  localStorage.removeItem('talenthub-admin-theme');

  const openCommand = (trigger) => {
    if (!commandDialog) return;
    lastCommandTrigger = trigger || document.activeElement;
    if (commandInput) commandInput.value = '';
    if (commandResults) commandResults.innerHTML = commandDefaultMarkup;
    commandDialog.showModal();
    requestAnimationFrame(() => commandInput?.focus());
  };
  const closeCommand = () => {
    commandDialog?.close();
    if (lastCommandTrigger instanceof HTMLElement) lastCommandTrigger.focus();
  };
  document.querySelectorAll('[data-command-open]').forEach((button) => button.addEventListener('click', () => openCommand(button)));
  document.querySelector('[data-command-close]')?.addEventListener('click', closeCommand);
  commandDialog?.addEventListener('click', (event) => { if (event.target === commandDialog) closeCommand(); });
  commandDialog?.addEventListener('cancel', (event) => { event.preventDefault(); closeCommand(); });
  document.addEventListener('keydown', (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      commandDialog?.open ? closeCommand() : openCommand(document.activeElement);
    }
  });

  document.querySelector('[data-refresh]')?.addEventListener('click', (event) => {
    const button = event.currentTarget;
    button.disabled = true;
    button.textContent = 'Đang đồng bộ...';
    refreshDashboard().finally(() => {
      button.disabled = false;
      button.textContent = 'Đồng bộ dữ liệu';
      showToast('Đã làm mới dữ liệu vận hành.');
    });
  });

  const orgFilter = document.querySelector('[data-org-filter]');
  orgFilter?.addEventListener('input', () => {
    const query = orgFilter.value.trim().toLocaleLowerCase('vi');
    document.querySelectorAll('[data-dashboard-organizations] [data-org-row]').forEach((row) => { row.hidden = query !== '' && !row.textContent.toLocaleLowerCase('vi').includes(query); });
  });

  const basePath = location.pathname.includes('/app/') ? location.pathname.split('/app/')[0] : '';
  const api = (path, options = {}) => fetch(`${basePath}/api/v1${path}`, { credentials: 'same-origin', ...options }).then(async (response) => {
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload?.error?.message || `HTTP ${response.status}`);
    return payload.data;
  });
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
  const statusLabels = {active:'Hoạt động',draft:'Bản nháp',published:'Đã công bố',ongoing:'Đang diễn ra',completed:'Hoàn thành',archived:'Đã lưu trữ',pending:'Chờ duyệt',suspended:'Tạm khóa',disabled:'Vô hiệu hóa',verified:'Đã xác minh',rejected:'Từ chối',inactive:'Chưa kích hoạt',paid:'Đã thanh toán',pending_payment:'Chờ thanh toán',pledged:'Đã cam kết',refunded:'Đã hoàn tiền',cancelled:'Đã hủy',sent:'Đã gửi',failed:'Thất bại',submitted:'Đã nộp',reviewing:'Đang xem xét',interview:'Phỏng vấn',accepted:'Đã chấp nhận',declined:'Đã từ chối',withdrawn:'Đã rút',invited:'Được mời',applied:'Đã ứng tuyển'};
  const roleLabels = {student:'Học sinh',teacher:'Giáo viên',school:'Nhà trường',enterprise:'Doanh nghiệp',platform_admin:'Quản trị nền tảng'};
  const schoolLevelLabel = (code) => ({cap2:'Cấp 2',cap3:'Cấp 3',cao_dang_dai_hoc:'Cao đẳng / Đại học'}[String(code || '').toLowerCase()] || '—');
  const commandActions = [
    {section:'dashboard', title:'Tổng quan vận hành', description:'Mở Dashboard Admin', keywords:'tong quan dashboard van hanh trang chu'},
    {section:'users', title:'Quản lý người dùng', description:'Tìm, tạo và cập nhật tài khoản', keywords:'nguoi dung user tai khoan hoc sinh giao vien admin'},
    {section:'organizations', title:'Quản lý tổ chức', description:'Xem trường học, doanh nghiệp và xác minh', keywords:'to chuc truong hoc doanh nghiep school enterprise xac minh'},
    {section:'activities', title:'Học tập & hoạt động', description:'Theo dõi hoạt động và lịch', keywords:'hoat dong hoc tap lich su kien'},
    {section:'applications', title:'Cơ hội & ứng tuyển', description:'Theo dõi quy trình ứng tuyển', keywords:'ung tuyen co hoi internship ho so'},
    {section:'payments', title:'Tài trợ & thanh toán', description:'Theo dõi lệnh thanh toán', keywords:'thanh toan tai tro payment'},
    {section:'notifications', title:'Thông báo', description:'Theo dõi trạng thái gửi thông báo', keywords:'thong bao notification'},
    {section:'audit', title:'Audit & bảo mật', description:'Tra cứu sự kiện và Request ID', keywords:'audit bao mat request id nhat ky'},
    {section:'rbac', title:'RBAC & quyền', description:'Quản lý vai trò và quyền', keywords:'rbac quyen vai tro permission role'},
    {section:'system', title:'Hệ thống', description:'Xem trạng thái runtime và migration', keywords:'he thong system migration database php'},
  ];
  let commandRequest = 0;
  const normalizeSearch = (value) => String(value ?? '').toLocaleLowerCase('vi').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd');
  const renderCommandResults = (query, users, organizations) => {
    if (!commandResults) return;
    if (query === '') {
      commandResults.innerHTML = commandDefaultMarkup;
      return;
    }
    const normalizedQuery = normalizeSearch(query);
    const resultButton = (section, item, title, subtitle, icon) => `<button type="button" data-command-result data-section="${section}" data-id="${escapeHtml(item.id)}"><span class="command-item-icon">${icon}</span><span><strong>${escapeHtml(title)}</strong><small>${escapeHtml(subtitle)}</small></span><kbd>↵</kbd></button>`;
    const actionResults = commandActions.filter((action) => normalizeSearch(`${action.title} ${action.description} ${action.keywords}`).includes(normalizedQuery)).slice(0, 6).map((action) => `<button type="button" data-command-action data-section="${action.section}"><span class="command-item-icon">⚡</span><span><strong>${escapeHtml(action.title)}</strong><small>${escapeHtml(action.description)}</small></span><kbd>↵</kbd></button>`).join('');
    const userResults = users.slice(0, 6).map((user) => resultButton('users', user, user.fullName || user.email, `${user.email || '—'} · ${roleLabels[user.role] || user.role || 'Người dùng'}`, '👤')).join('');
    const organizationResults = organizations.slice(0, 6).map((organization) => resultButton('organizations', organization, organization.name || 'Tổ chức', `${roleLabels[organization.type] || organization.type || 'Tổ chức'} · ${organization.email || organization.verificationStatus || '—'}`, '🏢')).join('');
    commandResults.innerHTML = (actionResults ? `<p class="command-group-label">Chức năng gợi ý</p>${actionResults}` : '') + (userResults ? `<p class="command-group-label">Người dùng</p>${userResults}` : '') + (organizationResults ? `<p class="command-group-label">Tổ chức</p>${organizationResults}` : '') || '<p class="command-empty">Không tìm thấy dữ liệu hoặc chức năng phù hợp.</p>';
  };
  commandInput?.addEventListener('input', async () => {
    if (!commandResults) return;
    const query = commandInput.value.trim();
    const request = ++commandRequest;
    if (query === '') {
      renderCommandResults('', [], []);
      return;
    }
    commandResults.innerHTML = '<p class="command-empty">Đang tìm kiếm…</p>';
    try {
      const [users, organizations] = await Promise.all([
        api(`/admin/users?search=${encodeURIComponent(query)}`),
        api(`/admin/organizations?search=${encodeURIComponent(query)}`),
      ]);
      if (request !== commandRequest || commandInput.value.trim() !== query) return;
      renderCommandResults(query, users.items || [], organizations.items || []);
    } catch (error) {
      if (request === commandRequest) commandResults.innerHTML = `<p class="command-empty">Không thể tìm kiếm: ${escapeHtml(error.message)}</p>`;
    }
  });
  commandResults?.addEventListener('click', (event) => {
    const action = event.target.closest('[data-command-action]');
    if (action) {
      closeCommand();
      loadSection(action.dataset.section);
      return;
    }
    const result = event.target.closest('[data-command-result]');
    if (result) {
      closeCommand();
      loadSection(result.dataset.section, result.dataset.id);
      return;
    }
    const shortcut = event.target.closest('[data-command-item]');
    if (!shortcut) return;
    const targets = ['users', 'organizations', 'audit'];
    const index = [...commandResults.querySelectorAll('[data-command-item]')].indexOf(shortcut);
    closeCommand();
    document.querySelector(`[data-admin-section="${targets[index] || 'dashboard'}"]`)?.click();
  });
  const columnLabels = {fullName:'Họ và tên',email:'Email',role:'Vai trò',status:'Trạng thái',createdAt:'Ngày tạo',expiresAt:'Hết hạn',lastLoginAt:'Đăng nhập gần nhất',name:'Tổ chức',type:'Loại',verificationStatus:'Xác minh',title:'Tiêu đề',category:'Danh mục',schoolName:'Nhà trường',startAt:'Bắt đầu',endAt:'Kết thúc',capacity:'Sức chứa',postTitle:'Vị trí thực tập',studentName:'Ứng viên',matchScore:'Độ phù hợp',appliedAt:'Ngày ứng tuyển',reviewedAt:'Ngày xem xét',orderCode:'Mã thanh toán',enterpriseName:'Doanh nghiệp',amount:'Số tiền',currency:'Tiền tệ',paymentMethod:'Phương thức',paymentStatus:'Thanh toán',provider:'Nhà cung cấp',providerReference:'Mã đối soát',paidAt:'Thời gian thanh toán',notificationStatus:'Trạng thái',deliveryChannel:'Kênh gửi',isRead:'Đã đọc',action:'Sự kiện',entityType:'Đối tượng',entityId:'Mã đối tượng',userId:'Người thực hiện',requestId:'Request ID',permissions:'Số quyền',description:'Mô tả',code:'Mã vai trò'};
  const hiddenColumns = new Set(['id','postId','studentId','orderId','registrationRequest']);
  const dateKeys = new Set(['createdAt','expiresAt','lastLoginAt','startAt','endAt','appliedAt','reviewedAt','paidAt']);
  const formatDate = (value) => { if (!value) return 'Chưa có'; const raw=String(value);const normalized=/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(raw)?raw.replace(' ','T')+'Z':raw;const date=new Date(normalized);return Number.isNaN(date.getTime())?raw:new Intl.DateTimeFormat('vi-VN',{dateStyle:'short',timeStyle:'short',timeZone:'Asia/Ho_Chi_Minh'}).format(date); };
  const statusTone = (value) => ['active','verified','paid','sent','completed','approved'].includes(String(value).toLowerCase()) ? 'success' : ['rejected','failed','disabled','cancelled'].includes(String(value).toLowerCase()) ? 'danger' : 'warning';
  const formatCell = (key,value) => { if (dateKeys.has(key)) return escapeHtml(formatDate(value)); if (key==='role'||key==='type') return escapeHtml(roleLabels[value]||value||'—'); if (key==='isRead') return value ? 'Đã đọc' : 'Chưa đọc'; if (key==='amount'&&value!==null) return Number(value).toLocaleString('vi-VN'); if (key==='matchScore'&&value!==null&&value!=='') return `${Number(value).toLocaleString('vi-VN')}%`; if (key.toLowerCase().includes('status')) return `<span class="status-badge ${statusTone(value)}">${escapeHtml(statusLabels[String(value).toLowerCase()]||value||'—')}</span>`; return escapeHtml(value??'—'); };
  const labels = {
    users:['Quản lý người dùng','Phân khu tài khoản học đường, tổ chức và hệ thống vận hành.'],
    organizations:['Tổ chức','School, Enterprise và hàng đợi xác minh.'],
    activities:['Học tập & hoạt động','Theo dõi hoạt động, lịch và trạng thái vận hành.'],
    applications:['Cơ hội & ứng tuyển','Theo dõi quy trình và các hồ sơ cần duyệt.'],
    payments:['Tài trợ & thanh toán','Theo dõi lệnh thanh toán và ngoại lệ đối soát.'],
    notifications:['Thông báo','Theo dõi delivery, trạng thái và retry.'],
    audit:['Audit & bảo mật','Dòng sự kiện theo người dùng, đối tượng và request ID.'],
    rbac:['RBAC & quyền','Vai trò và quyền hiệu lực trong hệ thống.'],
    system:['Hệ thống','Trạng thái runtime, cơ sở dữ liệu và migration. Chỉ quản trị viên có quyền xem.'],
  };
  const dashboardView = document.querySelector('[data-dashboard-view]');
  const tasksView = document.querySelector('[data-tasks-view]');
  const tasksList = document.querySelector('[data-tasks-list]');
  const tasksEmpty = document.querySelector('[data-tasks-empty]');
  const tasksLoading = document.querySelector('[data-tasks-loading]');
  const tasksSyncTime = document.querySelector('[data-tasks-sync-time]');
  const tasksSummaryPill = document.querySelector('[data-tasks-summary-pill]');
  let tasksAllItems = [];
  let activeTasksTab = 'all';
  let activeTasksDomain = 'all';
  let activeTasksSearch = '';
  const moduleView = document.querySelector('[data-module-view]');
  const moduleContent = document.querySelector('[data-module-content]');
  const moduleState = document.querySelector('[data-module-state]');
  const moduleSearch = document.querySelector('[data-module-search]');
  let currentSection = 'dashboard';
  let currentRows = [];
  let pendingAction = null;
  const dismissedQueueStorageKey = 'talenthub-admin-dismissed-queue-items';
  const dismissedQueueItems = () => { try { return new Set(JSON.parse(localStorage.getItem(dismissedQueueStorageKey) || '[]')); } catch { return new Set(); } };
  const queueItemKey = (item) => `${item.type}:${item.title}:${item.count}`;
  const saveDismissedQueueItem = (key) => { const dismissed = dismissedQueueItems(); dismissed.add(key); localStorage.setItem(dismissedQueueStorageKey, JSON.stringify([...dismissed])); };
  const refreshDashboard = async () => {
    try {
      const data = await api('/admin/dashboard');
      data.organizations = Number(data.schools || 0) + Number(data.enterprises || 0);
      document.querySelectorAll('[data-dashboard-metric]').forEach((element) => { element.textContent = Number(data[element.dataset.dashboardMetric] || 0).toLocaleString('vi-VN'); });
      const visibleQueue=(data.queue||[]).filter((item)=>!dismissedQueueItems().has(queueItemKey(item)));
      const queue = document.querySelector('.queue-list');
      if (queue) queue.innerHTML = visibleQueue.length ? visibleQueue.map((item)=>`<article class="queue-item" data-queue-item data-queue-key="${escapeHtml(queueItemKey(item))}"><span class="severity ${escapeHtml(item.severity)}" aria-hidden="true"></span><button class="queue-open" type="button" data-queue-section="${escapeHtml(item.type)}"><span class="queue-content"><strong>${escapeHtml(item.title)}</strong><small>${Number(item.count).toLocaleString('vi-VN')} bản ghi · ${escapeHtml(item.detail)}</small><span><b>${escapeHtml(item.owner)}</b> · Dữ liệu thời gian thực</span></span></button><button class="queue-dismiss" type="button" data-queue-dismiss aria-label="Đánh dấu đã xử lý: ${escapeHtml(item.title)}">Đã xử lý</button></article>`).join('') : '<div class="empty-state compact"><strong>Không có việc tồn đọng</strong><p>Các hàng đợi hiện đang ổn định.</p></div>';
      const queueCount=visibleQueue.reduce((sum,item)=>sum+Number(item.count||0),0);
      const queueBadge=document.querySelector('[data-queue-count]');if(queueBadge)queueBadge.textContent=`${queueCount.toLocaleString('vi-VN')} mục`;
      document.querySelectorAll('[data-nav-count]').forEach((badge)=>{const matched=visibleQueue.find((item)=>item.type===badge.dataset.navCount);const count=(badge.dataset.navCount==='dashboard'||badge.dataset.navCount==='tasks'||badge.dataset.navCount==='queue')?queueCount:Number(matched?.count||0);badge.textContent=count.toLocaleString('vi-VN');badge.hidden=count===0;badge.setAttribute('aria-label',`${count} mục cần xử lý`);});
      const alertButton=document.querySelector('[data-alert-count]');if(alertButton){alertButton.hidden=queueCount===0;alertButton.setAttribute('aria-label',`${queueCount} mục cần xử lý`);}
      const roleBox=document.querySelector('[data-role-distribution]');if(roleBox){const entries=Object.entries(data.usersByRole||{});const max=Math.max(1,...entries.map(([,count])=>Number(count)));roleBox.innerHTML=entries.length?`<div class="distribution-list">${entries.map(([role,count])=>`<div class="distribution-row"><div><span>${escapeHtml(roleLabels[role]||role)}</span><strong>${Number(count).toLocaleString('vi-VN')}</strong></div><div class="distribution-track"><span style="width:${Math.max(3,Number(count)/max*100)}%"></span></div></div>`).join('')}</div>`:'<div class="empty-state compact"><strong>Chưa có người dùng</strong></div>';}
      const orgBody=document.querySelector('[data-dashboard-organizations]');if(orgBody){orgBody.innerHTML=(data.recentOrganizations||[]).map((org)=>`<tr data-org-row><td><div class="org-cell"><span class="org-logo">${escapeHtml(String(org.name||'?').slice(0,1).toUpperCase())}</span><div><strong>${escapeHtml(org.name)}</strong><small>${escapeHtml(roleLabels[org.type]||org.type)}</small></div></div></td><td><span class="status-badge ${statusTone(org.verificationStatus)}">${escapeHtml(statusLabels[org.verificationStatus]||org.verificationStatus)}</span></td><td colspan="3" class="muted-cell">Tạo lúc ${escapeHtml(formatDate(org.createdAt))}</td><td><button class="button secondary small" type="button" data-dashboard-section="organizations">Mở</button></td></tr>`).join('')||'<tr><td colspan="6"><div class="empty-state compact">Chưa có tổ chức.</div></td></tr>';}
      const auditList=document.querySelector('[data-dashboard-audit]');if(auditList){auditList.innerHTML=(data.recentAudits||[]).map((event)=>`<li><time>${escapeHtml(formatDate(event.createdAt))}</time><span class="audit-dot"></span><div><strong>${escapeHtml(event.action)}</strong><small>${escapeHtml(event.entityType)} · ${escapeHtml(event.entityId)}</small></div></li>`).join('')||'<li><div>Chưa có sự kiện audit.</div></li>';}
      const updated=document.querySelector('.last-updated');if(updated)updated.lastChild.textContent=` Cập nhật ${formatDate(data.generatedAt)}`;
    } catch (error) { showToast(`Không thể cập nhật dashboard: ${error.message}`); }
  };

  const svgIcon = (name) => {
    const paths = {
      book: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
      building: '<path d="M3 21h18M6 21V5l6-3 6 3v16M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01"/>',
      briefcase: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M12 12v.01M3 12a18 18 0 0 0 18 0"/>',
      shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
      search: '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>',
      pulse: '<path d="M3 12h4l2-7 4 14 2-7h6"/>',
      plus: '<path d="M12 5v14M5 12h14"/>',
      refresh: '<path d="M21 12a9 9 0 1 1-2.63-6.36L21 8"/><path d="M21 3v5h-5"/>',
      check: '<polyline points="20 6 9 17 4 12"/>',
    };
    return `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name] || paths.search}</svg>`;
  };

  let activeUserZone = 'education';
  let activeUserSubRole = 'all';
  let activeUserStatus = 'all';
  let activeUserSearch = '';
  let userActionsRef = null;

  const userZones = [
    {
      id: 'education',
      number: 'KHU 1',
      name: 'NGƯỜI DÙNG HỌC ĐƯỜNG',
      shortName: 'Người dùng học đường',
      subtitle: 'Học sinh & Giáo viên',
      roles: ['student', 'teacher'],
      icon: 'book',
      roleMap: {
        student: 'Học sinh',
        teacher: 'Giáo viên',
      },
    },
    {
      id: 'school',
      number: 'KHU 2',
      name: 'NHÀ TRƯỜNG',
      shortName: 'Nhà trường',
      subtitle: 'Trường học & Cơ sở đào tạo',
      roles: ['school'],
      icon: 'building',
      roleMap: {
        school: 'Nhà trường',
      },
    },
    {
      id: 'enterprise',
      number: 'KHU 3',
      name: 'DOANH NGHIỆP',
      shortName: 'Doanh nghiệp',
      subtitle: 'Đối tác & Doanh nghiệp',
      roles: ['enterprise'],
      icon: 'briefcase',
      roleMap: {
        enterprise: 'Doanh nghiệp',
      },
    },
    {
      id: 'system',
      number: 'KHU 4',
      name: 'QUẢN TRỊ HỆ THỐNG',
      shortName: 'Quản trị hệ thống',
      subtitle: 'Quản trị nền tảng',
      roles: ['platform_admin', 'admin'],
      icon: 'shield',
      roleMap: {
        platform_admin: 'Quản trị nền tảng',
        admin: 'Quản trị viên',
      },
    },
  ];

  const getFilteredUsers = (currentZone) => {
    const zoneUsers = currentRows.filter((r) => currentZone.roles.includes(r.role));
    let filtered = zoneUsers;
    if (activeUserSubRole !== 'all') {
      filtered = filtered.filter((r) => r.role === activeUserSubRole);
    }
    if (activeUserStatus !== 'all') {
      filtered = filtered.filter((r) => String(r.status).toLowerCase() === activeUserStatus);
    }
    if (activeUserSearch.trim() !== '') {
      const q = normalizeSearch(activeUserSearch.trim());
      filtered = filtered.filter((r) => {
        const nameMatch = normalizeSearch(r.fullName).includes(q);
        const emailMatch = normalizeSearch(r.email).includes(q);
        const idMatch = normalizeSearch(r.id).includes(q);
        return nameMatch || emailMatch || idMatch;
      });
    }
    return { zoneUsers, filtered };
  };

  const renderUserTable = (rows, currentZone, zoneUsers, actions) => {
    if (!rows.length) {
      return `<div class="user-empty-state">
        <div class="empty-icon-box">${svgIcon('search')}</div>
        <strong>Không tìm thấy tài khoản phù hợp</strong>
        <p>Không có tài khoản nào trong "${escapeHtml(currentZone.shortName)}" khớp với từ khóa tìm kiếm hoặc bộ lọc trạng thái đã chọn.</p>
        ${(activeUserSearch || activeUserStatus !== 'all' || activeUserSubRole !== 'all') ? `
          <button type="button" class="button secondary small user-btn-reset-filters" data-user-reset-filters>Xóa tất cả bộ lọc</button>
        ` : ''}
      </div>`;
    }
    return `
      <div class="table-scroll user-table-scroll">
        <table class="user-table">
          <caption class="sr-only">Danh sách người dùng ${escapeHtml(currentZone.name)}</caption>
          <thead>
            <tr>
              <th scope="col" class="th-user">Họ và tên</th>
              <th scope="col" class="th-email">Email</th>
              <th scope="col" class="th-role">Vai trò</th>
              <th scope="col" class="th-status">Trạng thái</th>
              <th scope="col" class="th-created">Ngày tạo</th>
              <th scope="col" class="th-lastlogin">Đăng nhập gần nhất</th>
              <th scope="col" class="th-actions">Hành động</th>
            </tr>
          </thead>
          <tbody>
            ${rows.map((row) => `
              <tr class="user-table-row">
                <td class="td-user">
                  <div class="user-identity-cell">
                    <span class="user-avatar-mini" aria-hidden="true">${escapeHtml(String(row.fullName || row.email || '?').slice(0, 1).toUpperCase())}</span>
                    <div class="user-identity-info">
                      <strong class="user-fullname">${escapeHtml(row.fullName || '—')}</strong>
                      <span class="user-id-sub" title="Mã ID: ${escapeHtml(row.id || '')}">#${escapeHtml(String(row.id || '').slice(0, 8))}</span>
                    </div>
                  </div>
                </td>
                <td class="td-email">
                  <span class="user-email-text" title="${escapeHtml(row.email || '')}">${escapeHtml(row.email || '—')}</span>
                </td>
                <td class="td-role">
                  <span class="user-role-badge role-${escapeHtml(row.role)}">${escapeHtml(roleLabels[row.role] || row.role)}</span>
                </td>
                <td class="td-status">
                  <span class="user-status-pill status-${escapeHtml(String(row.status || '').toLowerCase())}">
                    <span class="status-dot-mini" aria-hidden="true"></span>
                    <span>${escapeHtml(statusLabels[String(row.status).toLowerCase()] || row.status || '—')}</span>
                  </span>
                </td>
                <td class="td-created">
                  <time class="user-datetime">${escapeHtml(formatDate(row.createdAt))}</time>
                </td>
                <td class="td-lastlogin">
                  <time class="user-datetime">${escapeHtml(formatDate(row.lastLoginAt))}</time>
                </td>
                <td class="td-actions">
                  ${actions(row)}
                </td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>
    `;
  };

  const renderUsersSection = (actions) => {
    userActionsRef = actions;
    const currentZone = userZones.find((z) => z.id === activeUserZone) || userZones[0];
    const { zoneUsers, filtered } = getFilteredUsers(currentZone);

    const categoryNavHtml = `
      <nav class="user-category-nav" role="tablist" aria-label="Phân khu quản lý người dùng">
        ${userZones.map((zone) => {
          const count = currentRows.filter((r) => zone.roles.includes(r.role)).length;
          const isActive = zone.id === activeUserZone;
          return `
            <div class="user-cat-card ${isActive ? 'is-active' : ''}" data-zone-select="${zone.id}" role="tab" tabindex="0" aria-selected="${isActive}">
              <div class="user-cat-accent" aria-hidden="true"></div>
              <div class="user-cat-inner">
                <div class="user-cat-icon-box">
                  ${svgIcon(zone.icon)}
                </div>
                <div class="user-cat-info">
                  <div class="user-cat-heading">
                    <span class="user-cat-number">${zone.number}</span>
                    <span class="user-cat-sep">·</span>
                    <h3 class="user-cat-name">${zone.shortName}</h3>
                    <span class="user-cat-count-pill">${count.toLocaleString('vi-VN')}</span>
                  </div>
                  <div class="user-cat-meta">
                    <span class="user-cat-sublabel">${zone.subtitle}</span>
                    <span class="user-cat-dot">·</span>
                    <span class="user-cat-role-chips">
                      ${zone.roles.map((r) => {
                        const rCount = currentRows.filter((row) => row.role === r).length;
                        return `<span class="cat-mini-chip">${zone.roleMap[r] || r} <b class="chip-num">(${rCount})</b></span>`;
                      }).join('')}
                    </span>
                    ${isActive ? `<span class="active-tag" title="Đang quản lý khu vực này"><span class="active-dot"></span>Đang xem</span>` : ''}
                  </div>
                </div>
              </div>
            </div>
          `;
        }).join('')}
      </nav>
    `;

    moduleContent.innerHTML = `
      <div class="user-management-wrapper">
        ${categoryNavHtml}
        <section class="panel user-management-panel" aria-labelledby="user-panel-heading-title">
          <div class="user-panel-toolbar">
            <div class="user-toolbar-primary">
              <div class="user-context-meta">
                <h3 id="user-panel-heading-title" class="user-context-title">${currentZone.shortName}</h3>
                <span class="user-context-count" data-user-zone-count>${filtered.length.toLocaleString('vi-VN')} / ${zoneUsers.length.toLocaleString('vi-VN')} tài khoản</span>
              </div>

              ${currentZone.roles.length > 1 ? `
                <div class="user-subrole-tabs" role="tablist" aria-label="Lọc theo vai trò">
                  <button type="button" class="user-tab-item ${activeUserSubRole === 'all' ? 'is-active' : ''}" data-user-subrole="all">
                    Tất cả vai trò <span class="tab-num">(${zoneUsers.length})</span>
                  </button>
                  ${currentZone.roles.map((r) => {
                    const rCount = currentRows.filter((row) => row.role === r).length;
                    return `
                      <button type="button" class="user-tab-item ${activeUserSubRole === r ? 'is-active' : ''}" data-user-subrole="${r}">
                        ${currentZone.roleMap[r] || r} <span class="tab-num">(${rCount})</span>
                      </button>
                    `;
                  }).join('')}
                </div>
              ` : ''}

              <div class="user-toolbar-ctas">
                <button class="button secondary small user-btn-refresh" type="button" data-user-refresh-btn title="Làm mới danh sách dữ liệu">
                  ${svgIcon('refresh')}
                  <span>Làm mới</span>
                </button>
                <button class="button primary small user-btn-create" type="button" data-user-create title="Thêm tài khoản người dùng mới">
                  ${svgIcon('plus')}
                  <span>Thêm tài khoản</span>
                </button>
              </div>
            </div>

            <div class="user-toolbar-secondary">
              <div class="user-search-wrapper">
                ${svgIcon('search')}
                <input type="search" class="user-search-input" placeholder="Tìm kiếm theo tên, email, ID..." value="${escapeHtml(activeUserSearch)}" data-user-search-input>
                ${activeUserSearch ? `<button type="button" class="user-search-clear" data-user-search-clear aria-label="Xóa tìm kiếm">&times;</button>` : ''}
              </div>

              <div class="user-filter-controls">
                <label for="user-status-filter-select" class="user-filter-label">Trạng thái:</label>
                <select id="user-status-filter-select" class="typeui-select user-status-filter" data-user-status-select>
                  <option value="all"${activeUserStatus === 'all' ? ' selected' : ''}>Tất cả trạng thái</option>
                  <option value="active"${activeUserStatus === 'active' ? ' selected' : ''}>Đang hoạt động</option>
                  <option value="pending"${activeUserStatus === 'pending' ? ' selected' : ''}>Chờ duyệt</option>
                  <option value="suspended"${activeUserStatus === 'suspended' ? ' selected' : ''}>Tạm khóa</option>
                  <option value="disabled"${activeUserStatus === 'disabled' ? ' selected' : ''}>Vô hiệu hóa</option>
                </select>
              </div>

              <div class="user-filter-status-info" data-user-filter-info>
                ${(activeUserSearch || activeUserStatus !== 'all' || activeUserSubRole !== 'all') ? `
                  <span class="user-filtered-tag">
                    Đang lọc: <b>${filtered.length}</b> kết quả
                    <button type="button" class="user-btn-reset-filters" data-user-reset-filters title="Xóa toàn bộ bộ lọc">Xóa lọc</button>
                  </span>
                ` : `
                  <span class="user-total-info">Hiển thị toàn bộ <b>${filtered.length}</b> tài khoản</span>
                `}
              </div>
            </div>
          </div>

          <div class="user-table-wrap">
            ${renderUserTable(filtered, currentZone, zoneUsers, actions)}
          </div>
        </section>
      </div>
    `;
  };

  const table = (rows, actions = null) => {
    if (!rows.length) return '<div class="empty-state"><strong>Chưa có dữ liệu</strong><p>Không có bản ghi phù hợp với bộ lọc hiện tại.</p></div>';
    const columns = Object.keys(rows[0]).filter((key) => key !== 'passwordHash'&&!hiddenColumns.has(key));
    return `<div class="table-summary">Hiển thị ${rows.length.toLocaleString('vi-VN')} bản ghi</div><div class="table-scroll"><table><caption class="sr-only">Dữ liệu ${escapeHtml(currentSection)}</caption><thead><tr>${columns.map((key)=>`<th scope="col">${escapeHtml(columnLabels[key]||key)}</th>`).join('')}${actions?'<th scope="col">Hành động</th>':''}</tr></thead><tbody>${rows.map((row)=>`<tr>${columns.map((key)=>`<td title="${escapeHtml(row[key]??'')}">${formatCell(key,row[key])}</td>`).join('')}${actions?`<td>${actions(row)}</td>`:''}</tr>`).join('')}</tbody></table></div>`;
  };
  const renderModule = (section, data) => {
    if (section === 'rbac') {
      const grouped = (data.roles || []).map((role) => ({...role, permissions:(data.mappings || []).filter((m)=>m.role===role.code).length}));
      moduleContent.innerHTML = table(grouped); return;
    }
    if (section === 'system') {
      const systemLabels = {database:'Cơ sở dữ liệu',databaseVersion:'Phiên bản cơ sở dữ liệu',migrationCount:'Migration đã ghi nhận',tableCount:'Bảng dữ liệu',serverTime:'Thời gian máy chủ',phpVersion:'PHP runtime'};
      const entries = Object.entries(data).filter(([key]) => systemLabels[key]);
      moduleContent.innerHTML = `<section class="system-summary" aria-label="Tổng quan hệ thống"><p>Thông tin kỹ thuật chỉ hiển thị trong khu vực này và chỉ dành cho quản trị viên.</p><div class="system-grid">${entries.map(([key,value])=>`<article><span>${escapeHtml(systemLabels[key])}</span><strong>${escapeHtml(String(value ?? '—'))}</strong></article>`).join('')}</div></section>`;
      return;
    }
    currentRows = data.items || [];
    const actions = section === 'users' ? (row) => {
      const organizationPending = ['school','enterprise'].includes(row.role) && row.status === 'pending';
      return `<div class="row-actions user-row-actions"><button class="button secondary small user-act-btn edit" data-user-edit data-id="${escapeHtml(row.id)}" title="Sửa tài khoản">Sửa</button>${organizationPending ? '<span class="action-note action-note-badge" title="Tổ chức cần xác minh">Duyệt tại Tổ chức</span>' : `<button class="button secondary small user-act-btn toggle ${row.status === 'active' ? 'is-suspend' : 'is-activate'}" data-user-action data-id="${escapeHtml(row.id)}" data-status="${row.status === 'active' ? 'suspended' : 'active'}" title="${row.status === 'active' ? 'Đình chỉ tài khoản' : 'Kích hoạt lại'}">${row.status === 'active' ? 'Đình chỉ' : 'Kích hoạt'}</button>`}${row.status !== 'disabled' ? `<button class="button danger small user-act-btn delete" data-user-delete data-id="${escapeHtml(row.id)}" title="Vô hiệu hóa tài khoản">Vô hiệu hóa</button>` : ''}</div>`;
    } : section === 'organizations' ? (row) => `<button class="button secondary small" data-org-action data-id="${escapeHtml(row.id)}" data-type="${escapeHtml(row.type)}" data-current-status="${escapeHtml(row.verificationStatus)}">Xem xét</button>` : null;
    if (section === 'organizations') {
      renderOrganizationsSection(actions);
      return;
    }
    if (section === 'users') {
      renderUsersSection(actions);
      return;
    }
    moduleContent.innerHTML = table(currentRows, actions);
  };

  const loadTasksSection = async () => {
    if (!tasksView) return;
    if (tasksLoading) tasksLoading.hidden = false;
    if (tasksList) tasksList.hidden = true;
    if (tasksEmpty) tasksEmpty.hidden = true;
    if (tasksSummaryPill) tasksSummaryPill.textContent = 'Đang đồng bộ dữ liệu...';

    try {
      const [dashData, orgData, userData, paymentData, appData] = await Promise.all([
        api('/admin/dashboard').catch(() => ({})),
        api('/admin/organizations').catch(() => ({ items: [] })),
        api('/admin/users').catch(() => ({ items: [] })),
        api('/admin/resources/payments').catch(() => ({ items: [] })),
        api('/admin/resources/applications').catch(() => ({ items: [] })),
      ]);

      const items = [];

      // 1. Tổ chức chờ xác minh (School / Enterprise pending verification or registration request)
      const orgItems = (orgData.items || []).filter((org) => {
        const vs = String(org.verificationStatus || '').toLowerCase();
        const st = String(org.status || '').toLowerCase();
        return vs === 'pending' || vs === 'inactive' || st === 'pending' || Boolean(org.registrationRequest);
      });
      orgItems.forEach((org) => {
        items.push({
          id: org.id,
          domain: 'organizations',
          domainLabel: org.type === 'school' ? 'Trường học' : 'Doanh nghiệp',
          domainIcon: org.type === 'school' ? 'building' : 'briefcase',
          title: `Xác minh ${org.type === 'school' ? 'Trường học' : 'Doanh nghiệp'}: ${org.name || 'Tổ chức'}`,
          meta: `Email: ${org.email || '—'} · Loại: ${org.type === 'school' ? 'Nhà trường' : 'Doanh nghiệp'}${org.type === 'school' && org.schoolLevel ? ` · Cấp bậc: ${schoolLevelLabel(org.schoolLevel)}` : ''} · ID: #${String(org.id).slice(0, 8)}`,
          category: 'pending_approval',
          severity: 'high',
          severityLabel: 'Ưu tiên cao',
          status: 'pending',
          statusLabel: 'Chờ duyệt',
          statusTone: 'warning',
          createdAt: org.createdAt,
          actionType: 'org',
          actionPayload: { id: org.id, type: org.type, currentStatus: org.verificationStatus || 'pending' },
          viewSection: 'organizations',
        });
      });

      // 2. Tài khoản người dùng cần xử lý (pending approval or suspended)
      const userItems = (userData.items || []).filter((u) => {
        const st = String(u.status || '').toLowerCase();
        return st === 'pending' || st === 'suspended';
      });
      userItems.forEach((u) => {
        const isPending = String(u.status).toLowerCase() === 'pending';
        items.push({
          id: u.id,
          domain: 'users',
          domainLabel: roleLabels[u.role] || u.role || 'Người dùng',
          domainIcon: 'shield',
          title: `Tài khoản ${roleLabels[u.role] || u.role}: ${u.fullName || u.email}`,
          meta: `Email: ${u.email} · Vai trò: ${roleLabels[u.role] || u.role} · ID: #${String(u.id).slice(0, 8)}`,
          category: isPending ? 'pending_approval' : 'needs_inspection',
          severity: isPending ? 'medium' : 'high',
          severityLabel: isPending ? 'Cần xem xét' : 'Ưu tiên cao',
          status: u.status,
          statusLabel: isPending ? 'Chờ duyệt' : 'Tạm khóa',
          statusTone: isPending ? 'warning' : 'danger',
          createdAt: u.createdAt,
          actionType: 'user',
          actionPayload: { id: u.id, status: isPending ? 'active' : 'active' },
          viewSection: 'users',
        });
      });

      // 3. Thanh toán đang chờ kiểm tra đối soát (payment orders pending)
      const paymentItems = (paymentData.items || []).filter((p) => {
        const ps = String(p.paymentStatus || '').toLowerCase();
        return ps === 'pending' || ps === 'pending_payment';
      });
      paymentItems.forEach((p) => {
        items.push({
          id: p.id,
          domain: 'payments',
          domainLabel: 'Thanh toán',
          domainIcon: 'book',
          title: `Lệnh thanh toán: ${p.orderCode || '#' + String(p.id).slice(0, 8)}`,
          meta: `${p.enterpriseName ? p.enterpriseName + ' · ' : ''}${Number(p.amount || 0).toLocaleString('vi-VN')} ${p.currency || 'VND'} · PT: ${p.paymentMethod || 'Chuyển khoản'}`,
          category: 'needs_inspection',
          severity: 'critical',
          severityLabel: 'Ưu tiên cao',
          status: p.paymentStatus,
          statusLabel: 'Chờ thanh toán',
          statusTone: 'warning',
          createdAt: p.createdAt,
          actionType: 'navigate',
          actionPayload: { id: p.id },
          viewSection: 'payments',
        });
      });

      // 4. Hồ sơ ứng tuyển đang chờ xem xét (applications submitted/pending)
      const appItems = (appData.items || []).filter((a) => {
        const st = String(a.status || '').toLowerCase();
        return st === 'submitted' || st === 'pending' || st === 'applied';
      });
      appItems.forEach((a) => {
        items.push({
          id: a.id,
          domain: 'applications',
          domainLabel: 'Ứng tuyển',
          domainIcon: 'briefcase',
          title: `Hồ sơ ứng tuyển: ${a.studentName || 'Ứng viên'}`,
          meta: `Vị trí: ${a.postTitle || 'Thực tập sinh'}${a.matchScore ? ' · Độ phù hợp: ' + a.matchScore + '%' : ''}`,
          category: 'needs_inspection',
          severity: 'medium',
          severityLabel: 'Cần kiểm tra',
          status: a.status,
          statusLabel: 'Chờ xem xét',
          statusTone: 'warning',
          createdAt: a.appliedAt || a.createdAt,
          actionType: 'navigate',
          actionPayload: { id: a.id },
          viewSection: 'applications',
        });
      });

      tasksAllItems = items;
      renderTasksView();
      if (tasksSyncTime) {
        tasksSyncTime.textContent = `Đồng bộ lúc ${formatDate(new Date().toISOString())}`;
      }
    } catch (error) {
      if (tasksLoading) tasksLoading.hidden = true;
      if (tasksSummaryPill) tasksSummaryPill.textContent = 'Lỗi kết nối';
      showToast(`Không thể tải việc cần xử lý: ${error.message}`);
    }
  };

  const renderTasksView = () => {
    if (!tasksView) return;
    if (tasksLoading) tasksLoading.hidden = true;

    const countAll = tasksAllItems.length;
    const countPending = tasksAllItems.filter(t => t.category === 'pending_approval').length;
    const countInspect = tasksAllItems.filter(t => t.category === 'needs_inspection').length;
    const countUrgent = tasksAllItems.filter(t => t.severity === 'critical' || t.severity === 'high').length;

    const badgeAll = document.querySelector('[data-tasks-count="all"]');
    if (badgeAll) badgeAll.textContent = countAll;
    const badgePending = document.querySelector('[data-tasks-count="pending_approval"]');
    if (badgePending) badgePending.textContent = countPending;
    const badgeInspect = document.querySelector('[data-tasks-count="needs_inspection"]');
    if (badgeInspect) badgeInspect.textContent = countInspect;
    const badgeUrgent = document.querySelector('[data-tasks-count="high_priority"]');
    if (badgeUrgent) badgeUrgent.textContent = countUrgent;

    document.querySelectorAll('[data-nav-count="tasks"]').forEach(b => {
      b.textContent = countAll.toLocaleString('vi-VN');
      b.hidden = countAll === 0;
      b.setAttribute('aria-label', `${countAll} việc cần xử lý`);
    });
    const alertBtn = document.querySelector('[data-alert-count]');
    if (alertBtn) {
      alertBtn.hidden = countAll === 0;
      alertBtn.setAttribute('aria-label', `${countAll} việc cần xử lý`);
    }

    let filtered = tasksAllItems;
    if (activeTasksTab === 'pending_approval') {
      filtered = filtered.filter(t => t.category === 'pending_approval');
    } else if (activeTasksTab === 'needs_inspection') {
      filtered = filtered.filter(t => t.category === 'needs_inspection');
    } else if (activeTasksTab === 'high_priority') {
      filtered = filtered.filter(t => t.severity === 'critical' || t.severity === 'high');
    }

    if (activeTasksDomain !== 'all') {
      filtered = filtered.filter(t => t.domain === activeTasksDomain);
    }

    if (activeTasksSearch.trim() !== '') {
      const q = normalizeSearch(activeTasksSearch.trim());
      filtered = filtered.filter(t => {
        return normalizeSearch(t.title).includes(q) ||
               normalizeSearch(t.meta).includes(q) ||
               normalizeSearch(t.domainLabel).includes(q) ||
               normalizeSearch(t.id).includes(q);
      });
    }

    if (tasksSummaryPill) {
      tasksSummaryPill.textContent = countAll === 0
        ? '0 việc cần xử lý'
        : `Hiển thị ${filtered.length} / ${countAll} việc cần xử lý`;
    }

    if (countAll === 0) {
      if (tasksList) tasksList.hidden = true;
      if (tasksEmpty) {
        tasksEmpty.hidden = false;
        tasksEmpty.innerHTML = `
          <div class="tasks-empty-icon-box">
            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
              <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
          </div>
          <h3 class="tasks-empty-title">Không có việc cần xử lý</h3>
          <p class="tasks-empty-desc">Hệ thống hiện không có yêu cầu đang chờ. Mọi tác vụ phê duyệt, xác minh và đối soát đã được xử lý hoàn tất.</p>
          <button class="button secondary small tasks-empty-action" type="button" data-tasks-refresh>
            ${svgIcon('refresh')}<span>Kiểm tra lại dữ liệu</span>
          </button>
        `;
      }
      return;
    }

    if (filtered.length === 0) {
      if (tasksList) tasksList.hidden = true;
      if (tasksEmpty) {
        tasksEmpty.hidden = false;
        tasksEmpty.innerHTML = `
          <div class="tasks-empty-icon-box">
            ${svgIcon('search')}
          </div>
          <h3 class="tasks-empty-title">Không có việc cần xử lý trong nhóm này</h3>
          <p class="tasks-empty-desc">Không tìm thấy yêu cầu nào phù hợp với bộ lọc hoặc từ khóa tìm kiếm hiện tại.</p>
          <button class="button secondary small tasks-empty-action" type="button" data-tasks-reset-filter>
            Xóa bộ lọc tìm kiếm
          </button>
        `;
      }
      return;
    }

    if (tasksEmpty) tasksEmpty.hidden = true;
    if (tasksList) {
      tasksList.hidden = false;
      tasksList.innerHTML = filtered.map(item => `
        <article class="task-action-card severity-${escapeHtml(item.severity)}" data-task-id="${escapeHtml(item.id)}">
          <div class="task-card-accent" aria-hidden="true"></div>
          <div class="task-card-main">
            <div class="task-card-header-row">
              <div class="task-domain-pill domain-${escapeHtml(item.domain)}">
                ${svgIcon(item.domainIcon || 'search')}
                <span>${escapeHtml(item.domainLabel)}</span>
              </div>
              <span class="task-severity-badge severity-${escapeHtml(item.severity)}">
                <span class="task-severity-dot"></span>
                <span>${escapeHtml(item.severityLabel)}</span>
              </span>
            </div>

            <h3 class="task-card-title">${escapeHtml(item.title)}</h3>
            <p class="task-card-meta">${escapeHtml(item.meta)}</p>

            <div class="task-card-footer-row">
              <div class="task-time-wrap">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                <span>${escapeHtml(formatDate(item.createdAt))}</span>
              </div>
              <span class="status-badge ${escapeHtml(item.statusTone)}">${escapeHtml(item.statusLabel)}</span>
            </div>
          </div>

          <div class="task-card-actions">
            <button type="button" class="button secondary small task-btn-view" data-task-view="${escapeHtml(item.viewSection)}" data-id="${escapeHtml(item.id)}" title="Xem chi tiết">
              Xem
            </button>
            <button type="button" class="button primary small task-btn-action" data-task-action="${escapeHtml(item.actionType)}" data-id="${escapeHtml(item.id)}" data-type="${escapeHtml(item.actionPayload?.type || '')}" data-status="${escapeHtml(item.actionPayload?.status || '')}" data-current-status="${escapeHtml(item.actionPayload?.currentStatus || '')}" title="Xử lý ngay">
              Xử lý
            </button>
          </div>
        </article>
      `).join('');
    }
  };

  const loadSection = async (section, search = '') => {
    currentSection = section;
    document.querySelectorAll('[data-admin-section]').forEach((item)=>{const active=item.dataset.adminSection===section;item.classList.toggle('is-active',active);active?item.setAttribute('aria-current','page'):item.removeAttribute('aria-current');});
    if (section === 'dashboard') {
      if (dashboardView) dashboardView.hidden = false;
      if (tasksView) tasksView.hidden = true;
      if (moduleView) moduleView.hidden = true;
      history.replaceState(null, '', '#dashboard');
      refreshDashboard();
      closeSidebar();
      return;
    }
    if (section === 'tasks' || section === 'queue') {
      if (dashboardView) dashboardView.hidden = true;
      if (tasksView) tasksView.hidden = false;
      if (moduleView) moduleView.hidden = true;
      history.replaceState(null, '', '#tasks');
      loadTasksSection();
      closeSidebar();
      return;
    }
    if (dashboardView) dashboardView.hidden = true;
    if (tasksView) tasksView.hidden = true;
    if (moduleView) moduleView.hidden = false;
    moduleContent.hidden = true;
    moduleState.hidden = false;
    moduleState.textContent = 'Đang tải dữ liệu…';
    const [title,description]=labels[section]||['Module Admin',''];document.querySelector('[data-module-title]').textContent=title;document.querySelector('[data-module-description]').textContent=description;if(moduleSearch){moduleSearch.value=search;moduleSearch.placeholder=`Tìm trong ${title.toLocaleLowerCase('vi')}...`;}
    const moduleTools = document.querySelector('.module-toolbar .table-tools');
    if (moduleTools) moduleTools.hidden = (section === 'users' || section === 'organizations');
    if (section === 'users') activeUserSearch = search;
    const basePath = ['users','organizations','audit','rbac','system'].includes(section)?`/admin/${section}`:`/admin/resources/${section}`;
    const path = ['users','organizations'].includes(section) && search !== '' ? `${basePath}?search=${encodeURIComponent(search)}` : basePath;
    try { const data=await api(path);renderModule(section,data);moduleState.hidden=true;moduleContent.hidden=false;history.replaceState(null,'',`#${section}`); }
    catch(error){moduleState.hidden=false;moduleState.innerHTML=`<strong>Không thể tải dữ liệu</strong><p>${escapeHtml(error.message)}</p>`;}
    closeSidebar();
  };
  document.querySelectorAll('[data-admin-section]').forEach((link)=>link.addEventListener('click',(event)=>{event.preventDefault();loadSection(link.dataset.adminSection);}));
  document.querySelector('[data-module-refresh]')?.addEventListener('click',()=>loadSection(currentSection));
  document.querySelector('.queue-list')?.addEventListener('click',(event)=>{const dismiss=event.target.closest('[data-queue-dismiss]');if(dismiss){const item=dismiss.closest('[data-queue-item]');if(item){saveDismissedQueueItem(item.dataset.queueKey);refreshDashboard();showToast('Đã ẩn mục khỏi hàng đợi. Dữ liệu gốc không bị xóa.');}return;}const open=event.target.closest('[data-queue-section]');if(open)document.querySelector(`[data-admin-section="${open.dataset.queueSection}"]`)?.click();});
  document.querySelector('[data-alert-count]')?.addEventListener('click',()=>loadSection('tasks'));
  document.querySelector('a[href="#queue"]')?.addEventListener('click',(event)=>{event.preventDefault();loadSection('tasks');});
  let moduleSearchTimer=null;
  moduleSearch?.addEventListener('input',()=>{const query=moduleSearch.value.trim();if(['users','organizations'].includes(currentSection)){window.clearTimeout(moduleSearchTimer);moduleSearchTimer=window.setTimeout(()=>loadSection(currentSection,query),250);return;}const normalized=query.toLocaleLowerCase('vi');moduleContent.querySelectorAll('tbody tr').forEach((row)=>row.hidden=normalized!==''&&!row.textContent.toLocaleLowerCase('vi').includes(normalized));});

  let orgSchoolSearch = '';
  let orgSchoolLevel = 'all';
  let orgSchoolVerification = 'all';
  let orgEnterpriseSearch = '';
  let orgEnterpriseVerification = 'all';
  let orgActionsRef = null;

  const getFilteredSchools = (schools) => {
    return schools.filter((row) => {
      if (orgSchoolVerification !== 'all') {
        const v = String(row.verificationStatus || '').toLowerCase();
        if (v !== orgSchoolVerification) return false;
      }
      if (orgSchoolLevel !== 'all') {
        const lvl = String(row.schoolLevel || '').toLowerCase();
        if (lvl !== orgSchoolLevel) return false;
      }
      if (orgSchoolSearch) {
        const q = normalizeSearch(orgSchoolSearch);
        const text = normalizeSearch(`${row.name || ''} ${row.id || ''} ${row.email || ''}`);
        if (!text.includes(q)) return false;
      }
      return true;
    });
  };

  const getFilteredEnterprises = (enterprises) => {
    return enterprises.filter((row) => {
      if (orgEnterpriseVerification !== 'all') {
        const v = String(row.verificationStatus || '').toLowerCase();
        if (v !== orgEnterpriseVerification) return false;
      }
      if (orgEnterpriseSearch) {
        const q = normalizeSearch(orgEnterpriseSearch);
        const text = normalizeSearch(`${row.name || ''} ${row.id || ''} ${row.email || ''}`);
        if (!text.includes(q)) return false;
      }
      return true;
    });
  };

  const renderOrgTable = (rows, type, totalCount, actions) => {
    if (!rows.length) {
      return `
        <div class="empty-state compact">
          <strong>Không tìm thấy ${type === 'school' ? 'nhà trường' : 'doanh nghiệp'}</strong>
          <p>Không có dữ liệu phù hợp với bộ lọc tìm kiếm hiện tại.</p>
        </div>
      `;
    }
    return `
      <div class="table-scroll org-table-scroll">
        <table class="org-data-table">
          <caption class="sr-only">Danh sách ${type === 'school' ? 'nhà trường' : 'doanh nghiệp'}</caption>
          <thead>
            <tr>
              <th scope="col" class="th-org-name">TỔ CHỨC</th>
              ${type === 'school' ? '<th scope="col" class="th-org-level">CẤP BẬC</th>' : ''}
              <th scope="col" class="th-org-status">TRẠNG THÁI</th>
              <th scope="col" class="th-org-verification">XÁC MINH</th>
              <th scope="col" class="th-org-created">NGÀY TẠO</th>
              <th scope="col" class="th-org-actions text-right">HÀNH ĐỘNG</th>
            </tr>
          </thead>
          <tbody>
            ${rows.map((row) => {
              const statusKey = String(row.status || 'active').toLowerCase();
              const vKey = String(row.verificationStatus || 'pending').toLowerCase();
              const logoLetter = escapeHtml(String(row.name || '?').trim().slice(0, 1).toUpperCase());
              const isVerified = ['verified', 'active', 'hoạt động', 'đã xác minh'].includes(vKey);
              const isPending = ['pending', 'chờ duyệt', 'chờ xác minh'].includes(vKey);
              const isRejected = ['rejected', 'từ chối'].includes(vKey);
              const verifTone = isVerified ? 'v-verified' : isPending ? 'v-pending' : isRejected ? 'v-rejected' : 'v-verified';
              const verifLabel = isVerified ? 'Đã xác minh' : isPending ? 'Chờ xác minh' : isRejected ? 'Từ chối' : 'Đã xác minh';
              return `
                <tr class="org-row" data-org-id="${escapeHtml(row.id)}">
                  <td class="td-org-name">
                    <div class="org-cell-identity">
                      <div class="org-cell-avatar type-${type}" aria-hidden="true">${logoLetter}</div>
                      <div class="org-cell-details">
                        <strong class="org-cell-title">${escapeHtml(row.name || 'Tổ chức')}</strong>
                        <div class="org-cell-sub">
                          <span class="org-cell-id">#${escapeHtml(String(row.id || '').slice(0, 8))}</span>
                          ${row.email ? `<span class="org-cell-email">${escapeHtml(row.email)}</span>` : ''}
                        </div>
                      </div>
                    </div>
                  </td>
                  ${type === 'school' ? `<td class="td-org-level"><span class="org-level-pill">${escapeHtml(schoolLevelLabel(row.schoolLevel))}</span></td>` : ''}
                  <td class="td-org-status">
                    <span class="user-status-pill status-${statusKey}">
                      <span class="status-dot"></span>
                      <span class="status-text">${escapeHtml(statusLabels[statusKey] || row.status || 'Hoạt động')}</span>
                    </span>
                  </td>
                  <td class="td-org-verification">
                    <span class="org-verif-badge ${verifTone}">
                      <span class="verif-dot"></span>
                      <span class="verif-text">${escapeHtml(verifLabel)}</span>
                    </span>
                  </td>
                  <td class="td-org-created">
                    <time class="user-datetime">${escapeHtml(formatDate(row.createdAt))}</time>
                  </td>
                  <td class="td-org-actions text-right">
                    <button class="button secondary small org-action-btn" type="button" data-org-action data-id="${escapeHtml(row.id)}" data-type="${escapeHtml(row.type)}" data-current-status="${escapeHtml(row.verificationStatus || 'pending')}" title="Xem xét hồ sơ tổ chức">
                      Xem xét
                    </button>
                  </td>
                </tr>
              `;
            }).join('')}
          </tbody>
        </table>
      </div>
    `;
  };

  const renderOrganizationsSection = (actions) => {
    orgActionsRef = actions;
    const schools = currentRows.filter((row) => row.type === 'school');
    const enterprises = currentRows.filter((row) => row.type === 'enterprise');

    const filteredSchools = getFilteredSchools(schools);
    const filteredEnterprises = getFilteredEnterprises(enterprises);

    moduleContent.innerHTML = `
      <div class="org-management-wrapper">
        <!-- KHU 1: TỔNG QUAN (2 card nhỏ gọn) -->
        <section class="org-overview-panel" aria-label="Tổng quan số liệu tổ chức">
          <div class="org-overview-grid">
            <!-- Card 1: Nhà trường -->
            <div class="org-stat-card card-school">
              <div class="org-stat-header">
                <div class="org-stat-icon-wrap icon-school">
                  ${svgIcon('building')}
                </div>
                <div class="org-stat-heading-meta">
                  <span class="org-stat-kicker">KHU 1 — ĐÀO TẠO</span>
                  <h4 class="org-stat-title">Nhà trường</h4>
                </div>
              </div>
              <div class="org-stat-body">
                <div class="org-stat-main-num">
                  <strong class="org-stat-val">${schools.length.toLocaleString('vi-VN')}</strong>
                  <span class="org-stat-unit">tổ chức giáo dục</span>
                </div>
                <div class="org-stat-chips">
                  <span class="org-chip chip-verified">
                    <span class="chip-dot"></span><b>${schools.length}</b> đang hoạt động
                  </span>
                </div>
              </div>
            </div>

            <!-- Card 2: Doanh nghiệp -->
            <div class="org-stat-card card-enterprise">
              <div class="org-stat-header">
                <div class="org-stat-icon-wrap icon-enterprise">
                  ${svgIcon('briefcase')}
                </div>
                <div class="org-stat-heading-meta">
                  <span class="org-stat-kicker">KHU 2 — ĐỐI TÁC</span>
                  <h4 class="org-stat-title">Doanh nghiệp</h4>
                </div>
              </div>
              <div class="org-stat-body">
                <div class="org-stat-main-num">
                  <strong class="org-stat-val">${enterprises.length.toLocaleString('vi-VN')}</strong>
                  <span class="org-stat-unit">doanh nghiệp liên kết</span>
                </div>
                <div class="org-stat-chips">
                  <span class="org-chip chip-verified">
                    <span class="chip-dot"></span><b>${enterprises.length}</b> đang hoạt động
                  </span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- KHU 2: NHÀ TRƯỜNG -->
        <section class="panel org-section-card" id="org-schools-section" aria-labelledby="org-schools-title">
          <div class="org-card-header">
            <div class="org-header-left">
              <div class="org-header-badge icon-school">
                ${svgIcon('building')}
              </div>
              <div>
                <div class="org-title-line">
                  <h3 id="org-schools-title" class="org-section-title">Nhà trường</h3>
                  <span class="org-count-tag" data-school-count-pill>${filteredSchools.length} / ${schools.length} tổ chức</span>
                </div>
                <p class="org-section-sub">Trường đại học, cao đẳng và các cơ sở giáo dục đối tác</p>
              </div>
            </div>
          </div>

          <div class="org-section-toolbar">
            <div class="org-search-box">
              ${svgIcon('search')}
              <input type="search" class="org-input-search" placeholder="Tìm trường học theo tên, mã ID..." value="${escapeHtml(orgSchoolSearch)}" data-school-search-input>
              ${orgSchoolSearch ? `<button type="button" class="org-clear-btn" data-school-search-clear aria-label="Xóa tìm kiếm">&times;</button>` : ''}
            </div>

            <div class="org-filter-group">
              <label for="org-school-verification-select" class="org-label-filter">Xác minh:</label>
              <select id="org-school-verification-select" class="typeui-select org-select-field" data-school-verification-select>
                <option value="all"${orgSchoolVerification === 'all' ? ' selected' : ''}>Tất cả trạng thái</option>
                <option value="verified"${orgSchoolVerification === 'verified' ? ' selected' : ''}>Đã xác minh</option>
                <option value="pending"${orgSchoolVerification === 'pending' ? ' selected' : ''}>Chờ duyệt</option>
                <option value="rejected"${orgSchoolVerification === 'rejected' ? ' selected' : ''}>Từ chối</option>
              </select>
            </div>

            <div class="org-filter-group">
              <label for="org-school-level-select" class="org-label-filter">Cấp bậc:</label>
              <select id="org-school-level-select" class="typeui-select org-select-field" data-school-level-select>
                <option value="all"${orgSchoolLevel === 'all' ? ' selected' : ''}>Tất cả cấp bậc</option>
                <option value="cap2"${orgSchoolLevel === 'cap2' ? ' selected' : ''}>Cấp 2</option>
                <option value="cap3"${orgSchoolLevel === 'cap3' ? ' selected' : ''}>Cấp 3</option>
                <option value="cao_dang_dai_hoc"${orgSchoolLevel === 'cao_dang_dai_hoc' ? ' selected' : ''}>Cao đẳng / Đại học</option>
              </select>
            </div>

            <div class="org-toolbar-status-meta">
              ${(orgSchoolSearch || orgSchoolVerification !== 'all' || orgSchoolLevel !== 'all') ? `
                <span class="org-active-filter-badge">
                  Đang lọc: <b>${filteredSchools.length}</b> kết quả
                  <button type="button" class="org-btn-reset" data-school-reset-filters title="Xóa lọc">Xóa lọc</button>
                </span>
              ` : `
                <span class="org-all-count-text">Hiển thị <b>${schools.length}</b> trường học</span>
              `}
            </div>
          </div>

          <div class="org-table-container" data-schools-table-wrap>
            ${renderOrgTable(filteredSchools, 'school', schools.length, actions)}
          </div>
        </section>

        <!-- KHU 3: DOANH NGHIỆP -->
        <section class="panel org-section-card" id="org-enterprises-section" aria-labelledby="org-enterprises-title">
          <div class="org-card-header">
            <div class="org-header-left">
              <div class="org-header-badge icon-enterprise">
                ${svgIcon('briefcase')}
              </div>
              <div>
                <div class="org-title-line">
                  <h3 id="org-enterprises-title" class="org-section-title">Doanh nghiệp</h3>
                  <span class="org-count-tag" data-enterprise-count-pill>${filteredEnterprises.length} / ${enterprises.length} tổ chức</span>
                </div>
                <p class="org-section-sub">Doanh nghiệp, công ty tiếp nhận thực tập và đối tác tuyển dụng</p>
              </div>
            </div>
          </div>

          <div class="org-section-toolbar">
            <div class="org-search-box">
              ${svgIcon('search')}
              <input type="search" class="org-input-search" placeholder="Tìm doanh nghiệp theo tên, mã ID..." value="${escapeHtml(orgEnterpriseSearch)}" data-enterprise-search-input>
              ${orgEnterpriseSearch ? `<button type="button" class="org-clear-btn" data-enterprise-search-clear aria-label="Xóa tìm kiếm">&times;</button>` : ''}
            </div>

            <div class="org-filter-group">
              <label for="org-enterprise-verification-select" class="org-label-filter">Xác minh:</label>
              <select id="org-enterprise-verification-select" class="typeui-select org-select-field" data-enterprise-verification-select>
                <option value="all"${orgEnterpriseVerification === 'all' ? ' selected' : ''}>Tất cả trạng thái</option>
                <option value="verified"${orgEnterpriseVerification === 'verified' ? ' selected' : ''}>Đã xác minh</option>
                <option value="pending"${orgEnterpriseVerification === 'pending' ? ' selected' : ''}>Chờ duyệt</option>
                <option value="rejected"${orgEnterpriseVerification === 'rejected' ? ' selected' : ''}>Từ chối</option>
              </select>
            </div>

            <div class="org-toolbar-status-meta">
              ${(orgEnterpriseSearch || orgEnterpriseVerification !== 'all') ? `
                <span class="org-active-filter-badge">
                  Đang lọc: <b>${filteredEnterprises.length}</b> kết quả
                  <button type="button" class="org-btn-reset" data-enterprise-reset-filters title="Xóa lọc">Xóa lọc</button>
                </span>
              ` : `
                <span class="org-all-count-text">Hiển thị <b>${enterprises.length}</b> doanh nghiệp</span>
              `}
            </div>
          </div>

          <div class="org-table-container" data-enterprises-table-wrap>
            ${renderOrgTable(filteredEnterprises, 'enterprise', enterprises.length, actions)}
          </div>
        </section>
      </div>
    `;
  };

  const updateSchoolTableView = () => {
    const schools = currentRows.filter((row) => row.type === 'school');
    const filteredSchools = getFilteredSchools(schools);
    const wrap = moduleContent.querySelector('[data-schools-table-wrap]');
    if (wrap) wrap.innerHTML = renderOrgTable(filteredSchools, 'school', schools.length, orgActionsRef);
    const pill = moduleContent.querySelector('[data-school-count-pill]');
    if (pill) pill.textContent = `${filteredSchools.length} / ${schools.length} tổ chức`;
    const statusMeta = moduleContent.querySelector('#org-schools-section .org-toolbar-status-meta');
    if (statusMeta) {
      statusMeta.innerHTML = (orgSchoolSearch || orgSchoolVerification !== 'all')
        ? `<span class="org-active-filter-badge">Đang lọc: <b>${filteredSchools.length}</b> kết quả <button type="button" class="org-btn-reset" data-school-reset-filters title="Xóa lọc">Xóa lọc</button></span>`
        : `<span class="org-all-count-text">Hiển thị <b>${schools.length}</b> trường học</span>`;
    }
  };

  const updateEnterpriseTableView = () => {
    const enterprises = currentRows.filter((row) => row.type === 'enterprise');
    const filteredEnterprises = getFilteredEnterprises(enterprises);
    const wrap = moduleContent.querySelector('[data-enterprises-table-wrap]');
    if (wrap) wrap.innerHTML = renderOrgTable(filteredEnterprises, 'enterprise', enterprises.length, orgActionsRef);
    const pill = moduleContent.querySelector('[data-enterprise-count-pill]');
    if (pill) pill.textContent = `${filteredEnterprises.length} / ${enterprises.length} tổ chức`;
    const statusMeta = moduleContent.querySelector('#org-enterprises-section .org-toolbar-status-meta');
    if (statusMeta) {
      statusMeta.innerHTML = (orgEnterpriseSearch || orgEnterpriseVerification !== 'all')
        ? `<span class="org-active-filter-badge">Đang lọc: <b>${filteredEnterprises.length}</b> kết quả <button type="button" class="org-btn-reset" data-enterprise-reset-filters title="Xóa lọc">Xóa lọc</button></span>`
        : `<span class="org-all-count-text">Hiển thị <b>${enterprises.length}</b> doanh nghiệp</span>`;
    }
  };

  const updateUserTableView = () => {
    const currentZone = userZones.find((z) => z.id === activeUserZone) || userZones[0];
    const { zoneUsers, filtered } = getFilteredUsers(currentZone);
    const countBadge = moduleContent.querySelector('[data-user-zone-count]');
    if (countBadge) countBadge.textContent = `${filtered.length.toLocaleString('vi-VN')} / ${zoneUsers.length.toLocaleString('vi-VN')} tài khoản`;
    const filterInfo = moduleContent.querySelector('[data-user-filter-info]');
    if (filterInfo) {
      filterInfo.innerHTML = (activeUserSearch || activeUserStatus !== 'all' || activeUserSubRole !== 'all')
        ? `<span class="user-filtered-tag">Đang lọc: <b>${filtered.length}</b> kết quả <button type="button" class="user-btn-reset-filters" data-user-reset-filters title="Xóa toàn bộ bộ lọc">Xóa lọc</button></span>`
        : `<span class="user-total-info">Hiển thị toàn bộ <b>${filtered.length}</b> tài khoản</span>`;
    }
    const tableWrap = moduleContent.querySelector('.user-table-wrap');
    if (tableWrap) tableWrap.innerHTML = renderUserTable(filtered, currentZone, zoneUsers, userActionsRef);
  };

  moduleContent?.addEventListener('click', (event) => {
    if (currentSection === 'organizations') {
      const refreshBtn = event.target.closest('[data-org-refresh-btn]');
      if (refreshBtn) {
        loadSection('organizations');
        return;
      }
      const schoolClear = event.target.closest('[data-school-search-clear]');
      if (schoolClear) {
        orgSchoolSearch = '';
        const input = moduleContent.querySelector('[data-school-search-input]');
        if (input) input.value = '';
        updateSchoolTableView();
        return;
      }
      const schoolReset = event.target.closest('[data-school-reset-filters]');
      if (schoolReset) {
        orgSchoolSearch = '';
        orgSchoolLevel = 'all';
        orgSchoolVerification = 'all';
        const input = moduleContent.querySelector('[data-school-search-input]');
        if (input) input.value = '';
        const selectV = moduleContent.querySelector('[data-school-verification-select]');
        if (selectV) selectV.value = 'all';
        const selectL = moduleContent.querySelector('[data-school-level-select]');
        if (selectL) selectL.value = 'all';
        updateSchoolTableView();
        return;
      }
      const enterpriseClear = event.target.closest('[data-enterprise-search-clear]');
      if (enterpriseClear) {
        orgEnterpriseSearch = '';
        const input = moduleContent.querySelector('[data-enterprise-search-input]');
        if (input) input.value = '';
        updateEnterpriseTableView();
        return;
      }
      const enterpriseReset = event.target.closest('[data-enterprise-reset-filters]');
      if (enterpriseReset) {
        orgEnterpriseSearch = '';
        orgEnterpriseVerification = 'all';
        const input = moduleContent.querySelector('[data-enterprise-search-input]');
        if (input) input.value = '';
        const select = moduleContent.querySelector('[data-enterprise-verification-select]');
        if (select) select.value = 'all';
        updateEnterpriseTableView();
        return;
      }
      return;
    }
    if (currentSection !== 'users') return;
    const zoneCard = event.target.closest('[data-zone-select]');
    if (zoneCard && !event.target.closest('[data-user-create]')) {
      const newZone = zoneCard.dataset.zoneSelect;
      if (newZone && newZone !== activeUserZone) {
        activeUserZone = newZone;
        activeUserSubRole = 'all';
        renderUsersSection(userActionsRef);
      }
      return;
    }
    const subRoleBtn = event.target.closest('[data-user-subrole]');
    if (subRoleBtn) {
      activeUserSubRole = subRoleBtn.dataset.userSubrole;
      renderUsersSection(userActionsRef);
      return;
    }
    const refreshBtn = event.target.closest('[data-user-refresh-btn]');
    if (refreshBtn) {
      loadSection('users');
      return;
    }
    const clearSearchBtn = event.target.closest('[data-user-search-clear]');
    if (clearSearchBtn) {
      activeUserSearch = '';
      const searchInput = moduleContent.querySelector('[data-user-search-input]');
      if (searchInput) searchInput.value = '';
      updateUserTableView();
      return;
    }
    const resetFiltersBtn = event.target.closest('[data-user-reset-filters]');
    if (resetFiltersBtn) {
      activeUserSearch = '';
      activeUserStatus = 'all';
      activeUserSubRole = 'all';
      renderUsersSection(userActionsRef);
      return;
    }
  });

  moduleContent?.addEventListener('change', (event) => {
    if (currentSection === 'organizations') {
      const schoolSelect = event.target.closest('[data-school-verification-select]');
      if (schoolSelect) {
        orgSchoolVerification = schoolSelect.value;
        updateSchoolTableView();
        return;
      }
      const schoolLevelSelect = event.target.closest('[data-school-level-select]');
      if (schoolLevelSelect) {
        orgSchoolLevel = schoolLevelSelect.value;
        updateSchoolTableView();
        return;
      }
      const enterpriseSelect = event.target.closest('[data-enterprise-verification-select]');
      if (enterpriseSelect) {
        orgEnterpriseVerification = enterpriseSelect.value;
        updateEnterpriseTableView();
        return;
      }
      return;
    }
    if (currentSection !== 'users') return;
    const statusSelect = event.target.closest('[data-user-status-select]');
    if (statusSelect) {
      activeUserStatus = statusSelect.value;
      updateUserTableView();
    }
  });

  moduleContent?.addEventListener('input', (event) => {
    if (currentSection === 'organizations') {
      const schoolInput = event.target.closest('[data-school-search-input]');
      if (schoolInput) {
        orgSchoolSearch = schoolInput.value;
        updateSchoolTableView();
        return;
      }
      const enterpriseInput = event.target.closest('[data-enterprise-search-input]');
      if (enterpriseInput) {
        orgEnterpriseSearch = enterpriseInput.value;
        updateEnterpriseTableView();
        return;
      }
      return;
    }
    if (currentSection !== 'users') return;
    const searchInput = event.target.closest('[data-user-search-input]');
    if (searchInput) {
      activeUserSearch = searchInput.value;
      updateUserTableView();
    }
  });

  tasksView?.addEventListener('click', (event) => {
    const tabBtn = event.target.closest('[data-tasks-tab]');
    if (tabBtn) {
      activeTasksTab = tabBtn.dataset.tasksTab;
      document.querySelectorAll('[data-tasks-tab]').forEach((b) => {
        const active = b === tabBtn;
        b.classList.toggle('is-active', active);
        b.setAttribute('aria-selected', String(active));
      });
      renderTasksView();
      return;
    }

    const refreshBtn = event.target.closest('[data-tasks-refresh]');
    if (refreshBtn) {
      const original = refreshBtn.innerHTML;
      refreshBtn.disabled = true;
      loadTasksSection().finally(() => {
        refreshBtn.disabled = false;
        showToast('Đã làm mới danh sách việc cần xử lý.');
      });
      return;
    }

    const resetFilterBtn = event.target.closest('[data-tasks-reset-filter]');
    if (resetFilterBtn) {
      activeTasksTab = 'all';
      activeTasksDomain = 'all';
      activeTasksSearch = '';
      const searchInp = document.querySelector('[data-tasks-search]');
      if (searchInp) searchInp.value = '';
      const selectInp = document.querySelector('[data-tasks-domain-filter]');
      if (selectInp) selectInp.value = 'all';
      document.querySelectorAll('[data-tasks-tab]').forEach((b) => {
        const active = b.dataset.tasksTab === 'all';
        b.classList.toggle('is-active', active);
        b.setAttribute('aria-selected', String(active));
      });
      renderTasksView();
      return;
    }

    const viewBtn = event.target.closest('[data-task-view]');
    if (viewBtn) {
      const sec = viewBtn.dataset.taskView;
      const id = viewBtn.dataset.id;
      loadSection(sec, id);
      return;
    }

    const actionBtn = event.target.closest('[data-task-action]');
    if (actionBtn) {
      const act = actionBtn.dataset.taskAction;
      const id = actionBtn.dataset.id;
      if (act === 'org') {
        pendingAction = { kind: 'organization', id: id, type: actionBtn.dataset.type };
        const decisionField = document.querySelector('[data-decision-field]');
        const decisionSelect = document.querySelector('[data-organization-decision]');
        if (decisionField) decisionField.hidden = false;
        if (decisionSelect) {
          decisionSelect.hidden = false;
          decisionSelect.value = actionBtn.dataset.currentStatus === 'rejected' ? 'rejected' : 'verified';
        }
        document.querySelector('[data-action-title]').textContent = 'Xét duyệt tổ chức';
        document.querySelector('[data-action-description]').textContent = 'Thao tác này sẽ được ghi vào audit log. Vui lòng cung cấp lý do.';
        actionDialog?.showModal();
        document.querySelector('#action-reason')?.focus();
      } else if (act === 'user') {
        pendingAction = { kind: 'user', id: id, status: actionBtn.dataset.status || 'active' };
        const decisionField = document.querySelector('[data-decision-field]');
        const decisionSelect = document.querySelector('[data-organization-decision]');
        if (decisionField) decisionField.hidden = true;
        if (decisionSelect) decisionSelect.hidden = true;
        document.querySelector('[data-action-title]').textContent = 'Kích hoạt / Xem xét tài khoản';
        document.querySelector('[data-action-description]').textContent = 'Thao tác này sẽ được ghi vào audit log. Vui lòng cung cấp lý do.';
        actionDialog?.showModal();
        document.querySelector('#action-reason')?.focus();
      } else {
        const sec = actionBtn.dataset.viewSection || 'dashboard';
        loadSection(sec, id);
      }
      return;
    }
  });

  tasksView?.addEventListener('input', (event) => {
    const searchInp = event.target.closest('[data-tasks-search]');
    if (searchInp) {
      activeTasksSearch = searchInp.value;
      renderTasksView();
    }
  });

  tasksView?.addEventListener('change', (event) => {
    const domainSelect = event.target.closest('[data-tasks-domain-filter]');
    if (domainSelect) {
      activeTasksDomain = domainSelect.value;
      renderTasksView();
    }
  });

  const actionDialog=document.querySelector('[data-action-dialog]');
  moduleContent?.addEventListener('click',(event)=>{const userButton=event.target.closest('[data-user-action]');const orgButton=event.target.closest('[data-org-action]');const deleteButton=event.target.closest('[data-user-delete]');if(!userButton&&!orgButton&&!deleteButton)return;pendingAction=deleteButton?{kind:'delete',id:deleteButton.dataset.id}:userButton?{kind:'user',id:userButton.dataset.id,status:userButton.dataset.status}:{kind:'organization',id:orgButton.dataset.id,type:orgButton.dataset.type};const decisionField=document.querySelector('[data-decision-field]');const decisionSelect=document.querySelector('[data-organization-decision]');const isOrganization=Boolean(orgButton);decisionField.hidden=!isOrganization;decisionSelect.hidden=!isOrganization;if(isOrganization){const current=orgButton.dataset.currentStatus;decisionSelect.value=current==='rejected'?'rejected':'verified';}document.querySelector('[data-action-title]').textContent=deleteButton?'Vô hiệu hóa tài khoản':userButton?(userButton.dataset.status==='active'?'Đình chỉ tài khoản':'Kích hoạt tài khoản'):'Xét duyệt tổ chức';document.querySelector('[data-action-description]').textContent=deleteButton?'Tài khoản sẽ chuyển sang trạng thái vô hiệu hóa; dữ liệu liên quan và audit log được giữ nguyên.':'Thao tác này sẽ được ghi vào audit log. Vui lòng cung cấp lý do.';actionDialog.showModal();document.querySelector('#action-reason').focus();});
  document.querySelector('[data-action-form]')?.addEventListener('submit',async(event)=>{event.preventDefault();if(!pendingAction)return;const reason=document.querySelector('#action-reason').value.trim();if(reason.length<5){showToast('Lý do phải có ít nhất 5 ký tự.');return;}const submit=event.submitter;submit.disabled=true;const original=submit.textContent;submit.textContent='Đang xử lý…';try{const csrf=(await api('/auth/csrf')).csrfToken;const isDelete=pendingAction.kind==='delete';const path=isDelete?`/admin/users/${encodeURIComponent(pendingAction.id)}`:pendingAction.kind==='user'?`/admin/users/${encodeURIComponent(pendingAction.id)}/status`:`/admin/organizations/${encodeURIComponent(pendingAction.type)}/${encodeURIComponent(pendingAction.id)}/verification`;const body=pendingAction.kind==='user'?{status:pendingAction.status,reason}:pendingAction.kind==='organization'?{decision:document.querySelector('[data-organization-decision]').value,reason}:{reason};await api(path,{method:isDelete?'DELETE':'PATCH',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(body)});actionDialog.close();document.querySelector('#action-reason').value='';showToast('Đã cập nhật và ghi audit log.');await loadSection(currentSection);}catch(error){showToast(error.message);}finally{submit.disabled=false;submit.textContent=original;}});
  const accountDialog=document.querySelector('[data-account-dialog]');const accountForm=document.querySelector('[data-account-form]');
  moduleContent?.addEventListener('click',(event)=>{const create=event.target.closest('[data-user-create]');const edit=event.target.closest('[data-user-edit]');if(!create&&!edit)return;accountForm.reset();const row=edit?currentRows.find((item)=>item.id===edit.dataset.id):null;accountForm.elements.id.value=row?.id||'';accountForm.elements.fullName.value=row?.fullName||'';accountForm.elements.email.value=row?.email||'';if(row){accountForm.elements.role.value=row.role||'student';}else{const defaultRole=activeUserZone==='school'?'school':activeUserZone==='enterprise'?'enterprise':activeUserZone==='system'?'platform_admin':'student';accountForm.elements.role.value=defaultRole;}accountForm.elements.password.required=!row;document.querySelector('[data-password-field]').hidden=Boolean(row);document.querySelector('[data-account-title]').textContent=row?'Sửa tài khoản':'Thêm tài khoản';accountDialog.showModal();accountForm.elements.fullName.focus();});
  document.querySelectorAll('[data-account-close]').forEach((button)=>button.addEventListener('click',()=>accountDialog.close()));
  accountForm?.addEventListener('submit',async(event)=>{event.preventDefault();if(!accountForm.checkValidity()){accountForm.reportValidity();return;}const values=Object.fromEntries(new FormData(accountForm));const editing=values.id!=='';const csrf=(await api('/auth/csrf')).csrfToken;try{await api(editing?`/admin/users/${encodeURIComponent(values.id)}`:'/admin/users',{method:editing?'PATCH':'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(values)});accountDialog.close();showToast(editing?'Đã cập nhật tài khoản.':'Đã tạo tài khoản.');await loadSection('users');}catch(error){showToast(error.message);}});
  document.querySelector('[data-dashboard-organizations]')?.addEventListener('click',(event)=>{if(event.target.closest('[data-dashboard-section="organizations"]'))loadSection('organizations');});
  const urlParams = new URLSearchParams(window.location.search);
  const pathSection = window.location.pathname.includes('/users') ? 'users' : '';
  const initial = location.hash.slice(1) || urlParams.get('section') || pathSection;
  if(labels[initial])loadSection(initial);else if(initial==='tasks'||initial==='queue')loadSection('tasks');else{document.querySelectorAll('[data-admin-section]').forEach((item)=>{const active=item.dataset.adminSection==='dashboard';item.classList.toggle('is-active',active);active?item.setAttribute('aria-current','page'):item.removeAttribute('aria-current');});refreshDashboard();}
  document.querySelector('[data-admin-logout]')?.addEventListener('click', async (event) => {
    const button = event.currentTarget;
    button.disabled = true;
    const original = button.innerHTML;
    button.textContent = 'Đang đăng xuất…';
    try {
      const csrf = (await api('/auth/csrf')).csrfToken;
      await api('/auth/logout', { method:'POST', headers:{'X-CSRF-Token':csrf} });
      window.location.assign(`${basePath}/login.php`);
    } catch (error) {
      button.disabled = false;
      button.innerHTML = original;
      showToast(`Không thể đăng xuất: ${error.message}`);
    }
  });
})();
