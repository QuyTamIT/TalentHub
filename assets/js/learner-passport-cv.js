(function (global) {
    'use strict';
    function fits(content) { return content.scrollHeight <= 268 * 96 / 25.4 && content.scrollWidth <= content.clientWidth + 1; }
    if (typeof module !== 'undefined' && module.exports) module.exports = { fits };
    if (!global.document) return;
    const doc = global.document;
    const error = doc.querySelector('[data-cv-error]');
    const content = doc.querySelector('[data-cv-content]');
    function check() {
        const ok = fits(content);
        doc.body.classList.toggle('cv-overflow', !ok);
        error.hidden = ok;
        error.textContent = ok ? '' : 'Nội dung vượt một trang A4. Chưa xuất PDF để tránh cắt mất chữ; vui lòng rút gọn nội dung hồ sơ.';
        return ok;
    }
    doc.querySelector('[data-cv-export]').addEventListener('click', () => {
        const url = new URL(global.location.href);
        url.search = '';
        url.searchParams.set('export', '1');
        url.searchParams.set('fresh', String(Date.now()));
        global.location.replace(url.href);
    });
    function initSealQr() {
        const qrNode = doc.getElementById('cv-seal-qr');
        if (!qrNode || typeof global.QRCode !== 'function') return;
        try {
            const rawUrl = qrNode.getAttribute('data-qr-url') || global.location.href;
            const url = new URL(rawUrl, global.location.origin).toString();
            qrNode.replaceChildren();
            new global.QRCode(qrNode, {
                text: url,
                width: 52,
                height: 52,
                colorDark: '#0f172a',
                colorLight: '#ffffff',
                correctLevel: global.QRCode.CorrectLevel.M,
            });
        } catch (e) {
            // keep fallback
        }
    }
    global.addEventListener('beforeprint', check);
    global.addEventListener('afterprint', () => doc.body.classList.remove('cv-overflow'));
    (async () => {
        initSealQr();
        await doc.fonts.ready;
        if (new URL(global.location.href).searchParams.get('export') === '1') {
            global.history.replaceState(null, '', global.location.pathname);
            if (check()) global.print();
        } else check();
    })();
})(typeof window !== 'undefined' ? window : globalThis);
