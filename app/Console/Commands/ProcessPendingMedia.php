<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ProcessMediaAsset;
use App\Models\MediaAsset;
use Illuminate\Console\Command;

/**
 * Generate derivatives for uploads the queue never got to, in this process.
 *
 * The pipeline belongs on the queue — re-encoding a 4000px original is not work
 * for a web request. But an upload that has already happened should not need
 * re-uploading just because no worker was running at the time, and on a fresh
 * machine that is exactly the state the catalogue is in.
 */
class ProcessPendingMedia extends Command
{
    /** @var string */
    protected $signature = 'media:process {--retry-failed : Also re-attempt assets that previously failed}';

    /** @var string */
    protected $description = 'Process uploaded images still waiting on the media queue';

    public function handle(): int
    {
        $statuses = (bool) $this->option('retry-failed')
            ? ['pending', 'processing', 'failed']
            : ['pending', 'processing'];

        $assets = MediaAsset::query()
            ->where('kind', 'image')
            ->whereIn('processing_status', $statuses)
            ->orderBy('id')
            ->get();

        if ($assets->isEmpty()) {
            $this->components->info('Nothing waiting.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($assets as $asset) {
            try {
                (new ProcessMediaAsset($asset->id))->handle();
                $this->components->twoColumnDetail("#{$asset->id} {$asset->path}", '<fg=green>ready</>');
            } catch (\Throwable $e) {
                $failed++;
                $this->components->twoColumnDetail("#{$asset->id} {$asset->path}", '<fg=red>failed</>');
                $this->line('   '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->components->info(sprintf(
            '%d processed, %d failed.',
            $assets->count() - $failed,
            $failed,
        ));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
