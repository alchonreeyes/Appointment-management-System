
const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
        if(entry.isIntersecting){
            entry.target.classList.add("show");
        }
    });
}, {
    threshold: 0.1
});

const headers = document.querySelectorAll("appointment", "ishihara", "medical");
   header.forEach((header) => {
    observer.observe(header);
   });

     