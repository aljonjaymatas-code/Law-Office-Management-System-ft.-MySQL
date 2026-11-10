<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

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

$signupError = '';
$usernameValue = '';
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = $_POST['username'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $usernameValue = is_string($usernameInput) ? trim($usernameInput) : '';
    $emailInput = is_string($emailInput) ? trim($emailInput) : '';
    $email = filter_var($emailInput, FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!staff_csrf_is_valid($_POST['_csrf'] ?? null)) {
        $signupError = 'Your session has expired. Refresh the page and try again.';
    } elseif (strlen($usernameValue) < 2 || strlen($usernameValue) > 100) {
        $signupError = 'Enter a name between 2 and 100 characters.';
    } elseif (
        $email === false
        || strlen($emailInput) > 150
        || strtolower(substr($emailInput, -10)) !== '@gmail.com'
    ) {
        $signupError = 'Enter a valid Gmail address ending in @gmail.com.';
    } elseif (
        !is_string($password)
        || strlen($password) < 8
        || preg_match('/[a-z]/', $password) !== 1
        || preg_match('/[A-Z]/', $password) !== 1
        || preg_match('/[0-9]/', $password) !== 1
        || preg_match('/[^a-zA-Z0-9]/', $password) !== 1
    ) {
        $signupError = 'Use at least 8 characters, including lowercase and uppercase letters, a number, and a special character.';
    } elseif (!is_string($confirmPassword) || !hash_equals($password, $confirmPassword)) {
        $signupError = 'The passwords do not match.';
    } else {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO users (username, email, password, role)
                 VALUES (:username, :email, :password, :role)'
            );
            $statement->execute([
                'username' => $usernameValue,
                'email' => strtolower($email),
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'staff',
            ]);

            session_regenerate_id(true);
            $_SESSION['staff_user_id'] = (int) $pdo->lastInsertId();
            unset($_SESSION['staff_csrf_token']);
            header('Location: index.php');
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $signupError = 'That name or email address is already in use.';
        }
    }

    $emailValue = $emailInput;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Portal Sign Up</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../CSS/staff-styles.css">
    <style>
        .staff-signup-page {
            background: radial-gradient(ellipse at top, #0f1b35 0%, #030712 58%, #000 100%);
        }

        .staff-signup-card {
            width: min(100%, 560px);
            border: 1px solid rgba(245, 158, 11, 0.24);
            border-radius: 22px;
            background: rgba(15, 23, 42, 0.88);
            padding: 36px 40px;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.36);
        }

        .staff-signup-input {
            height: 48px;
            border: 1px solid #243047;
            background: rgba(2, 8, 23, 0.76);
        }

        .staff-signup-input:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.12);
        }

        .password-rule-indicator {
            display: inline-flex;
            width: 15px;
            height: 15px;
            flex: 0 0 15px;
            align-items: center;
            justify-content: center;
            border: 1.5px solid #94a3b8;
            border-radius: 50%;
            color: #34d399;
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
        }

        .password-rule-indicator.is-met {
            border-color: #34d399;
            background: rgba(52, 211, 153, 0.12);
        }

        @media (max-width: 480px) {
            .staff-signup-card {
                padding: 30px 24px;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-[#020617] text-slate-100 antialiased selection:bg-amber-500 selection:text-slate-950">
    <main class="staff-signup-page flex min-h-screen items-center justify-center p-4">
        <section class="staff-signup-card">
            <div class="mb-7 text-center">
                <div class="mb-4 inline-flex h-[64px] w-[64px] items-center justify-center rounded-full border border-amber-500/40 bg-amber-500/5 text-amber-400">
                    <i data-lucide="shield-check" class="h-8 w-8"></i>
                </div>
                <h1 class="text-3xl font-bold tracking-wide text-white">Create Staff Account</h1>
                <p class="mt-2 text-base text-slate-400">Sign up to access the law office portal</p>
            </div>

            <?php if ($signupError !== ''): ?>
                <p role="alert" class="mb-5 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200"><?php echo staff_h($signupError); ?></p>
            <?php endif; ?>

            <form method="post" action="signup.php" class="space-y-4">
                <?php staff_csrf_field(); ?>
                <div>
                    <label for="username" class="mb-1.5 block text-sm font-medium uppercase tracking-wider text-amber-400">Full Name</label>
                    <input id="username" name="username" type="text" required minlength="2" maxlength="100" autocomplete="name" value="<?php echo staff_h($usernameValue); ?>" class="<?php echo staff_input_class(); ?> staff-signup-input" placeholder="Your full name">
                </div>

                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium uppercase tracking-wider text-amber-400">Email Address</label>
                    <input id="email" name="email" type="email" required maxlength="150" pattern=".+@gmail\.com" title="Use a Gmail address ending in @gmail.com." autocomplete="email" value="<?php echo staff_h($emailValue); ?>" class="<?php echo staff_input_class(); ?> staff-signup-input" placeholder="name@gmail.com">
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium uppercase tracking-wider text-amber-400">Password</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" required minlength="12" maxlength="255" autocomplete="new-password" aria-describedby="password-requirements" class="<?php echo staff_input_class(); ?> staff-signup-input pr-12" placeholder="Create a strong password">
                        <button type="button" data-password-toggle="password" aria-label="Show password" class="absolute inset-y-0 right-0 inline-flex w-12 items-center justify-center text-slate-400 transition hover:text-amber-300">
                            <i data-lucide="eye" class="h-5 w-5"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label for="confirm_password" class="mb-1.5 block text-sm font-medium uppercase tracking-wider text-amber-400">Confirm Password</label>
                    <div class="relative">
                        <input id="confirm_password" name="confirm_password" type="password" required maxlength="255" autocomplete="new-password" class="<?php echo staff_input_class(); ?> staff-signup-input pr-12" placeholder="Re-enter your password">
                        <button type="button" data-password-toggle="confirm_password" aria-label="Show confirm password" class="absolute inset-y-0 right-0 inline-flex w-12 items-center justify-center text-slate-400 transition hover:text-amber-300">
                            <i data-lucide="eye" class="h-5 w-5"></i>
                        </button>
                    </div>
                    <ul id="password-requirements" class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs text-slate-400" aria-live="polite">
                        <li class="inline-flex items-center gap-1.5" data-requirement="length"><span class="password-rule-indicator" aria-hidden="true"></span>At least 8 characters</li>
                        <li class="inline-flex items-center gap-1.5" data-requirement="lowercase"><span class="password-rule-indicator" aria-hidden="true"></span>Lowercase letter</li>
                        <li class="inline-flex items-center gap-1.5" data-requirement="uppercase"><span class="password-rule-indicator" aria-hidden="true"></span>Uppercase letter</li>
                        <li class="inline-flex items-center gap-1.5" data-requirement="number"><span class="password-rule-indicator" aria-hidden="true"></span>Number</li>
                        <li class="inline-flex items-center gap-1.5" data-requirement="special"><span class="password-rule-indicator" aria-hidden="true"></span>Special character</li>
                    </ul>
                </div>

                <button type="submit" class="flex h-[53px] w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-600 to-amber-500 text-base font-bold text-slate-950 transition hover:from-amber-500 hover:to-amber-400">
                    Create Account <i data-lucide="arrow-right" class="h-5 w-5"></i>
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-400">
                Already have an account?
                <a href="login.php" class="font-semibold text-amber-400 transition hover:text-amber-300">Log in</a>
            </p>
        </section>
    </main>
    <script>
        if (window.lucide) window.lucide.createIcons();

        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordToggle);
                const isVisible = input.type === 'text';
                input.type = isVisible ? 'password' : 'text';
                button.setAttribute('aria-label', `${isVisible ? 'Show' : 'Hide'} ${input.id === 'password' ? 'password' : 'confirm password'}`);
                button.innerHTML = `<i data-lucide="${isVisible ? 'eye' : 'eye-off'}" class="h-5 w-5"></i>`;
                if (window.lucide) window.lucide.createIcons();
            });
        });

        const passwordInput = document.getElementById('password');
        const requirementChecks = {
            length: (value) => value.length >= 12,
            lowercase: (value) => /[a-z]/.test(value),
            uppercase: (value) => /[A-Z]/.test(value),
            number: (value) => /[0-9]/.test(value),
            special: (value) => /[^a-zA-Z0-9]/.test(value),
        };

        passwordInput.addEventListener('input', () => {
            Object.entries(requirementChecks).forEach(([name, check]) => {
                const item = document.querySelector(`[data-requirement="${name}"]`);
                const met = check(passwordInput.value);
                const indicator = item.querySelector('.password-rule-indicator');
                indicator.textContent = met ? '✓' : '';
                indicator.classList.toggle('is-met', met);
                item.classList.toggle('text-emerald-400', met);
                item.classList.toggle('text-slate-400', !met);
            });
        });
    </script>
</body>
</html>
