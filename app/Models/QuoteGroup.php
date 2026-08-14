<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteGroup extends Model
{
    protected $fillable = ['quote_id', 'name', 'type', 'sort_order', 'subtotal'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'subtotal' => 'decimal:2'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order');
    }
}
