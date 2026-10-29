<?php
use Core\Router;

// Authentication Routes
Router::get('/', 'AuthController@showLogin');
Router::get('/login', 'AuthController@showLogin');
Router::post('/login', 'AuthController@login');
Router::get('/logout', 'AuthController@logout');

// Carrier (Dispatcher Calling Rig) Routes
Router::get('/carrier', 'CarrierController@dashboard');
Router::post('/carrier/search', 'CarrierController@search');
Router::post('/carrier/update-range', 'CarrierController@updateRange');
Router::post('/carrier/clock', 'CarrierController@clockAction');
Router::post('/carrier/record-call', 'CarrierController@recordCall');
Router::post('/carrier/save-lead', 'CarrierController@saveLead');
Router::post('/carrier/log-copy', 'CarrierController@logCopy');
Router::get('/carrier/calls', 'CarrierController@getCalls');
Router::get('/carrier/saved', 'CarrierController@getSaved');
Router::get('/carrier/performance', 'CarrierController@getPerformance');

// Admin Routes (API Settings, User MC Range Management, Detailed Logs)
Router::get('/admin', 'AdminController@dashboard');
Router::post('/admin/settings', 'AdminController@saveSettings');
Router::post('/admin/test-fmcsa', 'AdminController@testFmcsa');
Router::post('/admin/create-carrier', 'AdminController@createCarrier');
Router::post('/admin/update-carrier-range', 'AdminController@updateCarrierRange');
Router::get('/admin/user-logs', 'AdminController@getUserLogs');
Router::get('/admin/user-sessions', 'AdminController@getUserSessions');

