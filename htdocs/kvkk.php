<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenLog - KVKK Aydınlatma Metni</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-light: rgba(46, 125, 50, 0.15);
            --text-color: #f8fafc; /* Açık renk metin (koyu cam üzerinde harika okunur) */
            --text-muted: #cbd5e1;
            --title-color: #ffffff;
            --glass-border: rgba(255, 255, 255, 0.12);
            --glass-card: rgba(15, 23, 42, 0.65); /* Giriş ekranınızdaki gibi koruyucu koyu transparanlık */
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.7;
            color: var(--text-color);
            
            /* İSTEDİĞİNİZ ARKA PLAN GÖRSELİ (background.jpg) */
            background: linear-gradient(rgba(0, 0, 0, 0.35), rgba(0, 0, 0, 0.45)), 
                        url('images/background.jpeg') no-repeat center center fixed;
            background-size: cover;
            
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        /* Logo Stili */
        .page-logo {
            max-width: 180px;
            height: auto;
            margin-bottom: 35px;
            filter: drop-shadow(0 4px 10px rgba(0,0,0,0.4));
            transition: transform 0.3s ease;
        }
        
        .page-logo:hover {
            transform: scale(1.05);
        }

        /* Ana Konteyner Düzeni */
        .container {
            max-width: 1100px;
            width: 100%;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
        }

        /* Sol Menü (Glassmorphism Navigasyon) */
        .sidebar {
            position: sticky;
            top: 40px;
            height: fit-content;
            background: var(--glass-card);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
        }

        .sidebar-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            color: #81c784;
            margin-bottom: 18px;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 6px;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.25s ease;
            border: 1px solid transparent;
        }

        .sidebar-menu li a:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu li a.active {
            color: #ffffff;
            background: var(--primary-color);
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.4);
        }

        /* Sağ İçerik Alanı (Premium Cam Panel) */
        .content {
            background: var(--glass-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 50px;
            box-shadow: 0 15px 35px 0 rgba(0, 0, 0, 0.35);
        }

        /* Başlıklar */
        h1 {
            color: var(--title-color);
            font-size: 32px;
            font-weight: 800;
            line-height: 1.3;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 14px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        h1 i {
            color: #81c784;
            font-size: 36px;
        }

        .intro-text {
            font-size: 16px;
            color: #e2e8f0;
            background: rgba(255, 255, 255, 0.05);
            padding: 18px 24px;
            border-left: 4px solid #4caf50;
            border-radius: 4px 16px 16px 4px;
            margin-bottom: 40px;
        }

        /* Bölümler */
        section {
            padding-top: 10px;
            margin-bottom: 35px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding-bottom: 35px;
        }

        section:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }

        h2 {
            color: #81c784;
            font-size: 19px;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        h2 i {
            font-size: 22px;
        }

        p {
            margin-bottom: 14px;
            color: var(--text-muted);
            text-align: justify;
            font-size: 15px;
        }

        ul {
            list-style: none;
            margin: 20px 0;
            display: grid;
            gap: 10px;
        }

        ul li {
            position: relative;
            padding-left: 30px;
            color: var(--text-muted);
            font-size: 15px;
        }

        ul li::before {
            content: "\eac4"; 
            font-family: 'boxicons' !important;
            position: absolute;
            left: 2px;
            top: 1px;
            color: #4caf50;
            font-size: 20px;
            text-shadow: 0 0 8px rgba(76, 175, 80, 0.6);
        }

        strong {
            color: #ffffff;
            font-weight: 600;
        }

        .email-link {
            color: #81c784;
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px dashed #81c784;
            transition: all 0.2s;
        }

        .email-link:hover {
            color: #ffffff;
            border-bottom-style: solid;
        }

        /* Mobil Düzen */
        @media (max-width: 992px) {
            .container {
                grid-template-columns: 1fr;
            }
            .sidebar {
                display: none;
            }
            body {
                padding: 30px 15px;
            }
            .content {
                padding: 30px 22px;
            }
            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

    <img src="images/logo2.png" alt="GreenLog Logo" class="page-logo">

    <div class="container">
        <aside class="sidebar">
            <div class="sidebar-title"><i class='bx bx-compass'></i> Hızlı Menü</div>
            <ul class="sidebar-menu">
                <li><a href="#veri-sorumlusu" class="active"><i class='bx bx-buildings'></i> Veri Sorumlusu</a></li>
                <li><a href="#islenme-amaci"><i class='bx bx-target-lock'></i> İşlenme Amacı</a></li>
                <li><a href="#aktarilma"><i class='bx bx-transfer-alt'></i> Veri Aktarımı</a></li>
                <li><a href="#yontem-sebep"><i class='bx bx-folder-open'></i> Yöntem & Sebep</a></li>
                <li><a href="#haklariniz"><i class='bx bx-user-check'></i> Haklarınız</a></li>
            </ul>
        </aside>

        <main class="content">
            <h1><i class='bx bx-shield-quarter'></i> Kişisel Verilerin Korunması</h1>
            
            <p class="intro-text">
                Bu aydınlatma metni, 6698 sayılı Kişisel Verilerin Korunması Kanunu’nun (KVKK) 10. maddesi uyarınca, GreenLog Personel Giriş ve Otomasyon Sistemi’ni kullanan personellerimizin işlem ve sistem güvenliğini sağlamak amacıyla hazırlanmıştır.
            </p>

            <section id="veri-sorumlusu">
                <h2><i class='bx bx-buildings'></i> 1. VERİ SORUMLUSU</h2>
                <p>Kişisel verileriniz yönünden veri sorumlusu, <strong>GreenLog Yönetimi / Şirketi</strong>'dir.</p>
            </section>

            <section id="islenme-amaci">
                <h2><i class='bx bx-target-lock'></i> 2. KİŞİSEL VERİLERİNİZİN İŞLENME AMACI</h2>
                <p>Giriş panelimizi kullanmanız, sisteme erişim sağlamanız veya başarısız giriş denemelerinde bulunmanız durumunda; TC Kimlik Numaranız (WhiteShark log katmanında ilk 3 ve son 3 hanesi kalacak şekilde maskelenerek), IP adresiniz, işlem zamanınız, kullandığınız cihaz/tarayıcı bilgisi ve tercih etmeniz halinde "Beni Hatırla" çerezi (Cookie) verileriniz;</p>
                <ul>
                    <li>İşlem ve sistem güvenliğinin (siber saldırı analizi, yetkisiz erişim tespiti) sağlanması,</li>
                    <li>Kullanıcı kimlik doğrulama süreçlerinin yürütülmesi,</li>
                    <li>Hata ve sistem sorunlarının tespiti ile giderilmesi,</li>
                    <li>Doğabilecek hukuki uyuşmazlıklarda delil teşkil etmesi</li>
                </ul>
                <p>amaçlarıyla sınırlı, bağlantılı ve ölçülü olarak işlenmektedir.</p>
            </section>

            <section id="aktarilma">
                <h2><i class='bx bx-transfer-alt'></i> 3. KİŞİSEL VERİLERİNİZİN AKTARILMASI</h2>
                <p>Kişisel verileriniz, şirketimiz bünyesinde güvenli sunucularda saklanmakta olup, üçüncü taraflarla veya reklam şirketleriyle kesinlikle paylaşılmamaktadır. Sadece adli veya idari makamlarca resmi ve kanuni bir talep gelmesi durumunda, yasal yükümlülüklerimizi yerine getirmek amacıyla ilgili resmi makamlara aktarılabilecektir. Verileriniz yurtdışına aktarılmamaktadır.</p>
            </section>

            <section id="yontem-sebep">
                <h2><i class='bx bx-folder-open'></i> 4. VERİ TOPLAMA YÖNTEMİ VE HUKUKİ SEBEBİ</h2>
                <p>Kişisel verileriniz, giriş panelindeki form alanlarını doldurmanız, çerez tercihleri ve WhiteShark güvenlik katmanının otomatik olarak IP/cihaz bilgilerinizi log dosyasına (whiteshark_security.log) kaydetmesi yöntemiyle tamamen dijital ortamda elde edilmektedir. Bu veriler, KVKK’nın 5. maddesinde belirtilen; "Kanunlarda açıkça öngörülmesi" ve "Veri sorumlusunun meşru menfaati için veri işlenmesinin zorunlu olması" hukuki sebeplerine dayanılarak işlenmektedir.</p>
            </section>

            <section id="haklariniz">
                <h2><i class='bx bx-user-check'></i> 5. HAKLARINIZ</h2>
                <p>6698 sayılı Kanun’un 11. maddesi kapsamındaki haklarınızı (verilerinizin işlenip işlenmediğini öğrenme, silinmesini talep etme vb.) kullanmak için başvurularınızı şirketimizin resmi adresine yazılı olarak veya sistem yöneticilerimize <a href="mailto:greenlog5255@gmail.com" class="email-link">greenlog5255@gmail.com</a> üzerinden e-posta yoluyla iletebilirsiniz.</p>
            </section>
        </main>
    </div>

    <script>
        const links = document.querySelectorAll('.sidebar-menu a');
        const sections = document.querySelectorAll('section');

        links.forEach(link => {
            link.addEventListener('click', () => {
                links.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
            });
        });

        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                if (pageYOffset >= sectionTop - 120) {
                    current = section.getAttribute('id');
                }
            });

            links.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href').includes(current)) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>