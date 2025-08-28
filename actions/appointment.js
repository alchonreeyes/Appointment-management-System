const steps = document.querySelectorAll(".form-step");
const nextBtns = document.querySelectorAll(".next-btn");
const prevBtns = document.querySelectorAll(".prev-btn");

let formStepIndex = 0;

nextBtns.forEach(btn => {
    btn.addEventListener("click", () => {
        steps[formStepIndex].classList.remove("active");
        formStepIndex++;
        steps[formStepIndex].classList.add("active");
    });
});

prevBtns.forEach(btn => {
    btn.addEventListener("click", () => {
        steps[formStepIndex].classList.remove("active");
        formStepIndex--;
        steps[formStepIndex].classList.add("active");
    });
});
