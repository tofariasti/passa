<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Actions\RecordPurchaseEvent;
use App\Domain\Events\EventMessage;
use App\Domain\Events\ParseEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EventController extends Controller
{
    public function store(Request $request, ParseEvent $parse, RecordPurchaseEvent $record): JsonResponse
    {
        $message = $parse($request->getContent(), (int) $request->header('X-Network-Timestamp'));

        if (! $message instanceof EventMessage) {
            return response()->json(['message' => 'The message does not follow the contract.'], 422);
        }

        $result = $record->handle($message);

        return response()->json($result['body'], $result['status']);
    }
}
