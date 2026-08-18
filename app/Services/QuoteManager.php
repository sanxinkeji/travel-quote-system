<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class QuoteManager
{
    public function __construct(private readonly QuoteCalculator $calculator) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $creator): Quote
    {
        return DB::transaction(function () use ($data, $creator): Quote {
            $quote = new Quote;
            $quote->created_by = $creator->id;

            return $this->persist($quote, $data);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Quote $quote, array $data): Quote
    {
        return DB::transaction(function () use ($quote, $data): Quote {
            $quote->groups()->delete();

            return $this->persist($quote, $data);
        });
    }

    /** @param array<string, mixed> $data */
    public function createCopy(Quote $source, array $data, User $creator): Quote
    {
        return DB::transaction(function () use ($source, $data, $creator): Quote {
            $copy = new Quote;
            $copy->created_by = $creator->id;
            $copy->source_quote_id = $source->id;
            $data['status'] = 'historical';

            return $this->persist($copy, $data);
        });
    }

    public function updateSalesStatus(Quote $quote, string $salesStatus): Quote
    {
        return DB::transaction(function () use ($quote, $salesStatus): Quote {
            if ($quote->sales_status === $salesStatus) {
                return $quote;
            }

            $quote->sales_status = $salesStatus;
            $quote->won_at = $salesStatus === Quote::SALES_WON ? now() : null;
            $quote->save();

            return $quote->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    private function persist(Quote $quote, array $data): Quote
    {
        $calculation = $this->calculator->calculate($data['groups'], (int) $data['people_count']);
        $quote->fill(Arr::except($data, ['groups', 'total_amount', 'per_person_amount']));
        $quote->total_amount = $calculation['total'];
        $quote->per_person_amount = $calculation['per_person'];
        $quote->budget_per_person = $calculation['per_person'];
        $quote->save();

        foreach ($calculation['groups'] as $groupOrder => $groupData) {
            $group = $quote->groups()->create([
                'name' => $groupData['name'],
                'type' => $groupData['type'] ?? 'day',
                'sort_order' => $groupOrder,
                'subtotal' => $groupData['subtotal'],
            ]);

            foreach ($groupData['items'] as $itemOrder => $itemData) {
                $group->items()->create([
                    'time' => $itemData['time'] ?? null,
                    'name' => $itemData['name'],
                    'unit' => $itemData['unit'] ?? null,
                    'quantity' => $itemData['quantity'] ?? 0,
                    'unit_price' => $itemData['unit_price'],
                    'actual_total' => $itemData['actual_total'],
                    'note' => $itemData['note'] ?? null,
                    'is_tax' => $itemData['is_tax'],
                    'tax_rate' => $itemData['tax_rate'],
                    'sort_order' => $itemOrder,
                ]);
            }
        }

        return $quote->load(['createdBy', 'groups.items']);
    }
}
