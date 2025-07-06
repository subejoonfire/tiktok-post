<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Tiktok::index');
$routes->get('/tiktok', 'Tiktok::index');
$routes->get('/tiktok/login', 'Tiktok::login');
$routes->get('/tiktok/callback', 'Tiktok::callback');
$routes->get('/tiktok/dashboard', 'Tiktok::dashboard');
$routes->get('/tiktok/logout', 'Tiktok::logout');
$routes->post('/tiktok/upload', 'Tiktok::upload');
