<aside class="w-64 bg-[#0B0F19] text-white min-h-screen flex flex-col shadow-xl">

    <!-- LOGO BLOK -->
    <div class="flex flex-col items-center justify-center py-10 border-b border-white/10">
        <img src="/admin/assets/smaaky-logo.svg"
             class="w-4/5 max-w-[160px] mb-4 opacity-95"
             alt="Smaaky Logo">

        <h1 class="text-2xl font-black tracking-tight text-center">
            Smaaky Admin
        </h1>
    </div>

    <!-- MENU -->
    <nav class="flex-1 px-4 py-6 space-y-3">

        <!-- Dashboard -->
        <a href="/admin/dashboard.php"
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all
           <?php if($activePage=='dashboard') echo 'bg-white/10 text-white'; else echo 'text-white/70 hover:bg-white/5'; ?>">
            <span class="text-lg">📊</span>
            <span class="font-medium">Dashboard</span>
        </a>

        <!-- Orders -->
        <a href="/admin/orders.php"
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all
           <?php if($activePage=='orders') echo 'bg-white/10 text-white'; else echo 'text-white/70 hover:bg-white/5'; ?>">
            <span class="text-lg">🧾</span>
            <span class="font-medium">Bestellingen</span>
        </a>

        <!-- Products -->
        <a href="/admin/products.php"
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all
           <?php if($activePage=='products') echo 'bg-white/10 text-white'; else echo 'text-white/70 hover:bg-white/5'; ?>">
            <span class="text-lg">🍔</span>
            <span class="font-medium">Producten</span>
        </a>

        <!-- Categories -->
        <a href="/admin/categories.php"
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all
           <?php if($activePage=='categories') echo 'bg-white/10 text-white'; else echo 'text-white/70 hover:bg-white/5'; ?>">
            <span class="text-lg">📦</span>
            <span class="font-medium">Categorieën</span>
        </a>

        <!-- Extras -->
        <a href="/admin/extras.php"
           class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all
           <?php if($activePage=='extras') echo 'bg-white/10 text-white'; else echo 'text-white/70 hover:bg-white/5'; ?>">
            <span class="text-xl">➕</span>
            <span class="font-medium">Extras (Toppings)</span>
        </a>

    </nav>

    <!-- LOGOUT -->
    <div class="px-4 py-6 border-t border-white/10">
        <a href="/admin/logout.php"
           class="block text-red-400 font-semibold hover:text-red-300 transition">
            Uitloggen
        </a>
    </div>

</aside>
