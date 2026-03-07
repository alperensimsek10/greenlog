<?php
session_start();

// GÜVENLİK KONTROLÜ: Eğer oturum açılmamışsa, direkt index.php'ye geri gönder!
if (!isset($_SESSION['Personel_ID'])) {
    header("Location: index.php?error=Lütfen önce giriş yapınız.");
    exit();
}

// 1. Veritabanı bağlantımızı dahil ediyoruz
require_once 'db-connect.php';

$mesaj = "";

// 2. Form Gönderildiyse (Veri Ekleme İşlemi)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seraAdi = trim($_POST['sera_adi']);
    $aciklama = trim($_POST['aciklama']);

    // Sera adı boş değilse kaydet
    if (!empty($seraAdi)) {
        try {
            $sql = "INSERT INTO SERA (Sera_Adi, Aciklama) VALUES (:sera_adi, :aciklama)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':sera_adi' => $seraAdi,
                ':aciklama' => $aciklama
            ]);
            $mesaj = "<div class='alert alert-success d-flex align-items-center shadow-sm'><i class='bx bx-check-circle fs-4 me-2'></i> Sera başarıyla sisteme eklendi!</div>";
        } catch (PDOException $e) {
            $mesaj = "<div class='alert alert-danger d-flex align-items-center shadow-sm'><i class='bx bx-error-circle fs-4 me-2'></i> Hata oluştu: " . $e->getMessage() . "</div>";
        }
    } else {
        $mesaj = "<div class='alert alert-warning d-flex align-items-center shadow-sm'><i class='bx bx-info-circle fs-4 me-2'></i> Lütfen Sera Adı alanını doldurun.</div>";
    }
}

// 3. Tabloda Göstermek İçin Tüm Seraları Çekme İşlemi
$sorgu = $pdo->query("SELECT * FROM SERA ORDER BY Sera_ID DESC");
$seralar = $sorgu->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenLog - Yönetim Paneli</title>
    <link rel="shortcut icon" href="images/logo1.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <style>
        body {
            background-color: #f4f7f6; /* Daha yumuşak bir arka plan */
        }
        .navbar {
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s;
        }
        .card-header {
            border-bottom: none;
            padding: 15px 20px;
        }
        .btn-success {
            background-color: #2e8b57;
            border: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-success:hover {
            background-color: #246b43;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 139, 87, 0.3);
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(46, 139, 87, 0.25);
            border-color: #2e8b57;
        }
        .table-hover tbody tr:hover {
            background-color: #f1fcf5; /* Seralara uygun yeşil hover efekti */
        }
        .sera-id-badge {
            background-color: #e9ecef;
            color: #495057;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.85em;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white mb-4 py-3">
    <div class="container">
        <a class="navbar-brand text-success fw-bold d-flex align-items-center" href="#">
            <i class='bx bx-leaf fs-3 me-2'></i> GreenLog Panel
        </a>
        <div class="d-flex align-items-center">
            <span class="me-4 text-muted d-none d-md-block">
                <i class='bx bxs-user-circle fs-5 align-middle text-success'></i>
                Hoş Geldin, <strong class="text-dark"><?= htmlspecialchars($_SESSION['Ad_Soyad'] ?? 'Kullanıcı') ?></strong>
                <small>(<?= htmlspecialchars($_SESSION['Unvan'] ?? 'Personel') ?>)</small>
            </span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm d-flex align-items-center">
                <i class='bx bx-log-out fs-5 me-1'></i> Çıkış
            </a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="row text-center mb-4">
        <h3 class="text-dark fw-bold">Sera Yönetimi</h3>
        <p class="text-muted">Sisteme yeni sera ekleyebilir ve mevcut seraları yönetebilirsiniz.</p>
    </div>

    <?php if ($mesaj != "") echo $mesaj; ?>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white d-flex align-items-center">
                    <i class='bx bx-plus-circle fs-5 me-2'></i>
                    <h6 class="mb-0 fw-bold">Yeni Sera Ekle</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="sera_adi" class="form-label text-muted fw-bold small">SERA ADI</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class='bx bx-building-house text-muted'></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" id="sera_adi" name="sera_adi" placeholder="Örn: Kuzey Cam Sera" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="aciklama" class="form-label text-muted fw-bold small">AÇIKLAMA / DETAYLAR</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3" placeholder="Sera hakkında notlar..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold">
                            <i class='bx bx-save me-1'></i> Sisteme Kaydet
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class='bx bx-list-ul fs-5 me-2'></i>
                        <h6 class="mb-0 fw-bold">Kayıtlı Seralar</h6>
                    </div>
                    <span class="badge bg-light text-dark"><?= count($seralar) ?> Sera Bulundu</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-muted small">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Sera Adı</th>
                                <th>Açıklama</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (count($seralar) > 0): ?>
                                <?php foreach ($seralar as $sera): ?>
                                    <tr>
                                        <td class="ps-4"><span class="sera-id-badge">#<?= htmlspecialchars($sera['Sera_ID']) ?></span></td>
                                        <td class="fw-bold text-dark">
                                            <i class='bx bx-building text-success me-1'></i>
                                            <?= htmlspecialchars($sera['Sera_Adi']) ?>
                                        </td>
                                        <td class="text-muted"><?= htmlspecialchars($sera['Aciklama'] ?: '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">
                                        <i class='bx bx-folder-open fs-1 mb-2 d-block text-black-50'></i>
                                        Sisteme henüz hiç sera eklenmemiş.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>