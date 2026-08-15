document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('[data-password-toggle]').forEach(button=>{
        button.addEventListener('click',()=>{const input=document.getElementById(button.getAttribute('aria-controls'));if(!input)return;const showing=input.type==='text';input.type=showing?'password':'text';button.textContent=showing?'Hiện':'Ẩn';button.setAttribute('aria-pressed',String(!showing));});
    });
    const password=document.getElementById('password');const confirmation=document.querySelector('[data-password-confirm]');const match=document.querySelector('[data-password-match]');
    const validateMatch=()=>{if(!password||!confirmation||!match)return true;const valid=confirmation.value===''||password.value===confirmation.value;confirmation.setCustomValidity(valid?'':'Mật khẩu nhập lại chưa khớp.');match.textContent=valid?'':'Mật khẩu nhập lại chưa khớp.';return valid;};
    password?.addEventListener('input',validateMatch);confirmation?.addEventListener('input',validateMatch);
    document.querySelectorAll('[data-auth-form]').forEach(form=>form.addEventListener('submit',event=>{if(!validateMatch()||!form.checkValidity()){event.preventDefault();form.reportValidity();return;}const button=form.querySelector('[data-submit]');if(button){button.disabled=true;button.classList.add('is-loading');button.querySelector('span')?.replaceChildren(document.createTextNode('Đang xử lý...'));}}));
});
