<?php

return [
    // Gate production extraction-worker CLI access; this does not schedule any work.
    'worker_enabled' => env('QUESTION_BANK_WORKER_ENABLED', false),
    'max_source_attempts' => 3,
];
