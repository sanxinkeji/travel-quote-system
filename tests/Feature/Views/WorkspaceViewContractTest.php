<?php

namespace Tests\Feature\Views;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkspaceViewContractTest extends TestCase
{
    public static function requiredViews(): array
    {
        return [
            ['layouts/app.blade.php'],
            ['auth/login.blade.php'],
            ['quotes/index.blade.php'],
            ['quotes/won.blade.php'],
            ['quotes/show.blade.php'],
            ['quotes/edit.blade.php'],
            ['quotes/preview.blade.php'],
            ['users/index.blade.php'],
            ['users/create.blade.php'],
            ['users/edit.blade.php'],
        ];
    }

    #[DataProvider('requiredViews')]
    public function test_required_workspace_view_exists(string $relativePath): void
    {
        $this->assertFileExists(resource_path('views/'.$relativePath));
    }

    public function test_layout_keeps_navigation_small_and_role_aware(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('历史报价库', $layout);
        $this->assertStringContainsString('用户管理', $layout);
        $this->assertStringContainsString('已成交报价', $layout);
        $this->assertStringContainsString("routeIs('quotes.won')", $layout);
        $this->assertStringContainsString("role === 'admin'", $layout);
        $this->assertStringNotContainsString('报价详情</', $layout);
        $this->assertStringNotContainsString('报价编辑</', $layout);
    }

    public function test_quote_editor_has_nested_fields_and_dynamic_controls(): void
    {
        $editor = file_get_contents(resource_path('views/quotes/edit.blade.php'));
        $script = file_get_contents(public_path('js/quote-editor.js'));

        $this->assertStringContainsString('groups[', $editor);
        $this->assertStringContainsString('data-add-group', $editor);
        $this->assertStringContainsString('data-add-item', $editor);
        $this->assertStringContainsString('data-tax-item', $editor);
        $this->assertStringContainsString('calculateTaxBase', $script);
        $this->assertStringContainsString('税基自动汇总', $editor.$script);
        $this->assertStringContainsString('data-remove-item', $editor.$script);
    }

    public function test_quote_editor_uses_a_table_layout_for_quote_information(): void
    {
        $editor = file_get_contents(resource_path('views/quotes/edit.blade.php'));
        $styles = file_get_contents(public_path('css/workspace.css'));

        $this->assertStringContainsString('quote-information-table', $editor);
        $this->assertStringContainsString('information-table-wrap', $editor);
        $this->assertStringNotContainsString('class="information-grid"', $editor);
        $this->assertStringContainsString('.quote-information-table tr { display: grid;', $styles);
        $this->assertStringNotContainsString('.quote-information-table { min-width: 620px; }', $styles);
    }

    public function test_quote_editor_uses_fixed_month_and_trip_type_options_without_manual_budget_or_source_fields(): void
    {
        $editor = file_get_contents(resource_path('views/quotes/edit.blade.php'));
        $index = file_get_contents(resource_path('views/quotes/index.blade.php'));

        $this->assertStringContainsString('type="number" name="year"', $editor);
        $this->assertStringContainsString('select aria-label="月份" name="month"', $editor);
        $this->assertStringContainsString('@foreach(range(1, 12) as $month)', $editor);
        $this->assertStringContainsString('select aria-label="行程类型" name="duration_days"', $editor);
        foreach (['一日游', '两天一夜', '三天两夜', '四天三夜'] as $label) {
            $this->assertStringContainsString($label, $editor);
        }
        $this->assertStringNotContainsString('name="nights"', $editor);
        $this->assertStringNotContainsString('name="budget_per_person"', $editor);
        $this->assertStringNotContainsString('name="source_name"', $editor);
        $this->assertStringNotContainsString('name="source_url"', $editor);
        $this->assertStringNotContainsString('五天四夜', $index);
    }

    public function test_quote_editor_groups_information_fields_into_the_requested_table_rows(): void
    {
        $editor = file_get_contents(resource_path('views/quotes/edit.blade.php'));

        $this->assertMatchesRegularExpression(
            '/<tr data-information-row="trip">.*name="year".*name="month".*name="destination".*name="people_count".*name="duration_days".*<\/tr>/s',
            $editor
        );
        $this->assertMatchesRegularExpression(
            '/<tr data-information-row="contact">.*name="planner_name".*name="wechat".*name="phone".*name="executor".*<\/tr>/s',
            $editor
        );
        $this->assertMatchesRegularExpression(
            '/<tr data-information-row="reminder">.*name="reminder_title".*name="reminder_text".*<\/tr>/s',
            $editor
        );
    }

