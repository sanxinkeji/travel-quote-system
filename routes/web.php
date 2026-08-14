<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/quotes');

foreach (['auth.php', 'users.php', 'quotes.php'] as $routeFile) {
    $path = __DIR__.DIRECTORY_SEPARATOR.$routeFile;

    if (file_exists($path)) {
        require $path;
    }
}
