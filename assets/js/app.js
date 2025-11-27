// ===============================================
// SMAAKY FRONTEND – app.js (FINAL BUILD)
// ===============================================

// ---------- GLOBAL STATE ----------
const state = {
    categories: [],
    products: [],
    extras: [],
    cartMode: "delivery",
    cartItems: [],
    deliveryFeeDelivery: 2.50,
    deliveryFeePickup: 0.00,
};

const storeStatus = {
    open: true,
    delivery_paused: false,
    pickup_paused: false
};

// ---------- DOM CACHE ----------
const dom = {};

function cacheDom() {
    dom.categoryPills          = document.getElementById("category-pills");
    dom.menuSections           = document.getElementById("menu-sections");

    // Desktop cart
    dom.cartItemsDesktop       = document.getElementById("cart-items");
    dom.cartSubtotal           = document.getElementById("cart-subtotal");
    dom.cartDeliveryFee        = document.getElementById("cart-delivery-fee");
    dom.cartTotal              = document.getElementById("cart-total");

    // Mobile cart
    dom.cartItemsMobile        = document.getElementById("cart-items-mobile");
    dom.cartSubtotalMobile     = document.getElementById("cart-subtotal-mobile");
    dom.cartDeliveryFeeMobile  = document.getElementById("cart-delivery-fee-mobile");
    dom.cartTotalMobile        = document.getElementById("cart-total-mobile");

    // Checkout buttons
    dom.btnCheckout            = document.getElementById("btn-checkout");
    dom.btnCheckoutMobile      = document.getElementById("btn-checkout-mobile");

    // Delivery/Pickup controls
    dom.btnDelivery            = document.getElementById("btn-delivery");
    dom.btnPickup              = document.getElementById("btn-pickup");
    dom.btnDeliveryMobile      = document.getElementById("btn-delivery-mobile");
    dom.btnPickupMobile        = document.getElementById("btn-pickup-mobile");

    dom.cartInfoText           = document.getElementById("cart-info-text");
    dom.cartInfoTextMobile     = document.getElementById("cart-info-text-mobile");

    // Mobile cart drawer
    dom.mobileCartBar          = document.getElementById("mobile-cart-bar");
    dom.mobileCartOverlay      = document.getElementById("mobile-cart-overlay");
    dom.mobileCartClose        = document.getElementById("mobile-cart-close");
    dom.mobileCartLabel        = document.getElementById("mobile-cart-label");
    dom.mobileCartTotal        = document.getElementById("mobile-cart-total");

    // Extras modal
    dom.extrasModalBackdrop    = document.getElementById("extras-modal-backdrop");
    dom.extrasList             = document.getElementById("extras-list");
    dom.modalProductName       = document.getElementById("modal-product-name");
    dom.modalProductPrice      = document.getElementById("modal-product-price");
    dom.modalAddBtn            = document.getElementById("modal-add-btn");
    dom.modalCloseBtn          = document.getElementById("modal-close-btn");

    dom.storeStatusBar         = document.getElementById("store-status-bar");
    dom.openBadge              = document.getElementById("open-badge");
}

