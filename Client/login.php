<?php
require_once __DIR__ . '/_bootstrap.php';

$currentUser = client_current_user($pdo);
if ($currentUser !== null) {
    header('Location: index.php');
    exit;
}
unset($_SESSION['client_user_id']);

$loginError = '';
$emailValue = '';
$signupMessage = client_take_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = $_POST['email'] ?? '';
    $emailValue = is_string($emailInput) ? trim($emailInput) : '';
    $email = filter_var($emailValue, FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!client_csrf_is_valid($_POST['_csrf'] ?? null)) {
        $loginError = 'Your session has expired. Refresh the page and try again.';
    } elseif ($email === false || !is_string($password) || $password === '') {
        $loginError = 'Enter a valid email address and password.';
    } else {
        $statement = $pdo->prepare(
            "SELECT u.user_id, u.password
             FROM users u
             INNER JOIN clients c ON c.user_id = u.user_id
             WHERE LOWER(u.email) = LOWER(:email) AND LOWER(TRIM(u.role)) = 'client'
             "
        );
        $statement->execute(['email' => $email]);
        $account = $statement->fetch();

        if (is_array($account) && password_verify($password, (string) $account['password'])) {
            session_regenerate_id(true);
            $_SESSION['client_user_id'] = (int) $account['user_id'];
            unset($_SESSION['client_csrf_token']);
            header('Location: index.php');
            exit;
        }

        $loginError = 'Invalid email or password, or this is not a Client account.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="min-h-screen bg-[#020617] text-slate-100 antialiased selection:bg-amber-500 selection:text-slate-950">
    <main class="flex min-h-screen items-center justify-center bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-950 via-[#030712] to-black p-4">
        <section class="w-full max-w-[560px] rounded-[22px] border border-amber-500/20 bg-slate-900/85 p-10 shadow-2xl backdrop-blur-md max-[480px]:p-6">
            <div class="mb-8 text-center">
                <div class="mb-4 inline-flex h-[72px] w-[72px] items-center justify-center rounded-full border border-amber-500/40 bg-amber-500/5 text-amber-400">
                    <i data-lucide="shield-check" class="h-9 w-9"></i>
                </div>
                <h1 class="text-3xl font-bold tracking-wide text-white">Client Portal</h1>
                <p class="mt-2 text-base text-slate-400">Sign in to access your legal cases</p>
            </div>

            <?php if ($signupMessage !== ''): ?>
                <p role="status" class="mb-4 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-sm text-emerald-200"><?php echo client_h($signupMessage); ?></p>
            <?php endif; ?>
            <?php if ($loginError !== ''): ?>
                <p role="alert" class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200"><?php echo client_h($loginError); ?></p>
            <?php endif; ?>

            <form id="client-login-form" method="post" action="login.php" autocomplete="off" class="space-y-5">
                <?php client_csrf_field(); ?>
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium uppercase tracking-wider text-amber-400">Email Address</label>
                    <div class="relative">
                        <i data-lucide="mail" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500"></i>
                        <input id="email" name="email" type="email" required autocomplete="username" value="<?php echo client_h($emailValue); ?>" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-3 pl-12 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-amber-500" placeholder="name@gmail.com">
                    </div>
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium uppercase tracking-wider text-amber-400">Password</label>
                    <div class="relative">
                        <i data-lucide="lock" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500"></i>
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-3 pl-12 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-amber-500" placeholder="Enter your password">
                    </div>
                </div>
                <button type="submit" class="flex h-[55px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-600 to-amber-500 text-base font-bold text-slate-950 transition hover:from-amber-500 hover:to-amber-400">
                    Log In <i data-lucide="arrow-right" class="h-5 w-5"></i>
                </button>
            </form>

            <p class="mt-8 text-center text-base text-slate-400">
                Don't have an account?
                <a href="signup.php" class="font-semibold text-amber-400 transition hover:text-amber-300">Sign up for portal access</a>
            </p>
        </section>
    </main>
    <script>
        if (window.lucide) window.lucide.createIcons();

        const form = document.getElementById('client-login-form');
        const submittedKey = 'client-login-submitted';
        <?php if ($loginError !== ''): ?>
            sessionStorage.removeItem(submittedKey);
        <?php endif; ?>
        form.addEventListener('submit', () => sessionStorage.setItem(submittedKey, '1'));
        window.addEventListener('pageshow', () => {
            if (sessionStorage.getItem(submittedKey) === '1') {
                form.reset();
                sessionStorage.removeItem(submittedKey);
            }
        });
    </script>
</body>
</html>