<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <title>{{ $title ?? 'Meu cartão' }} · {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-neutral-950 text-neutral-100 antialiased">
        <main class="mx-auto flex min-h-screen max-w-3xl flex-col gap-8 px-6 py-10">
            {{ $slot }}
        </main>
    </body>
</html>
