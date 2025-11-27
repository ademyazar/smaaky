<!-- Instellingen -->
<li class="sidebar-item has-submenu <?= ($activeMenu === 'settings' ? 'active' : '') ?>">
    <a href="#" class="sidebar-link">
        <i class="icon icon-settings"></i>
        <span>Instellingen</span>
        <i class="submenu-arrow">&rsaquo;</i>
    </a>

    <ul class="submenu">
        <li><a href="settings_general.php">Bedrijfsinstellingen</a></li>
        <li><a href="settings_users.php">Gebruikersbeheer</a></li>
        <li><a href="settings_roles.php">Rollen & Rechten</a></li>
        <li><a href="settings_password.php">Wachtwoord wijzigen</a></li>
        <li><a href="settings_order.php">Bestelinstellingen</a></li>
        <li><a href="settings_email.php">E-mail instellingen</a></li>
    </ul>
</li>