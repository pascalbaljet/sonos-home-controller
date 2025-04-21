<?php

namespace App\Console\Commands;

use App\Http\Controllers\SonosController;
use Illuminate\Console\Command;

class CacheRooms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cache-rooms';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cache the Sonos rooms';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $jsonResponse = app(SonosController::class)->getRooms();

        $rooms = $jsonResponse->getData(true);

        $this->info('Rooms found: '.count($rooms));
    }
}
