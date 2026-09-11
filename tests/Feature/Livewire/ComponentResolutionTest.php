<?php

namespace Tests\Feature\Livewire;

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Guards the component file convention.
 *
 * Livewire components are emoji-free single-file components under
 * resources/views/livewire/. resources/views/components/ is reserved for anonymous
 * Blade components, because that directory is registered as BOTH a Livewire location
 * and Laravel's anonymous-component path — mixing the two there makes it ambiguous
 * which kind any given file is.
 *
 * The emoji prefix is not required by the resolver: Finder::normalizeName() strips it,
 * and Finder::resolveSingleFileComponentPath() falls back to plain filenames.
 */
class ComponentResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_four_existing_components_resolve_by_name_after_the_move(): void
    {
        $agreement = Agreement::factory()->signed()->create();

        $this->actingAs(User::factory()->admin()->create());

        Livewire::test('agreements-index')->assertOk();
        Livewire::test('agreement-show', ['agreement' => $agreement])->assertOk();
        Livewire::test('agreement-form')->assertOk();
        Livewire::test('user-manager')->assertOk();
    }

    public function test_no_livewire_component_files_remain_in_the_views_components_directory(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views/components'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            // The same signature Livewire's Finder::hasValidSingleFileComponentSource() uses.
            if (preg_match('/\<\?php.*\bnew\s+class\b/s', file_get_contents($file->getPathname()))) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Livewire single-file components belong in resources/views/livewire/, not in the '
            ."anonymous Blade component directory. Offending file(s):\n  ".implode("\n  ", $offenders)
        );
    }

    public function test_livewire_components_carry_no_emoji_prefix(): void
    {
        $offenders = [];

        foreach (glob(resource_path('views/livewire').'/*.blade.php') as $path) {
            if (preg_match('/^\x{26A1}/u', basename($path))) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Component filenames must not use the high-voltage emoji prefix. Set '
            .'make_command.emoji => false in config/livewire.php. Offending file(s): '
            .implode(', ', $offenders)
        );
    }
}
