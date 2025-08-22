// Responsive nav toggle and smooth scroll

document.addEventListener('DOMContentLoaded', function() {
    // Hamburger nav toggle with SVG icons
    const navToggle = document.getElementById('navToggle');
    const mainNav = document.getElementById('mainNav');
    if(navToggle && mainNav) {
        const hamburgerIcon = navToggle.querySelector('.hamburger-icon');
        const closeIcon = navToggle.querySelector('.close-icon');
        function setNavState(open) {
            if(open) {
                mainNav.classList.add('show');
                hamburgerIcon.style.display = 'none';
                closeIcon.style.display = 'inline-block';
            } else {
                mainNav.classList.remove('show');
                hamburgerIcon.style.display = 'inline-block';
                closeIcon.style.display = 'none';
            }
        }
        navToggle.addEventListener('click', function() {
            const isOpen = mainNav.classList.contains('show');
            setNavState(!isOpen);
        });
        // Hide nav on link click (mobile UX)
        mainNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                if(window.innerWidth <= 900) setNavState(false);
            });
        });
        // Ensure correct icon on resize
        window.addEventListener('resize', function() {
            if(window.innerWidth > 900) {
                hamburgerIcon.style.display = 'none';
                closeIcon.style.display = 'none';
            } else {
                setNavState(mainNav.classList.contains('show'));
            }
        });
        // Initial state
        setNavState(false);
    }
    // Smooth scroll for nav links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const href = this.getAttribute('href');
        // If href is exactly '#', treat as dropdown parent
        if (href === '#') {
            e.preventDefault();
            const parentLi = this.closest('.dropdown');
            if (parentLi) {
                parentLi.classList.toggle('open');
            }
            return;
        }
        // Only smooth scroll if href is a valid section (not just '#')
        if (href && href.length > 1 && href.startsWith('#')) {
            const target = document.querySelector(href);
            if(target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
            }
        }
        // Otherwise, let normal navigation happen (e.g., register.php)
    });
    });
// Optional: Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    document.querySelectorAll('.dropdown.open').forEach(function(drop) {
        if (!drop.contains(e.target)) {
            drop.classList.remove('open');
        }
    });
});
    // Contact form validation
    const contactForm = document.querySelector('#contact form');
    if(contactForm) {
        contactForm.addEventListener('submit', function(e) {
            const name = contactForm.name.value.trim();
            const email = contactForm.email.value.trim();
            const phone = contactForm.phone.value.trim();
            const message = contactForm.message.value.trim();
            if(!name || !email || !phone || !message) {
                alert('Please fill in all fields.');
                e.preventDefault();
            }
        });
    }
});
