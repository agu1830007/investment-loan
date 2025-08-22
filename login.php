<?php
ob_start();
require_once 'loginserver.php';
// IMPORTANT: Do not add any whitespace or output before this PHP block!
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>

    <main style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f8f9fa;">
        <form id="loginForm" method="post" action="" style="background:#fff;padding:2rem 2.5rem;border-radius:1rem;box-shadow:0 2px 12px rgba(0,0,0,0.08);min-width:220px;max-width:350px;">

            <h2 style="text-align:center;margin-bottom:1.5rem;">Login</h2>
           
            <?php if ($success): ?>
                <div class="input-success-message"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($errors['login'])): ?><div class="input-error-message"><?php echo $errors['login']; ?></div><?php endif; ?>
            <input type="text" name="username" placeholder="Username" value="<?php echo htmlspecialchars($username); ?>" class="<?php echo isset($errors['username']) ? 'input-error' : ''; ?>">
            <?php if (isset($errors['username'])): ?><div class="input-error-message"><?php echo $errors['username']; ?></div><?php endif; ?>
            <div class="password-container">
                <input type="password" name="password" id="password" placeholder="Password" class="<?php echo isset($errors['password']) ? 'input-error' : ''; ?>">
                <span class="toggle-password" data-target="password">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </span>
            </div>
           
            <?php if (isset($errors['password'])): ?><div class="input-error-message"><?php echo $errors['password']; ?></div><?php endif; ?>
            <button type="submit" style="width:100%;padding:0.8rem 0;background:#007bff;color:#fff;border:none;border-radius:0.5rem;font-size:1rem;">Login</button>
            <p style="margin-top:1rem;text-align:center;">Don't have an account? <a href="register.php">Register</a></p>
             <div style="text-align:center;margin-top:0.9rem;">
                <a href="forgot_password.php" style="font-size:1.05rem;color:#007bff;font-weight:500;display:inline-flex;align-items:center;gap:0.3em;text-decoration:none;">
                    Forgot Password?
                </a>
            </div>
        </form>
    </main>
    <script src="login.js"></script>
    <script>
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(function(icon) {
        icon.addEventListener('click', function() {
            var targetId = this.getAttribute('data-target');
            var input = document.getElementById(targetId);
            if (input.type === 'password') {
                input.type = 'text';
                this.innerHTML = `<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"22\" height=\"22\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95m3.362-2.568A9.956 9.956 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.965 9.965 0 01-4.293 5.032M15 12a3 3 0 11-6 0 3 3 0 016 0z\"/><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M3 3l18 18\"/></svg>`;
            } else {
                input.type = 'password';
                this.innerHTML = `<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"22\" height=\"22\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M15 12a3 3 0 11-6 0 3 3 0 016 0z\"/><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z\"/></svg>`;
            }
        });
    });
    </script>

</body>
</html>
<?php ob_end_flush(); ?>
