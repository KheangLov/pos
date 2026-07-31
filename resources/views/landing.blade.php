<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Omni POS') }}</title>

    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="grid min-h-screen place-items-center bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <main class="flex flex-col items-center gap-6 px-6 text-center">
        <span class="grid h-14 w-14 place-items-center rounded-2xl bg-amber-400 text-zinc-950">
            <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13 5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17"/><circle cx="9" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/></svg>
        </span>

        <div>
            <h1 class="text-xl font-semibold tracking-tight">{{ config('app.name', 'Omni POS') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Point of sale</p>
        </div>

        <a
            href="/admin"
            class="rounded-lg bg-amber-400 px-5 py-2.5 text-sm font-semibold text-zinc-950 transition hover:bg-amber-300"
        >
            Sign in
        </a>
    </main>
</body>
</html>
