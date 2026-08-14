<?php

namespace Tests\Feature\Quotes;

use App\Models\AuditLog;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_employee_can_save_a_quote_with_groups_items_and_server_calculated_tax(): void
    {
        $employee = $this->employee();

        $response = $this->actingAs($employee)->post(route('quotes.store'), $this->payload());

        $quote = Quote::query()->with('groups.items')->sole();
        $response->assertRedirect(route('quotes.show', $quote));
        $this->assertTrue($quote->createdBy->is($employee));
        $this->assertSame(24984.2, (float) $quote->total_amount);
        $this->assertSame(832.81, (float) $quote->per_person_amount);
        $this->assertCount(2, $quote->groups);
        $this->assertCount(3, $quote->groups->last()->items);

        $tax = $quote->groups->last()->items->last();
        $this->assertTrue($tax->is_tax);
        $this->assertSame(23570.0, (float) $tax->unit_price);
        $this->assertSame(1414.2, (float) $tax->actual_total);
    }

    public function test_a_new_quote_defaults_to_historical_when_status_is_omitted(): void
    {
        $employee = $this->employee();
        $payload = $this->payload();
        unset($payload['status']);

        $this->actingAs($employee)->post(route('quotes.store'), $payload)->assertRedirect();

        $this->assertDatabaseHas('quotes', ['created_by' => $employee->id, 'status' => 'historical']);
    }

    public function test_nights_and_reference_budget_are_derived_from_the_selected_trip_type_and_quote_total(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)->post(route('quotes.store'), $this->payload([
            'duration_days' => 4,
            'nights' => 0,
            'budget_per_person' => 1,
        ]))->assertRedirect();

        $quote = Quote::query()->sole();
        $this->assertSame(4, $quote->duration_days);
        $this->assertSame(3, $quote->nights);
        $this->assertSame(832.81, (float) $quote->per_person_amount);
        $this->assertSame(832.81, (float) $quote->budget_per_person);
    }

    public function test_quote_rejects_trip_types_outside_the_supported_options(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->from(route('quotes.create'))
            ->post(route('quotes.store'), $this->payload(['duration_days' => 5]))
            ->assertRedirect(route('quotes.create'))
            ->assertSessionHasErrors('duration_days');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_an_employee_can_replace_all_fields_groups_and_items_on_their_quote(): void
    {
        $employee = $this->employee();
        $quote = $this->createQuote($employee);
        $payload = $this->payload([
            'title' => 'Updated quote',
            'people_count' => 20,
            'groups' => [[
                'name' => 'DAY 01 updated',
                'type' => 'day',
                'items' => [[
                    'time' => '10:00',
                    'name' => 'New activity',
                    'unit' => 'person',
                    'quantity' => 20,
                    'unit_price' => 100,
                    'note' => 'new note',
                ]],
            ]],
        ]);

        $response = $this->actingAs($employee)->put(route('quotes.update', $quote), $payload);

        $response->assertRedirect(route('quotes.show', $quote));
        $quote->refresh()->load('groups.items');
        $this->assertSame('Updated quote', $quote->title);
        $this->assertSame(2000.0, (float) $quote->total_amount);
        $this->assertCount(1, $quote->groups);
        $this->assertCount(1, $quote->groups->first()->items);
        $this->assertSame('New activity', $quote->groups->first()->items->first()->name);
    }

    public function test_update_keeps_existing_status_when_status_is_omitted(): void
    {
        $employee = $this->employee();
        $quote = $this->createQuote($employee, ['status' => 'draft']);
        $payload = $this->payload(['title' => 'Updated draft']);
        unset($payload['status']);

        $this->actingAs($employee)->put(route('quotes.update', $quote), $payload)->assertRedirect();

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'title' => 'Updated draft', 'status' => 'draft']);
    }

    public function test_save_and_preview_redirects_to_customer_preview(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)
            ->post(route('quotes.store'), [...$this->payload(), 'after_save' => 'preview'])
            ->assertRedirect(route('quotes.preview', Quote::query()->sole()));
    }

    public function test_employee_cannot_update_or_delete_another_employees_quote(): void
    {
        $owner = $this->employee();
        $other = $this->employee();
        $quote = $this->createQuote($owner);

        $this->actingAs($other)
            ->put(route('quotes.update', $quote), $this->payload(['title' => 'Forbidden']))
            ->assertForbidden();

        $this->actingAs($other)
            ->delete(route('quotes.destroy', $quote))
            ->assertForbidden();

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'title' => 'Historic quote']);
    }

    public function test_employee_cannot_view_preview_or_copy_another_employees_draft(): void
    {
        $owner = $this->employee();
        $other = $this->employee();
        $draft = $this->createQuote($owner, ['status' => 'draft']);

        $this->actingAs($other)->get(route('quotes.show', $draft))->assertForbidden();
        $this->actingAs($other)->get(route('quotes.preview', $draft))->assertForbidden();
        $this->actingAs($other)->get(route('quotes.copy.edit', $draft))->assertForbidden();

        $this->actingAs($owner)->get(route('quotes.show', $draft))->assertOk();
        $this->actingAs($this->admin())->get(route('quotes.show', $draft))->assertOk();
    }

    public function test_quote_rejects_invalid_other_group_and_tax_item_placement(): void
    {
        $employee = $this->employee();
        $valid = $this->payload()['groups'];
        $invalidGroups = [
            [$valid[1], $valid[0]],
            [$valid[0], $valid[1], $valid[1]],
            [[
                'name' => 'DAY 01',
                'type' => 'day',
                'items' => [[
                    'name' => 'VAT invoice',
                    'unit' => '6%',
                    'quantity' => 1,
                    'is_tax' => true,
                ]],
            ]],
        ];

        foreach ($invalidGroups as $groups) {
            $this->actingAs($employee)
                ->post(route('quotes.store'), $this->payload(['groups' => $groups]))
                ->assertSessionHasErrors('groups');
        }

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_admin_can_update_and_delete_any_quote(): void
    {
        $owner = $this->employee();
        $admin = $this->admin();
        $quote = $this->createQuote($owner);

        $this->actingAs($admin)
            ->put(route('quotes.update', $quote), $this->payload(['title' => 'Admin updated']))
            ->assertRedirect(route('quotes.show', $quote));

        $this->actingAs($admin)
            ->delete(route('quotes.destroy', $quote))
            ->assertRedirect(route('quotes.index'));

        $this->assertDatabaseMissing('quotes', ['id' => $quote->id]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'quote.deleted',
            'subject_type' => Quote::class,
            'subject_id' => $quote->id,
        ]);
        $this->assertSame('Admin updated', AuditLog::query()->where('action', 'quote.deleted')->sole()->changes['title']);
    }

    public function test_any_active_employee_can_view_a_quote_and_open_copy_editor_without_creating_a_record(): void
    {
        $owner = $this->employee();
        $copier = $this->employee();
        $source = $this->createQuote($owner);

        $this->actingAs($copier)
            ->get(route('quotes.show', $source))
            ->assertOk();

        $response = $this->actingAs($copier)->get(route('quotes.copy.edit', $source));

        $response->assertOk();
        $response->assertViewIs('quotes.edit');
        $response->assertViewHas('formAction', route('quotes.copy.store', $source));
        $this->assertDatabaseCount('quotes', 1);
    }

    public function test_existing_copy_and_edit_button_redirects_to_copy_editor_without_creating_a_record(): void
    {
        $owner = $this->employee();
        $copier = $this->employee();
        $source = $this->createQuote($owner);

        $this->actingAs($copier)
            ->post(route('quotes.copy', $source), ['mode' => 'edit'])
            ->assertRedirect(route('quotes.copy.edit', $source));

        $this->assertDatabaseCount('quotes', 1);
    }

    public function test_saving_copy_creates_historical_quote_owned_by_current_employee_without_changing_source(): void
    {
        $owner = $this->employee();
        $copier = $this->employee();
        $source = $this->createQuote($owner)->load('groups.items');
        $sourceTitle = $source->title;
        $sourceItems = $source->groups->flatMap->items->pluck('name')->all();

        $payload = $this->payload([
            'title' => 'Copied and adjusted',
            'created_by' => $owner->id,
            'source_quote_id' => 999999,
            'status' => 'draft',
        ]);
        $response = $this->actingAs($copier)
            ->post(route('quotes.copy.store', $source), $payload);

        $copy = Quote::query()->whereKeyNot($source->id)->with('groups.items')->sole();
        $response->assertRedirect(route('quotes.show', $copy));
        $this->assertTrue($copy->createdBy->is($copier));
        $this->assertSame($source->id, $copy->source_quote_id);
        $this->assertSame('historical', $copy->status);
        $this->assertSame('Copied and adjusted', $copy->title);

        $source->refresh()->load('groups.items');
        $this->assertTrue($source->createdBy->is($owner));
        $this->assertSame($sourceTitle, $source->title);
        $this->assertSame($sourceItems, $source->groups->flatMap->items->pluck('name')->all());
    }

    public function test_invalid_copy_submission_does_not_create_or_change_a_quote(): void
    {
        $owner = $this->employee();
        $copier = $this->employee();
        $source = $this->createQuote($owner);

        $this->actingAs($copier)
            ->from(route('quotes.copy.edit', $source))
            ->post(route('quotes.copy.store', $source), $this->payload(['title' => '']))
            ->assertRedirect(route('quotes.copy.edit', $source))
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('quotes', 1);
        $this->assertSame('Historic quote', $source->fresh()->title);
    }

    public function test_direct_copy_opens_existing_preview_without_creating_a_record(): void
    {
        $owner = $this->employee();
        $copier = $this->employee();
        $source = $this->createQuote($owner);

        $this->actingAs($copier)
            ->post(route('quotes.copy', $source), ['mode' => 'direct'])
            ->assertRedirect(route('quotes.preview', $source));

        $this->assertDatabaseCount('quotes', 1);
    }

    public function test_an_employee_with_quotes_cannot_be_physically_deleted(): void
    {
        $employee = $this->employee();
        $quote = $this->createQuote($employee);

        try {
            $employee->delete();
            $this->fail('Expected the quote ownership foreign key to restrict user deletion.');
        } catch (QueryException) {
            // The database must preserve both the account and its quote history.
        }

        $this->assertDatabaseHas('users', ['id' => $employee->id]);
        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'created_by' => $employee->id,
        ]);
    }

    public function test_guests_cannot_access_quote_pages(): void
    {
        $this->get('/quotes')->assertRedirect('/login');
    }

    /** @param array<string, mixed> $overrides */
    private function createQuote(User $owner, array $overrides = []): Quote
    {
        $this->actingAs($owner)->post(route('quotes.store'), $this->payload($overrides))->assertRedirect();

        return Quote::query()->latest('id')->firstOrFail();
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Historic quote',
            'customer_title' => 'Acme team trip',
            'destination' => 'Huizhou',
            'year' => 2026,
            'month' => 7,
            'duration_days' => 2,
            'nights' => 1,
            'people_count' => 30,
            'budget_per_person' => 900,
            'planner_name' => 'Planner',
            'wechat' => 'trip-account',
            'phone' => '13800138000',
            'executor' => 'Travel agency',
            'reminder_title' => 'Notice',
            'reminder_text' => 'Schedule may change.',
            'source_name' => 'source.xlsx',
            'source_url' => 'https://docs.qq.com/sheet/example',
            'status' => 'historical',
            'groups' => [
                [
                    'name' => 'DAY 01',
                    'type' => 'day',
                    'items' => [[
                        'time' => '09:00',
                        'name' => 'Activities',
                        'unit' => 'group',
                        'quantity' => 1,
                        'unit_price' => 22970,
                        'note' => 'feature item',
                    ]],
                ],
                [
                    'name' => 'Other',
                    'type' => 'other',
                    'items' => [
                        ['name' => 'Insurance', 'unit' => 'person', 'quantity' => 30, 'unit_price' => 10],
                        ['name' => 'Service fee', 'unit' => 'person', 'quantity' => 30, 'unit_price' => 10],
                        ['name' => 'VAT invoice', 'unit' => '6%', 'quantity' => 1, 'is_tax' => true],
                    ],
                ],
            ],
        ], $overrides);
    }

    private function employee(): User
    {
        return User::factory()->create([
            'username' => fake()->unique()->userName(),
            'role' => 'employee',
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'username' => fake()->unique()->userName(),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
