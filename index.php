<?php
// 1. Veritabanı bağlantımızı dahil ediyoruz
require_once 'db-connect.php';

$mesaj = "";

// 2. Form Gönderildiyse (Veri Ekleme İşlemi)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seraAdi = $_POST['sera_adi'];
    $aciklama = $_POST['aciklama'];

    // Sera adı boş değilse kaydet
    if (!empty($seraAdi)) {
        try {
            $sql = "INSERT INTO SERA (Sera_Adi, Aciklama) VALUES (:sera_adi, :aciklama)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':sera_adi' => $seraAdi,
                ':aciklama' => $aciklama
            ]);
            $mesaj = "<div class='alert alert-success'>✅ Sera başarıyla eklendi!</div>";
        } catch (PDOException $e) {
            $mesaj = "<div class='alert alert-danger'>❌ Hata oluştu: " . $e->getMessage() . "</div>";
        }
    } else {
        $mesaj = "<div class='alert alert-warning'>⚠️ Lütfen Sera Adı alanını doldurun.</div>";
    }
}

// 3. Tabloda Göstermek İçin Tüm Seraları Çekme İşlemi (Listeleme)
$sorgu = $pdo->query("SELECT * FROM SERA ORDER BY Sera_ID DESC");
$seralar = $sorgu->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenLog - Sera Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row text-center mb-4">
        <h2 class="text-success">🌿 GreenLog Sera Yönetim Paneli</h2>
        <p class="text-muted">Sisteme yeni sera ekleyebilir ve mevcut seraları görüntüleyebilirsiniz.</p>
    </div>

    <?php if ($mesaj != "") echo $mesaj; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Yeni Sera Ekle</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="sera_adi" class="form-label">Sera Adı</label>
                            <input type="text" class="form-control" id="sera_adi" name="sera_adi" placeholder="Örn: Kuzey Cam Sera" required>
                        </div>
                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama / Detaylar</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3" placeholder="Sera hakkında bilgiler..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Sisteme Kaydet</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Kayıtlı Seralar</h5>
                </div>
                <div class="card-body">
                    <table class="table table-hover table-striped">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Sera Adı</th>
                            <th>Açıklama</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (count($seralar) > 0): ?>
                            <?php foreach ($seralar as $sera): ?>
                                <tr>
                                    <td><strong>#<?= htmlspecialchars($sera['Sera_ID']) ?></strong></td>
                                    <td><?= htmlspecialchars($sera['Sera_Adi']) ?></td>
                                    <td><?= htmlspecialchars($sera['Aciklama']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted">Sisteme henüz hiç sera eklenmemiş.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>
</html>