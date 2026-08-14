<?php

namespace Tests\Feature\Views;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteOldInputViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_editor_rebuilds_dynamic_groups_and_items_from_old_input(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $oldGroups = [
            [
                'name' => 'DAY 09',
                'type' => 'day',
                'sort_order' => 4,
                'items' => [[
                    'time' => '09:30-11:45',
                    'name' => 'Validation preserved activity',
                    'unit' => 'boat',
                    'quantity' => '3.5',
                    'unit_price' => '1288.88',
                    'actual_total' => '4500.55',
                    'note' => 'Keep every submitted field',
                    'is_tax' => '0',
                    'tax_rate' => '0',
                    'sort_order' => 7,
                ]],
            ],
            [
                'name' => 'Other fees after delete',
                'type' => 'other',
                'sort_order' => 8,
                'items' => [[
                    'time' => '/',
                    'name' => 'Insurance retained',
                    'unit' => 'person',
                    'quantity' => '20',
                    'unit_price' => '10',
                    'actual_total' => '200',
                    'note' => 'Retained ordinary item',
                    'is_tax' => '0',
                    'tax_rate' => '0',
                    'sort_order' => 0,
                ], [
                    'time' => '/',
                    'name' => '6% VAT invoice retained',
                    'unit' => '6%',
                    'quantity' => '1',
                    'unit_price' => '4700.55',
                    'actual_total' => '282.03',
                    'note' => 'Automatic tax retained',
                    'is_tax' => '1',
                    'tax_rate' => '0.06',
                    'sort_order' => 1,
                ]],
            ],
        ];

        $invalidSubmission = [
            'title' => 'Invalid submission title retained',
            'customer_title' => 'Customer title retained',
            'destination' => '',
            'year' => 2026,
            'month' => 8,
            'duration_days' => 2,
            'nights' => 1,
            'people_count' => 20,
            'budget_per_person' => 900,
            'status' => 'historical',
            'groups' => $oldGroups,
        ];

        $this->actingAs($user)
            ->from(route('quotes.create'))
            ->post(route('quotes.store'), $invalidSubmission)
            ->assertRedirect(route('quotes.create'))
            ->assertSessionHasErrors('destination');

        $response = $this->get(route('quotes.create'));

        $response->assertOk();
        $response->assertSee('Invalid submission title retained');
        $response->assertSee('DAY 09');
        $response->assertSee('Other fees after delete');
        $response->assertSee('Validation preserved activity');
        $response->assertSee('6% VAT invoice retained');
        $response->assertSee('value="09:30-11:45"', false);
        $response->assertSee('value="boat"', false);
        $response->assertSee('value="3.5"', false);
        $response->assertSee('value="1288.88"', false);
        $response->assertSee('value="4500.55"', false);
        $response->assertSee('value="Keep every submitted field"', false);
        $response->assertSee('name="groups[0][sort_order]" value="4"', false);
        $response->assertSee('name="groups[0][items][0][sort_order]" value="7"', false);
        $response->assertSee('name="groups[1][items][1][is_tax]" value="1"', false);
        $response->assertSee('groups[1][items][1][tax_rate]', false);
        $response->assertSee('value="0.06"', false);
        $response->assertSee('data-tax-item="true"', false);
        $this->assertSame(2, substr_count($response->getContent(), ' data-group '));
        $this->assertSame(3, substr_count($response->getContent(), ' data-item '));
    }
}
