<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:service {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new service class';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $servicePath = app_path("Services/{$name}.php");

        // Check if already exists
        if (File::exists($servicePath)) {
            $this->error("Service {$name} already exists!");
            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        // Create directory if not exists
        if (!File::exists(app_path('Services'))) {
            File::makeDirectory(app_path('Services'));
        }

        // Create the file
        File::put($servicePath, $this->getStub($name));

        $this->info("Service {$name} created successfully.");
        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }

    protected function getStub($name)
    {
        return <<<PHP
<?php

namespace App\Services;

class {$name}
{
    public function __construct()
    {
        //
    }
}
PHP;
    }
}
