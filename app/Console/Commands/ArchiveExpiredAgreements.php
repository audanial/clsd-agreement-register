<?php

namespace App\Console\Commands;

use App\Actions\RecordAgreementActivity;
use App\Models\Agreement;
use Illuminate\Console\Command;

class ArchiveExpiredAgreements extends Command
{
    protected $signature = 'agreements:archive-expired';

    protected $description = 'Archive agreements whose expiry date has passed';

    public function handle(): int
    {
        Agreement::query()
            ->whereNull('archived_at')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', today())
            ->get()
            ->each(function (Agreement $agreement) {
                $agreement->archived_at = now();
                $agreement->archive_reason = 'expired';
                $agreement->save();

                app(RecordAgreementActivity::class)(
                    $agreement,
                    'archived',
                    'Automatically archived: expiry date passed',
                    ['reason' => 'expired']
                );
            });

        return self::SUCCESS;
    }
}
