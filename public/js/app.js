// public/js/app.js — CampusMart frontend JS

document.addEventListener('DOMContentLoaded', () => {

  // ── Navbar: mobile menu + user dropdown ─────────────────
  const navbar = document.querySelector('.navbar');
  const navToggle = document.querySelector('.nav-toggle');
  const navLinks = document.getElementById('nav-links');
  const navUser = document.querySelector('.nav-user');
  const navUserBtn = navUser ? navUser.querySelector('.nav-user-btn') : null;
  const navUserMenu = navUser ? navUser.querySelector('.nav-user-menu') : null;

  const closeNavMenu = () => {
    if (!navbar) return;
    navbar.classList.remove('is-open');
    if (navToggle) navToggle.setAttribute('aria-expanded', 'false');
  };

  const closeUserMenu = () => {
    if (!navUser) return;
    navUser.classList.remove('is-open');
    if (navUserBtn) navUserBtn.setAttribute('aria-expanded', 'false');
  };

  if (navToggle) navToggle.addEventListener('click', () => {
    if (!navbar) return;
    const isOpen = navbar.classList.toggle('is-open');
    navToggle.setAttribute('aria-expanded', String(isOpen));
    if (!isOpen) closeUserMenu();
  });

  if (navUserBtn) navUserBtn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (!navUser) return;
    const isOpen = navUser.classList.toggle('is-open');
    navUserBtn.setAttribute('aria-expanded', String(isOpen));
  });

  if (navUserMenu) navUserMenu.addEventListener('click', (e) => {
    // Allow clicking inside the menu without triggering outside-close.
    e.stopPropagation();
    const target = e.target;
    if (target && target.tagName === 'A') {
      closeUserMenu();
      closeNavMenu();
    }
  });

  if (navLinks) navLinks.addEventListener('click', (e) => {
    const target = e.target;
    if (target && target.tagName === 'A') {
      closeNavMenu();
    }
  });

  document.addEventListener('click', (e) => {
    const target = e.target;
    if (navUser && target && !navUser.contains(target)) closeUserMenu();
    if (navbar && navToggle && target && !navToggle.contains(target) && navLinks && !navLinks.contains(target)) {
      closeNavMenu();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeNavMenu();
      closeUserMenu();
    }
  });

  // ── Image gallery (listing detail) ──────────────────────
  const thumbs = document.querySelectorAll('.thumb');
  const mainImg = document.getElementById('main-img');

  thumbs.forEach(thumb => {
    thumb.addEventListener('click', () => {
      thumbs.forEach(t => t.classList.remove('active'));
      thumb.classList.add('active');
      const src = thumb.dataset.src;
      if (mainImg) {
        if (mainImg.tagName === 'IMG') {
          mainImg.src = src;
        } else {
          mainImg.innerHTML = `<img src="${src}" alt="Product image" style="width:100%;height:100%;object-fit:cover">`;
        }
      }
    });
  });

  // ── Tabs (profile, admin) ────────────────────────────────
  const tabBtns = document.querySelectorAll('.tab-btn');
  const tabPanes = document.querySelectorAll('.tab-pane');

  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      tabBtns.forEach(b => b.classList.remove('active'));
      tabPanes.forEach(p => p.style.display = 'none');
      btn.classList.add('active');
      const target = document.getElementById('tab-' + btn.dataset.tab);
      if (target) target.style.display = 'block';
    });
  });

  // Show first tab by default
  if (tabBtns.length) {
    tabBtns[0].click();
  }

  // ── Modal helpers ────────────────────────────────────────
  document.querySelectorAll('[data-modal-open]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.modalOpen;
      const modal = document.getElementById(id);
      if (modal) modal.style.display = 'flex';
    });
  });

  document.querySelectorAll('[data-modal-close], .modal-overlay').forEach(el => {
    el.addEventListener('click', (e) => {
      if (e.target === el) {
        const overlay = el.closest('.modal-overlay') || el;
        overlay.style.display = 'none';
      }
    });
  });

  // ── Image preview on file input ──────────────────────────
  const imageInput = document.getElementById('images');
  const previewBox = document.getElementById('image-previews');
  if (imageInput && previewBox) {
    imageInput.addEventListener('change', () => {
      previewBox.innerHTML = '';
      Array.from(imageInput.files).slice(0, 5).forEach(file => {
        const url = URL.createObjectURL(file);
        const img = document.createElement('img');
        img.src = url;
        img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid #e0e4ea';
        previewBox.appendChild(img);
      });
    });
  }

  // ── Auto-scroll chat to bottom ───────────────────────────
  const chatMessages = document.getElementById('chat-messages');
  if (chatMessages) {
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  // ── Chat: send on Enter (not Shift+Enter) ────────────────
  const chatTextarea = document.getElementById('chat-input');
  if (chatTextarea) {
    chatTextarea.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        chatTextarea.closest('form').submit();
      }
    });
  }

  // ── Auto-dismiss alerts after 4 seconds ──────────────────
  document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
      alert.style.transition = 'opacity .5s';
      alert.style.opacity = '0';
      setTimeout(() => alert.remove(), 500);
    }, 4000);
  });

  // ── Confirm on dangerous buttons ────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', (e) => {
      if (!confirm(btn.dataset.confirm)) e.preventDefault();
    });
  });

});
