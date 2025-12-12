/**
 * Smaaky Admin Panel JavaScript
 */

let lastKnownOrderCount = -1;
let lastNotifiedOrderId = 0;
const newOrderSound = new Audio('../admin/sounds/new_order.wav');

// Tarayıcı Bildirim İzni İsteği
function requestNotificationPermission() {
    if (Notification.permission !== 'granted') {
        Notification.requestPermission();
    }
}

// Yeni Sipariş Bildirimini Göster
function showNewOrderNotification(count, orderId) {
    if (Notification.permission === 'granted' && orderId > lastNotifiedOrderId) {
        new Notification("NIEUWE BESTELLING!", {
            body: `${count} nieuwe bestelling(en) staan klaar om verwerkt te worden.`,
            icon: '../admin/assets/smaaky-logo.svg' 
        });
        lastNotifiedOrderId = orderId;
    }
}

// Yeni siparişleri kontrol etme fonksiyonu
async function checkNewOrders() {
    try {
        const response = await fetch('../admin/orders_check_new.php');
        const data = await response.json();

        if (data.success) {
            const currentCount = data.count;
            const lastId = data.last_id;

            // 1. Sidebar'daki sayacı güncelle
            const badge = document.getElementById('new-orders-badge');
            if (badge) {
                badge.textContent = currentCount;
                badge.style.display = currentCount > 0 ? 'block' : 'none';
            }

            // 2. Yeni sipariş geldi mi kontrol et
            if (lastKnownOrderCount !== -1 && currentCount > lastKnownOrderCount) {
                // Sadece yeni sipariş varsa ses çal ve bildirimi göster
                newOrderSound.play().catch(e => console.error("Geluid kon niet worden afgespeeld:", e));
                showNewOrderNotification(currentCount, lastId);
            }
            
            // 3. Bilinen son sipariş sayısını güncelle
            lastKnownOrderCount = currentCount;
        }

    } catch (error) {
        console.error("Fout bij het controleren van nieuwe bestellingen:", error);
    }
}


// Admin panelinde çalışacak ana fonksiyon
document.addEventListener('DOMContentLoaded', () => {
    
    // Tarayıcı bildirim iznini iste
    requestNotificationPermission();

    // İlk kontrolü hemen yap
    checkNewOrders(); 

    // Her 10 saniyede bir kontrol et
    setInterval(checkNewOrders, 10000); 

    // --- Mevcut Admin İşlevselliği ---

    // Sidebar Toggling (Varsayılır)
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('minimized');
        });
    }

    // Modal İşlevselliği (Varsayılır)
    // Sizin sisteminizde modal varsa buraya eklenebilir.
    document.querySelectorAll('[data-modal-target]').forEach(button => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-modal-target');
            const modal = document.getElementById(targetId);
            if (modal) {
                modal.classList.add('is-active');
            }
        });
    });

    document.querySelectorAll('.modal-close, .modal-background').forEach(closer => {
        closer.addEventListener('click', () => {
            closer.closest('.modal').classList.remove('is-active');
        });
    });
});