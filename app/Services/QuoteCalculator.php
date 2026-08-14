<?php

namespace App\Services;

class QuoteCalculator
{
    /**
     * @param  array<int, array<string, mixed>>  $groups
     * @return array{groups: array<int, array<string, mixed>>, tax_base: float, total: float, per_person: float}
     */
    public function calculate(array $groups, int $peopleCount): array
    {
        $taxBase = 0.0;

        foreach ($groups as $group) {
            foreach ($group['items'] ?? [] as $item) {
                if (! $this->isTaxItem($item)) {
                    $taxBase += $this->normalItemTotal($item);
                }
            }
        }

        $groups = array_map(function (array $group) use ($taxBase): array {
            $subtotal = 0.0;
            $items = array_map(function (array $item) use ($taxBase, &$subtotal): array {
                $isTax = $this->isTaxItem($item);
                $taxRate = $isTax ? $this->taxRate($item) : 0.0;
                $lineTotal = $isTax
                    ? $this->money($taxBase * $taxRate * $this->number($item['quantity'] ?? 1))
                    : $this->normalItemTotal($item);

                $item['is_tax'] = $isTax;
                $item['tax_rate'] = $taxRate;
                $item['unit_price'] = $isTax ? $taxBase : $this->number($item['unit_price'] ?? 0);
                $item['line_total'] = $lineTotal;
                $item['actual_total'] = $lineTotal;
                $subtotal += $lineTotal;

                return $item;
            }, $group['items'] ?? []);

            $group['items'] = $items;
            $group['subtotal'] = $this->money($subtotal);

            return $group;
        }, $groups);

        $total = $this->money(array_sum(array_column($groups, 'subtotal')));

        return [
            'groups' => $groups,
            'tax_base' => $this->money($taxBase),
            'total' => $total,
            'per_person' => $peopleCount > 0 ? $this->money($total / $peopleCount) : 0.0,
        ];
    }

    /** @param array<string, mixed> $item */
    public function isTaxItem(array $item): bool
    {
        if (filter_var($item['is_tax'] ?? false, FILTER_VALIDATE_BOOL)) {
            return true;
        }

        $name = strtolower((string) ($item['name'] ?? ''));

        return str_contains($name, 'invoice') || str_contains($name, "\u{53D1}\u{7968}");
    }

    /** @param array<string, mixed> $item */
    private function normalItemTotal(array $item): float
    {
        $explicit = $item['actual_total'] ?? $item['line_total'] ?? null;

        if ($explicit !== null && $explicit !== '') {
            return $this->money($this->number($explicit));
        }

        return $this->money(
            $this->number($item['quantity'] ?? 0) * $this->number($item['unit_price'] ?? 0)
        );
    }

    /** @param array<string, mixed> $item */
    private function taxRate(array $item): float
    {
        if (isset($item['tax_rate']) && $this->number($item['tax_rate']) > 0) {
            $rate = $this->number($item['tax_rate']);

            return $rate > 1 ? $rate / 100 : $rate;
        }

        $subject = implode(' ', [(string) ($item['unit'] ?? ''), (string) ($item['name'] ?? '')]);

        return preg_match('/(\d+(?:\.\d+)?)\s*%/', $subject, $match)
            ? ((float) $match[1]) / 100
            : 0.0;
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function money(float $value): float
    {
        return round($value, 2);
    }
}
