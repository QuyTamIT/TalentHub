(function(global){
 'use strict';
 const labels={draft:'Bản nháp',submitted:'Chờ giảng viên duyệt',changes_requested:'Cần chỉnh sửa',verified:'Giảng viên đã xác nhận',revoked:'Đã thu hồi xác nhận'};
 function actions(role,status){
  if(role==='teacher') return status==='submitted'?['verified','changes_requested']:status==='verified'?['revoked']:[];
  return ['draft','changes_requested'].includes(status)?['save','submit']:['verified','revoked'].includes(status)?['newRevision']:[];
 }
 function safeUrl(raw){try{const u=new URL(raw);return ['http:','https:'].includes(u.protocol)&&!u.username&&!u.password?u.href:null;}catch{return null;}}
 if(typeof module!=='undefined') module.exports={actions,safeUrl};
 if(!global.document) return;
 const doc=global.document;
 const el=(tag,text,cls)=>{const n=doc.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n;};

 doc.querySelectorAll('[data-portfolio]').forEach(root=>{
  const role=root.dataset.role||'student';
  const isCompletedFilter=root.dataset.filter==='completed';
  const notice=el('p','Đang tải báo cáo…');notice.setAttribute('role','status');
  const refresh=el('button','Tải lại');refresh.type='button';
  if(isCompletedFilter) {
   refresh.style.display='none'; // Ẩn nút tải lại thô trên thẻ hồ sơ
  }
  const list=el('div',undefined,isCompletedFilter?'portfolio-compact-grid':'portfolio-list');root.append(notice,refresh,list);
  let csrf='',catalog=[];

  async function request(body){
   let endpointUrl = root.dataset.endpoint;
   if (!body && isCompletedFilter) {
    const sep = endpointUrl.includes('?') ? '&' : '?';
    endpointUrl += `${sep}filter=completed`;
   }
   const response=await fetch(endpointUrl,{method:body?'POST':'GET',credentials:'same-origin',cache:'no-store',headers:body?{'Content-Type':'application/json','X-CSRF-Token':csrf}:{},...(body?{body:JSON.stringify(body)}:{})});
   let result;try{result=await response.json();}catch{throw new Error('Không đọc được phản hồi. Vui lòng thử lại.');}
   if(!response.ok||result.error)throw new Error(result.error?.message||'Không thể xử lý báo cáo.');
   return result.data;
  }

  async function load(){
   refresh.disabled=true;
   try{
    const data=await request();csrf=data.csrfToken;catalog=data.skills||[];
    let items=role==='teacher'?data.items:[...(data.projects||[]),...(data.internships||[])];
    if(root.dataset.context)items=items.filter(i=>i.kind==='project'&&i.contextId===root.dataset.context);
    if(isCompletedFilter){
     items=items.filter(i=>{
      const rep=i.report||{};
      if(i.kind==='internship') return rep.status==='verified' && rep.stage==='completed';
      return rep.status==='verified' || i.projectStatus==='completed';
     });
    }
    list.replaceChildren();
    if (items.length) {
     notice.textContent = isCompletedFilter
      ? 'Chỉ các vị trí thực tập đã được giảng viên hướng dẫn xác nhận hoàn thành mới hiển thị trong mục này.'
      : 'Chỉ báo cáo được giảng viên xác nhận mới là minh chứng trên CV.';
     items.forEach(render);
    } else {
     notice.textContent = isCompletedFilter
      ? 'Chưa có vị trí thực tập nào được ghi nhận hoàn thành.'
      : 'Chưa có báo cáo hoặc vị trí/dự án đủ điều kiện trong phạm vi của bạn.';
    }
   }catch(e){list.replaceChildren();notice.textContent=e.message;}finally{refresh.disabled=false;}
  }

  function field(form,name,label,value,type='text',max){
   const wrap=el('label',label);const input=el(type==='textarea'?'textarea':'input');input.name=name;
   if(type!=='textarea')input.type=type;input.value=value??'';if(max)input.maxLength=max;
   wrap.append(input);form.append(wrap);return input;
  }

  function render(item){
   const report=item.report||{},status=report.status||'draft';

   if (isCompletedFilter) {
    // Render Compact Card for completed showcase
    const card=el('article',undefined,'portfolio-compact-card');

    const header=el('div');
    const title=el('h3',item.title);
    const org=el('p',item.organization || 'Doanh nghiệp đối tác','portfolio-compact-card__org');
    header.append(title, org);

    const meta=el('div',undefined,'portfolio-compact-card__meta');
    if (report.startDate) {
     const dateText = `${report.startDate} → ${report.endDate || 'Đã kết thúc'}`;
     const dateRow = el('div', `📅 ${dateText}`, 'portfolio-compact-card__meta-item');
     meta.append(dateRow);
    }
    if (report.hours) {
     const hoursRow = el('div', `⚡ ${report.hours} giờ thực tế`, 'portfolio-compact-card__meta-item');
     meta.append(hoursRow);
    }
    const mentorRow = el('div', `👨‍🏫 Hướng dẫn: ${item.mentorName || 'Giảng viên hướng dẫn'}`, 'portfolio-compact-card__meta-item');
    meta.append(mentorRow);

    const badgesRow = el('div', undefined, 'learner-project-card__badges');
    badgesRow.style.cssText = 'display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; border-top: 1px solid #F1F5F9; padding-top: 0.75rem; margin-top: 0.25rem;';
    const roleBadge = el('span', item.kind === 'internship' ? 'Thực tập sinh' : 'Thành viên', 'learner-badge');
    roleBadge.style.cssText = 'background: #F1F5F9; color: #475569; font-weight: 600; font-size: 0.75rem; padding: 3px 9px; border-radius: 6px;';
    const statusBadge = el('span', '● Đã xác nhận hoàn thành', 'learner-badge');
    statusBadge.style.cssText = 'font-weight: 600; font-size: 0.75rem; padding: 3px 9px; border-radius: 6px; background: #DCFCE7; color: #15803D; border: 1px solid #86EFAC;';
    badgesRow.append(roleBadge, statusBadge);

    const actionsRow = el('div', undefined, 'portfolio-compact-actions');
    const detailBtn = el('button', 'Xem chi tiết', 'learner-btn learner-btn--outline');
    detailBtn.type = 'button';
    detailBtn.setAttribute('data-open-internship-detail', '');
    detailBtn.dataset.itemJson = JSON.stringify(item);
    detailBtn.style.cssText = 'flex: 1; font-size: 0.82rem; padding: 7px 12px; justify-content: center; border-color: #EA580C; color: #EA580C; font-weight: 600; cursor: pointer;';

    detailBtn.addEventListener('click', () => {
     openInternshipModal(item);
    });

    const partnerLink = el('a', '🏢 Doanh nghiệp', 'learner-btn learner-btn--outline');
    partnerLink.href = `partner.php?type=enterprise&id=${encodeURIComponent(item.contextId)}`;
    partnerLink.title = 'Xem thông tin doanh nghiệp trong hệ sinh thái';
    partnerLink.target = '_blank';
    partnerLink.rel = 'noopener noreferrer';
    partnerLink.style.cssText = 'font-size: 0.82rem; padding: 7px 10px; display: inline-flex; align-items: center; justify-content: center; color: #475569; text-decoration: none;';

    actionsRow.append(detailBtn, partnerLink);
    card.append(header, meta, badgesRow, actionsRow);
    list.append(card);
    return;
   }

   // Full form mode (e.g. inside project.php or teacher view)
   const card=el('article',undefined,'portfolio-card');
   card.append(el('h3',item.title),el('p',[item.kind==='project'?'Dự án':'Thực tập',item.organization,item.studentName].filter(Boolean).join(' · ')),el('p',`Hướng dẫn: ${item.mentorName||'Chưa được phân công'}`),el('p',`${item.report?labels[status]||status:'Chưa nộp báo cáo'}${report.revision?' · Phiên bản '+report.revision:''}`));
   if(report.notes)card.append(el('p',report.notes,'portfolio-notes'));
   if(item.kind==='internship'&&report.startDate)card.append(el('p',`${report.stage==='completed'?'Báo cáo hoàn thành':'Báo cáo đang thực tập'} · ${report.startDate} → ${report.endDate||'đang tiếp diễn'} · ${report.hours||0} giờ (nội dung báo cáo)`));
   for(const [key,label] of [['repositoryUrl','Minh chứng / mã nguồn'],['demoUrl','Bản demo']]){
    const url=safeUrl(report[key]);if(url){const a=el('a',label);a.href=url;a.target='_blank';a.rel='noopener noreferrer';card.append(a,el('br'));}
   }
   if(report.feedback)card.append(el('p',`Phản hồi giảng viên: ${report.feedback}`));
   if(report.reviewedAt)card.append(el('p',`Lần duyệt: ${report.reviewedAt}`));
   if(status==='verified'&&report.skills?.length)card.append(el('p',`Kỹ năng có xác nhận: ${report.skills.map(s=>s.name).join(', ')}`));
   const history=el('details');history.append(el('summary','Lịch sử xử lý'));
   (report.history||[]).forEach(h=>history.append(el('p',`v${h.version} · ${labels[h.status]||h.status} · ${h.createdAt}${h.feedback?' · '+h.feedback:''}`)));
   card.append(history);
   const permitted=actions(role,status);
   if(permitted.length){
    const form=el('form');let newRevision=false;
    if(role==='student'){
     field(form,'notes','Nội dung / đóng góp cá nhân',report.notes,'textarea',4000);
     field(form,'repositoryUrl','Link minh chứng / kho mã nguồn (http/https)',report.repositoryUrl,'url',1000);
     field(form,'demoUrl','Link demo (không bắt buộc)',report.demoUrl,'url',1000);
     if(item.kind==='internship'){
      const wrap=el('label','Loại báo cáo'),select=el('select');select.name='stage';
      for(const [v,l] of [['active','Đang thực tập'],['completed','Đề nghị xác nhận hoàn thành']]){const option=el('option',l);option.value=v;select.append(option);}
      select.value=report.stage||'active';wrap.append(select);form.append(wrap);
      field(form,'startDate','Ngày bắt đầu',report.startDate,'date');field(form,'endDate','Ngày kết thúc (khi hoàn thành)',report.endDate,'date');
      const hours=field(form,'hours','Số giờ thực tế',report.hours??0,'number');hours.min='0';hours.max='10000';hours.step='0.25';
     }
     if(permitted.includes('newRevision')){newRevision=true;form.prepend(el('p','Tạo bản mới sẽ chuyển về nháp; xác nhận cũ được giữ trong lịch sử, không còn dùng cho CV.'));}
    }else{
     field(form,'feedback','Nhận xét (bắt buộc khi yêu cầu sửa hoặc thu hồi)',report.feedback,'textarea',2000);
     if(status==='submitted'){
      const wrap=el('label','Kỹ năng thực sự được xác nhận (tối đa 10, có thể không chọn)');const select=el('select');select.name='skillIds';select.multiple=true;
      catalog.forEach(s=>{const option=el('option',s.name);option.value=s.id;select.append(option);});wrap.append(select);form.append(wrap);
     }
    }
    const buttons=el('div',undefined,'portfolio-actions');
    const buttonLabels={save:'Lưu nháp',submit:'Gửi giảng viên',newRevision:'Tạo bản nháp mới',verified:'Xác nhận',changes_requested:'Yêu cầu sửa',revoked:'Thu hồi xác nhận'};
    permitted.forEach(action=>{const b=el('button',buttonLabels[action]);b.type='submit';b.value=action;buttons.append(b);});form.append(buttons);
    const error=el('p');error.setAttribute('role','alert');form.append(error);
    form.addEventListener('submit',async e=>{
     e.preventDefault();const action=e.submitter?.value;if(!permitted.includes(action))return;
     const values=Object.fromEntries(new FormData(form));
     const body={kind:item.kind,expectedVersion:Number(report.version||0)};
     if(role==='student')Object.assign(body,values,{contextId:item.contextId,submit:action==='submit',newRevision});
     else Object.assign(body,{reportId:report.id,decision:action,feedback:values.feedback||'',skillIds:Array.from(form.querySelector('[name=skillIds]')?.selectedOptions||[],o=>o.value)});
     if(['revoked','newRevision'].includes(action)&&!global.confirm('Thao tác này sẽ bỏ xác nhận hiện tại khỏi CV và giữ bản cũ trong lịch sử. Tiếp tục?'))return;
     for(const b of buttons.children)b.disabled=true;
     try{await request(body);await load();}catch(err){error.textContent=err.message;}finally{for(const b of buttons.children)b.disabled=false;}
    });card.append(form);
   }
   list.append(card);
  }

  function openInternshipModal(item) {
   const modal = doc.getElementById('learner-internship-detail-modal');
   if (!modal) return;
   const rep = item.report || {};
   const setTxt = (id, val) => {
    const node = modal.querySelector(id);
    if (node) node.textContent = val || 'Chưa cập nhật';
   };

   setTxt('[data-intern-title]', item.title);
   setTxt('[data-intern-org]', item.organization);
   setTxt('[data-intern-mentor]', item.mentorName || 'Giảng viên hướng dẫn');
   setTxt('[data-intern-dates]', `${rep.startDate || 'Chưa rõ'} → ${rep.endDate || 'Đã hoàn thành'}`);
   setTxt('[data-intern-hours]', rep.hours ? `${rep.hours} giờ` : 'Chưa ghi nhận');
   setTxt('[data-intern-notes]', rep.notes || 'Không có ghi chú thêm.');
   setTxt('[data-intern-feedback]', rep.feedback || 'Chưa có nhận xét.');
   setTxt('[data-intern-reviewed-at]', rep.reviewedAt || 'Đã xác nhận');

   const repoLink = modal.querySelector('[data-intern-repo]');
   if (repoLink) {
    const url = safeUrl(rep.repositoryUrl);
    if (url) {
     repoLink.href = url;
     repoLink.hidden = false;
     repoLink.textContent = 'Mở liên kết minh chứng / kho mã nguồn';
    } else {
     repoLink.hidden = true;
    }
   }

   const demoLink = modal.querySelector('[data-intern-demo]');
   if (demoLink) {
    const url = safeUrl(rep.demoUrl);
    if (url) {
     demoLink.href = url;
     demoLink.hidden = false;
     demoLink.textContent = 'Mở bản demo sản phẩm';
    } else {
     demoLink.hidden = true;
    }
   }

   const skillsList = modal.querySelector('[data-intern-skills]');
   if (skillsList) {
    skillsList.replaceChildren();
    if (rep.skills && rep.skills.length) {
     rep.skills.forEach(s => {
      const li = el('li', s.name, 'learner-badge learner-badge--primary');
      li.style.cssText = 'display: inline-block; margin: 3px 6px 3px 0; background: #EFF6FF; color: #1D4ED8; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.8rem;';
      skillsList.append(li);
     });
    } else {
     skillsList.append(el('li', 'Không có kỹ năng ghi nhận bổ sung.'));
    }
   }

   const ecoBtn = modal.querySelector('[data-intern-eco-link]');
   if (ecoBtn) {
    ecoBtn.href = `partner.php?type=enterprise&id=${encodeURIComponent(item.contextId)}`;
   }

   if (global.LearnerUI && typeof global.LearnerUI.openModal === 'function') {
    global.LearnerUI.openModal(modal);
   } else {
    modal.hidden = false;
   }
  }

  refresh.addEventListener('click',load);load();
 });
})(typeof window!=='undefined'?window:globalThis);
