<!DOCTYPE html>
<html lang="en" data-accent="cyan" data-neutral="slate" data-surface="fills" data-shape="soft" data-type="loud" data-color="quiet">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <script>
            // Apply a saved light or dark choice before the styles load, so the page never flashes the wrong theme.
            try {
                const theme = localStorage.getItem('theme');
                if (theme === 'light' || theme === 'dark') document.documentElement.classList.add(theme);
            } catch (e) {}
        </script>
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body>
        <a class="skip" href="#main">Skip to content</a>
        @inertia
    </body>
</html>
