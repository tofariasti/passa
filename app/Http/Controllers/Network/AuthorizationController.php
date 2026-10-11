<?php

declare(strict_types=1);

namespace App\Http\Controllers\Network;

use App\Actions\AuthorizePurchase;
use App\Domain\Authorization\AuthorizationMessage;
use App\Domain\Authorization\ParseAuthorization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthorizationController extends Controller
{
    public function store(Request $request, ParseAuthorization $parse, AuthorizePurchase $authorize): JsonResponse
    {
        $message = $parse($request->getContent(), (int) $request->header('X-Network-Timestamp'));

        if (! $message instanceof AuthorizationMessage) {
            return response()->json(['message' => 'The message does not follow the contract.'], 422);
        }

        $decision = $authorize->handle($message);

        return response()->json($decision['body'], $decision['status']);
    }
}
