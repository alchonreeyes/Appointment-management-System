const steps = document.querySelectorAll(".form-step");
const nextBtns = document.querySelectorAll(".next-btn");
const prevBtns = document.querySelectorAll(".prev-btn");

const progressSteps = document.querySelectorAll(".progress-step");
const progressLines = document.querySelectorAll(".progress-line");

let formStepIndex = 0; // This is our single source of truth

// Function to update progress bar
function updateProgress(stepIndex) {
    progressSteps.forEach((step, i) => {
            if (i < stepIndex) {
            step.classList.add("completed");
            step.classList.remove("active");
        } else if (i === stepIndex) {
            step.classList.add("active");
            step.classList.remove("completed");
        } else {
            step.classList.remove("active", "completed");
        }
    });

    progressLines.forEach((line, i) => {
        if (i < stepIndex) {
            line.classList.add("completed");
        } else {
            line.classList.remove("completed");
        }
    });
}

// Show the current form step
function showStep(index) {
    steps.forEach((s, i) => {
        s.classList.toggle("active", i === index);
    });
    updateProgress(index);
}

// Next button
nextBtns.forEach(btn => {
    btn.addEventListener("click", () => {
        if (formStepIndex < steps.length - 1) {
            formStepIndex++;
            showStep(formStepIndex);
        }
    });
});

// Prev button
prevBtns.forEach(btn => {
    btn.addEventListener("click", () => {
        if (formStepIndex > 0) {
            formStepIndex--;
            showStep(formStepIndex);
        }
    });
});

// Initialize
showStep(formStepIndex);

const dateButtons = document.querySelectorAll(".date-strip button");
const timeButtons = document.querySelectorAll(".time-slots button");
const nativeDate = document.getElementById("nativeDate");
const nextAvailable = document.getElementById("nextAvailable");

const appointmentDate = document.getElementById("appointmentDate");
const appointmentTime = document.getElementById("appointmentTime");

let selectedDate = "";
let selectedTime = "";


// Handle Native Date Picker
nativeDate.addEventListener("change", (e) => {
    selectedDate = e.target.value;
    appointmentDate.value = selectedDate;

    // Sync strip (regenerate it for the new week)
    
    updateNextAvailable();
});

// Handle Time Slots
timeButtons.forEach(btn => {
    btn.addEventListener("click", () => {
        timeButtons.forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        selectedTime = btn.dataset.time;

        appointmentTime.value = selectedTime;
        updateNextAvailable();
    });
});

// Update "Next Available" text
function updateNextAvailable() {
    if (selectedDate && selectedTime) {
        // Use a more reliable way to parse the date to avoid timezone issues
        const [year, month, day] = selectedDate.split('-').map(Number);
        const dateObj = new Date(year, month - 1, day);
        let readableDate = dateObj.toDateString();
        nextAvailable.textContent = `Next Available: ${readableDate} ${selectedTime}`;
    } else if (selectedDate) {
        const [year, month, day] = selectedDate.split('-').map(Number);
        const dateObj = new Date(year, month - 1, day);
        let readableDate = dateObj.toDateString();
        nextAvailable.textContent = `Selected Date: ${readableDate}`;
    } else {
        nextAvailable.textContent = "Please select a date and time.";
    }
}

    const dateStrip = document.querySelector(".date-strip");

    // Generate week strip based on selected date
    function generateDateStrip(baseDateStr) {
            dateStrip.innerHTML = ""; // clear existing strip

            const [year, month, day] = baseDateStr.split('-').map(Number);
            let baseDate = new Date(year, month - 1, day);

            let dayOfWeek = baseDate.getDay(); // 0=Sun, 1=Mon...
            let diff = baseDate.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1);
            let monday = new Date(baseDate.setDate(diff));

            // loop 7 days (Mon → Sun)
            for (let i = 0; i < 7; i++) {
                    let d = new Date(monday);
                    d.setDate(monday.getDate() + i);
                    const dateValue = d.toISOString().split("T")[0];

                    let div = document.createElement("div");
                    div.className = "date-box";
                    div.textContent = d.toLocaleDateString("en-US", { weekday: "short", day: "numeric" });
                    div.dataset.date = dateValue;

                    // highlight the date that was originally selected
                    if (dateValue === baseDateStr) {
                            div.classList.add("selected");
                    }

                    // gray out weekends
                    if (d.getDay() === 0 || d.getDay() === 6) {
                            div.classList.add("weekend");
                    }

                    // add click event to choose
                    div.addEventListener("click", function() {
                            // Update state and UI
                            selectedDate = this.dataset.date;
                            appointmentDate.value = selectedDate;
                            nativeDate.value = selectedDate; // sync with picker

                            // Update visual selection
                            document.querySelectorAll(".date-box").forEach(el => el.classList.remove("selected"));
                            this.classList.add("selected");

                            // Update the "Next Available" text
                            updateNextAvailable();
                    });

                    dateStrip.appendChild(div);
            }
    }
