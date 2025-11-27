<?php
// admin/_header.php

// Session sadece bir kez başlasın
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

// Basit admin koruması (mevcut sistemine göre uyarlayabilirsin)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Sayfa başlığı & aktif menü
if (!isset($pageTitle)) {
    $pageTitle = 'Dashboard';
}
if (!isset($activeMenu)) {
    $activeMenu = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Smaaky Admin – <?= htmlspecialchars($pageTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Sidebar genişliği */
        .sidebar-width { width: 260px; }

        /* Scrollbar sade */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: rgba(148,163,184,0.7); border-radius: 999px; }
        ::-webkit-scrollbar-track { background: transparent; }

        /* Metoxi tarzı küçük gölge */
        .card-shadow { box-shadow: 0 10px 30px rgba(15,23,42,0.06); }

        /* Aktif menü */
        .nav-item-active {
            background: linear-gradient(90deg, #0f172a, #020617);
            color: #f97316 !important;
        }

        .badge-status {
            border-radius: 999px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900">
<div class="min-h-screen flex">

    <!-- SIDEBAR -->
    <aside class="sidebar-width bg-slate-900 text-slate-100 flex flex-col">
        <!-- Logo + Brand -->
        <div class="px-6 py-5 flex items-center gap-3 border-b border-slate-800">
            <div class="h-10 w-10 rounded-full bg-orange-500 flex items-center justify-center text-sm font-black tracking-tight">
                SM
            </div>
            <div>
                <div class="text-sm uppercase tracking-[0.18em] text-slate-400">Admin</div>
                <div class="text-xl font-black tracking-tight">Smaaky</div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-3 py-4 text-sm font-medium space-y-1 overflow-y-auto">
            <a href="dashboard.php"
               class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-800 <?=
                    $activeMenu === 'dashboard' ? 'nav-item-active' : '' ?>">
                <span class="text-lg">📊</span>
                <span>Dashboard</span>
            </a>

            <a href="orders.php"
               class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-800 <?=
                    $activeMenu === 'orders' ? 'nav-item-active' : '' ?>">
                <span class="text-lg">📦</span>
                <span>Bestellingen</span>
            </a>

            <a href="products.php"
               class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-800 <?=
                    $activeMenu === 'products' ? 'nav-item-active' : '' ?>">
                <span class="text-lg">🍔</span>
                <span>Producten</span>
            </a>

            <a href="categories.php"
               class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-800 <?=
                    $activeMenu === 'categories' ? 'nav-item-active' : '' ?>">
                <span class="text-lg">📂</span>
                <span>Categorieën</span>
            </a>

            <a href="extras.php"
               class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-800 <?=
                    $activeMenu === 'extras' ? 'nav-item-active' : '' ?>">
                <span class="text-lg">➕</span>
                <span>Extras (Toppings)</span>
            </a>

            <a href="settings.php"
               class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-800 <?=
                    $activeMenu === 'settings' ? 'nav-item-active' : '' ?>">
                <span class="text-lg">⚙️</span>
                <span>Instellingen</span>
            </a>
        </nav>

        <!-- Logout -->
        <div class="px-4 py-4 border-t border-slate-800">
            <a href="logout.php"
               class="flex items-center gap-2 text-sm font-semibold text-red-400 hover:text-red-300">
                <span>⏏</span>
                <span>Uitloggen</span>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT WRAPPER -->
    <div class="flex-1 flex flex-col min-h-screen">

        <!-- TOP BAR -->
        <header class="h-16 bg-white/80 backdrop-blur border-b border-slate-200 flex items-center justify-between px-6">
            <div>
                <h1 class="text-xl font-black tracking-tight"><?= htmlspecialchars($pageTitle) ?></h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Smaaky Rotterdam – beheer en inzicht in je bestellingen.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <div class="hidden md:flex items-center bg-slate-100 rounded-full px-3 py-1.5 text-xs text-slate-500">
                    <span class="mr-2">🔎</span>
                    <input type="text" placeholder="Zoeken..." class="bg-transparent outline-none w-32">
                </div>

                <button class="relative h-9 w-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-500">
                    🔔
                    <span class="absolute -top-1 -right-1 h-4 w-4 rounded-full bg-orange-500 text-[10px] text-white flex items-center justify-center">
                        3
                    </span>
                </button>

                <div class="flex items-center gap-2">
                    <div class="h-9 w-9 rounded-full bg-slate-900 text-slate-100 flex items-center justify-center text-xs font-semibold">
                        AD
                    </div>
                    <div class="hidden sm:block">
                        <div class="text-xs font-semibold">
                            Admin
                        </div>
                        <div class="text-[11px] text-slate-500">
                            Smaaky Rotterdam
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- PAGE CONTENT -->
        <main class="flex-1 p-6 overflow-y-auto bg-slate-100/80">