<?php

namespace Tests\Feature\Seeders;

use App\Models\Quote;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\HistoricalQuoteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalQuoteSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_the_existing_historical_quote_library(): void
    {
        $this->createAdmin();

        $this->seed(HistoricalQuoteSeeder::class);

        $this->assertDatabaseCount('quotes', 3);
        $this->assertDatabaseHas('quotes', ['destination' => '清远', 'status' => 'historical']);
        $this->assertDatabaseHas('quotes', ['destination' => '韶关', 'status' => 'historical']);
        $this->assertDatabaseHas('quotes', ['destination' => '惠州', 'status' => 'historical']);
    }

    public function test_it_can_be_run_repeatedly_without_duplicate_quotes(): void
    {
        $this->createAdmin();

        $this->seed(HistoricalQuoteSeeder::class);
        $this->seed(HistoricalQuoteSeeder::class);

        $this->assertDatabaseCount('quotes', 3);
    }

    public function test_huizhou_invoice_uses_all_non_tax_items_as_its_tax_base(): void
    {
        $this->createAdmin();

        $this->seed(HistoricalQuoteSeeder::class);

        $quote = Quote::query()->where('destination', '惠州')->with('groups.items')->sole();
        $this->assertSame(17060.92, (float) $quote->total_amount);
        $this->assertSame(853.05, (float) $quote->per_person_amount);
        $this->assertSame([12100.0, 4264.0, 696.92], $quote->groups->pluck('subtotal')->map(fn ($value) => (float) $value)->all());

        $invoice = $quote->groups->last()->items->firstWhere('is_tax', true);
        $this->assertNotNull($invoice);
        $this->assertSame(16564.0, (float) $invoice->unit_price);
        $this->assertSame(496.92, (float) $invoice->actual_total);
    }

    public function test_database_seeder_assigns_quotes_to_the_configured_admin(): void
    {
        User::factory()->create(['username' => 'older-admin', 'role' => 'admin']);
        $this->setAdminEnvironment('configured-admin', '指定管理员', 'a-secure-password-123');

        $this->seed(DatabaseSeeder::class);

        $configuredAdmin = User::query()->where('username', 'configured-admin')->sole();
        $this->assertSame([$configuredAdmin->id], Quote::query()->distinct()->pluck('created_by')->all());
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'username' => 'admin',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function setAdminEnvironment(string $username, string $name, string $password): void
    {
        foreach (compact('username', 'name', 'password') as $key => $value) {
            $environmentKey = 'ADMIN_'.strtoupper($key);
            putenv("{$environmentKey}={$value}");
            $_ENV[$environmentKey] = $value;
            $_SERVER[$environmentKey] = $value;
        }
    }

    protected function tearDown(): void
    {
        foreach (['ADMIN_USERNAME', 'ADMIN_NAME', 'ADMIN_PASSWORD'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        parent::tearDown();
    }
}