// ---------- HELPERS ----------
async function fetchJson(url) {
    const res = await fetch(url, { cache: "no-cache" });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

function formatPrice(v) {
    v = Number(v) || 0;
    return "€ " + v.toFixed(2).replace(".", ",");
}

// ---------- LOAD DATA ----------
async function loadData() {
    try {
        const [catJson, prodJson, extrasJson] = await Promise.all([
            fetchJson("api/categories.php"),
            fetchJson("api/products.php"),
            fetchJson("api/extras.php")
        ]);

        state.categories = catJson.data || [];
        state.products   = (prodJson.data || []).filter(p => Number(p.is_active) === 1);
        state.extras     = (extrasJson.data || []).filter(e => Number(e.is_active) === 1);

        renderCategoriesAndMenu();
        updateCartUI();
    } catch (err) {
        console.error("Menu can't load:", err);
        alert("Menu kon niet laden.");
    }
}

// ---------- RENDER MENU ----------
function renderCategoriesAndMenu() {
    dom.categoryPills.innerHTML = "";
    dom.menuSections.innerHTML  = "";

    state.categories.forEach((cat, idx) => {

        // Pills (scroll nav)
        const pill = document.createElement("button");
        pill.className = "category-pill";
        pill.textContent = cat.name;
        pill.dataset.categoryId = cat.id;

        if (idx === 0) pill.classList.add("active");

        pill.addEventListener("click", () => {
            document.querySelectorAll(".category-pill")
                .forEach(p => p.classList.remove("active"));
            pill.classList.add("active");
            scrollToCategory(cat.id);
        });

        dom.categoryPills.appendChild(pill);

        // Category section
        const section = document.createElement("section");
        section.className = "menu-section";
        section.id = `cat-${cat.id}`;

        const title = document.createElement("h3");
        title.className = "menu-section-title";
        title.textContent = cat.name;
        section.appendChild(title);

        const grid = document.createElement("div");
        grid.className = "product-grid";

        const prods = state.products.filter(
            p => Number(p.category_id) === Number(cat.id)
        );

        if (prods.length === 0) {
            const empty = document.createElement("p");
            empty.className = "menu-empty";
            empty.textContent = "Geen producten in deze categorie.";
            grid.appendChild(empty);
        } else {
            prods.forEach(prod => {
                grid.appendChild(createProductCard(prod));
            });
        }

        section.appendChild(grid);
        dom.menuSections.appendChild(section);
    });
}

function createProductCard(prod) {
    const card = document.createElement("article");
    card.className = "product-card";

    const img = document.createElement("div");
    img.className = "product-image";

    const url = prod.image_url || prod.image || "";
    if (url) img.style.backgroundImage = `url('${url.replace(/'/g, "\\'")}')`;

    card.appendChild(img);

    const body = document.createElement("div");
    body.className = "product-body";

    const name = document.createElement("h4");
    name.className = "product-name";
    name.textContent = prod.name;
    body.appendChild(name);

    const desc = document.createElement("p");
    desc.className = "product-desc";
    desc.textContent = prod.description || "";
    body.appendChild(desc);

    const bottom = document.createElement("div");
    bottom.className = "product-bottom";

    const price = document.createElement("div");
    price.className = "product-price";
    price.textContent = formatPrice(prod.price);
    bottom.appendChild(price);

    const btn = document.createElement("button");
    btn.className = "product-add-btn";
    btn.textContent = "Toevoegen";
    btn.addEventListener("click", () => openExtrasOrAdd(prod));
    bottom.appendChild(btn);

    body.appendChild(bottom);
    card.appendChild(body);
    return card;
}

function scrollToCategory(id) {
    const section = document.getElementById(`cat-${id}`);
    if (!section) return;
    const offset = section.getBoundingClientRect().top + window.scrollY - 80;
    window.scrollTo({ top: offset, behavior: "smooth" });
}

// ---------- EXTRAS MODAL ----------
let currentModalProduct = null;
let currentModalExtras = new Set();

function openExtrasOrAdd(prod) {
    const hasExtras = Number(prod.has_extras) === 1 && state.extras.length > 0;

    if (!hasExtras) {
        addProductToCart(prod, []);
        return;
    }

    currentModalProduct = prod;
    currentModalExtras = new Set();

    dom.modalProductName.textContent = prod.name;
    dom.modalProductPrice.textContent = formatPrice(prod.price);
    dom.modalAddBtn.textContent = "Toevoegen • " + formatPrice(prod.price);

    dom.extrasList.innerHTML = "";

    state.extras.forEach(ex => {
        const row = document.createElement("button");
        row.type = "button";
        row.className = "extra-row";
        row.dataset.extraId = ex.id;

        const left = document.createElement("span");
        left.className = "extra-name";
        left.textContent = ex.name;

        const right = document.createElement("span");
        right.className = "extra-price";
        right.textContent = "+ " + formatPrice(ex.price);

        row.appendChild(left);
        row.appendChild(right);

        row.addEventListener("click", () => toggleExtraSelection(ex, row));
        dom.extrasList.appendChild(row);
    });

    dom.extrasModalBackdrop.classList.add("visible");
}

function closeExtrasModal() {
    dom.extrasModalBackdrop.classList.remove("visible");
}

function toggleExtraSelection(ex, row) {
    if (currentModalExtras.has(ex.id)) {
        currentModalExtras.delete(ex.id);
        row.classList.remove("selected");
    } else {
        currentModalExtras.add(ex.id);
        row.classList.add("selected");
    }
    updateModalPrice();
}

function updateModalPrice() {
    const base = Number(currentModalProduct.price);
    let extrasTotal = 0;

    currentModalExtras.forEach(id => {
        const ex = state.extras.find(e => Number(e.id) === Number(id));
        extrasTotal += Number(ex.price) || 0;
    });

    const total = base + extrasTotal;

    dom.modalProductPrice.textContent = formatPrice(base);
    dom.modalAddBtn.textContent = "Toevoegen • " + formatPrice(total);
}

function confirmExtras() {
    const selectedExtras = [];

    currentModalExtras.forEach(id => {
        const ex = state.extras.find(e => Number(e.id) === Number(id));
        selectedExtras.push({
            id: ex.id,
            name: ex.name,
            price: Number(ex.price)
        });
    });

    addProductToCart(currentModalProduct, selectedExtras);
    closeExtrasModal();
}

// ---------- CART ----------
function addProductToCart(prod, extras) {
    state.cartItems.push({
        id: Date.now() + "-" + Math.random().toString(36).slice(2),
        productId: prod.id,
        name: prod.name,
        qty: 1,
        unitPrice: Number(prod.price),
        extras: extras
    });
    updateCartUI();
}

function changeCartQty(id, delta) {
    const item = state.cartItems.find(i => i.id === id);
    if (!item) return;

    item.qty += delta;
    if (item.qty <= 0) {
        state.cartItems = state.cartItems.filter(i => i.id !== id);
    }
    updateCartUI();
}

function renderCartItem(item) {
    const wrapper = document.createElement("div");
    wrapper.className = "cart-item";

    const main = document.createElement("div");
    main.className = "cart-item-main";

    const left = document.createElement("div");
    left.className = "cart-item-left";

    const title = document.createElement("div");
    title.className = "cart-item-title";
    title.textContent = item.name;
    left.appendChild(title);

    const qtyRow = document.createElement("div");
    qtyRow.className = "cart-item-qty-row";
    qtyRow.textContent =
        `${item.qty} × ${formatPrice(item.unitPrice)}`;
    left.appendChild(qtyRow);

    item.extras.forEach(ex => {
        const r = document.createElement("div");
        r.className = "cart-item-extra";
        r.textContent = `+ ${ex.name} (${formatPrice(ex.price)})`;
        left.appendChild(r);
    });

    const right = document.createElement("div");
    right.className = "cart-item-right";

    const minus = document.createElement("button");
    minus.className = "cart-qty-btn";
    minus.textContent = "–";
    minus.addEventListener("click", () => changeCartQty(item.id, -1));

    const qty = document.createElement("span");
    qty.className = "cart-qty";
    qty.textContent = item.qty;

    const plus = document.createElement("button");
    plus.className = "cart-qty-btn";
    plus.textContent = "+";
    plus.addEventListener("click", () => changeCartQty(item.id, +1));

    right.appendChild(minus);
    right.appendChild(qty);
    right.appendChild(plus);

    main.appendChild(left);
    main.appendChild(right);

    const total = document.createElement("div");
    total.className = "cart-item-total";

    const extrasTotal = item.extras.reduce((a, e) => a + Number(e.price), 0);
    total.textContent = formatPrice((item.unitPrice + extrasTotal) * item.qty);

    wrapper.appendChild(main);
    wrapper.appendChild(total);

    return wrapper;
}

function updateCartUI() {
    let subtotal = 0;
    state.cartItems.forEach(item => {
        const extrasTotal = item.extras.reduce((a, e) => a + Number(e.price), 0);
        subtotal += (item.unitPrice + extrasTotal) * item.qty;
    });

    const fee = state.cartMode === "delivery"
        ? state.deliveryFeeDelivery
        : state.deliveryFeePickup;

    const total = subtotal + fee;

    // Desktop
    dom.cartItemsDesktop.innerHTML = "";
    if (state.cartItems.length === 0) {
        dom.cartItemsDesktop.innerHTML = '<p class="cart-empty">Je winkelmandje is nog leeg.</p>';
    } else {
        state.cartItems.forEach(item =>
            dom.cartItemsDesktop.appendChild(renderCartItem(item))
        );
    }

    dom.cartSubtotal.textContent     = formatPrice(subtotal);
    dom.cartDeliveryFee.textContent  = formatPrice(fee);
    dom.cartTotal.textContent        = formatPrice(total);

    // Mobile
    dom.cartItemsMobile.innerHTML = "";
    if (state.cartItems.length === 0) {
        dom.cartItemsMobile.innerHTML = '<p class="cart-empty">Je winkelmandje is nog leeg.</p>';
    } else {
        state.cartItems.forEach(item =>
            dom.cartItemsMobile.appendChild(renderCartItem(item))
        );
    }

    dom.cartSubtotalMobile.textContent    = formatPrice(subtotal);
    dom.cartDeliveryFeeMobile.textContent = formatPrice(fee);
    dom.cartTotalMobile.textContent       = formatPrice(total);

    dom.mobileCartLabel.textContent = 
        `Winkelmandje (${state.cartItems.length})`;
    dom.mobileCartTotal.textContent = formatPrice(total);

    const info = state.cartMode === "delivery"
        ? "Bezorging op <strong>3068</strong>."
        : "Afhalen in de winkel.";

    dom.cartInfoText.innerHTML       = info;
    dom.cartInfoTextMobile.innerHTML = info;

    dom.btnCheckout.disabled       = state.cartItems.length === 0;
    dom.btnCheckoutMobile.disabled = state.cartItems.length === 0;
}

// ---------- CHECKOUT ----------
async function handleCheckout() {
    if (state.cartItems.length === 0) return;

    // ---- Basit form (geçici) ----
    const name   = prompt("Naam:");
    const phone  = prompt("Telefoon:");
    const email  = prompt("E-mail:");
    const address = prompt("Straat + huisnummer:");
    const zip    = prompt("Postcode:");
    const city   = prompt("Plaats:");

    if (!name || !phone || !address || !zip || !city) {
        alert("Vul alle velden in.");
        return;
    }

    // Straat + huisnummer ayrımı
    const parts = address.trim().split(" ");
    const house_number = parts.pop();
    const street = parts.join(" ");

    // ---- SUBTOTAL / TOTAL ----
    let subtotal = 0;
    const itemsForApi = state.cartItems.map(i => {
        const extrasTotal = i.extras.reduce((a, e) => a + Number(e.price), 0);
        const lineTotal = (i.unitPrice + extrasTotal) * i.qty;
        subtotal += lineTotal;

        return {
            product_id: i.productId,
            product_name: i.name,
            quantity: i.qty,
            unit_price: i.unitPrice,
            extras: i.extras,
            total_price: lineTotal
        };
    });

    const delivery_fee = state.cartMode === "delivery"
        ? state.deliveryFeeDelivery
        : state.deliveryFeePickup;

    const total = subtotal + delivery_fee;

    // ---- API PAYLOAD ----
    const payload = {
        customer_name: name,
        phone: phone,
        email: email,
        street: street,
        house_number: house_number,
        zip: zip,
        city: city,
        delivery_mode: state.cartMode,
        subtotal: subtotal,
        delivery_fee: delivery_fee,
        total: total,
        items: itemsForApi
    };

    // ---- API CALL ----
    try {
        const res = await fetch("api/place_order.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify(payload)
        });

        const data = await res.json();

        if (!data || data.status !== "success") {
            alert(data.message || "Bestelling kon niet worden opgeslagen.");
            return;
        }

        // Sepeti temizle
        state.cartItems = [];
        updateCartUI();

        // Başarılı yönlendirme
        window.location.href = "order_success.php?id=" + data.order_id;

    } catch (err) {
        console.error(err);
        alert("Er ging iets mis tijdens het verzenden.");
    }
}

