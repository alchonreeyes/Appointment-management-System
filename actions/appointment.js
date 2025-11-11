// appointment.js — single-file, no duplicate declarations
(function () {
  'use strict';

  // Wait for DOM
  document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Progress / Multi-step form ---------- */
    const steps = Array.from(document.querySelectorAll('.form-step'));
    const nextBtns = Array.from(document.querySelectorAll('.next-btn'));
    const prevBtns = Array.from(document.querySelectorAll('.prev-btn'));
    const progressSteps = Array.from(document.querySelectorAll('.progress-step'));
    const progressLines = Array.from(document.querySelectorAll('.progress-line'));
    let formStepIndex = 0;

    function updateProgress(stepIndex) {
      progressSteps.forEach((step, i) => {
        step.classList.toggle('completed', i < stepIndex);
        step.classList.toggle('active', i === stepIndex);
        if (i >= stepIndex) step.classList.remove('completed');
      });
      progressLines.forEach((line, i) => {
        line.classList.toggle('completed', i < stepIndex);
      });
    }

    function showStep(index) {
      steps.forEach((s, i) => s.classList.toggle('active', i === index));
      updateProgress(index);
    }

    nextBtns.forEach(btn => btn.addEventListener('click', () => {
      if (formStepIndex < steps.length - 1) {
        formStepIndex++;
        showStep(formStepIndex);
      }
    }));
    prevBtns.forEach(btn => btn.addEventListener('click', () => {
      if (formStepIndex > 0) {
        formStepIndex--;
        showStep(formStepIndex);
      }
    }));
    showStep(formStepIndex);

    /* ---------- DOM elements used by booking logic ---------- */
    const timeButtons = Array.from(document.querySelectorAll('.time-slots button'));
    const nextAvailableEl = document.getElementById('nextAvailable');
    const appointmentTimeInput = document.getElementById('appointmentTime');

    // Multi-date inputs (3-day booking)
    const multiDate1 = document.getElementById('multiDate1');
    const multiDate2 = document.getElementById('multiDate2');
    const multiDate3 = document.getElementById('multiDate3');
    const appointmentDatesJson = document.getElementById('appointment_dates_json');

    // If any of these elements are missing, code still runs safely
    const dateInputs = [multiDate1, multiDate2, multiDate3].filter(Boolean);

    /* ---------- Slot availability display element ---------- */
    let remainingDisplay = document.getElementById('remainingSlots');
    if (!remainingDisplay) {
      remainingDisplay = document.createElement('div');
      remainingDisplay.id = 'remainingSlots';
      remainingDisplay.style.fontWeight = '700';
      remainingDisplay.style.marginTop = '10px';
      // append after time-slots if present, else append to form
      const timeSlotsContainer = document.querySelector('.time-slots');
      if (timeSlotsContainer) timeSlotsContainer.after(remainingDisplay);
      else {
        const form = document.getElementById('appointmentForm') || document.querySelector('form');
        if (form) form.appendChild(remainingDisplay);
      }
    }

    /* ---------- State ---------- */
    let selectedDates = [];
    let selectedTime = '';

    /* ---------- Helpers ---------- */
    function setHiddenDates() {
      if (appointmentDatesJson) {
        appointmentDatesJson.value = JSON.stringify(selectedDates);
      }
    }

    function updateNextAvailableText() {
      if (!nextAvailableEl) return;
      if (selectedDates.length && selectedTime) {
        nextAvailableEl.textContent = `Selected Dates: ${selectedDates.join(', ')} at ${selectedTime}`;
      } else if (selectedDates.length) {
        nextAvailableEl.textContent = `Selected Dates: ${selectedDates.join(', ')}`;
      } else {
        nextAvailableEl.textContent = 'Please select 3 dates and a time.';
      }
    }

    async function fetchSlotsForDate(date) {
      // Sends appointment_date to check_slots.php
      try {
        const body = new URLSearchParams({ appointment_date: date });
        const resp = await fetch('../actions/check_slots.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: body.toString()
        });
        const data = await resp.json();
        return data; // { success: true, remaining: n, ... } per your check_slots.php
      } catch (err) {
        console.error('Error fetching slots for', date, err);
        return { success: false, message: 'Network/server error' };
      }
    }

    async function checkAllSelectedDates() {
      if (selectedDates.length === 0) {
        remainingDisplay.textContent = '';
        return { ok: false, message: 'No dates selected' };
      }
      remainingDisplay.textContent = 'Checking slot availability...';
      remainingDisplay.style.color = '#6b7280';

      for (const dt of selectedDates) {
        const r = await fetchSlotsForDate(dt);
        if (!r.success) {
          remainingDisplay.textContent = `Error checking ${dt}`;
          remainingDisplay.style.color = '#dc2626';
          return { ok: false, message: `Error checking ${dt}` };
        }
        if (typeof r.remaining !== 'undefined' && r.remaining <= 0) {
          remainingDisplay.textContent = `❌ ${dt} is fully booked.`;
          remainingDisplay.style.color = '#dc2626';
          return { ok: false, message: `${dt} fully booked` };
        }
      }
      remainingDisplay.textContent = '✅ All selected dates have available slots.';
      remainingDisplay.style.color = '#16a34a';
      return { ok: true };
    }

    /* ---------- Date input change handling ---------- */
    dateInputs.forEach((input) => {
      input.addEventListener('change', async () => {
        // Collect non-empty unique dates in ascending order
        const values = dateInputs.map(i => i.value).filter(v => v && v.trim() !== '');
        // Optional: enforce exactly 3 dates — but we'll allow fewer and let user finish the form
        selectedDates = Array.from(new Set(values)); // dedupe
        setHiddenDates();
        await checkAllSelectedDates();
        updateNextAvailableText();
      });
    });

    /* ---------- Time selection ---------- */
    timeButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        timeButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        selectedTime = btn.dataset.time || btn.textContent.trim();
        if (appointmentTimeInput) appointmentTimeInput.value = selectedTime;
        updateNextAvailableText();
      });
    });

    /* ---------- On form submit: validate required 3 dates (if you want to require exactly 3) ---------- */
    const appointmentForm = document.getElementById('appointmentForm');
    if (appointmentForm) {
      appointmentForm.addEventListener('submit', function (e) {
        // If site requirement: force exactly 3 chosen dates
        // Comment / adjust if you want "up to 3" instead
        if (selectedDates.length !== 3) {
          e.preventDefault();
          alert('Please select exactly 3 appointment dates.');
          return false;
        }
        // Also verify slot availability once more before sending
        // (optional synchronous check, but we can do an async check and block)
      });
    }

    /* ---------- Initial check if fields already populated on load ---------- */
    (async function initialLoad() {
      // populate selectedDates from hidden if exists
      if (appointmentDatesJson && appointmentDatesJson.value) {
        try {
          const parsed = JSON.parse(appointmentDatesJson.value);
          if (Array.isArray(parsed)) {
            selectedDates = parsed;
          }
        } catch (err) {
          // ignore
        }
      } else {
        const vals = dateInputs.map(i => i.value).filter(Boolean);
        if (vals.length) selectedDates = vals;
      }

      // populate time if input has value
      if (appointmentTimeInput && appointmentTimeInput.value) {
        selectedTime = appointmentTimeInput.value;
      }

      if (selectedDates.length) {
        await checkAllSelectedDates();
        updateNextAvailableText();
      }
    })();

  }); // DOMContentLoaded
})(); // IIFE
