<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

$loginError = '';
$emailValue = '';

if (isset($_SESSION['staff_user_id'])) {
    $userId = filter_var($_SESSION['staff_user_id'], FILTER_VALIDATE_INT);
    if ($userId !== false && $userId !== null) {
        $check = $pdo->prepare('SELECT role FROM users WHERE user_id = :user_id');
        $check->execute(['user_id' => $userId]);
        $role = $check->fetchColumn();
        if (is_string($role) && staff_is_authorized_role($role)) {
            header('Location: index.php');
            exit;
        }
    }
    unset($_SESSION['staff_user_id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['_csrf'] ?? '';
    $emailValue = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';

    if (!staff_csrf_is_valid($csrfToken)) {
        $loginError = 'Your session has expired. Refresh the page and try again.';
    } elseif ($email === false || $email === null || !is_string($password) || $password === '') {
        $loginError = 'Enter a valid email address and password.';
    } else {
        $statement = $pdo->prepare('SELECT user_id, password, role FROM users WHERE LOWER(email) = LOWER(:email)');
        $statement->execute(['email' => trim($email)]);
        $account = $statement->fetch();

        if (
            is_array($account)
            && staff_is_authorized_role((string) $account['role'])
            && password_verify($password, (string) $account['password'])
        ) {
            session_regenerate_id(true);
            $_SESSION['staff_user_id'] = (int) $account['user_id'];
            unset($_SESSION['staff_csrf_token']);
            header('Location: index.php');
            exit;
        }

        $loginError = 'Invalid email or password, or this is not a Staff account.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portal Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../CSS/staff-styles.css">
    <style>
        .staff-login-page {
            background: radial-gradient(ellipse at top, #0f1b35 0%, #030712 58%, #000 100%);
        }

        .staff-login-card {
            width: min(100%, 560px) !important;
            border: 1px solid rgba(245, 158, 11, 0.24);
            border-radius: 22px;
            background: rgba(15, 23, 42, 0.88);
            padding: 40px;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.36);
        }

        .staff-login-input {
            height: 53px;
            border: 1px solid #243047;
            background: rgba(2, 8, 23, 0.76);
            padding-left: 50px !important;
        }

        .staff-login-input:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.12);
        }

        @media (max-width: 480px) {
            .staff-login-card {
                padding: 30px 24px;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-[#020617] text-slate-100 antialiased selection:bg-amber-500 selection:text-slate-950">
    <main class="staff-login-page flex min-h-screen items-center justify-center p-4">
        <section class="staff-login-card">
            <div class="mb-10 text-center">
                <div class="mb-5 inline-flex h-[72px] w-[72px] items-center justify-center rounded-full border border-amber-500/40 bg-amber-500/5 text-amber-400">
                    <i data-lucide="shield-check" class="h-9 w-9"></i>
                </div>
                <h1 class="text-3xl font-bold tracking-wide text-white">Staff Portal</h1>
                <p class="mt-2 text-base text-slate-400">Sign in to access your legal cases</p>
            </div>

            <?php if ($loginError !== ''): ?>
                <p role="alert" class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200"><?php echo staff_h($loginError); ?></p>
            <?php endif; ?>

            <form id="login-form" method="post" action="login.php" autocomplete="off" class="space-y-5">
                <?php staff_csrf_field(); ?>
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium uppercase tracking-wider text-amber-400">Email Address</label>
                    <div class="relative">
                        <i data-lucide="mail" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500"></i>
                        <input id="email" name="email" type="email" required autocomplete="username" value="<?php echo staff_h($emailValue); ?>" class="<?php echo staff_input_class(); ?> staff-login-input" placeholder="name@gmail.com">
                    </div>
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium uppercase tracking-wider text-amber-400">Password</label>
                    <div class="relative">
                        <i data-lucide="lock" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-500"></i>
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="<?php echo staff_input_class(); ?> staff-login-input" placeholder="Enter your password">
                    </div>
                </div>
                <button type="submit" class="flex h-[55px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-600 to-amber-500 text-base font-bold text-slate-950 transition hover:from-amber-500 hover:to-amber-400">
                    Log In <i data-lucide="arrow-right" class="h-5 w-5"></i>
                </button>
            </form>
            <p class="mt-8 text-center text-base text-slate-400">
                Don't have an account?
                <a href="signup.php" class="font-semibold text-amber-400 transition hover:text-amber-300">Sign up</a>
            </p>
        </section>
    </main>
    <script>
        if (window.lucide) window.lucide.createIcons();

        const loginForm = document.getElementById('login-form');
        const loginSubmittedKey = 'staff-login-submitted';

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
