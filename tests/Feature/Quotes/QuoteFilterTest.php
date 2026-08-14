<?php

namespace Tests\Feature\Quotes;

use App\Models\Quote;
use App\Models\QuoteGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_filters_quotes_by_all_supported_history_fields(): void
    {
        $user = User::factory()->create([
            'username' => fake()->unique()->userName(),
            'role' => 'employee',
            'is_active' => true,
        ]);
        $matching = $this->quote($user, [
            'title' => 'Summer sailing plan',
            'destination' => 'Huizhou',
            'year' => 2026,
            'month' => 7,
            'duration_days' => 2,
            'people_count' => 30,
            'per_person_amount' => 832.81,
            'source_name' => 'July source.xlsx',
        ]);
        $group = QuoteGroup::query()->create([
            'quote_id' => $matching->id,
            'name' => 'DAY 01',
            'type' => 'day',
            'sort_order' => 0,
            'subtotal' => 100,
        ]);
        $group->items()->create([
            'name' => 'Double-hull sailing',
            'unit' => 'boat',
            'quantity' => 1,
            'unit_price' => 100,
            'actual_total' => 100,
            'sort_order' => 0,
        ]);
        $this->quote($user, [
            'title' => 'Other plan',
            'destination' => 'Qingyuan',
            'year' => 2025,
            'month' => 8,
            'duration_days' => 1,
            'people_count' => 60,
            'per_person_amount' => 450,
        ]);

        $response = $this->actingAs($user)->get(route('quotes.index', [
            'year' => 2026,
            'month' => 7,
            'destination' => 'Huizhou',
            'duration' => 2,
            'people_range' => '21-30',
            'budget_min' => 800,
            'budget_max' => 900,
            'keyword' => 'sailing',
        ]));

        $response->assertOk();
        $response->assertViewHas('quotes', function ($quotes) use ($matching): bool {
            return $quotes->count() === 1 && $quotes->first()->is($matching);
        });
    }

    public function test_people_filter_supports_the_151_to_200_range(): void
    {
        $user = User::factory()->create([
            'username' => fake()->unique()->userName(),
            'role' => 'employee',
            'is_active' => true,
        ]);
        $matching = $this->quote($user, ['people_count' => 180]);
        $this->quote($user, ['people_count' => 150, 'title' => 'Not in range']);

        $response = $this->actingAs($user)->get(route('quotes.index', ['people_range' => '151-200']));

        $response->assertViewHas('quotes', fn ($quotes): bool => $quotes->count() === 1 && $quotes->first()->is($matching));
    }

    public function test_admin_sees_the_delete_action_on_the_historical_quote_list(): void
    {
        $owner = User::factory()->create([
            'username' => fake()->unique()->userName(),
            'role' => 'employee',
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'username' => fake()->unique()->userName(),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $quote = $this->quote($owner, []);

        $response = $this->actingAs($admin)->get(route('quotes.index'));

        $response->assertOk();
        $response->assertSee('action="'.route('quotes.destroy', $quote).'"', false);
        $response->assertSee('name="_method" value="DELETE"', false);
        $response->assertSee('data-confirm="确定删除这份原始报价吗？删除后无法恢复。"', false);
        $response->assertSee('aria-label="删除原始报价"', false);
    }

    public function test_employee_does_not_see_the_delete_action_on_the_historical_quote_list(): void
    {
        $owner = User::factory()->create([
            'username' => fake()->unique()->userName(),
            'role' => 'employee',
            'is_active' => true,
        ]);
        $employee = User::factory()->create([
            'username' => fake()->unique()->userName(),
            'role' => 'employee',
            'is_active' => true,
        ]);
        $quote = $this->quote($owner, []);

        $response = $this->actingAs($employee)->get(route('quotes.index'));

        $response->assertOk();
        $response->assertDontSee('action="'.route('quotes.destroy', $quote).'"', false);
        $response->assertDontSee('aria-label="删除原始报价"', false);
    }

    /** @param array<string, mixed> $attributes */
    private function quote(User $owner, array $attributes): Quote
    {
        return Quote::query()->create(array_merge([
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
            'created_by' => $owner->id,
        ], $attributes));
    }
}
