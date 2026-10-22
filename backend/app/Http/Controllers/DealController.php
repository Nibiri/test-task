<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealExport;
use App\Models\RefreshOperation;
use App\Support\FieldValue;
use App\Sync\DealFieldEditor;
use App\Sync\DispatchesSyncJobs;
use App\Sync\StaleResolution;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DealController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        // Поиск и фильтр идут по "внешнее приоритетнее локального" без JOIN на override:
        // это покрыто индексами deals_title_trgm и deals_displayed_status.
        // Локальные правки (override.local_value) в фильтр не попадают сознательно.
        $query = Deal::query()
            ->with(['overrides', 'latestRefresh', 'export'])
            ->orderByDesc('id');

        if ($search !== '') {
            $query->whereRaw(
                'COALESCE(external_title, title) ILIKE ?',
                ['%'.addcslashes($search, '\\%_').'%'],
            );
        }
        if ($status !== '') {
            $query->whereRaw('COALESCE(external_status, status) = ?', [$status]);
        }

        $page = $query->paginate(10)->withQueryString();

        return response()->json([
            'data' => $page->getCollection()->map(fn (Deal $deal) => $deal->toApiArray())->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(Request $request, DealFieldEditor $editor, DispatchesSyncJobs $jobs): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric'],
            'scenario' => ['nullable', 'in:success,rate_limit,failure'],
        ]);

        [$deal, $export] = DB::transaction(function () use ($editor, $data) {
            $deal = $editor->create([
                'title' => $data['title'],
                'status' => $data['status'],
                'amount' => $data['amount'],
            ]);
            $export = DealExport::query()->create([
                'deal_id' => $deal->id,
                'idempotency_key' => 'deal-'.$deal->id,
                'payload' => [
                    'title' => $deal->title,
                    'status' => $deal->status,
                    'amount' => FieldValue::canonical('amount', $deal->amount),
                ],
                'status' => 'accepted',
                'scenario' => $data['scenario'] ?? null,
                'dispatch_after' => now(),
            ]);

            return [$deal, $export];
        });

        $this->dispatchExport($jobs, $export);

        return response()->json(['data' => $deal->fresh()->toApiArray()], 202);
    }

    public function show(Deal $deal): JsonResponse
    {
        return response()->json(['data' => $deal->toApiArray()]);
    }

    public function update(Request $request, Deal $deal, DealFieldEditor $editor): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:64'],
            'amount' => ['sometimes', 'numeric'],
        ]);

        $updated = $editor->update($deal, $data);

        return response()->json(['data' => $updated->toApiArray()]);
    }

    public function refresh(Request $request, Deal $deal, DispatchesSyncJobs $jobs): JsonResponse
    {
        if ($deal->external_id === null) {
            return response()->json(['message' => 'У сделки нет external_id, обновлять нечего.'], 422);
        }

        $data = $request->validate([
            'scenario' => ['nullable', 'in:success,rate_limit,failure'],
        ]);

        try {
            $operation = DB::transaction(fn () => RefreshOperation::query()->create([
                'deal_id' => $deal->id,
                'status' => 'accepted',
                'scenario' => $data['scenario'] ?? null,
                'dispatch_after' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Обновление уже выполняется.',
                'data' => $deal->fresh()->toApiArray(),
            ], 409);
        }

        try {
            $jobs->dispatchRefresh($operation->id);
            $operation->update([
                'dispatch_after' => now()->addSeconds((int) config('sync.dispatch_grace_seconds')),
            ]);
        } catch (Throwable $exception) {
            Log::error('Refresh dispatch failed.', [
                'operation_id' => $operation->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $operation->refresh();

        return response()->json(['data' => $deal->fresh()->toApiArray(), 'refresh' => [
            'id' => $operation->id,
            'status' => $operation->status,
            'outcome' => $operation->outcome,
            'outcome_detail' => $operation->outcome_detail,
            'attempt_count' => $operation->attempt_count,
            'last_error' => $operation->last_error,
        ]], 202);
    }

    public function resolve(Request $request, Deal $deal, DealFieldEditor $editor): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', 'in:title,status,amount'],
            'action' => ['required', 'in:accept_external,keep_local'],
            'external_version' => ['required', 'integer'],
        ]);

        try {
            $updated = $editor->resolve($deal, $data['field'], $data['action'], (int) $data['external_version']);
        } catch (StaleResolution) {
            return response()->json([
                'message' => 'Внешняя версия изменилась. Обновите карточку и повторите решение.',
            ], 409);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Локальной правки этого поля нет.'], 404);
        }

        return response()->json(['data' => $updated->toApiArray()]);
    }

    public function retryExport(Deal $deal, DispatchesSyncJobs $jobs): JsonResponse
    {
        $export = $deal->export;
        if (! $export || $export->status !== 'failed' || $deal->external_id !== null) {
            return response()->json(['message' => 'Повторная отправка сейчас не нужна.'], 422);
        }

        try {
            $export->update([
                'status' => 'accepted',
                'last_error' => null,
                'lease_until' => null,
                'finished_at' => null,
                'dispatch_after' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'message' => 'Отправка в CRM уже выполняется.',
                'data' => $deal->fresh()->toApiArray(),
            ], 409);
        }
        $this->dispatchExport($jobs, $export);

        return response()->json(['data' => $deal->fresh()->toApiArray()], 202);
    }

    private function dispatchExport(DispatchesSyncJobs $jobs, DealExport $export): void
    {
        try {
            $jobs->dispatchExport($export->id);
            $export->update([
                'dispatch_after' => now()->addSeconds((int) config('sync.dispatch_grace_seconds')),
            ]);
        } catch (Throwable $exception) {
            Log::error('Deal export dispatch failed.', [
                'export_id' => $export->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
