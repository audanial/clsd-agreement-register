<?php

namespace Tests\Feature\Submission;

use App\Actions\CreateSubmission;
use App\Models\Campus;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubmissionValidationTest extends TestCase
{
    use RefreshDatabase;

    private function validInput(): array
    {
        $campus = Campus::firstOrCreate(
            ['code' => 'TEST'],
            ['name' => 'Fictional Test Campus', 'is_institute' => false, 'sort_order' => 1, 'is_active' => true]
        );

        return [
            'title' => 'Fictional Submission Title',
            'campus_id' => $campus->id,
            'partner_name' => 'Fictional Partner Sdn Bhd',
            'agreement_type' => 'MOU',
            'purpose' => 'A fictional purpose for testing.',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->requester()->create());
    }

    public function test_fully_valid_payload_is_accepted(): void
    {
        app(CreateSubmission::class)($this->validInput());

        $this->assertSame(1, DB::table('submissions')->count());
        $this->assertSame(1, DB::table('submission_activities')->count());
    }

    public function test_null_agreement_type_is_accepted_and_stored_as_null(): void
    {
        app(CreateSubmission::class)([...$this->validInput(), 'agreement_type' => null]);

        $refetched = DB::table('submissions')->first();

        $this->assertNotNull($refetched);
        $this->assertNull($refetched->agreement_type);
        $this->assertSame(1, DB::table('submission_activities')->count());
    }

    public function test_each_approved_agreement_type_is_accepted(): void
    {
        foreach (['LOI', 'NDA', 'MOA', 'MOU', 'SEA', 'ADDENDUM'] as $type) {
            app(CreateSubmission::class)([...$this->validInput(), 'agreement_type' => $type]);
        }

        $this->assertSame(6, DB::table('submissions')->count());

        foreach (Submission::AGREEMENT_TYPES as $type) {
            $this->assertSame(
                1,
                DB::table('submissions')->where('agreement_type', $type)->count(),
                "agreement_type {$type} should be persisted.",
            );
        }
    }

    public function test_empty_string_agreement_type_is_normalized_to_null(): void
    {
        app(CreateSubmission::class)([...$this->validInput(), 'agreement_type' => '']);

        $refetched = DB::table('submissions')->first();

        $this->assertNotNull($refetched);
        $this->assertNull($refetched->agreement_type);
    }

    public function test_whitespace_only_agreement_type_is_normalized_to_null(): void
    {
        app(CreateSubmission::class)([...$this->validInput(), 'agreement_type' => "  \t\n "]);

        $refetched = DB::table('submissions')->first();

        $this->assertNotNull($refetched);
        $this->assertNull($refetched->agreement_type);
    }

    public function test_moc_agreement_type_is_rejected(): void
    {
        try {
            app(CreateSubmission::class)([...$this->validInput(), 'agreement_type' => 'MOC']);
            $this->fail('MOC agreement_type should be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('agreement_type', $e->errors());
        }

        $this->assertTablesEmpty();
    }

    public function test_unknown_agreement_type_is_rejected(): void
    {
        try {
            app(CreateSubmission::class)([...$this->validInput(), 'agreement_type' => 'XYZ']);
            $this->fail('Unknown agreement_type should be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('agreement_type', $e->errors());
        }

        $this->assertTablesEmpty();
    }

    public function test_missing_and_nonexistent_campus_id_are_rejected(): void
    {
        $cases = [
            'missing' => function (array $input): array {
                unset($input['campus_id']);

                return $input;
            },
            'nonexistent' => function (array $input): array {
                $input['campus_id'] = 99999;

                return $input;
            },
        ];

        foreach ($cases as $case => $mutate) {
            $input = $mutate($this->validInput());

            try {
                app(CreateSubmission::class)($input);
                $this->fail("{$case} campus_id should be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('campus_id', $e->errors());
            }

            $this->assertTablesEmpty();
        }
    }

    public function test_inactive_campus_is_rejected(): void
    {
        $inactive = Campus::create(['code' => 'INA', 'name' => 'Inactive Campus', 'is_institute' => false, 'sort_order' => 2, 'is_active' => false]);

        try {
            app(CreateSubmission::class)([...$this->validInput(), 'campus_id' => $inactive->id]);
            $this->fail('Inactive campus should be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('campus_id', $e->errors());
        }

        $this->assertTablesEmpty();
    }

    public function test_tbd_campus_is_rejected_even_when_active(): void
    {
        $tbd = Campus::create(['code' => 'TBD', 'name' => 'Not Assigned', 'is_institute' => false, 'sort_order' => 999, 'is_active' => true]);

        try {
            app(CreateSubmission::class)([...$this->validInput(), 'campus_id' => $tbd->id]);
            $this->fail('TBD campus should be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('campus_id', $e->errors());
        }

        $this->assertTablesEmpty();
    }

    public function test_missing_required_fields_are_rejected(): void
    {
        foreach (['title', 'partner_name', 'purpose'] as $field) {
            $input = $this->validInput();
            unset($input[$field]);

            try {
                app(CreateSubmission::class)($input);
                $this->fail("Missing {$field} should be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($field, $e->errors());
            }

            $this->assertTablesEmpty();
        }
    }

    public function test_title_of_256_characters_is_rejected(): void
    {
        $this->assertRejectedAtMaxLength('title', 256);
    }

    public function test_title_of_255_characters_is_accepted(): void
    {
        $this->assertAcceptedAtMaxLength('title', 255);
    }

    public function test_partner_name_of_256_characters_is_rejected(): void
    {
        $this->assertRejectedAtMaxLength('partner_name', 256);
    }

    public function test_partner_name_of_255_characters_is_accepted(): void
    {
        $this->assertAcceptedAtMaxLength('partner_name', 255);
    }

    public function test_purpose_of_5001_characters_is_rejected(): void
    {
        $this->assertRejectedAtMaxLength('purpose', 5001);
    }

    public function test_purpose_of_5000_characters_is_accepted(): void
    {
        $this->assertAcceptedAtMaxLength('purpose', 5000);
    }

    /**
     * Prove a boundary value within the limit is genuinely accepted.
     *
     * No try/catch here: if ValidationException were wrongly thrown, it
     * propagates out of the test and fails it, so this path cannot pass
     * through the rejection branch.
     */
    private function assertAcceptedAtMaxLength(string $field, int $length): void
    {
        $value = str_repeat('a', $length);

        app(CreateSubmission::class)([...$this->validInput(), $field => $value]);

        $this->assertSame(1, DB::table('submissions')->count());
        $this->assertSame(1, DB::table('submission_activities')->count());

        $refetched = DB::table('submissions')->first();

        $this->assertNotNull($refetched);
        $this->assertSame($value, $refetched->{$field});
        $this->assertSame($length, mb_strlen($refetched->{$field}));
    }

    private function assertRejectedAtMaxLength(string $field, int $length): void
    {
        try {
            app(CreateSubmission::class)([...$this->validInput(), $field => str_repeat('a', $length)]);
            $this->fail("{$field} of {$length} characters should be rejected.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }

        $this->assertTablesEmpty();
    }

    private function assertTablesEmpty(): void
    {
        $this->assertSame(0, DB::table('submission_activities')->count());
        $this->assertSame(0, DB::table('submissions')->count());
    }
}
