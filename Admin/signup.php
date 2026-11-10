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

if (!isset($_SESSION['admin_signup_csrf'])) {
    $_SESSION['admin_signup_csrf'] = bin2hex(random_bytes(32));
}

$signupError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = $_POST['username'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $username = is_string($usernameInput) ? trim($usernameInput) : '';
    $emailInput = is_string($emailInput) ? trim($emailInput) : '';
    $email = filter_var($emailInput, FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!is_string($csrfToken) || !hash_equals($_SESSION['admin_signup_csrf'], $csrfToken)) {
        $signupError = 'Your session has expired. Refresh the page and try again.';
    } elseif (strlen($username) < 2 || strlen($username) > 100) {
        $signupError = 'Enter a username between 2 and 100 characters.';
    } elseif (
        $email === false
        || strlen($emailInput) > 150
        || strtolower(substr($emailInput, -10)) !== '@gmail.com'
    ) {
        $signupError = 'Enter a valid Gmail address ending in @gmail.com.';
    } elseif (
        !is_string($password)
        || strlen($password) < 12
        || preg_match('/[a-z]/',    $password) !== 1
        || preg_match('/[A-Z]/',    $password) !== 1
        || preg_match('/[0-9]/', $password) !== 1
        || preg_match('/[^a-zA-Z0-9]/', $password) !== 1
    ) {
        $signupError = 'Use at least 12 characters, including lowercase and uppercase letters, a number, and a special character.';
    } elseif (!is_string($confirmPassword) || !hash_equals($password, $confirmPassword)) {
        $signupError = 'The passwords do not match.';
    } else {
        try {
            $statement = $pdo->prepare(
                'INSERT INTO users (username, email, password, role)
                 VALUES (:username, :email, :password, :role)'
            );
            $statement->execute([
                'username' => $username,
                'email' => strtolower($email),
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'lawyer',
            ]);

            $userId = (int) $pdo->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['admin_portal_role'] = 'lawyer';
            $_SESSION['admin_portal_user_id'] = $userId;
            unset($_SESSION['admin_signup_csrf']);
            header('Location: index.php');
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $signupError = 'That username or email address is already in use.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attorney Sign Up</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="styles.css">
    <style>
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
    </style>
</head>
<body class="bg-[#020617] text-slate-100 antialiased selection:bg-amber-500 selection:text-slate-950">
    <main class="min-h-screen bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-950 via-[#030712] to-black flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-slate-900/80 backdrop-blur-md rounded-2xl shadow-2xl border border-amber-500/20 p-8">
            <div class="text-center mb-8">
                <div class="inline-flex p-3 bg-amber-500/10 text-amber-400 rounded-full mb-3 border border-amber-500/30 gold-glow-sm">
                    <i data-lucide="scale" class="w-8 h-8"></i>
                </div>
                <h1 class="text-2xl font-bold text-white tracking-wide">Attorney Sign Up</h1>
                <p class="text-slate-400 text-sm mt-1">Create an attorney account for the law office portal.</p>
            </div>

            <?php if ($signupError !== ''): ?>
                <p role="alert" class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-300">
                    <?php echo htmlspecialchars($signupError, ENT_QUOTES, 'UTF-8'); ?>
                </p>
            <?php endif; ?>

            <form method="post" action="signup.php" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_signup_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div>
                        <label for="username" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-amber-400/80">Name</label>
                        <input
                            id="username"
                            name="username"
                            type="text"
                            required
                            minlength="2"
                            maxlength="100"
                            autocomplete="name"
                            value="<?php echo htmlspecialchars((string) ($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-full rounded-lg border border-slate-800 bg-slate-950/60 px-4 py-2.5 text-sm text-white transition focus:border-amber-500 focus:outline-none"
                            placeholder="Attorney name">
                    </div>

                    <div>
                        <label for="email" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-amber-400/80">Email Address</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            required
                            maxlength="150"
                            pattern=".+@gmail\.com"
                            title="Use a Gmail address ending in @gmail.com."
                            autocomplete="email"
                            value="<?php echo htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            class="w-full rounded-lg border border-slate-800 bg-slate-950/60 px-4 py-2.5 text-sm text-white transition focus:border-amber-500 focus:outline-none"
                            placeholder="name@gmail.com">
                    </div>

                    <div>
                        <label for="password" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-amber-400/80">Password</label>
                        <div class="relative">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                minlength="12"
                                autocomplete="new-password"
                                class="w-full rounded-lg border border-slate-800 bg-slate-950/60 px-4 py-2.5 pr-12 text-sm text-white transition focus:border-amber-500 focus:outline-none"
                                placeholder="At least 12 characters">
                            <button
                                type="button"
                                data-toggle-password="password"
                                aria-label="Show password"
                                aria-pressed="false"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 transition hover:text-amber-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-500">
                                <i data-lucide="eye" class="h-5 w-5" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="confirm_password" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-amber-400/80">Confirm Password</label>
                        <div class="relative">
                            <input
                                id="confirm_password"
                                name="confirm_password"
                                type="password"
                                required
                                minlength="12"
                                autocomplete="new-password"
                                class="w-full rounded-lg border border-slate-800 bg-slate-950/60 px-4 py-2.5 pr-12 text-sm text-white transition focus:border-amber-500 focus:outline-none"
                                placeholder="Re-enter your password">
                            <button
                                type="button"
                                data-toggle-password="confirm_password"
                                aria-label="Show confirm password"
                                aria-pressed="false"
                                class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 transition hover:text-amber-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-500">
                                <i data-lucide="eye" class="h-5 w-5" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-slate-400">Password must include:</p>
                        <ul class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-xs text-slate-400" aria-live="polite">
                            <li class="flex min-w-0 items-center gap-2" data-password-rule="length"><span class="password-rule-indicator" aria-hidden="true"></span><span>At least 12 characters</span></li>
                            <li class="flex min-w-0 items-center gap-2" data-password-rule="lowercase"><span class="password-rule-indicator" aria-hidden="true"></span><span>Lowercase letters (a–z)</span></li>
                            <li class="flex min-w-0 items-center gap-2" data-password-rule="uppercase"><span class="password-rule-indicator" aria-hidden="true"></span><span>Uppercase letters (A–Z)</span></li>
                            <li class="flex min-w-0 items-center gap-2" data-password-rule="number"><span class="password-rule-indicator" aria-hidden="true"></span><span>Digits / numbers (0–9)</span></li>
                            <li class="col-span-2 flex min-w-0 items-center gap-2" data-password-rule="symbol"><span class="password-rule-indicator" aria-hidden="true"></span><span>Special characters / symbols (e.g., !, @, #, $)</span></li>
                        </ul>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-gradient-to-r from-amber-600 to-amber-500 py-2.5 font-bold text-slate-950 transition duration-200 hover:from-amber-500 hover:to-amber-400">
                        Create Attorney Account
                    </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-400">
                Already have an account? <a href="login.php" class="font-medium text-amber-400 hover:underline">Log in</a>
            </p>
        </div>
    </main>
    <script>
        const passwordInput = document.getElementById('password');
        const passwordRules = {
            length: value => value.length >= 12,
            lowercase: value => /[a-z]/.test(value),
            uppercase: value => /[A-Z]/.test(value),
            number: value => /[0-9]/.test(value),
            symbol: value => /[^a-zA-Z0-9]/.test(value)
        };

        if (passwordInput) {
            const updatePasswordRules = () => {
                const value = passwordInput.value;
                Object.entries(passwordRules).forEach(([rule, isMet]) => {
                    const indicator = document.querySelector(`[data-password-rule="${rule}"] .password-rule-indicator`);
                    const met = isMet(value);
                    indicator.textContent = met ? '✓' : '';
                    indicator.classList.toggle('is-met', met);
                });
            };

            passwordInput.addEventListener('input', updatePasswordRules);
            updatePasswordRules();
        }
        document.querySelectorAll('[data-toggle-password]').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.togglePassword);
                const isVisible = input.type === 'text';
                input.type = isVisible ? 'password' : 'text';
                button.setAttribute('aria-pressed', String(!isVisible));
                button.setAttribute('aria-label', `${isVisible ? 'Show' : 'Hide'} ${input.id === 'password' ? 'password' : 'confirm password'}`);
                button.innerHTML = `<i data-lucide="${isVisible ? 'eye' : 'eye-off'}" class="h-5 w-5" aria-hidden="true"></i>`;
                if (window.lucide) window.lucide.createIcons();
            });
        });
        if (window.lucide) window.lucide.createIcons();
    </script>
</body>
</html>
