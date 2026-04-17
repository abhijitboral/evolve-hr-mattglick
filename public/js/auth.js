/**
 * EVOLVE HR - Authentication JavaScript
 * Handles login, registration, and token management
 */

// Auto-detect base path so the app works at any subfolder (e.g. /proposed/)
var BASE_PATH = (function () {
  var scripts = document.getElementsByTagName('script');
  for (var i = 0; i < scripts.length; i++) {
    var src = scripts[i].src;
    if (src && src.indexOf('/js/auth.js') !== -1) {
      // src = http://domain.com/proposed/js/auth.js  →  base = /proposed
      return src.replace(/\/js\/auth\.js.*$/, '');
    }
  }
  return '';
})();

var API_URL = BASE_PATH;

// Login Form Handler
var loginForm = document.getElementById('login-form');
if (loginForm) {
  loginForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    var email    = document.getElementById('login-email').value;
    var password = document.getElementById('login-password').value;

    try {
      var response = await fetch(API_URL + '/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });

      var data = await response.json();

      if (!response.ok) throw new Error(data.error || 'Login failed');

      localStorage.setItem('authToken', data.token);
      localStorage.setItem('user', JSON.stringify(data.user));

      showNotification('Login successful! Redirecting to dashboard...');

      setTimeout(function () {
        window.location.href = BASE_PATH + '/dashboard.html';
      }, 1500);

    } catch (error) {
      console.error('Login error:', error);
      showNotification(error.message || 'Login failed. Please try again.', 'error');
    }
  });
}

// Register Form Handler
var registerForm = document.getElementById('register-form');
if (registerForm) {
  registerForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    var firstName = document.getElementById('register-firstname').value;
    var lastName  = document.getElementById('register-lastname').value;
    var email     = document.getElementById('register-email').value;
    var phone     = document.getElementById('register-phone').value;
    var company   = document.getElementById('register-company').value;
    var password  = document.getElementById('register-password').value;

    try {
      var response = await fetch(API_URL + '/api/auth/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password, firstName, lastName, company, phone })
      });

      var data = await response.json();

      if (!response.ok) throw new Error(data.error || 'Registration failed');

      localStorage.setItem('authToken', data.token);
      localStorage.setItem('user', JSON.stringify(data.user));

      showNotification('Account created! Redirecting to dashboard...');

      setTimeout(function () {
        window.location.href = BASE_PATH + '/dashboard.html';
      }, 1500);

    } catch (error) {
      console.error('Registration error:', error);
      showNotification(error.message || 'Registration failed. Please try again.', 'error');
    }
  });
}

// Contact Form Handler
var contactForm = document.getElementById('contact-form');
if (contactForm) {
  contactForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    var name    = document.getElementById('contact-name').value;
    var email   = document.getElementById('contact-email').value;
    var phone   = document.getElementById('contact-phone').value;
    var company = document.getElementById('contact-company').value;
    var subject = document.getElementById('contact-subject').value;
    var message = document.getElementById('contact-message').value;

    try {
      var response = await fetch(API_URL + '/api/contact', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, phone, company, subject, message })
      });

      var data = await response.json();

      if (!response.ok) throw new Error(data.error || 'Message failed to send');

      var messageDiv = document.getElementById('contact-message-div');
      messageDiv.style.display     = 'block';
      messageDiv.style.background  = '#d4edda';
      messageDiv.style.color       = '#155724';
      messageDiv.style.borderLeft  = '4px solid #28a745';
      messageDiv.textContent       = data.message;

      contactForm.reset();
      messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    } catch (error) {
      console.error('Contact form error:', error);
      var messageDiv = document.getElementById('contact-message-div');
      messageDiv.style.display    = 'block';
      messageDiv.style.background = '#f8d7da';
      messageDiv.style.color      = '#721c24';
      messageDiv.style.borderLeft = '4px solid #f5c6cb';
      messageDiv.textContent      = error.message || 'Failed to send message. Please try again.';
    }
  });
}

function getAuthHeader() {
  return {
    'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
    'Content-Type': 'application/json'
  };
}

function isAuthenticated() {
  return !!localStorage.getItem('authToken');
}

function getCurrentUser() {
  var user = localStorage.getItem('user');
  return user ? JSON.parse(user) : null;
}

function logout() {
  localStorage.removeItem('authToken');
  localStorage.removeItem('user');
  window.location.href = BASE_PATH + '/';
}

function requireAuth() {
  if (!isAuthenticated()) {
    window.location.href = BASE_PATH + '/';
  }
}
