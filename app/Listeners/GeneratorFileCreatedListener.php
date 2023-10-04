<?php

namespace App\Listeners;

use InfyOm\Generator\Events\GeneratorFileCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class GeneratorFileCreatedListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\InfyOm\Generator\Events\GeneratorFileCreated  $event
     * @return void
     */
    public function handle(GeneratorFileCreated $event)
    {
        $jsonData = json_encode($event->data, JSON_PRETTY_PRINT);
        file_put_contents('event_data.json', $jsonData);
    }
}
