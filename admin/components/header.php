<?php
if (!isset($_SESSION)) session_start();

// Admin login kontrolü
if (!isset($_SESSION["admin_logged_in"])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Smaaky Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-gray-100 text-gray-900">

<div class="flex">