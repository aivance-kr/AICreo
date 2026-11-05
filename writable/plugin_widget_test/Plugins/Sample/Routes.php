<?php

declare(strict_types=1);
use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('sample', '\Plugins\Sample\Controllers\SampleController::index');
