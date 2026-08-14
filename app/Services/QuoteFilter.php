<?php

namespace App\Services;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Builder;

class QuoteFilter
{
    /** @param array<string, mixed> $filters */
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($this->integer($filters['year'] ?? null), fn (Builder $query, int $year) => $query->where('year', $year))
            ->when($this->integer($filters['month'] ?? null), fn (Builder $query, int $month) => $query->where('month', $month))
            ->when($this->text($filters['destination'] ?? null), fn (Builder $query, string $destination) => $query->where('destination', $destination))
            ->when($this->integer($filters['duration'] ?? null), fn (Builder $query, int $days) => $query->where('duration_days', $days))
            ->when($this->range($filters['people_range'] ?? null), function (Builder $query, array $range): void {
                $query->whereBetween('people_count', $range);
            })
            ->when($this->number($filters['budget_min'] ?? null), fn (Builder $query, float $minimum) => $query->where('per_person_amount', '>=', $minimum))
            ->when($this->number($filters['budget_max'] ?? null), fn (Builder $query, float $maximum) => $query->where('per_person_amount', '<=', $maximum))
            ->when($this->text($filters['keyword'] ?? null), function (Builder $query, string $keyword): void {
                $like = "%{$keyword}%";
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('title', 'like', $like)
                        ->orWhere('customer_title', 'like', $like)
                        ->orWhere('destination', 'like', $like)
                        ->orWhere('source_name', 'like', $like)
                        ->orWhereHas('groups.items', fn (Builder $items) => $items
                            ->where('name', 'like', $like)
                            ->orWhere('note', 'like', $like));
                });
            });
    }

    public function history(array $filters): Builder
    {
        return $this->apply(
            Quote::query()->historical()->with(['createdBy', 'groups.items']),
            $filters
        );
    }

    private function integer(mixed $value): ?int
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : null;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' && $text !== 'all' ? $text : null;
    }

    /** @return array{int, int}|null */
    private function range(mixed $value): ?array
    {
        return preg_match('/^(\d+)-(\d+)$/', (string) $value, $matches)
            ? [(int) $matches[1], (int) $matches[2]]
            : null;
    }
}