// ---------- STORE STATUS ----------
async function updateStoreStatus() {
    try {
        const res = await fetch("api/store_status.php", { cache: "no-cache" });
        if (!res.ok) throw new Error();
        const data = await res.json();

        storeStatus.open = !!data.open;
        storeStatus.delivery_paused = !!data.delivery_paused;
        storeStatus.pickup_paused   = !!data.pickup_paused;

        if (storeStatus.open) {
            dom.storeStatusBar.className = "store-status store-open";
            dom.storeStatusBar.textContent = "We zijn open! Nu bestellen.";
        } else {
            dom.storeStatusBar.className = "store-status store-closed";
            dom.storeStatusBar.textContent = "We zijn gesloten.";
        }

    } catch (err) {
        console.warn("store_status.php unreachable");
    }
}

// ---------- EVENTS ----------
function bindEvents() {
    dom.btnDelivery.addEventListener("click", () => {
        if (!storeStatus.delivery_paused) setCartMode("delivery");
    });

    dom.btnPickup.addEventListener("click", () => {
        if (!storeStatus.pickup_paused) setCartMode("pickup");
    });

    dom.btnDeliveryMobile.addEventListener("click", () => {
        if (!storeStatus.delivery_paused) setCartMode("delivery");
    });

    dom.btnPickupMobile.addEventListener("click", () => {
        if (!storeStatus.pickup_paused) setCartMode("pickup");
    });

    dom.btnCheckout.addEventListener("click", handleCheckout);
    dom.btnCheckoutMobile.addEventListener("click", handleCheckout);

    dom.mobileCartBar.addEventListener("click", () => {
        dom.mobileCartOverlay.classList.add("visible");
    });
    dom.mobileCartClose.addEventListener("click", () => {
        dom.mobileCartOverlay.classList.remove("visible");
    });
    dom.mobileCartOverlay.addEventListener("click", e => {
        if (e.target === dom.mobileCartOverlay) {
            dom.mobileCartOverlay.classList.remove("visible");
        }
    });

    dom.modalCloseBtn.addEventListener("click", closeExtrasModal);
    dom.extrasModalBackdrop.addEventListener("click", e => {
        if (e.target === dom.extrasModalBackdrop) closeExtrasModal();
    });
    dom.modalAddBtn.addEventListener("click", confirmExtras);
}

function setCartMode(mode) {
    state.cartMode = mode;

    dom.btnDelivery.classList.toggle("active", mode === "delivery");
    dom.btnPickup.classList.toggle("active", mode === "pickup");

    dom.btnDeliveryMobile.classList.toggle("active", mode === "delivery");
    dom.btnPickupMobile.classList.toggle("active", mode === "pickup");

    updateCartUI();
}

// ---------- INIT ----------
document.addEventListener("DOMContentLoaded", () => {
    cacheDom();
    bindEvents();
    loadData();
    updateStoreStatus();
    setInterval(updateStoreStatus, 60000);
});