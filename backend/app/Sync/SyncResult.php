<?php

declare(strict_types=1);

namespace App\Sync;

final class SyncResult
{
    /**
     * @param  list<string>  $preserved
     * @param  list<string>  $conflicted
     * @param  list<string>  $converged
     * @param  list<string>  $accepted
     */
    public function __construct(
        public readonly string $outcome,
        public readonly ?string $reason,
        public readonly ?int $dealId,
        public readonly array $preserved = [],
        public readonly array $conflicted = [],
        public readonly array $converged = [],
        public readonly array $accepted = [],
    ) {}

    public function detail(): array
    {
        return [
            'outcome' => $this->outcome,
            'reason' => $this->reason,
            'preserved' => $this->preserved,
            'conflicted' => $this->conflicted,
            'converged' => $this->converged,
            'accepted' => $this->accepted,
        ];
    }
}
