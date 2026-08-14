<?php

namespace Tests\Unit;

use App\Models\Quote;
use App\Models\QuoteGroup;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class QuoteHighlightsTest extends TestCase
{
    public function test_highlights_exclude_chinese_meals_and_tax_items(): void
    {
        $quote = new Quote;
        $group = new QuoteGroup;
        $group->setRelation('items', new Collection([
            $this->item("\u{56E2}\u{961F}\u{5348}\u{9910}"),
            $this->item("\u{53CC}\u{4F53}\u{5E06}\u{8239}\u{51FA}\u{6D77}"),
            $this->item("\u{6D77}\u{8FB9}\u{522B}\u{5885}\u{4F4F}\u{5BBF}"),
            $this->item("6%\u{589E}\u{503C}\u{7A0E}\u{53D1}\u{7968}", true),
        ]));
        $quote->setRelation('groups', new Collection([$group]));

        $this->assertSame(
            "\u{53CC}\u{4F53}\u{5E06}\u{8239}\u{51FA}\u{6D77} + \u{6D77}\u{8FB9}\u{522B}\u{5885}\u{4F4F}\u{5BBF}",
            $quote->highlights
        );
    }

    public function test_highlights_prioritize_activities_and_accommodation_over_transport(): void
    {
        $quote = new Quote;
        $group = new QuoteGroup;
        $group->setRelation('items', new Collection([
            $this->item('集合出发-惠州海边'),
            $this->item('网红双体帆船出海轰趴2小时'),
            $this->item('前往入住途径赶海公园打卡'),
            $this->item('入住巽寮湾凤池岛威尼斯·9房20床'),
            $this->item('返程'),
        ]));
        $quote->setRelation('groups', new Collection([$group]));

        $this->assertSame(
            '网红双体帆船出海轰趴2小时 + 前往入住途径赶海公园打卡 + 入住巽寮湾凤池岛威尼斯·9房20床',
            $quote->highlights
        );
    }

    private function item(string $name, bool $isTax = false): QuoteItem
    {
        $item = new QuoteItem;
        $item->name = $name;
        $item->is_tax = $isTax;

        return $item;
    }
}
