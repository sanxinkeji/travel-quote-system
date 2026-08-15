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

    public function test_quote_editor_gives_notes_more_space_than_compact_value_columns(): void
    {
        $styles = file_get_contents(public_path('css/workspace.css'));

        $this->assertStringContainsString(
            'grid-template-columns: 24px 25px minmax(82px,.55fr) minmax(145px,1.25fr) minmax(58px,.45fr) 48px 72px 82px minmax(170px,1.65fr) 28px;',
            $styles
        );
        $this->assertStringContainsString(
            'grid-template-columns: 22px 23px minmax(80px,.5fr) minmax(130px,1.15fr) 55px 48px 68px 78px minmax(150px,1.45fr) 27px;',
            $styles
        );
        $this->assertMatchesRegularExpression(
            '/^\.items-editor \{ padding: 0 7px 5px; width: 100%; max-width: 100%; overflow-x: auto; overscroll-behavior-inline: contain; \}$/m',
            $styles
        );
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
