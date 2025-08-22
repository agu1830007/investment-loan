document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    form.addEventListener('submit', function(e) {
        const username = form.username.value.trim();
        const email = form.email.value.trim();
        const password = form.password.value;
        const confirm = form.confirm.value;
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!username || !email || !password || !confirm) {
            alert('All fields are required.');
            e.preventDefault();
        } else if (!emailPattern.test(email)) {
            alert('Please enter a valid email address.');
            e.preventDefault();
        } else if (password !== confirm) {
            alert('Passwords do not match.');
            e.preventDefault();
        } else if (password.length < 6) {
            alert('Password must be at least 6 characters.');
            e.preventDefault();
        }
    });

    // Auth message show/hide logic (from auth-messages.js)
    document.querySelectorAll('.auth-success, .auth-error').forEach(function(msg) {
        msg.style.display = 'block';
        setTimeout(function() {
            msg.style.opacity = '0';
            setTimeout(function() { msg.style.display = 'none'; }, 600);
        }, 3500);
    });
});