    public function test_quote_information_panel_can_be_collapsed_without_removing_its_form_fields(): void
    {
        $editor = file_get_contents(resource_path('views/quotes/edit.blade.php'));
        $script = file_get_contents(public_path('js/quote-editor.js'));

        $this->assertStringContainsString('data-information-panel', $editor);
        $this->assertStringContainsString('data-toggle-information', $editor);
        $this->assertStringContainsString('aria-controls="quote-information-fields"', $editor);
        $this->assertStringContainsString('id="quote-information-fields"', $editor);
        $this->assertStringContainsString('toggleInformationPanel', $script);
        $this->assertStringContainsString("setAttribute('aria-expanded'", $script);
    }

    public function test_workspace_shows_immediate_navigation_feedback_with_reduced_motion_support(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $script = file_get_contents(public_path('js/workspace.js'));
        $styles = file_get_contents(public_path('css/workspace.css'));

        $this->assertStringContainsString('data-route-progress', $layout);
        $this->assertStringContainsString('beginRouteProgress', $script);
        $this->assertStringContainsString('is-navigating', $script);
        $this->assertStringContainsString('event.submitter', $script);
        $this->assertStringContainsString('.route-progress', $styles);
        $this->assertStringContainsString('@keyframes route-progress', $styles);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $styles);
    }

    public function test_workspace_versions_local_assets_after_deployments(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString("filemtime(public_path('css/workspace.css'))", $layout);
        $this->assertStringContainsString("filemtime(public_path('js/workspace.js'))", $layout);
        $this->assertStringContainsString('?v=', $layout);
    }

    public function test_quote_editor_versions_its_script_after_deployments(): void
    {
        $editor = file_get_contents(resource_path('views/quotes/edit.blade.php'));

        $this->assertStringContainsString("filemtime(public_path('js/quote-editor.js'))", $editor);
    }

    public function test_quote_editor_supports_restricted_drag_sorting_for_day_and_other_rows(): void
    {
        $editor = file_get_contents(resource_path('views/quotes/edit.blade.php'));
        $script = file_get_contents(public_path('js/quote-editor.js'));
        $styles = file_get_contents(public_path('css/workspace.css'));

        $this->assertStringContainsString('data-drag-handle', $editor);
        $this->assertStringContainsString("asset('vendor/sortable/Sortable.min.js')", $editor);
        $this->assertFileExists(public_path('vendor/sortable/Sortable.min.js'));
        $this->assertStringContainsString('new Sortable', $script);
        $this->assertStringContainsString("name: 'quote-day-items'", $script);
        $this->assertStringContainsString('pull: canPullDayItem', $script);
        $this->assertStringContainsString('put: false', $script);
        $this->assertStringContainsString('sourceItems.length > 1', $script);
        $this->assertStringContainsString('onEnd: finishItemDrag', $script);
        $this->assertStringContainsString('initializeItemSortables', $script);
        $this->assertMatchesRegularExpression(
            '/insertAdjacentHTML\([^;]+groupTemplate[^;]+;.*initializeItemSortables\(\)/s',
            $script
        );
        $this->assertStringContainsString('.item-drag-handle', $styles);
        $this->assertStringContainsString('.sortable-ghost', $styles);
    }

    public function test_quote_preview_exposes_image_and_spreadsheet_actions(): void
    {
        $preview = file_get_contents(resource_path('views/quotes/preview.blade.php'));
        $script = file_get_contents(public_path('js/quote-actions.js'));

        $this->assertStringContainsString('data-copy-image', $preview);
        $this->assertStringContainsString('data-download-image', $preview);
        $this->assertStringContainsString('data-export-table', $preview);
        $this->assertStringContainsString('html2canvas', $script);
        $this->assertStringContainsString('XLSX', $script);
        $this->assertStringContainsString("asset('vendor/html2canvas/html2canvas.min.js')", $preview);
        $this->assertStringContainsString("asset('vendor/xlsx-js-style/xlsx.bundle.js')", $preview);
        $this->assertStringNotContainsString("asset('vendor/xlsx/xlsx.full.min.js')", $preview);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $preview);
        $this->assertFileExists(public_path('vendor/html2canvas/html2canvas.min.js'));
        $this->assertFileExists(public_path('vendor/xlsx-js-style/xlsx.bundle.js'));
        $this->assertFileExists(public_path('vendor/xlsx-js-style/LICENSE'));
    }

    public function test_quote_document_keeps_numeric_column_headings_centered(): void
    {
        $styles = file_get_contents(public_path('css/workspace.css'));

        $this->assertMatchesRegularExpression(
            '/\.quote-table th\.numeric\s*\{[^}]*text-align:\s*center;/s',
            $styles
        );
    }

    public function test_quote_editor_keeps_time_compact_and_value_columns_usable(): void
    {
        $styles = file_get_contents(public_path('css/workspace.css'));

        $this->assertStringContainsString(
            'grid-template-columns: 24px 25px 108px minmax(145px,1.25fr) minmax(58px,.45fr) 64px 96px 108px minmax(170px,1.65fr) 28px;',
            $styles
        );
        $this->assertStringContainsString(
            'grid-template-columns: 22px 23px 100px minmax(130px,1.15fr) 55px 60px 88px 100px minmax(150px,1.45fr) 27px;',
            $styles
        );
        $this->assertMatchesRegularExpression(
            '/^\.items-editor \{ padding: 0 7px 5px; width: 100%; max-width: 100%; overflow-x: auto; overscroll-behavior-inline: contain; \}$/m',
            $styles
        );
    }

    public function test_quote_library_exposes_history_and_personal_scope_switches(): void
    {
        $index = file_get_contents(resource_path('views/quotes/index.blade.php'));
        $styles = file_get_contents(public_path('css/workspace.css'));

        $this->assertStringContainsString('quote-scope-switch', $index);
        $this->assertStringContainsString('历史报价大厅', $index);
        $this->assertStringContainsString('自用报价', $index);
        $this->assertStringContainsString('scope', $index);
        $this->assertStringContainsString('aria-current', $index);
        $this->assertStringContainsString('.quote-scope-switch', $styles);
        $this->assertStringContainsString('.quote-scope-option.active', $styles);
    }

    public function test_won_quote_view_exposes_report_month_metrics_and_shared_filters(): void
    {
        $won = file_get_contents(resource_path('views/quotes/won.blade.php'));

        foreach (['name="report_month"', 'name="creator_id"', '本月出报价', '本月成交', '成交额',
            'name="year"', 'name="month"', 'name="destination"', 'name="duration"',
            'name="people_range"', 'name="budget_min"', 'name="budget_max"', 'name="keyword"', '成交日期'] as $marker) {
            $this->assertStringContainsString($marker, $won);
        }
    }

    public function test_sales_status_partial_closes_its_editable_form(): void
    {
        $partial = file_get_contents(resource_path('views/quotes/_sales_status.blade.php'));

        $this->assertSame(1, substr_count($partial, '<form '));
        $this->assertSame(1, substr_count($partial, '</form>'));
    }

    public function test_quote_library_tables_keep_the_compact_layout_contract(): void
    {
        $index = file_get_contents(resource_path('views/quotes/index.blade.php'));
        $won = file_get_contents(resource_path('views/quotes/won.blade.php'));
        $styles = file_get_contents(public_path('css/workspace.css'));

        $title = '<span class="table-title" title="{{ $quote->title ?? $quote->customer_title ?? \'未命名报价\' }}">';
        $this->assertStringContainsString($title, $index);
        $this->assertStringContainsString($title, $won);
        $this->assertStringContainsString('class="data-table quote-library-table history-quotes-table"', $index);

        foreach ([10, 14, 6, 6, 8, 5, 6, 9, 13, 9, 14] as $column => $width) {
            $this->assertStringContainsString(
                '.history-quotes-table th:nth-child('.($column + 1).') { width: '.$width.'%; }',
                $styles
            );
        }

        foreach ([10, 14, 6, 6, 8, 5, 6, 9, 14, 8, 14] as $column => $width) {
            $this->assertStringContainsString(
                '.won-quotes-table th:nth-child('.($column + 1).') { width: '.$width.'%; }',
                $styles
            );
        }

        $this->assertStringContainsString('<th>成交额</th><th>主要项目</th><th>成交日期</th><th class="actions-cell">操作</th>', $won);

        $this->assertStringContainsString('.history-quotes-table td:nth-child(10) { overflow: visible; }', $styles);
        $this->assertStringNotContainsString('.quote-library-table td:nth-child(10) { overflow: visible; }', $styles);
        $this->assertStringContainsString('.detail-meta-strip { grid-template-columns: repeat(7, 1fr); }', $styles);
        $this->assertStringContainsString('padding: 4px 22px 4px 8px;', $styles);
        $this->assertStringContainsString('min-width: 88px;', $styles);
    }

    public function test_quote_library_tables_have_a_compact_no_page_overflow_mobile_layout(): void
    {
        $styles = file_get_contents(public_path('css/workspace.css'));

        $this->assertStringContainsString('.quote-library-table { min-width: 0; table-layout: fixed; width: 100%; }', $styles);
        $this->assertStringContainsString('.history-quotes-table th:nth-child(3), .history-quotes-table td:nth-child(3)', $styles);
        $this->assertStringContainsString('.won-quotes-table th:nth-child(3), .won-quotes-table td:nth-child(3)', $styles);
        $this->assertStringContainsString('.quote-library-table .row-actions { gap: 2px; }', $styles);
        $this->assertStringContainsString('.quote-library-table .row-actions .icon-btn { width: 24px; height: 24px; }', $styles);
    }

    public function test_quote_library_tables_reserve_status_and_actions_space_at_tablet_widths(): void
    {
        $styles = file_get_contents(public_path('css/workspace.css'));

        $this->assertStringContainsString('@media (min-width: 861px) and (max-width: 1180px)', $styles);
        $this->assertStringContainsString('.history-quotes-table th:nth-child(10) { width: 14%; }', $styles);
        $this->assertStringContainsString('.history-quotes-table th:nth-child(11) { width: 22%; }', $styles);
        $this->assertStringContainsString('.won-quotes-table th:nth-child(9) { width: 10%; }', $styles);
        $this->assertStringContainsString('.won-quotes-table th:nth-child(10) { width: 12%; }', $styles);
        $this->assertStringContainsString('.won-quotes-table th:nth-child(11) { width: 22%; }', $styles);
    }

    public function test_spreadsheet_export_contains_document_metadata_details_and_totals(): void
    {
        $document = file_get_contents(resource_path('views/quotes/_document.blade.php'));
        $script = file_get_contents(public_path('js/quote-actions.js'));

        foreach (['data-export-title', 'data-export-customer-title', 'data-export-planner',
            'data-export-wechat', 'data-export-phone', 'data-export-executor',
            'data-export-reminder-title', 'data-export-reminder-text', 'data-export-people',
            'data-export-per-person', 'data-export-total'] as $attribute) {
            $this->assertStringContainsString($attribute, $document);
        }
        $this->assertStringContainsString('data-export-table-source', $document);
        $this->assertStringContainsString('aoa_to_sheet', $script);
        $this->assertStringNotContainsString('sheet_add_dom', $script);
        $this->assertStringContainsString('buildQuoteWorkbook', $script);
        $this->assertStringContainsString('extractQuoteModel', $script);
        $this->assertStringContainsString('applyWorkbookStyles', $script);
        $this->assertStringContainsString('currencyFormat', $script);
        $this->assertStringContainsString('data-export-title', $script);
        $this->assertStringContainsString('data-export-total', $script);
    }

    public function test_user_management_filters_and_uses_the_protected_status_route(): void
    {
        $index = file_get_contents(resource_path('views/users/index.blade.php'));
        $form = file_get_contents(resource_path('views/users/_form.blade.php'));

        $this->assertStringContainsString('name="q"', $index);
        $this->assertStringContainsString('name="role"', $index);
        $this->assertStringContainsString('name="status"', $index);
        $this->assertStringContainsString("route('users.status'", $index);
        $this->assertStringNotContainsString('name="is_active"', $form);
    }
}
