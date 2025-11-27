<?php
// Basit güvenlik — ürünler frontend’den gelecek
$cartJson = $_GET["cart"] ?? "[]";
$cart = json_decode($cartJson, true);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Smaaky – Afrekenen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin:0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background:#f7f7f7;
            color:#111;
        }
        .container {
            max-width:580px;
            margin:0 auto;
            padding:20px;
        }
        .header {
            display:flex;
            align-items:center;
            gap:12px;
            padding:16px 20px;
            background:white;
            border-bottom:1px solid #eee;
            position:sticky;
            top:0;
            z-index:10;
        }
        .back-btn {
            border:none;
            background:none;
            font-size:22px;
            cursor:pointer;
        }
        h1 {
            font-size:24px;
            margin:0;
        }
        .section-title {
            font-size:18px;
            margin:25px 0 10px;
            font-weight:600;
        }
        .input-group {
            display:flex;
            flex-direction:column;
            margin-bottom:18px;
        }
        label {
            font-size:13px;
            margin-bottom:6px;
        }
        input {
            padding:12px;
            border-radius:10px;
            border:1px solid #ddd;
            font-size:15px;
        }
        .toggle-row {
            display:flex;
            background:#eee;
            border-radius:10px;
            padding:4px;
            margin:10px 0 25px;
        }
        .toggle-btn {
            flex:1;
            padding:12px;
            text-align:center;
            border-radius:8px;
            font-weight:600;
            cursor:pointer;
        }
        .toggle-btn.active {
            background:#E86A33;
            color:white;
        }

        .cart-card {
            background:white;
            padding:15px;
            border-radius:12px;
            margin-bottom:15px;
        }

        .cart-item {
            margin-bottom:12px;
        }
        .cart-item:last-child {
            margin-bottom:0;
        }
        .item-title {
            font-weight:600;
        }
        .extras {
            font-size:13px;
            color:#555;
            margin-left:6px;
            margin-top:2px;
        }

        .totals-box {
            background:white;
            border-radius:20px 20px 0 0;
            padding:20px;
            position:fixed;
            bottom:0;
            left:0;
            right:0;
            box-shadow:0 -4px 15px rgba(0,0,0,0.1);
        }
        .total-row {
            display:flex;
            justify-content:space-between;
            margin-bottom:8px;
            font-size:15px;
        }
        .total-row strong {
            font-size:17px;
        }
        .checkout-btn {
            width:100%;
            padding:16px;
            background:#E86A33;
            color:white;
            border:none;
            border-radius:12px;
            font-size:18px;
            font-weight:600;
            cursor:pointer;
            margin-top:10px;
        }

        .spacer {
            height:150px;
        }
    </style>
</head>
<body>

<div class="header">
    <button class="back-btn" onclick="history.back()">←</button>
    <h1>Afrekenen</h1>
</div>

<div class="container">

    <!-- BEZORG / AFHALEN -->
    <div class="section-title">Bezorgmethode</div>
    <div class="toggle-row">
        <div class="toggle-btn active" id="toggle-delivery">Bezorgd</div>
        <div class="toggle-btn" id="toggle-pickup">Afhalen</div>
    </div>

    <!-- ADRES FORM -->
    <div class="section-title">Jouw gegevens</div>

    <div class="input-group">
        <label>Naam</label>
        <input type="text" id="co_name">
    </div>
    <div class="input-group">
        <label>Telefoonnummer</label>
        <input type="text" id="co_phone">
    </div>
    <div class="input-group">
        <label>E-mail</label>
        <input type="email" id="co_email">
    </div>
    <div class="input-group">
        <label>Straat + Huisnummer</label>
        <input type="text" id="co_street">
    </div>
    <div class="input-group">
        <label>Postcode</label>
        <input type="text" id="co_zip">
    </div>
    <div class="input-group">
        <label>Plaats</label>
        <input type="text" id="co_city" value="Rotterdam">
    </div>

    <!-- ORDER OVERVIEW -->
    <div class="section-title">Jouw bestelling</div>

    <div class="cart-card">
        <?php foreach ($cart as $item): ?>
            <div class="cart-item">
                <div class="item-title"><?= htmlspecialchars($item["name"]) ?></div>
                <div><?= $item["qty"] ?> × € <?= number_format($item["price"],2) ?></div>

                <?php if (!empty($item["extras"])): ?>
                    <div class="extras">
                        <?php foreach ($item["extras"] as $ex): ?>
                            • <?= htmlspecialchars($ex["name"]) ?> (+€ <?= number_format($ex["price"],2) ?>)<br>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- STICKY TOTAL BOX -->
<div class="totals-box">
    <div class="total-row">
        <span>Subtotaal</span>
        <span id="subtotal">€ 0,00</span>
    </div>
    <div class="total-row">
        <span>Bezorgkosten</span>
        <span id="delivery">€ 0,00</span>
    </div>
    <div class="total-row">
        <strong>Totaal</strong>
        <strong id="total">€ 0,00</strong>
    </div>

    <button class="checkout-btn" onclick="submitOrder()">Bestelling plaatsen</button>
</div>

<script>
// MODE TOGGLE
let mode = "delivery";
document.getElementById("toggle-delivery").onclick = () => {
    mode = "delivery";
    toggle();
};
document.getElementById("toggle-pickup").onclick = () => {
    mode = "pickup";
    toggle();
};
function toggle(){
    document.getElementById("toggle-delivery").classList.toggle("active", mode==="delivery");
    document.getElementById("toggle-pickup").classList.toggle("active", mode==="pickup");
}

// TOTALS LOAD (FROM FRONTEND)
<?php
$subtotal = 0;
foreach ($cart as $item){
    $line = $item["qty"] * $item["price"];
    foreach ($item["extras"] ?? [] as $ex){
        $line += $ex["price"];
    }
    $subtotal += $line;
}
$delivery = 2.50;
$total = $subtotal + $delivery;
?>

document.getElementById("subtotal").innerText = "€ <?= number_format($subtotal,2) ?>";
document.getElementById("delivery").innerText = "€ <?= number_format($delivery,2) ?>";
document.getElementById("total").innerText      = "€ <?= number_format($total,2) ?>";

// SUBMIT
function submitOrder(){
    const order = {
        mode,
        customer_name: document.getElementById("co_name").value,
        phone: document.getElementById("co_phone").value,
        email: document.getElementById("co_email").value,
        street: document.getElementById("co_street").value,
        zip: document.getElementById("co_zip").value,
        city: document.getElementById("co_city").value,

        subtotal: <?= $subtotal ?>,
        delivery_fee: <?= $delivery ?>,
        total: <?= $total ?>,
        items: <?= json_encode($cart) ?>
    };

    fetch("api/place_order.php", {
        method:"POST",
        headers:{"Content-Type":"application/json"},
        body:JSON.stringify(order)
    })
    .then(res=>res.json())
    .then(data=>{
        if(data.status==="success"){
            alert("Bestelling geplaatst! 🎉");
            window.location = "/";
        } else {
            alert("Hata: "+data.message);
        }
    })
    .catch(err=>{
        alert("Sunucu hatası: "+err);
    });
}
</script>

</body>
</html>