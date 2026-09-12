<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
| Run `php artisan schedule:work` locally, or a single cron entry calling
| `schedule:run` every minute in production.
*/

// Refresh FX rates before the Lagos working day starts. Does nothing while
// FX_DRIVER is 'manual', which is the default.
Schedule::command('fx:refresh')
    ->dailyAt('06:00')
    ->timezone('Africa/Lagos')
    ->withoutOverlapping();

// Failed queue jobs are pruned, not kept forever; a notification that failed
// three days ago is not being retried.
Schedule::command('queue:prune-failed --hours=168')->weekly();

Schedule::command('model:prune')->daily();
