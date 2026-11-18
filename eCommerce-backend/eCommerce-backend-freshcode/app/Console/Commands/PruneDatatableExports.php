<?php

namespace App\Console\Commands;

use App\Models\DatatableExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneDatatableExports extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Delete expired DataTable export files and records';

    public function handle(): int
    {
        $count = 0;
        DatatableExport::where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(200, function ($exports) use (&$count): void {
                foreach ($exports as $export) {
                    if ($export->path) {
                        Storage::disk($export->disk)->delete($export->path);
                    }
                    $export->delete();
                    $count++;
                }
            });
        $this->info(__('labels.expired_exports_pruned', ['count' => $count]));

        return self::SUCCESS;
    }
}
