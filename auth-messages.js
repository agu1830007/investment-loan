// Show and auto-hide auth messages
window.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.auth-success, .auth-error').forEach(function(msg) {
        msg.style.display = 'block';
        setTimeout(function() {
            msg.style.opacity = '0';
            setTimeout(function() { msg.style.display = 'none'; }, 600);
        }, 3500);
    });
});
