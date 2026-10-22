<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\WebhookEvent;
use App\Sync\DispatchesSyncJobs;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class WebhookController extends Controller
{
    public function store(Request $request, DispatchesSyncJobs $jobs): JsonResponse
    {
        if (! $this->tokenMatches($request)) {
            Log::warning('Rejected webhook: token mismatch.');

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'event_id' => ['required', 'string', 'max:255'],
            'event_time' => ['required', 'date'],
            'deal' => ['required', 'array'],
            'deal.id' => ['required', 'string', 'max:64'],
            'deal.version' => ['required', $this->integerValue()],
            'deal.updated_at' => ['required', 'date'],
            'deal.title' => ['required', 'string', 'max:255'],
            'deal.status' => ['required', 'string', 'max:64'],
            'deal.amount' => ['required', $this->numberValue()],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        try {
            $event = DB::transaction(fn () => WebhookEvent::query()->create([
                'event_id' => $data['event_id'],
                'event_time' => $data['event_time'],
                'external_id' => $data['deal']['id'],
                'payload' => $data,
                'status' => 'received',
                'dispatch_after' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        try {
            $jobs->dispatchWebhook($event->id);
            $event->update([
                'dispatch_after' => now()->addSeconds((int) config('sync.dispatch_grace_seconds')),
            ]);
        } catch (Throwable $exception) {
            Log::error('Webhook dispatch failed.', [
                'event_id' => $event->event_id,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json(['status' => 'received', 'id' => $event->id], 202);
    }

    private function tokenMatches(Request $request): bool
    {
        $expected = (string) config('sync.webhook_token');
        if ($expected === '') {
            return false;
        }

        return hash_equals($expected, (string) $request->header('X-Webhook-Token', ''));
    }

    private function integerValue(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_int($value)) {
                $fail($attribute.' must be an integer.');
            }
        };
    }

    private function numberValue(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_int($value) && ! is_float($value)) {
                $fail($attribute.' must be a number.');
            }
        };
    }
}
