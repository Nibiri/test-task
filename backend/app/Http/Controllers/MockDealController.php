<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Sync\MockExternalDealGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MockDealController extends Controller
{
    public function store(Request $request, MockExternalDealGateway $gateway): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric'],
        ]);
        $key = (string) $request->header('Idempotency-Key', '');
        if ($key === '') {
            return response()->json(['message' => 'Idempotency-Key is required.'], 400);
        }

        $result = $gateway->create(
            $key,
            $data['title'],
            $data['status'],
            \App\Support\FieldValue::canonical('amount', $data['amount']),
            $request->query('scenario'),
        );
        $headers = $result['retry_after'] !== null ? ['Retry-After' => $result['retry_after']] : [];

        return response()->json($result['body'], $result['status'], $headers);
    }

    public function show(Request $request, string $externalId, MockExternalDealGateway $gateway): JsonResponse
    {
        $result = $gateway->respond($externalId, $request->query('scenario'));
        $headers = $result['retry_after'] !== null ? ['Retry-After' => $result['retry_after']] : [];

        return response()->json($result['body'], $result['status'], $headers);
    }
}
