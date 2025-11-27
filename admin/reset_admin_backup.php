<?php
// ÇIKTI ÖNCESİ TEMİZ
ob_start();
session_start();

require_once "../config.php";

// OLUŞTURULACAK KULLANICI
$email = "order@smaaky.com";
$name  = "Order Manager";
$role  = "admin";
$plain_password = "Admin123";

// HASH
$hash = password_hash($plain_password, PASSWORD_DEFAULT);

// VAR MI?
$stmt = $pdo->prepare("SELECT id FROM admin_users WHERE email = ?");
$stmt->execute([$email]);
$exists = $stmt->fetchColumn();

if ($exists) {
    // UPDATE
    $stmt = $pdo->prepare("UPDATE admin_users 
        SET name=?, password=?, role=? 
        WHERE email=?");
    $stmt->execute([$name, $hash, $role, $email]);
    echo "UPDATED<br>";
} else {
    // INSERT
    $stmt = $pdo->prepare("INSERT INTO admin_users (name, email, password, role)
        VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $hash, $role]);
    echo "CREATED<br>";
}

echo "Login: $email<br>";
echo "Password: $plain_password<br>";
echo "OK";

ob_end_flush();
?>