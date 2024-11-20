<?php
// index.php

// Get the requested URL
$url = isset($_GET['url']) ? $_GET['url'] : '/';

// Define your routes
switch ($url) {
    case '/':
        include '404.php';
        break;
}