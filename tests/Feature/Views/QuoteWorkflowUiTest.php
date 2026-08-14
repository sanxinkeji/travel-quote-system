<?php

namespace Tests\Feature\Views;

use Tests\TestCase;

class QuoteWorkflowUiTest extends TestCase
{
    public function test_history_uses_one_detail_entry_and_get_links_for_copy_workflows(): void
    {
        $index = file_get_contents(resource_path('views/quotes/index.blade.php'));

        $this->assertStringContainsString('<span class="table-title">', $index);
        $this->assertStringNotContainsString('<a class="table-title"', $index);
        $this->assertStringContainsString("route('quotes.preview', \$quote)", $index);
        $this->assertStringContainsString("route('quotes.copy.edit', \$quote)", $index);
        $this->assertStringNotContainsString("route('quotes.copy', \$quote)", $index);
    }

    public function test_detail_actions_distinguish_editing_the_source_from_reusing_it(): void
    {
        $show = file_get_contents(resource_path('views/quotes/show.blade.php'));

        $this->assertStringContainsString('编辑原报价', $show);
        $this->assertStringContainsString("route('quotes.preview', \$quote)", $show);
        $this->assertStringContainsString("route('quotes.copy.edit', \$quote)", $show);
        $this->assertStringNotContainsString("route('quotes.copy', \$quote)", $show);
    }

    public function test_copy_editor_clearly_explains_that_saving_creates_a_new_quote(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/QuoteController.php'));
        $editor = file_get_contents(resource_path('views/quotes/edit.blade.php'));

        $this->assertStringContainsString("'isCopy' => true", $controller);
        $this->assertStringContainsString('$isCopy = $isCopy ?? false', $editor);
        $this->assertStringContainsString("\$isCopy ? '复制并微调'", $editor);
        $this->assertStringContainsString('保存后会生成一条属于当前账号的新报价', $editor);
    }

    public function test_preview_back_link_respects_quote_ownership(): void
    {
        $preview = file_get_contents(resource_path('views/quotes/preview.blade.php'));

        $this->assertStringContainsString("@can('update', \$quote)", $preview);
        $this->assertStringContainsString("route('quotes.edit', \$quote)", $preview);
        $this->assertStringContainsString("route('quotes.show', \$quote)", $preview);
    }

    public function test_quote_tables_and_editor_use_the_larger_readable_type_scale(): void
    {
        $styles = file_get_contents(public_path('css/workspace.css'));

        foreach ([
            '.data-table th' => 'font-size: 11.5px',
            '.document-contact' => 'font-size: 12px',
            '.document-notice' => 'font-size: 12px',
            '.quote-table' => 'font-size: 12px',
            '.quote-information-table th' => 'font-size: 12px',
            '.item-grid input' => 'font-size: 12px',
            '.item-number' => 'font-size: 12px',
            '.item-header' => 'font-size: 11px',
            '.summary-line' => 'font-size: 12px',
            '.calculation-note' => 'font-size: 11px',
        ] as $selector => $declaration) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($selector, '/').'\s*\{[^}]*'.preg_quote($declaration, '/').'/s',
                $styles,
                "Expected {$selector} to include {$declaration}."
            );
        }
    }

    public function test_document_and_export_use_persisted_group_type_instead_of_editable_name(): void
    {
        $document = file_get_contents(resource_path('views/quotes/_document.blade.php'));
        $export = file_get_contents(public_path('js/quote-actions.js'));

        $this->assertStringContainsString('data-export-group-type', $document);
        $this->assertStringContainsString("\$groupType = \$group->type ?? 'day'", $document);
        $this->assertStringContainsString("\$groupType === 'other'", $document);
        $this->assertStringContainsString('dataset.exportGroupType', $export);
    }

    public function test_editor_tax_detection_matches_the_server_invoice_contract(): void
    {
        $editor = file_get_contents(public_path('js/quote-editor.js'));

        $this->assertStringContainsString('/(invoice|发票)/i', $editor);
        $this->assertStringContainsString("event.target.matches('[data-item-name]')", $editor);
        $this->assertStringContainsString('data-is-tax', $editor);
    }
}
