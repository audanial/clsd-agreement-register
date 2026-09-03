<?php

namespace Tests\Feature\Agreement;

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgreementDurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->legal()->create());
    }

    public function test_whole_year_duration_renders_as_years(): void
    {
        $agreement = Agreement::factory()->create([
            'agreement_date' => '2022-03-18',
            'expiry_date' => '2027-03-18',
        ]);

        $this->assertSame(60, $agreement->durationInMonths());
        $this->assertSame('5 years', $agreement->durationLabel());

        $html = view('components.agreement-duration', ['agreement' => $agreement])->render();

        $this->assertStringContainsString('18 Mar 2022 – 18 Mar 2027', $html);
        $this->assertStringContainsString('(5 years)', $html);
    }

    public function test_partial_year_duration_renders_years_and_months(): void
    {
        $agreement = Agreement::factory()->create([
            'agreement_date' => '2022-03-18',
            'expiry_date' => '2024-06-18',
        ]);

        $this->assertSame(27, $agreement->durationInMonths());
        $this->assertSame('2 years, 3 months', $agreement->durationLabel());

        $html = view('components.agreement-duration', ['agreement' => $agreement])->render();

        $this->assertStringContainsString('18 Mar 2022 – 18 Jun 2024', $html);
        $this->assertStringContainsString('(2 years, 3 months)', $html);
    }

    public function test_sub_year_duration_renders_months_only(): void
    {
        $agreement = Agreement::factory()->create([
            'agreement_date' => '2022-03-18',
            'expiry_date' => '2022-06-18',
        ]);

        $this->assertSame(3, $agreement->durationInMonths());
        $this->assertSame('3 months', $agreement->durationLabel());

        $html = view('components.agreement-duration', ['agreement' => $agreement])->render();

        $this->assertStringContainsString('18 Mar 2022 – 18 Jun 2022', $html);
        $this->assertStringContainsString('(3 months)', $html);
    }

    public function test_sub_month_duration_renders_less_than_a_month(): void
    {
        $agreement = Agreement::factory()->create([
            'agreement_date' => '2022-03-18',
            'expiry_date' => '2022-04-01',
        ]);

        $this->assertSame(0, $agreement->durationInMonths());
        $this->assertSame('less than a month', $agreement->durationLabel());

        $html = view('components.agreement-duration', ['agreement' => $agreement])->render();

        $this->assertStringContainsString('18 Mar 2022 – 01 Apr 2022', $html);
        $this->assertStringContainsString('(less than a month)', $html);
    }

    public function test_null_expiry_yields_no_duration_and_stays_indefinite(): void
    {
        $agreement = Agreement::factory()->create([
            'agreement_date' => '2022-03-18',
            'expiry_date' => null,
        ]);

        $this->assertNull($agreement->durationInMonths());
        $this->assertNull($agreement->durationLabel());
        $this->assertTrue($agreement->isIndefinite());

        $html = view('components.agreement-duration', ['agreement' => $agreement])->render();

        $this->assertStringContainsString('18 Mar 2022 – Indefinite', $html);
        $this->assertStringNotContainsString('(', $html);
    }

    public function test_null_start_date_yields_no_duration(): void
    {
        $agreement = Agreement::factory()->create([
            'agreement_date' => null,
            'expiry_date' => '2027-03-18',
        ]);

        $this->assertNull($agreement->durationInMonths());
        $this->assertNull($agreement->durationLabel());

        $html = view('components.agreement-duration', ['agreement' => $agreement])->render();

        $this->assertStringContainsString('— – 18 Mar 2027', $html);
        $this->assertStringNotContainsString('(', $html);
    }

    public function test_expiry_before_start_does_not_produce_a_negative_duration(): void
    {
        $agreement = Agreement::factory()->create([
            'agreement_date' => '2026-01-15',
            'expiry_date' => '2026-01-01',
        ]);

        $this->assertNull($agreement->durationInMonths());
        $this->assertNull($agreement->durationLabel());

        $html = view('components.agreement-duration', ['agreement' => $agreement])->render();

        $this->assertStringContainsString('15 Jan 2026 – 01 Jan 2026', $html);
        $this->assertStringNotContainsString('(', $html);
    }

    public function test_leap_year_boundary_is_handled(): void
    {
        $agreement = Agreement::factory()->create([
            'agreement_date' => '2024-02-29',
            'expiry_date' => '2025-03-01',
        ]);

        $this->assertSame(12, $agreement->durationInMonths());
        $this->assertSame('1 year', $agreement->durationLabel());

        $html = view('components.agreement-duration', ['agreement' => $agreement])->render();

        $this->assertStringContainsString('29 Feb 2024 – 01 Mar 2025', $html);
        $this->assertStringContainsString('(1 year)', $html);
    }
}
