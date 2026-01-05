(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    /* =========================================
       CLOSURE DATES - FETCH FROM ADMIN
       ========================================= */
    let closedDates = [];
    
    async function fetchClosedDates() {
      try {
        const response = await fetch('../actions/get-closed-dates.php');
        const data = await response.json();
        
        if (data.success) {
          closedDates = data.closed_dates;
          console.log('✅ Loaded closed dates:', closedDates);
          
          // Apply restrictions to all date inputs
          applyClosedDatesRestriction();
        }
      } catch (error) {
        console.error('❌ Failed to fetch closed dates:', error);
      }
    }
    
    function applyClosedDatesRestriction() {
      const dateInputs = document.querySelectorAll(".date-input");
      
      dateInputs.forEach(input => {
        // Listen for date changes
        input.addEventListener('input', function(e) {
          const selectedDate = e.target.value;
          
          if (closedDates.includes(selectedDate)) {
            const index = parseInt(e.target.dataset.index);
            
            // Clear the invalid date
            e.target.value = '';
            appointments[index].date = '';
            
            // Show error message
            const message = document.getElementById(`slot-message-${index}`);
            const badge = document.getElementById(`slot-badge-${index}`);
            
            if (message && badge) {
              badge.style.background = '#fee2e2';
              badge.style.color = '#991b1b';
              badge.textContent = 'Clinic Closed';
              
              message.style.display = 'block';
              message.style.background = '#fef2f2';
              message.style.color = '#991b1b';
              message.style.border = '1px solid #fecaca';
              message.style.padding = '8px';
              message.style.borderRadius = '4px';
              message.textContent = '❌ Sorry, the clinic is closed on this date. Please choose another day.';
            }
            
            alert('⚠️ The clinic is closed on ' + formatDate(selectedDate) + '. Please select another date.');
            updateSlotDisplay(index);
          }
        });
      });
    }
    
    function formatDate(dateStr) {
      const date = new Date(dateStr + 'T00:00:00');
      return date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
      });
    }
    
    // Initialize - fetch closed dates on page load
    fetchClosedDates();

    /* =========================================
       DYNAMIC APPOINTMENT ROWS
       ========================================= */
    const addBtn = document.getElementById('add-appt-btn');
    const row2 = document.getElementById('row-1');
    const row3 = document.getElementById('row-2');
    
    let visibleRows = 1;

    if(addBtn) {
        addBtn.addEventListener('click', function() {
            if (visibleRows === 1) {
                row2.style.display = 'block';
                visibleRows = 2;
            } else if (visibleRows === 2) {
                row3.style.display = 'block';
                visibleRows = 3;
                addBtn.style.display = 'none'; 
            }
        });
    }

    window.hideRow = function(index) {
        if (index === 1) {
            row2.style.display = 'none';
            const dateInput = row2.querySelector('.date-input');
            const timeSelect = row2.querySelector('.time-select');
            if(dateInput) dateInput.value = '';
            if(timeSelect) timeSelect.value = '';
            visibleRows--;
        } else if (index === 2) {
            row3.style.display = 'none';
            const dateInput = row3.querySelector('.date-input');
            const timeSelect = row3.querySelector('.time-select');
            if(dateInput) dateInput.value = '';
            if(timeSelect) timeSelect.value = '';
            visibleRows--;
        }
        addBtn.style.display = 'inline-block';
        appointments[index].date = "";
        appointments[index].time = "";
        
        for (let i = 0; i < appointments.length; i++) {
          updateSlotDisplay(i);
        }
        updateHiddenField();
    };

    /* =========================================
       INITIALIZATION & VARIABLES
       ========================================= */
    const steps = Array.from(document.querySelectorAll('.form-step'));
    const nextBtns = Array.from(document.querySelectorAll('.next-btn'));
    const prevBtns = Array.from(document.querySelectorAll('.prev-btn'));
    const progressSteps = Array.from(document.querySelectorAll('.progress-step'));
    const progressLines = Array.from(document.querySelectorAll('.progress-line'));
    const hiddenField = document.getElementById("appointment_dates_json");
    const form = document.getElementById("appointmentForm");

    let formStepIndex = 0;
    
    let appointments = [
      { date: "", time: "" },
      { date: "", time: "" },
      { date: "", time: "" }
    ];
    
    /* =========================================
       NAVIGATION & VALIDATION LOGIC
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

    function updateSummaryView() {
        console.log("Generating Summary..."); 

        const summaryBox = document.getElementById('finalSummary');
        if (!summaryBox) return;

        const name = document.querySelector('input[name="full_name"]')?.value || "N/A";
        const age = document.querySelector('input[name="age"]')?.value || "N/A";
        const gender = document.querySelector('select[name="gender"]')?.value || "N/A";
        const phone = document.querySelector('input[name="contact_number"]')?.value || "N/A";
        const occupation = document.querySelector('input[name="occupation"]')?.value || "N/A";

        const isNormalExam = document.querySelector('input[name="wear_glasses"]') !== null;
        const isMedicalCert = document.querySelector('input[name="certificate_purpose"]') !== null;
        const isIshihara = document.querySelector('input[name="ishihara_test_type"]') !== null;

        let specificContent = '';

        if (isNormalExam) {
            const productChecks = Array.from(document.querySelectorAll('input[name="selected_products[]"]:checked'));
            const productsList = productChecks.length > 0 
                ? productChecks.map(cb => cb.value).join('<br>') 
                : "None selected";

            const glassesEl = document.querySelector('input[name="wear_glasses"]:checked');
            const glasses = glassesEl ? glassesEl.value : "No";

            const contactsEl = document.querySelector('input[name="wear_contact_lenses"]:checked');
            const contacts = contactsEl ? contactsEl.value : "No";

            specificContent = `
                <div class="ams-summary-section ams-summary-section--wide">
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

                <div class="ams-summary-section ams-summary-section--wide">
                  <div class="ams-summary-title">Selected Eye Glasses</div>
                  <div class="ams-summary-row">
                    <div class="ams-summary-label" style="align-self: flex-start;">Frames to Try:</div>
                    <div class="ams-summary-value" style="text-align: right;">${productsList}</div>
                  </div>
                </div>
            `;
        } else if (isMedicalCert) {
            const certPurposeEl = document.querySelector('input[name="certificate_purpose"]:checked');
            const certPurpose = certPurposeEl ? certPurposeEl.value : "Not specified";
            
            const certOther = document.querySelector('input[name="certificate_other"]')?.value || "";
            const purposeDisplay = certPurpose === "Other" && certOther ? certOther : certPurpose;

            specificContent = `
                <div class="ams-summary-section ams-summary-section--wide">
                  <div class="ams-summary-title">Certificate Details</div>
                  <div class="ams-summary-row">
                    <div class="ams-summary-label">Purpose</div>
                    <div class="ams-summary-value">${purposeDisplay}</div>
                  </div>
                </div>
            `;
        } else if (isIshihara) {
            const testTypeEl = document.querySelector('input[name="ishihara_test_type"]:checked');
            const testType = testTypeEl ? testTypeEl.value : "Not specified";

            const reasonEl = document.querySelector('input[name="ishihara_reason"]');
            const reason = reasonEl?.value || "Not provided";

            const prevIssuesEl = document.querySelector('input[name="previous_color_issues"]:checked');
            const prevIssues = prevIssuesEl ? prevIssuesEl.value : "Not specified";

            const notesEl = document.querySelector('textarea[name="ishihara_notes"]');
            const notes = notesEl?.value || "None";

            specificContent = `
                <div class="ams-summary-section ams-summary-section--wide">
                  <div class="ams-summary-title">Ishihara Test Details</div>
                  <div class="ams-summary-row">
                    <div class="ams-summary-label">Test Type</div>
                    <div class="ams-summary-value">${testType}</div>
                  </div>
                  <div class="ams-summary-row">
                    <div class="ams-summary-label">Reason for Test</div>
                    <div class="ams-summary-value">${reason}</div>
                  </div>
                  <div class="ams-summary-row">
                    <div class="ams-summary-label">Previous Color Issues?</div>
                    <div class="ams-summary-value">${prevIssues}</div>
                  </div>
                  ${notes !== "None" ? `
                  <div class="ams-summary-row">
                    <div class="ams-summary-label">Additional Notes</div>
                    <div class="ams-summary-value">${notes}</div>
                  </div>
                  ` : ''}
                </div>
            `;
        }

        summaryBox.innerHTML = `
        <style>
          .ams-review-summary { font-family: system-ui, sans-serif; color: #0f172a; max-width: 820px; margin: 0 auto; background: #ffffff; border: 1px solid #e6edf3; border-radius: 12px; overflow: hidden; }
          .ams-review-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; background: #f8fafc; border-bottom: 1px solid #eef2f7; }
          .ams-review-title { font-size: 16px; font-weight: 600; color: #0b1220; }
          .ams-summary-body { padding: 18px 22px; display: grid; grid-template-columns: 1fr; gap: 14px; }
          .ams-summary-section { background: #fbfdff; border: 1px solid #eef6fa; padding: 12px 14px; border-radius: 10px; }
          .ams-summary-section--wide { grid-column: 1 / -1; }
          .ams-summary-title { font-size: 13px; font-weight: 700; color: #0b3a4a; margin-bottom: 8px; }
          .ams-summary-row { display: flex; gap: 10px; justify-content: space-between; padding: 6px 0; border-top: 1px dashed transparent; }
          .ams-summary-row + .ams-summary-row { border-top-color: #eef3f6; }
          .ams-summary-label { font-size: 13px; color: #334155; font-weight: 600; }
          .ams-summary-value { font-size: 13px; color: #0f172a; text-align: right; word-break: break-word; }
          .ams-summary-footer { padding: 12px 22px; background: #ffffff; border-top: 1px solid #eef2f7; text-align: right; }
          .ams-muted { color:#64748b; font-size:12px; }
          @media (min-width:700px) { .ams-summary-body { grid-template-columns: 1fr 1fr; } }
        </style>

        <div class="ams-review-summary">
          <div class="ams-review-header">
            <div class="ams-review-title">Review & Confirm</div>
            <div class="ams-muted">Final Step</div>
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
              <div class="ams-summary-row">
                <div class="ams-summary-label">Occupation</div>
                <div class="ams-summary-value">${occupation}</div>
              </div>
            </div>

            ${specificContent}
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

    function checkDuplicateAppointment(currentIndex) {
      const currentAppt = appointments[currentIndex];
      
      if (!currentAppt.date || !currentAppt.time) {
        return false;
      }

      for (let i = 0; i < appointments.length; i++) {
        if (i === currentIndex) continue;
        
        const otherAppt = appointments[i];
        
        if (otherAppt.date === currentAppt.date && otherAppt.time === currentAppt.time) {
          return true;
        }
      }
      
      return false;
    }

    function validateStep(stepElement) {
        let isValid = true;
        
        try {
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

            if (stepElement.querySelector('.date-input')) {
                const validSlots = appointments.filter(a => a.date && a.time);
                
                if (validSlots.length === 0) {
                    isValid = false;
                    alert("⚠️ Please select at least one appointment date and time.");
                    return false;
                }

                // CHECK FOR CLOSED DATES
                for (let i = 0; i < appointments.length; i++) {
                    if (appointments[i].date && appointments[i].time) {
                        // Check if date is closed
                        if (closedDates.includes(appointments[i].date)) {
                            isValid = false;
                            alert("⚠️ One of your selected dates is a clinic closure date (" + formatDate(appointments[i].date) + "). Please choose a different date.");
                            return false;
                        }
                        
                        // Check for duplicates
                        if (checkDuplicateAppointment(i)) {
                            isValid = false;
                            alert("⚠️ You have duplicate appointments selected. Please choose different dates or times.");
                            return false;
                        }
                    }
                }
            }

        } catch (err) {
            console.error("Validation Error:", err);
            return false;
        }

        return isValid;
    }

    nextBtns.forEach(btn => btn.addEventListener('click', () => {
      const currentStepElement = steps[formStepIndex];
      
      if (validateStep(currentStepElement)) {
        if (formStepIndex < steps.length - 1) {
          formStepIndex++;
          showStep(formStepIndex);

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
    
    showStep(formStepIndex);

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

      if (checkDuplicateAppointment(index)) {
        badge.style.background = '#fee2e2';
        badge.style.color = '#991b1b';
        badge.textContent = 'Duplicate!';
        message.style.display = 'block';
        message.style.background = '#fef2f2';
        message.style.color = '#991b1b';
        message.style.border = '1px solid #fecaca';
        message.style.padding = '8px';
        message.style.borderRadius = '4px';
        message.textContent = '⚠️ This date and time is already selected in another appointment. Please choose a different slot.';
        return;
      }

      badge.style.background = '#d1fae5';
      badge.style.color = '#065f46';
      badge.textContent = 'Available';
      message.style.display = 'none';
    }

    function updateHiddenField() {
      const field = document.getElementById("appointment_dates_json");
      if (!field) return;

      const validAppointments = appointments
        .filter(a => a.date && a.time)
        .map(a => ({ date: a.date, time: a.time }));
      
      field.value = JSON.stringify(validAppointments);
    }

    const dateInputs = document.querySelectorAll(".date-input");
    const timeSelects = document.querySelectorAll(".time-select");

    dateInputs.forEach(input => {
      input.addEventListener("change", (e) => {
        const index = parseInt(e.target.dataset.index);
        appointments[index].date = e.target.value;
        
        for (let i = 0; i < appointments.length; i++) {
          updateSlotDisplay(i);
        }
        
        updateHiddenField();
      });
    });

    timeSelects.forEach(select => {
      select.addEventListener("change", (e) => {
        const index = parseInt(e.target.dataset.index);
        appointments[index].time = e.target.value;
        
        for (let i = 0; i < appointments.length; i++) {
          updateSlotDisplay(i);
        }
        
        updateHiddenField();
      });
    });

    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    const yyyy = tomorrow.getFullYear();
    const mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
    const dd = String(tomorrow.getDate()).padStart(2, '0');
    const minDate = `${yyyy}-${mm}-${dd}`;

    dateInputs.forEach(input => {
        input.setAttribute("min", minDate);
        input.addEventListener('keydown', (e) => e.preventDefault()); 
    });

    if (form) {
      form.addEventListener("submit", async function(e) {
        e.preventDefault();

        const validAppointments = appointments.filter(a => a.date && a.time);
        
        if (validAppointments.length === 0) {
          alert("⚠️ Please select at least one appointment date and time.");
          return false;
        }

        for (let i = 0; i < appointments.length; i++) {
            if (appointments[i].date && appointments[i].time) {
                if (checkDuplicateAppointment(i)) {
                    alert("⚠️ You have duplicate appointments selected. Please choose different dates or times.");
                    return false;
                }
            }
        }

        const formData = new FormData(form);
        
        updateHiddenField();
        
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