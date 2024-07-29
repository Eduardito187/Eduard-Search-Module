<?php

return [
    'channels' => [
        'deleteBackupQuery' => [
            'driver' => 'single',
            'path' => storage_path('logs/deleteBackupQuery.log'),
            'level' => 'info',
        ],
        'runnerRulesExclude' => [
            'driver' => 'single',
            'path' => storage_path('logs/runnerRulesExclude.log'),
            'level' => 'info',
        ],
        'disabledIndexProducts' => [
            'driver' => 'single',
            'path' => storage_path('logs/disabledIndexProducts.log'),
            'level' => 'info',
        ]
    ]
];