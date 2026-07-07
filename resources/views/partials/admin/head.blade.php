{{-- Apply the persisted theme before first paint — must stay in sync with the
     `darkMode` policy passed to registerBlatUI() in resources/js/app.js. Runs
     synchronously (no defer/module) so it executes before Vite's JS bundle
     loads, preventing a flash of the wrong theme. --}}
<script>
    (function () {
        try {
            var root = document.documentElement;
            var get = function (key) { return localStorage.getItem('theme:' + key); };
            var mode = get('mode') || 'system';
            var isDark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            root.classList.toggle('dark', isDark);

            var attr = function (name, value, fallback) {
                if (value && value !== fallback) root.setAttribute(name, value);
            };
            attr('data-base', get('base'), 'neutral');
            attr('data-theme', get('preset'), 'default');
            attr('data-font', get('font'), 'sans');
            attr('data-shadow', get('shadow'), 'default');
            attr('data-spacing', get('spacing'), 'default');
            attr('data-tracking', get('tracking'), 'normal');
            attr('data-input-style', get('inputStyle'), 'outline');
            attr('data-font-heading', get('fontHeading'), 'sans');
            root.setAttribute('data-radius', get('radius') || '0.625');
        } catch (e) {}
    })();
</script>

@include('partials.admin.meta', ['title' => $title ?? null, 'description' => $description ?? null])

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])

@stack('head')
