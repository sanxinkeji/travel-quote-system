<?php

namespace Tests\Feature\Quotes;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteSalesStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_historical_quote_defaults_to_following_sales_state_without_won_timestamp(): void
    {
        $quote = Quote::query()->create($this->quoteAttributes([
            'created_by' => User::factory()->create()->id,
        ]));
        $quote->refresh();

        $this->assertSame('historical', $quote->status);
        $this->assertSame(Quote::SALES_FOLLOWING, $quote->sales_status);
        $this->assertNull($quote->won_at);
        $this->assertSame('跟进中', $quote->sales_status_label);
    }

    public function test_won_scope_requires_won_sales_status_and_timestamp(): void
    {
        $owner = User::factory()->create();
        $won = Quote::query()->create($this->quoteAttributes([
            'created_by' => $owner->id,
            'title' => 'Won quote',
            'sales_status' => Quote::SALES_WON,
            'won_at' => now(),
        ]));
        Quote::query()->create($this->quoteAttributes([
            'created_by' => $owner->id,
            'title' => 'Won status without timestamp',
            'sales_status' => Quote::SALES_WON,
        ]));

        $this->assertSame([$won->id], Quote::query()->won()->pluck('id')->all());
    }

    public function test_sales_status_label_falls_back_to_following_for_unknown_status(): void
    {
        $quote = new Quote(['sales_status' => 'unknown']);

        $this->assertSame('跟进中', $quote->sales_status_label);
    }

    /** @return array<string, mixed> */
    private function quoteAttributes(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Quote',
            'customer_title' => 'Customer',
            'destination' => 'Huizhou',
            'year' => 2026,
            'month' => 7,
            'duration_days' => 2,
            'nights' => 1,
            'people_count' => 30,
            'budget_per_person' => 900,
            'total_amount' => 24984.2,
            'per_person_amount' => 832.81,
            'status' => 'historical',
        ], $overrides);
    }
}
