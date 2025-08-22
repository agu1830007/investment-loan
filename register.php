<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="register.css">

</head>

<body>
    <main >
        <form id="registerForm" method="post" action="registerserver.php"
           >

            <h2 style="text-align:center;margin-bottom:1.5rem;">Register</h2>
            <?php
            session_start();
            $errors = [];
            $old = ['username'=>'','email'=>'','phone'=>''];
            if (isset($_SESSION['register_errors'])) {
                $errors = $_SESSION['register_errors'];
                unset($_SESSION['register_errors']);
            }
            if (isset($_SESSION['register_old'])) {
                $old = $_SESSION['register_old'];
                unset($_SESSION['register_old']);
            }
            if (isset($_SESSION['success'])) {
                echo '<div class="auth-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
                unset($_SESSION['success']);
            }
            ?>
            <input type="text" name="username" placeholder="Username" required value="<?php echo htmlspecialchars($old['username'] ?? ''); ?>" class="<?php echo isset($errors['username']) ? 'input-error' : ''; ?>">
            <?php if (isset($errors['username'])): ?><div class="input-error-message"><?php echo $errors['username']; ?></div><?php endif; ?>
            <input type="email" name="email" placeholder="Email" required value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" class="<?php echo isset($errors['email']) ? 'input-error' : ''; ?>">
            <?php if (isset($errors['email'])): ?><div class="input-error-message"><?php echo $errors['email']; ?></div><?php endif; ?>
            <input type="text" name="phone" placeholder="Phone number" required value="<?php echo htmlspecialchars($old['phone'] ?? ''); ?>" class="<?php echo isset($errors['phone']) ? 'input-error' : ''; ?>">
            <?php if (isset($errors['phone'])): ?><div class="input-error-message"><?php echo $errors['phone']; ?></div><?php endif; ?>
            <div class="password-container">
                <input type="password" name="password" id="password" placeholder="Password" required class="<?php echo isset($errors['password']) ? 'input-error' : ''; ?>">
                <span class="toggle-password" data-target="password">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </span>
            </div>
            <?php if (isset($errors['password'])): ?><div class="input-error-message"><?php echo $errors['password']; ?></div><?php endif; ?>
            <div class="password-container">
                <input type="password" name="confirm" id="confirm" placeholder="Confirm Password" required class="<?php echo isset($errors['confirm']) ? 'input-error' : ''; ?>">
                <span class="toggle-password" data-target="confirm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </span>
            </div>
            <?php if (isset($errors['confirm'])): ?><div class="input-error-message"><?php echo $errors['confirm']; ?></div><?php endif; ?>
            <?php if (isset($errors['register'])): ?><div class="input-error-message"><?php echo $errors['register']; ?></div><?php endif; ?>
            <button type="submit"
                style="width:100%;padding:0.8rem 0;background:#007bff;color:#fff;border:none;border-radius:0.5rem;font-size:1rem;">Register</button>
            <p style="margin-top:1rem;text-align:center;">Already have an account? <a href="login.php">Login</a></p>
        </form>
    </main>

    <script src="register.js"></script>
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

