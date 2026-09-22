document.addEventListener('DOMContentLoaded',()=>{
    document.querySelector('[data-error-summary]')?.focus();
    document.querySelectorAll('[data-password-toggle]').forEach(button=>{
        button.addEventListener('click',()=>{const input=document.getElementById(button.getAttribute('aria-controls'));if(!input)return;const showing=input.type==='text';input.type=showing?'password':'text';button.textContent=showing?'Hiện':'Ẩn';button.setAttribute('aria-pressed',String(!showing));});
    });
    const password=document.getElementById('password');const confirmation=document.querySelector('[data-password-confirm]');const match=document.querySelector('[data-password-match]');
    const validateMatch=()=>{if(!password||!confirmation||!match)return true;const valid=confirmation.value===''||password.value===confirmation.value;confirmation.setCustomValidity(valid?'':'Mật khẩu nhập lại chưa khớp.');match.textContent=valid?'':'Mật khẩu nhập lại chưa khớp.';return valid;};
    password?.addEventListener('input',validateMatch);confirmation?.addEventListener('input',validateMatch);
    const schoolSelect=document.querySelector('[data-school-select]');const classSelect=document.querySelector('[data-class-select]');const classHint=document.querySelector('[data-class-hint]');
    const syncClasses=(reset=false)=>{if(!schoolSelect||!classSelect)return;const schoolId=schoolSelect.value;let available=0;Array.from(classSelect.options).forEach((option,index)=>{if(index===0)return;const matches=schoolId!==''&&option.dataset.schoolId===schoolId;option.hidden=!matches;option.disabled=!matches;if(matches)available+=1;});if(reset||classSelect.selectedOptions[0]?.dataset.schoolId!==schoolId){classSelect.value='';}classSelect.disabled=schoolId===''||available===0;if(classHint){classHint.textContent=schoolId===''?'Chọn trường trước.':available===0?'Trường này chưa có lớp đang hoạt động.':`${available} lớp đang hoạt động.`;}};
    if(schoolSelect&&classSelect){syncClasses(false);schoolSelect.addEventListener('change',()=>{syncClasses(true);if(!classSelect.disabled)classSelect.focus();});}
    document.querySelectorAll('[data-auth-form]').forEach(form=>form.addEventListener('submit',event=>{if(!validateMatch()||!form.checkValidity()){event.preventDefault();form.reportValidity();return;}const button=form.querySelector('[data-submit]');if(button){button.disabled=true;button.setAttribute('aria-disabled','true');button.classList.add('is-loading');button.querySelector('span')?.replaceChildren(document.createTextNode('Đang xử lý...'));}}));
    document.querySelectorAll('[data-demo-login]').forEach(button=>{
        button.addEventListener('click',()=>{
            const form=document.querySelector('[data-auth-form]');
            const email=document.getElementById('email');
            const passwordInput=document.getElementById('password');
            if(!form||!email||!passwordInput)return;
            email.value=button.getAttribute('data-email')||'';
            passwordInput.value=button.getAttribute('data-password')||'';
            if(typeof form.requestSubmit==='function'){form.requestSubmit();}else{form.submit();}
        });
    });
    const demoModal=document.getElementById('auth-demo-modal');
    const openDemoModal=()=>{
        if(!demoModal)return;
        demoModal.hidden=false;
        demoModal.classList.add('is-open');
        document.body.classList.add('auth-demo-modal-open');
        const activeTab=demoModal.querySelector('.auth-demo-modal__tab.is-active')||demoModal.querySelector('[data-demo-tab]');
        activeTab?.focus();
    };
    const closeDemoModal=()=>{
        if(!demoModal)return;
        demoModal.hidden=true;
        demoModal.classList.remove('is-open');
        document.body.classList.remove('auth-demo-modal-open');
        document.querySelector('[data-open-demo-modal]')?.focus();
    };
    const activateDemoTab=(role)=>{
        if(!demoModal||!role)return;
        demoModal.querySelectorAll('[data-demo-tab]').forEach(tab=>{
            const on=tab.getAttribute('data-demo-tab')===role;
            tab.classList.toggle('is-active',on);
            tab.setAttribute('aria-selected',on?'true':'false');
            tab.tabIndex=on?0:-1;
        });
        demoModal.querySelectorAll('[data-demo-panel]').forEach(panel=>{
            const on=panel.getAttribute('data-demo-panel')===role;
            panel.classList.toggle('is-active',on);
            panel.hidden=!on;
        });
    };
    document.querySelector('[data-open-demo-modal]')?.addEventListener('click',event=>{
        event.preventDefault();
        openDemoModal();
    });
    document.querySelectorAll('[data-close-demo-modal]').forEach(el=>el.addEventListener('click',closeDemoModal));
    demoModal?.querySelectorAll('[data-demo-tab]').forEach(tab=>{
        tab.addEventListener('click',()=>activateDemoTab(tab.getAttribute('data-demo-tab')||''));
    });
    document.addEventListener('keydown',event=>{
        if(!demoModal||demoModal.hidden)return;
        if(event.key==='Escape'){closeDemoModal();return;}
        if(event.key!=='ArrowLeft'&&event.key!=='ArrowRight')return;
        const tabs=[...demoModal.querySelectorAll('[data-demo-tab]')];
        if(tabs.length===0)return;
        const current=tabs.findIndex(tab=>tab.classList.contains('is-active'));
        const next=event.key==='ArrowRight'
            ?(current+1)%tabs.length
            :(current-1+tabs.length)%tabs.length;
        activateDemoTab(tabs[next].getAttribute('data-demo-tab')||'');
        tabs[next].focus();
        event.preventDefault();
    });
});
window.addEventListener('pageshow',()=>{document.querySelectorAll('[data-submit].is-loading').forEach(button=>{button.disabled=false;button.removeAttribute('aria-disabled');button.classList.remove('is-loading');const label=button.querySelector('span');if(label){label.textContent=document.body.dataset.submitLabel||(document.body.classList.contains('auth-page--register')?'Tạo tài khoản học viên':'Đăng nhập');}});});
