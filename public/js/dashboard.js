/**
 * EVOLVE HR - Dashboard JavaScript
 * Handles ticket management, user interactions, and data fetching
 */

// Auto-detect base path so the app works at any subfolder (e.g. /proposed/)
var BASE_PATH = (function () {
  var scripts = document.getElementsByTagName('script');
  for (var i = 0; i < scripts.length; i++) {
    var src = scripts[i].src;
    if (src && src.indexOf('/js/dashboard.js') !== -1) {
      return src.replace(/\/js\/dashboard\.js.*$/, '');
    }
  }
  return '';
})();

var API_URL = BASE_PATH;

document.addEventListener('DOMContentLoaded', function () {
  requireAuth();
  initDashboard();
});

async function initDashboard() {
  var user = getCurrentUser();

  document.getElementById('user-name').textContent    = user.name;
  document.getElementById('profile-name').textContent = user.name;
  document.getElementById('profile-email').textContent = user.email;

  try {
    var res = await fetch(API_URL + '/api/auth/profile', { headers: getAuthHeader() });
    if (res.ok) {
      var data = await res.json();
      if (data.user && data.user.name) {
        document.getElementById('user-name').textContent    = data.user.name;
        document.getElementById('profile-name').textContent = data.user.name;
        document.getElementById('profile-email').textContent = data.user.email;
        localStorage.setItem('user', JSON.stringify(data.user));
        if (data.token) localStorage.setItem('authToken', data.token);
      }
    }
  } catch (e) {
    // HubSpot unavailable — cached values already showing
  }

  await loadTickets();
  setupFormHandlers();
}

async function loadTickets(silent) {
  silent = silent || false;
  var container = document.getElementById('tickets-container');

  if (!silent) {
    container.innerHTML = '<div class="loading">Loading tickets...</div>';
  }

  try {
    var response = await fetch(API_URL + '/api/tickets', { headers: getAuthHeader() });

    if (!response.ok) throw new Error('Failed to load tickets');

    var data = await response.json();

    if (data.count === 0) {
      if (silent && container.querySelector('.ticket-card-new')) return [];
      container.innerHTML = '<div class="empty-state" style="grid-column:1/-1;text-align:center;padding:3rem;color:#999;">You have no tickets yet. <a href="javascript:void(0)" onclick="showSection(\'new-ticket\', null)" style="color:#e94560;">Create one</a></div>';
      return [];
    }

    container.innerHTML = data.tickets.map(function (ticket) {
      return '<div class="ticket-card" data-status="' + ticket.status.toLowerCase() + '" onclick="viewTicket(\'' + ticket.id + '\')">' +
        '<div class="ticket-id">#' + ticket.ticketId + '</div>' +
        '<div class="ticket-subject">' + escapeHtml(ticket.subject) + '</div>' +
        '<div class="ticket-meta">' +
          '<span class="ticket-status ' + ticket.status.toLowerCase() + '">' + formatStatus(ticket.status) + '</span>' +
          '<span class="ticket-date">' + ticket.created + '</span>' +
        '</div>' +
        '<div class="ticket-date">Priority: ' + ticket.priority + '</div>' +
      '</div>';
    }).join('');

    return data.tickets;

  } catch (error) {
    console.error('Error loading tickets:', error);
    if (!silent) {
      container.innerHTML = '<div class="loading" style="color:#f44336;">Error loading tickets. Please try again.</div>';
    }
    return null;
  }
}

function prependTicketCard(ticket) {
  var container = document.getElementById('tickets-container');
  var loading   = container.querySelector('.loading');
  var empty     = container.querySelector('.empty-state');
  if (loading || empty) container.innerHTML = '';

  var card = document.createElement('div');
  card.className = 'ticket-card ticket-card-new';
  card.setAttribute('data-status', (ticket.status || 'new').toLowerCase());
  if (ticket.id) {
    card.setAttribute('data-ticket-id', ticket.id);
    card.setAttribute('onclick', 'viewTicket(\'' + ticket.id + '\')');
  }
  card.innerHTML =
    '<div class="ticket-id">' + (ticket.ticketId ? '#' + ticket.ticketId : '') + '</div>' +
    '<div class="ticket-subject">' + escapeHtml(ticket.subject) + '</div>' +
    '<div class="ticket-meta">' +
      '<span class="ticket-status new">' + formatStatus(ticket.status) + '</span>' +
      '<span class="ticket-date">' + ticket.created + '</span>' +
    '</div>' +
    '<div class="ticket-date">Priority: ' + ticket.priority + '</div>';

  container.insertBefore(card, container.firstChild);
}

