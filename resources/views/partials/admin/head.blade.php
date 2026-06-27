@include('partials.admin.meta', ['title' => $title ?? null, 'description' => $description ?? null])

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])

@stack('head')
