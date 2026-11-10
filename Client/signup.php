<?php
require_once __DIR__ . '/_bootstrap.php';

if (client_current_user($pdo) !== null) {
    header('Location: index.php');
    exit;
}
unset($_SESSION['client_user_id']);

$signupError = '';
$usernameValue = '';
$emailValue = '';
$phoneValue = '';
$addressValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = $_POST['username'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $phoneInput = $_POST['phone'] ?? '';
    $addressInput = $_POST['address'] ?? '';
    $usernameValue = is_string($usernameInput) ? trim($usernameInput) : '';
    $emailValue = is_string($emailInput) ? trim($emailInput) : '';
    $phoneValue = is_string($phoneInput) ? trim($phoneInput) : '';
    $addressValue = is_string($addressInput) ? trim($addressInput) : '';
    $email = filter_var($emailValue, FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!client_csrf_is_valid($_POST['_csrf'] ?? null)) {
        $signupError = 'Your session has expired. Refresh the page and try again.';
    } elseif (strlen($usernameValue) < 2 || strlen($usernameValue) > 100) {
        $signupError = 'Enter a name between 2 and 100 characters.';
    } elseif (
        $email === false
        || strlen($emailValue) > 150
        || strtolower(substr($emailValue, -10)) !== '@gmail.com'
    ) {
        $signupError = 'Enter a valid Gmail address ending in @gmail.com.';
    } elseif ($phoneValue !== '' && preg_match('/^[0-9]{1,11}$/D', $phoneValue) !== 1) {
        $signupError = 'Phone number must contain digits only and be no more than 11 digits.';
    } elseif (strlen($addressValue) > 50) {
        $signupError = 'Address must be 50 characters or fewer.';
    } elseif (
        !is_string($password)
        || strlen($password) < 12
        || preg_match('/[a-z]/', $password) !== 1
        || preg_match('/[A-Z]/', $password) !== 1
        || preg_match('/[0-9]/', $password) !== 1
        || preg_match('/[^a-zA-Z0-9]/', $password) !== 1
    ) {
        $signupError = 'Use at least 12 characters, including lowercase and uppercase letters, a number, and a special character.';
    } elseif (!is_string($confirmPassword) || !hash_equals($password, $confirmPassword)) {
        $signupError = 'The passwords do not match.';
    } else {
        try {
            $pdo->beginTransaction();
            $userStatement = $pdo->prepare(
                'INSERT INTO users (username, email, password, role, phone, address)
                 VALUES (:username, :email, :password, :role, :phone, :address)'
            );
            $userStatement->execute([
                'username' => $usernameValue,
                'email' => strtolower($email),
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'Client',
                'phone' => $phoneValue !== '' ? $phoneValue : null,
                'address' => $addressValue !== '' ? $addressValue : null,
            ]);
            $userId = (int) $pdo->lastInsertId();

            $clientStatement = $pdo->prepare(
                'INSERT INTO clients (user_id, fullname, phone, email, address)
                 VALUES (:user_id, :fullname, :phone, :email, :address)'
            );
            $clientStatement->execute([
                'user_id' => $userId,
                'fullname' => $usernameValue,
                'phone' => $phoneValue !== '' ? $phoneValue : null,
                'email' => strtolower($email),
                'address' => $addressValue !== '' ? $addressValue : null,
            ]);
            $pdo->commit();

            $_SESSION['client_flash'] = 'Your Client account was created. Sign in to continue.';
            header('Location: login.php');
            exit;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            $signupError = 'That name or email address is already in use.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal Sign Up</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="styles.css">
    <style>
        .client-signup-input {
            height: 48px;
        }

        .client-password-rule-indicator {
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

        .client-password-rule-indicator.is-met {
            border-color: #34d399;
            background: rgba(52, 211, 153, 0.12);
        }
    </style>
</head>
<body class="min-h-screen bg-[#020617] text-slate-100 antialiased selection:bg-amber-500 selection:text-slate-950">
    <main class="flex min-h-screen items-center justify-center bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-950 via-[#030712] to-black p-4">
        <section class="w-full max-w-[560px] rounded-[22px] border border-amber-500/20 bg-slate-900/85 p-8 shadow-2xl backdrop-blur-md">
            <div class="mb-6 text-center">
                <div class="mb-3 inline-flex rounded-full border border-amber-500/30 bg-amber-500/10 p-3 text-amber-400">
                    <i data-lucide="user-round" class="h-8 w-8"></i>
                </div>
                <h1 class="text-2xl font-bold tracking-wide text-white">Create Client Account</h1>
                <p class="mt-1 text-sm text-slate-400">Register to access your Client Portal</p>
            </div>

            <?php if ($signupError !== ''): ?>
                <p role="alert" class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200"><?php echo client_h($signupError); ?></p>
            <?php endif; ?>

            <form method="post" action="signup.php" class="space-y-4">
                <?php client_csrf_field(); ?>
                <div>
                    <label for="username" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-amber-400">Full Name</label>
                    <input id="username" name="username" type="text" required minlength="2" maxlength="100" autocomplete="name" value="<?php echo client_h($usernameValue); ?>" class="w-full rounded-lg border border-slate-800 bg-slate-950/60 px-4 py-2.5 text-sm text-white outline-none transition focus:border-amber-500" placeholder="Your name">
                </div>
                <div>
                    <label for="email" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-amber-400">Gmail Address</label>
                    <input id="email" name="email" type="email" required maxlength="150" pattern=".+@gmail\.com" title="Use a Gmail address ending in @gmail.com." autocomplete="email" value="<?php echo client_h($emailValue); ?>" class="w-full rounded-lg border border-slate-800 bg-slate-950/60 px-4 py-2.5 text-sm text-white outline-none transition focus:border-amber-500" placeholder="name@gmail.com">
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="phone" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-amber-400">Phone Number</label>
                        <input id="phone" name="phone" type="text" inputmode="numeric" pattern="[0-9]{0,11}" maxlength="11" autocomplete="tel-national" data-phone-input title="Enter up to 11 digits only." value="<?php echo client_h($phoneValue); ?>" class="w-full rounded-lg border border-slate-800 bg-slate-950/60 px-4 py-2.5 text-sm text-white outline-none transition focus:border-amber-500" placeholder="09XXXXXXXXX">
                        <p class="mt-1 text-xs text-slate-400">Enter up to 11 digits only (example: 09123456789).</p>
                    </div>
                    <div>
                        <label for="address" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-amber-400">Address</label>
                        <input id="address" name="address" type="text" maxlength="50" autocomplete="street-address" value="<?php echo client_h($addressValue); ?>" class="w-full rounded-lg border border-slate-800 bg-slate-950/60 px-4 py-2.5 text-sm text-white outline-none transition focus:border-amber-500" placeholder="Your address">
                        <p class="mt-1 text-xs text-slate-400">Up to 50 characters.</p>
                    </div>
                </div>
                <div>
                    <label for="password" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-amber-400">Password</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" required minlength="12" maxlength="255" autocomplete="new-password" aria-describedby="password-requirements" class="client-signup-input w-full rounded-lg border border-slate-800 bg-slate-950/60 px-4 py-2.5 pr-12 text-sm text-white outline-none transition focus:border-amber-500" placeholder="At least 12 characters">
                        <button type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-400 transition hover:text-amber-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-500">
                            <i data-lucide="eye" class="h-5 w-5" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label for="confirm_password" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-amber-400">Confirm Password</label>
                    <div class="relative">
                        <input id="confirm_password" name="confirm_password" type="password" required maxlength="255" autocomplete="new-password" class="client-signup-input w-full rounded-lg border border-slate-800 bg-slate-950/60 px-4 py-2.5 pr-12 text-sm text-white outline-none transition focus:border-amber-500" placeholder="Re-enter your password">
                        <button type="button" data-password-toggle="confirm_password" aria-label="Show confirm password" aria-pressed="false" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-400 transition hover:text-amber-400 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-500">
                            <i data-lucide="eye" class="h-5 w-5" aria-hidden="true"></i>
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">Password must include:</p>
                    <ul id="password-requirements" class="mt-2 grid grid-cols-2 gap-x-4 gap-y-2 text-xs text-slate-400" aria-live="polite">
                        <li class="flex min-w-0 items-center gap-2" data-password-rule="length"><span class="client-password-rule-indicator" aria-hidden="true"></span><span>At least 12 characters</span></li>
                        <li class="flex min-w-0 items-center gap-2" data-password-rule="lowercase"><span class="client-password-rule-indicator" aria-hidden="true"></span><span>Lowercase letters (a–z)</span></li>
                        <li class="flex min-w-0 items-center gap-2" data-password-rule="uppercase"><span class="client-password-rule-indicator" aria-hidden="true"></span><span>Uppercase letters (A–Z)</span></li>
                        <li class="flex min-w-0 items-center gap-2" data-password-rule="number"><span class="client-password-rule-indicator" aria-hidden="true"></span><span>Digits / numbers (0–9)</span></li>
                        <li class="col-span-2 flex min-w-0 items-center gap-2" data-password-rule="symbol"><span class="client-password-rule-indicator" aria-hidden="true"></span><span>Special characters / symbols (e.g., !, @, #, $)</span></li>
                    </ul>
                </div>
                <button type="submit" class="w-full rounded-lg bg-gradient-to-r from-amber-600 to-amber-500 py-2.5 font-bold text-slate-950 transition hover:from-amber-500 hover:to-amber-400">
                    Create Client Account
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-400">
                Already have an account? <a href="login.php" class="font-medium text-amber-400 hover:underline">Log in</a>
            </p>
        </section>
    </main>
    <script src="../JS/phone-input.js"></script>
    <script>
        const passwordInput = document.getElementById('password');
        const passwordRules = {
            length: (value) => value.length >= 12,
            lowercase: (value) => /[a-z]/.test(value),
            uppercase: (value) => /[A-Z]/.test(value),
            number: (value) => /[0-9]/.test(value),
            symbol: (value) => /[^a-zA-Z0-9]/.test(value),
        };

        passwordInput.addEventListener('input', () => {
            Object.entries(passwordRules).forEach(([rule, check]) => {
                const item = document.querySelector(`[data-password-rule="${rule}"]`);
                const indicator = item.querySelector('.client-password-rule-indicator');
                const met = check(passwordInput.value);
                indicator.textContent = met ? '✓' : '';
                indicator.classList.toggle('is-met', met);
                item.classList.toggle('text-emerald-400', met);
                item.classList.toggle('text-slate-400', !met);
            });
        });

        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordToggle);
                const isVisible = input.type === 'text';
                input.type = isVisible ? 'password' : 'text';
                button.setAttribute('aria-pressed', String(!isVisible));
                button.setAttribute(
                    'aria-label',
                    `${isVisible ? 'Show' : 'Hide'} ${input.id === 'password' ? 'password' : 'confirm password'}`
                );
                button.innerHTML = `<i data-lucide="${isVisible ? 'eye' : 'eye-off'}" class="h-5 w-5" aria-hidden="true"></i>`;
                if (window.lucide) window.lucide.createIcons();
            });
        });

        if (window.lucide) window.lucide.createIcons();
    </script>
</body>
</html>
