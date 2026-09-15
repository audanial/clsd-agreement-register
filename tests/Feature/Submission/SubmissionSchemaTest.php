<?php

namespace Tests\Feature\Submission;

use App\Models\Campus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubmissionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_foreign_key_column_is_covered_by_a_leading_index_on_sqlite(): void
    {
        $this->assertForeignKeysAreIndexed('submissions');
        $this->assertForeignKeysAreIndexed('submission_activities');
    }

    private function assertForeignKeysAreIndexed(string $table): void
    {
        $indexes = Schema::getIndexes($table);
        $foreignKeys = Schema::getForeignKeys($table);

        $this->assertNotEmpty($foreignKeys, "{$table} should define foreign keys.");

        $leadingColumns = collect($indexes)
            ->map(fn (array $index) => $index['columns'][0] ?? null)
            ->filter()
            ->unique()
            ->values();

        foreach ($foreignKeys as $foreignKey) {
            $column = $foreignKey['columns'][0] ?? null;

            $this->assertNotNull($column, "Foreign key on {$table} has no column.");
            $this->assertContains(
                $column,
                $leadingColumns->all(),
                "Foreign key column {$column} on {$table} is not the leading column of an index."
            );
        }
    }

    public function test_submissions_status_defaults_to_pending(): void
    {
        $user = User::factory()->create();
        $campus = Campus::create(['code' => 'TEST', 'name' => 'Fictional Test Campus', 'is_active' => true, 'sort_order' => 1]);

        DB::table('submissions')->insert([
            'created_by' => $user->id,
            'campus_id' => $campus->id,
            'title' => 'Schema Test',
            'partner_name' => 'Fictional Partner',
            'purpose' => 'Schema purpose.',
            'submitted_at' => now(),
        ]);

        $this->assertSame('pending', DB::table('submissions')->value('status'));
    }

    public function test_submissions_agreement_id_exists_and_is_nullable(): void
    {
        $this->assertTrue(Schema::hasColumn('submissions', 'agreement_id'));

        $column = collect(Schema::getColumns('submissions'))
            ->firstWhere('name', 'agreement_id');

        $this->assertNotNull($column);
        $this->assertTrue($column['nullable']);
    }
}
