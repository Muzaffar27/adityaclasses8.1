<?php

require __DIR__.'/../adityaclasses/vendor/autoload.php';

$app = require_once __DIR__.'/../adityaclasses/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo $kernel->call('config:clear');
echo "<br>";
echo $kernel->call('cache:clear');
echo "<br>";
echo $kernel->call('route:clear');
echo "<br>";
echo $kernel->call('view:clear');