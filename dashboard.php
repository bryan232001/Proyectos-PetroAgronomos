<?php
// dashboard.php

// Autoload classes using Composer
require_once __DIR__ . '/vendor/autoload.php';

session_start();

// Include database connection and helper functions
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

// Use the fully qualified class name for the controller
use App\Controllers\DashboardController;

// Create an instance of the controller and pass the database connection
$dashboardController = new DashboardController($pdo);

// The controller will now handle the entire request (logic and view rendering)
$dashboardController->index();