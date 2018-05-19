<?php

namespace Folklore\Panneau\Console;

use Illuminate\Console\Command;

class PublishCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'panneau:publish {--tags=*} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish panneau files';

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
        $tags = $this->option('tags');
        $force = $this->option('force');
        if (!sizeof($tags)) {
            $choiceTags = [
                'config',
                'views',
                'lang',
                'assets',
            ];
            $this->choice('What tags to you want to publish?', $choiceTags, null, null, true);
        }

        if (!$force) {
            $force = $this->confirm('Do you want to force publish?');
        }

        foreach ($tags as $tag) {
            $this->call('vendor:publish', [
                '--provider' => \Folklore\Panneau\PanneauServiceProvider::class,
                '--tag' => $tag,
                '--force' => $force
            ]);
        }
    }
}
