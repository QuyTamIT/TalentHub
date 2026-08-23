/** Learner activity domain, persistence and interactions. */
(function(global){'use strict';
const KEY='talenthub.learner.activities.v1';
const statusLabels={
  approved:'Đã đăng ký',
  pending:'Chờ duyệt',
  rejected:'Bị từ chối',
  cancelled:'Đã hủy',
  attended:'Đã tham gia',
  registered:'Đã đăng ký',
  checked_in:'Đã check-in',
  completed:'Hoàn thành',
  waitlisted:'Danh sách chờ'
};
function getStatusLabel(status){
  return (status && statusLabels[status]) || status || 'Không xác định';
}
function canRegisterActivity(activity,now){
  if(!activity||activity.can_register===false||!['published','ongoing','active'].includes(activity.status))return false;
  const current=new Date(now||Date.now()).getTime();
  const closes=new Date(activity.registration_closes_at||activity.start_at||'').getTime();
  if(!Number.isFinite(current)||!Number.isFinite(closes))return false;
  const opensValue=activity.registration_opens_at;
  if(!opensValue)return current<=closes;
  const opens=new Date(opensValue).getTime();
  return Number.isFinite(opens)&&current>=opens&&current<=closes;
}
function activityCtaState(activity,registration,now){
  if(registration){
    const label=getStatusLabel(registration.status);
    return{label,disabled:true,tone:'outline',explanation:`Trạng thái đăng ký: ${label}.`};
  }
  if(canRegisterActivity(activity,now)){
    return{label:'Đăng ký hoạt động',disabled:false,tone:'primary',explanation:'Đăng ký sẽ được ghi trực tiếp vào hệ thống.'};
  }
  const current=new Date(now||Date.now()).getTime();
  const opens=new Date(activity?.registration_opens_at||'').getTime();
  const closes=new Date(activity?.registration_closes_at||activity?.start_at||'').getTime();
  const explanation=Number.isFinite(opens)&&Number.isFinite(current)&&current<opens
    ?'Chưa đến thời gian mở đăng ký.'
    :Number.isFinite(closes)&&Number.isFinite(current)&&current>closes
      ?'Đã hết hạn đăng ký.'
      :'Hoạt động hiện không nhận đăng ký.';
  return{label:'Đã đóng đăng ký',disabled:true,tone:'outline',explanation};
}
function resolveRegistrationStatus(a){
  if(Number(a.participants)>=Number(a.capacity))return'waitlisted';
  return a.approval_mode==='teacher_review'?'pending':'approved';
}
function hasScheduleConflict(activity,registrations,catalog){
  const active=new Set(['approved','registered','pending','waitlisted','attended','checked_in']);
  return registrations.some(r=>{
    if(!active.has(r.status)||r.activity_id===activity.id)return false;
    const other=catalog.find(a=>a.id===r.activity_id);
    return other&&new Date(activity.start_at)<new Date(other.end_at)&&new Date(activity.end_at)>new Date(other.start_at);
  });
}
function createActivityStorage(storage=null,key=KEY){
  const memoryFallback={items:new Map()};
  const isStorageAvailable=Boolean(storage && typeof storage.getItem==='function');
  const read=()=>{
    try{
      if(!isStorageAvailable){
        const v=memoryFallback.items.get(key);
        return v?JSON.parse(v):{schema_version:1,registrations:[]};
      }
      const p=JSON.parse(storage.getItem(key)||'null');
      return p?.schema_version===1&&Array.isArray(p.registrations)?p:{schema_version:1,registrations:[]};
    }catch(e){
      return{schema_version:1,registrations:[]};
    }
  };
  const write=s=>{
    try{
      const str=JSON.stringify(s);
      if(isStorageAvailable) storage.setItem(key,str);
      else memoryFallback.items.set(key,str);
    }catch(e){}
  };
  return{
    getRegistrations:()=>read().registrations,
    getRegistration:id=>read().registrations.find(r=>r.id===id)||null,
    saveRegistration:r=>{
      const s=read(),i=s.registrations.findIndex(x=>x.id===r.id);
      if(i>=0)s.registrations[i]=r;else s.registrations.push(r);
      write(s);return r;
    },
    getByActivity:id=>read().registrations.find(r=>r.activity_id===id)||null
  };
}
function createRegistration({studentId,activity,now,id}){
  if(!canRegisterActivity(activity,now))return null;
  const at=new Date(now||Date.now()).toISOString();
  return{
    id:id||`registration-${activity.id}-${Date.now()}`,
    student_id:studentId,
    activity_id:activity.id,
    status:resolveRegistrationStatus(activity),
    created_at:at,
    updated_at:at,
    cancelled_at:null,
    cancellation_reason:null,
    checkin_id:null,
    experience_hours:null,
    feedback:null,
    source:'learner_local'
  };
}
function cancelRegistration(r,reason,now){
  if(!['approved','registered','pending','waitlisted'].includes(r.status))return null;
  const at=new Date(now||Date.now()).toISOString();
  return{...r,status:'cancelled',updated_at:at,cancelled_at:at,cancellation_reason:String(reason||'').trim()};
}
function saveFeedback(r,rating,comment,now){
  if(r.status!=='attended'&&r.status!=='completed')return null;
  return{...r,updated_at:new Date(now||Date.now()).toISOString(),feedback:{rating:Number(rating),comment:String(comment||'').trim()}};
}
function mergeRegistrations(mock,local){
  const byActivity=new Map((mock||[]).map(r=>[r.activity_id,r]));
  (local||[]).forEach(r=>byActivity.set(r.activity_id,r));
  return Array.from(byActivity.values());
}
function canUseLocalActivityMutations(source){
  return source==='mock';
}
function createActivityRegistrationGateway(api){
  if(!api||typeof api.send!=='function')return null;
  return{
    register:activityId=>api.send('POST','/activity-registrations.php',{action:'register',activityId}),
    cancel:(registrationId,reason)=>api.send('POST','/activity-registrations.php',{action:'cancel',registrationId,reason})
  };
}
function normalizeServerRegistration(registration){
  if(!registration||typeof registration!=='object')return null;
  return{
    ...registration,
    activity_id:registration.activity_id||registration.activityId,
    student_id:registration.student_id||registration.studentId,
    created_at:registration.created_at||registration.registeredAt,
    updated_at:registration.updated_at||registration.updatedAt,
    cancelled_at:registration.cancelled_at??registration.cancelledAt??null,
    cancellation_reason:registration.cancellation_reason??registration.cancellationReason??null
  };
}
function resolveRegistrationCollection(serverRegistrations,localRegistrations,source){
  const server=Array.isArray(serverRegistrations)?serverRegistrations:[];
  if(!canUseLocalActivityMutations(source))return server;
  return mergeRegistrations(server,Array.isArray(localRegistrations)?localRegistrations:[]);
}
global.LearnerActivities={
  canRegisterActivity,
  activityCtaState,
  resolveRegistrationStatus,
  hasScheduleConflict,
  createActivityStorage,
  createRegistration,
  cancelRegistration,
  saveFeedback,
  mergeRegistrations,
  resolveRegistrationCollection,
  canUseLocalActivityMutations,
  createActivityRegistrationGateway,
  normalizeServerRegistration,
  getStatusLabel,
  statusLabels
};
if(typeof document==='undefined')return;
document.addEventListener('DOMContentLoaded',()=>{
  const bootEl=document.getElementById('learner-activities-boot');
  if(!bootEl)return;
  let boot;try{boot=JSON.parse(bootEl.textContent)}catch(e){return}
  const source=boot.source==='mock'?'mock':'database';
  const localMutationsEnabled=canUseLocalActivityMutations(source);
  const store=localMutationsEnabled?createActivityStorage(global.localStorage):null;
  const api=source==='database'&&global.TalentHubLearnerApi
    ?global.TalentHubLearnerApi.createLearnerApiClient({baseUrl:'/app/learner/api/v1',csrfToken:boot.csrf_token||''})
    :null;
  const gateway=createActivityRegistrationGateway(api);
  const serverMutationsEnabled=source==='database'&&gateway!==null;
  const upsertServerRegistration=registration=>{
    const normalized=normalizeServerRegistration(registration);
    if(!normalized)return null;
    const index=(boot.registrations||[]).findIndex(item=>item.id===normalized.id);
    if(index>=0)boot.registrations[index]=normalized;else boot.registrations.push(normalized);
    return normalized;
  };
  const all=()=>resolveRegistrationCollection(boot.registrations||[],store?store.getRegistrations():[],source);

  const detail=document.querySelector('[data-activity-detail-page]');
  if(detail){
    const activity=boot.activity;
    let registration=all().find(r=>r.activity_id===activity.id);
    const button=detail.querySelector('[data-register-current]');
    const message=detail.querySelector('[data-registration-message]');
    const setButtonTone=tone=>{
      if(!button)return;
      button.classList.remove('learner-btn--primary','learner-btn--secondary','learner-btn--outline');
      button.classList.add(`learner-btn--${['primary','secondary','outline'].includes(tone)?tone:'outline'}`);
    };
    const render=()=>{
      registration=all().find(r=>r.activity_id===activity.id);
      if(!button)return;
      const cta=!localMutationsEnabled&&!serverMutationsEnabled&&!registration&&canRegisterActivity(activity)
        ?{label:'Đăng ký trực tuyến chưa khả dụng',disabled:true,tone:'outline',explanation:'Kết nối đăng ký trực tuyến hiện chưa khả dụng.'}
        :activityCtaState(activity,registration);
      button.textContent=cta.label;
      button.disabled=cta.disabled;
      setButtonTone(cta.tone);
      if(message){message.textContent=cta.explanation;message.dataset.tone=cta.tone;}
    };
    render();
    button?.addEventListener('click',async()=>{
      if(serverMutationsEnabled){
        button.disabled=true;
        if(message)message.textContent='Đang ghi nhận đăng ký...';
        try{
          const result=await gateway.register(activity.id);
          const created=upsertServerRegistration(result.registration);
          if(message)message.textContent=`Đã ghi nhận: ${getStatusLabel(created?.status)}.`;
        }catch(error){
          if(message)message.textContent=error?.message||'Không thể đăng ký hoạt động.';
        }
        render();return;
      }
      if(!localMutationsEnabled){
        if(message)message.textContent='Đăng ký trực tuyến chưa khả dụng.';render();return;
      }
      if(!canRegisterActivity(activity)){
        if(message)message.textContent='Hoạt động hiện không nhận đăng ký.';
        render();return;
      }
      const active=all().filter(r=>r.status!=='cancelled');
      if(hasScheduleConflict(activity,active,boot.catalog)){
        if(message)message.textContent='Lịch hoạt động bị trùng với một đăng ký hiện có.';
        return;
      }
      const existing=store?.getByActivity(activity.id);
      const created=createRegistration({studentId:boot.student_id,activity,id:existing?.id});
      if(!created){
        if(message)message.textContent='Hoạt động hiện không nhận đăng ký.';
        render();return;
      }
      store?.saveRegistration(created);
      if(message)message.textContent=`Đã ghi nhận: ${getStatusLabel(created.status)}.`;
      render();
    });
  }

  const mine=document.querySelector('[data-my-activities-page]');
  if(mine){
    const render=()=>{
      const registrations=all(),container=mine.querySelector('[data-my-registration-list]');
      if(!container)return;
      const cards=registrations.map(r=>{
        const a=boot.catalog.find(x=>x.id===r.activity_id);
        if(!a)return null;
        const safeStatus=Object.prototype.hasOwnProperty.call(statusLabels,r.status)?r.status:'unknown';
        const isEligibleCancel=['approved','registered','pending','waitlisted'].includes(r.status);
        const isEligibleFeedback=(r.status==='attended'||r.status==='completed')&&!r.feedback;
        const isEligibleCheckin=(r.status==='approved'||r.status==='registered');
        const card=document.createElement('article');
        card.className='learner-card learner-my-activity';
        card.dataset.status=safeStatus;
        const status=document.createElement('span');
        status.className=`learner-registration-status learner-registration-status--${safeStatus}`;
        status.textContent=getStatusLabel(r.status);
        const content=document.createElement('div');
        const title=document.createElement('h2');title.textContent=String(a.title||'Hoạt động');
        const meta=document.createElement('p');
        meta.textContent=`${new Date(a.start_at).toLocaleString('vi-VN')} · ${String(a.location||'Chưa cập nhật')}`;
        content.append(title,meta);
        const detailLink=document.createElement('a');
        detailLink.className='learner-btn learner-btn--outline';
        detailLink.href=`activity-detail.php?id=${encodeURIComponent(String(a.id||''))}`;
        detailLink.textContent='Chi tiết';
        card.append(status,content,detailLink);
        if(isEligibleCheckin){
          const checkinLink=document.createElement('a');
          checkinLink.className='learner-btn learner-btn--primary';
          checkinLink.href=`checkin.php?activity=${encodeURIComponent(String(a.id||''))}`;
          checkinLink.textContent='Đi tới check-in';
          card.appendChild(checkinLink);
        }
        if((localMutationsEnabled||serverMutationsEnabled)&&isEligibleCancel){
          const cancelButton=document.createElement('button');
          cancelButton.type='button';cancelButton.dataset.cancelRegistration=String(r.id||'');cancelButton.textContent='Hủy đăng ký';
          card.appendChild(cancelButton);
        }
        if(localMutationsEnabled&&isEligibleFeedback){
          const feedbackButton=document.createElement('button');
          feedbackButton.type='button';feedbackButton.dataset.feedbackRegistration=String(r.id||'');feedbackButton.textContent='Gửi phản hồi 5★';
          card.appendChild(feedbackButton);
        }
        return card;
      }).filter(Boolean);
      container.replaceChildren(...cards);

      container.querySelectorAll('[data-cancel-registration]').forEach(b=>b.addEventListener('click',async()=>{
        const current=registrations.find(r=>r.id===b.dataset.cancelRegistration);
        if(serverMutationsEnabled){
          b.disabled=true;
          try{
            const result=await gateway.cancel(current.id,'Sinh viên chủ động hủy');
            upsertServerRegistration(result.registration);
          }catch(error){
            const status=mine.querySelector('[data-registration-command-status]');
            if(status)status.textContent=error?.message||'Không thể hủy đăng ký.';
          }
          render();return;
        }
        if(!localMutationsEnabled)return;
        const next=cancelRegistration(current,'Sinh viên chủ động hủy');
        if(next)store?.saveRegistration(next);
        render();
      }));
      container.querySelectorAll('[data-feedback-registration]').forEach(b=>b.addEventListener('click',()=>{
        if(!localMutationsEnabled)return;
        const current=registrations.find(r=>r.id===b.dataset.feedbackRegistration);
        const next=saveFeedback(current,5,'Hoạt động hữu ích.');
        if(next)store?.saveRegistration(next);
        render();
      }));
    };
    render();
    mine.querySelectorAll('[data-registration-filter]').forEach(b=>b.addEventListener('click',()=>{
      mine.querySelectorAll('[data-registration-filter]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));
      const filter=b.dataset.registrationFilter;
      mine.querySelectorAll('[data-status]').forEach(card=>{
        const cardStatus=card.dataset.status;
        if(filter==='all'){
          card.hidden=false;
        }else if(filter==='approved'){
          card.hidden=!(cardStatus==='approved'||cardStatus==='registered');
        }else if(filter==='attended'){
          card.hidden=!(cardStatus==='attended'||cardStatus==='completed'||cardStatus==='checked_in');
        }else{
          card.hidden=cardStatus!==filter;
        }
      });
    }));
  }
});
})(typeof window!=='undefined'?window:globalThis);
