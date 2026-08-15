<?php

namespace Tests\Feature\Views;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteEditorNumberFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_quantities_are_rendered_without_trailing_zeroes(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $quote = Quote::query()->create([
            'created_by' => $admin->id,
            'title' => 'Quantity format quote',
            'customer_title' => 'Quantity format quote',
            'destination' => 'Huizhou',
            'year' => 2026,
            'month' => 8,
            'duration_days' => 1,
            'nights' => 0,
            'people_count' => 20,
            'total_amount' => 0,
            'per_person_amount' => 0,
            'status' => 'historical',
        ]);
        $group = $quote->groups()->create([
            'name' => 'DAY 01',
            'type' => 'day',
            'sort_order' => 0,
            'subtotal' => 0,
        ]);
        $group->items()->createMany([
            ['name' => 'Whole quantity', 'quantity' => 20, 'unit_price' => 0, 'sort_order' => 0],
            ['name' => 'Fractional quantity', 'quantity' => 1.5, 'unit_price' => 0, 'sort_order' => 1],
        ]);

        $response = $this->actingAs($admin)->get(route('quotes.edit', $quote));

        $response->assertOk();
        $response->assertSee('name="groups[0][items][0][quantity]" value="20"', false);
        $response->assertSee('name="groups[0][items][1][quantity]" value="1.5"', false);
        $response->assertDontSee('value="20.00" aria-label="数量"', false);
        $response->assertDontSee('value="1.50" aria-label="数量"', false);
    }
}
