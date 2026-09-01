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
  ,no_show:'Không tham gia'
};
function getStatusLabel(status){
  return (status && statusLabels[status]) || status || 'Không xác định';
}
function resolveRegistrationMessage(explanation,commandFeedback=''){
  return String(commandFeedback||'').trim()||String(explanation||'');
}
function activityAvailabilityState(activity,now){
  const status=String(activity?.status||'').toLowerCase();
  if(['ongoing','active'].includes(status))return{code:'ongoing',label:'Đang diễn ra',explanation:'Hoạt động đang diễn ra và không nhận đăng ký mới.'};
  if(status==='completed')return{code:'completed',label:'Đã kết thúc',explanation:'Hoạt động đã kết thúc.'};
  if(status!=='published')return{code:'unavailable',label:'Không nhận đăng ký',explanation:'Hoạt động hiện không nhận đăng ký.'};
  const current=new Date(now??Date.now()).getTime();
  const starts=new Date(activity?.start_at||'').getTime();
  const ends=new Date(activity?.end_at||activity?.start_at||'').getTime();
  const opensValue=activity?.registration_opens_at;
  const opens=opensValue?new Date(opensValue).getTime():Number.NaN;
  const closes=new Date(activity?.registration_closes_at||activity?.start_at||'').getTime();
  if(!Number.isFinite(current)||!Number.isFinite(closes))return{code:'unavailable',label:'Không nhận đăng ký',explanation:'Không thể xác định thời gian đăng ký.'};
  if(Number.isFinite(ends)&&current>=ends)return{code:'completed',label:'Đã kết thúc',explanation:'Hoạt động đã kết thúc.'};
  if(Number.isFinite(opens)&&current<opens)return{code:'not_open',label:'Chưa mở đăng ký',explanation:'Hoạt động chưa đến thời gian mở đăng ký.'};
  if(current>=closes)return{code:'expired',label:'Đã hết hạn đăng ký',explanation:'Hoạt động đã hết hạn đăng ký.'};
  const capacity=Number(activity?.capacity);
  const participants=Number(activity?.participants);
  const remaining=Number(activity?.remaining);
  if((Number.isFinite(capacity)&&capacity<=0)
    ||(Number.isFinite(capacity)&&Number.isFinite(participants)&&participants>=capacity)
    ||(activity?.remaining!==undefined&&Number.isFinite(remaining)&&remaining<=0)){
    return{code:'full',label:'Đã hết chỗ',explanation:'Hoạt động đã đủ số lượng đăng ký.'};
  }
  return{code:'open',label:'Đang mở đăng ký',explanation:'Hoạt động đang nhận đăng ký.'};
}
function canRegisterActivity(activity,now){
  return Boolean(activity)&&activity.can_register!==false&&activityAvailabilityState(activity,now).code==='open';
}
function normalizeActivitySearch(value){
  return String(value||'')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g,'')
    .replace(/đ/g,'d')
    .replace(/Đ/g,'D')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g,' ')
    .trim();
}
function activityMatchesDiscoveryFilters(activity,filters={},now){
  if(!activity||typeof activity!=='object')return false;
  const query=normalizeActivitySearch(filters.query);
  if(query&&!normalizeActivitySearch(activity.search).includes(query))return false;
  const category=String(filters.category||'Tất cả');
  if(category!=='Tất cả'&&String(activity.category||'')!==category)return false;
  if(filters.onlyAvailable&&activity.available===false)return false;
  const time=String(filters.time||'all');
  if(time==='all')return true;
  const days=time==='7d'?7:time==='30d'?30:null;
  if(days===null)return true;
  const current=new Date(now||Date.now()).getTime();
  const starts=new Date(activity.startAt||'').getTime();
  if(!Number.isFinite(current)||!Number.isFinite(starts))return false;
  return starts>=current&&starts<=current+(days*24*60*60*1000);
}
function registrationPageForStatus(status){
  if(['pending','approved','waitlisted'].includes(status))return'registered';
  if(['attended','no_show'].includes(status))return'history';
  return null;
}
function activityCtaState(activity,registration,now){
  if(registration){
    const supported=new Set(['approved','pending','waitlisted','rejected','cancelled','attended','registered','checked_in']);
    const label=supported.has(registration.status)?getStatusLabel(registration.status):'Không thể đăng ký';
    const explanation=registration.status==='pending'
      ?'Đăng ký đang chờ giáo viên xét duyệt.'
      :registration.status==='approved'||registration.status==='registered'
        ?'Đăng ký đã được ghi nhận.'
        :registration.status==='attended'||registration.status==='checked_in'
          ?'Bạn đã tham gia hoạt động này.'
          :`Trạng thái đăng ký: ${label}.`;
    return{label,disabled:true,tone:'outline',explanation};
  }
  if(canRegisterActivity(activity,now)){
    return{label:'Đăng ký hoạt động',disabled:false,tone:'primary',explanation:'Đăng ký sẽ được ghi trực tiếp vào hệ thống.'};
  }
  const availability=activityAvailabilityState(activity,now);
  const labels={
    not_open:'Chưa mở đăng ký',
    full:'Đã hết chỗ',
    expired:'Đã hết hạn',
    ongoing:'Đang diễn ra',
    completed:'Đã kết thúc',
    unavailable:'Không nhận đăng ký'
  };
  return{label:labels[availability.code]||'Không nhận đăng ký',disabled:true,tone:'outline',explanation:availability.explanation};
}
function activeRegistrations(registrations){
  return (Array.isArray(registrations)?registrations:[]).filter(row=>['pending','approved','waitlisted'].includes(row?.status));
}
function registeredSummary(registrations){
  const active=activeRegistrations(registrations);
  const approved=active.filter(row=>row.status==='approved').length;
  const pending=active.filter(row=>row.status==='pending').length;
  return{total:active.length,approved,pending};
}
function registrationMatchesRegisteredFilters(registration,activity,filters={}){
  if(!['pending','approved','waitlisted'].includes(registration?.status))return false;
  const requested=filters.status||'all';
  if(requested==='approved'&&registration.status!=='approved')return false;
  if(requested==='pending'&&registration.status!=='pending')return false;
  const haystack=normalizeActivitySearch(`${activity?.title||''} ${activity?.organizer_name||''} ${activity?.school_name||''}`);
  return haystack.includes(normalizeActivitySearch(filters.query||''));
}
function canCancelRegistration(registration,activity,now=Date.now()){
  if(!['pending','approved','waitlisted'].includes(registration?.status))return false;
  const current=new Date(now).getTime();
  const closes=new Date(activity?.cancellation_closes_at||'').getTime();
  return Number.isFinite(current)&&Number.isFinite(closes)&&current<closes;
}
function canCheckinRegistration(registration){
  return registration?.status==='approved';
}
function historyRecordTimestamp(record){
  for(const field of ['attendance_resolved_at','checked_in_at','end_at','updated_at']){
    const value=new Date(record?.[field]||'').getTime();
    if(Number.isFinite(value))return value;
  }
  return 0;
}
function attendanceHistory(registrations){
  return (Array.isArray(registrations)?registrations:[])
    .filter(row=>['attended','no_show'].includes(row?.status))
    .map(row=>row.status==='no_show'?{...row,checked_in_at:null,experience_hours:0}:{...row})
    .sort((left,right)=>historyRecordTimestamp(right)-historyRecordTimestamp(left));
}
function historySummary(registrations,now=Date.now()){
  const history=attendanceHistory(registrations),current=new Date(now);
  const attended=history.filter(row=>row.status==='attended').length;
  const noShow=history.filter(row=>row.status==='no_show').length;
  const hours=history.reduce((sum,row)=>sum+(row.status==='attended'&&Number.isFinite(Number(row.experience_hours))?Number(row.experience_hours):0),0);
  const month=history.filter(row=>{const date=new Date(historyRecordTimestamp(row));return Number.isFinite(current.getTime())&&date.getUTCFullYear()===current.getUTCFullYear()&&date.getUTCMonth()===current.getUTCMonth();}).length;
  return{attended,noShow,hours,month};
}
function attendanceRate(registrations){
  const history=attendanceHistory(registrations);
  return history.length===0?0:Math.round((history.filter(row=>row.status==='attended').length/history.length)*100);
}
function groupHistoryByMonth(registrations){
  return attendanceHistory(registrations).reduce((groups,row)=>{const timestamp=historyRecordTimestamp(row);const key=timestamp?new Date(timestamp).toISOString().slice(0,7):'unknown';(groups[key]??=[]).push(row);return groups;},{});
}
function historyMatchesFilters(record,filters={},now=Date.now()){
  if(!['attended','no_show'].includes(record?.status))return false;
  if(filters.status&&filters.status!=='all'&&record.status!==filters.status)return false;
  const period=String(filters.period||'all');if(period==='all')return true;
  const days=period==='30d'?30:period==='90d'?90:period==='365d'?365:null;if(days===null)return true;
  const current=new Date(now).getTime(),timestamp=historyRecordTimestamp(record);
  return Number.isFinite(current)&&timestamp>0&&timestamp>=current-(days*86400000)&&timestamp<=current;
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
function createSingleFlightRegistration(gateway){
  let inFlight=null;
  return activityId=>{
    if(inFlight)return inFlight;
    if(!gateway||typeof gateway.register!=='function')return Promise.reject(new Error('Registration gateway is unavailable.'));
    try{
      inFlight=Promise.resolve(gateway.register(activityId)).finally(()=>{inFlight=null;});
    }catch(error){
      return Promise.reject(error);
    }
    return inFlight;
  };
}
function registrationErrorMessage(error){
  const code=String(error?.code||'');
  const messages={
    ACTIVITY_SCHOOL_SCOPE_DENIED:'Bạn không thể đăng ký hoạt động ngoài trường của mình.',
    REGISTRATION_EXISTS:'Bạn đã có đăng ký cho hoạt động này.',
    SCHEDULE_CONFLICT:'Lịch hoạt động bị trùng với một đăng ký hiện có.',
    REGISTRATION_CLOSED:'Hoạt động đã đóng đăng ký hoặc không còn ở trạng thái phù hợp.',
    INVALID_REGISTRATION_STATE:'Hoạt động đã đóng đăng ký hoặc không còn ở trạng thái phù hợp.',
    ACTIVITY_SCHOOL_SCOPE_UNAVAILABLE:'Chưa thể xác minh phạm vi trường. Vui lòng thử lại sau.',
    SERVICE_UNAVAILABLE:'Dịch vụ đăng ký tạm thời không khả dụng. Vui lòng thử lại sau.',
    NETWORK_ERROR:'Không thể kết nối đến máy chủ. Vui lòng thử lại.',
    REQUEST_TIMEOUT:'Máy chủ phản hồi quá lâu. Vui lòng thử lại.'
  };
  return messages[code]||'Không thể đăng ký hoạt động. Vui lòng thử lại.';
}
function registrationCapacityDelta(status){
  return ['approved','attended'].includes(String(status||''))?1:0;
}
function registrationSuccessMessage(status){
  const messages={
    approved:'Đăng ký thành công! Bạn đã được ghi nhận.',
    attended:'Đăng ký thành công! Bạn đã được ghi nhận.',
    pending:'Đăng ký thành công! Đang chờ giáo viên phê duyệt.',
    waitlisted:'Đăng ký thành công! Bạn đang ở danh sách chờ.'
  };
  return messages[String(status||'')]||'Đăng ký thành công! Hệ thống đã ghi nhận yêu cầu của bạn.';
}
function registrationBlockingState(error){
  if(String(error?.code||'')!=='SCHEDULE_CONFLICT')return null;
  return{
    label:'Không thể đăng ký: trùng lịch',
    disabled:true,
    tone:'outline',
    explanation:'Bạn chưa thể đăng ký vì thời gian hoạt động trùng với một hoạt động đã đăng ký. Hãy kiểm tra mục Đã đăng ký.'
  };
}
function normalizeRegistrationCapacity(snapshot){
  const capacity=Number(snapshot?.capacity);
  const participants=Number(snapshot?.participants);
  if(!Number.isFinite(capacity)||capacity<=0||!Number.isFinite(participants)||participants<0)return null;
  const confirmed=Math.floor(participants);
  const total=Math.floor(capacity);
  return{
    participants:confirmed,
    capacity:total,
    remaining:Math.max(0,total-confirmed),
    percent:Math.min(100,Math.round((confirmed/total)*100))
  };
}
function createActivityCatalogFreshness(storage=null,key='talenthub:activity-catalog-stale'){
  const available=Boolean(storage&&typeof storage.getItem==='function'&&typeof storage.setItem==='function');
  const current=()=>{
    if(!available)return 0;
    try{
      const revision=Number.parseInt(storage.getItem(key)||'0',10);
      return Number.isInteger(revision)&&revision>0?revision:0;
    }catch(error){return 0;}
  };
  return{
    current,
    isNewerThan:revision=>current()>Math.max(0,Number.parseInt(revision,10)||0),
    markForStatus:status=>{
      if(registrationCapacityDelta(status)===0||!available)return false;
      try{storage.setItem(key,String(current()+1));return true;}catch(error){return false;}
    }
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
  resolveRegistrationMessage,
  normalizeServerRegistration,
  getStatusLabel,
  statusLabels,
  activityMatchesDiscoveryFilters,
  registrationPageForStatus,
  activityAvailabilityState,
  registrationErrorMessage,
  registrationCapacityDelta,
  registrationSuccessMessage,
  registrationBlockingState,
  normalizeRegistrationCapacity,
  createActivityCatalogFreshness,
  createSingleFlightRegistration,
  activeRegistrations,
  registeredSummary,
  registrationMatchesRegisteredFilters,
  canCancelRegistration,
  canCheckinRegistration,
  attendanceHistory,
  historySummary,
  attendanceRate,
  groupHistoryByMonth,
  historyMatchesFilters
};
if(typeof document==='undefined')return;
document.addEventListener('DOMContentLoaded',()=>{
  let catalogStorage=null;
  try{catalogStorage=global.sessionStorage;}catch(error){}
  const catalogFreshness=createActivityCatalogFreshness(catalogStorage);
  const discovery=document.querySelector('[data-activity-discovery-page]');
  if(discovery){
    const discoveryRevision=catalogFreshness.current();
    global.addEventListener?.('pageshow',event=>{
      if(event?.persisted===true&&catalogFreshness.isNewerThan(discoveryRevision))global.location.reload();
    });
    const cards=Array.from(discovery.querySelectorAll('[data-activity-card]'));
    const search=discovery.querySelector('[data-activity-search-input]');
    const time=discovery.querySelector('[data-activity-time-filter]');
    const availability=discovery.querySelector('[data-activity-availability-filter]');
    const categoryButtons=Array.from(discovery.querySelectorAll('[data-activity-filter]'));
    const resultStatus=discovery.querySelector('[data-activity-result-status]');
    const filterEmpty=discovery.querySelector('[data-activity-filter-empty]');
    let category='Tất cả';
    const renderDiscovery=()=>{
      const filters={
        query:search?.value||'',
        category,
        time:time?.value||'all',
        onlyAvailable:Boolean(availability?.checked)
      };
      let visible=0;
      cards.forEach(card=>{
        const matches=activityMatchesDiscoveryFilters({
          search:card.dataset.activitySearch||'',
          category:card.dataset.filterCategory||'',
          startAt:card.dataset.startAt||'',
          available:card.dataset.available!=='false'
        },filters);
        card.hidden=!matches;
        if(matches)visible+=1;
      });
      if(resultStatus)resultStatus.textContent=`${visible} hoạt động phù hợp`;
      if(filterEmpty)filterEmpty.hidden=visible!==0;
    };
    search?.addEventListener('input',renderDiscovery);
    time?.addEventListener('change',renderDiscovery);
    availability?.addEventListener('change',renderDiscovery);
    categoryButtons.forEach(button=>button.addEventListener('click',()=>{
      category=button.dataset.activityFilter||'Tất cả';
      categoryButtons.forEach(candidate=>candidate.setAttribute('aria-pressed',String(candidate===button)));
      renderDiscovery();
    }));
    renderDiscovery();
  }

  const historyPage=document.querySelector('[data-activity-history-page]');
  if(historyPage){
    const cards=Array.from(historyPage.querySelectorAll('[data-history-card]'));
    const groups=Array.from(historyPage.querySelectorAll('[data-history-month-group]'));
    const empty=historyPage.querySelector('[data-history-filter-empty]');
    const period=historyPage.querySelector('[data-history-period]');
    let status='all';
    const renderHistory=()=>{
      let visible=0;
      cards.forEach(card=>{const matches=historyMatchesFilters({status:card.dataset.status,attendance_resolved_at:card.dataset.historyTimestamp},{status,period:period?.value||'all'});card.hidden=!matches;if(matches)visible+=1;});
      groups.forEach(group=>{group.hidden=!group.querySelector('[data-history-card]:not([hidden])');});
      if(empty)empty.hidden=visible!==0;
    };
    historyPage.querySelectorAll('[data-history-filter]').forEach(button=>button.addEventListener('click',()=>{status=button.dataset.historyFilter||'all';historyPage.querySelectorAll('[data-history-filter]').forEach(candidate=>candidate.setAttribute('aria-pressed',String(candidate===button)));renderHistory();}));
    period?.addEventListener('change',renderHistory);renderHistory();
  }

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
  const submitRegistration=createSingleFlightRegistration(gateway);
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
    let capacitySnapshot=normalizeRegistrationCapacity(activity);
    let registrationBlock=null;
    const button=detail.querySelector('[data-register-current]');
    const message=detail.querySelector('[data-registration-message]');
    const feedbackBox=detail.querySelector('[data-registration-feedback-box]');
    const feedbackTitle=detail.querySelector('[data-feedback-title]');
    const feedbackDesc=detail.querySelector('[data-feedback-desc]');
    const count=detail.querySelector('[data-activity-count]');
    const participants=detail.querySelector('[data-activity-participants]');
    const remaining=detail.querySelector('[data-activity-remaining]');
    const capacityProgress=detail.querySelector('[data-activity-capacity-progress]');
    global.addEventListener?.('pageshow',event=>{
      if(event?.persisted===true)global.location.reload();
    });
    const renderCapacity=()=>{
      if(!capacitySnapshot)return;
      const{participants:confirmed,capacity,remaining:left,percent}=capacitySnapshot;
      if(count)count.textContent=`${confirmed}/${capacity} học sinh`;
      if(participants)participants.textContent=`${confirmed}/${capacity} đã đăng ký`;
      if(remaining)remaining.textContent=String(left);
      if(capacityProgress){
        capacityProgress.value=String(percent);
        capacityProgress.textContent=`${percent}%`;
        capacityProgress.setAttribute('aria-label',`Đã sử dụng ${percent}% số chỗ`);
      }
    };
    const setButtonTone=tone=>{
      if(!button)return;
      button.classList.remove('learner-btn--primary','learner-btn--secondary','learner-btn--outline');
      button.classList.add(`learner-btn--${['primary','secondary','outline'].includes(tone)?tone:'outline'}`);
    };
    const render=(commandFeedback='')=>{
      let registration=all().find(r=>r.activity_id===activity.id);
      if(!button)return;
      const defaultCta=!localMutationsEnabled&&!serverMutationsEnabled&&!registration&&canRegisterActivity(activity)
        ?{label:'Đăng ký trực tuyến chưa khả dụng',disabled:true,tone:'outline',explanation:'Kết nối đăng ký trực tuyến hiện chưa khả dụng.'}
        :activityCtaState(activity,registration);
      const cta=registrationBlock||defaultCta;
      const isSuccessState=Boolean(registration&&['approved','registered','attended','checked_in','pending'].includes(registration.status));

      if(feedbackBox){
        if(isSuccessState){
          feedbackBox.hidden=false;
          feedbackBox.classList.add('learner-activity-feedback-box--success');
          if(registration.status==='pending'){
            if(feedbackTitle)feedbackTitle.textContent='Đã gửi yêu cầu đăng ký!';
            if(feedbackDesc)feedbackDesc.textContent='Hệ thống đã chuyển yêu cầu đến giáo viên phụ trách để xét duyệt.';
          }else{
            if(feedbackTitle)feedbackTitle.textContent='Đăng ký tham gia thành công!';
            if(feedbackDesc)feedbackDesc.textContent='Hệ thống đã ghi nhận tên bạn vào danh sách. Vui lòng có mặt đúng giờ để check-in QR.';
          }
        }else{
          feedbackBox.hidden=true;
        }
      }

      if(isSuccessState){
        button.textContent=registration.status==='pending'?'✓ Đã gửi yêu cầu đăng ký':'✓ Đã đăng ký tham gia';
        button.disabled=true;
        button.classList.add('learner-btn--registered-disabled');
        if(message)message.hidden=true;
      }else{
        button.textContent=cta.label;
        button.disabled=cta.disabled;
        button.classList.remove('learner-btn--registered-disabled');
        setButtonTone(cta.tone);
        if(message){
          message.hidden=false;
          message.textContent=resolveRegistrationMessage(cta.explanation,commandFeedback);
          message.dataset.tone=cta.tone;
        }
      }
    };
    render();
    button?.addEventListener('click',async()=>{
      if(serverMutationsEnabled){
        registrationBlock=null;
        button.disabled=true;
        if(message)message.textContent='Đang ghi nhận đăng ký...';
        let commandFeedback='';
        try{
          const result=await submitRegistration(activity.id);
          const serverCapacity=normalizeRegistrationCapacity(result.capacity);
          const normalizedRegistration=normalizeServerRegistration(result.registration);
          if(!serverCapacity||!normalizedRegistration)throw{code:'INVALID_RESPONSE'};
          const created=upsertServerRegistration(result.registration);
          capacitySnapshot=serverCapacity;
          catalogFreshness.markForStatus(created?.status);
          commandFeedback=registrationSuccessMessage(created?.status);
        }catch(error){
          registrationBlock=registrationBlockingState(error);
          commandFeedback=registrationBlock?.explanation||registrationErrorMessage(error);
        }
        renderCapacity();render(commandFeedback);return;
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
        registrationBlock=registrationBlockingState({code:'SCHEDULE_CONFLICT'});
        render(registrationBlock?.explanation||'Lịch hoạt động bị trùng với một đăng ký hiện có.');return;
      }
      const existing=store?.getByActivity(activity.id);
      const created=createRegistration({studentId:boot.student_id,activity,id:existing?.id});
      if(!created){
        if(message)message.textContent='Hoạt động hiện không nhận đăng ký.';
        render();return;
      }
      store?.saveRegistration(created);
      capacitySnapshot=normalizeRegistrationCapacity({
        participants:(Number(activity.participants)||0)+registrationCapacityDelta(created.status),
        capacity:activity.capacity
      });
      if(message)message.textContent=registrationSuccessMessage(created.status);
      renderCapacity();
      render();
    });
  }

  const mine=document.querySelector('[data-my-activities-page]');
  if(mine){
    const cards=Array.from(mine.querySelectorAll('[data-registration-card]'));
    const search=mine.querySelector('[data-registration-search]');
    const empty=mine.querySelector('[data-registration-empty]');
    let filter='all';
    const updateRegisteredKpis=()=>{
      const connected=cards.filter(card=>card.isConnected).map(card=>({status:card.dataset.status}));
      const summary=registeredSummary(connected);
      for(const [key,value] of Object.entries(summary)){
        const target=mine.querySelector(`[data-registered-kpi="${key}"]`);
        if(target)target.textContent=String(value);
      }
    };
    const applyFilters=()=>{
      let visible=0;
      cards.forEach(card=>{
        const matches=registrationMatchesRegisteredFilters(
          {status:card.dataset.status},
          {title:card.dataset.registrationSearchText},
          {query:search?.value||'',status:filter}
        );
        card.hidden=!matches||!card.isConnected;
        if(!card.hidden)visible+=1;
      });
      if(empty)empty.hidden=visible!==0;
    };
    search?.addEventListener('input',applyFilters);
    mine.querySelectorAll('[data-registration-filter]').forEach(b=>b.addEventListener('click',()=>{
      mine.querySelectorAll('[data-registration-filter]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));
      filter=b.dataset.registrationFilter||'all';applyFilters();
    }));
    mine.querySelectorAll('[data-cancel-registration]').forEach(button=>button.addEventListener('click',async()=>{
      const registration=all().find(item=>item.id===button.dataset.cancelRegistration);
      const status=mine.querySelector('[data-registration-command-status]');
      if(!registration||!canCancelRegistration(registration,{cancellation_closes_at:button.dataset.cancellationClosesAt}))return;
      button.disabled=true;
      try{
        if(serverMutationsEnabled){
          const result=await gateway.cancel(registration.id,'Sinh viên chủ động hủy');
          upsertServerRegistration(result.registration);
        }else if(localMutationsEnabled){
          const next=cancelRegistration(registration,'Sinh viên chủ động hủy');
          if(next)store?.saveRegistration(next);
        }else throw{code:'SERVICE_UNAVAILABLE'};
        button.closest('[data-registration-card]')?.remove();
        if(status)status.textContent='Đã hủy đăng ký theo phản hồi từ hệ thống.';
        updateRegisteredKpis();applyFilters();
      }catch(error){
        button.disabled=false;
        if(status)status.textContent=registrationErrorMessage(error).replace('đăng ký hoạt động','hủy đăng ký');
      }
    }));
    applyFilters();
  }
});
})(typeof window!=='undefined'?window:globalThis);