// ==========================
// ✅ FIX FOR MEDICAL PURPOSE RADIO BUTTONS
// ==========================
document.addEventListener("DOMContentLoaded", function() {
  const radios = document.querySelectorAll('input[name="certificate_purpose"]');
  const otherInput = document.querySelector('input[name="certificate_other"]');

  if (!radios.length) return; // Ignore if not on medical page

  // Hide "Other" text by default
  otherInput.style.display = "none";

  radios.forEach(radio => {
    radio.addEventListener("change", () => {
      if (radio.value === "Other") {
        otherInput.style.display = "block";
        otherInput.required = true;
      } else {
        otherInput.style.display = "none";
        otherInput.value = "";
        otherInput.required = false;
      }
    });
  });
});



document.getElementById("appointmentForm").addEventListener("submit", function() {
  // Find selected radio button manually
  const selected = document.querySelector('input[name="certificate_purpose"]:checked');
  if (selected) {
    selected.disabled = false; // make sure it's active when posting
  }
});
// ===============================
// ✅ Remaining Slot Display
// ===============================
function updateRemainingSlots(selectedDate) {
 async function checkSlot(date) {
  const serviceId = document.getElementById('serviceId').value;
  const data = new URLSearchParams({ date, service_id: serviceId });

  const res = await fetch('/actions/check-slot.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: data.toString()
  });
  const j = await res.json();
  if (!j.ok) {
    console.error('Slot check failed', j);
    return { ok:false };
  }
  return j; // {ok:true, remaining: n, ...}
}

// Example usage: when nativeDate changes:
document.getElementById('nativeDate').addEventListener('change', async (e) => {
  const date = e.target.value;
  const info = await checkSlot(date);
  if (info.ok) {
    document.getElementById('remainingSlotsP').textContent = `Remaining slots: ${info.remaining}`;
    if (info.remaining === 0) {
      showBookingPopup('Fully Booked', 'No more slots for selected date and service.');
      // disable submit button
      document.querySelector('button[type="submit"]').disabled = true;
    } else {
      document.querySelector('button[type="submit"]').disabled = false;
    }
  }
});
}

// ===============================
// ✅ SLOT CHECKER (fetch from PHP)
// ===============================
const remainingDisplay = document.createElement('p');
remainingDisplay.id = "remainingSlots";
remainingDisplay.style.fontWeight = "bold";
remainingDisplay.style.marginTop = "10px";
document.querySelector(".time-slots").after(remainingDisplay);

async function checkSlots() {
  const date = document.getElementById("nativeDate").value;
  const serviceId = document.querySelector('input[name="service_id"]').value;

  if (!date || !serviceId) return;

  try {
    const response = await fetch("../actions/check_slots.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `service_id=${serviceId}&appointment_date=${date}`
    });

    const data = await response.json();

    if (data.success) {
      remainingDisplay.textContent = `Remaining Slots: ${data.remaining}`;
      if (data.remaining <= 0) {
        showBookingPopup("Fully Booked", "Sorry, this date is already full. Please pick another date.");
        document.querySelector('button[type="submit"]').disabled = true;
      } else {
        document.querySelector('button[type="submit"]').disabled = false;
      }
    } else {
      remainingDisplay.textContent = "Error checking slots.";
    }
  } catch (error) {
    remainingDisplay.textContent = "Error connecting to server.";
  }
}

// 🔹 Run check whenever the date changes
document.getElementById("nativeDate").addEventListener("change", checkSlots);
