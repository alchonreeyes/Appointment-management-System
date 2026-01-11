(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    let closedDates = [];
    let flatpickrInstances = [];
    
    // ========================================
    // FETCH CLOSED DATES FROM ADMIN
    // ========================================
    async function fetchClosedDates() {
      try {
        const response = await fetch('../actions/get-closed-dates.php');
        const data = await response.json();
        
        if (data.success) {
          closedDates = data.closed_dates;
          console.log('✅ Loaded closed dates:', closedDates);
          
          // Initialize Flatpickr after loading closed dates
          initializeFlatpickr();
        }
      } catch (error) {
        console.error('❌ Failed to fetch closed dates:', error);
        // Still initialize Flatpickr even if fetch fails
        initializeFlatpickr();
      }
    }
    
    // ========================================
    // INITIALIZE FLATPICKR DATE PICKERS
    // ========================================
    function initializeFlatpickr() {
  const dateInputs = document.querySelectorAll(".date-input");
  const today = new Date();
  const tomorrow = new Date(today);
  tomorrow.setDate(tomorrow.getDate() + 1);
  
  dateInputs.forEach(input => {
    const index = parseInt(input.dataset.index);
    const timeSelect = document.querySelector(`.time-select[data-index="${index}"]`);
    
    const flatpickrInstance = flatpickr(input, {
      minDate: tomorrow,
      dateFormat: "Y-m-d",
      disable: [
        // Disable Sundays
        function(date) {
          return (date.getDay() === 0);
        },
        // Disable closed dates from admin
        ...closedDates
      ],
      onChange: function(selectedDates, dateStr) {
        appointments[index].date = dateStr;
        
        // Update time options when date changes
        if (timeSelect) {
          updateTimeOptions(input, timeSelect);
        }
        
        // Update all displays
        for (let i = 0; i < appointments.length; i++) {
          updateSlotDisplay(i);
        }
        updateHiddenField();
      },
      onDayCreate: function(dObj, dStr, fp, dayElem) {
        const dateStr = dayElem.dateObj.toISOString().split('T')[0];
        
        // Gray out Sundays
        if (dayElem.dateObj.getDay() === 0) {
          dayElem.classList.add('flatpickr-disabled-sunday');
        }
        
        // Gray out closed dates
        if (closedDates.includes(dateStr)) {
          dayElem.classList.add('flatpickr-disabled-closed');
        }
      }
    });
    
    flatpickrInstances.push(flatpickrInstance);
  });
}
// Add this RIGHT AFTER the initializeFlatpickr() function
async function updateTimeOptions(dateInput, timeSelect) {
  const date = dateInput.value;
  const serviceId = document.querySelector('input[name="service_id"]')?.value;
  
  if (!date || !serviceId) return;
  
  console.log('🔄 Updating time options for:', date);
  
  try {
    const response = await fetch(`../actions/get_available_times.php?service_id=${serviceId}&date=${date}`);
    const data = await response.json();
    
    console.log('⏰ Available times data:', data);
    
    if (data.success) {
      // Reset all options first
      Array.from(timeSelect.options).forEach(option => {
        if (option.value === '') return;
        option.disabled = false;
        option.textContent = option.textContent.replace(' (Booked)', '');
        option.style.color = '';
      });

      // Disable unavailable times
      Array.from(timeSelect.options).forEach(option => {
        if (option.value === '') return;
        
        const isUnavailable = data.unavailable_times.includes(option.value);
        
        if (isUnavailable) {
          option.disabled = true;
          option.textContent = option.textContent.replace(' (Booked)', '') + ' (Booked)';
          option.style.color = '#999';
          option.style.textDecoration = 'line-through';
        }
      });
      
      // If currently selected time is now unavailable, clear it
      if (data.unavailable_times.includes(timeSelect.value)) {
        timeSelect.value = '';
        const index = timeSelect.getAttribute('data-index');
        if (index !== null) {
          appointments[parseInt(index)].time = '';
          updateSlotDisplay(parseInt(index));
        }
      }
    }
  } catch (error) {
    console.error('❌ Error updating time options:', error);
  }
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

    const steps = Array.from(document.querySelectorAll('.form-step'));
    const nextBtns = Array.from(document.querySelectorAll('.next-btn'));
    const prevBtns = Array.from(document.querySelectorAll('.prev-btn'));
    const progressSteps = Array.from(document.querySelectorAll('.progress-step'));
    const progressLines = Array.from(document.querySelectorAll('.progress-line'));
    const hiddenField = document.getElementById("appointment_dates_json");
    const form = document.getElementById("appointmentForm");

    let formStepIndex = 0;
    
    let appointments = [
  { date: "", time: "", isFullyBooked: false },
  { date: "", time: "", isFullyBooked: false },
  { date: "", time: "", isFullyBooked: false }
];
    
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
/* 

i did this instead of claude suggestion but i revised it

1:am 
-- 1. Create the missing table
CREATE TABLE IF NOT EXISTS `appointment_slots` (
  `slot_id` int(11) NOT NULL AUTO_INCREMENT,
  `service_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL, 
  `available_slots` int(11) NOT NULL DEFAULT 5,
  PRIMARY KEY (`slot_id`),
  -- This makes sure you can't have two rows for "Dental - Jan 10 - 10:00AM"
  UNIQUE KEY `uniq_service_date_time` (`service_id`, `appointment_date`, `appointment_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

*/
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
      
      if (!currentAppt.date || !currentAppt.time) return false;

      for (let i = 0; i < appointments.length; i++) {
        if (i === currentIndex) continue;
        if (appointments[i].date === currentAppt.date && appointments[i].time === currentAppt.time) {
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

            for (let i = 0; i < appointments.length; i++) {
                if (appointments[i].date && appointments[i].time) {
                    // Check closed dates
                    if (closedDates.includes(appointments[i].date)) {
                        isValid = false;
                        alert("⚠️ The clinic is closed on " + formatDate(appointments[i].date) + ". Please choose a different date.");
                        return false;
                    }
                    
                    // Check duplicates
                    if (checkDuplicateAppointment(i)) {
                        isValid = false;
                        alert("⚠️ You have duplicate appointments selected. Please choose different dates or times.");
                        return false;
                    }
                    
                    // ⭐ NEW: Check if slot is fully booked
                    if (appointments[i].isFullyBooked === true) {
                        isValid = false;
                        alert("⚠️ One or more selected time slots are fully booked. Please select different times.");
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

    // Replace this function (around line 380 in your code)
function updateSlotDisplay(index) {
  const appt = appointments[index];
  const badge = document.getElementById(`slot-badge-${index}`);
  const message = document.getElementById(`slot-message-${index}`);
  
  if (!badge || !message) return;
  
  // Reset booked flag
  if (!appointments[index].isFullyBooked) {
    appointments[index].isFullyBooked = false;
  }
  
  // First check if inputs are empty
  if (!appt.date || !appt.time) {
    badge.style.background = '#e5e7eb';
    badge.style.color = '#6b7280';
    badge.textContent = 'Select date & time';
    message.style.display = 'none';
    appointments[index].isFullyBooked = false; // Not booked if empty
    return;
  }

  // Check for duplicates in current form
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
    message.textContent = '⚠️ This date and time is already selected in another appointment.';
    appointments[index].isFullyBooked = true; // Treat duplicates as blocked
    return;
  }

  // NOW CHECK SERVER AVAILABILITY
  badge.style.background = '#fef3c7';
  badge.style.color = '#92400e';
  badge.textContent = 'Checking...';
  
  const serviceId = document.querySelector('input[name="service_id"]').value;
  const url = `../actions/check_slot.php?service_id=${serviceId}&date=${appt.date}&time=${appt.time}`;
  
  console.log('🔍 Checking slot:', url);
  
  fetch(url)
    .then(response => {
      console.log('📡 Response status:', response.status);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      return response.json();
    })
    .then(data => {
      console.log('✅ Slot data:', data);
      
      if (data.available) {
        badge.style.background = '#d1fae5';
        badge.style.color = '#065f46';
        badge.textContent = `Available (${data.remaining} left)`;
        message.style.display = 'none';
        appointments[index].isFullyBooked = false; // Available!
      } else {
        badge.style.background = '#fee2e2';
        badge.style.color = '#991b1b';
        badge.textContent = 'Fully Booked';
        message.style.display = 'block';
        message.style.background = '#fef2f2';
        message.style.color = '#991b1b';
        message.style.padding = '8px';
        message.style.borderRadius = '4px';
        message.textContent = '⚠️ ' + (data.message || 'This slot is fully booked. Please select a different time.');
        appointments[index].isFullyBooked = true; // BLOCKED!
      }
    })
    .catch(err => {
      console.error('❌ Slot check error:', err);
      badge.style.background = '#fef3c7';
      badge.style.color = '#92400e';
      badge.textContent = 'Error checking';
      message.style.display = 'block';
      message.style.background = '#fffbeb';
      message.style.color = '#92400e';
      message.style.padding = '8px';
      message.style.borderRadius = '4px';
      message.textContent = 'Unable to check availability. Please try again.';
      appointments[index].isFullyBooked = true; // Treat errors as blocked for safety
    });
}

    function updateHiddenField() {
      const field = document.getElementById("appointment_dates_json");
      if (!field) return;

      const validAppointments = appointments
        .filter(a => a.date && a.time)
        .map(a => ({ date: a.date, time: a.time }));
      
      field.value = JSON.stringify(validAppointments);
    }

    const timeSelects = document.querySelectorAll(".time-select");

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

   if (form) {
  form.addEventListener("submit", async function(e) {
    e.preventDefault();

    const validAppointments = appointments.filter(a => a.date && a.time);
    
    if (validAppointments.length === 0) {
      alert("⚠️ Please select at least one appointment date and time.");
      return false;
    }

    // Check for duplicates
    for (let i = 0; i < appointments.length; i++) {
        if (appointments[i].date && appointments[i].time) {
            if (checkDuplicateAppointment(i)) {
                alert("⚠️ You have duplicate appointments selected. Please choose different dates or times.");
                return false;
            }
            
            // ⭐ NEW: Check if any slot is fully booked
            if (appointments[i].isFullyBooked === true) {
                alert("⚠️ One or more selected time slots are fully booked. Please select different times and try again.");
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
 // Check slot availability when date or time changes
function checkSlotAvailability(rowIndex) {
    const dateInput = document.querySelector(`.date-input[data-index="${rowIndex}"]`);
    const timeSelect = document.querySelector(`.time-select[data-index="${rowIndex}"]`);
    const badge = document.getElementById(`slot-badge-${rowIndex}`);
    const message = document.getElementById(`slot-message-${rowIndex}`);
    
    const date = dateInput ? dateInput.value : '';
    const time = timeSelect ? timeSelect.value : '';
    const serviceIdInput = document.querySelector('input[name="service_id"]');
    const serviceId = serviceIdInput ? serviceIdInput.value : '';
    
    if (!date || !time || !serviceId) {
        badge.textContent = 'Select date & time';
        badge.className = 'slot-badge';
        if (message) message.style.display = 'none';
        return;
    }
    
    // Show loading state
    badge.textContent = 'Checking...';
    badge.className = 'slot-badge badge-checking';
    
    // Build URL - IMPORTANT: Check the correct path
    const url = `../actions/check_slot.php?service_id=${serviceId}&date=${date}&time=${time}`;
    
    console.log('Checking slot:', url); // Debug log
    
    // AJAX call to check availability
    fetch(url)
        .then(response => {
            console.log('Response status:', response.status); // Debug log
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Slot data:', data); // Debug log
            
            if (data.available) {
                badge.textContent = `Available (${data.remaining} left)`;
                badge.className = 'slot-badge badge-available';
                if (message) message.style.display = 'none';
            } else {
                badge.textContent = 'Fully Booked';
                badge.className = 'slot-badge badge-full';
                if (message) {
                    message.textContent = data.message || 'This slot is no longer available';
                    message.style.display = 'block';
                    message.style.backgroundColor = '#fee2e2';
                    message.style.color = '#991b1b';
                    message.style.padding = '8px';
                    message.style.borderRadius = '4px';
                }
            }
        })
        .catch(err => {
            console.error('Slot check error:', err); // Debug log
            badge.textContent = 'Error checking';
            badge.className = 'slot-badge badge-error';
            if (message) {
                message.textContent = 'Unable to check availability. Please try again.';
                message.style.display = 'block';
                message.style.backgroundColor = '#fef3c7';
                message.style.color = '#92400e';
            }
        });
}

// Initialize event listeners when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing slot checkers...'); // Debug log
    
    // Attach to existing inputs
    document.querySelectorAll('.date-input, .time-select').forEach(input => {
        input.addEventListener('change', function() {
            const index = this.getAttribute('data-index');
            console.log('Input changed for row:', index); // Debug log
            checkSlotAvailability(index);
        });
    });
    
    // Also check on page load if values are already selected
    document.querySelectorAll('.date-input').forEach(input => {
        const index = input.getAttribute('data-index');
        if (input.value) {
            checkSlotAvailability(index);
        }
    });
});
// Disable booked time slots dynamically
async function updateTimeOptions(dateInput, timeSelect) {
  const date = dateInput.value;
  const serviceId = document.querySelector('input[name="service_id"]').value;
  
  if (!date || !serviceId) return;
  
  try {
    const response = await fetch(`../actions/get_available_times.php?service_id=${serviceId}&date=${date}`);
    const data = await response.json();
    
    if (data.success) {
      // Disable unavailable times
      Array.from(timeSelect.options).forEach(option => {
        if (option.value === '') return; // Skip "Select Time" option
        
        const isUnavailable = data.unavailable_times.includes(option.value);
        option.disabled = isUnavailable;
        
        if (isUnavailable) {
          option.textContent = option.textContent.replace(' (Booked)', '') + ' (Booked)';
          option.style.color = '#999';
        } else {
          option.textContent = option.textContent.replace(' (Booked)', '');
          option.style.color = '';
        }
      });
      
      // If currently selected time is now unavailable, clear it
      if (data.unavailable_times.includes(timeSelect.value)) {
        timeSelect.value = '';
      }
    }
  } catch (error) {
    console.error('Error updating time options:', error);
  }
}
})();