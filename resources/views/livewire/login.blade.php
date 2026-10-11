<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <p class="text-sm font-medium uppercase tracking-widest text-emerald-400">Passa</p>
        <h1 class="text-3xl font-semibold tracking-tight">Entrar</h1>
    </div>

    <form class="flex flex-col gap-4" wire:submit="authenticate">
        <label class="flex flex-col gap-1 text-sm" for="email">
            E-mail
            <input
                id="email"
                class="rounded-lg border border-neutral-700 bg-neutral-900 px-3 py-2 text-neutral-100"
                type="email"
                name="email"
                wire:model="email"
                autocomplete="username"
                required
            />
        </label>

        <label class="flex flex-col gap-1 text-sm" for="password">
            Senha
            <input
                id="password"
                class="rounded-lg border border-neutral-700 bg-neutral-900 px-3 py-2 text-neutral-100"
                type="password"
                name="password"
                wire:model="password"
                autocomplete="current-password"
                required
            />
        </label>

        @error('email')
            <p class="text-sm text-red-400">{{ $message }}</p>
        @enderror

        @error('password')
            <p class="text-sm text-red-400">{{ $message }}</p>
        @enderror

        <button class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-medium text-neutral-950" type="submit">
            <span wire:loading.remove wire:target="authenticate">Entrar</span>
            <span wire:loading wire:target="authenticate">Carregando…</span>
        </button>
    </form>
</div>
