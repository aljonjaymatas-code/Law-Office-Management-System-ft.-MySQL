<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = staff_require_auth($pdo);
$roleLabel = ucwords(str_replace(['_', '-'], ' ', (string) $user['role']));
$view = is_string($_GET['view'] ?? null) ? $_GET['view'] : 'account-settings';
$views = [
    'account-settings' => ['Account Settings', 'settings'],
    'customization' => ['Customization', 'paintbrush'],
];

if (!isset($views[$view])) {
    http_response_code(404);
    $view = 'account-settings';
}

[$title, $titleIcon] = $views[$view];
staff_layout_start($title, $user, 'settings.php?view=' . $view);
?>
<?php if ($view === 'account-settings'): ?>
    <section class="overflow-hidden rounded-2xl border border-amber-500/20 bg-slate-900/80 shadow-xl">
        <div class="border-b border-slate-800 p-6">
            <div class="flex items-center gap-3">
                <i data-lucide="<?php echo staff_h($titleIcon); ?>" class="h-5 w-5 text-amber-400"></i>
                <div>
                    <h2 class="text-xl font-semibold text-white">Account Settings</h2>
                    <p class="mt-1 text-sm text-slate-400">Your account information. Contact an administrator to request changes.</p>
                </div>
            </div>
        </div>
        <dl class="grid gap-4 p-6 sm:grid-cols-2">
            <?php foreach ([
                'Username' => $user['username'],
                'Role' => $roleLabel,
                'Email' => $user['email'],
            ] as $label => $value): ?>
                <div class="rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-400"><?php echo staff_h($label); ?></dt>
                    <dd class="mt-2 break-words text-sm font-medium text-white"><?php echo staff_h($value); ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>
<?php else: ?>
    <section class="overflow-hidden rounded-2xl border border-amber-500/20 bg-slate-900/80 shadow-xl">
        <div class="border-b border-slate-800 p-6">
            <div class="flex items-center gap-3">
                <i data-lucide="<?php echo staff_h($titleIcon); ?>" class="h-5 w-5 text-amber-400"></i>
                <div>
                    <h2 class="text-xl font-semibold text-white">Customization</h2>
                    <p class="mt-1 text-sm text-slate-400">Choose a dark-mode accent palette. Your selection is saved in this browser.</p>
                </div>
            </div>
        </div>
        <div class="grid gap-4 p-6 md:grid-cols-3" aria-label="Accent color palette">
            <?php foreach ([
                'amber' => ['Classic Amber', 'Warm gold accents', '#f59e0b'],
                'ocean' => ['Ocean Blue', 'Calm blue accents', '#38bdf8'],
                'emerald' => ['Emerald', 'Fresh green accents', '#34d399'],
            ] as $themeId => [$themeName, $description, $color]): ?>
                <button
                    type="button"
                    aria-pressed="false"
                    data-staff-theme-option="<?php echo staff_h($themeId); ?>"
                    class="rounded-xl border border-slate-700 bg-slate-950/50 p-4 text-left transition hover:border-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-400">
                    <span class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-3">
                            <span class="h-8 w-8 rounded-full border border-white/20" style="background-color: <?php echo staff_h($color); ?>"></span>
                            <span>
                                <span class="block text-sm font-semibold text-white"><?php echo staff_h($themeName); ?></span>
                                <span class="mt-1 block text-xs text-slate-400"><?php echo staff_h($description); ?></span>
                            </span>
                        </span>
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="hidden h-4 w-4 shrink-0 text-amber-300" data-staff-theme-check>
                            <path d="m5 12 4 4L19 6"></path>
                        </svg>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>
        <p class="hidden px-6 pb-6 text-sm text-amber-200" role="status" data-staff-theme-error>
            The palette is active for this visit, but your browser couldn’t save it for your next visit.
        </p>
    </section>
    <script>
        (() => {
            const root = document.documentElement;
            const storageKey = root.dataset.themeStorageKey;
            const options = [...document.querySelectorAll('[data-staff-theme-option]')];
            const errorMessage = document.querySelector('[data-staff-theme-error]');

            const renderSelection = (theme) => {
                options.forEach((option) => {
                    const selected = option.dataset.staffThemeOption === theme;
                    option.setAttribute('aria-pressed', selected ? 'true' : 'false');
                    option.classList.toggle('staff-theme-selected', selected);
                    option.querySelector('[data-staff-theme-check]')?.classList.toggle('hidden', !selected);
                });
            };

            renderSelection(root.dataset.staffTheme || 'amber');

            options.forEach((option) => {
                option.addEventListener('click', () => {
                    const theme = option.dataset.staffThemeOption;
                    root.dataset.staffTheme = theme;
                    errorMessage.classList.add('hidden');
                    renderSelection(theme);

                    try {
                        window.localStorage.setItem(storageKey, theme);
                    } catch (error) {
                        console.error('Unable to save the Staff portal theme.', error);
                        errorMessage.classList.remove('hidden');
                    }
                });
            });
        })();
    </script>
<?php endif; ?>

<?php staff_layout_end(); ?>
