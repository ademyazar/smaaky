<?php
require_once 'config.php';
require_once 'admin/_functions.php';

// Store status
$isStoreOpen = isStoreOpen();
$isDeliveryPaused = isDeliveryPaused();
$isPickupPaused = isPickupPaused();

// Cart data from session
$cart = $_SESSION['cart'] ?? [];
$cartTotal = calculateCartTotal($cart);
$deliveryFee = getDeliveryFee();

// If cart is empty, redirect to index
if (empty($cart)) {
    header("Location: index.php");
    exit;
}

// Default form values
$name = $_SESSION['customer_name'] ?? '';
$phone = $_SESSION['customer_phone'] ?? '';
$email = $_SESSION['customer_email'] ?? '';
$address = $_SESSION['customer_address'] ?? '';
$houseno = $_SESSION['customer_houseno'] ?? '';
$zip = $_SESSION['customer_zip'] ?? '';
$note = $_SESSION['customer_note'] ?? '';

// Handle coupon data (if any)
$coupon = $_SESSION['coupon'] ?? null;
$discountAmount = 0;
if ($coupon) {
    $discountAmount = calculateDiscount($cartTotal, $coupon);
}

// Calculate final total
$finalTotal = $cartTotal + $deliveryFee - $discountAmount;
if ($finalTotal < 0) $finalTotal = 0;

// Payment methods array - SADECE MOLLIE YÖNTEMLERİ
$paymentMethods = [
    // mollie_method anahtarı, api/create_order.php'ye Mollie metodunu bildirir
    'ideal' => ['label' => 'iDEAL', 'icon' => 'credit-card', 'class' => 'online', 'mollie_method' => 'ideal'],
    'creditcard' => ['label' => 'Credit Card', 'icon' => 'credit-card', 'class' => 'online', 'mollie_method' => 'creditcard'],
    'klarna' => ['label' => 'Klarna Pay Now', 'icon' => 'banknote', 'class' => 'online', 'mollie_method' => 'klarnapaynow'], // Mollie'de Klarna Pay Now'ın karşılığıdır
];
// Default selected payment method
$selectedPayment = $_SESSION['payment_method'] ?? 'ideal';
$selectedDelivery = $_SESSION['delivery_type'] ?? 'delivery';

// --- HTML START ---
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Afrekenen - Smaaky</title>
    <!-- Tailwind CSS ve Lucide Icons index.php'den zaten yüklü -->
</head>
<body class="text-neutral-900 pb-24 md:pb-0">

<!-- Bu dosya, index.php'nin gövdesine entegre olduğu için sadece div içeriğini döndürüyoruz. -->
<!-- NOT: Checkout sayfasının HTML yapısı, index.php'deki 'view-checkout' div'i ile uyumlu olmalıdır. -->

<div id="view-checkout" class="max-w-lg mx-auto p-4 pt-6 min-h-screen fade-in bg-white">
    <button onclick="app.showMenu()" class="mb-6 flex items-center gap-2 text-sm font-bold text-gray-500 hover:text-black transition-colors">
        <div class="bg-gray-100 p-2 rounded-full"><i data-lucide="chevron-left" class="w-4 h-4"></i></div>
        Terug naar menu
    </button>
    
    <div class="bg-white rounded-3xl">
        <h2 class="text-3xl font-black mb-6 tracking-tight">Bestelling Afronden</h2>
        
        <!-- Teslimat Tipi Seçimi -->
        <div id="delivery-tab-container" class="flex bg-gray-100 p-1 rounded-xl mb-6">
            <?php if(isset($settings['delivery_open']) && $settings['delivery_open']): ?>
            <button onclick="app.setDeliveryType('delivery')" id="tab-delivery" class="flex-1 py-3 rounded-lg text-sm font-bold transition-all flex items-center justify-center gap-2 shadow-sm bg-white text-black">
                <i data-lucide="bike" class="w-4 h-4"></i> Bezorgen
            </button>
            <?php endif; ?>
            
            <?php if(isset($settings['pickup_open']) && $settings['pickup_open']): ?>
            <button onclick="app.setDeliveryType('pickup')" id="tab-pickup" class="flex-1 py-3 rounded-lg text-sm font-bold transition-all flex items-center justify-center gap-2 text-gray-500 hover:text-black">
                <i data-lucide="shopping-bag" class="w-4 h-4"></i> Afhalen
            </button>
            <?php endif; ?>
        </div>

        <form onsubmit="app.submitOrder(event)" class="space-y-6">
            <!-- Kişisel Bilgiler -->
            <div class="space-y-4">
                <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">Jouw gegevens</h3>
                <input required name="name" id="input-name" value="<?= htmlspecialchars($name) ?>" class="w-full p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 transition-all font-medium" placeholder="Naam">
                <input required name="phone" id="input-phone" value="<?= htmlspecialchars($phone) ?>" type="tel" class="w-full p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 transition-all font-medium" placeholder="Telefoonnummer">
                <input type="email" name="email" id="input-email" value="<?= htmlspecialchars($email) ?>" class="w-full p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 transition-all font-medium" placeholder="E-mail (optioneel)">
            </div>

            <!-- Adres Bilgileri -->
            <div id="address-section" class="space-y-4">
                <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide mt-2">Bezorgadres</h3>
                <div class="grid grid-cols-3 gap-3">
                    <input name="zip" id="input-zip" value="<?= htmlspecialchars($zip) ?>" class="col-span-1 p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 font-medium" placeholder="Postcode" maxlength="6">
                    <input name="houseno" id="input-houseno" value="<?= htmlspecialchars($houseno) ?>" class="col-span-2 p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 font-medium" placeholder="Huisnummer">
                </div>
                <input name="address" id="input-address" value="<?= htmlspecialchars($address) ?>" class="w-full p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 font-medium" placeholder="Straatnaam">
                <input name="note" id="input-note" value="<?= htmlspecialchars($note) ?>" class="w-full p-4 bg-gray-50 rounded-xl outline-none focus:ring-2 focus:ring-black border border-transparent focus:border-gray-200 font-medium" placeholder="Notitie (bel werkt niet, etc.)">
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

            <!-- Ödeme Yöntemi (SADECE MOLLIE) -->
            <div class="space-y-3 pt-2">
                <h3 class="font-bold text-gray-800 text-sm uppercase tracking-wide">Betaalmethode (Online)</h3>
                <div class="space-y-3" id="payment-methods-container">
                    <?php 
                    $isFirst = true;
                    foreach ($paymentMethods as $key => $method): 
                        $isChecked = $key === $selectedPayment;
                    ?>
                    <label class="flex items-center p-4 border rounded-xl cursor-pointer transition-all has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50 has-[:checked]:shadow-sm">
                        <input type="radio" 
                               name="payment_method" 
                               value="<?= htmlspecialchars($method['mollie_method']) ?>" 
                               class="w-5 h-5 accent-orange-600" 
                               <?= $isFirst ? 'checked' : '' ?>
                               onchange="app.setPaymentMethod('<?= htmlspecialchars($method['mollie_method']) ?>')">
                        <span class="ml-3 font-bold flex items-center gap-2">
                            <i data-lucide="<?= htmlspecialchars($method['icon']) ?>" class="w-5 h-5"></i> 
                            <?= htmlspecialchars($method['label']) ?>
                        </span>
                    </label>
                    <?php 
                    $isFirst = false;
                    endforeach; 
                    ?>
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
<?php 
// --- HTML END ---
?>