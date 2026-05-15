/* ============================================
   WorkPods - scripts.js
   Form validation, UI interactions, dynamic pricing
   ============================================ */

"use strict";

/* ── DOM Ready ─────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initForms();
  initFileUpload();
  initTimeSlots();
  initReservationPricing();
  initNavbarScroll();
  initAlertDismiss();
  initAdminCharts();
});

/* ── Navbar scroll effect ──────────────────── */
function initNavbarScroll() {
  const navbar = document.querySelector('.navbar');
  if (!navbar) return;
  window.addEventListener('scroll', () => {
    navbar.style.background = window.scrollY > 40
      ? 'rgba(13,15,18,0.97)'
      : 'rgba(13,15,18,0.85)';
  });
}

/* ── Auto-dismiss alerts ───────────────────── */
function initAlertDismiss() {
  document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el => {
    setTimeout(() => el.remove(), 4000);
  });
}

/* ═══════════════════════════════════════════
   FORM VALIDATION
═══════════════════════════════════════════ */
const validators = {
  required: (val) => val.trim() !== '' || 'Ce champ est obligatoire.',
  email:    (val) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) || 'Email invalide.',
  minLen:   (n) => (val) => val.length >= n || `Minimum ${n} caractères.`,
  maxLen:   (n) => (val) => val.length <= n || `Maximum ${n} caractères.`,
  password: (val) => /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/.test(val)
    || 'Au moins 8 caractères, une majuscule, une minuscule et un chiffre.',
  positiveNum: (val) => (!isNaN(val) && parseFloat(val) > 0) || 'Doit être un nombre positif.',
  phone:    (val) => /^[+\d\s\-()]{8,15}$/.test(val) || 'Numéro de téléphone invalide.',
};

function validate(input) {
  const rules  = (input.dataset.validate || '').split('|').filter(Boolean);
  const label  = input.dataset.label || 'Ce champ';
  let   error  = null;

  for (const rule of rules) {
    const [name, arg] = rule.split(':');
    const fn = name === 'minLen' ? validators.minLen(+arg)
             : name === 'maxLen' ? validators.maxLen(+arg)
             : validators[name];
    if (!fn) continue;
    const result = fn(input.value);
    if (result !== true) { error = result; break; }
  }

  // Confirm password check
  if (input.dataset.matchField) {
    const target = document.getElementById(input.dataset.matchField);
    if (target && input.value !== target.value) {
      error = 'Les mots de passe ne correspondent pas.';
    }
  }

  const errEl = document.getElementById(`err-${input.id}`);
  if (error) {
    input.classList.add('is-invalid');
    input.classList.remove('is-valid');
    if (errEl) { errEl.textContent = error; errEl.classList.add('show'); }
    return false;
  } else {
    input.classList.remove('is-invalid');
    input.classList.add('is-valid');
    if (errEl) errEl.classList.remove('show');
    return true;
  }
}

