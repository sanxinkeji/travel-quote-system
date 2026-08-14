<?php

namespace Tests\Feature\Views;

use Tests\TestCase;

class QuoteSpreadsheetExportViewTest extends TestCase
{
    public function test_quote_preview_loads_the_local_styled_xlsx_build_with_its_license(): void
    {
        $preview = file_get_contents(resource_path('views/quotes/preview.blade.php'));

        $this->assertStringContainsString("asset('vendor/xlsx-js-style/xlsx.bundle.js')", $preview);
        $this->assertStringNotContainsString("asset('vendor/xlsx/xlsx.full.min.js')", $preview);
        $this->assertFileExists(public_path('vendor/xlsx-js-style/xlsx.bundle.js'));
        $this->assertFileExists(public_path('vendor/xlsx-js-style/LICENSE'));
        $this->assertStringContainsString("filemtime(public_path('vendor/xlsx-js-style/xlsx.bundle.js'))", $preview);
        $this->assertStringContainsString("filemtime(public_path('js/quote-actions.js'))", $preview);
    }
}
