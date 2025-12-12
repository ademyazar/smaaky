<?php
// PHP Error Reporting (Prodüksiyonda kapalı tutulur)
// 500 hatasını önlemek için hatayı ekrana yazdırmayı kapalı tutuyoruz
ini_set('display_errors', 0);
error_reporting(0);

// --- 1. GEREKLİ DOSYALARI YÜKLEME SIRASI (KRİTİK) ---

// 1.1. Temel Yapılandırma (KRİTİK VE TEK ZORUNLU YÜKLEME)
if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    // Kritik Hata: Uygulama config.php olmadan çalışamaz
    die("Kritik Hata: config.php dosyası bulunamıyor.");
}

// --- GEREKLİ DOSYALAR BİTTİ ---


// 2. AYARLARI ÇEK
// Bu kısım config.php'nin $pdo değişkenini içerdiğini varsayar.
$settings = [];
if (isset($pdo)) {
    try {
        $settingsRows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_ASSOC);
        foreach($settingsRows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (PDOException $e) {
        // Veritabanı hatasında varsayılan ayarları kullan
        error_log("Veritabanı Ayar Çekme Hatası: " . $e->getMessage());
    }
}

// Varsayılanlar
if(!isset($settings['restaurant_open'])) $settings['restaurant_open'] = 1;
if(!isset($settings['delivery_open'])) $settings['delivery_open'] = 1;
if(!isset($settings['pickup_open'])) $settings['pickup_open'] = 1;
if(!isset($settings['cash_enabled'])) $settings['cash_enabled'] = 1; // Cash on Delivery ayarı

// İlk teslimat tipini belirle
$initialDeliveryType = 'delivery';
if ($settings['delivery_open'] == 0 && $settings['pickup_open'] == 1) {
    $initialDeliveryType = 'pickup';
} elseif ($settings['delivery_open'] == 1 && $settings['pickup_open'] == 0) {
    $initialDeliveryType = 'delivery';
} elseif ($settings['delivery_open'] == 0 && $settings['pickup_open'] == 0) {
    $initialDeliveryType = 'none'; 
}

// --- ÖDEME YÖNTEMLERİ TANIMLARI (STATİK SÜRÜM + KAPIDA ÖDEME) ---
$paymentMethods = [
    'ideal'      => ['label' => 'iDEAL', 'icon' => 'credit-card', 'mollie_method' => 'ideal'],
    'creditcard' => ['label' => 'Credit Card', 'icon' => 'credit-card', 'mollie_method' => 'creditcard'],
    'klarna'     => ['label' => 'Klarna Pay Now', 'icon' => 'banknote', 'mollie_method' => 'klarnapaylater'], 
];

// Kapıda Ödeme (Cash on Delivery) etkinse ekle
if ($settings['cash_enabled'] == 1) {
    // Mollie ile ödeme yapılmayacağı için mollie_method'u 'cash' olarak ayarla
    $paymentMethods['cash'] = ['label' => 'Contant bij bezorging', 'icon' => 'wallet', 'mollie_method' => 'cash'];
}
// --- BİTTİ ---

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smaaky - Lekker en Snel</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        body { font-family: 'Inter', sans-serif; background-color: #fafafa; }
        .loader { border: 3px solid #f3f3f3; border-top: 3px solid #fff; border-radius: 50%; width: 20px; height: 20px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .fade-in { animation: fadeIn 0.3s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        /* Yeni Stil: Header'ı daha keskin ve ortalanmış yap */
        .header-content-wrapper {
            max-width: 1200px; /* Maksimum genişlik */
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 0 1rem; /* Yan boşluklar */
        }
    </style>
    <!-- Admin Ayarlarını JS'e Aktar -->
    <script>
        window.SITE_SETTINGS = <?php echo json_encode($settings); ?>;
        window.INITIAL_DELIVERY_TYPE = '<?php echo $initialDeliveryType; ?>';
    </script>
</head>
<body class="text-neutral-900 pb-24 md:pb-0">

    <!-- KAPALI UYARISI (Header Üstü, Z-index 70'e çekildi) -->
    <div id="closed-banner" class="hidden bg-red-600 text-white text-center py-3 px-4 text-sm font-bold sticky top-0 z-[70] shadow-md flex justify-center items-center gap-2">
        <i data-lucide="clock" width="18"></i>
        <span>Helaas zijn we gesloten. U kunt het menu bekijken, maar niet bestellen. Openingstijden: 12:00 - 23:00</span>
    </div>

    <!-- Header (Sticky, Top 0'dan başlıyor ve Banner yüksekliği kadar aşağı itilecek) -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur shadow-sm border-b border-gray-100 py-4 transition-all" id="main-header">
        <div class="header-content-wrapper">
            <!-- Logo Alanı -->
            <div class="flex items-center gap-3 cursor-pointer" onclick="app.showMenu()">
                <!-- Logo Görseli -->
                <img src="" class="h-10 w-auto object-contain" onerror="this.style.display='none'; document.getElementById('logo-text').style.display='block'">
                <!-- Logo Metni (Görsel hata verirse görünür) -->
                <span id="logo-text" class="hidden font-black text-2xl italic tracking-tighter">SMAAKY SMASHBURGERS</span>
            </div>
            
            <!-- Sepet Butonu -->
            <button onclick="app.toggleCart(true)" class="relative p-2 hover:bg-orange-50 rounded-full transition-colors group">
                <i data-lucide="shopping-bag" class="text-gray-800 group-hover:text-orange-600 transition-colors"></i>
                <span id="cart-badge" class="hidden absolute top-0 right-0 bg-orange-600 text-white text-[10px] font-bold w-5 h-5 flex items-center justify-center rounded-full border-2 border-white shadow-sm">0</span>
            </button>
        </div>
    </header>

    <!-- Yükleniyor Ekranı -->
    <div id="loading-screen" class="fixed inset-0 bg-white z-50 flex flex-col items-center justify-center text-center p-4">
        <div class="w-10 h-10 border-4 border-gray-200 border-t-black rounded-full animate-spin mb-4"></div>
        <p id="loading-text" class="text-gray-500 font-medium">Menu wordt geladen...</p>
    </div>

    <!-- ANA SAYFA (MENÜ) -->
    <div id="view-menu" class="hidden">
        <!-- Hero Banner -->
        <div class="relative h-[250px] md:h-[350px] bg-black overflow-hidden">
            <img src="assets/img/hero-bg.jpg" onerror="this.src='https://images.unsplash.com/photo-1547584370-2cc98b8b8dc8?auto=format&fit=crop&w=1600&q=80'" class="w-full h-full object-cover opacity-60">
            <div class="absolute inset-0 bg-gradient-to-t from-neutral-900 via-transparent to-transparent"></div>
            <div class="absolute bottom-0 left-0 right-0 p-6 md:p-12 text-white">
                <div id="store-status-pill" class="inline-flex items-center gap-2 px-3 py-1 text-xs font-bold uppercase tracking-wider rounded mb-4 bg-green-600 text-white">
                    <i data-lucide="clock" width="14"></i> Nu Open
                </div>
                <h2 class="text-4xl md:text-6xl font-black mb-3 tracking-tighter italic leading-none">
                    SMAAKY <span class="text-orange-500">VIBES.</span>
                </h2>
            </div>
        </div>

        <!-- Kategoriler -->
        <div class="sticky top-[73px] z-30 bg-white border-b border-gray-100 shadow-sm overflow-x-auto no-scrollbar py-3 px-4" id="cat-bar">
            <div class="flex gap-2 md:justify-center min-w-max mx-auto max-w-5xl" id="category-container"></div>
        </div>

        <!-- Ürünler -->
        <main class="max-w-6xl mx-auto p-4 md:p-8 min-h-screen">
            <div id="products-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8"></div>
        </main>
    </div>

    <!-- CHECKOUT SAYFASI -->
    <div id="view-checkout" class="hidden max-w-lg mx-auto p-4 pt-6 min-h-screen fade-in bg-white">
        <button onclick="app.showMenu()" class="mb-6 flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-black transition-colors">
            <div class="bg-gray-100 p-2 rounded-full"><i data-lucide="chevron-left" class="w-4 h-4"></i></div>
            Terug naar menu
        </button>
        
        <div class="bg-white rounded-3xl">
            <h2 class="text-3xl font-black mb-6 tracking-tight">Bestelling Afronden</h2>
            
            <!-- Teslimat Tipi Seçimi -->
            <div id="delivery-tab-container" class="flex bg-gray-100 p-1 rounded-xl mb-6">
                <?php if($settings['delivery_open']): ?>
                <button onclick="app.setDeliveryType('delivery')" id="tab-delivery" class="flex-1 py-3 rounded-lg text-sm font-bold transition-all flex items-center justify-center gap-2 shadow-sm bg-white text-black">
                    <i data-lucide="bike" class="w-4 h-4"></i> Bezorgen
                </button>
                <?php endif; ?>
                
                <?php if($settings['pickup_open']): ?>
                <button onclick="app.setDeliveryType('pickup')" id="tab-pickup" class="flex-1 py-3 rounded-lg text-sm font-bold transition-all flex items-center justify-center gap-2 text-gray-500 hover:text-black">
                    <i data-lucide="shopping-bag" class="w-4 h-4"></i> Afhalen
                </button>
                <?php endif; ?>
            </div>

            <form onsubmit="app.submitOrder(event)" class="space-y-6">
                <!-- Kişisel Bilgiler -->
                <div class="space-y-4">
                    <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">Jouw gegevens</h3>
                    <input required name="name" id="input-name" class="w-full p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 transition-all font-medium" placeholder="Naam">
                    <input required name="phone" id="input-phone" type="tel" class="w-full p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 transition-all font-medium" placeholder="Telefoonnummer">
                    <input type="email" name="email" id="input-email" class="w-full p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 transition-all font-medium" placeholder="E-mail (optioneel)">
                </div>

                <!-- Adres Bilgileri -->
                <div id="address-section" class="space-y-4">
                    <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide mt-2">Bezorgadres</h3>
                    <div class="grid grid-cols-3 gap-3">
                        <input name="zip" id="input-zip" class="col-span-1 p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 font-medium" placeholder="Postcode" maxlength="6">
                        <input name="houseno" id="input-houseno" class="col-span-2 p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 font-medium" placeholder="Huisnummer">
                    </div>
                    <input name="address" id="input-address" class="w-full p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 font-medium" placeholder="Straatnaam">
                    <input name="note" id="input-note" class="w-full p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 font-medium" placeholder="Notitie (bel werkt niet, etc.)">
                </div>

                <!-- Kupon Kodu Alanı -->
                <div class="space-y-3 pt-2">
                    <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">Kortingscode</h3>
                    <div class="flex gap-2">
                        <input type="text" id="coupon-code-input" class="flex-1 p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 uppercase font-bold tracking-wide placeholder:font-normal" placeholder="KORTINGSCODE">
                        <button type="button" onclick="app.applyCoupon()" id="coupon-apply-btn" class="shrink-0 bg-black text-white px-6 rounded-xl font-bold hover:bg-gray-800 transition-colors disabled:bg-gray-400">
                            Toepassen
                        </button>
                    </div>
                    <div id="coupon-message" class="text-sm font-medium pt-1"></div>
                </div>

                <!-- Ödeme Yöntemi (STATİK DÖNGÜ) -->
                <div class="space-y-3 pt-2">
                    <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">Betaalmethode</h3>
                    <div class="space-y-3" id="payment-methods-container">
                        <?php 
                        $isFirst = true;
                        foreach ($paymentMethods as $key => $method): 
                            // Kapıda ödeme, sadece teslimat açıksa ve seçili değilse ilk seçenek olamaz
                            $isChecked = $isFirst && ($method['mollie_method'] !== 'cash' || $settings['cash_enabled'] == 0);
                        ?>
                        <label class="flex items-center p-4 border rounded-xl cursor-pointer transition-all has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50 has-[:checked]:shadow-sm">
                            <input type="radio" 
                                   name="payment_method" 
                                   value="<?= htmlspecialchars($method['mollie_method']) ?>" 
                                   class="w-5 h-5 accent-orange-600" 
                                   <?= $isChecked ? 'checked' : '' ?>
                                   onchange="app.setPaymentMethod('<?= htmlspecialchars($method['mollie_method']) ?>')">
                            <span class="ml-3 font-bold flex items-center gap-2">
                                <i data-lucide="<?= htmlspecialchars($method['icon']) ?>" class="w-5 h-5"></i> 
                                <?= htmlspecialchars($method['label']) ?>
                            </span>
                        </label>
                        <?php 
                        if ($isChecked) $isFirst = false;
                        endforeach; 
                        ?>
                        <?php if (empty($paymentMethods)): ?>
                            <div class="p-4 bg-red-100 text-red-800 rounded-xl font-medium">
                                Fout: Kon geen betaalmethoden laden. Controleer Mollie API sleutel in Admin Instellingen.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Özet -->
                <div class="pt-6 border-t border-gray-100 space-y-3 text-sm bg-gray-50/50 p-6 rounded-2xl">
                    <div class="flex justify-between text-gray-600 font-medium"><span>Subtotaal</span><span id="checkout-subtotal">€0.00</span></div>
                    <div class="flex justify-between text-gray-600 font-medium"><span>Bezorgkosten</span><span id="checkout-delivery">€0.00</span></div>
                    <div id="checkout-discount-row" class="hidden flex justify-between text-green-600 font-bold"><span>Korting</span><span id="checkout-discount">-€0.00</span></div>
                    <div class="flex justify-between font-black text-2xl pt-4 border-t border-dashed border-gray-300 mt-2 text-gray-900"><span>Totaal</span><span id="checkout-total">€0.00</span></div>
                </div>

                <button type="submit" id="checkout-submit-btn" class="w-full bg-black text-white py-5 rounded-xl font-black text-lg shadow-xl hover:bg-gray-900 hover:scale-[1.01] active:scale-[0.99] transition-all flex justify-center items-center gap-3">
                    <span>Veilig Betalen & Bestellen</span> <i data-lucide="lock" class="w-5 h-5"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- CHECKOUT MODAL -->
<div id="checkout-modal" class="checkout-modal-backdrop">
    <div class="checkout-modal">
        <button id="checkout-close" class="checkout-close-btn">×</button>

        <h2>Bestelling afronden</h2>
        <p>Vul je gegevens in en bevestig je bestelling.</p>

        <div id="checkout-error" class="checkout-error" style="display:none;"></div>

        <form id="checkout-form">

            <label>Naam*</label>
            <input type="text" name="name" required>

            <label>Telefoonnummer*</label>
            <input type="text" name="phone" required>

            <label>E-mail*</label>
            <input type="email" name="email" required>

            <label>Straat + huisnummer*</label>
            <input type="text" name="street_house" required>

            <label>Postcode*</label>
            <input type="text" name="zip" required>

            <label>Plaats*</label>
            <input type="text" name="city" required>

            <label>Opmerking (optioneel)</label>
            <textarea name="note"></textarea>

            <div class="checkout-total-block">
                <div>Subtotaal: <span id="co-subtotal">€0,00</span></div>
                <div>Bezorgkosten: <span id="co-delivery-fee">€0,00</span></div>
                <div class="co-total-row">Totaal: <span id="co-total">€0,00</span></div>
            </div>

            <button id="checkout-submit" type="submit" class="checkout-submit-btn">
                Bestelling plaatsen
            </button>

        </form>
    </div>
</div>

    <!-- CART SIDEBAR -->
    <div id="cart-sidebar" class="hidden fixed inset-0 z-50 flex justify-end isolate">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="app.toggleCart(false)"></div>
        <div class="relative w-full max-w-md bg-white h-full shadow-2xl flex flex-col transform transition-transform duration-300 translate-x-full" id="cart-panel">
            <div class="p-5 border-b bg-white flex justify-between items-center shrink-0">
                <h2 class="font-black text-2xl italic tracking-tight flex items-center gap-2"><i data-lucide="shopping-bag" class="w-6 h-6"></i> BESTELLING</h2>
                <button onclick="app.toggleCart(false)" class="p-2 bg-gray-100 rounded-full hover:bg-gray-200"><i data-lucide="x"></i></button>
            </div>
            <div id="cart-items" class="flex-1 overflow-y-auto p-5 space-y-4 bg-gray-50/50"></div>
            <div class="p-6 bg-white border-t border-gray-100 shadow-[0_-4px_20px_rgba(0,0,0,0.05)]">
                <div class="space-y-2 mb-4 text-sm font-medium">
                    <div class="flex justify-between text-gray-500"><span>Subtotaal</span><span id="cart-subtotal">€0.00</span></div>
                    <div class="flex justify-between text-gray-500"><span>Bezorgkosten</span><span id="cart-delivery-fee">€0.00</span></div>
                    <div id="cart-discount-row" class="hidden flex justify-between text-green-600 font-bold"><span>Korting</span><span id="cart-discount">-€0.00</span></div>
                    <div class="flex justify-between font-black text-xl pt-2 border-t border-gray-100 text-gray-900"><span>Totaal</span><span id="cart-total">€0.00</span></div>
                </div>
                <button onclick="app.goToCheckout()" id="btn-checkout" class="w-full bg-orange-600 text-white py-4 rounded-xl font-black text-lg hover:bg-orange-700 transition-all disabled:bg-gray-300 flex justify-center items-center gap-2 shadow-lg shadow-orange-200">
                    Afrekenen <i data-lucide="arrow-right" class="w-5 h-5"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- MOBIL STICKY CART -->
    <div id="mobile-sticky-cart" class="hidden fixed bottom-6 left-4 right-4 z-40 md:hidden">
        <button onclick="app.toggleCart(true)" class="w-full bg-black text-white py-4 px-6 rounded-2xl shadow-2xl flex justify-between items-center font-bold text-lg hover:scale-[1.02] transition-transform">
            <div class="flex items-center gap-3">
                <span class="bg-orange-600 text-white text-xs w-6 h-6 rounded-full flex items-center justify-center border-2 border-black" id="mobile-badge">0</span>
                <span>Bekijk bestelling</span>
            </div>
            <span id="mobile-total">€0.00</span>
        </button>
    </div>

    <script>
        const CONFIG = {
            deliveryFee: 2.50,
            freeThreshold: 20.00,
            apiMenu: 'api/menu.php', 
            apiOrder: 'api/create_order.php', 
            apiCoupon: 'api/apply_coupon.php',
            placeholderImg: 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=800&q=80'
        };

        const app = {
            data: {
                categories: [], products: [], extras: {}, cart: [],
                activeCategory: null, isStoreOpen: true, modalProduct: null,
                selectedExtras: [], deliveryType: window.INITIAL_DELIVERY_TYPE || 'delivery', 
                paymentMethod: 'ideal', settings: window.SITE_SETTINGS || {},
                appliedCoupon: null, discountAmount: 0.00
            },

            async init() {
                this.data.isStoreOpen = this.data.settings.restaurant_open == 1;
                this.updateStoreStatusUI();
                
                // Kapıda ödeme varsayılan olabilir
                this.data.paymentMethod = this.data.settings.cash_enabled == 1 ? 'cash' : 'ideal';
                
                this.setDeliveryType(this.data.deliveryType);

                try {
                    const response = await fetch(CONFIG.apiMenu);
                    const json = await response.json();
                    if(json.status === 'success') {
                        this.data.categories = json.categories;
                        this.data.products = json.products.filter(p => p.is_active == 1); 
                        this.data.extras = json.extras_by_product;
                        if(this.data.categories.length > 0) this.data.activeCategory = 'all';
                        
                        document.getElementById('loading-screen').classList.add('hidden');
                        document.getElementById('view-menu').classList.remove('hidden');
                        this.renderCategories();
                        this.renderProducts();
                        lucide.createIcons();
                    }
                } catch (e) { console.error(e); }
                
                // Header'ı yeniden konumlandır
                this.adjustStickyHeader();
            },
            
            adjustStickyHeader() {
                const header = document.getElementById('main-header');
                const banner = document.getElementById('closed-banner');
                const catBar = document.getElementById('cat-bar');
                
                if (!banner.classList.contains('hidden')) {
                    // Banner varsa, header'ı banner'ın hemen altına ayarla
                    header.style.top = banner.offsetHeight + 'px';
                    // Kategori barını header'ın altına ayarla
                    catBar.style.top = (banner.offsetHeight + header.offsetHeight) + 'px';
                } else {
                    // Banner yoksa, header'ı en üste ayarla
                    header.style.top = '0px';
                    // Kategori barını header'ın altına ayarla (Yaklaşık 73px)
                    catBar.style.top = header.offsetHeight + 'px';
                }
            },

            updateStoreStatusUI() {
                const banner = document.getElementById('closed-banner');
                const pill = document.getElementById('store-status-pill');
                
                if (!this.data.isStoreOpen) {
                    banner.classList.remove('hidden');
                    pill.className = "inline-flex items-center gap-2 px-3 py-1 text-xs font-bold uppercase tracking-wider rounded mb-4 bg-red-600 text-white";
                    pill.innerHTML = '<i data-lucide="lock" width="14"></i> Gesloten';
                } else {
                    banner.classList.add('hidden');
                    pill.className = "inline-flex items-center gap-2 px-3 py-1 text-xs font-bold uppercase tracking-wider rounded mb-4 bg-green-600 text-white";
                    pill.innerHTML = '<i data-lucide="clock" width="14"></i> Nu Open';
                }
                this.adjustStickyHeader(); // Durum değişince header'ı tekrar konumlandır
            },

            renderCategories() {
                const container = document.getElementById('category-container');
                const activeCategories = this.data.categories.filter(cat => 
                    this.data.products.some(p => p.category_id == cat.id)
                );
                let html = `<button onclick="app.setCategory('all')" class="px-5 py-2.5 rounded-full text-sm font-bold transition-all ${this.data.activeCategory === 'all' ? 'bg-black text-white shadow-lg transform scale-105' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}">Alles</button>`;
                activeCategories.forEach(cat => {
                    const isActive = this.data.activeCategory === cat.id;
                    html += `<button onclick="app.setCategory(${cat.id})" class="px-5 py-2.5 rounded-full text-sm font-bold transition-all ${isActive ? 'bg-black text-white shadow-lg transform scale-105' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}">${cat.name}</button>`;
                });
                container.innerHTML = html;
            },

            setCategory(id) {
                this.data.activeCategory = id;
                this.renderCategories();
                this.renderProducts();
            },

            renderProducts() {
                const grid = document.getElementById('products-grid');
                let filtered = this.data.products;
                if (this.data.activeCategory !== 'all') {
                    filtered = this.data.products.filter(p => p.category_id == this.data.activeCategory);
                }

                grid.innerHTML = filtered.map(item => {
                    let imgSrc = item.image_url ? item.image_url : (item.image ? 'assets/img/' + item.image : CONFIG.placeholderImg);
                    let canOrder = this.data.isStoreOpen && item.is_active == 1; 
                    return `
                    <div class="group bg-white rounded-3xl shadow-[0_2px_15px_-3px_rgba(0,0,0,0.07)] border border-gray-100 overflow-hidden flex flex-col h-full hover:shadow-xl transition-all duration-300 ${!canOrder ? 'opacity-70 grayscale' : ''}">
                        <div class="relative h-56 overflow-hidden cursor-pointer" onclick="app.handleItemClick(${item.id})">
                            <img src="${imgSrc}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" onerror="this.src='${CONFIG.placeholderImg}'">
                            ${canOrder ? `<div class="absolute bottom-4 right-4 bg-white text-black w-12 h-12 rounded-full flex items-center justify-center shadow-lg translate-y-16 group-hover:translate-y-0 transition-transform duration-300 z-10"><i data-lucide="plus" width="24"></i></div>` : ''}
                        </div>
                        <div class="p-6 flex flex-col flex-1">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-black text-xl tracking-tight leading-tight pr-2">${item.name}</h4>
                                <span class="font-bold text-lg bg-gray-50 text-gray-900 px-3 py-1 rounded-lg">€${parseFloat(item.price).toFixed(2)}</span>
                            </div>
                            <p class="text-gray-500 text-sm leading-relaxed mb-6 line-clamp-2 flex-1">${item.description || ''}</p>
                            <button onclick="app.handleItemClick(${item.id})" class="w-full py-3.5 mt-auto font-bold rounded-xl transition-all flex items-center justify-center gap-2 ${canOrder ? 'bg-gray-100 hover:bg-black hover:text-white text-black' : 'bg-gray-100 text-gray-400 cursor-not-allowed'}" ${!canOrder ? 'disabled' : ''}>
                                ${canOrder ? '<i data-lucide="plus" width="18"></i> Toevoegen' : '<i data-lucide="lock" width="18"></i> Gesloten'}
                            </button>
                        </div>
                    </div>`;
                }).join('');
                lucide.createIcons();
            },

            handleItemClick(id) {
                if(!this.data.isStoreOpen) return alert("Helaas zijn we gesloten.");
                const product = this.data.products.find(p => p.id == id);
                const productExtras = this.data.extras[id];
                if (productExtras && productExtras.length > 0) this.openModal(product, productExtras);
                else this.addToCart(product, []);
            },

            openModal(product, extras) {
                this.data.modalProduct = product;
                this.data.selectedExtras = [];
                let imgSrc = product.image_url ? product.image_url : (product.image ? 'assets/img/' + product.image : CONFIG.placeholderImg);
                
                document.getElementById('modal-img').src = imgSrc;
                document.getElementById('modal-title').innerText = product.name;
                document.getElementById('modal-price').innerText = `€${parseFloat(product.price).toFixed(2)}`;
                
                document.getElementById('modal-extras').innerHTML = extras.map(ex => `
                    <div onclick="app.toggleExtra(${ex.id}, ${ex.price})" id="extra-${ex.id}" class="flex items-center justify-between p-4 rounded-xl border border-gray-100 cursor-pointer transition-all hover:bg-gray-50">
                        <div class="flex items-center gap-3">
                            <div class="check-box w-6 h-6 rounded-md border-2 border-gray-300 bg-white flex items-center justify-center transition-colors"></div>
                            <span class="font-bold text-gray-700">${ex.name}</span>
                        </div>
                        <span class="text-sm font-bold text-gray-500">+€${parseFloat(ex.price).toFixed(2)}</span>
                    </div>
                `).join('');
                
                document.getElementById('modal-product').classList.remove('hidden');
                this.updateModalTotal();
            },

            toggleExtra(id, price) {
                const index = this.data.selectedExtras.findIndex(e => e.id == id);
                const el = document.getElementById(`extra-${id}`);
                const checkBox = el.querySelector('.check-box');

                if (index > -1) {
                    this.data.selectedExtras.splice(index, 1);
                    el.classList.remove('border-orange-500', 'bg-orange-50');
                    checkBox.classList.remove('bg-orange-600', 'border-orange-600');
                    checkBox.innerHTML = '';
                } else {
                    const exName = this.data.extras[this.data.modalProduct.id].find(e => e.id == id).name;
                    this.data.selectedExtras.push({ id, price, name: exName });
                    el.classList.add('border-orange-500', 'bg-orange-50');
                    checkBox.classList.add('bg-orange-600', 'border-orange-600');
                    checkBox.innerHTML = '<i data-lucide="check" class="text-white w-4 h-4"></i>';
                    lucide.createIcons();
                }
                this.updateModalTotal();
            },

            updateModalTotal() {
                let total = parseFloat(this.data.modalProduct.price);
                this.data.selectedExtras.forEach(e => total += parseFloat(e.price));
                document.getElementById('modal-total-btn').innerText = `€${total.toFixed(2)}`;
            },

            addModalToCart() {
                this.addToCart(this.data.modalProduct, this.data.selectedExtras);
                this.closeModal();
            },

            closeModal() {
                document.getElementById('modal-product').classList.add('hidden');
                this.data.modalProduct = null;
            },

            addToCart(product, extras) {
                const cartId = `${product.id}-${extras.map(e => e.id).sort().join('-')}`;
                const extrasTotal = extras.reduce((sum, e) => sum + parseFloat(e.price), 0);
                const finalPrice = parseFloat(product.price) + extrasTotal;

                const existing = this.data.cart.find(c => c.cartId === cartId);
                if (existing) { existing.qty++; } 
                else { this.data.cart.push({ ...product, cartId, qty: 1, selectedExtras: extras, finalPrice }); }
                
                this.data.appliedCoupon = null; this.data.discountAmount = 0.00;
                this.updateCartUI();
                this.toggleCart(true);
            },

            removeFromCart(cartId) {
                const idx = this.data.cart.findIndex(c => c.cartId === cartId);
                if (idx > -1) {
                    if (this.data.cart[idx].qty > 1) this.data.cart[idx].qty--;
                    else this.data.cart.splice(idx, 1);
                }
                this.data.appliedCoupon = null; this.data.discountAmount = 0.00;
                this.updateCartUI();
            },

            toggleCart(show) {
                const sidebar = document.getElementById('cart-sidebar');
                const panel = document.getElementById('cart-panel');
                if (show) {
                    sidebar.classList.remove('hidden');
                    setTimeout(() => panel.classList.remove('translate-x-full'), 10);
                } else {
                    panel.classList.add('translate-x-full');
                    setTimeout(() => sidebar.classList.add('hidden'), 300);
                }
            },

            setDeliveryType(type) {
                this.data.deliveryType = type;
                const tabD = document.getElementById('tab-delivery');
                const tabP = document.getElementById('tab-pickup');
                const addr = document.getElementById('address-section');
                const inputs = addr ? addr.querySelectorAll('input') : [];
                
                if(tabD) {
                    if(type === 'delivery') {
                        tabD.className = "flex-1 py-3 rounded-lg text-sm font-bold transition-all flex items-center justify-center gap-2 shadow-sm bg-white text-black";
                        if(tabP) tabP.className = "flex-1 py-3 rounded-lg text-sm font-bold transition-all flex items-center justify-center gap-2 text-gray-500 hover:text-black";
                        if(addr) { addr.classList.remove('hidden'); inputs.forEach(i => i.required = true); document.getElementById('input-note').required = false; }
                    } else {
                        if(tabP) tabP.className = "flex-1 py-3 rounded-lg text-sm font-bold transition-all flex items-center justify-center gap-2 shadow-sm bg-white text-black";
                        tabD.className = "flex-1 py-3 rounded-lg text-sm font-bold transition-all flex items-center justify-center gap-2 text-gray-500 hover:text-black";
                        if(addr) { addr.classList.add('hidden'); inputs.forEach(i => i.required = false); }
                    }
                }
                this.updateCartUI();
            },

            setPaymentMethod(method) { this.data.paymentMethod = method; },
            
            async applyCoupon() {
                const input = document.getElementById('coupon-code-input');
                const btn = document.getElementById('coupon-apply-btn');
                const msg = document.getElementById('coupon-message');
                const code = input.value.trim().toUpperCase();
                
                if (!code) return;
                btn.disabled = true; msg.innerHTML = 'Controleren...';
                
                try {
                    const cartData = this.data.cart.map(i => ({ product_id: i.id, price: i.finalPrice, qty: i.qty }));
                    const res = await fetch(CONFIG.apiCoupon, { method: 'POST', body: JSON.stringify({ code, cart: cartData }) });
                    const json = await res.json();
                    
                    if (json.status === 'success') {
                        this.data.appliedCoupon = json.coupon;
                        this.data.discountAmount = parseFloat(json.discount);
                        msg.className = 'text-sm font-bold text-green-600 mt-2 flex items-center gap-1';
                        msg.innerHTML = '<i data-lucide="check" class="w-4 h-4"></i> Code toegepast!';
                    } else {
                        this.data.appliedCoupon = null; this.data.discountAmount = 0.00;
                        msg.className = 'text-sm font-bold text-red-500 mt-2';
                        msg.innerHTML = json.message || 'Ongeldige code';
                    }
                } catch (e) { console.error(e); } 
                finally { btn.disabled = false; this.updateCartUI(); lucide.createIcons(); }
            },

            updateCartUI() {
                const totalQty = this.data.cart.reduce((a, b) => a + b.qty, 0);
                const badge = document.getElementById('cart-badge');
                badge.innerText = totalQty;
                badge.classList.toggle('hidden', totalQty === 0);

                const list = document.getElementById('cart-items');
                if (this.data.cart.length === 0) {
                    list.innerHTML = `<div class="text-center py-20 opacity-50"><i data-lucide="shopping-cart" class="w-12 h-12 mx-auto mb-3"></i><p>Je winkelwagen is leeg.</p></div>`;
                    document.getElementById('btn-checkout').disabled = true;
                    document.getElementById('mobile-sticky-cart').classList.add('hidden');
                } else {
                    document.getElementById('mobile-sticky-cart').classList.remove('hidden');
                    document.getElementById('mobile-badge').innerText = totalQty;
                    document.getElementById('btn-checkout').disabled = !this.data.isStoreOpen;

                    list.innerHTML = this.data.cart.map(item => {
                        let imgSrc = item.image_url || (item.image ? 'assets/img/' + item.image : CONFIG.placeholderImg);
                        return `
                        <div class="flex gap-4 items-start bg-white p-3 rounded-xl border border-gray-100 shadow-sm">
                            <div class="relative shrink-0 w-16 h-16 rounded-lg overflow-hidden">
                                <img src="${imgSrc}" class="w-full h-full object-cover">
                                <div class="absolute inset-0 bg-black/10"></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-bold text-sm truncate pr-2">${item.name}</h4>
                                    <span class="font-bold text-sm whitespace-nowrap">€${(item.finalPrice * item.qty).toFixed(2)}</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">${item.selectedExtras.map(e => `+ ${e.name}`).join(', ')}</div>
                                <div class="flex items-center gap-3 mt-3">
                                    <div class="flex items-center bg-gray-100 rounded-lg p-0.5">
                                        <button onclick="app.removeFromCart('${item.cartId}')" class="w-7 h-7 flex items-center justify-center hover:bg-white rounded-md transition-colors"><i data-lucide="minus" class="w-3 h-3"></i></button>
                                        <span class="w-6 text-center font-bold text-sm">${item.qty}</span>
                                        <button onclick="app.addToCart({id:${item.id}, price:${item.price}, name:'${item.name}', image:'${item.image}', image_url:'${item.image_url}'}, [])" class="w-7 h-7 flex items-center justify-center hover:bg-white rounded-md transition-colors"><i data-lucide="plus" class="w-3 h-3"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    }).join('');
                    lucide.createIcons();
                }

                const subTotal = this.data.cart.reduce((a, b) => a + (b.finalPrice * b.qty), 0);
                let fee = (this.data.deliveryType === 'delivery') ? (subTotal >= CONFIG.freeThreshold ? 0 : CONFIG.deliveryFee) : 0;
                const total = subTotal + fee - this.data.discountAmount;
                
                const discRowC = document.getElementById('cart-discount-row');
                const discRowCh = document.getElementById('checkout-discount-row');
                if (this.data.discountAmount > 0) {
                    discRowC.classList.remove('hidden'); discRowCh.classList.remove('hidden');
                    document.getElementById('cart-discount').innerText = `-€${this.data.discountAmount.toFixed(2)}`;
                    document.getElementById('checkout-discount').innerText = `-€${this.data.discountAmount.toFixed(2)}`;
                } else {
                    discRowC.classList.add('hidden'); discRowCh.classList.add('hidden');
                }

                document.getElementById('cart-subtotal').innerText = `€${subTotal.toFixed(2)}`;
                document.getElementById('cart-delivery-fee').innerText = fee === 0 ? 'Gratis' : `€${fee.toFixed(2)}`;
                document.getElementById('cart-total').innerText = `€${total.toFixed(2)}`;
                
                document.getElementById('checkout-subtotal').innerText = `€${subTotal.toFixed(2)}`;
                document.getElementById('checkout-delivery').innerText = fee === 0 ? 'Gratis' : `€${fee.toFixed(2)}`;
                document.getElementById('checkout-total').innerText = `€${total.toFixed(2)}`;
                
                document.getElementById('mobile-total').innerText = `€${total.toFixed(2)}`;
            },

            showMenu() {
                document.getElementById('view-menu').classList.remove('hidden');
                document.getElementById('view-checkout').classList.add('hidden');
                window.scrollTo(0,0);
            },

            goToCheckout() {
                if(!this.data.isStoreOpen) return alert("Gesloten.");
                this.toggleCart(false);
                document.getElementById('view-menu').classList.add('hidden');
                document.getElementById('view-checkout').classList.remove('hidden');
                this.setDeliveryType(this.data.deliveryType);
                window.scrollTo(0,0);
            },

            async submitOrder(e) {
                e.preventDefault();
                const btn = document.getElementById('checkout-submit-btn');
                const form = e.target;
                
                const subTotal = this.data.cart.reduce((a, b) => a + (b.finalPrice * b.qty), 0);
                let fee = (this.data.deliveryType === 'delivery') ? (subTotal >= CONFIG.freeThreshold ? 0 : CONFIG.deliveryFee) : 0;
                
                // Gönderilecek veriyi oluştur
                const rawData = {
                    name: form.name.value, phone: form.phone.value, email: form.email.value,
                    zip: form.zip ? form.zip.value : '', houseno: form.houseno ? form.houseno.value : '',
                    address: form.address ? form.address.value : '', note: form.note ? form.note.value : '',
                    delivery_type: this.data.deliveryType, payment_method: this.data.paymentMethod,
                    cart: this.data.cart, coupon_code: this.data.appliedCoupon?.code,
                    discount_amount: this.data.discountAmount, delivery_fee: fee,
                    total: parseFloat(document.getElementById('checkout-total').innerText.replace('€',''))
                };

                // JSON.stringify ile veriyi güvenli bir şekilde JSON'a dönüştür
                const jsonBody = JSON.stringify(rawData);

                btn.disabled = true;
                btn.innerHTML = '<div class="loader"></div>';

                try {
                    const res = await fetch(CONFIG.apiOrder, {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: jsonBody // Güvenli JSON gövdesi gönderiliyor
                    });
                    const json = await res.json();
                    
                    if (json.success) {
                        if (json.redirect_url) window.location.href = json.redirect_url; // Mollie / Başarı
                        else alert("Bestelling geplaatst!");
                    } else {
                        // Hata mesajını daha anlaşılır hale getir
                        alert(json.message || "Fout bij bestellen. Details: " + JSON.stringify(json));
                        btn.disabled = false; btn.innerHTML = 'Opnieuw proberen';
                    }
                } catch (e) {
                    console.error("JavaScript Fetch Hatası:", e, "Gönderilen Ham Veri:", jsonBody); // KRİTİK DEBUG
                    alert("Verbindingsfout. (JavaScript Hata: Ağ bağlantısı veya JSON parse hatası.)");
                    btn.disabled = false; btn.innerHTML = 'Opnieuw proberen';
                }
            }
        };

        // Pencere yeniden boyutlandığında header ayarını tekrar yap
        window.addEventListener('resize', () => app.adjustStickyHeader());
        document.addEventListener('DOMContentLoaded', () => app.init());
    </script>
</body>
</html>