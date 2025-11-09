<?php
session_start();
require_once('../config/koneksi.php');

if (!isset($_SESSION['id_pengendara'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (!isset($koneksi)) {
    die("Koneksi database tidak tersedia. Periksa file config/koneksi.php.");
}

// Ambil stasiun aktif dengan total stok baterai
try {
    $stmt = $koneksi->query("
        SELECT s.id_stasiun, s.nama_stasiun, s.alamat, s.latitude, s.longitude, 
               COALESCE(SUM(sb.jumlah), 0) AS total_stok
        FROM stasiun_pengisian s 
        LEFT JOIN stok_baterai sb ON s.id_stasiun = sb.id_stasiun 
        WHERE s.status_operasional = 'aktif' 
        GROUP BY s.id_stasiun
    ");
    $stasiun = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error query database: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cari Lokasi - E-Station</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map { 
            height: 500px; 
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .station-card {
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .station-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .nearest-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .stock-badge {
            font-size: 0.85rem;
            padding: 5px 10px;
            font-weight: 600;
            border-radius: 15px;
        }
        /* Indikator Stok Sesuai User Story */
        .stock-high { 
            background-color: #28a745; 
            color: white;
        }
        .stock-medium { 
            background-color: #ffc107; 
            color: #000; 
        }
        .stock-low { 
            background-color: #dc3545; 
            color: white;
        }
        .stock-empty { 
            background-color: #6c757d; 
            color: white;
        }
        .distance-badge {
            background-color: #17a2b8;
            color: white;
            font-size: 0.85rem;
            padding: 3px 8px;
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">E-Station</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="transaction_history.php">Riwayat</a>
                <a class="nav-link" href="battery_stock.php">Stok Baterai</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2 class="mb-4">🗺️ Cari Lokasi Stasiun Pengisian</h2>
        
        <div class="row">
            <div class="col-md-8">
                <div class="mb-3">
                    <input id="searchInput" type="text" class="form-control" placeholder="Cari alamat atau kota...">
                    <button id="searchBtn" class="btn btn-primary mt-2">🔍 Cari Lokasi</button>
                    <button id="getCurrentLocation" class="btn btn-success mt-2">📍 Gunakan Lokasi Saya</button>
                </div>
                <div id="map"></div>
            </div>
            
            <div class="col-md-4">
                <h5>Stasiun Terdekat</h5>
                <small class="text-muted">Diurutkan berdasarkan jarak & stok baterai</small>
                <div id="stationList" class="mt-3">
                    <?php foreach ($stasiun as $s): 
                        // Indikator Stok Sesuai User Story
                        $stock = (int)$s['total_stok'];
                        if ($stock == 0) {
                            $stockClass = 'stock-empty';
                            $stockLabel = '⚫ Stok Habis';
                        } elseif ($stock <= 3) {
                            $stockClass = 'stock-low';
                            $stockLabel = '🔴 Hampir Habis';
                        } elseif ($stock <= 10) {
                            $stockClass = 'stock-medium';
                            $stockLabel = '🟡 Stok Terbatas';
                        } else {
                            $stockClass = 'stock-high';
                            $stockLabel = '🟢 Stok Banyak';
                        }
                    ?>
                        <div class="card station-card <?php echo $stock == 0 ? 'opacity-75' : ''; ?>" 
                             data-id="<?php echo $s['id_stasiun']; ?>"
                             data-lat="<?php echo $s['latitude']; ?>" 
                             data-lng="<?php echo $s['longitude']; ?>"
                             data-stock="<?php echo $s['total_stok']; ?>">
                            <div class="card-body p-3">
                                <h6 class="card-title mb-2"><?php echo htmlspecialchars($s['nama_stasiun']); ?></h6>
                                <p class="card-text small mb-2 text-muted"><?php echo htmlspecialchars($s['alamat']); ?></p>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge <?php echo $stockClass; ?> stock-badge">
                                        <?php echo $stockLabel; ?>: <?php echo $stock; ?> unit
                                    </span>
                                </div>
                                <span class="distance-badge" data-distance="">-</span>
                                <a href="station_detail.php?id=<?php echo $s['id_stasiun']; ?>" 
                                   class="btn btn-sm btn-primary mt-2 w-100"
                                   <?php echo $stock == 0 ? 'onclick="return confirm(\'Stok baterai habis! Yakin ingin melihat detail?\')"' : ''; ?>>
                                    Detail Stasiun
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div id="estimation" class="nearest-info" style="display:none;">
            <h5>📊 Stasiun Terdekat (Prioritas: Jarak & Stok)</h5>
            <div id="estimationContent"></div>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map;
        let userMarker;
        let stationMarkers = [];
        let routeLine;
        const stations = <?php echo json_encode($stasiun); ?>;

        // Inisialisasi peta (Jakarta sebagai default)
        map = L.map('map').setView([-6.2088, 106.8456], 12);

        // Tambah tile layer (peta dasar dari OpenStreetMap)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        // Icon custom untuk stasiun (hijau)
        const stationIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        // Icon untuk lokasi user (biru)
        const userIcon = L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        // Tambah marker untuk semua stasiun dengan indikator stok
        stations.forEach(station => {
            const stock = parseInt(station.total_stok);
            let markerColor, stockStatus;
            
            // Indikator warna marker sesuai stok (User Story)
            if (stock == 0) {
                markerColor = 'grey';
                stockStatus = '⚫ Stok Habis';
            } else if (stock <= 3) {
                markerColor = 'red';
                stockStatus = '🔴 Hampir Habis';
            } else if (stock <= 10) {
                markerColor = 'orange';
                stockStatus = '🟡 Stok Terbatas';
            } else {
                markerColor = 'green';
                stockStatus = '🟢 Stok Banyak';
            }
            
            // Icon marker dengan warna sesuai stok
            const customIcon = L.icon({
                iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-${markerColor}.png`,
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });
            
            const marker = L.marker([parseFloat(station.latitude), parseFloat(station.longitude)], {
                icon: customIcon
            }).addTo(map);
            
            marker.bindPopup(`
                <div style="min-width: 200px;">
                    <b>${station.nama_stasiun}</b><br>
                    <small>${station.alamat}</small><br>
                    <hr style="margin: 8px 0;">
                    <strong>${stockStatus}</strong><br>
                    <span style="font-size: 1.2em;">🔋 ${stock} unit tersedia</span><br>
                    <a href="station_detail.php?id=${station.id_stasiun}" class="btn btn-sm btn-primary mt-2" style="width: 100%;">Lihat Detail</a>
                </div>
            `);
            
            stationMarkers.push({marker: marker, data: station});
        });

        // Fungsi hitung jarak (Haversine formula)
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Radius bumi dalam km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                    Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        // Fungsi cari stasiun terdekat dengan prioritas stok
        function findNearestStations(userLat, userLng) {
            // Hitung jarak semua stasiun
            const stationsWithDistance = stations.map(station => {
                const dist = calculateDistance(
                    userLat, userLng,
                    parseFloat(station.latitude), parseFloat(station.longitude)
                );
                return { ...station, distance: dist };
            });

            // Sort berdasarkan jarak, jika sama prioritaskan stok lebih tinggi
            stationsWithDistance.sort((a, b) => {
                if (Math.abs(a.distance - b.distance) < 0.5) { // Jika selisih < 500m
                    return parseInt(b.total_stok) - parseInt(a.total_stok); // Prioritas stok
                }
                return a.distance - b.distance; // Prioritas jarak
            });

            // Ambil 5 terdekat
            const top5 = stationsWithDistance.slice(0, 5);
            const nearest = top5[0];

            // Update jarak di card
            document.querySelectorAll('.station-card').forEach(card => {
                const stationId = card.dataset.id;
                const stationData = stationsWithDistance.find(s => s.id_stasiun == stationId);
                if (stationData) {
                    const distanceBadge = card.querySelector('.distance-badge');
                    distanceBadge.textContent = `${stationData.distance.toFixed(2)} km`;
                    card.dataset.distance = stationData.distance;
                }
            });

            // Urutkan ulang card berdasarkan jarak
            const stationListDiv = document.getElementById('stationList');
            const cards = Array.from(document.querySelectorAll('.station-card'));
            cards.sort((a, b) => {
                const distA = parseFloat(a.dataset.distance) || 999;
                const distB = parseFloat(b.dataset.distance) || 999;
                if (Math.abs(distA - distB) < 0.5) {
                    return parseInt(b.dataset.stock) - parseInt(a.dataset.stock);
                }
                return distA - distB;
            });
            cards.forEach(card => stationListDiv.appendChild(card));

            // Estimasi untuk stasiun terdekat
            const estimatedTime = (nearest.distance / 60) * 60; // Asumsi 60 km/jam
            const estimatedCost = nearest.distance * 2000 * 0.15; // 0.15 kWh/km, Rp 2000/kWh
            
            // Indikator stok untuk info estimasi
            const stock = parseInt(nearest.total_stok);
            let stockBadge, stockWarning = '';
            if (stock == 0) {
                stockBadge = '<span class="badge stock-empty">⚫ Stok Habis</span>';
                stockWarning = '<div class="alert alert-danger mt-3 mb-0"><strong>⚠️ Perhatian:</strong> Stok baterai habis! Pertimbangkan stasiun lain.</div>';
            } else if (stock <= 3) {
                stockBadge = '<span class="badge stock-low">🔴 Hampir Habis</span>';
                stockWarning = '<div class="alert alert-warning mt-3 mb-0"><strong>⚠️ Perhatian:</strong> Stok terbatas, sebaiknya hubungi stasiun terlebih dahulu.</div>';
            } else if (stock <= 10) {
                stockBadge = '<span class="badge stock-medium">🟡 Stok Terbatas</span>';
            } else {
                stockBadge = '<span class="badge stock-high">🟢 Stok Banyak</span>';
            }

            document.getElementById('estimation').style.display = 'block';
            document.getElementById('estimationContent').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>🏢 Stasiun:</strong> ${nearest.nama_stasiun}</p>
                        <p><strong>📍 Alamat:</strong> ${nearest.alamat}</p>
                        <p><strong>📏 Jarak:</strong> ${nearest.distance.toFixed(2)} km</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>⏱️ Estimasi Waktu:</strong> ${estimatedTime.toFixed(0)} menit</p>
                        <p><strong>💰 Estimasi Biaya Listrik:</strong> Rp ${estimatedCost.toFixed(0).replace(/\B(?=(\d{3})+(?!\d))/g, ".")}</p>
                        <p><strong>🔋 Status Ketersediaan:</strong> ${stockBadge} ${stock} unit</p>
                    </div>
                </div>
                ${stockWarning}
                <a href="station_detail.php?id=${nearest.id_stasiun}" class="btn btn-light mt-2">Lihat Detail Lengkap</a>
            `;

            // Hapus rute lama
            if (routeLine) map.removeLayer(routeLine);

            // Gambar rute (garis lurus sederhana)
            routeLine = L.polyline([
                [userLat, userLng],
                [parseFloat(nearest.latitude), parseFloat(nearest.longitude)]
            ], {color: 'blue', weight: 3, dashArray: '10, 10'}).addTo(map);

            // Fit map ke user dan stasiun terdekat
            const bounds = L.latLngBounds([
                [userLat, userLng],
                [parseFloat(nearest.latitude), parseFloat(nearest.longitude)]
            ]);
            map.fitBounds(bounds, {padding: [50, 50]});
        }

        // Tombol gunakan lokasi saat ini
        document.getElementById('getCurrentLocation').addEventListener('click', () => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;

                    // Hapus marker user sebelumnya
                    if (userMarker) map.removeLayer(userMarker);

                    // Tambah marker user
                    userMarker = L.marker([lat, lng], {icon: userIcon}).addTo(map);
                    userMarker.bindPopup('<b>📍 Lokasi Anda</b>').openPopup();

                    // Zoom ke lokasi user
                    map.setView([lat, lng], 14);

                    // Cari stasiun terdekat
                    findNearestStations(lat, lng);
                }, error => {
                    alert('Gagal mendapatkan lokasi: ' + error.message);
                });
            } else {
                alert('Geolocation tidak didukung oleh browser Anda.');
            }
        });

        // Klik card stasiun untuk zoom
        document.querySelectorAll('.station-card').forEach(card => {
            card.addEventListener('click', (e) => {
                // Jangan zoom jika klik tombol detail
                if (e.target.tagName === 'A') return;
                
                const lat = parseFloat(card.dataset.lat);
                const lng = parseFloat(card.dataset.lng);
                map.setView([lat, lng], 16);
                
                // Buka popup marker yang diklik
                stationMarkers.forEach(sm => {
                    const markerPos = sm.marker.getLatLng();
                    if (markerPos.lat === lat && markerPos.lng === lng) {
                        sm.marker.openPopup();
                    }
                });
            });
        });

        // Pencarian menggunakan Nominatim (OpenStreetMap)
        document.getElementById('searchBtn').addEventListener('click', () => {
            const query = document.getElementById('searchInput').value;
            if (!query) {
                alert('Masukkan lokasi yang ingin dicari!');
                return;
            }

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=id`)
                .then(response => response.json())
                .then(data => {
                    if (data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lng = parseFloat(data[0].lon);

                        // Hapus marker user sebelumnya
                        if (userMarker) map.removeLayer(userMarker);

                        // Tambah marker user
                        userMarker = L.marker([lat, lng], {icon: userIcon}).addTo(map);
                        userMarker.bindPopup(`<b>🔍 Hasil Pencarian</b><br>${data[0].display_name}`).openPopup();

                        // Zoom ke lokasi
                        map.setView([lat, lng], 14);

                        // Cari stasiun terdekat
                        findNearestStations(lat, lng);
                    } else {
                        alert('Lokasi tidak ditemukan! Coba kata kunci lain.');
                    }
                })
                .catch(error => {
                    alert('Error pencarian: ' + error.message);
                });
        });

        // Enter key untuk search
        document.getElementById('searchInput').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                document.getElementById('searchBtn').click();
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>