async function viewTicket(ticketId) {
  var modal   = document.getElementById('ticket-modal');
  var content = document.getElementById('ticket-detail-content');

  try {
    var response = await fetch(API_URL + '/api/tickets/' + ticketId, { headers: getAuthHeader() });

    if (!response.ok) throw new Error('Failed to load ticket');

    var data   = await response.json();
    var ticket = data.ticket;

    content.innerHTML =
      '<div class="ticket-detail">' +
        '<div class="detail-header">' +
          '<div><h2>' + escapeHtml(ticket.subject) + '</h2><p class="ticket-id">#' + ticket.ticketId + '</p></div>' +
          '<span class="ticket-status ' + ticket.status.toLowerCase() + '">' + formatStatus(ticket.status) + '</span>' +
        '</div>' +
        '<div class="detail-info">' +
          '<div class="info-row"><span class="label">Status:</span><span>' + formatStatus(ticket.status) + '</span></div>' +
          '<div class="info-row"><span class="label">Priority:</span><span>' + ticket.priority + '</span></div>' +
          '<div class="info-row"><span class="label">Created:</span><span>' + ticket.created + '</span></div>' +
          '<div class="info-row"><span class="label">Updated:</span><span>' + ticket.updated + '</span></div>' +
        '</div>' +
        '<div class="detail-description"><h3>Description</h3><p>' + escapeHtml(ticket.description).replace(/\n/g, '<br>') + '</p></div>' +
        '<div class="detail-actions"><button class="btn-primary" onclick="closeTicketModal()">Close</button></div>' +
      '</div>';

    modal.style.display = 'flex';

  } catch (error) {
    console.error('Error loading ticket:', error);
    content.innerHTML = '<div class="loading" style="color:#f44336;">Error loading ticket details.</div>';
    modal.style.display = 'flex';
  }
}

function closeTicketModal() {
  document.getElementById('ticket-modal').style.display = 'none';
}

async function refreshTickets() {
  var btn = document.getElementById('refresh-btn');
  btn.disabled    = true;
  btn.textContent = '⏳ Refreshing...';
  await loadTickets();
  btn.disabled    = false;
  btn.textContent = '↻ Refresh';
}

function setupFormHandlers() {
  var newTicketForm = document.getElementById('new-ticket-form');
  if (!newTicketForm) return;

  var submitting = false;

  newTicketForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (submitting) return;
    submitting = true;

    var submitBtn    = newTicketForm.querySelector('button[type="submit"]');
    var originalText = submitBtn.textContent;
    submitBtn.disabled    = true;
    submitBtn.textContent = 'Submitting...';

    var subject     = document.getElementById('ticket-subject').value;
    var description = document.getElementById('ticket-description').value;
    var priority    = document.getElementById('ticket-priority').value;
    var msgBox      = document.getElementById('ticket-form-message');

    msgBox.style.display = 'none';
    msgBox.className     = '';

    try {
      var response = await fetch(API_URL + '/api/tickets', {
        method: 'POST',
        headers: getAuthHeader(),
        body: JSON.stringify({ subject, description, priority })
      });

      var data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Failed to create ticket');

      var optimisticTicket = {
        id:       data.ticket && data.ticket.id ? data.ticket.id : null,
        ticketId: data.ticket && data.ticket.ticketId ? data.ticket.ticketId : null,
        subject:  subject,
        status:   'new',
        priority: priority.toUpperCase(),
        created:  new Date().toLocaleDateString()
      };

      msgBox.textContent  = 'Ticket submitted successfully! Redirecting to My Tickets...';
      msgBox.className    = 'form-message form-message-success';
      msgBox.style.display = 'block';

      setTimeout(async function () {
        try {
          newTicketForm.reset();
          msgBox.style.display = 'none';

          document.querySelectorAll('.content-section').forEach(function (s) { s.classList.remove('active'); });
          document.getElementById('tickets-section').classList.add('active');
          document.querySelectorAll('.nav-item').forEach(function (item) {
            item.classList.remove('active');
            if (item.getAttribute('onclick') && item.getAttribute('onclick').indexOf("'tickets'") !== -1) {
              item.classList.add('active');
            }
          });

          prependTicketCard(optimisticTicket);

          var existing = await loadTickets(true);
          if (existing && optimisticTicket.id &&
              existing.some(function (t) { return t.id === String(optimisticTicket.id); })) {
            await loadTickets();
          }
        } catch (err) {
          console.error('Redirect error:', err);
          var container = document.getElementById('tickets-container');
          if (optimisticTicket.id && !container.querySelector('[data-ticket-id="' + optimisticTicket.id + '"]')) {
            prependTicketCard(optimisticTicket);
          }
        }
      }, 2000);

    } catch (error) {
      console.error('Error creating ticket:', error);
      msgBox.textContent   = error.message || 'Failed to create ticket. Please try again.';
      msgBox.className     = 'form-message form-message-error';
      msgBox.style.display = 'block';
      submitting           = false;
      submitBtn.disabled   = false;
      submitBtn.textContent = originalText;
      return;
    }

    submitting            = false;
    submitBtn.disabled    = false;
    submitBtn.textContent = originalText;
  });
}

