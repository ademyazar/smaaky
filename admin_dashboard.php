<?php
session_start();
// KRİTİK DÜZELTME 1: Config dosyasını direkt root'tan yüklemeyi dene.
require_once 'config.php';
// KRİTİK DÜZELTME 2: Admin alt klasöründeki dosyaları doğru yolla yükle.
require_once 'admin/_auth.php'; // Yetkilendirme kontrolü
require_once 'admin/_functions.php'; // Yardımcı fonksiyonlar

requireAdmin(); // Admin yetkilendirmesini kontrol et

// --- API: CANLI SİPARİŞ ---
if (isset($_GET['api']) && $_GET['api'] == 'get_live_data') {
    // SADECE PHP JSON ÇIKTISI DÖNDÜRÜLÜR
    header('Content-Type: application/json');

    try {
        $stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 20");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $responseOrders = [];
        foreach ($orders as $order) {
            $orderId = $order['id'];
            // Sipariş öğelerini çek
            $stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmtItems->execute([$orderId]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            // Görüntüleme için toplamı topla
            $totalPrice = array_sum(array_column($items, 'total_price'));
            
            $responseOrders[] = [
                'id' => $orderId,
                'customer_name' => $order['customer_name'],
                'phone' => $order['phone'],
                'email' => $order['email'],
                'zip' => $order['zip'],
                'city' => $order['city'],
                'street' => $order['street'],
                'house_number' => $order['house_number'],
                'delivery_type' => $order['delivery_type'],
                'payment_method' => $order['payment_method'],
                'status' => $order['status'],
                'note' => $order['note'],
                'total_price' => $totalPrice, 
                'delivery_fee' => $order['delivery_fee'],
                'discount_amount' => $order['discount_amount'],
                'coupon_code' => $order['coupon_code'],
                'created_at' => $order['created_at'],
                'items' => $items
            ];
        }

        echo json_encode(['success' => true, 'orders' => $responseOrders]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Veri çekme hatası: ' . $e->getMessage()]);
        exit;
    }
}

// --- API: SİPARİŞ DURUMUNU GÜNCELLE ---
if (isset($_POST['action']) && $_POST['action'] == 'update_status' && isset($_POST['id']) && isset($_POST['status'])) {
    header('Content-Type: application/json');
    $orderId = (int)$_POST['id'];
    $newStatus = $_POST['status'];

    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
        echo json_encode(['success' => true, 'id' => $orderId, 'status' => $newStatus]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Durum güncelleme hatası: ' . $e->getMessage()]);
        exit;
    }
}
// --- API SONU ---


// --- HTML GÖSTERİMİ ---
$pageTitle = 'Live Bestellingen';
$activeMenu = 'live_orders';
// Gereken dosyaları dahil et
// KRİTİK DÜZELTME: Admin alt klasöründeki dosyaları doğru yolla yükle.
require_once 'admin/_header.php'; // Başlık ve başlangıç HTML'si
?>

<div class="page-header">
    <h1 class="page-title">Live Bestellingen (Wachtrij)</h1>
    <p class="page-subtitle">Alle nieuwe bestellingen en hun huidige status.</p>
</div>

<!-- Sipariş Listesi -->
<div id="orders-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <!-- Buraya JS ile sipariş kartları yüklenecek -->
</div>

<!-- Makbuz Yazdırma Alanı (Gizli) -->
<div id="receipt-area" class="hidden"></div>


<!-- JS Kısmı -->
<?php require_once 'admin/_footer.php'; // Kapanış HTML'si ve JS yüklemeleri ?>

<script>
    // --- JS Global Durum ---
    let currentOrders = [];
    let lastKnownOrderId = 0; // Yeni sipariş bildirimi için kullanılır

    // --- Durum Etiketleri ve Stilleri ---
    const statusMap = {
        'new': { label: 'NIEUW', color: 'bg-red-500', next: 'cooking' },
        'pending': { label: 'BETAALAFWACHTING', color: 'bg-yellow-500', next: 'cooking' },
        'cooking': { label: 'BEREIDING', color: 'bg-yellow-500', next: 'delivering' },
        'delivering': { label: 'ONDERWEG', color: 'bg-blue-500', next: 'completed' },
        'completed': { label: 'VOLTOOID', color: 'bg-green-500', next: null },
        'cancelled': { label: 'GEANNULEERD', color: 'bg-gray-500', next: null },
    };

    // --- API FONKSİYONLARI ---

    async function fetchOrders() {
        try {
            const response = await fetch('admin_dashboard.php?api=get_live_data');
            const data = await response.json();

            if (data.success) {
                const newOrders = data.orders.filter(o => o.status !== 'completed' && o.status !== 'cancelled');
                
                // Yeni sipariş bildirimi için kontrol (admin/assets/js/admin.js içinde yapılır)
                // Bu dosya (admin_dashboard.php) sadece veriyi gösterir.
                
                currentOrders = newOrders;
                renderOrders(currentOrders);
            } else {
                console.error("API Hatası:", data.message);
            }
        } catch (error) {
            console.error("Veri çekilirken ağ hatası:", error);
            document.getElementById('orders-list').innerHTML = '<div class="text-red-500 col-span-full p-4 border rounded-xl bg-red-50">Fout bij het laden van bestellingen. Controleer de console.</div>';
        }
    }

    async function updateOrderStatus(id, status) {
        try {
            const response = await fetch('admin_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'update_status',
                    id: id,
                    status: status
                })
            });
            const data = await response.json();

            if (data.success) {
                // Sadece durum değişirse listeyi yenile
                fetchOrders();
            } else {
                alert("Fout bij het bijwerken van de status: " + data.message);
            }
        } catch (error) {
            console.error("Durum güncelleme ağ hatası:", error);
        }
    }

    // --- RENDER FONKSİYONU ---

    function renderOrders(orders) {
        const list = document.getElementById('orders-list');
        list.innerHTML = orders.map(order => {
            const statusInfo = statusMap[order.status] || { label: order.status, color: 'bg-gray-300', next: null };
            const nextStatus = statusInfo.next;
            const nextLabel = nextStatus ? statusMap[nextStatus].label : 'Archiveren';
            
            // Ürün Listesi
            const itemsHtml = order.items.map(item => {
                let extras = '';
                // Ekstra ürünler string formatında gelebilir, JSON parse etmeye gerek yok
                if (item.extras && item.extras.length > 0) {
                   extras = `<div class="text-xs text-gray-500 ml-4">+ ${item.extras}</div>`;
                }

                return `<div class="flex justify-between text-sm mt-1">
                            <span>${item.qty}x ${item.product_name}</span>
                            <span>€${parseFloat(item.total_price).toFixed(2)}</span>
                        </div>${extras}`;
            }).join('');
            
            // Teslimat Adresi
            const addressHtml = order.delivery_type === 'delivery' 
                ? `<div class="text-sm text-gray-700 mt-2">${order.street} ${order.house_number}, ${order.zip} ${order.city}</div>`
                : `<div class="text-sm font-bold mt-2 text-orange-600">AFHALEN (Pick-up)</div>`;

            // Sipariş Notu
            const noteHtml = order.note 
                ? `<div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-gray-800">Note: ${order.note}</div>` 
                : '';

            // İlerleme butonu
            const progressButton = nextStatus ? `
                <button onclick="updateOrderStatus(${order.id}, '${nextStatus}')" class="flex-1 bg-black text-white py-3 rounded-lg font-bold hover:bg-gray-80s0 transition-colors">
                    Volgende Stap: ${nextLabel}
                </button>
            ` : `
                <button onclick="updateOrderStatus(${order.id}, 'completed')" class="flex-1 bg-gray-200 text-gray-600 py-3 rounded-lg font-bold cursor-pointer hover:bg-gray-300 transition-colors">
                    Archiveren
                </button>
            `;


            return `
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 space-y-4">
                    <!-- Başlık ve Durum -->
                    <div class="flex justify-between items-start border-b pb-4">
                        <div>
                            <span class="text-2xl font-black">#${order.id}</span>
                            <span class="text-sm ml-2 font-bold ${statusInfo.color} text-white px-2 py-0.5 rounded-full">${statusInfo.label}</span>
                        </div>
                        <div class="text-xl font-black">€${parseFloat(order.total_price).toFixed(2)}</div>
                    </div>

                    <!-- Müşteri ve Adres -->
                    <div>
                        <div class="font-bold text-lg">${order.customer_name} (${order.phone})</div>
                        ${addressHtml}
                    </div>

                    <!-- Not -->
                    ${noteHtml}

                    <!-- Ürünler -->
                    <div class="border-t border-b py-4 space-y-1">
                        ${itemsHtml}
                    </div>
                    
                    <!-- Eylemler -->
                    <div class="flex gap-2">
                        ${progressButton}
                        <button onclick="printOrder(${order.id})" class="shrink-0 bg-gray-100 text-gray-700 p-3 rounded-lg hover:bg-gray-200 transition-colors" title="Printen">
                            <i data-lucide="printer" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
        
        // Yeni ikonları oluştur
        lucide.createIcons();
    }
    
    // --- YAZDIRMA FONKSİYONU ---
    // Bu fonksiyon, admin_dashboard.php'nin PHP bölümünde (aşağıda) tanımlanmıştır.

    // --- BAŞLANGIÇ ---

    document.addEventListener('DOMContentLoaded', () => {
        // İlk yüklemede siparişleri çek ve 15 saniyede bir güncelle
        fetchOrders();
        setInterval(fetchOrders, 15000); 
    });


    // --- KRİTİK DÜZELTME: Siyah Ekran Kilidini Kaldırmak İçin Eklendi ---
    document.addEventListener('DOMContentLoaded', () => {
        // Hata nedeniyle takılı kalan modal arka planını kaldırmak için 1 saniye bekliyoruz
        setTimeout(() => {
            const overlay = document.querySelector('.fixed.inset-0.bg-black\\/50');
            const hiddenElements = document.querySelectorAll('.hidden-on-load'); // Genellikle yüklenene kadar gizlenen elementler
            
            // Eğer siyah overlay hala varsa, onu kaldır
            if (overlay && overlay.parentElement) {
                // Modalı veya overlay'i tamamen DOM'dan kaldır
                overlay.parentElement.removeChild(overlay); 
                console.warn("DEBUG: Algılanan tam ekran kilidi (overlay) kaldırıldı.");
            }

            // Eğer bir loading spinner varsa onu da kaldır (Görsel temizlik)
            const loadingSpinner = document.getElementById('loading-spinner');
            if(loadingSpinner) loadingSpinner.remove();

            // Lütfen Tarayıcı Konsolunu (F12) açın ve herhangi bir kırmızı renkte PHP veya JS hatası olup olmadığını kontrol edin. 
            // Çözümün anahtarı o hatadadır!
        }, 1000); 
    });

    // --- PHP'den gelen Yazdırma Fonksiyonu ---
    // Not: Bu fonksiyonun tarayıcıda çalışması için PHP etiketi kapatılmalıdır.
</script>

<?php 
    // Yazdırma fonksiyonu (PHP tarafında tanımlanıp JS'e aktarılır)
    // admin_dashboard.php'deki mevcut yazdırma kodu buraya gelir.
    // [Yukarıdaki kodda yazdırma fonksiyonu zaten var, bu yüzden sadece etiketleri kontrol ediyoruz]
?>
<!-- Makbuz Yazdırma Scripti (PHP tarafından JS'e veri aktarır) -->
<script>
    // global currentOrders değişkenine erişim sağlaması için buraya yerleştirildi.
    // Bu kod, admin_dashboard.php'nin ilk başında bulunan kısımdır.
    <?php if (!empty($orders)): ?>
        // Yazdırma fonksiyonu (mevcut dosyanın API kısmında tanımlıdır)
        // printOrder(id) fonksiyonu zaten tanımlı olmalı
        
        // Eğer tarayıcınızda hala hata varsa, bu kısmın üstündeki veya altındaki PHP kodlarında çökme var demektir.
        
        // PRINT FUNCTION DÜZELTME
        function printOrder(id) { 
            const order = currentOrders.find(o => o.id === id); 
            if(!order) return; 
            const area = document.getElementById('receipt-area'); 
            const dateStr = new Date(order.created_at).toLocaleDateString('nl-NL'); 
            const timeStr = new Date(order.created_at).toLocaleTimeString('nl-NL', {hour: '2-digit', minute:'2-digit'}); 

            // İndirim Bilgilerini Hesapla
            const discountAmount = parseFloat(order.discount_amount || 0);
            const couponCode = order.coupon_code || '';
            const deliveryFee = parseFloat(order.delivery_fee || 0);
            // total_price: Toplam tutar (Kargo dahil, İndirim uygulanmış)
            // subTotalWithoutDiscount: Ürün Toplamı + İndirim (Eski Subtotal'i hesaplar gibi)
            const subTotalWithoutDiscount = parseFloat(order.total_price) - deliveryFee + discountAmount;
            
            let itemsHtml = order.items.map(item => { 
                let ex = ''; 
                // item.extras zaten string olarak gelmeli
                if (item.extras && item.extras.length > 0) ex = `<div style="font-size:11px; margin-left:10px; color:#555;">+ ${item.extras}</div>`; 

                return `<div style="margin-bottom:8px;"><div style="display:flex; justify-content:space-between; align-items:flex-start;"><span style="font-weight:bold; width:10%;">${item.qty}x</span><span style="width:65%; font-weight:bold;">${item.product_name}</span><span style="width:25%; text-align:right;">EUR ${parseFloat(item.total_price).toFixed(2)}</span></div>${ex}</div>`; 
            }).join(''); 
            
            // İndirim satırı eklendi
            const discountPrint = (discountAmount > 0) ? `<div style="display:flex; justify-content:space-between; margin-top:5px; font-weight:bold; color:green;"><span>Korting (${couponCode})</span><span>-EUR ${discountAmount.toFixed(2)}</span></div>` : ''; 
            
            // QR Kodu
            const qrData = `Order ID: ${order.id} | Status: ${order.status} | Total: EUR ${parseFloat(order.total_price).toFixed(2)} | Date: ${dateStr}`;
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=${encodeURIComponent(qrData)}`;

            area.innerHTML = `
                <div style="width:80mm; font-family:'Courier New', monospace; font-size:12px; color:black; line-height:1.2;">
                    <div style="text-align:center; margin-bottom:15px;">
                        <div style="font-size:24px; font-weight:bold; margin-bottom:5px;">SMAAKY</div>
                        <div>Ambachtsplein 9,<br>3068 GV Rotterdam<br>Tel: 0639839800</div>
                        <div style="margin-top:10px; font-weight:bold; font-size:16px;">#${order.id}</div>
                        <div>${dateStr} ${timeStr}</div>
                    </div>
                    <div style="text-align:center; margin-bottom:15px; border-top:2px solid black; border-bottom:2px solid black; padding:10px 0;">
                        <div style="font-size:20px; font-weight:bold;">${order.delivery_type === 'delivery' ? 'Delivery' : 'Pick-up'}</div>
                        ${order.status === 'confirmed' ? '<div style="font-weight:bold; margin-top:5px;">Confirmed time: '+timeStr+'</div>' : ''}
                    </div>
                    <div style="margin-bottom:15px;">
                        <div style="font-weight:bold; font-size:14px;">${order.customer_name}</div>
                        <div>${order.street || 'Afhaal'} ${order.house_number || ''}</div>
                        <div>${order.zip || ''} ${order.city || ''}</div>
                        <div style="margin-top:5px;">Tel: ${order.phone}</div>
                    </div>
                    <div style="border-top:1px solid black; border-bottom:1px solid black; padding:10px 0; margin-bottom:15px;">${itemsHtml}</div>
                    <div style="margin-bottom:15px;">
                        <div style="display:flex; justify-content:space-between;"><span>Subtotal (v. korting)</span><span>EUR ${subTotalWithoutDiscount.toFixed(2)}</span></div>
                        ${deliveryFee > 0 ? `<div style="display:flex; justify-content:space-between;"><span>Bezorgkosten</span><span>EUR ${deliveryFee.toFixed(2)}</span></div>` : ''}
                        ${discountPrint}
                        <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:16px; margin-top:10px; border-top:1px solid black; padding-top:5px;">
                            <span>Total</span>
                            <span>EUR ${parseFloat(order.total_price).toFixed(2)}</span>
                        </div>
                    </div>
                    ${order.note ? `<div style="margin-bottom:15px; font-weight:bold; border:1px solid black; padding:5px;">Comments: ${order.note}</div>` : ''}
                    <div style="text-align:center; font-size:11px; margin-top:20px;">
                        <div>v1.173.1 (Coupon/Countdown Update)</div>
                        <div style="font-weight:bold; font-size:14px; margin:10px 0; text-decoration:underline;">Payment Info:</div>
                        <div style="font-weight:bold; font-size:18px;">Order is paid<br>online</div>
                        <div style="margin-top:5px;">Payment: ${order.payment_method}</div>
                        <div style="margin-top:15px;"><img src="${qrUrl}" width="100" height="100"></div>
                        <div style="margin-top:15px; font-size:10px; font-style:italic;">Scan QR voor bestelstatus (Web)</div>
                    </div>
                </div>
            `; 
            setTimeout(() => window.print(), 500); 
        }
    <?php endif; ?>
</script>