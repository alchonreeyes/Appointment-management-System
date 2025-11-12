// appointment.js – AJAX Version with Real-time Slot Checking
(function () {
  'use strict';

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

    /* ---------- 3 Date+Time Appointment System ---------- */
    const dateInputs = document.querySelectorAll(".date-input");
    const timeSelects = document.querySelectorAll(".time-select");
    const hiddenField = document.getElementById("appointment_dates_json");
    const summaryDiv = document.getElementById("appointmentSummary");
    const summaryContent = document.getElementById("summaryContent");

    // Track 3 appointments
    let appointments = [
      { date: "", time: "", remaining: null },
      { date: "", time: "", remaining: null },
      { date: "", time: "", remaining: null }
    ];

    // Check slot availability via AJAX
    async function checkSlot(date, time) {
      try {
        const formData = new FormData();
        formData.append("appointment_date", date);
        formData.append("appointment_time", time);

        const res = await fetch("../actions/check_slots.php", {
          method: "POST",
          body: formData
        });

        if (!res.ok) {
          throw new Error(`HTTP error! status: ${res.status}`);
        }

        const json = await res.json();
        
        if (!json.success) {
          console.error("Slot check error:", json.message);
          return null;
        }

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

    // Update individual appointment slot display
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

    // Update summary
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

    // Update hidden field
    function updateHiddenField() {
      const validAppointments = appointments
        .filter(a => a.date && a.time)
        .map(a => ({ date: a.date, time: a.time }));
      
      hiddenField.value = JSON.stringify(validAppointments);
      console.log("Hidden field updated:", hiddenField.value);
    }

    // Check specific appointment slot
    async function checkAppointmentSlot(index) {
      const appt = appointments[index];
      
      if (!appt.date || !appt.time) {
        appt.remaining = null;
        updateSlotDisplay(index);
        updateSummary();
        updateHiddenField();
        return;
      }

      // Show checking state
      appt.remaining = null;
      updateSlotDisplay(index);
      
      // Fetch slot info
      const result = await checkSlot(appt.date, appt.time);
      
      if (result === null) {
        appt.remaining = null;
        const message = document.getElementById(`slot-message-${index}`);
        if (message) {
          message.style.display = 'block';
          message.style.background = '#fee2e2';
          message.style.color = '#991b1b';
          message.innerHTML = '❌ Error checking slot availability. Please try again.';
        }
      } else {
        appt.remaining = result.remaining;
        updateSlotDisplay(index);
      }
      
      updateSummary();
      updateHiddenField();
    }

    // Date input listeners
    dateInputs.forEach(input => {
      input.addEventListener("change", async (e) => {
        const index = parseInt(e.target.dataset.index);
        appointments[index].date = e.target.value;
        await checkAppointmentSlot(index);
      });
    });

    // Time select listeners
    timeSelects.forEach(select => {
      select.addEventListener("change", async (e) => {
        const index = parseInt(e.target.dataset.index);
        appointments[index].time = e.target.value;
        await checkAppointmentSlot(index);
      });
    });

    /* ---------- AJAX Form Submission ---------- */
    const form = document.getElementById("appointmentForm");
    if (form) {
      form.addEventListener("submit", async function(e) {
        e.preventDefault();

        console.log("Form submission started");

        // Validate at least one appointment
        const validAppointments = appointments.filter(a => a.date && a.time);
        
        if (validAppointments.length === 0) {
          alert("⚠️ Please select at least one appointment date and time.");
          return false;
        }

        console.log("Valid appointments:", validAppointments);

        // Check for fully booked slots
        let hasFullSlot = false;
        let fullSlotMessage = "";
        
        for (let i = 0; i < appointments.length; i++) {
          const appt = appointments[i];
          if (appt.date && appt.time && appt.remaining === 0) {
            hasFullSlot = true;
            fullSlotMessage = `❌ Sorry, ${appt.date} at ${appt.time} is fully booked. Please select a different time.`;
            break;
          }
        }

        if (hasFullSlot) {
          alert(fullSlotMessage);
          return false;
        }

        // Final slot check
        for (let i = 0; i < appointments.length; i++) {
          const appt = appointments[i];
          if (appt.date && appt.time) {
            const result = await checkSlot(appt.date, appt.time);
            if (result && result.remaining === 0) {
              alert(`❌ Sorry, ${appt.date} at ${appt.time} just got fully booked. Please select a different time.`);
              await checkAppointmentSlot(i);
              return false;
            }
          }
        }

        // Collect form data
        const formData = new FormData(form);
        
        // Make sure the JSON is properly set
        updateHiddenField();
        console.log("Appointment JSON being sent:", hiddenField.value);
        
        // Re-append to ensure it's in FormData
        formData.set('appointment_dates_json', hiddenField.value);

        // Debug: Log all form data
        console.log("Form data entries:");
        for (let pair of formData.entries()) {
          console.log(pair[0] + ': ' + pair[1]);
        }

        // Submit via AJAX
        try {
          const response = await fetch("../actions/appointment-action.php", {
            method: "POST",
            body: formData
          });

          const result = await response.json();
          console.log("Server response:", result);

          if (result.success) {
            alert("✅ " + result.message);
            // Redirect or reset form
            window.location.href = "../pages/appointment-success.php";
          } else {
            alert("❌ " + result.message);
          }
        } catch (error) {
          console.error("Submission error:", error);
          alert("❌ An error occurred while submitting. Please try again.");
        }
      });
    }

  }); // DOMContentLoaded
})(); // IIFE