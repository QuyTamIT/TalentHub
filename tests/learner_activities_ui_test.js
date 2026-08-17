'use strict';
const test=require('node:test'); const assert=require('node:assert/strict');
require('../assets/js/learner-activities.js');
const {canRegisterActivity,resolveRegistrationStatus,hasScheduleConflict,createActivityStorage,createRegistration,cancelRegistration,saveFeedback,mergeRegistrations}=global.LearnerActivities;
const memory=()=>{const d={};return{getItem:k=>d[k]??null,setItem:(k,v)=>{d[k]=String(v)}}};
const openActivity=(overrides={})=>({id:'a1',participants:1,capacity:2,approval_mode:'automatic',status:'published',registration_opens_at:'2026-08-01T00:00:00Z',registration_closes_at:'2026-08-31T23:59:59Z',can_register:true,...overrides});

test('registration status follows capacity and approval rules',()=>{
 assert.equal(resolveRegistrationStatus({participants:10,capacity:20,approval_mode:'automatic'}),'registered');
 assert.equal(resolveRegistrationStatus({participants:20,capacity:20,approval_mode:'automatic'}),'waitlisted');
 assert.equal(resolveRegistrationStatus({participants:10,capacity:20,approval_mode:'teacher_review'}),'pending');
});
test('schedule conflict detects overlapping active registrations',()=>{
 const activity={id:'new',start_at:'2026-08-28T15:00:00Z',end_at:'2026-08-28T17:00:00Z'};
 const catalog=[activity,{id:'old',start_at:'2026-08-28T14:00:00Z',end_at:'2026-08-28T16:00:00Z'}];
 assert.equal(hasScheduleConflict(activity,[{activity_id:'old',status:'registered'}],catalog),true);
 assert.equal(hasScheduleConflict(activity,[{activity_id:'old',status:'cancelled'}],catalog),false);
});
test('storage survives corrupt data and round trips registration',()=>{
 const raw=memory(); raw.setItem('x','{bad'); const store=createActivityStorage(raw,'x'); assert.deepEqual(store.getRegistrations(),[]);
 store.saveRegistration({id:'r1',activity_id:'a1'}); assert.equal(store.getRegistration('r1').activity_id,'a1');
});
test('registration contract and lifecycle are immutable',()=>{
 const registration=createRegistration({studentId:'s1',activity:openActivity(),now:'2026-08-13T10:00:00Z',id:'r1'});
 assert.equal(registration.status,'registered'); assert.equal(registration.student_id,'s1');
 const cancelled=cancelRegistration(registration,'Không tham gia được','2026-08-13T11:00:00Z'); assert.equal(cancelled.status,'cancelled'); assert.equal(registration.status,'registered');
 const completed={...registration,status:'completed'}; const reviewed=saveFeedback(completed,5,'Rất hữu ích','2026-08-13T12:00:00Z'); assert.equal(reviewed.feedback.rating,5);
 assert.equal(saveFeedback(registration,5,'x','2026-08-13T12:00:00Z'),null);
});
test('registration requires published or active status inside the registration window',()=>{
 assert.equal(canRegisterActivity(openActivity(),'2026-08-14T00:00:00Z'),true);
 assert.equal(canRegisterActivity(openActivity({status:'active'}),'2026-08-14T00:00:00Z'),true);
 for(const status of ['draft','cancelled','closed','completed']){
  const activity=openActivity({status});
  assert.equal(canRegisterActivity(activity,'2026-08-14T00:00:00Z'),false);
  assert.equal(createRegistration({studentId:'s1',activity,now:'2026-08-14T00:00:00Z'}),null);
 }
 assert.equal(canRegisterActivity(openActivity({registration_closes_at:'2026-08-13T23:59:59Z'}),'2026-08-14T00:00:00Z'),false);
 assert.equal(createRegistration({studentId:'s1',activity:openActivity({registration_closes_at:'2026-08-13T23:59:59Z'}),now:'2026-08-14T00:00:00Z'}),null);
 assert.equal(canRegisterActivity(openActivity({registration_opens_at:'2026-08-15T00:00:00Z'}),'2026-08-14T00:00:00Z'),false);
 assert.equal(canRegisterActivity(openActivity({can_register:false}),'2026-08-14T00:00:00Z'),false);
});
test('merge registrations lets local state override mock by activity',()=>{
 const result=mergeRegistrations([{id:'m',activity_id:'a',status:'registered'}],[{id:'l',activity_id:'a',status:'cancelled'}]);
 assert.deepEqual(result.map(x=>x.id),['l']);
});
