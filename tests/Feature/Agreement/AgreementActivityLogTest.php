<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\Campus;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgreementActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Campus::create(['code' => 'TBD', 'name' => 'Not Assigned', 'is_active' => true, 'sort_order' => 9999]);
    }

    public function test_creating_an_agreement_writes_a_created_activity(): void
    {
        $partner = Partner::factory()->create();
        $campus = Campus::active()->first();
        $user = User::factory()->legal()->create();

        $this->actingAs($user);

        Livewire::test('agreement-form')
            ->set('title', 'Activity Test')
            ->set('type', 'MOU')
            ->set('partnerMode', 'existing')
            ->set('partner_id', $partner->id)
            ->set('campus_id', $campus->id)
            ->set('document_status', 'pending')
            ->set('project_status', 'not_started')
            ->call('save')
            ->assertRedirect();

        $agreement = Agreement::where('title', 'Activity Test')->first();
        $this->assertSame(1, $agreement->activities()->where('type', 'created')->count());
    }

    public function test_document_status_change_writes_a_status_changed_activity(): void
    {
        $agreement = Agreement::factory()->create(['document_status' => 'pending']);
        $user = User::factory()->legal()->create();

        $this->actingAs($user);

        Livewire::test('agreement-show', ['agreement' => $agreement])
            ->set('document_status', 'signed')
            ->call('updateStatus');

        $agreement->refresh();
        $this->assertSame(1, $agreement->activities()->where('type', 'status_changed')->count());
    }

    public function test_meta_records_the_field_and_the_from_and_to_values(): void
    {
        $agreement = Agreement::factory()->create(['document_status' => 'pending']);
        $user = User::factory()->legal()->create();

        $this->actingAs($user);

        Livewire::test('agreement-show', ['agreement' => $agreement])
            ->set('document_status', 'signed')
            ->call('updateStatus');

        $activity = $agreement->activities()->where('type', 'status_changed')->first();
        $this->assertNotNull($activity);
        $this->assertSame('document_status', $activity->meta['field']);
        $this->assertSame('pending', $activity->meta['from']);
        $this->assertSame('signed', $activity->meta['to']);
    }

    public function test_project_status_change_writes_its_own_activity_row(): void
    {
        $agreement = Agreement::factory()->create([
            'document_status' => 'signed',
            'project_status' => 'not_started',
        ]);
        $user = User::factory()->legal()->create();

        $this->actingAs($user);

        Livewire::test('agreement-show', ['agreement' => $agreement])
            ->set('project_status', 'ongoing')
            ->call('updateStatus');

        $agreement->refresh();
        $this->assertSame(1, $agreement->activities()->where('type', 'status_changed')->count());
        $this->assertSame('project_status', $agreement->activities()->first()->meta['field']);
    }

    public function test_no_activity_is_written_when_no_status_changed(): void
    {
        $agreement = Agreement::factory()->create([
            'document_status' => 'signed',
            'project_status' => 'ongoing',
        ]);
        $user = User::factory()->legal()->create();

        $this->actingAs($user);

        Livewire::test('agreement-show', ['agreement' => $agreement])
            ->call('updateStatus');

        $this->assertSame(0, $agreement->activities()->where('type', 'status_changed')->count());
    }

    public function test_editing_an_ordinary_field_writes_no_updated_activity(): void
    {
        $agreement = Agreement::factory()->create(['notes' => 'Old notes']);
        $user = User::factory()->legal()->create();

        $this->actingAs($user);

        Livewire::test('agreement-form', ['agreement' => $agreement])
            ->set('notes', 'New notes')
            ->call('save')
            ->assertRedirect();

        $agreement->refresh();
        $this->assertSame(0, $agreement->activities()->where('type', 'updated')->count());
    }

    public function test_activity_records_the_acting_user(): void
    {
        $agreement = Agreement::factory()->create(['document_status' => 'pending']);
        $user = User::factory()->legal()->create();

        $this->actingAs($user);

        Livewire::test('agreement-show', ['agreement' => $agreement])
            ->set('document_status', 'signed')
            ->call('updateStatus');

        $activity = $agreement->activities()->where('type', 'status_changed')->first();
        $this->assertSame($user->id, $activity->user_id);
    }

    public function test_status_change_activity_records_distinct_from_and_to_values(): void
    {
        $agreement = Agreement::factory()->create([
            'document_status' => 'pending',
            'project_status' => 'not_started',
        ]);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-show', ['agreement' => $agreement])
            ->set('document_status', 'signed')
            ->set('project_status', 'ongoing')
            ->call('updateStatus');

        $documentActivity = $agreement->activities()
            ->where('type', 'status_changed')
            ->where('meta->field', 'document_status')
            ->first();
        $projectActivity = $agreement->activities()
            ->where('type', 'status_changed')
            ->where('meta->field', 'project_status')
            ->first();

        $this->assertNotNull($documentActivity);
        $this->assertNotNull($projectActivity);

        $this->assertNotSame($documentActivity->meta['from'], $documentActivity->meta['to']);
        $this->assertNotSame($projectActivity->meta['from'], $projectActivity->meta['to']);

        $this->assertStringContainsString('from pending to signed', strtolower($documentActivity->description));
        $this->assertStringContainsString('from not_started to ongoing', strtolower($projectActivity->description));
    }

    public function test_edit_form_status_change_records_distinct_from_and_to_values(): void
    {
        $agreement = Agreement::factory()->create([
            'document_status' => 'pending',
            'project_status' => 'not_started',
        ]);

        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form', ['agreement' => $agreement])
            ->set('document_status', 'signed')
            ->set('project_status', 'ongoing')
            ->call('save')
            ->assertRedirect();

        $documentActivity = $agreement->activities()
            ->where('type', 'status_changed')
            ->where('meta->field', 'document_status')
            ->first();

        $this->assertNotNull($documentActivity);
        $this->assertNotSame($documentActivity->meta['from'], $documentActivity->meta['to']);
        $this->assertStringContainsString('from pending to signed', strtolower($documentActivity->description));
    }
}
