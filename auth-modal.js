(function () {
  'use strict';

  const CLIENT_ID   = '896451806994-mbc9bqqlhca9mgs7m0vuhknlcrruoud6.apps.googleusercontent.com';
  const AUTH_URL    = '/auth.php';
  let   currentUser = null;
  let   gisReady    = false;
  let   currentMode = 'login'; // 'login' | 'signup'

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
  #vpAuthOverlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.78);z-index:99999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(6px);}
  #vpAuthOverlay.vp-open{display:flex;}
  .vp-modal{background:#111;border:1px solid #2a2a2a;border-radius:4px;width:100%;max-width:380px;padding:40px 32px 32px;position:relative;text-align:center;}
  .vp-modal-close{position:absolute;top:12px;right:16px;background:none;border:none;color:#555;font-size:24px;cursor:pointer;line-height:1;transition:color .15s;}
  .vp-modal-close:hover{color:#fff;}
  .vp-modal-eyebrow{font-family:'Bebas Neue',sans-serif;font-size:13px;letter-spacing:3px;color:#D49848;margin-bottom:10px;}
  .vp-modal-title{font-size:22px;font-weight:700;color:#F0EDE8;margin-bottom:8px;}
  .vp-modal-sub{font-size:13px;color:#666;margin-bottom:28px;line-height:1.6;}
  #vpGoogleBtnWrap{display:flex;justify-content:center;min-height:44px;}
  .vp-modal-note{font-size:11px;color:#444;margin-top:20px;line-height:1.6;}
  .vp-modal-switch{font-size:12px;color:#555;margin-top:14px;}
  .vp-modal-switch button{background:none;border:none;color:#D49848;font-size:12px;cursor:pointer;font-family:inherit;text-decoration:underline;padding:0;}

  /* Nav — logged out: two buttons */
  .vp-nav-auth-wrap{display:flex;align-items:center;gap:8px;flex-shrink:0;}
  .vp-nav-login{background:none;border:1px solid rgba(212,152,72,.4);color:#D49848;padding:7px 14px;border-radius:2px;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;cursor:pointer;white-space:nowrap;font-family:inherit;transition:all .15s;flex-shrink:0;}
  .vp-nav-login:hover{background:rgba(212,152,72,.1);border-color:#D49848;}
  .vp-nav-signup{background:#D49848;border:1px solid #D49848;color:#000;padding:7px 16px;border-radius:2px;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;cursor:pointer;white-space:nowrap;font-family:inherit;transition:opacity .15s;flex-shrink:0;}
  .vp-nav-signup:hover{opacity:.85;}

  /* Nav — logged in: avatar pill */
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
    <div class="vp-modal" role="dialog" aria-modal="true">
      <button class="vp-modal-close" aria-label="Close" onclick="vpAuth.close()">&times;</button>
      <div class="vp-modal-eyebrow">Vistec GraphX</div>
      <h2 class="vp-modal-title" id="vpModalTitle">Welcome Back</h2>
      <p class="vp-modal-sub" id="vpModalSub">Sign in with Google to access your orders and saved designs.</p>
      <div id="vpGoogleBtnWrap"></div>
      <p class="vp-modal-note">We only store your name, email, and profile photo.<br>Your information is never shared or sold.</p>
      <p class="vp-modal-switch" id="vpModalSwitch">
        Don't have an account? <button onclick="vpAuth.open('signup')">Sign Up</button>
      </p>
    </div>
  </div>`;

  function injectModal() {
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.getElementById('vpAuthOverlay').addEventListener('click', function (e) {
      if (e.target === this) vpAuth.close();
    });
  }

  // ── Nav: inject two buttons into .nav-right ───────────────
  function makeAuthWrap() {
    const wrap = document.createElement('div');
    wrap.className = 'vp-nav-auth-wrap';
    wrap.id        = 'vpNavAuthWrap';

    const loginBtn = document.createElement('button');
    loginBtn.className   = 'vp-nav-login';
    loginBtn.textContent = 'Login';
    loginBtn.onclick     = () => vpAuth.open('login');

    const signupBtn = document.createElement('button');
    signupBtn.className   = 'vp-nav-signup';
    signupBtn.textContent = 'Sign Up';
    signupBtn.onclick     = () => vpAuth.open('signup');

    wrap.appendChild(loginBtn);
    wrap.appendChild(signupBtn);
    return wrap;
  }

  function injectNavButton() {
    const navRight = document.querySelector('.nav-right');
    if (!navRight || document.getElementById('vpNavAuthWrap')) return;
    navRight.insertBefore(makeAuthWrap(), navRight.firstChild);
  }

  // ── Nav: swap between auth buttons and user pill ──────────
  function refreshNav(user) {
    const existing = document.getElementById('vpNavAuthWrap') || document.getElementById('vpNavUser');
    if (!existing) return;
    const parent = existing.parentNode;

    if (!user) {
      parent.replaceChild(makeAuthWrap(), existing);
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

  // ── Update modal copy based on mode ───────────────────────
  function applyModalMode(mode) {
    currentMode = mode;
    const title  = document.getElementById('vpModalTitle');
    const sub    = document.getElementById('vpModalSub');
    const toggle = document.getElementById('vpModalSwitch');
    if (!title) return;

    if (mode === 'signup') {
      title.textContent  = 'Create Your Account';
      sub.textContent    = 'Sign up with Google to track orders and save your custom designs.';
      toggle.innerHTML   = 'Already have an account? <button onclick="vpAuth.open(\'login\')">Login</button>';
    } else {
      title.textContent  = 'Welcome Back';
      sub.textContent    = 'Sign in with Google to access your orders and saved designs.';
      toggle.innerHTML   = 'Don\'t have an account? <button onclick="vpAuth.open(\'signup\')">Sign Up</button>';
    }
  }

  // ── Render Google Sign-In button ─────────────────────────
  async function renderGoogleBtn(mode) {
    await loadGIS();
    const wrap = document.getElementById('vpGoogleBtnWrap');
    if (!wrap) return;
    wrap.innerHTML = ''; // clear previous render
    google.accounts.id.initialize({
      client_id: CLIENT_ID,
      callback:  handleCredential,
      use_fedcm_for_prompt: false,
    });
    google.accounts.id.renderButton(wrap, {
      theme:          'outline',
      size:           'large',
      text:           mode === 'signup' ? 'signup_with' : 'signin_with',
      width:          316,
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
        showWelcome(d.user.name.split(' ')[0], currentMode);
      } else {
        alert('Sign-in failed: ' + (d.error || 'Please try again.'));
      }
    } catch (e) {
      alert('Sign-in error. Please check your connection and try again.');
    }
  }

  // ── Welcome toast ─────────────────────────────────────────
  function showWelcome(firstName, mode) {
    const msg = mode === 'signup'
      ? 'Account created — welcome, ' + firstName + '!'
      : 'Welcome back, ' + firstName + '!';
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%);background:#D49848;color:#000;padding:12px 28px;border-radius:3px;font-size:13px;font-weight:700;letter-spacing:1px;z-index:99999;box-shadow:0 4px 20px rgba(0,0,0,.4);transition:opacity .4s;white-space:nowrap;';
    t.textContent   = msg;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 3000);
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
    open(mode = 'login') {
      const overlay = document.getElementById('vpAuthOverlay');
      if (!overlay) return;
      applyModalMode(mode);
      overlay.classList.add('vp-open');
      document.body.style.overflow = 'hidden';
      renderGoogleBtn(mode);
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
