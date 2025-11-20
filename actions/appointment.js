// appointment.js – Fixed Version
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {

    /* =========================================
       1. INITIALIZATION & VARIABLES
       ========================================= */
    const steps = Array.from(document.querySelectorAll('.form-step'));
    const nextBtns = Array.from(document.querySelectorAll('.next-btn'));
    const prevBtns = Array.from(document.querySelectorAll('.prev-btn'));
    const progressSteps = Array.from(document.querySelectorAll('.progress-step'));
    const progressLines = Array.from(document.querySelectorAll('.progress-line'));
    const hiddenField = document.getElementById("appointment_dates_json");
    const summaryDiv = document.getElementById("appointmentSummary");
    const summaryContent = document.getElementById("summaryContent");
    const form = document.getElementById("appointmentForm");

    let formStepIndex = 0;

    // MOVED TO TOP: Define this early so validation can see it
    let appointments = [
      { date: "", time: "", remaining: null },
      { date: "", time: "", remaining: null },
      { date: "", time: "", remaining: null }
    ];
    

    /* =========================================
       2. NAVIGATION & VALIDATION LOGIC
       ========================================= */
    
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

    function validateStep(stepElement) {
        let isValid = true;
        
        try {
            // A. Check Standard Inputs (Text, Number, Select)
            const inputs = stepElement.querySelectorAll('input[required], select[required], textarea[required]');
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('input-error');
                    input.addEventListener('input', () => input.classList.remove('input-error'), {once: true});
                } else {
                    input.classList.remove('input-error');
                }
            });

            // B. Check Radio Buttons
            const radioGroups = new Set();
            stepElement.querySelectorAll('input[type="radio"][required]').forEach(r => radioGroups.add(r.name));
            
            radioGroups.forEach(groupName => {
                const radios = stepElement.querySelectorAll(`input[name="${groupName}"]`);
                const isChecked = Array.from(radios).some(r => r.checked);
                if (!isChecked) {
                    isValid = false;
                    alert(`Please select an option for: ${groupName.replace('_', ' ')}`);
                }
            });

            // C. SPECIAL CHECK: Date & Time Step (The part that was breaking)
            if (stepElement.querySelector('.date-input')) {
                // Check if at least one slot has BOTH date and time
                const validSlots = appointments.filter(a => a.date && a.time);
                
                if (validSlots.length === 0) {
                    isValid = false;
                    alert("⚠️ Please select at least one appointment date and time.");
                } else {
                    // Check if any selected slot is fully booked
                    const hasBlockedSlot = appointments.some(a => a.remaining === 0);
                    if (hasBlockedSlot) {
                        isValid = false;
                        alert("❌ One of your selected slots is fully booked. Please change it.");
                    }
                }
            }

        } catch (err) {
            console.error("Validation Error:", err);
            alert("An error occurred during validation. Please check console.");
            return false;
        }

        return isValid;
    }

    // BUTTON LISTENERS
    nextBtns.forEach(btn => btn.addEventListener('click', () => {
      const currentStepElement = steps[formStepIndex];
      
      if (validateStep(currentStepElement)) {
        if (formStepIndex < steps.length - 1) {
          formStepIndex++;
          showStep(formStepIndex);
        }
      }
    }));
    
    prevBtns.forEach(btn => btn.addEventListener('click', () => {
      if (formStepIndex > 0) {
        formStepIndex--;
        showStep(formStepIndex);
      }
    }));
    
    // Initialize
    showStep(formStepIndex);


    /* =========================================
       3. SLOT CHECKING LOGIC (AJAX)
       ========================================= */

    async function checkSlot(date, time) {
      try {
        const formData = new FormData();
        formData.append("appointment_date", date);
        formData.append("appointment_time", time);

        const res = await fetch("../actions/check_slots.php", {
          method: "POST",
          body: formData
        });

        if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
        const json = await res.json();
        
        if (!json.success) return null;

        return {
          remaining: json.remaining,
          max_slots: json.max_slots || 3,
          used_slots: json.used_slots || 0
        };
      } catch (err) {
        console.error("Slot check failed:", err);
        return null;
      }
    }

    function updateSlotDisplay(index) {
      const appt = appointments[index];
      const badge = document.getElementById(`slot-badge-${index}`);
      const message = document.getElementById(`slot-message-${index}`);
      
      if (!badge || !message) return;
      
      if (!appt.date || !appt.time) {
        badge.style.background = '#e5e7eb';
        badge.style.color = '#6b7280';
        badge.textContent = 'Select date & time';
        message.style.display = 'none';
        return;
      }

      if (appt.remaining === null) {
        badge.style.background = '#fef3c7';
        badge.style.color = '#92400e';
        badge.textContent = 'Checking...';
        message.style.display = 'block';
        message.style.background = '#fffbeb';
        message.style.color = '#92400e';
        message.innerHTML = '⏳ Checking availability...';
        return;
      }

      if (appt.remaining === 0) {
        badge.style.background = '#fee2e2';
        badge.style.color = '#991b1b';
        badge.textContent = 'FULLY BOOKED';
        message.style.display = 'block';
        message.style.background = '#fee2e2';
        message.style.color = '#991b1b';
        message.innerHTML = `❌ <strong>This time slot is fully booked.</strong> Please select a different time.`;
      } else if (appt.remaining === 1) {
        badge.style.background = '#fed7aa';
        badge.style.color = '#9a3412';
        badge.textContent = '1 SLOT LEFT';
        message.style.display = 'block';
        message.style.background = '#fff7ed';
        message.style.color = '#9a3412';
        message.innerHTML = `⚠️ <strong>Only 1 slot remaining!</strong> Book quickly.`;
      } else {
        badge.style.background = '#d1fae5';
        badge.style.color = '#065f46';
        badge.textContent = `${appt.remaining} SLOTS LEFT`;
        message.style.display = 'block';
        message.style.background = '#ecfdf5';
        message.style.color = '#065f46';
        message.innerHTML = `✅ <strong>${appt.remaining} out of 3 slots available</strong> for this time.`;
      }
    }

    function updateSummary() {
      const validAppointments = appointments.filter(a => a.date && a.time);
      
      if (validAppointments.length === 0) {
        summaryDiv.style.display = 'none';
        return;
      }

      let summaryHTML = '<ul style="margin: 0; padding-left: 20px;">';
      validAppointments.forEach((appt, i) => {
        const status = appt.remaining === 0 ? '❌ BLOCKED' : 
                       appt.remaining === 1 ? '⚠️ LAST SLOT' : 
                       appt.remaining === null ? '⏳ Checking...' : '✅ Available';
        summaryHTML += `<li><strong>${appt.date}</strong> at <strong>${appt.time}</strong> - ${status}</li>`;
      });
      summaryHTML += '</ul>';
      
      summaryContent.innerHTML = summaryHTML;
      summaryDiv.style.display = 'block';
    }

    function updateHiddenField() {
      const validAppointments = appointments
        .filter(a => a.date && a.time)
        .map(a => ({ date: a.date, time: a.time }));
      
      hiddenField.value = JSON.stringify(validAppointments);
    }

    async function checkAppointmentSlot(index) {
      const appt = appointments[index];
      
      if (!appt.date || !appt.time) {
        appt.remaining = null;
        updateSlotDisplay(index);
        updateSummary();
        updateHiddenField();
        return;
      }

      appt.remaining = null;
      updateSlotDisplay(index);
      
      const result = await checkSlot(appt.date, appt.time);
      
      if (result === null) {
        appt.remaining = null;
        const message = document.getElementById(`slot-message-${index}`);
        if (message) {
            message.style.display = 'block';
            message.innerHTML = '❌ Error checking slot availability.';
        }
      } else {
        appt.remaining = result.remaining;
        updateSlotDisplay(index);
      }
      
      updateSummary();
      updateHiddenField();
    }

    /* =========================================
       4. INPUT LISTENERS
       ========================================= */
    const dateInputs = document.querySelectorAll(".date-input");
    const timeSelects = document.querySelectorAll(".time-select");

    dateInputs.forEach(input => {
      input.addEventListener("change", async (e) => {
        const index = parseInt(e.target.dataset.index);
        appointments[index].date = e.target.value;
        await checkAppointmentSlot(index);
      });
    });

    timeSelects.forEach(select => {
      select.addEventListener("change", async (e) => {
        const index = parseInt(e.target.dataset.index);
        appointments[index].time = e.target.value;
        await checkAppointmentSlot(index);
      });
    });
    /* =========================================
       0. RESTRICT DATE RANGE (No Past, No Far Future)
       ========================================= */

    
    // 1. Get "Today" in YYYY-MM-DD format
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const minDate = `${yyyy}-${mm}-${dd}`;

    // 2. Calculate "Max Date" (e.g., 30 days from now)
    const futureDate = new Date();
    futureDate.setDate(futureDate.getDate() + 30); // Change 30 to however many days you want
    const f_yyyy = futureDate.getFullYear();
    const f_mm = String(futureDate.getMonth() + 1).padStart(2, '0');
    const f_dd = String(futureDate.getDate()).padStart(2, '0');
    const maxDate = `${f_yyyy}-${f_mm}-${f_dd}`;

    // 3. Apply to all date inputs
    dateInputs.forEach(input => {
        input.setAttribute("min", minDate); // Disable past dates
        input.setAttribute("max", maxDate); // Disable far future dates
        
        // Optional: Prevent typing manually
        input.addEventListener('keydown', (e) => e.preventDefault()); 
    });

    /* =========================================
       5. FORM SUBMISSION
       ========================================= */
    if (form) {
      form.addEventListener("submit", async function(e) {
        e.preventDefault();

        // Final Validation before submit
        const validAppointments = appointments.filter(a => a.date && a.time);
        
        if (validAppointments.length === 0) {
          alert("⚠️ Please select at least one appointment date and time.");
          return false;
        }

        // Check for full slots
        const hasFullSlot = appointments.some(a => a.date && a.time && a.remaining === 0);
        if (hasFullSlot) {
            alert("❌ One of your selected slots is fully booked. Please change it.");
            return false;
        }

        // Collect form data
        const formData = new FormData(form);
        updateHiddenField();
        formData.set('appointment_dates_json', hiddenField.value);

        try {
          const response = await fetch("../actions/appointment-action.php", {
            method: "POST",
            body: formData
          });

          const result = await response.json();

          if (result.success) {
            window.location.href = "../pages/appointment-success.php";
          } else {
            alert("❌ " + result.message);
          }
        } catch (error) {
          console.error("Submission error:", error);
          alert("❌ An error occurred while submitting.");
        }
      });
    }

  }); 
})();