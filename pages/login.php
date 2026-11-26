<section class="simple-auth login-auth">
    <h1>Login</h1>
        <form method="post" class="simple-auth-form">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf" value="<?= e(auth_csrf()) ?>">
            <label class="auth-field"><span class="auth-sr-only">Email atau nomor WhatsApp</span><input name="identity" required autocomplete="username" placeholder="Email (Gmail) atau nomor WhatsApp*"></label>
            <label class="auth-field password-field"><span class="auth-sr-only">Password</span><input name="password" type="password" required autocomplete="current-password" placeholder="Password*"><button type="button" class="password-toggle" aria-label="Tampilkan password" aria-pressed="false"><i class="bi bi-eye-slash" aria-hidden="true"></i></button></label>
            <a class="forgot-link" href="?page=forgot-password">Forgot Password?</a>
            <button class="auth-submit" type="submit">LOGIN</button>
        </form>
        <p class="auth-account-link">Belum punya akun? <a href="?page=register">Register</a></p>
</section>
