<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The `documents` disk holds Private & Confidential agreements. These tests pin the
 * settings that keep it private, so a well-meaning future edit to config/filesystems.php
 * cannot quietly expose it over HTTP.
 */
class DocumentsDiskConfigTest extends TestCase
{
    public function test_the_documents_disk_is_configured_and_private(): void
    {
        $this->assertNotNull(config('filesystems.disks.documents'), 'The documents disk is not configured.');

        $this->assertSame('private', config('filesystems.disks.documents.visibility'));

        $this->assertNotNull(Storage::disk('documents'));
    }

    public function test_the_documents_disk_is_not_served_over_http(): void
    {
        $this->assertFalse(
            config('filesystems.disks.documents.serve'),
            "The documents disk must never set 'serve' => true: it would register /storage/{path} "
            .'routes that hand out P&C files to anyone with a signed URL, with no per-user check '
            .'and no audit entry.'
        );
    }

    public function test_no_http_route_exists_for_the_documents_disk(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->all();

        $this->assertNotContains('storage.documents', $names);
        $this->assertNotContains('storage.documents.upload', $names);
    }

    public function test_the_documents_disk_throws_on_failure(): void
    {
        $this->assertTrue(
            config('filesystems.disks.documents.throw'),
            'A failed write of a legal document must raise, not return false silently.'
        );
    }

    public function test_the_documents_disk_root_is_outside_the_public_directory(): void
    {
        $root = config('filesystems.disks.documents.root');

        $this->assertStringNotContainsString(
            DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR,
            $root.DIRECTORY_SEPARATOR
        );
    }
}
