(function () {
  'use strict';

  const CLIENT_ID   = '896451806994-mbc9bqqlhca9mgs7m0vuhknlcrruoud6.apps.googleusercontent.com';
  const AUTH_URL    = '/auth.php';
  let   currentUser = null;
  let   gisReady    = false;
  let   currentMode = 'login';

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
  #vpAuthOverlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:99999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(6px);}
  #vpAuthOverlay.vp-open{display:flex;}
  .vp-modal{background:#111;border:1px solid #2a2a2a;border-radius:4px;width:100%;max-width:400px;padding:36px 32px 28px;position:relative;}
  .vp-modal-close{position:absolute;top:12px;right:16px;background:none;border:none;color:#555;font-size:24px;cursor:pointer;line-height:1;transition:color .15s;}
  .vp-modal-close:hover{color:#fff;}
  .vp-modal-eyebrow{font-family:'Bebas Neue',sans-serif;font-size:12px;letter-spacing:3px;color:#D49848;margin-bottom:8px;}
  .vp-modal-title{font-size:22px;font-weight:700;color:#F0EDE8;margin-bottom:20px;}

  /* Form fields */
  .vp-field{margin-bottom:14px;}
  .vp-field label{display:block;font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#888;margin-bottom:5px;}
  .vp-field input{width:100%;background:#1a1a1a;border:1px solid #333;border-radius:2px;color:#F0EDE8;font-size:14px;padding:10px 12px;font-family:inherit;outline:none;transition:border-color .15s;box-sizing:border-box;}
  .vp-field input:focus{border-color:#D49848;}
  .vp-field input::placeholder{color:#444;}
  .vp-error{font-size:12px;color:#e05555;min-height:16px;margin-bottom:10px;line-height:1.4;}
  .vp-btn-submit{width:100%;background:#D49848;color:#000;border:none;border-radius:2px;font-size:13px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:12px;cursor:pointer;font-family:inherit;transition:opacity .15s;margin-bottom:16px;}
  .vp-btn-submit:hover{opacity:.85;}
  .vp-btn-submit:disabled{opacity:.5;cursor:not-allowed;}

  /* Divider */
  .vp-divider{display:flex;align-items:center;gap:12px;margin-bottom:16px;}
  .vp-divider::before,.vp-divider::after{content:'';flex:1;height:1px;background:#2a2a2a;}
  .vp-divider span{font-size:11px;color:#555;letter-spacing:1px;text-transform:uppercase;}

  /* Google button */
  #vpGoogleBtnWrap{display:flex;justify-content:center;min-height:44px;margin-bottom:16px;}

  /* Footer */
  .vp-modal-note{font-size:11px;color:#444;text-align:center;line-height:1.6;margin-bottom:10px;}
  .vp-modal-switch{font-size:12px;color:#555;text-align:center;}
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

  // ── Modal shell (content swapped dynamically) ─────────────
  const modalHtml = `
  <div id="vpAuthOverlay">
    <div class="vp-modal" role="dialog" aria-modal="true">
      <button class="vp-modal-close" aria-label="Close" onclick="vpAuth.close()">&times;</button>
      <div class="vp-modal-eyebrow">Vistec GraphX</div>
      <h2 class="vp-modal-title" id="vpModalTitle"></h2>
      <div id="vpModalBody"></div>
    </div>
  </div>`;

  // ── Form templates ────────────────────────────────────────
  function loginBody() {
    return `
      <form id="vpLoginForm" onsubmit="return false;">
        <div class="vp-field">
          <label for="vpLoginEmail">Email</label>
          <input type="email" id="vpLoginEmail" placeholder="your@email.com" autocomplete="email" required/>
        </div>
        <div class="vp-field">
          <label for="vpLoginPass">Password</label>
          <input type="password" id="vpLoginPass" placeholder="••••••••" autocomplete="current-password" required/>
        </div>
        <div class="vp-error" id="vpLoginErr"></div>
        <button type="submit" class="vp-btn-submit" id="vpLoginBtn">Login</button>
      </form>
      <div class="vp-divider"><span>or</span></div>
      <div id="vpGoogleBtnWrap"></div>
      <p class="vp-modal-note">We only store your name, email, and profile photo.</p>
      <p class="vp-modal-switch">Don't have an account? <button onclick="vpAuth.open('signup')">Sign Up</button></p>`;
  }

  function signupBody() {
    return `
      <form id="vpSignupForm" onsubmit="return false;">
        <div class="vp-field">
          <label for="vpSignupName">Full Name</label>
          <input type="text" id="vpSignupName" placeholder="Your name" autocomplete="name" required/>
        </div>
        <div class="vp-field">
          <label for="vpSignupEmail">Email</label>
          <input type="email" id="vpSignupEmail" placeholder="your@email.com" autocomplete="email" required/>
        </div>
        <div class="vp-field">
          <label for="vpSignupPass">Password</label>
          <input type="password" id="vpSignupPass" placeholder="Min. 8 characters" autocomplete="new-password" required/>
        </div>
        <div class="vp-field">
          <label for="vpSignupPass2">Confirm Password</label>
          <input type="password" id="vpSignupPass2" placeholder="Repeat password" autocomplete="new-password" required/>
        </div>
        <div class="vp-error" id="vpSignupErr"></div>
        <button type="submit" class="vp-btn-submit" id="vpSignupBtn">Create Account</button>
      </form>
      <div class="vp-divider"><span>or</span></div>
      <div id="vpGoogleBtnWrap"></div>
      <p class="vp-modal-note">We only store your name, email, and profile photo.</p>
      <p class="vp-modal-switch">Already have an account? <button onclick="vpAuth.open('login')">Login</button></p>`;
  }

  // ── Inject modal ──────────────────────────────────────────
  function injectModal() {
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    document.getElementById('vpAuthOverlay').addEventListener('click', function (e) {
      if (e.target === this) vpAuth.close();
    });
  }

  // ── Set modal content for mode ────────────────────────────
  function applyMode(mode) {
    currentMode = mode;
    document.getElementById('vpModalTitle').textContent = mode === 'signup' ? 'Create Account' : 'Welcome Back';
    document.getElementById('vpModalBody').innerHTML    = mode === 'signup' ? signupBody() : loginBody();

    if (mode === 'login') {
      document.getElementById('vpLoginForm').addEventListener('submit', handleLoginSubmit);
    } else {
      document.getElementById('vpSignupForm').addEventListener('submit', handleSignupSubmit);
    }

    renderGoogleBtn(mode);
  }

  // ── Form submit handlers ──────────────────────────────────
  async function handleLoginSubmit() {
    const email  = document.getElementById('vpLoginEmail').value.trim();
    const pass   = document.getElementById('vpLoginPass').value;
    const errEl  = document.getElementById('vpLoginErr');
    const btn    = document.getElementById('vpLoginBtn');
    errEl.textContent = '';
    btn.disabled = true;
    btn.textContent = 'Signing in…';

    try {
      const fd = new FormData();
      fd.append('email', email);
      fd.append('password', pass);
      const r = await fetch(AUTH_URL + '?action=login', { method: 'POST', body: fd, credentials: 'include' });
      const d = await r.json();
      if (d.success) {
        onAuthSuccess(d.user);
      } else {
        errEl.textContent = d.error || 'Login failed. Please try again.';
        btn.disabled = false;
        btn.textContent = 'Login';
      }
    } catch (e) {
      errEl.textContent = 'Connection error. Please try again.';
      btn.disabled = false;
      btn.textContent = 'Login';
    }
  }

  async function handleSignupSubmit() {
    const name   = document.getElementById('vpSignupName').value.trim();
    const email  = document.getElementById('vpSignupEmail').value.trim();
    const pass   = document.getElementById('vpSignupPass').value;
    const pass2  = document.getElementById('vpSignupPass2').value;
    const errEl  = document.getElementById('vpSignupErr');
    const btn    = document.getElementById('vpSignupBtn');
    errEl.textContent = '';

    if (pass !== pass2) { errEl.textContent = 'Passwords do not match.'; return; }
    if (pass.length < 8) { errEl.textContent = 'Password must be at least 8 characters.'; return; }

    btn.disabled = true;
    btn.textContent = 'Creating account…';

    try {
      const fd = new FormData();
      fd.append('name',     name);
      fd.append('email',    email);
      fd.append('password', pass);
      const r = await fetch(AUTH_URL + '?action=register', { method: 'POST', body: fd, credentials: 'include' });
      const d = await r.json();
      if (d.success) {
        onAuthSuccess(d.user);
      } else {
        errEl.textContent = d.error || 'Sign up failed. Please try again.';
        btn.disabled = false;
        btn.textContent = 'Create Account';
      }
    } catch (e) {
      errEl.textContent = 'Connection error. Please try again.';
      btn.disabled = false;
      btn.textContent = 'Create Account';
    }
  }

  // ── Shared post-auth handler ──────────────────────────────
  function onAuthSuccess(user) {
    currentUser = user;
    refreshNav(user);
    vpAuth.close();
    const msg = currentMode === 'signup'
      ? 'Account created — welcome, ' + user.name.split(' ')[0] + '!'
      : 'Welcome back, ' + user.name.split(' ')[0] + '!';
    showToast(msg);
  }

  // ── Google Sign-In button ─────────────────────────────────
  async function renderGoogleBtn(mode) {
    await loadGIS();
    const wrap = document.getElementById('vpGoogleBtnWrap');
    if (!wrap) return;
    wrap.innerHTML = '';
    google.accounts.id.initialize({
      client_id: CLIENT_ID,
      callback:  handleGoogleCredential,
      use_fedcm_for_prompt: false,
    });
    google.accounts.id.renderButton(wrap, {
      theme:          'outline',
      size:           'large',
      text:           mode === 'signup' ? 'signup_with' : 'signin_with',
      width:          336,
      logo_alignment: 'left',
    });
  }

  async function handleGoogleCredential(response) {
    const fd = new FormData();
    fd.append('credential', response.credential);
    try {
      const r = await fetch(AUTH_URL + '?action=google', { method: 'POST', body: fd, credentials: 'include' });
      const d = await r.json();
      if (d.success) {
        onAuthSuccess(d.user);
      } else {
        alert('Google sign-in failed: ' + (d.error || 'Please try again.'));
      }
    } catch (e) {
      alert('Connection error. Please check your connection and try again.');
    }
  }

  // ── Toast ─────────────────────────────────────────────────
  function showToast(msg) {
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%);background:#D49848;color:#000;padding:12px 28px;border-radius:3px;font-size:13px;font-weight:700;letter-spacing:1px;z-index:99999;box-shadow:0 4px 20px rgba(0,0,0,.4);transition:opacity .4s;white-space:nowrap;';
    t.textContent   = msg;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 3000);
  }

  // ── Nav buttons ───────────────────────────────────────────
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

  function escHtml(s) {
    return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

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
        ${user.picture
          ? `<img src="${escHtml(user.picture)}" alt="${escHtml(user.name)}" referrerpolicy="no-referrer"/>`
          : `<div style="width:32px;height:32px;border-radius:50%;border:2px solid #D49848;background:#222;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#D49848;flex-shrink:0;">${escHtml(first[0])}</div>`
        }
        <span class="vp-nav-user-name">${escHtml(first)}</span>
        <div class="vp-nav-drop">
          <a href="/account.php">My Account</a>
          <a href="/account.php#orders">My Orders</a>
          <button onclick="vpAuth.logout()">Sign Out</button>
        </div>`;
      parent.replaceChild(pill, existing);
    }
  }

  // ── Session check ─────────────────────────────────────────
  async function checkStatus() {
    try {
      const r = await fetch(AUTH_URL + '?action=status', { credentials: 'include' });
      const d = await r.json();
      if (d.loggedIn) { currentUser = d.user; refreshNav(currentUser); }
    } catch (e) {}
  }

  // ── Public API ────────────────────────────────────────────
  window.vpAuth = {
    open(mode = 'login') {
      const overlay = document.getElementById('vpAuthOverlay');
      if (!overlay) return;
      applyMode(mode);
      overlay.classList.add('vp-open');
      document.body.style.overflow = 'hidden';
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
