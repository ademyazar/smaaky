<?php
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Smaaky – Bestelling ontvangen</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { 
            font-family: Arial; 
            background:#fafafa; 
            text-align:center;
            padding:40px;
        }
        .box {
            max-width:450px;
            margin:auto;
            background:white;
            padding:40px;
            border-radius:20px;
            box-shadow:0 5px 25px rgba(0,0,0,0.1);
        }
        .checkmark {
            font-size:60px;
            color:#4CAF50;
            margin-bottom:20px;
        }
        a {
            display:inline-block;
            margin-top:20px;
            font-size:18px;
            text-decoration:none;
            color:white;
            background:black;
            padding:12px 25px;
            border-radius:10px;
        }
    </style>
</head>
<body>

<div class="box">
    <div class="checkmark">✔</div>
    <h2>Bedankt voor je bestelling!</h2>
    <p>Je bestelling is succesvol ontvangen.</p>

    <?php if ($order_id): ?>
        <p>Bestelnummer: <strong>#<?= $order_id ?></strong></p>
    <?php endif; ?>

    <a href="/">Terug naar home</a>
</div>

</body>
</html>