function showSection(section, clickedEl) {
  // Hide all content sections
  document.querySelectorAll('.content-section').forEach(function (s) { s.classList.remove('active'); });

  var sectionId       = section === 'tickets' ? 'tickets-section' : section + '-section';
  var selectedSection = document.getElementById(sectionId);
  if (selectedSection) selectedSection.classList.add('active');

  // Update active nav item
  document.querySelectorAll('.nav-item').forEach(function (item) { item.classList.remove('active'); });
  var navItem = clickedEl ? clickedEl.closest('.nav-item') : null;
  if (navItem) {
    navItem.classList.add('active');
  } else {
    // Fallback: match by section name in onclick attribute
    document.querySelectorAll('.nav-item').forEach(function (item) {
      var oc = item.getAttribute('onclick') || '';
      if (oc.indexOf("'" + section + "'") !== -1) {
        item.classList.add('active');
      }
    });
  }

  if (section === 'tickets') loadTickets();
}

function filterTickets() {
  var filter  = document.getElementById('status-filter').value;
  var tickets = document.querySelectorAll('.ticket-card');
  tickets.forEach(function (ticket) {
    if (!filter) {
      ticket.style.display = '';
    } else {
      ticket.style.display = (ticket.getAttribute('data-status') || '') === filter ? '' : 'none';
    }
  });
}

function formatStatus(status) {
  var map = { new: 'New', in_progress: 'In Progress', waiting_customer: 'Waiting', closed: 'Closed' };
  return map[status] || status;
}

function escapeHtml(text) {
  var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
  return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
}

/* Ticket detail styles */
var ticketDetailStyles = document.createElement('style');
ticketDetailStyles.textContent = [
  '.ticket-detail{animation:fadeIn .3s ease-out}',
  '.detail-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:2rem;border-bottom:2px solid #e94560;padding-bottom:1rem}',
  '.detail-header h2{font-size:1.8rem;color:#1a1a2e;margin-bottom:.5rem}',
  '.detail-header .ticket-id{color:#999;font-size:.9rem}',
  '.detail-info{background:#f5f5f5;padding:1.5rem;border-radius:4px;margin-bottom:1.5rem}',
  '.info-row{display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid #ddd}',
  '.info-row:last-child{border-bottom:none}',
  '.info-row .label{font-weight:600;color:#1a1a2e}',
  '.detail-description{margin-bottom:2rem}',
  '.detail-description h3{margin-bottom:1rem;color:#1a1a2e}',
  '.detail-description p{color:#666;line-height:1.8;white-space:pre-wrap;word-break:break-word}',
  '.detail-actions{display:flex;gap:1rem;justify-content:flex-end;padding-top:1rem;border-top:1px solid #ddd}',
  '.empty-state{background:white;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1)}',
  '.empty-state a{text-decoration:none;font-weight:600}',
  '.empty-state a:hover{text-decoration:underline}'
].join('');
document.head.appendChild(ticketDetailStyles);
