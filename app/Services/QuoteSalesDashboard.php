<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class QuoteSalesDashboard
{
    /** @return array{value: string, start: CarbonImmutable, end: CarbonImmutable} */
    public function period(?string $reportMonth): array
    {
        if (is_string($reportMonth) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $reportMonth) === 1) {
            $start = CarbonImmutable::createFromFormat('!Y-m', $reportMonth, config('app.timezone'));
        } else {
            $start = CarbonImmutable::now(config('app.timezone'))->startOfMonth();
        }

        return [
            'value' => $start->format('Y-m'),
            'start' => $start->startOfDay(),
            'end' => $start->endOfMonth()->endOfDay(),
        ];
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    /** @return array{issued_count: int, won_count: int, won_amount: float} */
    public function summary(array $period, ?int $creatorId = null, ?User $viewer = null): array
    {
        if ($viewer && ! $viewer->isAdmin()) {
            $creatorId = $viewer->id;
        }

        $issued = Quote::query()->historical()
            ->when($creatorId, fn (Builder $query, int $id) => $query->where('created_by', $id))
            ->whereBetween('created_at', [$period['start'], $period['end']])
            ->count();

        $won = Quote::query()->historical()->won()
            ->when($creatorId, fn (Builder $query, int $id) => $query->where('created_by', $id))
            ->whereBetween('won_at', [$period['start'], $period['end']]);

        return [
            'issued_count' => $issued,
            'won_count' => (clone $won)->count(),
            'won_amount' => (float) (clone $won)->sum('total_amount'),
        ];
    }
}
