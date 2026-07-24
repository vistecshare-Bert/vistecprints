(function () {
  'use strict';

  const CLIENT_ID    = '896451806994-mbc9bqqlhca9mgs7m0vuhknlcrruoud6.apps.googleusercontent.com';
  const AUTH_URL     = '/auth.php';
  let   currentUser  = null;
  let   gisReady     = false;

  // ── Load Google Identity Services ────────────────────────
  function loadGIS() {
    if (gisReady) return Promise.resolve();
    return new Promise(resolve => {
      const s = document.createElement('script');
      s.src   = 'https://accounts.google.com/gsi/client';
      s.async = true;
      s.onload = () => { gisReady = true; resolve(); };
      document.head.appendChild(s);
    });
  }

  // ── CSS ──────────────────────────────────────────────────
  const css = `
  /* Auth overlay */
  #vpAuthOverlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:99999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(6px);}
  #vpAuthOverlay.vp-open{display:flex;}
  .vp-modal{background:#111;border:1px solid #2a2a2a;border-radius:4px;width:100%;max-width:380px;padding:40px 32px 32px;position:relative;text-align:center;}
  .vp-modal-close{position:absolute;top:12px;right:16px;background:none;border:none;color:#555;font-size:24px;cursor:pointer;line-height:1;transition:color .15s;}
  .vp-modal-close:hover{color:#fff;}
  .vp-modal-eyebrow{font-family:'Bebas Neue',sans-serif;font-size:13px;letter-spacing:3px;color:#D49848;margin-bottom:10px;}
  .vp-modal-title{font-size:20px;font-weight:700;color:#F0EDE8;margin-bottom:6px;}
  .vp-modal-sub{font-size:13px;color:#666;margin-bottom:28px;line-height:1.55;}
  /* Google button container — rendered by GIS */
  #vpGoogleBtnWrap{display:flex;justify-content:center;min-height:44px;}
  .vp-modal-note{font-size:11px;color:#444;margin-top:20px;line-height:1.6;}
  /* Nav auth button (logged out) */
  .vp-nav-auth{background:none;border:1px solid rgba(212,152,72,.45);color:#D49848;padding:6px 13px;border-radius:2px;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;cursor:pointer;white-space:nowrap;font-family:inherit;transition:all .15s;flex-shrink:0;}
  .vp-nav-auth:hover{background:rgba(212,152,72,.12);border-color:#D49848;}
  /* Nav user pill (logged in) */
  .vp-nav-user{display:flex;align-items:center;gap:8px;cursor:pointer;position:relative;flex-shrink:0;}
  .vp-nav-user img{width:32px;height:32px;border-radius:50%;border:2px solid #D49848;object-fit:cover;flex-shrink:0;}
  .vp-nav-user-name{font-size:12px;color:#D49848;font-weight:700;letter-spacing:.5px;white-space:nowrap;}
  .vp-nav-drop{display:none;position:absolute;top:calc(100% + 10px);right:0;background:#111;border:1px solid #2a2a2a;border-radius:3px;min-width:160px;z-index:9999;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,.5);}
  .vp-nav-user:hover .vp-nav-drop,.vp-nav-user.open .vp-nav-drop{display:block;}
  .vp-nav-drop a,.vp-nav-drop button{display:block;width:100%;text-align:left;padding:11px 16px;font-size:13px;color:#bbb;text-decoration:none;background:none;border:none;border-bottom:1px solid #1a1a1a;cursor:pointer;font-family:inherit;transition:background .15s;}
  .vp-nav-drop a:last-child,.vp-nav-drop button:last-child{border-bottom:none;}
  .vp-nav-drop a:hover,.vp-nav-drop button:hover{background:#1a1a1a;color:#D49848;}
  `;
  const styleEl = document.createElement('style');
  styleEl.textContent = css;
  document.head.appendChild(styleEl);

  // ── Modal HTML ───────────────────────────────────────────
  const modalHtml = `
  <div id="vpAuthOverlay">
    <div class="vp-modal" role="dialog" aria-modal="true" aria-label="Sign in">
      <button class="vp-modal-close" aria-label="Close" onclick="vpAuth.close()">&times;</button>
      <div class="vp-modal-eyebrow">Vistec GraphX</div>
      <h2 class="vp-modal-title">Sign Up / Login</h2>
      <p class="vp-modal-sub">Use your Google account to track orders<br>and save your designs.</p>
      <div id="vpGoogleBtnWrap"></div>
      <p class="vp-modal-note">We only store your name, email, and profile photo.<br>Your information is never shared or sold.</p>
    </div>
  </div>`;

  // ── Inject modal when DOM is ready ───────────────────────
  function injectModal() {
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.getElementById('vpAuthOverlay').addEventListener('click', function (e) {
      if (e.target === this) vpAuth.close();
    });
  }

  // ── Nav: inject button into .nav-right ───────────────────
  function injectNavButton() {
    const navRight = document.querySelector('.nav-right');
    if (!navRight || document.getElementById('vpNavAuth')) return;
    const btn = document.createElement('button');
    btn.className = 'vp-nav-auth';
    btn.id        = 'vpNavAuth';
    btn.textContent = 'Sign Up / Login';
    btn.onclick   = () => vpAuth.open();
    navRight.insertBefore(btn, navRight.firstChild);
  }

  // ── Nav: update to show user avatar or login button ──────
  function refreshNav(user) {
    const existing = document.getElementById('vpNavAuth') || document.getElementById('vpNavUser');
    if (!existing) return;
    const parent = existing.parentNode;

    if (!user) {
      const btn = document.createElement('button');
      btn.className = 'vp-nav-auth';
      btn.id        = 'vpNavAuth';
      btn.textContent = 'Sign Up / Login';
      btn.onclick   = () => vpAuth.open();
      parent.replaceChild(btn, existing);
    } else {
      const first = (user.name || 'Account').split(' ')[0];
      const pill  = document.createElement('div');
      pill.className = 'vp-nav-user';
      pill.id        = 'vpNavUser';
      pill.innerHTML = `
        <img src="${escHtml(user.picture)}" alt="${escHtml(user.name)}" referrerpolicy="no-referrer"/>
        <span class="vp-nav-user-name">${escHtml(first)}</span>
        <div class="vp-nav-drop">
          <a href="/account.php">My Account</a>
          <a href="/account.php#orders">My Orders</a>
          <button onclick="vpAuth.logout()">Sign Out</button>
        </div>`;
      parent.replaceChild(pill, existing);
    }
  }

  function escHtml(s) {
    return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  // ── Render Google Sign-In button ─────────────────────────
  async function renderGoogleBtn() {
    await loadGIS();
    const wrap = document.getElementById('vpGoogleBtnWrap');
    if (!wrap) return;
    google.accounts.id.initialize({
      client_id: CLIENT_ID,
      callback:  handleCredential,
      use_fedcm_for_prompt: false,
    });
    google.accounts.id.renderButton(wrap, {
      theme: 'outline',
      size:  'large',
      text:  'continue_with',
      width: 316,
      logo_alignment: 'left',
    });
  }

  // ── Handle Google credential response ────────────────────
  async function handleCredential(response) {
    const fd = new FormData();
    fd.append('credential', response.credential);
    try {
      const r = await fetch(AUTH_URL + '?action=google', { method: 'POST', body: fd, credentials: 'include' });
      const d = await r.json();
      if (d.success) {
        currentUser = d.user;
        refreshNav(currentUser);
        vpAuth.close();
        showWelcome(d.user.name.split(' ')[0]);
      } else {
        alert('Sign-in failed: ' + (d.error || 'Please try again.'));
      }
    } catch (e) {
      alert('Sign-in error. Please check your connection and try again.');
    }
  }

  // ── Welcome toast ─────────────────────────────────────────
  function showWelcome(firstName) {
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%);background:#D49848;color:#000;padding:12px 28px;border-radius:3px;font-size:13px;font-weight:700;letter-spacing:1px;z-index:99999;box-shadow:0 4px 20px rgba(0,0,0,.4);transition:opacity .4s;';
    t.textContent   = 'Welcome, ' + firstName + '!';
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 2800);
  }

  // ── Check session on page load ────────────────────────────
  async function checkStatus() {
    try {
      const r = await fetch(AUTH_URL + '?action=status', { credentials: 'include' });
      const d = await r.json();
      if (d.loggedIn) { currentUser = d.user; refreshNav(currentUser); }
    } catch (e) { /* offline / server error — fail silently */ }
  }

  // ── Public API ────────────────────────────────────────────
  window.vpAuth = {
    open() {
      const overlay = document.getElementById('vpAuthOverlay');
      if (!overlay) return;
      overlay.classList.add('vp-open');
      document.body.style.overflow = 'hidden';
      renderGoogleBtn();
    },
    close() {
      const overlay = document.getElementById('vpAuthOverlay');
      if (overlay) overlay.classList.remove('vp-open');
      document.body.style.overflow = '';
    },
    async logout() {
      try { await fetch(AUTH_URL + '?action=logout', { credentials: 'include' }); } catch (e) {}
      currentUser = null;
      refreshNav(null);
      if (window.location.pathname.includes('account')) window.location.href = '/index.html';
    },
    getUser() { return currentUser; },
  };

  // ── Boot ──────────────────────────────────────────────────
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { injectModal(); injectNavButton(); checkStatus(); });
  } else {
    injectModal(); injectNavButton(); checkStatus();
  }

}());
