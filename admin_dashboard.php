<?php
session_start();
require_once 'config.php';

// --- API: CANLI SİPARİŞ ---
if (isset($_GET['api']) && $_GET['api'] == 'get_live_data') {
    error_reporting(0); ini_set('display_errors', 0); header('Content-Type: application/json');
    try {
        $activeOrders = $pdo->query("SELECT * FROM orders WHERE status NOT IN ('completed', 'cancelled') ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        // Ayarları çek
        $settingsRows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach($settingsRows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $cookingTime = (int) ($settings['cooking_time'] ?? 20);
        $deliveryTime = (int) ($settings['delivery_time'] ?? 30);
        
        foreach ($activeOrders as &$order) {
            $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?"); $stmt->execute([$order['id']]); $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Geri Sayım Hesaplaması
            $baseTime = $cookingTime;
            if ($order['delivery_type'] === 'delivery') {
                $baseTime += $deliveryTime;
            }
            
            // Siparişin oluşturulmasından bu yana geçen süre (dakika)
            $createdTime = new DateTime($order['created_at']);
            $now = new DateTime();
            $interval = $now->diff($createdTime);
            $elapsedMin = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;

            // Kalan süre = Tahmini süre - Geçen süre
            $remainingMin = max(0, $baseTime - $elapsedMin);
            $order['estimatedRemainingMin'] = $remainingMin;
            
            // Eğer sipariş onaylanmışsa, kalan süreyi göstermeye başla
            if ($order['status'] !== 'pending') {
                 // Basitlik için, onaylandıktan sonra timer'ı başlatıyoruz.
                 $order['timerValue'] = $remainingMin;
            } else {
                 // Pending ise sadece geçen süreyi göster
                 $order['timerValue'] = $elapsedMin;
            }
            
        }
        echo json_encode(['status' => 'success', 'orders' => $activeOrders, 'settings' => $settings]);
    } catch (Exception $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
    exit;
}

// --- HTML BAŞLANGICI ---
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
$uploadDir = 'assets/img/'; $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];

// GÜVENLİK
if (isset($_POST['login'])) { if ($_POST['username'] === 'admin' && $_POST['password'] === 'admin123') { $_SESSION['admin_logged_in'] = true; header("Location: admin_dashboard.php"); exit; } else { $error = "Ongeldig wachtwoord!"; } }
if (isset($_GET['logout'])) { session_destroy(); header("Location: admin_dashboard.php"); exit; }
if (!isset($_SESSION['admin_logged_in'])) { echo '<!DOCTYPE html><html lang="nl"><head><title>Smaaky Admin</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-gray-900 h-screen flex items-center justify-center"><div class="bg-white p-8 rounded-2xl shadow-xl w-96"><h1 class="text-3xl font-black italic mb-6 text-center">SMAAKY</h1><form method="post" class="space-y-4"><input type="text" name="username" placeholder="Gebruikersnaam" class="w-full p-3 border rounded-xl"><input type="password" name="password" placeholder="Wachtwoord" class="w-full p-3 border rounded-xl"><button type="submit" name="login" class="w-full bg-black text-white p-3 rounded-xl font-bold">Inloggen</button></form></div></body></html>'; exit; }

// --- EXPORT ÜRÜNLER (GET isteği olduğundan, POST bloğunun ve sayfa render'ının üstünde olmalı) ---
if (isset($_GET['action']) && $_GET['action'] == 'export_products') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="products_export_'.date('Ymd_His').'.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Başlık satırını yaz (fputcsv düzeltildi)
    fputcsv($output, ['id', 'category_id', 'name', 'description', 'price', 'is_active', 'image', 'linked_extras'], ',', '"', '\\');
    
    // Verileri çek
    $products = $pdo->query("SELECT p.*, GROUP_CONCAT(pe.extra_id) as linked_extras FROM products p LEFT JOIN product_extras pe ON p.id = pe.product_id GROUP BY p.id ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $p) {
        $row = [
            $p['id'],
            $p['category_id'],
            $p['name'],
            $p['description'],
            number_format($p['price'], 2, '.', ''), // Fiyatı formatla
            $p['is_active'],
            $p['image'],
            $p['linked_extras'] // Virgülle ayrılmış extra ID'ler
        ];
        // PHP 8+ uyumluluğu için 4. ve 5. parametreleri (enclosure ve escape) ekle (fputcsv düzeltildi)
        fputcsv($output, $row, ',', '"', '\\'); 
    }
    
    fclose($output);
    exit; // ÖNEMLİ: Bu, dosya indirme bittikten sonra sayfanın geri kalanının yüklenmesini engeller.
}

// İŞLEMLER (POST)
$msg = ""; $msgType = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sipariş Durumu
    if (isset($_POST['action']) && $_POST['action'] == 'update_status') { $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$_POST['status'], $_POST['order_id']]); echo "ok"; exit; }
    
    // Ürün Kaydet
    if (isset($_POST['action']) && $_POST['action'] == 'save_product') {
        $name = $_POST['name']; $desc = $_POST['description']; $price = $_POST['price']; $cat_id = $_POST['category_id']; $pid = $_POST['product_id'] ?? null; $imageName = $_POST['current_image'] ?? '';
        if (!empty($_FILES['image']['name'])) { $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION)); if (in_array($ext, $allowedTypes)) { $target = uniqid().'.'.$ext; if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir.$target)) $imageName = $target; } }
        if ($pid) { $pdo->prepare("UPDATE products SET category_id=?, name=?, description=?, price=?, image=? WHERE id=?")->execute([$cat_id, $name, $desc, $price, $imageName, $pid]); } 
        else { $pdo->prepare("INSERT INTO products (category_id, name, description, price, image, is_active) VALUES (?, ?, ?, ?, ?, 1)")->execute([$cat_id, $name, $desc, $price, $imageName]); $pid = $pdo->lastInsertId(); }
        $pdo->prepare("DELETE FROM product_extras WHERE product_id = ?")->execute([$pid]);
        if (isset($_POST['linked_extras'])) { $stmtEx = $pdo->prepare("INSERT INTO product_extras (product_id, extra_id) VALUES (?, ?)"); foreach($_POST['linked_extras'] as $eid) { $stmtEx->execute([$pid, $eid]); } }
        $msg = "Product opgeslagen!"; $msgType = "success";
    }

    // --- IMPORT ÜRÜNLER ---
    if (isset($_POST['action']) && $_POST['action'] == 'import_products') {
        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            // Düzeltildi: fgetcsv'e escape parametreleri eklendi.
            $header = fgetcsv($file, 1000, ',', '"', '\\'); // Satır 116 (Eski 116)
            $importedCount = 0;
            $updatedCount = 0;

            $pdo->beginTransaction();
            try {
                $stmtExtrasDelete = $pdo->prepare("DELETE FROM product_extras WHERE product_id = ?");
                $stmtExtrasInsert = $pdo->prepare("INSERT INTO product_extras (product_id, extra_id) VALUES (?, ?)");
                $stmtCheck = $pdo->prepare("SELECT id FROM products WHERE name = ?"); 
                $stmtUpdate = $pdo->prepare("UPDATE products SET category_id=?, description=?, price=?, is_active=?, image=? WHERE id=?");
                $stmtInsert = $pdo->prepare("INSERT INTO products (category_id, name, description, price, is_active, image) VALUES (?, ?, ?, ?, ?, ?)");

                // Düzeltildi: fgetcsv'e escape parametreleri eklendi.
                while (($row = fgetcsv($file, 1000, ',', '"', '\\')) !== FALSE) { // Satır 128 (Eski 128)
                    $data = array_combine($header, $row);
                    
                    if (empty($data['name']) || empty($data['price']) || !isset($data['category_id'])) {
                        continue; 
                    }
                    
                    $name = trim($data['name']);
                    $stmtCheck->execute([$name]);
                    $pid = $stmtCheck->fetchColumn();

                    $cat_id = (int)$data['category_id'];
                    $desc = trim($data['description'] ?? '');
                    $price = (float)$data['price'];
                    $is_active = (int)($data['is_active'] ?? 1);
                    $image = trim($data['image'] ?? '');
                    $linked_extras_str = trim($data['linked_extras'] ?? '');
                    
                    if ($pid) {
                        $stmtUpdate->execute([$cat_id, $desc, $price, $is_active, $image, $pid]);
                        $updatedCount++;
                    } else {
                        $stmtInsert->execute([$cat_id, $name, $desc, $price, $is_active, $image]);
                        $pid = $pdo->lastInsertId();
                        $importedCount++;
                    }
                    
                    if ($pid) {
                        $stmtExtrasDelete->execute([$pid]);
                        if (!empty($linked_extras_str)) {
                            $extra_ids = explode(',', $linked_extras_str);
                            foreach ($extra_ids as $eid) {
                                $eid = (int)trim($eid);
                                if ($eid > 0) {
                                    $stmtExtrasInsert->execute([$pid, $eid]);
                                }
                            }
                        }
                    }
                }

                $pdo->commit();
                $msg = "Import succesvol: $importedCount nieuwe, $updatedCount bijgewerkt.";
                $msgType = "success";

            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = "Import fout: " . $e->getMessage();
                $msgType = "error";
            }
            fclose($file);
        } else {
            $msg = "Fout: Geen bestand geüpload.";
            $msgType = "error";
        }
    }
    
    // KUPON KAYDET
    if (isset($_POST['action']) && $_POST['action'] == 'save_coupon') {
        $code = strtoupper($_POST['code']); $type = $_POST['type']; $value = $_POST['value']; 
        $target_type = $_POST['target_type']; $target_id = $_POST['target_id'] ?: null; $min_order = $_POST['min_order_amount'];
        $pdo->prepare("INSERT INTO coupons (code, type, value, target_type, target_id, min_order_amount) VALUES (?, ?, ?, ?, ?, ?)")->execute([$code, $type, $value, $target_type, $target_id, $min_order]);
        $msg = "Kortingscode toegevoegd!"; $msgType = "success";
    }
    if (isset($_POST['action']) && $_POST['action'] == 'delete_coupon') { $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$_POST['id']]); $msg="Verwijderd"; $msgType="success"; }

    // Diğer İşlemler
    if (isset($_POST['action']) && $_POST['action'] == 'delete_product') { $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$_POST['id']]); $msg="Verwijderd"; $msgType="success"; }
    if (isset($_POST['action']) && $_POST['action'] == 'save_category') { $name=$_POST['name']; $sort=$_POST['sort_order']; $id=$_POST['cat_id']??null; if($id){$pdo->prepare("UPDATE categories SET name=?, sort_order=? WHERE id=?")->execute([$name,$sort,$id]);}else{$pdo->prepare("INSERT INTO categories (name,sort_order) VALUES (?,?)")->execute([$name,$sort]);} $msg="Opgeslagen"; $msgType="success"; }
    if (isset($_POST['action']) && $_POST['action'] == 'delete_category') { $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$_POST['id']]); $msg="Verwijderd"; $msgType="success"; }
    if (isset($_POST['action']) && $_POST['action'] == 'save_extra') { $name=$_POST['name']; $price=$_POST['price']; $id=$_POST['extra_id']??null; if($id){$pdo->prepare("UPDATE extras SET name=?, price=? WHERE id=?")->execute([$name,$price,$id]);}else{$pdo->prepare("INSERT INTO extras (name,price,is_active) VALUES (?,?,1)")->execute([$name,$price]);} $msg="Opgeslagen"; $msgType="success"; }
    if (isset($_POST['action']) && $_POST['action'] == 'delete_extra') { $pdo->prepare("DELETE FROM extras WHERE id = ?")->execute([$_POST['id']]); $msg="Verwijderd"; $msgType="success"; }
    if (isset($_POST['action']) && $_POST['action'] == 'toggle_product_status') { $pdo->prepare("UPDATE products SET is_active = ? WHERE id = ?")->execute([$_POST['status'], $_POST['id']]); echo "ok"; exit; }
    if (isset($_POST['action']) && $_POST['action'] == 'save_settings') {
        $settingsToSave = ['restaurant_open'=>isset($_POST['restaurant_open'])?1:0, 'delivery_open'=>isset($_POST['delivery_open'])?1:0, 'pickup_open'=>isset($_POST['pickup_open'])?1:0, 'cooking_time'=>$_POST['cooking_time']??20, 'delivery_time'=>$_POST['delivery_time']??30];
        foreach ($settingsToSave as $k => $v) { $chk=$pdo->prepare("SELECT id FROM settings WHERE setting_key=?"); $chk->execute([$k]); if($chk->rowCount()>0) $pdo->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?")->execute([$v,$k]); else $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?)")->execute([$k,$v]); }
        header("Location: ?page=settings&msg=saved"); exit;
    }
}

