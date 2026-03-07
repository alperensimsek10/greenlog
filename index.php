<!DOCTYPE html>
<html lang="tr">
<head>
    <link rel="shortcut icon" href="images/logo1.png" type="image/x-icon">
    <link rel="stylesheet" href="style_login.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenLog - Giriş</title>

    <style>
        .error {
            background: #ffcccc;
            color: #cc0000;
            padding: 10px;
            width: 100%;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 14px;
            border: 1px solid #cc0000;
        }
    </style>
</head>
<body>
<div class="kutu">
    <img src="images/logo.png" alt="GreenLog Logo">
    <form action="login.php" method="POST">
        <h1>Giriş Ekranı</h1>

        <?php if (isset($_GET['error'])) { ?>
            <div class="error"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php } ?>

        <div class="input-box">
            <input id="usernamek" name="username" type="text" placeholder="TC Kimlik No" required>
            <i class='bx bxs-user'></i>
        </div>
        <div class="input-box">
            <input id="passwordk" name="password" type="password" placeholder="Şifreniz" required>
            <i class='bx bxs-lock-alt'></i>
        </div>
        <button type="submit" class="button">Giriş Yap</button>
    </form>
</div>
</body>
</html>