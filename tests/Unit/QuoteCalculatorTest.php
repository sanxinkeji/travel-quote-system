<?php

namespace Tests\Unit;

use App\Services\QuoteCalculator;
use PHPUnit\Framework\TestCase;

class QuoteCalculatorTest extends TestCase
{
    public function test_it_calculates_normal_items_from_quantity_and_unit_price(): void
    {
        $result = (new QuoteCalculator)->calculate([
            ['name' => 'DAY 01', 'type' => 'day', 'items' => [
                ['name' => 'Sailing', 'quantity' => 2, 'unit_price' => 1400],
                ['name' => 'Hotel', 'quantity' => 1, 'unit_price' => 7300],
            ]],
        ], 20);

        $this->assertSame(10100.0, $result['total']);
        $this->assertSame(505.0, $result['per_person']);
        $this->assertSame(10100.0, $result['groups'][0]['subtotal']);
        $this->assertSame(2800.0, $result['groups'][0]['items'][0]['line_total']);
    }

    public function test_an_explicit_normal_item_total_takes_priority(): void
    {
        $result = (new QuoteCalculator)->calculate([
            ['name' => 'DAY 01', 'type' => 'day', 'items' => [
                ['name' => 'Coach', 'quantity' => 1, 'unit_price' => 1000, 'line_total' => 880],
            ]],
        ], 10);

        $this->assertSame(880.0, $result['total']);
    }

    public function test_tax_base_excludes_tax_rows_and_tax_uses_rate_quantity_and_base(): void
    {
        $result = (new QuoteCalculator)->calculate([
            ['name' => 'DAY 01', 'type' => 'day', 'items' => [
                ['name' => 'Activities', 'quantity' => 1, 'unit_price' => 22970],
            ]],
            ['name' => 'Other', 'type' => 'other', 'items' => [
                ['name' => 'Insurance', 'quantity' => 30, 'unit_price' => 10],
                ['name' => 'Service fee', 'quantity' => 30, 'unit_price' => 10],
                ['name' => 'VAT invoice', 'unit' => '6%', 'quantity' => 1, 'unit_price' => 999, 'line_total' => 999, 'is_tax' => true],
            ]],
        ], 30);

        $this->assertSame(23570.0, $result['tax_base']);
        $this->assertSame(1414.2, $result['groups'][1]['items'][2]['line_total']);
        $this->assertSame(23570.0, $result['groups'][1]['items'][2]['unit_price']);
        $this->assertSame(2014.2, $result['groups'][1]['subtotal']);
        $this->assertSame(24984.2, $result['total']);
        $this->assertSame(832.81, $result['per_person']);
    }

    public function test_it_detects_a_tax_row_and_rate_from_its_name_or_unit(): void
    {
        $result = (new QuoteCalculator)->calculate([
            ['name' => 'Other', 'type' => 'other', 'items' => [
                ['name' => 'Admission', 'quantity' => 2, 'unit_price' => 100],
                ['name' => '3% VAT invoice', 'unit' => '/', 'quantity' => 1],
            ]],
        ], 2);

        $tax = $result['groups'][0]['items'][1];

        $this->assertTrue($tax['is_tax']);
        $this->assertSame(0.03, $tax['tax_rate']);
        $this->assertSame(6.0, $tax['line_total']);
    }

    public function test_it_detects_a_chinese_invoice_name(): void
    {
        $invoice = "6%\u{589E}\u{503C}\u{7A0E}\u{666E}\u{901A}\u{53D1}\u{7968}";
        $result = (new QuoteCalculator)->calculate([
            ['name' => 'Other', 'items' => [
                ['name' => 'Admission', 'quantity' => 1, 'unit_price' => 100],
                ['name' => $invoice, 'quantity' => 1],
            ]],
        ], 1);

        $this->assertTrue($result['groups'][0]['items'][1]['is_tax']);
        $this->assertSame(6.0, $result['groups'][0]['items'][1]['line_total']);
    }
}
