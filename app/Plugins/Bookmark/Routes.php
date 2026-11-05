<?php

declare(strict_types=1);
use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('my/bookmarks', ['filter' => 'auth:member'], static function (RouteCollection $routes): void {
    $routes->get('/', '\Plugins\Bookmark\Controllers\BookmarkController::index');
    $routes->post('toggle/(:num)', '\Plugins\Bookmark\Controllers\BookmarkController::toggle/$1');
});
