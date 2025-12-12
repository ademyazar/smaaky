<?php 
$activeMenu = 'settings';
require_once '_header.php';
?>
<h1 class="page-title">Wachtwoord wijzigen</h1>
<form method="post">
    <label>Huidig wachtwoord</label><br>
    <input type="password" name="old_password"><br><br>

    <label>Nieuw wachtwoord</label><br>
    <input type="password" name="new_password"><br><br>

    <label>Herhaal nieuw wachtwoord</label><br>
    <input type="password" name="repeat_password"><br><br>

    <button class="btn">Opslaan</button>
</form>
<?php require_once '_footer.php'; ?>