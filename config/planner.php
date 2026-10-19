<?php

return [
    'queue' => [
        // 'shared': all clubs on one queue; 'per_club': queue "club-{slug}" so each club can get its own worker.
        'mode' => env('PLANNER_QUEUE_MODE', 'shared'),
        'shared' => env('PLANNER_QUEUE', 'planner'),
    ],

    'demo' => [
        // How long a demo club made from a demo request can be used.
        'trial_days' => (int) env('PLANNER_DEMO_TRIAL_DAYS', 14),
        // Days after the trial ended before platform:prune-demo-clubs deletes the demo.
        'prune_after_days' => (int) env('PLANNER_DEMO_PRUNE_AFTER_DAYS', 30),
    ],
];
