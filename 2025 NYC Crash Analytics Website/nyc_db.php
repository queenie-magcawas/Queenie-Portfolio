<?php

$host = "sql107.infinityfree.com";
$username = "if0_41881329";
$password = "**********";
$dbname = "if0_41881329_crashes_2025";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>