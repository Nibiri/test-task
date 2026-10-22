<?php

declare(strict_types=1);

namespace App\Sync;

use App\Support\FieldValue;
use Carbon\CarbonImmutable;

final class ExternalDealDTO
{
    public function __construct(
        public readonly string $id,
        public readonly int $version,
        public readonly CarbonImmutable $updatedAt,
        public readonly string $title,
        public readonly string $status,
        public readonly string $amount,
    ) {}

    public function field(string $name): string
    {
        return match ($name) {
            'title' => $this->title,
            'status' => $this->status,
            'amount' => $this->amount,
            default => throw new \InvalidArgumentException('Unknown deal field.'),
        };
    }

    public static function fromArray(array $deal): self
    {
        return new self(
            id: (string) $deal['id'],
            version: (int) $deal['version'],
            updatedAt: CarbonImmutable::parse($deal['updated_at']),
            title: (string) $deal['title'],
            status: (string) $deal['status'],
            amount: FieldValue::canonical('amount', $deal['amount']),
        );
    }
}