function initForms() {
  document.querySelectorAll('[data-validate-form]').forEach(form => {
    const inputs = form.querySelectorAll('[data-validate]');

    // Live validation on blur
    inputs.forEach(input => {
      input.addEventListener('blur', () => validate(input));
      input.addEventListener('input', () => {
        if (input.classList.contains('is-invalid')) validate(input);
      });
    });

    // Submit validation
    form.addEventListener('submit', (e) => {
      let valid = true;
      inputs.forEach(input => { if (!validate(input)) valid = false; });
      if (!valid) {
        e.preventDefault();
        const firstInvalid = form.querySelector('.is-invalid');
        firstInvalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      // Show loading state on submit button
      const btn = form.querySelector('[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner"></span> Chargement...`;
      }
    });
  });
}

/* ═══════════════════════════════════════════
   FILE UPLOAD PREVIEW
═══════════════════════════════════════════ */
function initFileUpload() {
  const area    = document.querySelector('.file-upload-area');
  const input   = document.getElementById('pod-image') || document.getElementById('profile-photo');
  const preview = document.getElementById('img-preview');
  if (!area || !input || !preview) return;

  ['dragenter','dragover'].forEach(ev => {
    area.addEventListener(ev, (e) => { e.preventDefault(); area.classList.add('drag-over'); });
  });
  ['dragleave','drop'].forEach(ev => {
    area.addEventListener(ev, (e) => { e.preventDefault(); area.classList.remove('drag-over'); });
  });
  area.addEventListener('drop', (e) => {
    const file = e.dataTransfer.files[0];
    if (file) showPreview(file);
  });

  input.addEventListener('change', () => {
    if (input.files[0]) showPreview(input.files[0]);
  });

  function showPreview(file) {
    if (!file.type.startsWith('image/')) {
      showAlert('Veuillez sélectionner une image.', 'danger');
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      showAlert('Image trop grande (max 5 Mo).', 'danger');
      return;
    }
    const reader = new FileReader();
    reader.onload = (e) => {
      preview.src = e.target.result;
      preview.style.display = 'block';
    };
    reader.readAsDataURL(file);
  }
}

/* ═══════════════════════════════════════════
   TIME SLOTS SELECTION
═══════════════════════════════════════════ */
function initTimeSlots() {
  const slots   = document.querySelectorAll('.time-slot:not(.disabled)');
  const hiddenInput = document.getElementById('selected_time');
  if (!slots.length) return;

  slots.forEach(slot => {
    slot.addEventListener('click', () => {
      slots.forEach(s => s.classList.remove('selected'));
      slot.classList.add('selected');
      if (hiddenInput) hiddenInput.value = slot.dataset.time;
      updateSummary();
    });
  });
}

/* ═══════════════════════════════════════════
   RESERVATION DYNAMIC PRICING
═══════════════════════════════════════════ */
function initReservationPricing() {
  const durationSelect = document.getElementById('duration');
  const pricePerHour   = parseFloat(document.getElementById('price-per-hour')?.dataset.price || 0);
  if (!durationSelect || !pricePerHour) return;

  durationSelect.addEventListener('change', updateSummary);
  updateSummary();
}

function updateSummary() {
  const durationEl   = document.getElementById('duration');
  const priceEl      = document.getElementById('price-per-hour');
  const totalEl      = document.getElementById('summary-total');
  const summaryDurEl = document.getElementById('summary-duration');
  const summaryTimeEl= document.getElementById('summary-time');
  const selectedSlot = document.querySelector('.time-slot.selected');

  if (!durationEl || !priceEl || !totalEl) return;

  const hours    = parseFloat(durationEl.value) || 0;
  const price    = parseFloat(priceEl.dataset.price) || 0;
  const total    = (hours * price).toFixed(2);

  if (totalEl)      totalEl.textContent      = `${total} DT`;
  if (summaryDurEl) summaryDurEl.textContent = hours > 0 ? `${hours}h` : '—';
  if (summaryTimeEl && selectedSlot) summaryTimeEl.textContent = selectedSlot.dataset.time;
}

/* ═══════════════════════════════════════════
   ADMIN CHARTS (Chart.js)
═══════════════════════════════════════════ */
function initAdminCharts() {
  const reservationsCtx = document.getElementById('reservationsChart');
  const revenueCtx      = document.getElementById('revenueChart');

  if (reservationsCtx && typeof Chart !== 'undefined') {
    const labels = reservationsCtx.dataset.labels
      ? JSON.parse(reservationsCtx.dataset.labels) : [];
    const data   = reservationsCtx.dataset.values
      ? JSON.parse(reservationsCtx.dataset.values) : [];

    new Chart(reservationsCtx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Réservations',
          data,
          backgroundColor: 'rgba(108,99,255,0.6)',
          borderColor: 'rgba(108,99,255,1)',
          borderWidth: 2,
          borderRadius: 8,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { color: '#6b7280' }, grid: { color: '#2a2f3e' } },
          x: { ticks: { color: '#6b7280' }, grid: { display: false } }
        }
      }
    });
  }

  if (revenueCtx && typeof Chart !== 'undefined') {
    const labels = revenueCtx.dataset.labels
      ? JSON.parse(revenueCtx.dataset.labels) : [];
    const data   = revenueCtx.dataset.values
      ? JSON.parse(revenueCtx.dataset.values) : [];

    new Chart(revenueCtx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Revenus (DT)',
          data,
          borderColor: 'rgba(167,139,250,1)',
          backgroundColor: 'rgba(167,139,250,0.1)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: 'rgba(167,139,250,1)',
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { ticks: { color: '#6b7280' }, grid: { color: '#2a2f3e' } },
          x: { ticks: { color: '#6b7280' }, grid: { display: false } }
        }
      }
    });
  }
}

/* ═══════════════════════════════════════════
   UTILITY HELPERS
═══════════════════════════════════════════ */
function showAlert(message, type = 'info') {
  const existing = document.querySelector('.js-alert');
  if (existing) existing.remove();

  const div = document.createElement('div');
  div.className = `alert alert-${type} js-alert`;
  div.textContent = message;

  const container = document.querySelector('.container, .auth-card, .admin-main');
  if (container) container.prepend(div);
  setTimeout(() => div.remove(), 4000);
}

// Delete confirmation
document.querySelectorAll('[data-confirm]').forEach(btn => {
  btn.addEventListener('click', (e) => {
    if (!confirm(btn.dataset.confirm || 'Êtes-vous sûr ?')) e.preventDefault();
  });
});

// Auto-hide flash messages
setTimeout(() => {
  document.querySelectorAll('.flash-msg').forEach(el => {
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 400);
  });
}, 3000);
