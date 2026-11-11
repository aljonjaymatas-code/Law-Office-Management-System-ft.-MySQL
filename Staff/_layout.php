<?php
function staff_layout_start(string $title, array $user, string $activePage, string $success = '', string $error = '', string $headerAction = '', string $headerActionTarget = ''): void
{
    global $pdo;

    $notificationsStatement = $pdo->prepare(
        'SELECT n.notification_id, n.notification_type, n.document_access_request_id,
                dar.status AS document_access_request_status,
                n.title, n.message, n.created_at, n.read_at
         FROM notifications n
         LEFT JOIN document_access_requests dar
           ON dar.request_id = n.document_access_request_id
         WHERE n.user_id = :user_id
         ORDER BY n.created_at DESC, n.notification_id DESC
         LIMIT 50'
    );
    $notificationsStatement->execute(['user_id' => (int) $user['user_id']]);
    $notifications = $notificationsStatement->fetchAll();
    $unreadNotifications = count(array_filter(
        $notifications,
        static fn (array $notification): bool => $notification['read_at'] === null
    ));

    $navigation = [
        'index.php'         => ['Dashboard', 'layout-dashboard'],
        'clients.php'       => ['Clients', 'users-round'],
        'cases.php'         => ['Cases', 'briefcase-business'],
        'appointments.php'  => ['Appointments', 'calendar-days'],
        'time_entries.php'  => ['Time Entries', 'clock-3'],
        'documents.php'     => ['Documents', 'files'],
    ];
    $themeStorageKey = 'staff-portal-theme:' . strtolower((string) $user['email']);
    $roleLabel = ucwords(str_replace(['_', '-'], ' ', (string) $user['role']));
    ?>
    <!DOCTYPE html>
    <html lang="en" data-theme-storage-key="<?php echo staff_h($themeStorageKey); ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo staff_h($title); ?> - Staff Portal</title>
        <script>
            (() => {
                const root = document.documentElement;
                const storageKey = root.dataset.themeStorageKey;
                try {
                    const savedTheme = window.localStorage.getItem(storageKey);
                    root.dataset.staffTheme = ['amber', 'ocean', 'emerald'].includes(savedTheme) ? savedTheme : 'amber';
                } catch (error) {
                    console.error('Unable to read the saved Staff portal theme.', error);
                    root.dataset.staffTheme = 'amber';
                }
            })();
        </script>
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://unpkg.com/lucide@latest"></script>
        <link rel="stylesheet" href="../CSS/staff-styles.css?v=4">
        <style>
            html {
                overflow-y: scroll;
                scrollbar-gutter: stable;
            }
        </style>
    </head>
    <body class="min-h-screen bg-[#020617] text-slate-100 antialiased selection:bg-amber-500 selection:text-slate-950">
        <header class="sticky top-0 z-40 border-b border-amber-500/20 bg-slate-900/95 px-4 py-4 shadow-lg backdrop-blur-md sm:px-6">
            <div class="mx-auto flex max-w-screen-2xl flex-wrap items-center justify-between gap-4">
                <a href="index.php" class="flex items-center gap-3">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-amber-700 text-slate-950 gold-glow-sm">
                        <i data-lucide="users" class="h-6 w-6"></i>
                    </span>
                    <span>
                        <span class="block text-lg font-bold tracking-wide text-white">Law Office Management System</span>
                        <span class="block text-xs font-semibold tracking-wider text-amber-400">STAFF PORTAL</span>
                    </span>
                </a>
                <form id="staff-search-form" method="get" action="search.php" role="search" class="order-3 w-full md:order-none md:mx-6 md:w-auto md:flex-1 md:max-w-xl">
                    <label for="staff-global-search" class="sr-only">Search clients, cases, appointments, and documents</label>
                    <div class="relative">
                        <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"></i>
                        <input
                            id="staff-global-search"
                            name="q"
                            type="search"
                            maxlength="100"
                            value="<?php echo staff_h(is_string($_GET['q'] ?? null) ? $_GET['q'] : ''); ?>"
                            placeholder="Search clients, cases, appointments, documents..."
                            autocomplete="off"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="staff-search-suggestions"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 py-2.5 pl-10 pr-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-amber-500">
                        <div id="staff-search-dropdown" class="absolute left-0 right-0 top-full z-50 mt-2 hidden overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-2xl">
                            <p id="staff-search-status" class="hidden px-4 py-3 text-sm text-slate-400" role="status"></p>
                            <ul id="staff-search-suggestions" class="max-h-96 overflow-y-auto py-1" role="listbox" aria-label="Search suggestions"></ul>
                        </div>
                    </div>
                </form>
                <div class="flex shrink-0 items-center gap-3">
                    <div class="relative" data-header-popover>
                        <button
                            type="button"
                            aria-label="Notifications"
                            aria-controls="staff-notifications-panel"
                            aria-expanded="false"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 bg-slate-950 text-amber-400 transition hover:border-amber-500/60 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500"
                            data-header-popover-toggle>
                            <i data-lucide="bell" class="h-5 w-5"></i>
                            <?php if ($unreadNotifications > 0): ?>
                                <span data-notification-badge class="absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-slate-900 bg-red-500 px-1 text-[10px] font-bold text-white"><?php echo $unreadNotifications; ?></span>
                            <?php endif; ?>
                        </button>
                        <section
                            id="staff-notifications-panel"
                            aria-label="Notifications"
                            class="absolute right-0 top-full z-50 mt-3 w-[min(22rem,90vw)] overflow-hidden rounded-xl border border-slate-700 bg-slate-900 text-left shadow-2xl"
                            data-header-popover-panel
                            hidden>
                            <div class="flex items-center justify-between border-b border-slate-800 px-4 py-3">
                                <h2 class="text-sm font-semibold text-white">Notifications</h2>
                                <button type="button" class="text-xs font-medium text-amber-300 hover:text-amber-200" data-notification-mark-all <?php echo $unreadNotifications === 0 ? 'hidden' : ''; ?>>Mark all read</button>
                            </div>
                            <p class="hidden px-4 py-2 text-xs text-red-300" role="alert" data-notification-error></p>
                            <div class="max-h-80 overflow-y-auto" data-notification-list data-csrf="<?php echo staff_h(staff_csrf_token()); ?>">
                                <?php if ($notifications === []): ?>
                                    <p class="px-4 py-6 text-center text-sm text-slate-400" role="status">You’re all caught up. There are no notifications yet.</p>
                                <?php else: foreach ($notifications as $notification): ?>
                                    <div
                                        data-notification-id="<?php echo (int) $notification['notification_id']; ?>"
                                        data-notification-read="<?php echo $notification['read_at'] !== null ? 'true' : 'false'; ?>"
                                        class="border-b border-slate-800 px-4 py-3 transition hover:bg-slate-800/70 <?php echo $notification['read_at'] === null ? 'bg-amber-500/5' : 'opacity-70'; ?>">
                                        <span class="flex items-start justify-between gap-3">
                                            <span class="text-sm font-semibold text-white"><?php echo staff_h($notification['title']); ?></span>
                                            <?php if ($notification['read_at'] === null): ?><span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-amber-400"></span><?php endif; ?>
                                        </span>
                                        <span class="mt-1 block text-xs text-slate-300"><?php echo staff_h($notification['message']); ?></span>
                                        <span class="mt-1 block text-[10px] text-slate-500"><?php echo staff_h(date('M j, Y g:i A', strtotime($notification['created_at']))); ?></span>
                                        <?php if ($notification['notification_type'] === 'document_access_requested' && $notification['document_access_request_status'] === 'Pending'): ?>
                                            <span class="mt-3 flex gap-2">
                                                <button type="button" data-request-decision="Approved" data-request-id="<?php echo (int) $notification['document_access_request_id']; ?>" class="rounded-md bg-emerald-500 px-3 py-1.5 text-xs font-semibold text-slate-950 transition hover:bg-emerald-400">Approve</button>
                                                <button type="button" data-request-decision="Declined" data-request-id="<?php echo (int) $notification['document_access_request_id']; ?>" class="rounded-md bg-red-500 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-red-400">Decline</button>
                                            </span>
                                        <?php elseif ($notification['notification_type'] === 'document_access_requested' && $notification['document_access_request_status'] !== null): ?>
                                            <span class="mt-2 block text-xs font-semibold <?php echo $notification['document_access_request_status'] === 'Approved' ? 'text-emerald-300' : 'text-red-300'; ?>">
                                                Request <?php echo staff_h(strtolower((string) $notification['document_access_request_status'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </section>
                    </div>
                    <div class="relative" data-header-popover>
                        <button
                            type="button"
                            aria-label="Open profile menu"
                            aria-controls="staff-profile-panel"
                            aria-expanded="false"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-amber-500 bg-slate-950 text-amber-400 transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500"
                            data-header-popover-toggle>
                            <i data-lucide="user-round" class="h-5 w-5"></i>
                        </button>
                        <section
                            id="staff-profile-panel"
                            aria-label="Profile menu"
                            class="absolute right-0 top-full z-50 mt-3 hidden h-[calc(100dvh-8rem)] min-h-[320px] max-h-[36rem] w-64 flex flex-col overflow-hidden rounded-xl border border-slate-700 bg-slate-950 text-left shadow-2xl"
                            data-header-popover-panel
                            hidden>
                            <div class="flex items-center gap-3 border-b border-slate-800 px-4 py-4">
                                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-amber-500/60 bg-slate-900 text-amber-400">
                                    <i data-lucide="user-round" class="h-5 w-5"></i>
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-white"><?php echo staff_h($user['username']); ?></span>
                                    <span class="block text-xs text-amber-400"><?php echo staff_h($roleLabel); ?></span>
                                    <span class="block truncate text-[11px] text-slate-400"><?php echo staff_h($user['email']); ?></span>
                                </span>
                            </div>
                            <nav class="flex-1 space-y-1 overflow-y-auto p-2" aria-label="Profile navigation">
                                <?php foreach ($navigation as $path => [$label, $icon]): ?>
                                    <a
                                        href="<?php echo staff_h($path); ?>"
                                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition <?php echo $activePage === $path ? 'bg-amber-500/15 text-amber-300' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>"
                                        <?php echo $activePage === $path ? 'aria-current="page"' : ''; ?>>
                                        <i data-lucide="<?php echo staff_h($icon); ?>" class="h-4 w-4 shrink-0"></i>
                                        <span><?php echo staff_h($label); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </nav>
                            <div class="space-y-1 border-t border-slate-800 p-2">
                                <?php foreach ([
                                    'settings.php?view=account-settings' => ['Account Settings', 'settings'],
                                    'settings.php?view=customization' => ['Customization', 'paintbrush'],
                                ] as $path => [$label, $icon]): ?>
                                    <a
                                        href="<?php echo staff_h($path); ?>"
                                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition <?php echo $activePage === $path ? 'bg-amber-500/15 text-amber-300' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>"
                                        <?php echo $activePage === $path ? 'aria-current="page"' : ''; ?>>
                                        <i data-lucide="<?php echo staff_h($icon); ?>" class="h-4 w-4 shrink-0"></i>
                                        <span><?php echo staff_h($label); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <footer class="mt-auto border-t border-slate-800 p-3">
                                <a href="logout.php" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-red-300 transition hover:bg-red-500/10 hover:text-red-200">
                                    <i data-lucide="log-out" class="h-4 w-4"></i><span>Log Out</span>
                                </a>
                            </footer>
                        </section>
                    </div>
                </div>
            </div>
        </header>
        <nav class="border-b border-slate-800 bg-slate-950/80 px-4 sm:px-6" aria-label="Staff sections">
            <div class="mx-auto flex max-w-screen-2xl gap-1 overflow-x-auto py-2">
                <?php foreach ($navigation as $path => [$label, $icon]): ?>
                    <a
                        href="<?php echo staff_h($path); ?>"
                        class="inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm transition <?php echo $activePage === $path ? 'bg-amber-500 text-slate-950' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>"
                        <?php echo $activePage === $path ? 'aria-current="page"' : ''; ?>>
                        <i data-lucide="<?php echo staff_h($icon); ?>" class="h-4 w-4"></i>
                        <?php echo staff_h($label); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>
        <main class="mx-auto max-w-screen-2xl space-y-6 p-4 sm:p-6">
            <section class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-amber-500/25 bg-gradient-to-r from-blue-900/40 via-slate-900/90 to-slate-900/90 p-5 shadow-xl sm:p-6">
                <div>
                    <h1 class="text-2xl font-bold tracking-wide text-white"><?php echo staff_h($title); ?></h1>
                    <p class="mt-1 text-sm text-slate-300">Staff operations and records from the law office database.</p>
                </div>
                <?php if ($headerAction !== '' && $headerActionTarget !== ''): ?>
                    <button
                        type="button"
                        data-dialog-toggle="<?php echo staff_h($headerActionTarget); ?>"
                        aria-controls="<?php echo staff_h($headerActionTarget); ?>"
                        aria-haspopup="dialog"
                        aria-expanded="false"
                        class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-gradient-to-r from-amber-600 to-amber-500 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:from-amber-500 hover:to-amber-400">
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        <span><?php echo staff_h($headerAction); ?></span>
                    </button>
                <?php endif; ?>
            </section>
            <?php if ($success !== ''): ?>
                <p role="status" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-200"><?php echo staff_h($success); ?></p>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <p role="alert" class="rounded-xl border border-red-500/30 bg-red-500/10 p-4 text-sm text-red-200"><?php echo staff_h($error); ?></p>
            <?php endif; ?>
    <?php
}

function staff_layout_end(): void
{
    ?>
        </main>
        <script src="../JS/phone-input.js"></script>
        <script>
            if (window.lucide) window.lucide.createIcons();

            (() => {
                const notificationList = document.querySelector('[data-notification-list]');
                const notificationError = document.querySelector('[data-notification-error]');
                const notificationToggle = document.querySelector('[aria-controls="staff-notifications-panel"]');
                const markAllButton = document.querySelector('[data-notification-mark-all]');
                const renderNotifications = (notifications) => {
                    if (!notificationList) return;
                    notificationList.replaceChildren();
                    if (notifications.length === 0) {
                        const emptyState = document.createElement('p');
                        emptyState.className = 'px-4 py-6 text-center text-sm text-slate-400';
                        emptyState.setAttribute('role', 'status');
                        emptyState.textContent = 'You’re all caught up. There are no notifications yet.';
                        notificationList.append(emptyState);
                    }
                    notifications.forEach((notification) => {
                        const button = document.createElement('button');
                        const item = document.createElement('div');
                        item.dataset.notificationId = String(notification.notification_id);
                        item.dataset.notificationRead = notification.read ? 'true' : 'false';
                        item.className = `border-b border-slate-800 px-4 py-3 transition hover:bg-slate-800/70 ${notification.read ? 'opacity-70' : 'bg-amber-500/5'}`;
                        button.type = 'button';
                        button.className = 'block w-full text-left';
                        const heading = document.createElement('span');
                        heading.className = 'flex items-start justify-between gap-3';
                        const title = document.createElement('span');
                        title.className = 'text-sm font-semibold text-white';
                        title.textContent = notification.title;
                        heading.append(title);
                        if (!notification.read) {
                            const unreadDot = document.createElement('span');
                            unreadDot.className = 'mt-1 h-2 w-2 shrink-0 rounded-full bg-amber-400';
                            heading.append(unreadDot);
                        }
                        const message = document.createElement('span');
                        message.className = 'mt-1 block text-xs text-slate-300';
                        message.textContent = notification.message;
                        const createdAt = document.createElement('span');
                        createdAt.className = 'mt-1 block text-[10px] text-slate-500';
                        createdAt.textContent = new Date(String(notification.created_at).replace(' ', 'T')).toLocaleString();
                        button.append(heading, message, createdAt);
                        item.append(button);
                        if (
                            notification.notification_type === 'document_access_requested'
                            && notification.document_access_request_id
                            && notification.document_access_request_status === 'Pending'
                        ) {
                            const actions = document.createElement('div');
                            actions.className = 'mt-3 flex gap-2';
                            [
                                { decision: 'Approved', label: 'Approve', style: 'bg-emerald-500 text-slate-950 hover:bg-emerald-400' },
                                { decision: 'Declined', label: 'Decline', style: 'bg-red-500 text-white hover:bg-red-400' }
                            ].forEach(({ decision, label, style }) => {
                                const actionButton = document.createElement('button');
                                actionButton.type = 'button';
                                actionButton.dataset.requestDecision = decision;
                                actionButton.dataset.requestId = String(notification.document_access_request_id);
                                actionButton.className = `rounded-md px-3 py-1.5 text-xs font-semibold transition ${style}`;
                                actionButton.textContent = label;
                                actions.append(actionButton);
                            });
                            item.append(actions);
                        } else if (
                            notification.notification_type === 'document_access_requested'
                            && notification.document_access_request_status
                        ) {
                            const outcome = document.createElement('span');
                            outcome.className = `mt-2 block text-xs font-semibold ${notification.document_access_request_status === 'Approved' ? 'text-emerald-300' : 'text-red-300'}`;
                            outcome.textContent = `Request ${notification.document_access_request_status.toLowerCase()}`;
                            item.append(outcome);
                        }
                        notificationList.append(item);
                    });
                    const unreadCount = notifications.filter((notification) => !notification.read).length;
                    markAllButton?.toggleAttribute('hidden', unreadCount === 0);
                    if (notificationToggle) {
                        let badge = notificationToggle.querySelector('[data-notification-badge]');
                        if (unreadCount === 0) {
                            badge?.remove();
                        } else {
                            if (!badge) {
                                badge = document.createElement('span');
                                badge.dataset.notificationBadge = '';
                                badge.className = 'absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-slate-900 bg-red-500 px-1 text-[10px] font-bold text-white';
                                notificationToggle.append(badge);
                            }
                            badge.textContent = String(unreadCount);
                        }
                    }
                };
                const refreshNotifications = async () => {
                    try {
                        const response = await fetch('../notification-read.php?portal=staff');
                        const result = await response.json();
                        if (!response.ok || !Array.isArray(result.notifications)) {
                            throw new Error(result.error || 'Unable to load notifications.');
                        }
                        renderNotifications(result.notifications);
                        notificationError?.classList.add('hidden');
                    } catch (error) {
                        if (notificationError) {
                            notificationError.textContent = error.message;
                            notificationError.classList.remove('hidden');
                        }
                    }
                };
                const updateNotification = async (notificationId = null) => {
                    if (!notificationList) return;
                    const body = new URLSearchParams({
                        portal: 'staff',
                        _csrf: notificationList.dataset.csrf,
                        action: notificationId === null ? 'mark_all_read' : 'mark_read'
                    });
                    if (notificationId !== null) body.set('notification_id', notificationId);
                    try {
                        const response = await fetch('../notification-read.php?portal=staff', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                            body
                        });
                        const result = await response.json();
                        if (!response.ok || !result.success) throw new Error(result.error || 'Unable to update notifications.');
                        if (notificationError) notificationError.classList.add('hidden');
                        await refreshNotifications();
                    } catch (error) {
                        if (notificationError) {
                            notificationError.textContent = error.message;
                            notificationError.classList.remove('hidden');
                        }
                    }
                };
                const decideDocumentAccessRequest = async (requestId, decision, button) => {
                    if (!notificationList) return;
                    button.disabled = true;
                    const body = new URLSearchParams({
                        _csrf: notificationList.dataset.csrf,
                        request_id: requestId,
                        decision
                    });
                    try {
                        const response = await fetch('document-access-request.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                            body
                        });
                        const result = await response.json();
                        if (!response.ok || !result.success) throw new Error(result.error || 'Unable to update document access.');
                        notificationError?.classList.add('hidden');
                        await refreshNotifications();
                    } catch (error) {
                        button.disabled = false;
                        if (notificationError) {
                            notificationError.textContent = error.message;
                            notificationError.classList.remove('hidden');
                        }
                    }
                };
                markAllButton?.addEventListener('click', () => updateNotification());
                notificationList?.addEventListener('click', (event) => {
                    const decisionButton = event.target.closest('[data-request-decision]');
                    if (decisionButton) {
                        event.preventDefault();
                        event.stopPropagation();
                        decideDocumentAccessRequest(
                            decisionButton.dataset.requestId,
                            decisionButton.dataset.requestDecision,
                            decisionButton
                        );
                        return;
                    }
                    const notification = event.target.closest('[data-notification-id]');
                    if (notification?.dataset.notificationRead === 'false') {
                        updateNotification(notification.dataset.notificationId);
                    }
                });
                refreshNotifications();
                window.setInterval(refreshNotifications, 15000);
            })();

            (() => {
                const groups = [...document.querySelectorAll('[data-header-popover]')];
                const closeGroup = (group, restoreFocus = false) => {
                    const toggle = group.querySelector('[data-header-popover-toggle]');
                    const panel = group.querySelector('[data-header-popover-panel]');
                    if (!toggle || !panel) return;
                    const wasOpen = toggle.getAttribute('aria-expanded') === 'true';
                    panel.hidden = true;
                    panel.classList.add('hidden');
                    toggle.setAttribute('aria-expanded', 'false');
                    if (restoreFocus && wasOpen) toggle.focus();
                };

                groups.forEach((group) => {
                    const toggle = group.querySelector('[data-header-popover-toggle]');
                    const panel = group.querySelector('[data-header-popover-panel]');
                    if (!toggle || !panel) return;

                    toggle.addEventListener('click', () => {
                        const shouldOpen = toggle.getAttribute('aria-expanded') !== 'true';
                        groups.forEach((otherGroup) => closeGroup(otherGroup));
                        if (shouldOpen) {
                            panel.hidden = false;
                            panel.classList.remove('hidden');
                            toggle.setAttribute('aria-expanded', 'true');
                        }
                    });
                });

                document.addEventListener('click', (event) => {
                    groups.forEach((group) => {
                        if (!group.contains(event.target)) closeGroup(group);
                    });
                });

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') groups.forEach((group) => closeGroup(group, true));
                });
            })();

            document.querySelectorAll('[data-dialog-toggle]').forEach((button) => {
                button.addEventListener('click', () => {
                    const dialog = document.getElementById(button.dataset.dialogToggle);
                    if (!dialog) return;
                    if (dialog.open) {
                        dialog.close();
                        return;
                    }
                    dialog.showModal();
                    button.setAttribute('aria-expanded', 'true');
                });
            });

            document.querySelectorAll('dialog[data-floating-form]').forEach((dialog) => {
                const trigger = document.querySelector(`[data-dialog-toggle="${dialog.id}"]`);
                const closeButton = dialog.querySelector('[data-dialog-close]');
                const syncExpandedState = () => {
                    if (trigger) trigger.setAttribute('aria-expanded', dialog.open ? 'true' : 'false');
                };
                if (closeButton) closeButton.addEventListener('click', () => dialog.close());
                dialog.addEventListener('close', syncExpandedState);
                dialog.addEventListener('click', (event) => {
                    if (event.target === dialog) dialog.close();
                });
                if (dialog.dataset.openOnLoad === 'true') dialog.showModal();
            });

            (() => {
                const form = document.getElementById('staff-search-form');
                const input = document.getElementById('staff-global-search');
                const dropdown = document.getElementById('staff-search-dropdown');
                const list = document.getElementById('staff-search-suggestions');
                const status = document.getElementById('staff-search-status');
                if (!form || !input || !dropdown || !list || !status) return;

                const navigationEntry = performance.getEntriesByType('navigation')[0];
                const isReload = navigationEntry
                    ? navigationEntry.type === 'reload'
                    : performance.navigation && performance.navigation.type === 1;
                if (isReload) input.value = '';

                let debounceTimer;
                let requestController;
                let activeIndex = -1;

                const closeDropdown = () => {
                    dropdown.classList.add('hidden');
                    input.setAttribute('aria-expanded', 'false');
                    input.removeAttribute('aria-activedescendant');
                    activeIndex = -1;
                };

                const showMessage = (message) => {
                    list.replaceChildren();
                    status.textContent = message;
                    status.classList.remove('hidden');
                    dropdown.classList.remove('hidden');
                    input.setAttribute('aria-expanded', 'true');
                    activeIndex = -1;
                };

                const setActiveOption = (index) => {
                    const options = list.querySelectorAll('[role="option"]');
                    if (options.length === 0) return;
                    activeIndex = (index + options.length) % options.length;
                    options.forEach((option, optionIndex) => {
                        const selected = optionIndex === activeIndex;
                        option.setAttribute('aria-selected', selected ? 'true' : 'false');
                        option.classList.toggle('bg-slate-800', selected);
                    });
                    input.setAttribute('aria-activedescendant', options[activeIndex].id);
                    options[activeIndex].scrollIntoView({block: 'nearest'});
                };

                const renderSuggestions = (results) => {
                    list.replaceChildren();
                    status.textContent = '';
                    status.classList.add('hidden');

                    if (results.length === 0) {
                        showMessage('No matching records.');
                        return;
                    }

                    results.forEach((result, index) => {
                        const item = document.createElement('li');
                        const link = document.createElement('a');
                        const title = document.createElement('span');
                        const details = document.createElement('span');
                        const category = document.createElement('span');

                        item.setAttribute('role', 'presentation');
                        link.id = `staff-search-option-${index}`;
                        link.href = result.href;
                        link.setAttribute('role', 'option');
                        link.setAttribute('aria-selected', 'false');
                        link.tabIndex = -1;
                        link.className = 'flex items-start justify-between gap-4 px-4 py-3 text-left transition hover:bg-slate-800';
                        title.className = 'block truncate text-sm font-medium text-white';
                        title.textContent = result.title;
                        details.className = 'mt-1 block truncate text-xs text-slate-400';
                        details.textContent = result.details;
                        category.className = 'shrink-0 rounded-full border border-slate-700 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-amber-300';
                        category.textContent = result.category;

                        const text = document.createElement('span');
                        text.className = 'min-w-0';
                        text.append(title, details);
                        link.append(text, category);
                        item.append(link);
                        list.append(item);
                    });

                    dropdown.classList.remove('hidden');
                    input.setAttribute('aria-expanded', 'true');
                    setActiveOption(0);
                };

                const search = async () => {
                    const query = input.value.trim();
                    if (query.length < 1) {
                        closeDropdown();
                        return;
                    }

                    if (requestController) requestController.abort();
                    requestController = new AbortController();
                    showMessage('Searching...');

                    try {
                        const url = new URL('search.php', window.location.href);
                        url.searchParams.set('q', query);
                        url.searchParams.set('format', 'json');
                        const response = await fetch(url, {
                            headers: {'Accept': 'application/json'},
                            signal: requestController.signal
                        });
                        const payload = await response.json();
                        if (!response.ok) throw new Error(payload.error || 'Search request failed.');
                        renderSuggestions(payload.results);
                    } catch (error) {
                        if (error.name === 'AbortError') return;
                        console.error('Staff search suggestions failed.', error);
                        showMessage('Search is unavailable. Press Enter to try the full search.');
                    }
                };

                input.addEventListener('input', () => {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(search, 200);
                });

                input.addEventListener('keydown', (event) => {
                    const options = list.querySelectorAll('[role="option"]');
                    if (event.key === 'ArrowDown' && options.length > 0) {
                        event.preventDefault();
                        setActiveOption(activeIndex + 1);
                    } else if (event.key === 'ArrowUp' && options.length > 0) {
                        event.preventDefault();
                        setActiveOption(activeIndex < 0 ? options.length - 1 : activeIndex - 1);
                    } else if (event.key === 'Enter' && activeIndex >= 0 && options[activeIndex]) {
                        event.preventDefault();
                        window.location.href = options[activeIndex].href;
                    } else if (event.key === 'Escape') {
                        closeDropdown();
                    }
                });

                list.addEventListener('pointerover', (event) => {
                    const option = event.target.closest('[role="option"]');
                    if (!option) return;
                    const options = Array.from(list.querySelectorAll('[role="option"]'));
                    setActiveOption(options.indexOf(option));
                });

                document.addEventListener('pointerdown', (event) => {
                    if (!form.contains(event.target)) closeDropdown();
                });
            })();
        </script>
    </body>
    </html>
    <?php
}

function staff_table_empty_row(int $columnCount, string $message = 'No records found.'): void
{
    ?>
    <tr><td colspan="<?php echo $columnCount; ?>" class="p-6 text-center text-slate-400"><?php echo staff_h($message); ?></td></tr>
    <?php
}

function staff_input_class(): string
{
    return 'w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-amber-500';
}

function staff_panel_class(): string
{
    return 'overflow-hidden rounded-2xl border border-amber-500/20 bg-slate-900/80 shadow-xl';
}

function staff_panel_heading(string $title): void
{
    ?>
    <div class="border-b border-slate-800 p-5">
        <h2 class="font-bold tracking-wide text-white"><?php echo staff_h($title); ?></h2>
    </div>
    <?php
}
