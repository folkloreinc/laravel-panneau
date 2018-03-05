<?php

namespace Folklore\Panneau\Console;

use Illuminate\Console\Command;

class PublishVendorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'panneau:vendor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish vendor assets';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->call('vendor:publish', [
            '--provider' => \Folklore\Panneau\PanneauServiceProvider::class,
            '--tag' => 'vendor',
            '--force' => true
        ]);
    }
}
