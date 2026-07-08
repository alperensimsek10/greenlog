<?php
if (!isset($_SESSION['Personel_ID'])) { exit("Erişim Engellendi"); }

try {
    // 1. SERALARI ÇEK
    $sera_sql = "SELECT Sera_ID, Sera_Adi, Aciklama, enlem, boylam, Aktif_Mi FROM sera WHERE enlem IS NOT NULL";
    $sera_sorgu = $pdo->query($sera_sql);
    $sera_features = array();
    
    while($row = $sera_sorgu->fetch(PDO::FETCH_ASSOC)) {
        $sera_id = $row['Sera_ID'];
        $sera_features[] = array(
            "type" => "Feature",
            "geometry" => array("type" => "Point", "coordinates" => array((float)$row["boylam"], (float)$row["enlem"])),
            "properties" => array(
                "tip" => "sera",
                "Sera_Adi" => htmlspecialchars($row['Sera_Adi']),
                "Aktif_Mi" => (int)$row['Aktif_Mi'],
                "description" => "
                    <div class='gl-modern-card'>
                        <div class='card-header sera-header'>
                            <i class='fas fa-warehouse'></i>
                            <span>" . htmlspecialchars($row['Sera_Adi']) . "</span>
                        </div>
                        <div class='card-body'>
                            <p class='sera-desc'>" . htmlspecialchars($row['Aciklama']) . "</p>
                            <a href='panel.php?sayfa=sera_detay&id=" . $sera_id . "' class='btn-sera-go'>
                                <span>Detaylara Git</span>
                                <i class='fas fa-arrow-right'></i>
                            </a>
                        </div>
                    </div>"
            )
        );
    }

    // 2. BİTKİLERİ ÇEK
    $bitki_sql = "SELECT b.Bitki_ID, b.Takson_ID, b.Ekim_Tarihi, b.Aktiflik, b.enlem, b.boylam, s.Sera_Adi, 
                         t.Bitki_Adi, t.Takson_Adi 
                  FROM bitki b 
                  LEFT JOIN sera s ON b.Sera_ID = s.Sera_ID 
                  LEFT JOIN takson t ON b.Takson_ID = t.Takson_ID
                  WHERE b.enlem IS NOT NULL AND b.boylam IS NOT NULL";
    $bitki_sorgu = $pdo->query($bitki_sql);
    $bitki_features = array();

    while($row = $bitki_sorgu->fetch(PDO::FETCH_ASSOC)) {
        $is_active = (int)$row['Aktiflik'];
        $bitki_features[] = array(
            "type" => "Feature",
            "geometry" => array("type" => "Point", "coordinates" => array((float)$row["boylam"], (float)$row["enlem"])),
            "properties" => array(
                "tip" => "bitki",
                "label" => htmlspecialchars($row['Bitki_Adi'] ?: 'Bilinmeyen Bitki'),
                "Aktiflik" => $is_active,
                "description" => "
                    <div class='gl-modern-card'>
                        <div class='card-header " . ($is_active ? 'active-header' : 'passive-header') . "'>
                            <i class='fas fa-leaf'></i>
                            <div class='header-text-group'>
                                <small class='tax-label'>" . htmlspecialchars($row['Takson_Adi'] ?: 'Genel Tür') . "</small>
                                <span class='plant-main-title'>" . htmlspecialchars($row['Bitki_Adi'] ?: 'Bilinmeyen Bitki') . "</span>
                            </div>
                        </div>
                        <div class='card-body'>
                            <div class='info-row'><b>ID:</b> <span>#" . $row['Bitki_ID'] . "</span></div>
                            <div class='info-row'><b>Sera:</b> <span>" . htmlspecialchars($row['Sera_Adi']) . "</span></div>
                            <div class='info-row'><b>Ekim:</b> <span>" . date("d.m.Y", strtotime($row['Ekim_Tarihi'])) . "</span></div>
                            <div class='status-badge " . ($is_active ? 'badge-green' : 'badge-red') . "'>
                                " . ($is_active ? 'Aktif Gelişim' : 'Pasif / Boş') . "
                            </div>
                            <a href='panel.php?sayfa=bitki_detay&takson_id=" . $row['Takson_ID'] . "' class='btn-plant-go'>
                                <span>Bitkiye Git</span>
                                <i class='fas fa-chevron-right'></i>
                            </a>
                        </div>
                    </div>"
            )
        );
    }

    $geojson_sera = json_encode(array("type" => "FeatureCollection", "features" => $sera_features));
    $geojson_bitki = json_encode(array("type" => "FeatureCollection", "features" => $bitki_features));

} catch (PDOException $e) { }
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v10.0.0/ol.css">
<script src="https://cdn.jsdelivr.net/npm/ol@v10.0.0/dist/ol.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">

