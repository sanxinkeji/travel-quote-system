<?php

namespace Tests\Feature\Views;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateQuoteDefaultsViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_quote_starts_with_a_day_group_and_standard_other_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('quotes.create'));

        $response->assertOk();
        $response->assertSee('value="DAY 01"', false);
        $response->assertSee('value="其他项"', false);
        $response->assertSee('name="groups[1][type]" value="other"', false);
        $response->assertSee('全陪导游');
        $response->assertSee('增值税普通发票');
        $response->assertSee('旅游出行团体意外险');
        $response->assertSee('旅行社策划/操作服务费');
        $response->assertSee('定制横幅1条');
        $response->assertSee('name="groups[1][items][1][is_tax]" value="1"', false);
        $response->assertSee('name="groups[1][items][1][tax_rate]" value="0.03"', false);
    }
}
