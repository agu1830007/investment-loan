document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const username = form.username.value.trim();
        const password = form.password.value;
        if (!username || !password) {
            alert('All fields are required.');
            return;
        }
        const formData = new FormData();
        formData.append('username', username);
        formData.append('password', password);
        fetch('loginserver.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.redirect) {
                window.location.href = data.redirect;
            } else {
                alert(data.message || 'Login failed.');
            }
        })
        .catch(() => alert('Network error. Please try again.'));
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
