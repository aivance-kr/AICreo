<?php

declare(strict_types=1);
use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('weather', '\Plugins\Weather\Controllers\WeatherController::index');
