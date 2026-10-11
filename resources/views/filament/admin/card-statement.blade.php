<div class="space-y-4">
    <label class="block text-sm" for="statement-month">
        Mês
        <input
            id="statement-month"
            class="mt-1 block rounded-lg border px-3 py-2"
            type="text"
            wire:model.live="month"
            placeholder="AAAA-MM"
        />
    </label>

    <p class="text-sm" wire:loading>Carregando…</p>

    @if ($statement === null)
        <p>Mês inválido. Use AAAA-MM.</p>
    @else
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
    @endif
</div>
