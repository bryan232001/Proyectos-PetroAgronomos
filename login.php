<?php
// login.php

// Autoload classes using Composer
require_once __DIR__ . '/vendor/autoload.php';

session_start();

// Include database connection and helper functions
// Note: The controller now includes header/footer, so we only need the db and helpers here.
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';


// Use the fully qualified class name for the controller
use App\Controllers\LoginController;

// Create an instance of the controller and pass the database connection
$loginController = new LoginController($pdo);

// The controller will now handle the entire request (logic and view rendering)
$loginController->handleRequest();
