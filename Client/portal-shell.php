<?php
if (!defined('CLIENT_PORTAL_ACCESS')) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-client-theme="amber">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Law Office Management System - Client Portal</title>
    <script>
        window.CLIENT_PORTAL_THEME_STORAGE_KEY = <?php echo json_encode(
            'client-portal-theme:' . strtolower((string) $clientPortalData['profile']['email']),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
        ); ?>;
        try {
            const savedTheme = window.localStorage.getItem(window.CLIENT_PORTAL_THEME_STORAGE_KEY);
            if (['amber', 'ocean', 'emerald'].includes(savedTheme)) {
                document.documentElement.dataset.clientTheme = savedTheme;
            }
        } catch (error) {
            console.error('Unable to read the Client portal theme from browser storage.', error);
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/react@18/umd/react.development.js" crossorigin></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.development.js" crossorigin></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <link rel="stylesheet" href="styles.css?v=2">
</head>
<body class="bg-[#020617] text-slate-100 antialiased selection:bg-amber-500 selection:text-slate-950">
    <div id="root"></div>
    <script>
        window.CLIENT_PORTAL_DATA = <?php echo json_encode(
            $clientPortalData,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
        ); ?>;
    </script>
    <script type="text/babel" src="js/client-app.js?v=3"></script>
</body>
</html>
