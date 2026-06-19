<?php

use Illuminate\Support\Facades\Route;

use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/run-migrations', function () {
    Artisan::call('migrate:fresh', ['--force' => true]);
    return 'Migration runned successfully';
});

Route::get('/run-new-migrations', function () {
    Artisan::call('migrate', ['--force' => true]);
    return 'Migration runned successfully';
});


Route::get('/run-seeder', function () {
    Artisan::call('db:seed', ['--force' => true]);
    return 'Database seeded successfully';
});

Route::get('/optimize-clear', function () {
    Artisan::call('optimize:clear');
    return 'Optimization cache cleared';
});

Route::get('/storage-link', function () {
    Artisan::call('storage:link');
    return 'Storage linked';
});

Route::get('/route-list', function () {
    $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())->map(function ($route) {
        return [
            'method' => implode('|', $route->methods()),
            'uri'    => $route->uri(),
            'name'   => $route->getName(),
            'action' => $route->getActionName(),
        ];
    })->filter(function ($route) {
        // Only show api routes
        return str_starts_with($route['uri'], 'api/');
    })->values();

    return response()->json([
        'total'  => $routes->count(),
        'routes' => $routes,
    ]);
});