$page = $_GET['page'] ?? 'live_orders';
try {
    $settingsRows = $pdo->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_ASSOC); $currentSettings = []; foreach($settingsRows as $r) { $currentSettings[$r['setting_key']] = $r['setting_value']; }
} catch (Exception $e) { $currentSettings = ['restaurant_open'=>1, 'delivery_open'=>1, 'pickup_open'=>1, 'cooking_time'=>20, 'delivery_time'=>30]; }

// Ürünler sayfasında kullanılmak üzere kategorileri ve ürünleri çekelim
$allCats = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$allProds = $pdo->query("SELECT p.*, c.name as cname, GROUP_CONCAT(pe.extra_id) as linked_extras FROM products p LEFT JOIN categories c ON p.category_id=c.id LEFT JOIN product_extras pe ON p.id = pe.product_id GROUP BY p.id ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$allExtras = $pdo->query("SELECT * FROM extras ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Smaaky Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background: #F3F4F6; } .sidebar-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; color: #9CA3AF; transition: all 0.2s; font-weight: 500; margin-bottom: 4px; } .sidebar-link:hover { background-color: #1F2937; color: white; } .sidebar-link.active { background-color: #F97316; color: white; box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3); } .toggle-checkbox:checked { right: 0; border-color: #F97316; } .toggle-checkbox:checked + .toggle-label { background-color: #F97316; } .timer-circle { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 4px solid #E5E7EB; color: #6B7280; } .timer-circle.active { border-color: #F97316; color: #F97316; } ::-webkit-scrollbar { width: 6px; height: 6px; } ::-webkit-scrollbar-track { background: transparent; } ::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 3px; } @media print { .no-print { display: none !important; } body { background: white !important; } #receipt-area { display: block !important; position: absolute; left: 0; top: 0; width: 80mm; } @page { margin: 0; size: 80mm auto; } }</style>
</head>
<body class="flex h-screen overflow-hidden text-gray-800">
    <aside class="no-print w-72 bg-[#111827] text-white flex-col hidden md:flex shrink-0 p-4">
        <div class="px-2 py-4 flex items-center gap-3 mb-6"><div class="w-10 h-10 bg-gradient-to-br from-orange-400 to-orange-600 rounded-xl flex items-center justify-center font-black italic text-lg shadow-lg">SM</div><div><h1 class="font-bold text-lg leading-none tracking-tight">SMAAKY</h1><span class="text-xs text-gray-400 font-medium">Panel v3.3 Pro</span></div></div>
        <nav class="flex-1 space-y-1 overflow-y-auto">
            <p class="px-4 text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 mt-2">Overzicht</p>
            <a href="?page=live_orders" class="sidebar-link <?php echo $page=='live_orders'?'active':''; ?>"><i data-lucide="monitor-play" size="20"></i> Live Bestellingen</a>
            <a href="?page=bestellingen" class="sidebar-link <?php echo $page=='bestellingen'?'active':''; ?>"><i data-lucide="bar-chart-2" size="20"></i> Bestellingen (Stats)</a>
            <p class="px-4 text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 mt-6">Beheer</p>
            <a href="?page=products" class="sidebar-link <?php echo $page=='products'?'active':''; ?>"><i data-lucide="hamburger" size="20"></i> Producten</a>
            <a href="?page=categories" class="sidebar-link <?php echo $page=='categories'?'active':''; ?>"><i data-lucide="list" size="20"></i> Categorieën</a>
            <a href="?page=extras" class="sidebar-link <?php echo $page=='extras'?'active':''; ?>"><i data-lucide="layers" size="20"></i> Toppings</a>
            <a href="?page=coupons" class="sidebar-link <?php echo $page=='coupons'?'active':''; ?>"><i data-lucide="tag" size="20"></i> Kortingscodes</a>
            <a href="?page=menulist" class="sidebar-link <?php echo $page=='menulist'?'active':''; ?>"><i data-lucide="clipboard-list" size="20"></i> Menu Status</a>
            <p class="px-4 text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 mt-6">Admin</p>
            <a href="?page=settings" class="sidebar-link <?php echo $page=='settings'?'active':''; ?>"><i data-lucide="settings" size="20"></i> Instellingen</a>
        </nav>
        <div class="mt-auto pt-4 border-t border-gray-800"><a href="?logout=true" class="sidebar-link hover:bg-red-500/10 hover:text-red-400"><i data-lucide="log-out" size="20"></i> Uitloggen</a></div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0 no-print">
        <header class="bg-white border-b h-16 flex items-center justify-between px-6 shadow-sm z-10 shrink-0"><h2 class="text-xl font-bold text-gray-800 capitalize"><?php echo ucfirst(str_replace('_', ' ', $page)); ?></h2><div class="flex items-center gap-4"><button onclick="enableSound()" id="sound-btn" class="flex items-center gap-2 px-4 py-2 rounded-full bg-gray-100 text-sm font-bold text-gray-500 hover:bg-gray-200 transition"><i data-lucide="volume-x" size="18"></i> <span class="hidden sm:inline">Geluid Uit</span></button></div></header>
        <main class="flex-1 overflow-y-auto p-4 md:p-6 bg-[#f9fafb] h-[calc(100vh-64px)]">
            <?php if($msg): ?><div class="mb-6 p-4 rounded-xl text-sm font-bold shadow-sm <?php echo $msgType=='success'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'; ?>"><?php echo $msg; ?></div><?php endif; ?>
            
            <?php if ($page == 'live_orders'): ?>
                <!-- Live Orders content... (Unchanged) -->
                <div class="flex h-[calc(100%-20px)] gap-6">
                    <div class="w-1/3 flex flex-col bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center"><h3 class="font-bold text-gray-700 flex items-center gap-2"><i data-lucide="list-ordered"></i> Wachtrij</h3><div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div></div><div id="order-list" class="flex-1 overflow-y-auto p-2 space-y-2"><div class="text-center py-10 text-gray-400">Laden...</div></div>
                    </div>
                    <div class="w-2/3 flex flex-col bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden relative"><div id="order-detail-view" class="flex-1 p-8 overflow-y-auto"><div class="h-full flex flex-col items-center justify-center text-gray-400"><i data-lucide="chef-hat" size="64" class="mb-4 opacity-20 text-gray-500"></i><p class="font-medium text-lg">Selecteer een bestelling.</p></div></div></div>
                </div>
            <?php elseif ($page == 'bestellingen'): ?>
                <!-- Stats content... (Unchanged) -->
                <?php $today=date('Y-m-d'); $totalOrdersToday=$pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = '$today'")->fetchColumn(); $revenueToday=$pdo->query("SELECT SUM(total_price) FROM orders WHERE DATE(created_at) = '$today'")->fetchColumn()?:0; $activeProducts=$pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn(); $lastOrders=$pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC); $topProducts=$pdo->query("SELECT product_name, COUNT(*) as qty FROM order_items GROUP BY product_name ORDER BY qty DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC); ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8"><div class="bg-white p-6 rounded-2xl border shadow-sm flex flex-col"><span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">VANDAAG</span><div class="flex justify-between items-end"><div><p class="text-gray-500 font-medium">Bestellingen</p><h3 class="text-3xl font-black text-gray-800 mt-1"><?php echo $totalOrdersToday; ?></h3></div><div class="p-3 bg-orange-100 text-orange-600 rounded-xl"><i data-lucide="shopping-bag"></i></div></div></div><div class="bg-white p-6 rounded-2xl border shadow-sm flex flex-col"><span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">VANDAAG</span><div class="flex justify-between items-end"><div><p class="text-gray-500 font-medium">Omzet</p><h3 class="text-3xl font-black text-gray-800 mt-1">€<?php echo number_format($revenueToday, 2); ?></h3></div><div class="p-3 bg-green-100 text-green-600 rounded-xl"><i data-lucide="euro"></i></div></div></div><div class="bg-white p-6 rounded-2xl border shadow-sm flex flex-col"><span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">STATUS</span><div class="flex justify-between items-end"><div><p class="text-gray-500 font-medium">Actieve Producten</p><h3 class="text-3xl font-black text-gray-800 mt-1"><?php echo $activeProducts; ?></h3></div><div class="p-3 bg-blue-100 text-blue-600 rounded-xl"><i data-lucide="layers"></i></div></div></div></div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8"><div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border overflow-hidden"><div class="p-6 border-b border-gray-100"><h3 class="font-bold text-lg text-gray-800">Laatste bestellingen</h3></div><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-gray-500 uppercase font-semibold"><tr><th class="p-4">ID</th><th class="p-4">Klant</th><th class="p-4">Bedrag</th><th class="p-4">Status</th><th class="p-4">Tijd</th></tr></thead><tbody class="divide-y divide-gray-100"><?php foreach($lastOrders as $ord): $stClass=match($ord['status']){'pending'=>'bg-yellow-100 text-yellow-800','completed'=>'bg-green-100 text-green-800','cancelled'=>'bg-red-100 text-red-800',default=>'bg-gray-100 text-gray-800'}; ?><tr class="hover:bg-gray-50"><td class="p-4 font-mono text-gray-400">#<?php echo $ord['id']; ?></td><td class="p-4 font-bold"><?php echo $ord['customer_name']; ?></td><td class="p-4">€<?php echo number_format($ord['total_price'],2); ?></td><td class="p-4"><span class="px-2 py-1 rounded text-xs font-bold uppercase <?php echo $stClass; ?>"><?php echo $ord['status']; ?></span></td><td class="p-4 text-gray-500"><?php echo date('H:i', strtotime($ord['created_at'])); ?></td></tr><?php endforeach; ?></tbody></table></div></div><div class="bg-white rounded-2xl shadow-sm border p-6"><h3 class="font-bold text-lg text-gray-800 mb-6">Meest verkochte</h3><div class="space-y-4"><?php foreach($topProducts as $idx=>$tp): ?><div class="flex items-center justify-between"><div class="flex items-center gap-3"><div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center font-bold text-gray-500 text-xs"><?php echo $idx+1; ?></div><span class="font-medium text-gray-800"><?php echo $tp['product_name']; ?></span></div><span class="font-bold text-gray-900"><?php echo $tp['qty']; ?>x</span></div><?php endforeach; ?></div></div></div>
            <?php elseif ($page == 'products'): ?>
                <!-- YENİ ÜRÜN YÖNETİMİ DÜZENİ -->
                <?php $currentCatId = $_GET['cat_id'] ?? ($allCats[0]['id'] ?? null); ?>
                <div class="flex flex-col md:flex-row gap-6 h-full max-h-[calc(100vh-140px)]">
                    
                    <!-- SOL: KATEGORİ LİSTESİ -->
                    <div class="w-full md:w-64 flex-shrink-0 bg-white rounded-2xl shadow-sm border overflow-hidden flex flex-col">
                        <div class="p-4 border-b border-gray-100">
                            <h3 class="font-bold text-lg text-gray-800">Categorieën</h3>
                        </div>
                        <div class="flex-1 overflow-y-auto p-2">
                            <?php foreach($allCats as $cat): 
                                $catProdCount = count(array_filter($allProds, fn($p) => $p['category_id'] == $cat['id']));
                            ?>
                                <a href="?page=products&cat_id=<?php echo $cat['id']; ?>" class="flex justify-between items-center p-3 rounded-xl transition-colors mb-1 
                                    <?php echo $currentCatId == $cat['id'] ? 'bg-orange-100 text-orange-700 font-bold shadow-sm' : 'hover:bg-gray-50 text-gray-700'; ?>">
                                    <span class="truncate"><?php echo $cat['name']; ?></span>
                                    <span class="text-xs font-medium px-2 py-0.5 rounded-full <?php echo $currentCatId == $cat['id'] ? 'bg-orange-500 text-white' : 'bg-gray-200 text-gray-600'; ?>"><?php echo $catProdCount; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div class="p-4 border-t border-gray-100">
                             <a href="?page=categories" class="text-sm font-bold text-gray-500 hover:text-orange-500 flex items-center gap-1">
                                <i data-lucide="settings-2" size="16"></i> Categorie Instellingen
                            </a>
                        </div>
                    </div>

                    <!-- SAĞ: ÜRÜN KARTLARI -->
                    <div class="flex-1 min-h-0 flex flex-col">
                        
                        <div class="flex justify-between items-center mb-4 p-4 bg-white rounded-2xl shadow-sm border">
                            <div class="flex items-center gap-4">
                                <h3 class="font-bold text-xl text-gray-800">
                                    <?php 
                                        $currentCat = array_filter($allCats, fn($c) => $c['id'] == $currentCatId);
                                        echo $currentCat ? (reset($currentCat)['name'] . ' (' . count(array_filter($allProds, fn($p) => $p['category_id'] == $currentCatId)) . ')') : 'Alle Producten';
                                    ?>
                                </h3>
                                <button onclick="openModal('product-modal')" class="bg-black text-white px-4 py-2 rounded-xl font-bold flex gap-2 hover:bg-gray-800 shadow-lg text-sm">
                                    <i data-lucide="plus" size="18"></i> Nieuw Product
                                </button>
                            </div>
                            
                            <div class="flex gap-3">
                                <a href="?action=export_products" class="bg-blue-600 text-white px-4 py-2 rounded-xl font-bold flex gap-2 hover:bg-blue-700 shadow-md text-sm">
                                    <i data-lucide="download" size="18"></i> Export CSV
                                </a>
                                <button onclick="openModal('import-modal')" class="bg-gray-600 text-white px-4 py-2 rounded-xl font-bold flex gap-2 hover:bg-gray-700 shadow-md text-sm">
                                    <i data-lucide="upload" size="18"></i> Import CSV
                                </button>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto p-2 space-y-4">
                            <?php $catProds = array_filter($allProds, fn($p) => $p['category_id'] == $currentCatId); ?>
                            
                            <?php if (empty($catProds)): ?>
                                <div class="bg-white p-10 text-center rounded-xl shadow-sm border text-gray-500">
                                    <i data-lucide="inbox" size="32" class="mb-3 mx-auto"></i>
                                    <p class="font-medium">Geen producten in deze categorie.</p>
                                    <p class="text-sm">Klik op 'Nieuw Product' om toe te voegen.</p>
                                </div>
                            <?php endif; ?>

                            <?php foreach($catProds as $p): 
                                $image_url = $p['image'] ? 'assets/img/'.$p['image'] : 'https://placehold.co/100x100/f5f5f5/a0a0a0?text=IMG';
                                $extraCount = $p['linked_extras'] ? count(explode(',', $p['linked_extras'])) : 0;
                            ?>
                                <div class="bg-white rounded-xl shadow-lg border border-gray-100 flex p-4 items-center product-card">
                                    <img src="<?php echo $image_url; ?>" onerror="this.onerror=null; this.src='https://placehold.co/100x100/f5f5f5/a0a0a0?text=IMG'" class="w-24 h-24 object-cover rounded-xl mr-4 flex-shrink-0 bg-gray-100" alt="<?php echo $p['name']; ?>">
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-bold text-lg text-gray-900 truncate"><?php echo $p['name']; ?></h4>
                                        <p class="text-sm text-gray-500 mb-2 truncate"><?php echo $p['description'] ?: 'Geen beschrijving beschikbaar.'; ?></p>
                                        
                                        <div class="flex items-center gap-4 text-sm font-medium">
                                            <span class="text-orange-600 font-black text-xl">€<?php echo number_format($p['price'], 2); ?></span>
                                            <span class="text-xs text-gray-500 flex items-center gap-1 bg-gray-100 px-2 py-0.5 rounded-full">
                                                <i data-lucide="layers" size="14"></i> <?php echo $extraCount; ?> Toppings
                                            </span>
                                            
                                            <div class="relative inline-block w-12 align-middle select-none transition duration-200 ease-in ml-4">
                                                <input type="checkbox" id="toggle-prod-<?php echo $p['id']; ?>" onchange="toggleProduct(<?php echo $p['id']; ?>, this.checked)" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer border-gray-300" <?php echo $p['is_active'] ? 'checked' : ''; ?>/>
                                                <label for="toggle-prod-<?php echo $p['id']; ?>" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label>
                                            </div>
                                            <span class="text-xs text-gray-500">Aktief</span>
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-2 ml-4 flex-shrink-0">
                                        <button onclick='editProduct(<?php echo json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' class="px-3 py-2 bg-blue-50 text-blue-600 rounded-lg font-bold hover:bg-blue-100 flex items-center gap-2 text-sm">
                                            <i data-lucide="pencil" size="16"></i> Bewerken
                                        </button>
                                        <form method="POST" class="inline" onsubmit="return confirm('Weet je zeker dat je dit product wilt verwijderen?')">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                            <button class="px-3 py-2 bg-red-50 text-red-600 rounded-lg font-bold hover:bg-red-100 flex items-center gap-2 text-sm">
                                                <i data-lucide="trash-2" size="16"></i> Verwijder
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($page == 'coupons'): ?>
                <!-- KUPON SAYFASI (Unchanged) -->
                <div class="flex justify-between items-center mb-6"><h3 class="font-bold text-lg">Kortingscodes</h3><button onclick="openModal('coupon-modal')" class="bg-black text-white px-4 py-2 rounded-lg font-bold flex gap-2 text-sm"><i data-lucide="plus"></i> Toevoegen</button></div>
                <div class="bg-white rounded-2xl shadow-sm border overflow-hidden max-w-4xl">
                    <table class="w-full text-left text-sm"><thead class="bg-gray-50 text-gray-500 uppercase font-semibold"><tr><th class="p-4">Code</th><th class="p-4">Type</th><th class="p-4">Waarde</th><th class="p-4">Toepassing</th><th class="p-4">Min. Bestelling</th><th class="p-4 text-right">Actie</th></tr></thead>
                        <tbody class="divide-y">
                            <?php $coupons = $pdo->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC); foreach($coupons as $cp): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="p-4 font-bold font-mono text-orange-600"><?php echo $cp['code']; ?></td>
                                <td class="p-4 text-xs font-bold uppercase"><?php echo $cp['type']; ?></td>
                                <td class="p-4 font-bold"><?php echo $cp['type']=='percent' ? '%'.intval($cp['value']) : '€'.number_format($cp['value'],2); ?></td>
                                <td class="p-4 text-gray-500 text-xs capitalize"><?php echo $cp['target_type']; ?></td>
                                <td class="p-4 text-gray-500">€<?php echo number_format($cp['min_order_amount'],2); ?></td>
                                <td class="p-4 text-right"><form method="POST" class="inline" onsubmit="return confirm('Verwijderen?')"><input type="hidden" name="action" value="delete_coupon"><input type="hidden" name="id" value="<?php echo $cp['id']; ?>"><button class="text-red-600 hover:underline">Verwijder</button></form></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($page == 'categories'): ?>
                <!-- YENİ KATEGORİ YÖNETİMİ DÜZENİ -->
                <div class="flex justify-between items-center mb-6">
                    <h3 class="font-bold text-lg">Categorieën Beheer</h3>
                    <button onclick="openModal('cat-modal')" class="bg-black text-white px-4 py-2 rounded-lg font-bold flex gap-2 text-sm shadow-md"><i data-lucide="plus"></i> Toevoegen</button>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border overflow-hidden max-w-3xl">
                    <div class="divide-y divide-gray-100">
                        <?php foreach($allCats as $c): ?>
                            <div class="flex items-center justify-between p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex items-center gap-4">
                                    <span class="text-gray-400 font-mono w-6 text-center text-sm"><?php echo $c['sort_order']; ?></span>
                                    <span class="font-bold text-gray-800"><?php echo $c['name']; ?></span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="text-sm text-gray-500">ID: <?php echo $c['id']; ?></span>
                                    <button onclick='editCat(<?php echo json_encode($c); ?>)' class="text-blue-600 hover:text-blue-700 font-bold text-sm">Bewerken</button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Verwijderen?')">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                                        <button class="text-red-600 hover:text-red-700 font-bold text-sm">Verwijder</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($page == 'extras'): ?>
                <!-- Extras content... (Unchanged) -->
                <div class="flex justify-between items-center mb-6"><h3 class="font-bold text-lg">Extra's (Toppings)</h3><button onclick="openModal('extra-modal')" class="bg-black text-white px-4 py-2 rounded-lg font-bold flex gap-2 text-sm"><i data-lucide="plus"></i> Toevoegen</button></div><div class="bg-white rounded-2xl shadow-sm border overflow-hidden max-w-3xl"><table class="w-full text-left text-sm"><thead class="bg-gray-50 text-gray-500 uppercase font-semibold"><tr><th class="p-4">Naam</th><th class="p-4">Prijs</th><th class="p-4 text-right">Actie</th></tr></thead><tbody class="divide-y"><?php $extras = $pdo->query("SELECT * FROM extras ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC); foreach($extras as $e): ?><tr class="hover:bg-gray-50"><td class="p-4 font-bold"><?php echo $e['name']; ?></td><td class="p-4 font-bold text-green-600">+€<?php echo number_format($e['price'],2); ?></td><td class="p-4 text-right"><button onclick='editExtra(<?php echo json_encode($e); ?>)' class="text-blue-600 hover:underline mr-3">Bewerken</button><form method="POST" class="inline" onsubmit="return confirm('Verwijderen?')"><input type="hidden" name="action" value="delete_extra"><input type="hidden" name="id" value="<?php echo $e['id']; ?>"><button class="text-red-600 hover:underline">Verwijder</button></form></td></tr><?php endforeach; ?></tbody></table></div>
            <?php elseif ($page == 'menulist'): ?>
                <!-- Menulist content... (Unchanged) -->
                <div class="max-w-5xl mx-auto"><div class="mb-6 flex justify-between items-center"><div><h3 class="text-lg font-bold text-gray-800">Menu Beheer</h3></div><div class="relative"><i data-lucide="search" class="absolute left-3 top-2.5 text-gray-400" size="18"></i><input type="text" id="menu-search" onkeyup="filterMenu()" placeholder="Zoek..." class="pl-10 pr-4 py-2 border rounded-xl outline-none focus:border-orange-500 w-64 bg-white shadow-sm"></div></div><div class="space-y-6" id="menu-container"><?php $cats = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC); foreach($cats as $cat): $prods = $pdo->prepare("SELECT * FROM products WHERE category_id = ? ORDER BY id DESC"); $prods->execute([$cat['id']]); $prods = $prods->fetchAll(PDO::FETCH_ASSOC); if(count($prods) > 0): ?><div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden menu-category"><div class="px-6 py-4 bg-gray-50 border-b border-gray-100"><h4 class="font-bold text-gray-800 text-lg"><?php echo $cat['name']; ?></h4></div><div class="divide-y divide-gray-100"><?php foreach($prods as $p): ?><div class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors menu-item"><div><p class="font-bold text-gray-800 menu-item-name"><?php echo $p['name']; ?></p></div><div class="flex items-center gap-4"><span class="text-sm font-bold text-gray-600">€<?php echo number_format($p['price'], 2); ?></span><div class="relative inline-block w-12 align-middle select-none transition duration-200 ease-in"><input type="checkbox" onchange="toggleProduct(<?php echo $p['id']; ?>, this.checked)" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer border-gray-300" <?php echo $p['is_active'] ? 'checked' : ''; ?>/><label class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label></div></div></div><?php endforeach; ?></div></div><?php endif; endforeach; ?></div></div>
            <?php elseif ($page == 'settings'): ?>
                <!-- Settings content... (Unchanged) -->
                <div class="max-w-4xl mx-auto"><form method="POST" class="space-y-6"><input type="hidden" name="action" value="save_settings"><div class="bg-white rounded-2xl shadow-sm border p-6"><h3 class="font-bold text-lg text-gray-800 mb-4">Restaurant Status</h3><div class="flex items-center justify-between py-3 border-b border-gray-100"><div><p class="font-bold text-gray-700">Restaurant</p><p class="text-xs text-gray-400">Open of sluit het restaurant online.</p></div><div class="relative inline-block w-12 mr-2 align-middle select-none transition duration-200 ease-in"><input type="checkbox" name="restaurant_open" id="toggle-res" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer border-gray-300" <?php echo $currentSettings['restaurant_open'] ? 'checked' : ''; ?>/><label for="toggle-res" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label></div></div><div class="flex items-center justify-between py-3 border-b border-gray-100"><div><p class="font-bold text-gray-700">Bezorgen (Delivery)</p></div><div class="relative inline-block w-12 mr-2 align-middle select-none transition duration-200 ease-in"><input type="checkbox" name="delivery_open" id="toggle-del" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer border-gray-300" <?php echo $currentSettings['delivery_open'] ? 'checked' : ''; ?>/><label for="toggle-del" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label></div></div><div class="flex items-center justify-between py-3"><div><p class="font-bold text-gray-700">Afhalen (Pick-up)</p></div><div class="relative inline-block w-12 mr-2 align-middle select-none transition duration-200 ease-in"><input type="checkbox" name="pickup_open" id="toggle-pick" class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer border-gray-300" <?php echo $currentSettings['pickup_open'] ? 'checked' : ''; ?>/><label for="toggle-pick" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer"></label></div></div></div><div class="bg-white rounded-2xl shadow-sm border p-6"><h3 class="font-bold text-lg text-gray-800 mb-4">Tijden</h3><div class="grid grid-cols-2 gap-6"><div><label class="block text-sm font-bold text-gray-700 mb-2">Bereidingstijd (min)</label><input type="number" name="cooking_time" value="<?php echo $currentSettings['cooking_time']; ?>" class="w-full border p-2 rounded-lg text-center font-bold"></div><div><label class="block text-sm font-bold text-gray-700 mb-2">Bezorgtijd (min)</label><input type="number" name="delivery_time" value="<?php echo $currentSettings['delivery_time']; ?>" class="w-full border p-2 rounded-lg text-center font-bold"></div></div></div><div class="flex justify-end"><button type="submit" class="bg-black text-white px-8 py-3 rounded-xl font-bold hover:bg-gray-800 shadow-lg">Opslaan</button></div></form></div><?php endif; ?>
        </main>
    </div>

    <!-- PRODUCT MODAL -->
    <div id="product-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"><div class="bg-white rounded-xl w-full max-w-lg p-6 shadow-2xl max-h-[90vh] overflow-y-auto"><h3 class="font-bold text-lg mb-4" id="pm-title">Product</h3><form method="POST" enctype="multipart/form-data" class="space-y-4"><input type="hidden" name="action" value="save_product"><input type="hidden" name="product_id" id="pm-id"><input type="hidden" name="current_image" id="pm-img"><div class="grid grid-cols-2 gap-3"><input type="text" name="name" id="pm-name" placeholder="Naam" class="border p-2 rounded w-full outline-none focus:border-orange-500" required><input type="number" step="0.01" name="price" id="pm-price" placeholder="Prijs" class="border p-2 rounded w-full outline-none focus:border-orange-500" required></div><select name="category_id" id="pm-cat" class="border p-2 rounded w-full outline-none focus:border-orange-500"><?php foreach($allCats as $c) echo "<option value='{$c['id']}'>{$c['name']}</option>"; ?></select><textarea name="description" id="pm-desc" placeholder="Beschrijving" class="border p-2 rounded w-full outline-none focus:border-orange-500"></textarea><input type="file" name="image" class="text-sm"><div class="border-t pt-4"><label class="block font-bold mb-2 text-sm text-gray-700">Beschikbare Extra's (Toppings):</label><div class="grid grid-cols-2 gap-2 bg-gray-50 p-2 rounded border max-h-40 overflow-y-auto"><?php foreach($allExtras as $ex): ?><label class="flex items-center gap-2 cursor-pointer p-1 hover:bg-white rounded"><input type="checkbox" name="linked_extras[]" value="<?=$ex['id']?>" class="extra-checkbox rounded text-orange-600 focus:ring-orange-500"><span class="text-sm"><?=$ex['name']?> <span class="text-gray-400 text-xs">(+€<?=number_format($ex['price'],2)?>)</span></span></label><?php endforeach; ?></div><p class="text-xs text-gray-400 mt-1">Selecteer welke toppings bij dit product horen.</p></div><div class="flex justify-end gap-2 pt-2"><button type="button" onclick="closeModal('product-modal')" class="px-4 py-2 text-gray-500">Annuleren</button><button type="submit" class="bg-black text-white px-4 py-2 rounded font-bold hover:bg-gray-800">Opslaan</button></div></form></div></div>
    
    <!-- IMPORT MODAL (YENİ) -->
    <div id="import-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-md p-6 shadow-2xl">
            <h3 class="font-bold text-lg mb-4 flex items-center gap-2"><i data-lucide="upload" size="20"></i> Producten Importeren (CSV)</h3>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="import_products">
                <p class="text-sm text-gray-600">Upload een CSV-bestand om producten toe te voegen of bij te werken. De verwachte kolommen zijn: 
                    <code class="bg-gray-100 p-1 rounded text-xs font-mono">name, description, price, category_id, is_active, image, linked_extras (virgülle ayrılmış ID'ler)</code>
                </p>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-2">Selecteer CSV Bestand</label>
                    <input type="file" name="csv_file" accept=".csv" class="w-full border p-2 rounded-lg bg-gray-50" required>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeModal('import-modal')" class="px-4 py-2 text-gray-500">Annuleren</button>
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700 flex items-center gap-2">
                        <i data-lucide="chevrons-up" size="18"></i> Importeer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- COUPON MODAL -->
    <div id="coupon-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl w-full max-w-sm p-6 shadow-2xl">
            <h3 class="font-bold text-lg mb-4">Nieuwe Kortingscode</h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="save_coupon">
                <div><label class="block text-xs font-bold text-gray-500">Code (Bijv. SMAAKY20)</label><input type="text" name="code" class="border p-2 rounded w-full uppercase font-bold" required></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="block text-xs font-bold text-gray-500">Type</label><select name="type" class="border p-2 rounded w-full bg-white"><option value="percent">Percentage (%)</option><option value="fixed">Vast Bedrag (€)</option><option value="bogo">2 Halen 1 Betalen</option></select></div>
                    <div><label class="block text-xs font-bold text-gray-500">Waarde</label><input type="number" step="0.01" name="value" class="border p-2 rounded w-full" placeholder="20"></div>
                </div>
                <div><label class="block text-xs font-bold text-gray-500">Geldig Voor</label><select name="target_type" class="border p-2 rounded w-full bg-white"><option value="all">Alles</option><option value="category">Specifieke Categorie</option><option value="product">Specifiek Product</option></select></div>
                <div><label class="block text-xs font-bold text-gray-500">Target ID (Indien nodig)</label><input type="number" name="target_id" class="border p-2 rounded w-full" placeholder="Categorie of Product ID"></div>
                <div><label class="block text-xs font-bold text-gray-500">Min. Bestelbedrag</label><input type="number" step="0.01" name="min_order_amount" value="0" class="border p-2 rounded w-full"></div>
                <div class="flex justify-end gap-2 pt-2"><button type="button" onclick="closeModal('coupon-modal')" class="px-4 py-2 text-gray-500">Annuleren</button><button type="submit" class="bg-black text-white px-4 py-2 rounded font-bold">Opslaan</button></div>
            </form>
        </div>
    </div>

    <!-- DİĞER MODALLAR -->
    <div id="cat-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"><div class="bg-white rounded-xl w-full max-w-sm p-6"><h3 class="font-bold text-lg mb-4" id="cm-title">Categorie</h3><form method="POST" class="space-y-3"><input type="hidden" name="action" value="save_category"><input type="hidden" name="cat_id" id="cm-id"><input type="text" name="name" id="cm-name" placeholder="Naam" class="border p-2 rounded w-full" required><input type="number" name="sort_order" id="cm-sort" placeholder="Volgorde" class="border p-2 rounded w-full"><div class="flex justify-end gap-2 mt-4"><button type="button" onclick="closeModal('cat-modal')" class="px-4 py-2 text-gray-500">Annuleren</button><button type="submit" class="bg-black text-white px-4 py-2 rounded font-bold">Opslaan</button></div></form></div></div>
    <div id="extra-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"><div class="bg-white rounded-xl w-full max-w-sm p-6"><h3 class="font-bold text-lg mb-4" id="em-title">Extra</h3><form method="POST" class="space-y-3"><input type="hidden" name="action" value="save_extra"><input type="hidden" name="extra_id" id="em-id"><input type="text" name="name" id="em-name" placeholder="Naam" class="border p-2 rounded w-full" required><input type="number" step="0.01" name="price" id="em-price" placeholder="Prijs" class="border p-2 rounded w-full" required><div class="flex justify-end gap-2 mt-4"><button type="button" onclick="closeModal('extra-modal')" class="px-4 py-2 text-gray-500">Annuleren</button><button type="submit" class="bg-black text-white px-4 py-2 rounded font-bold">Opslaan</button></div></form></div></div>
    <div id="receipt-area" style="display:none;"></div><audio id="notification-sound" src="admin/sounds/new_order.wav" preload="auto"></audio>

    <script>
        lucide.createIcons(); 
        let currentOrders = []; 
        let isSoundEnabled = false; 
        let selectedOrderId = null;
        let cookingTime = 20; // Varsayılan değerler
        let deliveryTime = 30; // Varsayılan değerler

        function openModal(id) { document.getElementById(id).classList.remove('hidden'); if(id==='product-modal') resetProductForm(); if(id==='cat-modal') resetCatForm(); if(id==='extra-modal') resetExtraForm(); }
        function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
        function resetProductForm() { document.getElementById('pm-title').innerText="Product Toevoegen"; document.getElementById('pm-id').value=""; document.getElementById('pm-name').value=""; document.getElementById('pm-price').value=""; document.getElementById('pm-desc').value=""; document.getElementById('pm-img').value=""; document.querySelectorAll('.extra-checkbox').forEach(cb => cb.checked = false); 
            // Yeni ürün eklerken kategoriyi mevcut seçili kategoriye otomatik ayarla
            const currentCatId = new URLSearchParams(window.location.search).get('cat_id');
            if (currentCatId) {
                document.getElementById('pm-cat').value = currentCatId;
            }
        }
        function editProduct(p) { 
            openModal('product-modal'); 
            document.getElementById('pm-title').innerText="Product Bewerken"; 
            document.getElementById('pm-id').value=p.id; 
            document.getElementById('pm-name').value=p.name; 
            document.getElementById('pm-price').value=p.price; 
            document.getElementById('pm-desc').value=p.description; 
            document.getElementById('pm-cat').value=p.category_id; 
            document.getElementById('pm-img').value=p.image; 
            document.querySelectorAll('.extra-checkbox').forEach(cb => cb.checked = false); 
            if(p.linked_extras) { 
                const ids = p.linked_extras.toString().split(','); 
                ids.forEach(id => { 
                    const el = document.querySelector(`.extra-checkbox[value="${id}"]`); 
                    if(el) el.checked = true; 
                }); 
            } 
        }
        function resetCatForm(){ document.getElementById('cm-title').innerText="Categorie Toevoegen"; document.getElementById('cm-id').value=""; document.getElementById('cm-name').value=""; document.getElementById('cm-sort').value=""; }
        function editCat(c){ openModal('cat-modal'); document.getElementById('cm-title').innerText="Categorie Bewerken"; document.getElementById('cm-id').value=c.id; document.getElementById('cm-name').value=c.name; document.getElementById('cm-sort').value=c.sort_order; }
        function resetExtraForm(){ document.getElementById('em-title').innerText="Extra Toevoegen"; document.getElementById('em-id').value=""; document.getElementById('em-name').value=""; document.getElementById('em-price').value=""; }
        function editExtra(e){ openModal('extra-modal'); document.getElementById('em-title').innerText="Extra Bewerken"; document.getElementById('em-id').value=e.id; document.getElementById('em-name').value=e.name; document.getElementById('em-price').value=e.price; }
        function filterMenu() { let input = document.getElementById('menu-search').value.toLowerCase(); document.querySelectorAll('.menu-item').forEach(item => { item.style.display = item.querySelector('.menu-item-name').innerText.toLowerCase().includes(input) ? 'flex' : 'none'; }); document.querySelectorAll('.menu-category').forEach(cat => { cat.style.display = cat.querySelectorAll('.menu-item[style="display: flex;"]').length > 0 ? 'block' : 'none'; }); }
        async function toggleProduct(id, status) { 
            const fd = new FormData(); 
            fd.append('action', 'toggle_product_status'); 
            fd.append('id', id); 
            fd.append('status', status ? 1 : 0); 
            const res = await fetch('', { method: 'POST', body: fd }); 
            if (res.ok) {
                 // Buton ikonlarını güncelle (gerekirse)
                 lucide.createIcons();
            }
        }
        function enableSound() { const a = document.getElementById('notification-sound'); a.play().then(() => { a.pause(); a.currentTime=0; isSoundEnabled=true; document.getElementById('sound-btn').innerHTML = `<i data-lucide="volume-2" size="18"></i> Geluid Aan`; document.getElementById('sound-btn').classList.replace('bg-gray-100','bg-green-100'); document.getElementById('sound-btn').classList.replace('text-gray-500','text-green-700'); lucide.createIcons(); }).catch(e=>alert("Browser geblokkeerd!")); }
        function playSound(){ if(isSoundEnabled){ const a=document.getElementById('notification-sound'); a.currentTime=0; a.play().catch(e=>{}); } }

        <?php if($page == 'live_orders'): ?>
        document.addEventListener('DOMContentLoaded', () => { 
            fetchData(); 
            // Her 5 saniyede bir veri çek
            setInterval(fetchData, 5000); 
            // Her 60 saniyede bir, kalan süreleri güncelle (sadece pending olmayanlar için)
            setInterval(updateTimers, 60000); 
        });

        function updateTimers() {
            // Sadece pending olmayan (countdown aktif olan) siparişler için kalan süreyi 1 dakika azalt
            currentOrders = currentOrders.map(order => {
                if (order.status !== 'pending' && order.timerValue > 0) {
                    order.timerValue -= 1;
                    // Timer 0'a ulaştığında, zamanlayıcı halkasını kırmızı yap (gerekirse)
                }
                return order;
            });
            // Listeyi yeniden render et
            renderLiveOrderList(currentOrders);
            // Seçili sipariş detayını yeniden render et
            if (selectedOrderId) {
                const ord = currentOrders.find(o => o.id == selectedOrderId);
                if (ord) renderLiveOrderDetail(ord);
            }
        }

        async function fetchData() { 
            try { 
                const res = await fetch('?api=get_live_data'); 
                const text = await res.text(); 
                let data; 
                try { 
                    data = JSON.parse(text); 
                } catch(e) { 
                    // API yanıtı JSON değilse (örneğin hata mesajı)
                    console.error("API Response is not valid JSON:", text);
                    return; 
                } 
                
                if(data.status !== 'success') return; 
                
                // Ayarları güncelle
                if (data.settings) {
                    cookingTime = parseInt(data.settings.cooking_time || 20);
                    deliveryTime = parseInt(data.settings.delivery_time || 30);
                }

                const orders = data.orders; 
                if (orders.length > currentOrders.length) { 
                    const maxOld = currentOrders.length ? Math.max(...currentOrders.map(o=>o.id)) : 0; 
                    const maxNew = Math.max(...orders.map(o=>o.id)); 
                    if (maxNew > maxOld) playSound(); 
                } 
                
                // Geri sayım değerlerini korumak için, yeni veriyi mevcut veriye map et
                const mergedOrders = orders.map(newOrder => {
                    const existingOrder = currentOrders.find(o => o.id === newOrder.id);
                    if (existingOrder && existingOrder.status === newOrder.status) {
                        // Statü değişmediyse, timer değerini koru (updateTimers() ile güncellenecek)
                        newOrder.timerValue = existingOrder.timerValue;
                    } else if (newOrder.status !== 'pending') {
                         // Statü pending'den çıktıysa, countdown'ı sıfırla/başlat
                        newOrder.timerValue = newOrder.estimatedRemainingMin; 
                    } else {
                         // Pending ise geçen süreyi göster
                        newOrder.timerValue = newOrder.timerValue; // API'dan gelen elapsed süresi
                    }
                    return newOrder;
                });
                
                currentOrders = mergedOrders; 
                renderLiveOrderList(currentOrders); 
                
                if(selectedOrderId) { 
                    const ord = currentOrders.find(o => o.id == selectedOrderId); 
                    if(ord) renderLiveOrderDetail(ord); 
                    else { 
                        selectedOrderId = null; 
                        document.getElementById('order-detail-view').innerHTML = '<div class="h-full flex flex-col items-center justify-center text-gray-400"><p>Bestelling niet gevonden of afgerond.</p></div>'; 
                    } 
                } 
            } catch (e) { 
                console.error("Fout bij het ophalen van bestellingen:", e);
            } 
        }

        function renderLiveOrderList(orders) { 
            const listEl = document.getElementById('order-list'); 
            if(!listEl) return; 
            
            if (orders.length === 0) { 
                listEl.innerHTML = `<div class="text-center py-10 text-gray-400">Geen actieve bestellingen</div>`; 
                return; 
            } 
            
            listEl.innerHTML = orders.map(order => { 
                // Geri sayım mantığı
                const timerValue = order.timerValue !== undefined ? order.timerValue : order.estimatedRemainingMin;
                
                let timeDisplay, timerClass, timeLabel;
                if (order.status === 'pending') {
                    timeDisplay = `${timerValue}m`;
                    timerClass = 'active';
                    timeLabel = 'min';
                } else {
                    timeDisplay = `${timerValue}m`;
                    // Kalan süre 0'a yakınsa/geçtiyse kırmızı yap
                    timerClass = timerValue <= 5 ? 'bg-red-500 text-white' : (timerValue <= 15 ? 'bg-yellow-500 text-white' : 'active');
                    timeLabel = 'over';
                }
                
                const isSelected = selectedOrderId === order.id ? 'bg-orange-50 border-orange-200 shadow-sm' : 'bg-white border-gray-100 hover:bg-gray-50'; 
                const statusColor = order.status === 'pending' ? 'text-red-500 bg-red-50' : (order.status==='confirmed' ? 'text-orange-500 bg-orange-50' : 'text-blue-500 bg-blue-50'); 
                
                return `<div onclick="selectOrder(${order.id})" class="cursor-pointer p-4 rounded-xl border transition-all mb-2 ${isSelected}">
                            <div class="flex items-center gap-4">
                                <div class="timer-circle ${timerClass} w-12 h-12 text-sm bg-white">${timeDisplay}</div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start mb-1">
                                        <h4 class="font-bold text-gray-800 truncate">${order.customer_name}</h4>
                                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded ${statusColor}">${order.status}</span>
                                    </div>
                                    <div class="flex justify-between items-end">
                                        <p class="text-xs text-gray-400 font-mono">#${order.id}</p>
                                        <p class="text-xs font-bold text-gray-600">€${parseFloat(order.total_price).toFixed(2)}</p>
                                    </div>
                                </div>
                            </div>
                        </div>`; 
            }).join(''); 
        }

        function selectOrder(id) { selectedOrderId = id; renderLiveOrderList(currentOrders); renderLiveOrderDetail(currentOrders.find(o => o.id === id)); }
        
        function renderLiveOrderDetail(order) { 
            const view = document.getElementById('order-detail-view'); 
            if(!view || !order) return; 
            
            // Kupon ve İndirim Düzeltmesi
            const discountAmount = parseFloat(order.discount_amount || 0);
            const couponCode = order.coupon_code || '';
            const deliveryFee = parseFloat(order.delivery_fee || 0);
            // SubTotal (Kupon uygulanmadan önceki net ürün tutarı)
            const subTotalWithoutDiscount = parseFloat(order.total_price) - deliveryFee + discountAmount;
            
            // Geri sayım
            const timerValue = order.timerValue !== undefined ? order.timerValue : order.estimatedRemainingMin;
            const timerClass = timerValue <= 5 ? 'bg-red-500 text-white' : (timerValue <= 15 ? 'bg-yellow-500 text-white' : 'bg-gray-100 text-gray-800');
            const timeLabel = order.status === 'pending' ? 'geleden' : 'over';
            
            let buttons = ''; 
            if(order.status === 'pending') { buttons = `<button onclick="updateStatus(${order.id}, 'confirmed')" class="flex-1 py-4 bg-orange-600 hover:bg-orange-700 text-white font-bold rounded-xl shadow-lg text-lg flex items-center justify-center gap-2"><i data-lucide="check-circle"></i> ACCEPTEREN</button>`; } 
            else if(order.status === 'confirmed') { buttons = `<button onclick="updateStatus(${order.id}, 'preparing')" class="flex-1 py-4 bg-black hover:bg-gray-800 text-white font-bold rounded-xl shadow-lg text-lg flex items-center justify-center gap-2"><i data-lucide="flame"></i> NAAR KEUKEN</button>`; } 
            else if(order.status === 'preparing') { const next = order.delivery_type === 'delivery' ? 'delivering' : 'completed'; const text = order.delivery_type === 'delivery' ? 'KLAAR VOOR BEZORGER' : 'KLAAR (AFHALEN)'; const icon = order.delivery_type === 'delivery' ? 'bike' : 'shopping-bag'; buttons = `<button onclick="updateStatus(${order.id}, '${next}')" class="flex-1 py-4 bg-yellow-500 hover:bg-yellow-600 text-white font-bold rounded-xl shadow-lg text-lg flex items-center justify-center gap-2"><i data-lucide="${icon}"></i> ${text}</button>`; } 
            else if(order.status === 'delivering') { buttons = `<button onclick="updateStatus(${order.id}, 'completed')" class="flex-1 py-4 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl shadow-lg text-lg flex items-center justify-center gap-2"><i data-lucide="check"></i> AFRONDEN</button>`; } 
            
            const itemsHtml = order.items.map(item => { let ex=''; try { const parsed=JSON.parse(item.extras); if(parsed&&parsed.length) ex=`<div class="text-xs text-gray-500 mt-1 ml-6 flex flex-wrap gap-1">${parsed.map(e=>`<span class="bg-gray-100 px-1 rounded">+${e.name}</span>`).join('')}</div>`; } catch(e){} return `<div class="py-3 border-b border-gray-100 last:border-0"><div class="flex justify-between items-start"><div><span class="font-bold text-gray-800 mr-3 w-6 inline-block">${item.quantity}x</span><span class="text-gray-700 font-medium">${item.product_name}</span></div><span class="font-bold text-gray-800">€${parseFloat(item.total_price).toFixed(2)}</span></div>${ex}</div>`; }).join(''); 
            
            const discountHtml = (discountAmount > 0) ? `
                <div class="flex justify-between items-center text-green-600 font-bold text-lg mb-2">
                    <span>Korting (${couponCode})</span>
                    <span>-€${discountAmount.toFixed(2)}</span>
                </div>
            ` : ''; 
            
            view.innerHTML = `<div class="max-w-3xl mx-auto h-full flex flex-col"><div class="flex justify-between items-start mb-6 pb-6 border-b border-gray-100"><div><h2 class="text-3xl font-black text-gray-900 mb-1 leading-none tracking-tight">Bestelling #${order.id}</h2><p class="text-gray-500 text-sm font-medium mt-1 flex items-center gap-2"><i data-lucide="clock" size="14"></i> ${order.created_at}</p><div class="mt-4 flex gap-6 text-sm"><div class="flex items-center gap-2 text-gray-700 font-bold bg-gray-100 px-3 py-1.5 rounded-lg"><i data-lucide="user" size="16"></i> ${order.customer_name}</div><div class="flex items-center gap-2 text-gray-700 font-bold bg-gray-100 px-3 py-1.5 rounded-lg"><i data-lucide="phone" size="16"></i> ${order.phone}</div></div>${order.delivery_type==='delivery' ? `<div class="mt-4 p-3 bg-blue-50 border border-blue-100 rounded-xl text-blue-800 text-sm"><p class="font-bold flex items-center gap-2"><i data-lucide="map-pin" size="16"></i> Bezorgadres:</p><p class="pl-6">${order.street} ${order.house_number}, ${order.zip} ${order.city}</p></div>` : ''}</div><div class="text-right"><div class="inline-flex flex-col items-center justify-center w-20 h-20 rounded-full border-4 border-gray-100 ${timerClass} mb-2 shadow-sm"><span class="text-3xl font-black">${timerValue}</span><span class="text-[10px] uppercase font-bold">${timeLabel}</span></div><p class="text-xs font-bold uppercase tracking-wide text-gray-500 bg-gray-100 px-2 py-1 rounded text-center">${order.delivery_type}</p></div></div><div class="flex-1 overflow-y-auto mb-6 pr-2"><div class="bg-gray-50 rounded-xl p-6 mb-4 border border-gray-100 shadow-sm">${itemsHtml}</div>${order.note ? `<div class="bg-yellow-50 text-yellow-800 p-4 rounded-xl border border-yellow-200 font-bold mb-4 flex gap-3 shadow-sm"><i data-lucide="sticky-note" class="shrink-0"></i> ${order.note}</div>` : ''}</div><div class="mt-auto pt-6 border-t border-gray-100"><div class="flex justify-between items-center text-gray-500 text-sm mb-1"><span>Subtotaal (v. korting)</span><span>€${subTotalWithoutDiscount.toFixed(2)}</span></div>${discountHtml}<div class="flex justify-between items-center text-gray-500 text-sm mb-4"><span>Bezorgkosten</span><span>€${deliveryFee.toFixed(2)}</span></div><div class="flex justify-between items-center mb-6 text-xl"><span class="font-bold text-gray-500">Totaalbedrag</span><span class="font-black text-gray-900 text-3xl">€${parseFloat(order.total_price).toFixed(2)}</span></div><div class="flex gap-4">${buttons}<button onclick="printOrder(${order.id})" class="px-6 py-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl flex flex-col items-center justify-center gap-1 transition-colors border border-gray-200"><i data-lucide="printer" size="20"></i> <span class="text-xs">PRINT</span></button><button onclick="if(confirm('Annuleren?')) updateStatus(${order.id}, 'cancelled')" class="px-6 py-4 bg-red-50 hover:bg-red-100 text-red-500 font-bold rounded-xl flex flex-col items-center justify-center gap-1 transition-colors border border-red-100"><i data-lucide="x" size="20"></i> <span class="text-xs">ANNUL</span></button></div></div></div>`; 
            lucide.createIcons(); 
        }

        async function updateStatus(id, status) { 
            const fd = new FormData(); 
            fd.append('action', 'update_status'); 
            fd.append('order_id', id); 
            fd.append('status', status); 
            await fetch('', { method: 'POST', body: fd }); 
            
            // Eğer confirmed (onaylandı) durumuna geçiyorsa, geri sayımı hemen başlat
            if (status === 'confirmed') {
                const order = currentOrders.find(o => o.id == id);
                if (order) {
                    // API'dan gelen estimatedRemainingMin değerini kullan
                    order.timerValue = order.estimatedRemainingMin;
                }
            }
            
            fetchData(); 
            if(status === 'cancelled' || status === 'completed') { selectedOrderId = null; document.getElementById('order-detail-view').innerHTML = '<div class="h-full flex flex-col items-center justify-center text-gray-400"><p>Selecteer een bestelling.</p></div>'; } 
        }
        
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
            // SubTotal: Toplam - Kargo + İndirim = Kupon uygulanmadan önceki net ürün tutarı
            const subTotalWithoutDiscount = parseFloat(order.total_price) - deliveryFee + discountAmount;
            
            let itemsHtml = order.items.map(item => { 
                let ex = ''; 
                try { 
                    const parsed = JSON.parse(item.extras); 
                    if(parsed && parsed.length) ex = `<div style="font-size:11px; margin-left:10px; color:#555;">+ ${parsed.map(e=>e.name).join(', ')}</div>`; 
                } catch(e){} 
                return `<div style="margin-bottom:8px;"><div style="display:flex; justify-content:space-between; align-items:flex-start;"><span style="font-weight:bold; width:10%;">${item.quantity}x</span><span style="width:65%; font-weight:bold;">${item.product_name}</span><span style="width:25%; text-align:right;">EUR ${parseFloat(item.total_price).toFixed(2)}</span></div>${ex}</div>`; 
            }).join(''); 
            
            // İndirim satırı eklendi
            const discountPrint = (discountAmount > 0) ? `<div style="display:flex; justify-content:space-between; margin-top:5px; font-weight:bold; color:green;"><span>Korting (${couponCode})</span><span>-EUR ${discountAmount.toFixed(2)}</span></div>` : ''; 
            
            // QR Kodu: Müşteriye bilgi verecek bir URL veya basit bir doğrulama verisi
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
</body>
</html>