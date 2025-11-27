<?php
// ÇIKTI GÖNDERMEDEN BAŞLA — çok önemli!
ob_start();
session_start();
require_once "../config.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email  = trim($_POST["email"] ?? "");
    $pass   = trim($_POST["password"] ?? "");

    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($pass, $user["password"])) {

        // Kullanıcı bilgilerini session’a koy
        $_SESSION["admin_logged_in"] = true;
        $_SESSION["admin_id"]        = $user["id"];
        $_SESSION["admin_name"]      = $user["name"];
        $_SESSION["admin_role"]      = $user["role"];

        // Redirect
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Ongeldige inloggegevens.";
    }
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Smaaky Admin Login</title>
    <link rel="stylesheet" href="assets/admin-login.css">
</head>
<body>

<div class="login-box">
    <h1>Smaaky Admin Login</h1>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="email" name="email" placeholder="E-mail" required>
        <input type="password" name="password" placeholder="Wachtwoord" required>
        <button type="submit">Inloggen</button>
    </form>
</div>

</body>
</html>