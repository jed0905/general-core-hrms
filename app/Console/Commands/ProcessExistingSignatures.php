<?php

namespace App\Console\Commands;

use App\Models\PersonalInformation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcessExistingSignatures extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'signatures:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command processes existing signature images to make the background transparent and updates the database paths accordingly.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $records = PersonalInformation::whereNotNull('e_signature_path')->get();

        foreach ($records as $record) {
            $path = $record->e_signature_path;

            if (!Storage::disk('public')->exists($path)) {
                continue;
            }

            $input = storage_path('app/public/' . $path);

            // Skip if already processed (optional rule)
            if (str_contains($path, 'processed')) {
                continue;
            }

            $filename = 'processed_' . basename($path);
            $processedPath = 'signatures/processed/' . $filename;
            $output = storage_path('app/public/' . $processedPath);

            if (!file_exists(dirname($output))) {
                mkdir(dirname($output), 0755, true);
            }

            $command = "convert \"$input\" -alpha set -fuzz 12% -transparent white -trim +repage -strip \"$output\"";
            exec($command, $log, $status);

            if ($status === 0 && file_exists($output)) {
                // Update DB to use processed version
                $record->update([
                    'e_signature_path' => $processedPath
                ]);
            }
        }

        $this->info('All signatures processed.');
    }
}
