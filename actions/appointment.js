// appointment.js – Fixed Version
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    /* =========================================
       DYNAMIC APPOINTMENT ROWS
       ========================================= */
    const addBtn = document.getElementById('add-appt-btn');
    const row2 = document.getElementById('row-1');
    const row3 = document.getElementById('row-2');
    
    // Counter to track how many are showing
    let visibleRows = 1;

    if(addBtn) {
        addBtn.addEventListener('click', function() {
            if (visibleRows === 1) {
                row2.style.display = 'block';
                visibleRows = 2;
            } else if (visibleRows === 2) {
                row3.style.display = 'block';
                visibleRows = 3;
                addBtn.style.display = 'none'; // Max reached
            }
        });
    }

    // Function to hide rows (needs to be global or attached to window)
    window.hideRow = function(index) {
        if (index === 1) {
            // Hide Row 2
            row2.style.display = 'none';
            // Clear inputs
            const dateInput = row2.querySelector('.date-input');
            const timeSelect = row2.querySelector('.time-select');
            if(dateInput) dateInput.value = '';
            if(timeSelect) timeSelect.value = '';
            
            // If row 3 is visible, we might want to shift it up, but simple hiding is easier
            if(visibleRows === 3) {
                 // If removing row 2 but row 3 is open, maybe just clear row 2?
                 // Simple logic: Decrement count
            }
            visibleRows--;
        } else if (index === 2) {
            row3.style.display = 'none';
            const dateInput = row3.querySelector('.date-input');
            const timeSelect = row3.querySelector('.time-select');
            if(dateInput) dateInput.value = '';
            if(timeSelect) timeSelect.value = '';
            visibleRows--;
        }
        
        // Show add button again if it was hidden
        addBtn.style.display = 'inline-block';
        
        // Reset the appointment data in the JS array for that index
        appointments[index].date = "";
        appointments[index].time = "";
        appointments[index].remaining = null;
    };
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
    /* =========================================
       GENERATE SUMMARY FUNCTION
       ========================================= */
    /* =========================================
       GENERATE SUMMARY FUNCTION (ROBUST VERSION)
       ========================================= */
    function updateSummaryView() {
        console.log("Generating Summary..."); // Check your browser console for this!
        
        const summaryBox = document.getElementById('finalSummary');
        if (!summaryBox) {
            console.error("Summary Box not found!");
            return;
        }
        
        // 1. Get Personal Info (Use 'Value' or fallback to empty string)
        const name = document.querySelector('input[name="full_name"]')?.value || "N/A";
        const age = document.querySelector('input[name="age"]')?.value || "N/A";
        const gender = document.querySelector('select[name="gender"]')?.value || "N/A";
        const phone = document.querySelector('input[name="contact_number"]')?.value || "N/A";
        
        // 2. Get Appointments
        // We filter the 'appointments' array directly
        const bookedSlots = appointments
            .filter(a => a.date && a.time)
            .map(a => `<div>📅 <strong>${a.date}</strong> at ⏰ <strong>${a.time}</strong></div>`)
            .join('');

        // 3. Get Preferences (Brands)
        // Note: checkboxes might be hidden, so we query them by name
        const brandChecks = Array.from(document.querySelectorAll('input[name="brands[]"]:checked'));
        const brands = brandChecks.length > 0 ? brandChecks.map(cb => cb.value).join(', ') : "None";
            
        // 4. Get Preferences (Shapes)
        const shapeChecks = Array.from(document.querySelectorAll('input[name="frame_shape[]"]:checked'));
        const shapes = shapeChecks.length > 0 ? shapeChecks.map(cb => cb.value).join(', ') : "None";

        // 5. Get Eye History
        const glassesEl = document.querySelector('input[name="wear_glasses"]:checked');
        const glasses = glassesEl ? glassesEl.value : "No";

        const contactsEl = document.querySelector('input[name="wear_contact_lenses"]:checked');
        const contacts = contactsEl ? contactsEl.value : "No";

        // Inject HTML
        summaryBox.innerHTML = `
        <style>
          .ams-review-summary {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
            color: #0f172a;
            max-width: 820px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e6edf3;
            box-shadow: 0 6px 18px rgba(12, 31, 53, 0.06);
            border-radius: 12px;
            overflow: hidden;
          }
          .ams-review-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 22px;
            background: linear-gradient(90deg,#f8fafc 0%, #f1f5f9 100%);
            border-bottom: 1px solid #eef2f7;
          }
          .ams-review-title {
            font-size: 16px;
            font-weight: 600;
            color: #0b1220;
          }
          .ams-review-sub {
            font-size: 13px;
            color: #475569;
          }
          .ams-summary-body {
            padding: 18px 22px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
          }
          .ams-summary-section {
            background: #fbfdff;
            border: 1px solid #eef6fa;
            padding: 12px 14px;
            border-radius: 10px;
          }
          .ams-summary-title {
            font-size: 13px;
            font-weight: 700;
            color: #0b3a4a;
            margin-bottom: 8px;
          }
          .ams-summary-row {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            padding: 6px 0;
            border-top: 1px dashed transparent;
          }
          .ams-summary-row + .ams-summary-row { border-top-color: #eef3f6; }
          .ams-summary-label {
            font-size: 13px;
            color: #334155;
            flex: 0 0 44%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 600;
          }
          .ams-summary-value {
            font-size: 13px;
            color: #0f172a;
            flex: 1 1 auto;
            text-align: right;
            word-break: break-word;
          }
          .ams-summary-value small {
            color: #64748b;
            font-weight: 500;
          }
          .ams-slots-list {
            text-align: left;
            line-height: 1.5;
            color: #0f172a;
            font-size: 13px;
            margin: 0;
            padding-left: 18px;
          }
          .ams-slot-item {
            margin: 6px 0;
            display: flex;
            align-items: center;
            gap: 8px;
          }
          .ams-slot-bullet {
            display:inline-flex;
            width:10px;
            height:10px;
            border-radius:50%;
            background:#06b6d4;
            flex: 0 0 10px;
          }
          .ams-summary-footer {
            padding: 12px 22px;
            background: #ffffff;
            border-top: 1px solid #eef2f7;
            text-align: right;
          }
          .ams-muted { color:#64748b; font-size:12px; }
          @media (min-width:700px) {
            .ams-summary-body { grid-template-columns: 1fr 1fr; }
            .ams-summary-section { min-height: 64px; }
            .ams-summary-section--wide { grid-column: 1 / -1; }
          }
        </style>

        <div class="ams-review-summary" role="region" aria-label="Appointment summary">
          <div class="ams-review-header">
            <div>
              <div class="ams-review-title">Review & Confirm</div>
              <div class="ams-review-sub">Please verify your details and selected appointment slots</div>
            </div>
            <div class="ams-muted">Check before submitting</div>
          </div>

          <div class="ams-summary-body">
            <div class="ams-summary-section ams-summary-section--wide">
              <div class="ams-summary-title">Patient Details</div>
              <div class="ams-summary-row">
          <div class="ams-summary-label">Name</div>
          <div class="ams-summary-value">${name}</div>
              </div>
              <div class="ams-summary-row">
          <div class="ams-summary-label">Age / Gender</div>
          <div class="ams-summary-value">${age} / ${gender}</div>
              </div>
              <div class="ams-summary-row">
          <div class="ams-summary-label">Phone</div>
          <div class="ams-summary-value">${phone}</div>
              </div>
            </div>

            <div class="ams-summary-section">
              <div class="ams-summary-title">Appointment Slots</div>
              <div class="ams-summary-value" style="text-align:left;">
          ${bookedSlots
            ? `<div class="ams-slots-list">${bookedSlots.split('</div>').filter(Boolean).map(s => `<div class="ams-slot-item"><span class="ams-slot-bullet" aria-hidden="true"></span>${s}</div>`).join('')}</div>`
            : '<div class="ams-muted">No slots selected</div>'}
              </div>
            </div>

            <div class="ams-summary-section">
              <div class="ams-summary-title">Eye History</div>
              <div class="ams-summary-row">
          <div class="ams-summary-label">Wears Glasses?</div>
          <div class="ams-summary-value">${glasses}</div>
              </div>
              <div class="ams-summary-row">
          <div class="ams-summary-label">Wears Contacts?</div>
          <div class="ams-summary-value">${contacts}</div>
              </div>
            </div>

            <div class="ams-summary-section">
              <div class="ams-summary-title">Preferences</div>
              <div class="ams-summary-row">
          <div class="ams-summary-label">Interested Brands</div>
          <div class="ams-summary-value">${brands}</div>
              </div>
              <div class="ams-summary-row">
          <div class="ams-summary-label">Frame Shapes</div>
          <div class="ams-summary-value">${shapes}</div>
              </div>
            </div>
          </div>

          <div class="ams-summary-footer">
            <span class="ams-muted">Make sure all information is correct.</span>
          </div>
        </div>
        `;
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

          // --- CRITICAL CHECK ---
          // Ensure 'steps.length - 1' actually matches the index of your Summary Page.
          // If you have 5 steps (Index 0, 1, 2, 3, 4), then this must be 4.
          if (formStepIndex === steps.length - 1) {
              updateSummaryView(); 
          }
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

    /* =========================================
       SAFE UPDATE SUMMARY (Prevents crash)
       ========================================= */
    function updateSummary() {
      // 1. Get elements dynamically to ensure they exist
      const summaryDiv = document.getElementById("appointmentSummary");
      const summaryContent = document.getElementById("summaryContent");

      // 2. SAFETY CHECK: If elements are missing, STOP. Do not crash.
      if (!summaryDiv || !summaryContent) {
          // It's okay if they are missing in Step 2, just exit silently.
          return;
      }

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
      // Always re-query to be safe
      const field = document.getElementById("appointment_dates_json");
      
      if (!field) {
          console.error("Critical Error: Hidden field 'appointment_dates_json' not found in the DOM!");
          // Try to find it by name as a fallback
          const fallback = document.querySelector('input[name="appointment_dates_json"]');
          if (fallback) {
              console.log("Recovered using name selector.");
              // Update logic using fallback
              const validAppointments = appointments
                .filter(a => a.date && a.time)
                .map(a => ({ date: a.date, time: a.time }));
              fallback.value = JSON.stringify(validAppointments);
              return;
          }
          return;
      }

      const validAppointments = appointments
        .filter(a => a.date && a.time)
        .map(a => ({ date: a.date, time: a.time }));
      
      field.value = JSON.stringify(validAppointments);
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
        
        // Update the hidden field one last time
        updateHiddenField();
        
        // Re-select the field to ensure we have the value
        const jsonField = document.getElementById("appointment_dates_json");
        if (jsonField) {
            formData.set('appointment_dates_json', jsonField.value);
        } else {
            alert("System Error: Could not find appointment data field.");
            return false;
        }

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