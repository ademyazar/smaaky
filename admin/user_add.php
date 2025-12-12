<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/_header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role  = trim($_POST['role']);
    $pass  = trim($_POST['password']);

    if ($name === '' || $email === '' || $pass === '') {
        $error = 'Alle velden zijn verplicht.';
    } else {
        $hash = password_hash($pass, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO admin_users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hash, $role]);

        $success = 'Gebruiker succesvol toegevoegd.';
    }
}
?>

<div class="page-content">
    <div class="page-header">
        <h1 class="page-title">Nieuwe gebruiker</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <label>Naam</label>
            <input type="text" name="name" class="form-input">

            <label>E-mail</label>
            <input type="email" name="email" class="form-input">

            <label>Wachtwoord</label>
            <input type="password" name="password" class="form-input">

            <label>Rol</label>
            <select name="role" class="form-input">
                <option value="admin">Admin</option>
                <option value="manager">Manager</option>
            </select>

            <button class="btn btn-primary mt-3">Opslaan</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>