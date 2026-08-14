<?php

namespace App\Models;

use App\Policies\QuotePolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UsePolicy(QuotePolicy::class)]
class Quote extends Model
{
    protected $fillable = [
        'created_by',
        'source_quote_id',
        'title',
        'customer_title',
        'destination',
        'year',
        'month',
        'duration_days',
        'nights',
        'people_count',
        'budget_per_person',
        'total_amount',
        'per_person_amount',
        'planner_name',
        'wechat',
        'phone',
        'executor',
        'reminder_title',
        'reminder_text',
        'source_name',
        'source_url',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'duration_days' => 'integer',
            'nights' => 'integer',
            'people_count' => 'integer',
            'budget_per_person' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'per_person_amount' => 'decimal:2',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceQuote(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_quote_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(QuoteGroup::class)->orderBy('sort_order');
    }

    public function scopeHistorical(Builder $query): Builder
    {
        return $query->where('status', 'historical');
    }

    protected function highlights(): Attribute
    {
        return Attribute::get(function (): string {
            $meals = [
                'breakfast', 'lunch', 'dinner', 'meal', 'bbq',
                '早餐', '午餐', '晚餐', '餐', '烧烤',
            ];
            $transport = ['集合出发', '前往', '返程'];
            $featured = [
                '漂流', '景区', '游玩', '帆船', '出海', '上岛', '赶海', '打卡', '公园',
                '住宿', '入住', '酒店', '民宿', '别墅', '拓展', '团建', '轰趴', '探险', '瀑布',
            ];

            $items = $this->groups
                ->flatMap->items
                ->reject(function (QuoteItem $item) use ($featured, $meals, $transport): bool {
                    $name = mb_strtolower($item->name);
                    $hasFeature = collect($featured)->contains(fn (string $word): bool => str_contains($name, $word));
                    $isMeal = collect($meals)->contains(fn (string $word): bool => str_contains($name, $word));
                    $isTransportOnly = ! $hasFeature && collect($transport)->contains(
                        fn (string $word): bool => str_contains($name, $word)
                    );

                    return $item->is_tax || $isMeal || $isTransportOnly;
                })
                ->pluck('name')
                ->filter()
                ->unique();

            $priority = $items->filter(fn (string $name): bool => collect($featured)->contains(
                fn (string $word): bool => str_contains(mb_strtolower($name), $word)
            ));

            return $priority->concat($items->diff($priority))->take(3)->implode(' + ');
        });
    }
}
