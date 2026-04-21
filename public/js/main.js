/**
 * EVOLVE HR - Main JavaScript
 * Handles navigation, modals, and page interactions
 */

// Auto-detect base path so the app works at any subfolder (e.g. /proposed/)
var BASE_PATH = (function () {
  var scripts = document.getElementsByTagName('script');
  for (var i = 0; i < scripts.length; i++) {
    var src = scripts[i].src;
    if (src && src.indexOf('/js/main.js') !== -1) {
      return src.replace(/\/js\/main\.js.*$/, '');
    }
  }
  return '';
})();

document.addEventListener('DOMContentLoaded', function () {
  var token = localStorage.getItem('authToken');

  // If logged-in user lands on the public homepage, send them straight to dashboard
  if (token && document.body.classList.contains('landing')) {
    window.location.href = BASE_PATH + '/dashboard.html';
    return;
  }

  if (token) updateNavForLoggedInUser();

  // Scroll-based header — only on pages that have a full-screen hero banner
  var header = document.getElementById('header');
  var banner = document.getElementById('banner');
  if (header && banner) {
    var onScroll = function () {
      var bannerH = banner.offsetHeight || window.innerHeight;
      if (window.scrollY > bannerH * 0.25) {
        header.classList.remove('alt');
      } else {
        header.classList.add('alt');
      }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  // Mobile menu toggle
  var menuToggle = document.querySelector('.menuToggle');
  var menu       = document.getElementById('menu');
  if (menuToggle && menu) {
    menuToggle.addEventListener('click', function (e) {
      e.preventDefault();
      menu.classList.toggle('is-active');
    });
    menu.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () { menu.classList.remove('is-active'); });
    });
    document.addEventListener('click', function (e) {
      if (menu.classList.contains('is-active') &&
          !menu.contains(e.target) &&
          e.target !== menuToggle) {
        menu.classList.remove('is-active');
      }
    });
  }

  setTimeout(function () { document.body.classList.remove('is-preload'); }, 100);
});

function goToAuth() {
  var token = localStorage.getItem('authToken');
  if (token) {
    window.location.href = BASE_PATH + '/dashboard.html';
  } else {
    openAuthModal();
  }
}

function openAuthModal() {
  var modal = document.getElementById('auth-modal');
  if (modal) modal.style.display = 'flex';
}

function closeAuthModal() {
  var modal = document.getElementById('auth-modal');
  if (modal) modal.style.display = 'none';
}

function switchTab(tab) {
  document.querySelectorAll('.auth-tab').forEach(function (t) {
    t.classList.remove('active');
  });
  var selectedTab = document.getElementById(tab + '-tab');
  if (selectedTab) selectedTab.classList.add('active');
}

function updateNavForLoggedInUser() {
  var dashboardUrl = BASE_PATH + '/dashboard.html';

  // Desktop "Log In" button → "Dashboard"
  var desktopBtn = document.getElementById('nav-login-btn');
  if (desktopBtn) {
    desktopBtn.textContent = 'Dashboard';
    desktopBtn.href = dashboardUrl;
    desktopBtn.onclick = null;
  }

  // Mobile menu "Log In" link → "Dashboard"
  var mobileBtn = document.getElementById('nav-login-btn-mobile');
  if (mobileBtn) {
    mobileBtn.textContent = 'Dashboard';
    mobileBtn.href = dashboardUrl;
    mobileBtn.onclick = null;
  }

  // "Home" nav links on public pages → dashboard for logged-in users
  document.querySelectorAll('#nav a').forEach(function (a) {
    var href = a.getAttribute('href') || '';
    if (href === 'index.html' || href === '#banner') {
      a.href = dashboardUrl;
    }
  });
  document.querySelectorAll('#menu a').forEach(function (a) {
    var href = a.getAttribute('href') || '';
    if (href === 'index.html' || href === '#banner') {
      a.href = dashboardUrl;
    }
  });
}

// Close auth modal when clicking the dark overlay (outside the box)
document.addEventListener('click', function (event) {
  var modal = document.getElementById('auth-modal');
  if (modal && event.target === modal) closeAuthModal();
});

function showNotification(message, type) {
  type = type || 'success';
  var notification = document.createElement('div');
  notification.className = 'notification notification-' + type;
  notification.textContent = message;
  notification.style.cssText = [
    'position:fixed', 'top:24px', 'right:24px', 'padding:16px 24px',
    'background:' + (type === 'success' ? '#4caf50' : '#f44336'),
    'color:white', 'border-radius:6px', 'z-index:99999',
    'font-size:15px', 'font-weight:600', 'max-width:360px',
    'box-shadow:0 4px 20px rgba(0,0,0,0.35)',
    'animation:notifySlideIn 0.3s ease-out'
  ].join(';');

  document.body.appendChild(notification);

  setTimeout(function () {
    notification.style.animation = 'notifySlideOut 0.3s ease-out';
    setTimeout(function () { notification.remove(); }, 300);
  }, 3000);
}

var style = document.createElement('style');
style.textContent = [
  '@keyframes slideIn{from{transform:translateX(400px);opacity:0}to{transform:translateX(0);opacity:1}}',
  '@keyframes slideOut{from{transform:translateX(0);opacity:1}to{transform:translateX(400px);opacity:0}}'
].join('');
document.head.appendChild(style);
