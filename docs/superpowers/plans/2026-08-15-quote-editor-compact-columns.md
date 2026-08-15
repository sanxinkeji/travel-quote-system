# Quote Editor Compact Columns Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the quote editor's time and numeric columns compact and give the released horizontal space to the note column without changing editor behavior.

**Architecture:** Keep the existing ten-column CSS Grid and shared Blade markup. Add one view-contract regression test for both desktop and medium-width grid definitions, then update only those two CSS declarations; all quote creation, editing, and copy flows inherit the result from the shared editor.

**Tech Stack:** Laravel Blade, PHPUnit, CSS Grid, Browser plugin DOM inspection.

---

### Task 1: Compact the quote item grid

**Files:**
- Modify: `tests/Feature/Views/WorkspaceViewContractTest.php`
- Modify: `public/css/workspace.css`

- [ ] **Step 1: Write the failing view-contract test**

Add this test to `WorkspaceViewContractTest` before the preview tests:

```php
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
}
```

- [ ] **Step 2: Run the focused test and verify RED**

Run:

```bash
wsl.exe sh -lc 'cd "/mnt/d/旅游内部系统/travel-quote-system" && php artisan test --filter=test_quote_editor_gives_notes_more_space_than_compact_value_columns'
```

Expected: one failing test because `workspace.css` still contains the old wider time and numeric columns.

- [ ] **Step 3: Implement the desktop and medium-width grids**

Replace the main `.item-grid` declaration in `public/css/workspace.css` with:

```css
.item-grid { display: grid; grid-template-columns: 24px 25px minmax(82px,.55fr) minmax(145px,1.25fr) minmax(58px,.45fr) 48px 72px 82px minmax(170px,1.65fr) 28px; gap: 5px; align-items: center; padding: 7px 4px; border-bottom: 1px solid #edf0f4; }
```

Replace the `.item-grid` declaration inside `@media (max-width: 1180px)` with:

```css
.item-grid { grid-template-columns: 22px 23px minmax(80px,.5fr) minmax(130px,1.15fr) 55px 48px 68px 78px minmax(150px,1.45fr) 27px; gap: 4px; }
```

- [ ] **Step 4: Run the focused test and verify GREEN**

Run the command from Step 2 again.

Expected: one passing test.

- [ ] **Step 5: Run the full automated suite**

Run:

```bash
wsl.exe sh -lc 'cd "/mnt/d/旅游内部系统/travel-quote-system" && php artisan test'
npm.cmd test
```

Expected: all PHP and JavaScript tests pass with zero failures.

- [ ] **Step 6: Verify the rendered editor**

Open `http://127.0.0.1:5138/quotes/1/edit` in the Browser plugin and verify:

- Page title and editor content render normally.
- Computed desktop widths for time, quantity, unit price, and actual total are smaller than the note input width.
- The note input receives at least 170px on the wide desktop layout.
- Header cells and inputs remain aligned because they share `.item-grid`.
- Adding an item still creates a complete editable row.
- No relevant console errors or warnings are present.
- Do not capture screenshots because the user asked to provide repository screenshots later.

- [ ] **Step 7: Commit and publish**

Run:

```bash
git add tests/Feature/Views/WorkspaceViewContractTest.php public/css/workspace.css docs/superpowers/plans/2026-08-15-quote-editor-compact-columns.md
git commit -m "style: compact quote editor value columns"
git push origin main
```

Expected: the local and remote `main` revisions match.

- [ ] **Step 8: Deploy the CSS change**

Use the authenticated BaoTa file manager to replace `/www/wwwroot/baojia.dclvyou.com/public/css/workspace.css`. The layout already appends `filemtime()` to this asset URL, so the new stylesheet bypasses browser caches without changing Blade templates. Confirm the uploaded file timestamp changes, load the production login page, and log out of BaoTa when finished.
