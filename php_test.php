<?php
// Minimal PHP test file to check if PHP is running and server is not blocking requests
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "PHP is working. POST: ";
print_r($_POST);
