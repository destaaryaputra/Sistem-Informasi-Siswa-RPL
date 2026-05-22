// Vercel Live Feedback toolbar implementation
class VercelLiveFeedback extends HTMLElement {
  constructor() {
    super();
    const shadow = this.attachShadow({ mode: 'closed' });
    this.shadow = shadow;
    this.isHidden = false;
    this.position = { top: '10px', right: '10px' };
    try {
      const savedPos = localStorage.getItem('vercel-live-feedback-pos');
      if (savedPos) this.position = JSON.parse(savedPos);
      const hidden = sessionStorage.getItem('vercel-live-feedback-hidden');
      if (hidden === '1') this.isHidden = true;
    } catch (_) {}
  }
  connectedCallback() {
    const style = document.createElement('style');
    style.textContent = `
      .toolbar { position: fixed; background: #111; color: #fff; padding: 6px 10px; font-size: 12px; border-radius: 4px; cursor: move; z-index: 9999; user-select: none; display: flex; align-items: center; }
      .toolbar button { background: none; border: none; color: #fff; margin-left: 8px; cursor: pointer; font-size: 14px; }
    `;
    const container = document.createElement('div');
    container.className = 'toolbar';
    container.textContent = 'Vercel Feedback';
    const hideBtn = document.createElement('button');
    hideBtn.textContent = '✕';
    hideBtn.title = 'Hide toolbar';
    hideBtn.addEventListener('click', () => { this.isHidden = true; sessionStorage.setItem('vercel-live-feedback-hidden', '1'); container.style.display = 'none'; });
    container.appendChild(hideBtn);
    this.shadow.appendChild(style);
    this.shadow.appendChild(container);
    this.applyPosition();
    let offsetX, offsetY;
    const onMouseDown = (e) => { e.preventDefault(); offsetX = e.clientX - container.getBoundingClientRect().left; offsetY = e.clientY - container.getBoundingClientRect().top; document.addEventListener('mousemove', onMouseMove); document.addEventListener('mouseup', onMouseUp); };
    const onMouseMove = (e) => { const left = e.clientX - offsetX; const top = e.clientY - offsetY; container.style.left = `${left}px`; container.style.top = `${top}px`; };
    const onMouseUp = () => { document.removeEventListener('mousemove', onMouseMove); document.removeEventListener('mouseup', onMouseUp); this.position = { top: container.style.top, left: container.style.left }; localStorage.setItem('vercel-live-feedback-pos', JSON.stringify(this.position)); };
    container.addEventListener('mousedown', onMouseDown);
    const onKeydown = (e) => { if (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'f') { e.preventDefault(); this.toggleVisibility(); } };
    window.addEventListener('keydown', onKeydown);
  }
  applyPosition() {
    const container = this.shadow.querySelector('.toolbar');
    if (!container) return;
    if (this.position.top) container.style.top = this.position.top;
    if (this.position.left) container.style.left = this.position.left;
    if (!container.style.left && this.position.right) container.style.right = this.position.right;
    container.style.position = 'fixed';
    if (this.isHidden) container.style.display = 'none';
  }
  toggleVisibility() {
    const container = this.shadow.querySelector('.toolbar');
    if (!container) return;
    this.isHidden = !this.isHidden;
    container.style.display = this.isHidden ? 'none' : 'flex';
    sessionStorage.setItem('vercel-live-feedback-hidden', this.isHidden ? '1' : '');
  }
}
customElements.define('vercel-live-feedback', VercelLiveFeedback);