<style>
    :root { --gl-emerald: #10b981; --gl-red: #ef4444; --gl-dark: #0f172a; --gl-gray: #64748b; --gl-blue: #3b82f6; }
    .map-outer-wrapper { position: relative; width: 100%; height: calc(100vh - 120px); border-radius: 24px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.15); font-family: 'Plus Jakarta Sans', sans-serif; }
    #map { width: 100%; height: 100%; background: #f1f5f9; }

    /* Arama Paneli */
    .map-search-container { position: absolute; top: 20px; right: 20px; z-index: 20; width: 260px; }
    .search-input-wrapper { background: white; border-radius: 14px; display: flex; align-items: center; padding: 4px 12px; box-shadow: 0 8px 20px rgba(0,0,0,0.1); border: 1px solid rgba(0,0,0,0.05); }
    .search-input-wrapper input { border: none; padding: 8px; outline: none; width: 100%; font-family: inherit; font-weight: 600; font-size: 13px; }
    .search-input-wrapper i { color: var(--gl-gray); }

    /* Arama Vurgu Efekti (Pulse) */
    @keyframes pulse-ring {
        0% { transform: scale(.3); opacity: 0.8; }
        80%, 100% { opacity: 0; }
    }
    .search-highlight-pulse { 
        width: 60px; height: 60px; border: 4px solid var(--gl-blue); border-radius: 50%; 
        background-color: rgba(59, 130, 246, 0.3); 
        animation: pulse-ring 1.5s cubic-bezier(0.215, 0.61, 0.355, 1) infinite; 
    }

    /* Katman Değiştirici */
    .map-toggle-btn { position: absolute; bottom: 25px; left: 25px; z-index: 20; background: white; border: none; padding: 10px 16px; border-radius: 12px; cursor: pointer; font-weight: 700; font-size: 12px; display: flex; align-items: center; gap: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); transition: all 0.2s; color: var(--gl-dark); }
    .map-toggle-btn:hover { transform: translateY(-2px); background: var(--gl-dark); color: white; }

    /* Legend */
    .map-legend { position: absolute; top: 20px; left: 20px; background: rgba(255, 255, 255, 0.95); padding: 15px; border-radius: 18px; z-index: 10; box-shadow: 0 10px 25px rgba(0,0,0,0.1); backdrop-filter: blur(10px); min-width: 160px; }
    .legend-item { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 11px; font-weight: 700; color: #475569; }
    .dot { width: 8px; height: 8px; border-radius: 50%; }

    /* Popup & Cards */
    .ol-popup { position: absolute; min-width: 240px; bottom: 45px; left: -50%; transform: translateX(-50%); z-index: 1000; }
    .gl-modern-card { background: white; border-radius: 18px; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.2); }
    .card-header { padding: 12px 16px; display: flex; align-items: center; gap: 12px; color: white; }
    .header-text-group { display: flex; flex-direction: column; }
    .tax-label { font-size: 10px; font-weight: 700; opacity: 0.8; }
    .plant-main-title { font-weight: 800; font-size: 15px; }
    .active-header { background: var(--gl-emerald); }
    .passive-header { background: var(--gl-red); }
    .sera-header { background: var(--gl-dark); }
    .card-body { padding: 15px; display: flex; flex-direction: column; gap: 8px; }
    .btn-sera-go, .btn-plant-go { display: flex; align-items: center; justify-content: space-between; padding: 10px; border-radius: 10px; text-decoration: none !important; font-size: 12px; font-weight: 700; transition: 0.2s; }
    .btn-sera-go { background: var(--gl-dark); color: white !important; }
    .btn-plant-go { background: #f1f5f9; color: var(--gl-dark) !important; }
    .info-row { display: flex; justify-content: space-between; font-size: 12px; }
    .status-badge { padding: 5px; text-align: center; border-radius: 6px; font-size: 10px; font-weight: 800; }
    .badge-green { background: #ecfdf5; color: var(--gl-emerald); }
    .badge-red { background: #fef2f2; color: var(--gl-red); }
</style>

<div class="map-outer-wrapper">
    <div class="map-search-container">
        <div class="search-input-wrapper">
            <i class="fas fa-search"></i>
            <input type="text" id="mapSearch" placeholder="Sera veya Bitki Ara...">
        </div>
    </div>

    <button class="map-toggle-btn" onclick="toggleSatellite()">
        <i class="fas fa-layer-group"></i>
        <span id="toggleText">Uydu Görünümü</span>
    </button>

    <div id="map"></div>
    
    <div class="map-legend">
        <div class="legend-item"><div class="dot" style="background:var(--gl-emerald)"></div> Aktif Durum</div>
        <div class="legend-item"><div class="dot" style="background:var(--gl-red)"></div> Pasif / Boş</div>
        <div class="legend-item"><div class="dot" style="background:var(--gl-blue)"></div> Aranan Hedef</div>
    </div>

    <div id="search-marker" style="pointer-events: none; display: none;">
        <div class="search-highlight-pulse"></div>
    </div>

    <div id="popup" class="ol-popup">
        <div id="popup-content"></div>
    </div>
</div>

<script>
    // 1. KATMANLAR
    const osmLayer = new ol.layer.Tile({ source: new ol.source.OSM() });
    const satelliteLayer = new ol.layer.Tile({
        source: new ol.source.XYZ({ url: 'https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}' }),
        visible: false
    });

    // 2. VERİ KAYNAKLARI
    const seraSource = new ol.source.Vector({ 
        features: new ol.format.GeoJSON().readFeatures(<?php echo $geojson_sera; ?>, { 
            dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' 
        }) 
    });

    const bitkiSource = new ol.source.Vector({ 
        features: new ol.format.GeoJSON().readFeatures(<?php echo $geojson_bitki; ?>, { 
            dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' 
        }) 
    });

    // 3. STİLLER
    const seraStyle = (f) => new ol.style.Style({
        image: new ol.style.Icon({
            anchor: [0.5, 1],
            src: 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(`<svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21.5C16.5 17.5 21 13.5 21 8.5C21 3.5 17 0.5 12 0.5C7 0.5 3 3.5 3 8.5C3 13.5 7.5 17.5 12 21.5Z" fill="${f.get('Aktif_Mi') == 1 ? '#10b981' : '#ef4444'}" stroke="white" stroke-width="1.5"/></svg>`),
        }),
        text: new ol.style.Text({
            text: f.get('Sera_Adi'), offsetY: -45, font: '800 13px Plus Jakarta Sans',
            fill: new ol.style.Fill({color: '#0f172a'}),
            stroke: new ol.style.Stroke({color: '#fff', width: 3})
        })
    });

    const bitkiStyle = (f) => new ol.style.Style({
        image: new ol.style.RegularShape({
            fill: new ol.style.Fill({color: f.get('Aktiflik') == 1 ? '#10b981' : '#ef4444'}),
            stroke: new ol.style.Stroke({color: '#fff', width: 1.5}),
            points: 4, radius: 9, angle: Math.PI / 4
        })
    });

    const seraLayer = new ol.layer.Vector({ source: seraSource, style: seraStyle });
    const bitkiLayer = new ol.layer.Vector({ source: bitkiSource, style: bitkiStyle, visible: false });

    // 4. HARİTA BAŞLATMA
    const map = new ol.Map({
        target: 'map',
        layers: [osmLayer, satelliteLayer, bitkiLayer, seraLayer],
        view: new ol.View({ center: ol.proj.fromLonLat([41.8547, 41.1876]), zoom: 16 }),
        controls: []
    });

    const overlay = new ol.Overlay({ element: document.getElementById('popup'), autoPan: { animation: { duration: 250 } } });
    map.addOverlay(overlay);

    // Arama Vurgu Katmanı (Overlay)
    const searchMarkerEl = document.getElementById('search-marker');
    const searchOverlay = new ol.Overlay({
        element: searchMarkerEl,
        positioning: 'center-center',
        stopEvent: false
    });
    map.addOverlay(searchOverlay);

    // 5. FONKSİYONLAR
    window.toggleSatellite = function() {
        const isSat = satelliteLayer.getVisible();
        satelliteLayer.setVisible(!isSat);
        osmLayer.setVisible(isSat);
        document.getElementById('toggleText').innerText = isSat ? 'Uydu Görünümü' : 'Harita Görünümü';
    };

    // ARAMA MANTIĞI
    document.getElementById('mapSearch').addEventListener('input', (e) => {
        const val = e.target.value.toLowerCase().trim();
        
        if (val.length < 2) {
            searchOverlay.setPosition(undefined);
            searchMarkerEl.style.display = 'none';
            return;
        }

        const allFeatures = [...seraSource.getFeatures(), ...bitkiSource.getFeatures()];
        const match = allFeatures.find(f => (f.get('Sera_Adi') || f.get('label') || '').toLowerCase().includes(val));

        if (match) {
            const coords = match.getGeometry().getCoordinates();
            
            // Vurguyu göster
            searchMarkerEl.style.display = 'block';
            searchOverlay.setPosition(coords);
            
            // Odaklan
            map.getView().animate({ center: coords, zoom: 20, duration: 800 });

            // Popup içeriğini güncelle ve göster
            document.getElementById('popup-content').innerHTML = match.get('description');
            overlay.setPosition(coords);
        } else {
            searchOverlay.setPosition(undefined);
            searchMarkerEl.style.display = 'none';
        }
    });

    // Zoom Kontrolü
    map.getView().on('change:resolution', () => {
        const zoom = map.getView().getZoom();
        bitkiLayer.setVisible(zoom > 17.5);
        seraLayer.setOpacity(zoom > 17.5 ? 0.3 : 1);
    });

    // TIKLAMA OLAYI
    map.on('singleclick', (e) => {
        const feature = map.forEachFeatureAtPixel(e.pixel, f => f);
        if (feature) {
            const coords = feature.getGeometry().getCoordinates();
            const props = feature.getProperties();
            document.getElementById('popup-content').innerHTML = props.description;
            overlay.setPosition(coords);

            // Tıklanan yer aramayı temizlesin (opsiyonel)
            searchOverlay.setPosition(undefined);
            searchMarkerEl.style.display = 'none';

            if (props.tip === 'sera') {
                map.getView().animate({ center: coords, zoom: 19, duration: 1200, easing: ol.easing.easeInOut });
            }
        } else {
            overlay.setPosition(undefined);
            searchOverlay.setPosition(undefined);
            searchMarkerEl.style.display = 'none';
            if (seraSource.getFeatures().length > 0) {
                map.getView().fit(seraSource.getExtent(), { padding: [80, 80, 80, 80], duration: 1200, maxZoom: 17 });
            }
        }
    });

    setTimeout(() => {
        if (seraSource.getFeatures().length > 0) {
            map.getView().fit(seraSource.getExtent(), { padding: [80, 80, 80, 80], duration: 1200, maxZoom: 17 });
        }
    }, 500);
</script>