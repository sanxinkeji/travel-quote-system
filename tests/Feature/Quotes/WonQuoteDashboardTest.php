<?php

namespace Tests\Feature\Quotes;

use App\Models\Quote;
use App\Models\User;
use App\Services\QuoteFilter;
use App\Services\QuoteSalesDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WonQuoteDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_uses_creation_month_for_issued_quotes_and_won_month_for_revenue(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 18, 12, 0, 0, config('app.timezone')));
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->quote($owner, '八月成交报价', '惠州', 'historical', '2026-08-02', '2026-08-05', 18000);
        $this->quote($other, '其他员工成交报价', '清远', 'historical', '2026-08-04', '2026-08-10', 24000);
        $this->quote($owner, '八月跟进报价', '惠州', 'historical', '2026-08-12', null, 9000);
        $this->quote($owner, '草稿成交报价', '惠州', 'draft', '2026-08-06', '2026-08-11', 7000);
        $this->quote($owner, '七月成交报价', '惠州', 'historical', '2026-07-02', '2026-07-05', 10000);

        $period = app(QuoteSalesDashboard::class)->period('2026-08');
        $summary = app(QuoteSalesDashboard::class)->summary($period);

        $this->assertSame([
            'issued_count' => 3,
            'won_count' => 2,
            'won_amount' => 42000.0,
        ], $summary);

        $ownerSummary = app(QuoteSalesDashboard::class)->summary($period, $owner->id);

        $this->assertSame([
            'issued_count' => 2,
            'won_count' => 1,
            'won_amount' => 18000.0,
        ], $ownerSummary);
        Carbon::setTestNow();
    }

    public function test_won_filter_applies_creator_and_trip_filters_within_report_month(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->quote($owner, '八月成交报价', '惠州', 'historical', '2026-08-02', '2026-08-05', 18000);
        $this->quote($other, '其他员工成交报价', '惠州', 'historical', '2026-08-04', '2026-08-10', 24000);
        $this->quote($owner, '清远成交报价', '清远', 'historical', '2026-08-04', '2026-08-10', 19000);
        $this->quote($owner, '七月成交报价', '惠州', 'historical', '2026-07-02', '2026-07-05', 10000);

        $period = app(QuoteSalesDashboard::class)->period('2026-08');
        $quotes = app(QuoteFilter::class)->won([
            'creator_id' => $owner->id,
            'destination' => '惠州',
        ], $period['start'], $period['end'])->get();

        $this->assertCount(1, $quotes);
        $this->assertSame('八月成交报价', $quotes->first()->title);
    }

    public function test_invalid_report_month_falls_back_to_the_current_month(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 18, 12, 0, 0, config('app.timezone')));

        $period = app(QuoteSalesDashboard::class)->period('2026-13');

        $this->assertSame('2026-08', $period['value']);
        $this->assertSame('2026-08-01', $period['start']->format('Y-m-d'));
        $this->assertSame('2026-08-31', $period['end']->format('Y-m-d'));
        Carbon::setTestNow();
    }

    public function test_authenticated_user_can_view_the_won_page_with_metrics_and_creator_filter(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 18, 12, 0, 0, config('app.timezone')));
        $owner = User::factory()->create(['name' => '陈小丽', 'is_active' => true]);
        $this->quote($owner, '已成交惠州团建', '惠州', 'historical', '2026-08-02', '2026-08-05', 18000);

        $response = $this->actingAs($owner)->get(route('quotes.won', [
            'report_month' => '2026-08',
            'creator_id' => $owner->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('summary', [
            'issued_count' => 1,
            'won_count' => 1,
            'won_amount' => 18000.0,
        ]);
        $response->assertSeeText('已成交惠州团建');
        $response->assertSeeText('陈小丽');
        $response->assertSeeText('本月成交');
        Carbon::setTestNow();
    }

    public function test_employee_won_page_is_scoped_to_their_own_quotes_and_creator_option(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 18, 12, 0, 0, config('app.timezone')));
        $owner = User::factory()->create(['name' => '陈小丽', 'is_active' => true]);
        $other = User::factory()->create(['name' => '李明', 'is_active' => true]);
        $this->quote($owner, '员工自己的成交报价', '惠州', 'historical', '2026-08-02', '2026-08-05', 18000);
        $this->quote($other, '其他员工的成交报价', '清远', 'historical', '2026-08-04', '2026-08-10', 24000);

        $response = $this->actingAs($owner)->get(route('quotes.won', [
            'report_month' => '2026-08',
        ]));

        $response->assertOk();
        $response->assertViewHas('quotes', function ($quotes): bool {
            return $quotes->getCollection()->pluck('title')->all() === ['员工自己的成交报价'];
        });
        $response->assertViewHas('summary', [
            'issued_count' => 1,
            'won_count' => 1,
            'won_amount' => 18000.0,
        ]);
        $response->assertViewHas('creators', function ($creators) use ($owner): bool {
            return $creators->pluck('id')->all() === [$owner->id];
        });
        $response->assertSeeText('员工自己的成交报价');
        $response->assertDontSeeText('其他员工的成交报价');
        Carbon::setTestNow();
    }

    private function quote(
        User $owner,
        string $title,
        string $destination,
        string $status,
        string $createdAt,
        ?string $wonAt,
        float $totalAmount
    ): Quote {
        $quote = Quote::query()->create([
            'created_by' => $owner->id,
            'title' => $title,
            'destination' => $destination,
            'year' => 2026,
            'month' => 8,
            'duration_days' => 2,
            'nights' => 1,
            'people_count' => 20,
            'total_amount' => $totalAmount,
            'per_person_amount' => $totalAmount / 20,
            'status' => $status,
            'sales_status' => $wonAt ? Quote::SALES_WON : Quote::SALES_FOLLOWING,
            'won_at' => $wonAt ? Carbon::parse($wonAt, config('app.timezone')) : null,
        ]);
        $quote->forceFill(['created_at' => Carbon::parse($createdAt, config('app.timezone'))])->saveQuietly();

        return $quote;
    }
}
