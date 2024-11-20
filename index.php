<?php
// index.php

// Get the requested URL
$url = isset($_GET['url']) ? $_GET['url'] : '/';

// Define your routes
switch ($url) {
    case '/':
        include 'home.php';
        break;
    // Add more routes as needed
    default:
        include '404.php';
        break;
}