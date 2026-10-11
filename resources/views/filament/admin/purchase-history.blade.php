<div class="space-y-4">
    <p wire:loading>Carregando…</p>

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
</div>
