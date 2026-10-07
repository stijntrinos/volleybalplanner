<?php

return [
    'queue' => [
        // 'shared': all clubs on one queue; 'per_club': queue "club-{slug}" so each club can get its own worker.
        'mode' => env('PLANNER_QUEUE_MODE', 'shared'),
        'shared' => env('PLANNER_QUEUE', 'planner'),
    ],
];
