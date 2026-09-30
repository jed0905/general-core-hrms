<?php

namespace App\Console\Commands;

use App\Models\LeaveApplicationAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MoveLeaveAttachmentsToPrivateStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave:move-attachments-private {--dry-run : List what would move without moving anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Move leave attachments from the public disk to private storage (safe to re-run)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $public = Storage::disk(LeaveApplicationAttachment::LEGACY_DISK);
        $private = Storage::disk(LeaveApplicationAttachment::DISK);
        $moved = 0;
        $missing = 0;

        LeaveApplicationAttachment::query()->orderBy('id')->each(function (LeaveApplicationAttachment $attachment) use ($public, $private, &$moved, &$missing) {
            $path = $attachment->file_path;

            if ($private->exists($path)) {
                return;
            }

            if (! $public->exists($path)) {
                $missing++;
                $this->warn("Attachment #{$attachment->id}: file not found ({$path}).");

                return;
            }

            if (! $this->option('dry-run')) {
                // Same relative path, so file_path stays valid. Only delete the
                // public copy once the private one is confirmed.
                if (! $private->writeStream($path, $public->readStream($path)) || ! $private->exists($path)) {
                    $this->error("Attachment #{$attachment->id}: could not copy {$path}; left in place.");

                    return;
                }

                $public->delete($path);
            }

            $moved++;
        });

        $verb = $this->option('dry-run') ? 'Would move' : 'Moved';
        $this->info("{$verb} {$moved} attachment(s) to private storage. Missing files: {$missing}.");

        return self::SUCCESS;
    }
}
