
document.addEventListener('DOMContentLoaded', function() {
    // Tab navigation logic
    const navLinks = document.querySelectorAll('.dashboard-nav a[href^="#"]');
    const sections = document.querySelectorAll('.dashboard-section');
    function showSection(id) {
        sections.forEach(sec => {
            if (sec.id === id) {
                sec.classList.add('active');
                sec.style.display = '';
            } else {
                sec.classList.remove('active');
                sec.style.display = 'none';
            }
        });
        navLinks.forEach(link => {
            link.classList.toggle('active', link.getAttribute('href') === '#' + id);
        });
    }
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.getAttribute('href').startsWith('#')) {
                e.preventDefault();
                showSection(this.getAttribute('href').substring(1));
            }
        });
    });
    // Show the first visible section by default
    let firstSection = document.querySelector('.dashboard-section');
    if (firstSection) {
        showSection(firstSection.id);
    }

   
});


    
           