<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    protected $fillable = [
        'quote_group_id',
        'time',
        'name',
        'unit',
        'quantity',
        'unit_price',
        'actual_total',
        'note',
        'is_tax',
        'tax_rate',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'actual_total' => 'decimal:2',
            'is_tax' => 'boolean',
            'tax_rate' => 'decimal:6',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(QuoteGroup::class, 'quote_group_id');
    }
}
