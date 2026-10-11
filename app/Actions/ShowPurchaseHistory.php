<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Purchase;

final readonly class ShowPurchaseHistory
{
    /**
     * @return array{network_id: string, decision: string, reason: string|null, lines: list<array{occurred_at: string, kind: string, amount_cents: int|null, reference: string, sequence: int|null, final: bool|null}>}|null
     */
    public function handle(int $purchaseId): ?array
    {
        $purchase = Purchase::query()->with('events')->find($purchaseId);

        if (! $purchase instanceof Purchase) {
            return null;
        }

        $rows = [[
            'at' => $purchase->occurred_at,
            'tie' => 0,
            'id' => 0,
            'kind' => 'authorization',
            'amount_cents' => $purchase->amount_cents,
            'reference' => $purchase->network_authorization_id,
            'sequence' => null,
            'final' => null,
        ]];

        foreach ($purchase->events as $event) {
            $rows[] = [
                'at' => $event->occurred_at,
                'tie' => 1,
                'id' => $event->id,
                'kind' => $event->type->value,
                'amount_cents' => $event->amount_cents,
                'reference' => $event->network_id,
                'sequence' => $event->sequence,
                'final' => $event->is_final,
            ];
        }

        usort($rows, function (array $left, array $right): int {
            $byTime = $left['at'] <=> $right['at'];

            if ($byTime !== 0) {
                return $byTime;
            }

            return [$left['tie'], $left['id']] <=> [$right['tie'], $right['id']];
        });

        return [
            'network_id' => $purchase->network_authorization_id,
            'decision' => $purchase->decision->value,
            'reason' => $purchase->reason?->value,
            'lines' => array_map(fn (array $row): array => [
                'occurred_at' => $row['at']->utc()->format('Y-m-d\TH:i:s\Z'),
                'kind' => $row['kind'],
                'amount_cents' => $row['amount_cents'],
                'reference' => $row['reference'],
                'sequence' => $row['sequence'],
                'final' => $row['final'],
            ], $rows),
        ];
    }
}
