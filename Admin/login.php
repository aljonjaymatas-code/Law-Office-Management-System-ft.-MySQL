<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/_auth.php';
session_start();

$userRole = $_SESSION['admin_portal_role'] ?? '';
if (isset($_SESSION['admin_portal_user_id'])) {
    $sessionUserId = filter_var($_SESSION['admin_portal_user_id'], FILTER_VALIDATE_INT);
    if ($sessionUserId !== false && $sessionUserId !== null && $sessionUserId > 0) {
        $sessionAccountStatement = $pdo->prepare('SELECT role FROM users WHERE user_id = :user_id');
        $sessionAccountStatement->execute(['user_id' => $sessionUserId]);
        $sessionAccountRole = $sessionAccountStatement->fetchColumn();
        if (
            is_string($sessionAccountRole)
            && admin_portal_role_for_account_role($sessionAccountRole) === $userRole
        ) {
            header('Location: index.php');
            exit;
        }
    }

    unset($_SESSION['admin_portal_role'], $_SESSION['admin_portal_user_id']);
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if ($email === false || $email === null || !is_string($password)) {
        $loginError = 'Enter a valid email address and password.';
    } else {
        $statement = $pdo->prepare('SELECT user_id, password, role FROM users WHERE LOWER(email) = LOWER(:email)');
        $statement->execute(['email' => trim($email)]);
        $user = $statement->fetch();
        $userRole = is_array($user) ? admin_portal_role_for_account_role((string) $user['role']) : null;

        if (is_array($user) && $userRole !== null && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_portal_role'] = $userRole;
            $_SESSION['admin_portal_user_id'] = (int) $user['user_id'];
            header('Location: index.php');
            exit;
        }

        $loginError = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attorney's Portal - Login</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-[#020617] text-slate-100 antialiased selection:bg-amber-500 selection:text-slate-950">
    <main class="min-h-screen bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-950 via-[#030712] to-black flex items-center justify-center p-4">
        <div class="w-full max-w-[560px] bg-slate-900/80 backdrop-blur-md rounded-[22px] shadow-2xl border border-amber-500/20 p-10 max-[480px]:p-6">
            <div class="text-center mb-8">
                <div class="inline-flex p-3 bg-amber-500/10 text-amber-400 rounded-full mb-3 border border-amber-500/30 gold-glow-sm">
                    <i data-lucide="scale" class="w-8 h-8"></i>
                </div>
                <h1 class="text-2xl font-bold text-white tracking-wide">Attorney's Portal</h1>
                <p class="text-slate-400 text-sm mt-1">Sign in to manage firm operations or legal matters.</p>
            </div>

            <?php if ($loginError !== ''): ?>
                <p role="alert" class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-300">
                    <?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php endif; ?>

            <form id="login-form" method="post" action="login.php" autocomplete="off" class="space-y-4">
                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-amber-400/80 mb-1">Email Address</label>
                    <div class="relative">
                        <span class="absolute left-3 top-3 text-slate-500"><i data-lucide="mail" class="w-5 h-5"></i></span>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            required
                            autocomplete="username"
                            class="w-full bg-slate-950/60 border border-slate-800 rounded-lg pl-10 pr-4 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500 transition"
                            placeholder="name@gmail.com">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-amber-400/80 mb-1">Password</label>
                    <div class="relative">
                        <span class="absolute left-3 top-3 text-slate-500"><i data-lucide="lock" class="w-5 h-5"></i></span>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="w-full bg-slate-950/60 border border-slate-800 rounded-lg pl-10 pr-4 py-2.5 text-white text-sm focus:outline-none focus:border-amber-500 transition"
                            placeholder="••••••••••••">
                    </div>
                </div>

                <button
                    type="submit"
                    class="w-full mt-2 bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-400 text-slate-950 font-bold py-2.5 rounded-lg transition duration-200 flex items-center justify-center gap-2 gold-glow">
                    Log In <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>

            <div class="text-center mt-6">
                        <p class="mb-4 text-sm text-slate-400">
                            Don't have an account?
                            <a href="signup.php" class="font-medium text-amber-400 hover:underline">Sign up as an attorney</a>
                        </p>
                <p class="text-xs text-slate-500">
                    Restricted to Administrator / Managing Partner and Lawyer / Attorney roles.
                </p>
            </div>
        </div>
    </main>
    <script>
        if (window.lucide) window.lucide.createIcons();

        const loginForm = document.getElementById('login-form');
        const loginSubmittedKey = 'admin-login-submitted';

        <?php if ($loginError !== ''): ?>
            sessionStorage.removeItem(loginSubmittedKey);
        <?php endif; ?>

        loginForm.addEventListener('submit', () => {
            sessionStorage.setItem(loginSubmittedKey, '1');
        });

        window.addEventListener('pageshow', () => {
            if (sessionStorage.getItem(loginSubmittedKey) === '1') {
                loginForm.reset();
                sessionStorage.removeItem(loginSubmittedKey);
            }
        });
    </script>
</body>
</html>