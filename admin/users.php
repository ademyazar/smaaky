<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/_functions.php';
requireAdmin();

// Kullanıcıları çek
$stmt = $pdo->query("SELECT id, name, email, role, created_at FROM admin_users ORDER BY id ASC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-content">
    <div class="page-header">
        <h1 class="page-title">Admin Gebruikers</h1>
        <a href="user_add.php" class="btn btn-primary">➕ Nieuwe gebruiker</a>
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Naam</th>
                    <th>E-mail</th>
                    <th>Rol</th>
                    <th>Aangemaakt op</th>
                    <th>Actie</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= $u['role'] ?></td>
                        <td><?= $u['created_at'] ?></td>
                        <td>
                            <a class="btn-sm btn-secondary" href="user_edit.php?id=<?= $u['id'] ?>">Bewerken</a>
                            <a class="btn-sm btn-danger" 
                                href="user_delete.php?id=<?= $u['id'] ?>" 
                                onclick="return confirm('Weet je zeker dat je deze gebruiker wilt verwijderen?')">
                                Verwijderen
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>