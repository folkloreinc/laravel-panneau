<?php

namespace Panneau\Console;

use Illuminate\Console\Command;
use Panneau\Uploads\Uploads;

/**
 * Removes the direct uploads that expired, used or not, with their files and the multipart
 * uploads they left open. Schedule it: `Schedule::command('panneau:prune-uploads')->hourly()`.
 */
final class PruneUploadsCommand extends Command
{
    protected $signature = 'panneau:prune-uploads';

    protected $description = 'Remove the direct uploads that expired, with their files';

    public function handle(Uploads $uploads): int
    {
        $count = $uploads->prune();
        $this->components->info(sprintf('Removed %d expired %s.', $count, $count === 1 ? 'upload' : 'uploads'));

        return self::SUCCESS;
    }
}
