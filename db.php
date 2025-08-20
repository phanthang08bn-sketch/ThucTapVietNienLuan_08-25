<?php
$host = "dpg-d2ett6juibrs738epejg-a.singapore-postgres.render.com";
$port = "5432";
$dbname = "db_quanlythuchi_dey5";
$user = "db_quanlythuchi_dey5_user";
$password = "rt0ya0aedHgvQj8Ww3wi57U34HOqe5P8";

// Kết nối PostgreSQL qua SSL
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password sslmode=require");

// Kiểm tra kết nối
if (!$conn)
    die("Kết nối đến PostgreSQL thất bại.");
?>
