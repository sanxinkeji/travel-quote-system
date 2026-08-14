<?php

namespace App\Http\Requests;

use App\Services\QuoteCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class QuoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $prepared = [];

        if (! $this->has('status')) {
            $prepared['status'] = $this->route('quote')?->status ?? 'historical';
        }

        $durationDays = filter_var($this->input('duration_days'), FILTER_VALIDATE_INT);
        if ($durationDays !== false) {
            $prepared['nights'] = max((int) $durationDays - 1, 0);
        }

        $this->merge($prepared);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'customer_title' => ['nullable', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'duration_days' => ['required', 'integer', 'in:1,2,3,4'],
            'nights' => ['required', 'integer', 'in:0,1,2,3'],
            'people_count' => ['required', 'integer', 'between:1,10000'],
            'planner_name' => ['nullable', 'string', 'max:100'],
            'wechat' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'executor' => ['nullable', 'string', 'max:255'],
            'reminder_title' => ['nullable', 'string', 'max:255'],
            'reminder_text' => ['nullable', 'string', 'max:10000'],
            'status' => ['required', 'in:draft,historical'],
            'groups' => ['required', 'array', 'min:1'],
            'groups.*.name' => ['required', 'string', 'max:100'],
            'groups.*.type' => ['nullable', 'in:day,other'],
            'groups.*.items' => ['required', 'array', 'min:1'],
            'groups.*.items.*.time' => ['nullable', 'string', 'max:100'],
            'groups.*.items.*.name' => ['required', 'string', 'max:255'],
            'groups.*.items.*.unit' => ['nullable', 'string', 'max:100'],
            'groups.*.items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'groups.*.items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'groups.*.items.*.actual_total' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'groups.*.items.*.note' => ['nullable', 'string', 'max:5000'],
            'groups.*.items.*.is_tax' => ['nullable', 'boolean'],
            'groups.*.items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $groups = $this->input('groups');
            if (! is_array($groups)) {
                return;
            }

            $otherIndexes = [];
            foreach ($groups as $groupIndex => $group) {
                if (! is_array($group)) {
                    continue;
                }

                $isOther = ($group['type'] ?? 'day') === 'other';
                if ($isOther) {
                    $otherIndexes[] = $groupIndex;

                    continue;
                }

                foreach (($group['items'] ?? []) as $itemIndex => $item) {
                    if (is_array($item) && app(QuoteCalculator::class)->isTaxItem($item)) {
                        $validator->errors()->add('groups', '税费项目只能放在其他项分组中。');
                        $validator->errors()->add("groups.{$groupIndex}.items.{$itemIndex}.is_tax", '税费项目只能放在其他项分组中。');
                    }
                }
            }

            if (count($otherIndexes) > 1) {
                $validator->errors()->add('groups', '其他项分组最多只能有一个。');
            }

            if ($otherIndexes !== [] && $otherIndexes[0] !== array_key_last($groups)) {
                $validator->errors()->add('groups', '其他项分组必须位于报价表最后。');
            }
        }];
    }
}
