<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('app:cleanup-tmp-downloads')]
#[Description('Delete leftover temp download folders older than 1 hour')]
class CleanupTmpDownloads extends Command
{
    public function handle(): void
    {
        $tmpRoot = storage_path('app/tmp');

        if (! File::isDirectory($tmpRoot)) {
            return;
        }

        $cutoff = now()->subHour()->getTimestamp();
        $removed = 0;

        foreach (File::directories($tmpRoot) as $dir) {
            if (File::lastModified($dir) < $cutoff) {
                File::deleteDirectory($dir);
                $removed++;
            }
        }

        $this->info("Removed {$removed} leftover temp download folder(s).");
    }
}
