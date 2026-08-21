(() => {
  'use strict';

  const root = document.documentElement;
  const sidebar = document.querySelector('#admin-sidebar');
  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
  const sidebarClose = document.querySelector('[data-sidebar-close]');
  const commandDialog = document.querySelector('[data-command-dialog]');
  const commandInput = document.querySelector('[data-command-input]');
  const commandItems = [...document.querySelectorAll('[data-command-item]')];
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

  commandInput?.addEventListener('input', () => {
    const query = commandInput.value.trim().toLocaleLowerCase('vi');
    commandItems.forEach((item) => {
      item.hidden = query !== '' && !item.textContent.toLocaleLowerCase('vi').includes(query);
    });
  });
  commandItems.forEach((item, index) => item.addEventListener('click', () => {
    closeCommand();
    const targets = ['users', 'organizations', 'audit', 'organizations'];
    document.querySelector(`[data-admin-section="${targets[index] || 'dashboard'}"]`)?.click();
  }));

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

  document.querySelectorAll('[data-queue-item]').forEach((item) => item.addEventListener('click', () => {
    showToast(`Đã chọn: ${item.dataset.title}. Mở module liên quan để xử lý.`);
  }));

  const orgFilter = document.querySelector('[data-org-filter]');
  const orgRows = [...document.querySelectorAll('[data-org-row]')];
  orgFilter?.addEventListener('input', () => {
    const query = orgFilter.value.trim().toLocaleLowerCase('vi');
    orgRows.forEach((row) => { row.hidden = query !== '' && !row.textContent.toLocaleLowerCase('vi').includes(query); });
  });

  const basePath = location.pathname.includes('/app/') ? location.pathname.split('/app/')[0] : '';
  const api = (path, options = {}) => fetch(`${basePath}/api/v1${path}`, { credentials: 'same-origin', ...options }).then(async (response) => {
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload?.error?.message || `HTTP ${response.status}`);
    return payload.data;
  });
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
  const statusLabels = {active:'Hoạt động',pending:'Chờ duyệt',suspended:'Tạm khóa',disabled:'Vô hiệu hóa',verified:'Đã xác minh',rejected:'Từ chối',inactive:'Chưa kích hoạt',paid:'Đã thanh toán',sent:'Đã gửi',failed:'Thất bại',submitted:'Đã nộp',applied:'Đã ứng tuyển'};
  const roleLabels = {student:'Học sinh',teacher:'Giáo viên',school:'Nhà trường',enterprise:'Doanh nghiệp',platform_admin:'Quản trị nền tảng'};
  const columnLabels = {fullName:'Họ và tên',email:'Email',role:'Vai trò',status:'Trạng thái',createdAt:'Ngày tạo',lastLoginAt:'Đăng nhập gần nhất',name:'Tổ chức',type:'Loại',verificationStatus:'Xác minh',title:'Tiêu đề',category:'Danh mục',startAt:'Bắt đầu',endAt:'Kết thúc',amount:'Số tiền',currency:'Tiền tệ',paymentStatus:'Thanh toán',provider:'Nhà cung cấp',notificationStatus:'Trạng thái',deliveryChannel:'Kênh gửi',isRead:'Đã đọc',action:'Sự kiện',entityType:'Đối tượng',entityId:'Mã đối tượng',userId:'Người thực hiện',requestId:'Request ID',permissions:'Số quyền',description:'Mô tả',code:'Mã vai trò'};
  const hiddenColumns = new Set(['id','postId','studentId','orderId']);
  const dateKeys = new Set(['createdAt','lastLoginAt','startAt','endAt','appliedAt','reviewedAt']);
  const formatDate = (value) => { if (!value) return 'Chưa có'; const raw=String(value);const normalized=/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(raw)?raw.replace(' ','T')+'Z':raw;const date=new Date(normalized);return Number.isNaN(date.getTime())?raw:new Intl.DateTimeFormat('vi-VN',{dateStyle:'short',timeStyle:'short',timeZone:'Asia/Ho_Chi_Minh'}).format(date); };
  const statusTone = (value) => ['active','verified','paid','sent','completed','approved'].includes(String(value).toLowerCase()) ? 'success' : ['rejected','failed','disabled','cancelled'].includes(String(value).toLowerCase()) ? 'danger' : 'warning';
  const formatCell = (key,value) => { if (dateKeys.has(key)) return escapeHtml(formatDate(value)); if (key==='role'||key==='type') return escapeHtml(roleLabels[value]||value||'—'); if (key==='isRead') return value ? 'Đã đọc' : 'Chưa đọc'; if (key==='amount'&&value!==null) return Number(value).toLocaleString('vi-VN'); if (key.toLowerCase().includes('status')) return `<span class="status-badge ${statusTone(value)}">${escapeHtml(statusLabels[String(value).toLowerCase()]||value||'—')}</span>`; return escapeHtml(value??'—'); };
  const labels = {
    users:['Người dùng','Danh tính, vai trò, trạng thái và truy cập tài khoản.'],
    organizations:['Tổ chức','School, Enterprise và hàng đợi xác minh.'],
    activities:['Học tập & hoạt động','Theo dõi hoạt động, lịch và trạng thái vận hành.'],
    applications:['Cơ hội & ứng tuyển','Theo dõi quy trình và các hồ sơ cần duyệt.'],
    payments:['Tài trợ & thanh toán','Theo dõi lệnh thanh toán và ngoại lệ đối soát.'],
    notifications:['Thông báo','Theo dõi delivery, trạng thái và retry.'],
    audit:['Audit & bảo mật','Dòng sự kiện theo người dùng, đối tượng và request ID.'],
    rbac:['RBAC & quyền','Vai trò và quyền hiệu lực trong hệ thống.'],
    system:['Hệ thống','Phiên bản runtime, database và migration status.'],
  };
  const dashboardView = document.querySelector('[data-dashboard-view]');
  const moduleView = document.querySelector('[data-module-view]');
  const moduleContent = document.querySelector('[data-module-content]');
  const moduleState = document.querySelector('[data-module-state]');
  const moduleSearch = document.querySelector('[data-module-search]');
  let currentSection = 'dashboard';
  let currentRows = [];
  let pendingAction = null;
  const refreshDashboard = async () => {
    try {
      const data = await api('/admin/dashboard');
      data.organizations = Number(data.schools || 0) + Number(data.enterprises || 0);
      document.querySelectorAll('[data-dashboard-metric]').forEach((element) => { element.textContent = Number(data[element.dataset.dashboardMetric] || 0).toLocaleString('vi-VN'); });
      const queue = document.querySelector('.queue-list');
      if (queue) queue.innerHTML = (data.queue || []).length ? data.queue.map((item)=>`<button class="queue-item" type="button" data-queue-section="${escapeHtml(item.type)}"><span class="severity ${escapeHtml(item.severity)}" aria-hidden="true"></span><span class="queue-content"><strong>${escapeHtml(item.title)}</strong><small>${Number(item.count).toLocaleString('vi-VN')} bản ghi · ${escapeHtml(item.detail)}</small><span><b>${escapeHtml(item.owner)}</b> · Dữ liệu thời gian thực</span></span><span aria-hidden="true">›</span></button>`).join('') : '<div class="empty-state compact"><strong>Không có việc tồn đọng</strong><p>Các hàng đợi hiện đang ổn định.</p></div>';
      const queueCount=(data.queue||[]).reduce((sum,item)=>sum+Number(item.count||0),0);
      const queueBadge=document.querySelector('[data-queue-count]');if(queueBadge)queueBadge.textContent=`${queueCount.toLocaleString('vi-VN')} mục`;
      document.querySelectorAll('[data-nav-count]').forEach((badge)=>{const matched=(data.queue||[]).find((item)=>item.type===badge.dataset.navCount);const count=badge.dataset.navCount==='dashboard'?queueCount:Number(matched?.count||0);badge.textContent=count.toLocaleString('vi-VN');badge.hidden=count===0;badge.setAttribute('aria-label',`${count} mục cần xử lý`);});
      const alertButton=document.querySelector('[data-alert-count]');if(alertButton){alertButton.hidden=queueCount===0;alertButton.setAttribute('aria-label',`${queueCount} mục cần xử lý`);}
      const roleBox=document.querySelector('[data-role-distribution]');if(roleBox){const entries=Object.entries(data.usersByRole||{});const max=Math.max(1,...entries.map(([,count])=>Number(count)));roleBox.innerHTML=entries.length?`<div class="distribution-list">${entries.map(([role,count])=>`<div class="distribution-row"><div><span>${escapeHtml(roleLabels[role]||role)}</span><strong>${Number(count).toLocaleString('vi-VN')}</strong></div><div class="distribution-track"><span style="width:${Math.max(3,Number(count)/max*100)}%"></span></div></div>`).join('')}</div>`:'<div class="empty-state compact"><strong>Chưa có người dùng</strong></div>';}
      const orgBody=document.querySelector('[data-dashboard-organizations]');if(orgBody){orgBody.innerHTML=(data.recentOrganizations||[]).map((org)=>`<tr><td><div class="org-cell"><span class="org-logo">${escapeHtml(String(org.name||'?').slice(0,1).toUpperCase())}</span><div><strong>${escapeHtml(org.name)}</strong><small>${escapeHtml(roleLabels[org.type]||org.type)}</small></div></div></td><td><span class="status-badge ${statusTone(org.verificationStatus)}">${escapeHtml(statusLabels[org.verificationStatus]||org.verificationStatus)}</span></td><td colspan="3" class="muted-cell">Tạo lúc ${escapeHtml(formatDate(org.createdAt))}</td><td><button class="button secondary small" type="button" data-dashboard-section="organizations">Mở</button></td></tr>`).join('')||'<tr><td colspan="6"><div class="empty-state compact">Chưa có tổ chức.</div></td></tr>';}
      const auditList=document.querySelector('[data-dashboard-audit]');if(auditList){auditList.innerHTML=(data.recentAudits||[]).map((event)=>`<li><time>${escapeHtml(formatDate(event.createdAt))}</time><span class="audit-dot"></span><div><strong>${escapeHtml(event.action)}</strong><small>${escapeHtml(event.entityType)} · ${escapeHtml(event.entityId)}</small></div></li>`).join('')||'<li><div>Chưa có sự kiện audit.</div></li>';}
      const updated=document.querySelector('.last-updated');if(updated)updated.lastChild.textContent=` Cập nhật ${formatDate(data.generatedAt)}`;
    } catch (error) { showToast(`Không thể cập nhật dashboard: ${error.message}`); }
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
    if (section === 'system') { moduleContent.innerHTML = `<div class="system-grid">${Object.entries(data).map(([key,value])=>`<article><span>${escapeHtml(key)}</span><strong>${escapeHtml(value)}</strong></article>`).join('')}</div>`; return; }
    currentRows = data.items || [];
    const actions = section === 'users' ? (row) => {const organizationPending=['school','enterprise'].includes(row.role)&&row.status==='pending';return `<div class="row-actions"><button class="button secondary small" data-user-edit data-id="${escapeHtml(row.id)}">Sửa</button>${organizationPending?'<span class="action-note">Duyệt tại Tổ chức</span>':`<button class="button secondary small" data-user-action data-id="${escapeHtml(row.id)}" data-status="${row.status==='active'?'suspended':'active'}">${row.status==='active'?'Đình chỉ':'Kích hoạt'}</button>`}${row.status!=='disabled'?`<button class="button danger small" data-user-delete data-id="${escapeHtml(row.id)}">Vô hiệu hóa</button>`:''}</div>`;} : section === 'organizations' ? (row) => `<button class="button secondary small" data-org-action data-id="${escapeHtml(row.id)}" data-type="${escapeHtml(row.type)}" data-current-status="${escapeHtml(row.verificationStatus)}">Xem xét</button>` : null;
    moduleContent.innerHTML = (section==='users'?'<button class="button primary module-primary-action" data-user-create>Thêm tài khoản</button>':'')+table(currentRows, actions);
  };
  const loadSection = async (section) => {
    currentSection = section;
    document.querySelectorAll('[data-admin-section]').forEach((item)=>{const active=item.dataset.adminSection===section;item.classList.toggle('is-active',active);active?item.setAttribute('aria-current','page'):item.removeAttribute('aria-current');});
    if (section === 'dashboard') { dashboardView.hidden=false;moduleView.hidden=true;history.replaceState(null,'','#dashboard');refreshDashboard();closeSidebar();return; }
    dashboardView.hidden=true;moduleView.hidden=false;moduleContent.hidden=true;moduleState.hidden=false;moduleState.textContent='Đang tải dữ liệu…';
    const [title,description]=labels[section]||['Module Admin',''];document.querySelector('[data-module-title]').textContent=title;document.querySelector('[data-module-description]').textContent=description;if(moduleSearch){moduleSearch.value='';moduleSearch.placeholder=`Tìm trong ${title.toLocaleLowerCase('vi')}...`;}
    const path = ['users','organizations','audit','rbac','system'].includes(section)?`/admin/${section}`:`/admin/resources/${section}`;
    try { const data=await api(path);renderModule(section,data);moduleState.hidden=true;moduleContent.hidden=false;history.replaceState(null,'',`#${section}`); }
    catch(error){moduleState.hidden=false;moduleState.innerHTML=`<strong>Không thể tải dữ liệu</strong><p>${escapeHtml(error.message)}</p>`;}
    closeSidebar();
  };
  document.querySelectorAll('[data-admin-section]').forEach((link)=>link.addEventListener('click',(event)=>{event.preventDefault();loadSection(link.dataset.adminSection);}));
  document.querySelector('[data-module-refresh]')?.addEventListener('click',()=>loadSection(currentSection));
  document.querySelector('.queue-list')?.addEventListener('click',(event)=>{const item=event.target.closest('[data-queue-section]');if(item)document.querySelector(`[data-admin-section="${item.dataset.queueSection}"]`)?.click();});
  moduleSearch?.addEventListener('input',()=>{const query=moduleSearch.value.trim().toLocaleLowerCase('vi');moduleContent.querySelectorAll('tbody tr').forEach((row)=>row.hidden=query!==''&&!row.textContent.toLocaleLowerCase('vi').includes(query));});

  const actionDialog=document.querySelector('[data-action-dialog]');
  moduleContent?.addEventListener('click',(event)=>{const userButton=event.target.closest('[data-user-action]');const orgButton=event.target.closest('[data-org-action]');const deleteButton=event.target.closest('[data-user-delete]');if(!userButton&&!orgButton&&!deleteButton)return;pendingAction=deleteButton?{kind:'delete',id:deleteButton.dataset.id}:userButton?{kind:'user',id:userButton.dataset.id,status:userButton.dataset.status}:{kind:'organization',id:orgButton.dataset.id,type:orgButton.dataset.type};const decisionField=document.querySelector('[data-decision-field]');const decisionSelect=document.querySelector('[data-organization-decision]');const isOrganization=Boolean(orgButton);decisionField.hidden=!isOrganization;decisionSelect.hidden=!isOrganization;if(isOrganization){const current=orgButton.dataset.currentStatus;decisionSelect.value=['verified','rejected','pending'].includes(current)?current:'verified';}document.querySelector('[data-action-title]').textContent=deleteButton?'Vô hiệu hóa tài khoản':userButton?(userButton.dataset.status==='active'?'Đình chỉ tài khoản':'Kích hoạt tài khoản'):'Xét duyệt tổ chức';document.querySelector('[data-action-description]').textContent=deleteButton?'Tài khoản sẽ chuyển sang trạng thái vô hiệu hóa; dữ liệu liên quan và audit log được giữ nguyên.':'Thao tác này sẽ được ghi vào audit log. Vui lòng cung cấp lý do.';actionDialog.showModal();document.querySelector('#action-reason').focus();});
  document.querySelector('[data-action-form]')?.addEventListener('submit',async(event)=>{event.preventDefault();if(!pendingAction)return;const reason=document.querySelector('#action-reason').value.trim();if(reason.length<5){showToast('Lý do phải có ít nhất 5 ký tự.');return;}const submit=event.submitter;submit.disabled=true;const original=submit.textContent;submit.textContent='Đang xử lý…';try{const csrf=(await api('/auth/csrf')).csrfToken;const isDelete=pendingAction.kind==='delete';const path=isDelete?`/admin/users/${encodeURIComponent(pendingAction.id)}`:pendingAction.kind==='user'?`/admin/users/${encodeURIComponent(pendingAction.id)}/status`:`/admin/organizations/${encodeURIComponent(pendingAction.type)}/${encodeURIComponent(pendingAction.id)}/verification`;const body=pendingAction.kind==='user'?{status:pendingAction.status,reason}:pendingAction.kind==='organization'?{decision:document.querySelector('[data-organization-decision]').value,reason}:{reason};await api(path,{method:isDelete?'DELETE':'PATCH',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(body)});actionDialog.close();document.querySelector('#action-reason').value='';showToast('Đã cập nhật và ghi audit log.');await loadSection(currentSection);}catch(error){showToast(error.message);}finally{submit.disabled=false;submit.textContent=original;}});
  const accountDialog=document.querySelector('[data-account-dialog]');const accountForm=document.querySelector('[data-account-form]');
  moduleContent?.addEventListener('click',(event)=>{const create=event.target.closest('[data-user-create]');const edit=event.target.closest('[data-user-edit]');if(!create&&!edit)return;accountForm.reset();const row=edit?currentRows.find((item)=>item.id===edit.dataset.id):null;accountForm.elements.id.value=row?.id||'';accountForm.elements.fullName.value=row?.fullName||'';accountForm.elements.email.value=row?.email||'';accountForm.elements.role.value=row?.role||'student';accountForm.elements.password.required=!row;document.querySelector('[data-password-field]').hidden=Boolean(row);document.querySelector('[data-account-title]').textContent=row?'Sửa tài khoản':'Thêm tài khoản';accountDialog.showModal();accountForm.elements.fullName.focus();});
  document.querySelectorAll('[data-account-close]').forEach((button)=>button.addEventListener('click',()=>accountDialog.close()));
  accountForm?.addEventListener('submit',async(event)=>{event.preventDefault();if(!accountForm.checkValidity()){accountForm.reportValidity();return;}const values=Object.fromEntries(new FormData(accountForm));const editing=values.id!=='';const csrf=(await api('/auth/csrf')).csrfToken;try{await api(editing?`/admin/users/${encodeURIComponent(values.id)}`:'/admin/users',{method:editing?'PATCH':'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify(values)});accountDialog.close();showToast(editing?'Đã cập nhật tài khoản.':'Đã tạo tài khoản.');await loadSection('users');}catch(error){showToast(error.message);}});
  document.querySelector('[data-dashboard-organizations]')?.addEventListener('click',(event)=>{if(event.target.closest('[data-dashboard-section="organizations"]'))loadSection('organizations');});
  const initial=location.hash.slice(1);if(labels[initial])loadSection(initial);else refreshDashboard();
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
