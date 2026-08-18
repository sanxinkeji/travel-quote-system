<?php

namespace Tests\Feature\Quotes;

use App\Models\Quote;
use App\Models\User;
use App\Services\QuoteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_owner_can_mark_a_quote_won_and_reverting_clears_won_time(): void
    {
        $owner = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $quote = Quote::query()->create($this->quoteAttributes(['created_by' => $owner->id]));
        $wonAt = Carbon::create(2026, 8, 18, 10, 30, 0, config('app.timezone'));
        Carbon::setTestNow($wonAt);

        $this->actingAs($owner)->patch(route('quotes.sales-status', $quote), [
            'sales_status' => Quote::SALES_WON,
        ])->assertRedirect();

        $this->assertSame(Quote::SALES_WON, $quote->fresh()->sales_status);
        $this->assertTrue($quote->fresh()->won_at->equalTo($wonAt));

        $this->actingAs($owner)->patch(route('quotes.sales-status', $quote), [
            'sales_status' => Quote::SALES_OTHER,
        ])->assertRedirect();

        $this->assertSame(Quote::SALES_OTHER, $quote->fresh()->sales_status);
        $this->assertNull($quote->fresh()->won_at);
        Carbon::setTestNow();
    }

    public function test_repeating_won_status_does_not_refresh_the_original_won_time(): void
    {
        $owner = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $quote = Quote::query()->create($this->quoteAttributes(['created_by' => $owner->id]));
        $firstWonAt = Carbon::create(2026, 8, 18, 10, 30, 0, config('app.timezone'));
        Carbon::setTestNow($firstWonAt);

        $this->actingAs($owner)->patch(route('quotes.sales-status', $quote), [
            'sales_status' => Quote::SALES_WON,
        ])->assertRedirect();

        Carbon::setTestNow($firstWonAt->copy()->addDay());
        $this->actingAs($owner)->patch(route('quotes.sales-status', $quote), [
            'sales_status' => Quote::SALES_WON,
        ])->assertRedirect();

        $this->assertTrue($quote->fresh()->won_at->equalTo($firstWonAt));
        Carbon::setTestNow();
    }

    public function test_employee_cannot_change_another_users_status_but_admin_can(): void
    {
        $owner = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $quote = Quote::query()->create($this->quoteAttributes(['created_by' => $owner->id]));

        $this->actingAs($other)->patch(route('quotes.sales-status', $quote), [
            'sales_status' => Quote::SALES_WON,
        ])->assertForbidden();

        $this->actingAs($admin)->patch(route('quotes.sales-status', $quote), [
            'sales_status' => Quote::SALES_WON,
        ])->assertRedirect();

        $this->assertSame(Quote::SALES_WON, $quote->fresh()->sales_status);
    }

    public function test_unknown_sales_status_is_rejected_and_a_real_change_is_audited(): void
    {
        $owner = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        $quote = Quote::query()->create($this->quoteAttributes(['created_by' => $owner->id]));

        $this->actingAs($owner)->patch(route('quotes.sales-status', $quote), [
            'sales_status' => 'cancelled',
        ])->assertSessionHasErrors('sales_status');

        $this->actingAs($owner)->patch(route('quotes.sales-status', $quote), [
            'sales_status' => Quote::SALES_WON,
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $owner->id,
            'action' => 'quote.sales_status_changed',
            'subject_id' => $quote->id,
        ]);
    }

    public function test_regular_quote_update_cannot_overwrite_sales_transition_fields(): void
    {
        $owner = User::factory()->create();
        $wonAt = Carbon::create(2026, 8, 10, 9, 0, 0, config('app.timezone'));
        $quote = Quote::query()->create($this->quoteAttributes([
            'created_by' => $owner->id,
            'sales_status' => Quote::SALES_WON,
            'won_at' => $wonAt,
        ]));

        app(QuoteManager::class)->update($quote, $this->managerPayload([
            'sales_status' => Quote::SALES_OTHER,
            'won_at' => null,
        ]));

        $quote->refresh();
        $this->assertSame(Quote::SALES_WON, $quote->sales_status);
        $this->assertTrue($quote->won_at->equalTo($wonAt));
    }

    public function test_regular_create_and_copy_always_start_in_following_sales_state(): void
    {
        $owner = User::factory()->create();
        $manager = app(QuoteManager::class);
        $spoofedSalesFields = [
            'sales_status' => Quote::SALES_WON,
            'won_at' => now(),
        ];

        $created = $manager->create($this->managerPayload($spoofedSalesFields), $owner);
        $copy = $manager->createCopy($created, $this->managerPayload($spoofedSalesFields), $owner);

        foreach ([$created, $copy] as $quote) {
            $this->assertSame(Quote::SALES_FOLLOWING, $quote->sales_status);
            $this->assertNull($quote->won_at);
        }
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

    /** @return array<string, mixed> */
    private function managerPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Managed quote',
            'customer_title' => 'Customer',
            'destination' => 'Huizhou',
            'year' => 2026,
            'month' => 8,
            'duration_days' => 1,
            'nights' => 0,
            'people_count' => 20,
            'groups' => [[
                'name' => 'DAY 01',
                'type' => 'day',
                'items' => [[
                    'name' => 'Activity',
                    'quantity' => 1,
                    'unit_price' => 1000,
                ]],
            ]],
        ], $overrides);
    }
}
