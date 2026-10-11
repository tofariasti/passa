<div class="flex flex-col gap-8" wire:poll.4s>
    <div class="flex flex-col gap-2">
        <p class="text-sm font-medium uppercase tracking-widest text-emerald-400">Passa</p>
        <h1 class="text-3xl font-semibold tracking-tight">Meu cartão</h1>
        <p class="text-sm text-neutral-400">Cartão {{ $cardToken }}</p>
    </div>

    <p class="text-sm" wire:loading>Carregando…</p>

    <section class="flex flex-col gap-2">
        <h2 class="text-lg font-medium">Agora</h2>
        <p>Disponível: {{ $available['available_cents'] }} centavos</p>
        <p>Limite restante: {{ $available['limit_remaining_cents'] }} centavos</p>
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="text-lg font-medium">Extrato de {{ $statement['month'] }}</h2>
        <p>Limite: {{ $statement['limit_cents'] }} centavos</p>
        <p>Limite restante: {{ $statement['limit_remaining_cents'] }} centavos</p>

        @if ($statement['transactions'] === [])
            <p>Nenhuma transação neste mês.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr>
                        <th class="text-left">Quando</th>
                        <th class="text-left">Tipo</th>
                        <th class="text-left">Valor (centavos)</th>
                        <th class="text-left">Referência</th>
                        <th class="text-left">Limite restante depois</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($statement['transactions'] as $line)
                        <tr>
                            <td>{{ $line['occurred_at'] }}</td>
                            <td>{{ $line['type'] === 'hold' ? 'Reserva' : 'Ajuste' }}</td>
                            <td>{{ $line['amount_cents'] }}</td>
                            <td>{{ $line['reference'] }}</td>
                            <td>{{ $line['limit_remaining_after_cents'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="flex flex-col gap-4">
        <h2 class="text-lg font-medium">Compras</h2>

        @if ($histories === [])
            <p>Nenhuma compra.</p>
        @else
            @foreach ($histories as $history)
                <article class="flex flex-col gap-2">
                    <h3 class="font-medium">{{ $history['network_id'] }}</h3>
                    <p>
                        Decisão:
                        {{ $history['decision'] === 'approved' ? 'Aprovada' : 'Recusada' }}
                        ({{ $history['decision'] }})
                    </p>

                    @if ($history['reason'])
                        <p>Motivo: {{ $history['reason'] }}</p>
                    @endif

                    <table class="w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left">Quando</th>
                                <th class="text-left">Tipo</th>
                                <th class="text-left">Valor (centavos)</th>
                                <th class="text-left">Referência</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history['lines'] as $line)
                                <tr>
                                    <td>{{ $line['occurred_at'] }}</td>
                                    <td>
                                        @if ($line['kind'] === 'authorization')
                                            Autorização
                                        @elseif ($line['kind'] === 'capture')
                                            Captura{{ $line['final'] ? ' final' : '' }}
                                        @else
                                            Cancelamento
                                        @endif
                                    </td>
                                    <td>{{ $line['amount_cents'] ?? '—' }}</td>
                                    <td>{{ $line['reference'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </article>
            @endforeach
        @endif
    </section>
</div>
