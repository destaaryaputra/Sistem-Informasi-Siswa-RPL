/**
 * EduAlert: Custom Modal & Alert Utility
 */
const EduAlert = (() => {
    let activeResolve = null;

    const createModal = (type, title, message, showCancel = false) => {
        const overlay = document.createElement('div');
        overlay.className = 'edu-modal-overlay';
        
        let iconSvg = '';
        let confirmClass = 'confirm';
        
        switch (type) {
            case 'success':
                iconSvg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                break;
            case 'danger':
                iconSvg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
                confirmClass = 'confirm danger';
                break;
            case 'warning':
                iconSvg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
                break;
            default: // info
                iconSvg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>';
        }

        overlay.innerHTML = `
            <div class="edu-modal-box">
                <div class="edu-modal-header">
                    <div class="edu-modal-icon ${type}">${iconSvg}</div>
                    <h3>${title}</h3>
                </div>
                <div class="edu-modal-body">${message}</div>
                <div class="edu-modal-footer">
                    ${showCancel ? '<button class="edu-modal-btn cancel">Batal</button>' : ''}
                    <button class="edu-modal-btn ${confirmClass}">Oke</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        requestAnimationFrame(() => overlay.classList.add('is-visible'));

        return new Promise((resolve) => {
            const close = (result) => {
                overlay.classList.remove('is-visible');
                setTimeout(() => {
                    overlay.remove();
                    resolve(result);
                }, 200);
            };

            overlay.querySelector('.confirm').addEventListener('click', () => close(true));
            if (showCancel) {
                overlay.querySelector('.cancel').addEventListener('click', () => close(false));
            }
        });
    };

    return {
        alert: (message, title = 'Informasi') => createModal('info', title, message, false),
        success: (message, title = 'Berhasil') => createModal('success', title, message, false),
        error: (message, title = 'Kesalahan') => createModal('danger', title, message, false),
        confirm: (message, title = 'Konfirmasi', isDanger = false) => createModal(isDanger ? 'danger' : 'warning', title, message, true)
    };
})();

// Automatic Form Confirmation Helper
document.addEventListener('submit', async (e) => {
    const form = e.target;
    const confirmMsg = form.getAttribute('data-confirm');
    
    if (confirmMsg && !form.dataset.eduConfirmed) {
        e.preventDefault();
        const isDanger = form.querySelector('button.danger') !== null || confirmMsg.toLowerCase().includes('hapus');
        const confirmed = await EduAlert.confirm(confirmMsg, 'Konfirmasi Tindakan', isDanger);
        
        if (confirmed) {
            form.dataset.eduConfirmed = 'true';
            form.submit();
        }
    }
});

// Override window.alert/confirm (Optional, but useful for legacy code)
// Note: This won't be truly synchronous, so it's better to use data-confirm for forms.
window.eduAlert = EduAlert.alert;
window.eduConfirm = EduAlert.confirm;
