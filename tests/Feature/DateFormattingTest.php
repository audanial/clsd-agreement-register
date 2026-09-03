<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DateFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_date_component_renders_day_month_year(): void
    {
        $html = view('components.date', ['value' => Carbon::parse('2022-03-18')])->render();

        $this->assertStringContainsString('18 Mar 2022', $html);
        $this->assertStringNotContainsString('03/18/2022', $html);
        $this->assertStringNotContainsString('2022-03-18', $html);
    }

    public function test_the_date_component_renders_a_dash_for_null(): void
    {
        $html = view('components.date', ['value' => null])->render();

        $this->assertStringContainsString('—', $html);
        $this->assertStringNotContainsString('01 Jan 1970', $html);
    }

    public function test_no_view_renders_a_month_first_date_format(): void
    {
        $files = app(Filesystem::class)->allFiles(resource_path('views'));

        $monthFirstPatterns = [
            '/(?<!Y-)\bm-d\b/',
            '/(?<!Y-)\bn-j\b/',
            '/(?<!Y\/)\bm\/d\b/',
            '/(?<!Y\/)\bn\/j\b/',
        ];

        foreach ($files as $file) {
            $contents = $file->getContents();

            foreach ($monthFirstPatterns as $pattern) {
                $this->assertDoesNotMatchRegularExpression(
                    $pattern,
                    $contents,
                    "{$file->getPathname()} appears to contain a month-first date format ({$pattern})."
                );
            }
        }
    }

    public function test_date_inputs_carry_a_dd_mm_yyyy_hint(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        $html = Livewire::test('agreement-form')->html();

        $this->assertSame(2, substr_count($html, '(DD/MM/YYYY)'));
    }

    public function test_form_date_inputs_still_use_the_y_m_d_wire_format(): void
    {
        $this->actingAs(User::factory()->legal()->create());

        Livewire::test('agreement-form')
            ->set('agreement_date', '2024-08-12')
            ->set('expiry_date', '2027-08-12')
            ->assertSet('agreement_date', '2024-08-12')
            ->assertSet('expiry_date', '2027-08-12');
    }
}
