<?php
$host = "dpg-d2io8b6r433s73e1bsrg-a.singapore-postgres.render.com";
$port = "5432";
$dbname = "db_quanlythuchi_dey5_f407";
$user = "db_quanlythuchi_dey5_user";
$password = "B9ROJF69QKlejYycWu0G1ufl13FmPGYA";

// Kết nối PostgreSQL qua SSL
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password sslmode=require");

// Kiểm tra kết nối
if (!$conn)
    die("Kết nối đến PostgreSQL thất bại.");
?>
