<?php

$host = "sql107.infinityfree.com";
$dbname = "if0_41881329_data";
$username = "if0_41881329";
$password = "**********";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>