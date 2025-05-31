<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Localisation des Gares Tunisiennes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="style.css" />
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <button class="close-sidebar" onclick="toggleSidebar()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="sidebar-content">
        <div class="menu-section">
                <div class="menu-title" onclick="tog('dashboard-section')">
                    <span><i class="fas fa-tachometer-alt"></i> Dashboard</span>
                </div>
            </div>
            <div class="form-group">
                    <button class="btn btn-primary" style="width: 100%;" onclick="openCommunicationPanel()">
                        <i class="fas fa-comments"></i> Communication avec le train
                    </button>
            </div>
            <div class="modal" id="communication-modal">
                <div class="modal-header">
                    <div class="modal-title">Communication avec le train</div>
                    <button class="modal-close" onclick="hideCommunicationPanel()">&times;</button>
                </div>
                <div style="padding: 15px;">
                    <div id="messages-container" style="height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-bottom: 15px; background-color: #f9f9f9;">
                    </div>
                    <div class="form-group">
                        <label for="message-input">Message à envoyer:</label>
                        <textarea id="message-input" class="form-control" rows="3" style="width: 100%;"></textarea>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <button class="btn btn-secondary" onclick="hideCommunicationPanel()">
                            <i class="fas fa-times"></i> Fermer
                        </button>
                        <button class="btn btn-primary" onclick="sendMessage()">
                            <i class="fas fa-paper-plane"></i> Envoyer
                        </button>
                    </div>
                </div>
            </div>
            <!-- Train Information Section -->
            <div class="menu-section">
                <div class="menu-title" onclick="toggleMenu('train-info')">
                    <span><i class="fas fa-info-circle"></i> Informations du Train</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="menu-items active" id="train-info">
                    <div class="train-info">
                        <div class="info-label">Statut du train</div>
                        <div class="info-value" id="train-status">Arrêté</div>
                    </div>
                    
                    <div class="train-info">
                        <div class="info-label">Position actuelle</div>
                        <div class="info-value" id="train-position">-</div>
                    </div>
                    <div class="train-info">
                        <div class="info-label">Prochaine gare</div>
                        <div class="info-value" id="next-station">-</div>
                    </div>
                    <div class="train-info">
                        <div class="info-label">Vitesse actuelle</div>
                        <div class="info-value" id="current-speed">0 km/h</div>
                    </div>
                </div>
            </div>
            <!-- Station Info Panel -->
<div id="station-info-panel" class="station-info-panel">
    <button class="close-panel" onclick="hideStationInfo()">&times;</button>
    <h3 id="station-name">Nom de la station</h3>
    <div class="station-info-row">
        <span class="station-info-label">Type:</span>
        <span class="station-info-value" id="station-type">Gare</span>
    </div>
    <div class="station-info-row">
        <span class="station-info-label">Secteur:</span>
        <span class="station-info-value" id="station-sector">Nord</span>
    </div>
    <div class="station-info-row">
        <span class="station-info-label">Prochain train:</span>
        <span class="station-info-value" id="station-next-train">-</span>
    </div>
    <div class="station-info-row">
        <span class="station-info-label">Heure arrivée:</span>
        <span class="station-info-value" id="station-arrival">-</span>
    </div>
    <div class="station-info-row">
        <span class="station-info-label">Heure départ:</span>
        <span class="station-info-value" id="station-departure">-</span>
    </div>
    <div id="station-additional-info" style="margin-top: 10px; font-size: 14px;"></div>
</div>
            <!-- Map Controls Section -->
            <div class="menu-section">
                <div class="menu-title" onclick="toggleMenu('map-controls')">
                    <span><i class="fas fa-map-marked-alt"></i> Contrôles de la Carte</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="menu-items" id="map-controls">
                    <a href="#" class="menu-item" onclick="toggleMarkers(); return false;">
                        <i class="fas fa-map-pin"></i> Afficher/Masquer les gares
                    </a>
                    <a href="#" class="menu-item" onclick="toggleRailwayMenu(); return false;">
                        <i class="fas fa-route"></i> Afficher les lignes ferroviaires
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- Navbar -->
    <div class="navbar">
        <div class="datetime" id="datetime"></div>
        <input type="text" id="search-input" placeholder="Rechercher une gare..." onkeyup="searchStation()" />
        <div class="icons">
            <button class="icon-button" onclick="showAbout()">
                <i class="fas fa-info-circle"></i>
            </button>
            <button class="icon-button" onclick="toggleMarkers()">
                <i class="fas fa-map-pin"></i>
            </button>
            <button class="icon-button" onclick="toggleRailwayMenu()">
                <i class="fas fa-route"></i>
            </button>
            <a href="logout.php" class="icon-button">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
    <!-- Map -->
    <div id="map"></div>

    <!-- Railway Menu -->
    <div id="railway-menu">
        <button class="railway-btn" onclick="toggleRailway('blue')">Tunis-Bizerte</button>
        <button class="railway-btn" onclick="toggleRailway('red')">Tunis-Gobaa</button>
        <button class="railway-btn" onclick="toggleRailway('green')">Tunis-Ghardimaou</button>
        <button class="railway-btn" onclick="toggleRailway('black')">Tunis-Cedria</button>
        <button class="railway-btn" onclick="toggleRailway('aqua')">Tunis-Dahmani</button>
        <button class="railway-btn" onclick="toggleRailway('yellow')">Tunis-Nabeul</button>
        <button class="railway-btn" onclick="toggleRailway('purple')">Tunis-Sousse</button>
        <button class="railway-btn" onclick="toggleRailway('brown')">Tunis Bougatfa</button>
    </div>
    <!-- About Modal -->
    <div class="modal-overlay" id="modal-overlay" onclick="hideAbout()"></div>
    <div class="modal" id="about-modal">
        <div class="modal-header">
            <div class="modal-title">À Propos</div>
            <button class="modal-close" onclick="hideAbout()">&times;</button>
        </div>
        <p>Ce site permet de localiser différentes trains et gares ferroviaires en Tunisie et d'afficher leurs informations sur une carte interactive.</p>
        <p>Les données affichées proviennent de sources publiques et sont mises à jour régulièrement.</p>
        <div style="margin-top: 20px; text-align: right;">
            <button class="btn btn-primary" onclick="hideAbout()">Fermer</button>
        </div>
    </div>
     <!-- Dashboard Modal -->
<div class="modal" id="dashboard-modal">
    <div class="modal-header">
        <div class="modal-title">Tableau de Bord SNCFT</div>
        <button class="modal-close" onclick="hideDashboard()">&times;</button>
    </div>
    <div class="dashboard-content">
        <div class="metrics-container">
            <div class="metric-card">
                <div class="metric-value" id="total-stations">0</div>
                <div class="metric-label">Gares Ferroviaires</div>
                <div class="metric-icon"><i class="fas fa-train"></i></div>
            </div>
            
            <div class="metric-card">
                <div class="metric-value" id="active-trains">0</div>
                <div class="metric-label">Trains en Circulation</div>
                <div class="metric-icon"><i class="fas fa-subway"></i></div>
            </div>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        // Variables globales
        var map;
        var markers = [];
        var stations = [];
        var railwayLines = {};
        var railwayPolylines = {};
        var trainMarker;
        var trainAnimationInterval;
        var currentTrainPositionIndex = 0;
        var currentTrainLine = [];
        var currentTrainSpeed = 60;
        var isTrainMoving = false;
        
        // Initialisation de l'application
        function init() {
            initMap();
            loadStations();
            loadRailwayLines();
            updateDateTime();
            setInterval(updateDateTime, 1000);
        }
        
        // Initialisation de la carte
        function initMap() {
            map = L.map('map').setView([36.8083, 10.1528], 12);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            map.on('click', function() {
    hideStationInfo();
});
        }
        
        // Chargement des stations
        function loadStations() {
            stations = [
                { name: 'Gare de Tunis', coords: [36.7950, 10.1805], info: 'Place de Barcelone', sector: 'Tunis', type: 'Gare principale' },
                { name: 'Gare de Manouba', coords: [36.8159, 10.1012], info: 'Manouba', sector: 'Tunis-Bizerte Tunis-Ghardimaou', type: 'Gare principale' },
                { name: 'Gare de Jdaida', coords: [36.8509, 9.9332], info: 'Jdaida', sector: 'Tunis-Bizerte Tunis-Ghardimaou', type: 'Station' },
                { name: 'Gare de Tinja', coords: [37.1595, 9.7575], info: 'Tinja' , sector: 'Bizerte', type: 'Station' },
                { name: 'Gare de Chawat', coords: [36.8900, 9.9445], info: 'Chawat' , sector: 'Bizerte', type: 'Station' },
                { name: 'Gare de Mateur', coords: [37.0380, 9.6845], info: 'Mateur', sector: 'Bizerte', type: 'Station' },
                { name: 'Gare de Sidi Othmen', coords: [36.9590, 9.9250], info: 'Sidi othmen' , sector: 'Bizerte', type: 'Station'},
                { name: 'Gare de Ain Ghlel', coords: [37.0230, 9.8345], info: 'Ain Ghlel', sector: 'Bizerte', type: 'Station' },
                { name: 'Gare de Bizerte', coords: [37.2660, 9.8660], info: 'Bizerte', sector: 'Bizerte', type: 'Station' },
                { name: 'Gare de Béja', coords: [36.7250, 9.1900], info: 'Béja', sector: 'Tunis-Ghardimaou', type: 'Gare principale' },
                { name: 'Gare de Tebourba', coords: [36.8290, 9.8450], info: 'Tebourba', sector: 'Tunis-Ghardimaou', type: 'Station' },
                { name: 'Gare de Jendouba', coords: [36.5011, 8.7770], info: 'Jendouba', sector: 'Tunis-Ghardimaou', type: 'Gare principale' },
                { name: 'Gare de Ghardimaou', coords: [36.4480, 8.4370], info: 'Ghardimaou' ,sector: 'Tunis-Ghardimaou', type: 'Gare principale' },
                { name: 'Gare de Bourj Toumi', coords: [36.7565, 9.7200], info: 'Bourj Toumi', sector: 'Tunis-Ghardimaou', type: 'Station' },
                { name: 'Gare de Oued Zarga', coords: [36.6730, 9.4260], info: 'Oued Zarga' , sector: 'Tunis-Ghardimaou', type: 'Station' },
                { name: 'Gare de Oued Mliz', coords: [36.4690, 8.5497], info: 'Oued Mliz', sector: 'Tunis-Ghardimaou', type: 'Station' },
                { name: 'Gare de Medjez el Bab', coords: [36.6650, 9.6060], info: 'Medjez el Bab' , sector: 'Tunis-Ghardimaou', type: 'Station' },
                { name: 'Gare de Sousse', coords: [35.8300, 10.6385], info: 'Sousse' },
                { name: 'Gare de Mahdia', coords: [35.5005, 11.0640], info: 'Mahdia' },
                { name: 'Gare de Sfax', coords: [34.7350, 10.7667], info: 'Sfax' },
                { name: 'Gare de Gabès', coords: [33.8840, 10.1000], info: 'Gabès' },
                { name: 'Gare de Gafsa', coords: [34.3940, 8.8030], info: 'Gafsa' },
                { name: 'Gare de Nabeul', coords: [36.4510, 10.735], info: 'Nabeul' },
                { name: 'Gare du Kef', coords: [36.1667, 8.7040], info: 'Le Kef' },
                { name: 'Gare de Dahmani', coords: [35.9452, 8.8290], info: 'Dahmani' },
                { name: 'Gare de Kalâa Khasba', coords: [35.6600, 8.5910], info: 'Kalâa Khasba' },
                { name: 'Gare de Gaâfour', coords: [36.3220, 9.3260], info: 'Gaâfour' },
                { name: 'Station Eraouadha', coords: [36.8010, 10.1480], info: 'Eraouadha' },
                { name: 'Station Le Bardo', coords: [36.8070, 10.1350], info: 'Le Bardo' },
                { name: 'Station Mellacine', coords: [36.7960, 10.1559], info: 'Mellacine' },
                { name: 'Station Sida Manoubia', coords: [36.7866, 10.1654], info: 'Saida Manoubia' },
                { name: 'Station Les orangers', coords: [36.8180, 10.0855], info: 'Les orangers' },
                { name: 'Station Gobaa', coords: [36.8217, 10.0640], info: 'Gobaa' },
                { name: 'Station Jebel Jelloud', coords: [36.7720, 10.2090], info: 'Jebel Jelloud' },
                { name: 'Gare de Radès', coords: [36.7685, 10.2710], info: 'Radès' },
                { name: 'Station Ezzahra', coords: [36.7490, 10.3041], info: 'Ezzahra' },
                { name: 'Gare de Hammam Lif', coords: [36.7290, 10.3367], info: 'Hammam Lif' },
                { name: 'Station Borj Cédria', coords: [36.7037, 10.3970], info: 'Borj Cédria' },
                { name: 'Gare de Bir Bouregba', coords: [36.4301, 10.5743], info: 'Bir Bouregba' },
                { name: 'Gare de Bou Argoub', coords: [36.5309, 10.5515], info: 'Bou Argoub' },
                { name: 'Gare de Hammamet', coords: [36.4073, 10.6128], info: 'Hammamet' },
                { name: 'Gare de Grombalia', coords: [36.5959, 10.4980], info: 'Grombalia' },
                { name: "Station Cité Eriadh", coords: [36.6995, 10.4150], info: 'Cité Eriadh'},
                { name: "Station Kalaa Kobra", coords: [35.8680, 10.5530], info: 'Kalaa Kobra'},
                { name: "Station Naassen", coords: [36.7030, 10.2325], info: 'Nassen'},
                { name: "Station Bir Kassa", coords: [36.7399, 10.2290], info:'Bir Kassa'},
                { name: "Station Cheylus", coords: [36.5553, 10.0600], info: 'Cheylus'},
                { name: "Station Pont de Fahs", coords: [36.3765, 9.9020], info: 'Pont de Fahs' },
                { name: "Station Bou Arada", coords: [36.3570, 9.6260], info :'Bou Arada' },
                { name: "Station Sidi Bourouis", coords: [36.1751, 9.1250], info :'Sidi Bourouis' },
                { name: "Station Sidi Ayad", coords: [36.3511, 9.3910], info : 'Sidi Ayad' },
                { name: "Station Le Sers", coords: [36.0759, 9.0232], info :'Le Sers' },
                { name: "Station El aroussa", coords: [36.3810, 9.4547], info :'El aroussa' },
                { name: 'Gare de Le Krib', coords: [36.2540, 9.1860], info: 'Le Krib' },
                { name: 'Gare de Le khwet', coords: [36.2550, 9.2548], info: 'Le Khwet' },
                { name: "Les Zouarines", coords: [36.0230, 8.9029], info:'Les Zouarines' },
                { name: "Ain Masria", coords: [35.9122, 8.7393], info: 'Ain Masria' },
                { name: "Oudna", coords: [36.6253, 10.1510], info:'Oudna' },
                { name: "Khlidia", coords: [36.6462, 10.1945], info:'Khlidia' },
                { name: "depot", coords: [36.7875, 10.1897]},
                { name: "Gare Megrine", coords: [36.7683, 10.2340], info:'Megrine' },
                { name: "Gare de Fondok Jdid", coords: [36.6690, 10.4464] },
                { name: "Samech", coords: [36.6253, 10.4611], info:'Samech' },
                { name: "Bouficha", coords: [36.3025, 10.4510], info:'Bouficha' },
                { name: "Enfidha", coords: [36.1305, 10.3836], info:'Enfidha'},
                { name: "Gare Kalaa Soghra", coords: [35.8218, 10.5715], info:'Kalaa Soghra' },
                {name: "Smenja", coords:[36.4573, 10.0235], info:'Smenja'},
                { name: "Enajah", coords: [36.7930, 10.1547], info: 'Enajah' },
                { name: "Tayaran (Zouhour 1)", coords: [36.7920, 10.1390], info: 'Tayaran (Zouhour 1)' },
                { name: "Zouhour 2", coords: [36.7878, 10.1276], info: 'Zouhour 2' },
                { name: "Hrayriya", coords: [36.7840, 10.1155], info: 'Hrayriya' },
                { name: "Bougatfa (Sidi Hssine)", coords: [36.7800, 10.1019], info: 'Bougatfa (Sidi Hssine)' },
            ];
            
            
stations.forEach(function(station) {
    var marker = L.circleMarker(station.coords, {
        radius: 6,
        fillColor: "#3498db",
        color: "#fff",
        weight: 1,
        opacity: 1,
        fillOpacity: 0.8
    }).bindPopup('<b>' + station.name + '</b><br>' + station.info);
    
    // Ajouter l'événement de clic
    marker.on('click', function() {
        showStationInfo(station);
    });
    
    markers.push(marker);
    marker.addTo(map);
});

        }
        
        // Chargement des lignes ferroviaires
        function loadRailwayLines() {
            railwayLines = {
                blue: [
                    [36.7950, 10.1805], // Gare de Tunis
                    [36.7900, 10.1805],
                    [36.7866, 10.1754], 
                    [36.7850, 10.1700],
                    [36.7866, 10.1654], //Saida
                    [36.7900, 10.1632],
                    [36.7960, 10.1559], //malacine
                    [36.8010, 10.1480], //eraouadha
                    [36.8070, 10.1350], //bardo
                    [36.8159, 10.1012], // Gare de Manouba
                    [36.8180, 10.0855], //les orangers
                    [36.8217, 10.0640], //terminus gobaa
                    [36.8509, 9.9332],  // Gare de Jdaida
                    [36.8900, 9.9445],  // Gare de Chawat
                    [36.9590, 9.9250],  
                    [37.0230, 9.8345],  // 7 3wiet
                    [37.0200, 9.8000],
                    [37.0080, 9.7500],
                    [37.0380, 9.6845],  // Gare de Mateur
                    [37.1595, 9.7575],  // Gare de Tinja
                    [37.2450, 9.8117],   // Gare de Msida
                    [37.2660, 9.8660]   // Gare de Bizerte
                ],
                red: [
                    [36.7950, 10.1805], // Gare de Tunis
                    [36.7900, 10.1805],
                    [36.7866, 10.1754], 
                    [36.7850, 10.1700],
                    [36.7866, 10.1654], //Saida
                    [36.7900, 10.1632],
                    [36.7960, 10.1559], //malacine
                    [36.8010, 10.1480], //eraouadha
                    [36.8070, 10.1350], //bardo
                    [36.8159, 10.1012], // Gare de Manouba
                    [36.8180, 10.0855], //les orangers
                    [36.8217, 10.0640], //gobaa
                ],
                green: [
                    [36.7950, 10.1805], // Gare de Tunis
                    [36.7900, 10.1805],
                    [36.7866, 10.1754], 
                    [36.7850, 10.1700],
                    [36.7866, 10.1654], //Saida
                    [36.7900, 10.1632],
                    [36.7960, 10.1559], //malacine
                    [36.8010, 10.1480], //eraouadha
                    [36.8070, 10.1350], //bardo
                    [36.8159, 10.1012], // Gare de Manouba
                    [36.8180, 10.0855], //les orangers
                    [36.8217, 10.0640], //terminus gobaa
                    [36.8509, 9.9332],  // Gare de Jdaida
                    [36.8290, 9.8450], //tborba
                    [36.7565, 9.7200],
                    [36.6650, 9.6060],
                    [36.6730, 9.4260], //oued zarga
                    [36.7000, 9.4000],
                    [36.7700, 9.3060],
                    [36.7585, 9.2060],
                    [36.7250, 9.1900], //beja
                    [36.6000, 9.1900],
                    [36.5800, 9.0000],
                    [36.6000, 8.8900],
                    [36.5011, 8.7770], //jendouba
                    [36.4485, 8.6700],
                    [36.4690, 8.5497], //oued mliz
                    [36.4480, 8.4370], //ghardimaou
                ],
                black: [
                    [36.7950, 10.1805], // Gare de Tunis
                    [36.7900, 10.1815],
                    [36.7875, 10.1897],
                    [36.7720, 10.2090], //jbal jloud
                    [36.7683, 10.2340],
                    [36.7685, 10.2710], //rades
                    [36.7490, 10.3041], //ezzahra
                    [36.7290, 10.3367], //hammam lif
                    [36.7030, 10.4000], //borj cedria
                    [36.6995, 10.4150]
                ],
                aqua: [
                    [36.7950, 10.1805], // Gare de Tunis
                    [36.7900, 10.1815],
                    [36.7875, 10.1897],
                    [36.7720, 10.2090], // Jbal Jloud
                    [36.7399, 10.2290], // Bir Kassa
                    [36.7030, 10.2325], // Nassen
                    [36.6462, 10.1945], //khlidia
                    [36.6253, 10.1510], //oudna
                    [36.5553, 10.0600], // Cheylus
                    [36.5303, 10.0215],
                    [36.4573, 10.0235],//smenja
                    [36.3765, 9.9020],  // Fahs
                    [36.3570, 9.6260],  // Bouarada
                    [36.3810, 9.4547],
                    [36.3511, 9.3910], //sidiayad
                    [36.3220, 9.3260],  // Gaafour
                    [36.2550, 9.2548], //khwet
                    [36.2540, 9.1860],
                    [36.1751, 9.1250], //sidi bourouis
                    [36.0759, 9.0232],  // Sers
                    [36.0230, 8.9029],
                    [35.9452, 8.8290],  // Dahmani
                ],
                yellow: [
                    [36.7950, 10.1805], // Gare de Tunis
                    [36.7900, 10.1815],
                    [36.7875, 10.1897],
                    [36.7866, 10.1902],
                    [36.7720, 10.2090], //jbal jloud
                    [36.7683, 10.2340],
                    [36.7685, 10.2710], //rades
                    [36.7490, 10.3041], //ezzahra
                    [36.7290, 10.3367], //hammam lif
                    [36.7030, 10.4000], //borj cedria
                    [36.6690, 10.4464], //fondok jdid
                    [36.6253, 10.4611], //samech
                    [36.5959, 10.4980],//grombalia
                    [36.5309, 10.5515],//bouargoub
                    [36.4301, 10.5743],//bir bouregba
                    [36.4073, 10.6128],//hammamet
                    [36.4510, 10.735],//nabeul
                ],
                purple: [
                    [36.7950, 10.1805], // Gare de Tunis
                    [36.7900, 10.1815],
                    [36.7875, 10.1897],
                    [36.7866, 10.1902],
                    [36.7720, 10.2090], //jbal jloud
                    [36.7683, 10.2340],
                    [36.7685, 10.2710], //rades
                    [36.7490, 10.3041], //ezzahra
                    [36.7290, 10.3367], //hammam lif
                    [36.7030, 10.4000], //borj cedria
                    [36.6690, 10.4464], //fondok jdid
                    [36.6253, 10.4611], //samech
                    [36.5959, 10.4980],//grombalia
                    [36.5309, 10.5515],//bouargoub
                    [36.4301, 10.5743],//bir bouregba
                    [36.3025, 10.4510],//bouficha
                    [36.1305, 10.3836],//enfidha
                    [35.8680, 10.5530],//kalaa kobra
                    [35.8218, 10.5715],//kalaa soghra
                    [35.8300, 10.6385],//sousse
                ],
                brown: [
        [36.7950, 10.1805], // Gare de Tunis
        [36.7900, 10.1805],
        [36.7866, 10.1754], 
        [36.7850, 10.1705],
        [36.7866, 10.1654], // Saida Manoubia
        [36.7900, 10.1632],
        [36.7930, 10.1547], // Enajah
        [36.7940, 10.1530],
        [36.7947, 10.1490],
        [36.7920, 10.1390], // Tayaran (Zouhour 1)
        [36.7878, 10.1276], // Zouhour 2
        [36.7840, 10.1155], // Hrayriya
        [36.7800, 10.1019]  // Bougatfa (Sidi Hssine)
    ],
            };
            
            // Création des polylignes pour chaque ligne
            for (var color in railwayLines) {
                railwayPolylines[color] = L.polyline(railwayLines[color], {
                    color: color,
                    weight: 4,
                    opacity: 0.7,
                    dashArray: '10, 10'
                });
            }
        }
        
        // Afficher/masquer les marqueurs des gares
        function toggleMarkers() {
            if (map.hasLayer(markers[0])) {
                markers.forEach(marker => map.removeLayer(marker));
            } else {
                markers.forEach(marker => marker.addTo(map));
            }
        }
        
        // Afficher/masquer le menu des lignes ferroviaires
        function toggleRailwayMenu() {
            var menu = document.getElementById('railway-menu');
            menu.classList.toggle('active');
        }
        
        // Afficher/masquer une ligne ferroviaire spécifique
        function toggleRailway(color) {
            if (railwayPolylines[color]) {
                if (map.hasLayer(railwayPolylines[color])) {
                    map.removeLayer(railwayPolylines[color]);
                } else {
                    railwayPolylines[color].addTo(map);
                }
            }
        }
        
        // Rechercher une gare
        function searchStation() {
            var input = document.getElementById('search-input').value.toLowerCase();
            var found = stations.find(station => station.name.toLowerCase().includes(input));
            
            if (found) {
                map.setView(found.coords, 15);
                
                // Ouvrir le popup de la gare trouvée
                markers.forEach(marker => {
                    if (marker.getLatLng().equals(found.coords)) {
                        marker.openPopup();
                    }
                });
            }
        }
        
        // Afficher/masquer la sidebar
        function toggleSidebar() {
            var sidebar = document.querySelector('.sidebar');
            var map = document.getElementById('map');
            var navbar = document.querySelector('.navbar');
            
            if (sidebar.style.transform === 'translateX(-300px)') {
                sidebar.style.transform = 'translateX(0)';
                map.style.left = '300px';
                navbar.style.left = '300px';
            } else {
                sidebar.style.transform = 'translateX(-300px)';
                map.style.left = '0';
                navbar.style.left = '0';
            }
        }
        
        // Afficher/masquer un sous-menu
        function toggleMenu(menuId) {
            var menu = document.getElementById(menuId);
            menu.classList.toggle('active');
            
            // Animer l'icône de chevron
            var chevron = menu.previousElementSibling.querySelector('.fa-chevron-down');
            chevron.classList.toggle('rotate');
        }
        
        // Afficher la modal "À propos"
        function showAbout() {
            document.getElementById('modal-overlay').classList.add('active');
            document.getElementById('about-modal').classList.add('active');
        }
        
        // Masquer la modal "À propos"
        function hideAbout() {
            document.getElementById('modal-overlay').classList.remove('active');
            document.getElementById('about-modal').classList.remove('active');
        }
        // Afficher la modal Dashboard
// Fonctions Dashboard
function showDashboard() {
    document.getElementById('modal-overlay').classList.add('active');
    document.getElementById('dashboard-modal').classList.add('active');
    updateDashboardMetrics();
}

function hideDashboard() {
    document.getElementById('modal-overlay').classList.remove('active');
    document.getElementById('dashboard-modal').classList.remove('active');
}

// Mettre à jour les positions des conducteurs périodiquement et mettre à jour le dashboard
function updateDriverPositionsAndDashboard() {
    updateDriverPositions().then(activeDrivers => {
        // Mettre à jour le compteur dans le dashboard
        document.getElementById('active-trains').textContent = activeDrivers;
        
        // Si le dashboard est ouvert, rafraîchir toutes les métriques
        if (document.getElementById('dashboard-modal').classList.contains('active')) {
            updateDashboardMetrics();
        }
    });
}

// Remplacer l'ancien setInterval par le nouveau
setInterval(updateDriverPositionsAndDashboard, 5000);

// Charger les positions au démarrage
updateDriverPositionsAndDashboard();

function updateDashboardMetrics() {
    // Mettre à jour les métriques
    document.getElementById('total-stations').textContent = stations.length;
    
    // Le nombre de trains actifs est maintenant géré par updateDriverPositionsAndDashboard
    
    // Statistiques factices (à remplacer par des données réelles)
    document.getElementById('departures-today').textContent = Math.floor(Math.random() * 50) + 20;
    document.getElementById('delayed-trains').textContent = Math.floor(Math.random() * 5);
    document.getElementById('on-time').textContent = (100 - Math.floor(Math.random() * 5)) + '%';
    
    // Initialiser le graphique (si Chart.js est inclus)
    if (typeof Chart !== 'undefined') {
        initTrainTypeChart();
    }
}

function initTrainTypeChart() {
    var ctx = document.getElementById('trainTypeChart').getContext('2d');
    var chart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['EMU', 'DMU', 'DP', 'DI', 'GT', 'AEX'],
            datasets: [{
                data: [35, 25, 15, 10, 10, 5],
                backgroundColor: [
                    '#3498db',
                    '#e74c3c',
                    '#2ecc71',
                    '#f39c12',
                    '#9b59b6',
                    '#1abc9c'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'right'
            }
        }
    });
}
        
        // Mettre à jour la date et l'heure
        function updateDateTime() {
            var now = new Date();
            var options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            document.getElementById('datetime').textContent = now.toLocaleDateString('fr-FR', options);
        }
        // Fonctions pour la communication
function openCommunicationPanel() {
    document.getElementById('modal-overlay').classList.add('active');
    document.getElementById('communication-modal').classList.add('active');
    
    // Charger les messages existants
    fetchMessages();
}

function hideCommunicationPanel() {
    document.getElementById('modal-overlay').classList.remove('active');
    document.getElementById('communication-modal').classList.remove('active');
}

function sendMessage() {
    var message = document.getElementById('message-input').value;
    if (!message.trim()) return;
    
    // Ajouter le message à l'interface immédiatement
    addMessageToInterface('Vous', message, new Date());
    
    // Envoyer le message au serveur
    fetch('send.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'sender=control&message=' + encodeURIComponent(message)
    })
    .then(response => response.text())
    .then(data => {
        console.log('Message sent:', data);
        document.getElementById('message-input').value = '';
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function fetchMessages() {
    fetch('get.php?receiver=control')
    .then(response => response.json())
    .then(messages => {
        var container = document.getElementById('messages-container');
        container.innerHTML = '';
        
        messages.forEach(msg => {
            addMessageToInterface(msg.sender, msg.message, new Date(msg.timestamp));
        });
    })
    .catch(error => {
        console.error('Error fetching messages:', error);
    });
}

function addMessageToInterface(sender, message, timestamp) {
    var container = document.getElementById('messages-container');
    var messageDiv = document.createElement('div');
    messageDiv.style.marginBottom = '10px';
    messageDiv.style.padding = '8px';
    messageDiv.style.backgroundColor = sender === 'Vous' ? '#e3f2fd' : '#f1f1f1';
    messageDiv.style.borderRadius = '5px';
    messageDiv.style.color = 'black'; // ✅ Texte en noir

    var timeStr = timestamp.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    messageDiv.innerHTML = `<strong>${sender}</strong> (${timeStr}):<br>${message}`;

    container.appendChild(messageDiv);
    container.scrollTop = container.scrollHeight;
}
// Variables pour suivre les conducteurs
var driverMarkers = {};

function updateDriverPositions() {
    return fetch('get_positions.php')
        .then(response => response.json())
        .then(drivers => {
            // Supprimer les marqueurs des conducteurs qui ne sont plus actifs
            for (var driverId in driverMarkers) {
                if (!drivers.some(d => d.id == driverId)) {
                    map.removeLayer(driverMarkers[driverId]);
                    delete driverMarkers[driverId];
                }
            }

            // Ajouter/mettre à jour les marqueurs des conducteurs
            drivers.forEach(driver => {
                var position = [driver.lat, driver.lng];
                
                if (!driverMarkers[driver.id]) {
                    // Créer un nouveau marqueur
                    var driverIcon = L.divIcon({
                        className: 'driver-marker',
                        html: '<i class="fas fa-user" style="color: black; font-size: 20px; position: absolute; top: 3px; left: 3px;"></i>',
                        iconSize: [16, 16]
                    });
                    
                    driverMarkers[driver.id] = L.marker(position, {
                        icon: driverIcon,
                        zIndexOffset: 1000
                    }).bindPopup(`
                        <b>${driver.name}</b><br>
                        Type de train: ${driver.train_type}<br>
                        Vitesse: ${driver.speed} km/h<br>
                        Dernière mise à jour: ${new Date(driver.timestamp * 1000).toLocaleTimeString()}
                    `).addTo(map);
                } else {
                    // Mettre à jour la position existante
                    driverMarkers[driver.id].setLatLng(position);
                    driverMarkers[driver.id].setPopupContent(`
                        <b>${driver.name}</b><br>
                        Type de train: ${driver.train_type}<br>
                        Vitesse: ${driver.speed} km/h<br>
                        Dernière mise à jour: ${new Date(driver.timestamp * 1000).toLocaleTimeString()}
                    `);
                }
            });
            
            return drivers.length;
        })
        .catch(error => {
            console.error('Error fetching driver positions:', error);
            return 0;
        });
}

// Mettre à jour les positions des conducteurs périodiquement
setInterval(updateDriverPositions, 5000);

// Charger les positions au démarrage
updateDriverPositions();

// Vérifier les nouveaux messages périodiquement
setInterval(fetchMessages, 5000); // Toutes les 5 secondes
        
        // Initialiser l'application au chargement
        window.onload = init;
        function tog(menuId) {
    showDashboard(); // Affiche le dashboard au lieu de basculer un menu
}
function showStationInfo(station) {
    const panel = document.getElementById('station-info-panel');
    const now = new Date();
    
    // Générer des heures aléatoires pour la démo
    const nextHour = new Date(now.getTime() + Math.floor(Math.random() * 60) * 60000);
    const arrivalTime = new Date(nextHour.getTime() - Math.floor(Math.random() * 10) * 60000);
    const departureTime = new Date(nextHour.getTime() + Math.floor(Math.random() * 10) * 60000);
    
    // Mettre à jour le panel avec les informations
    document.getElementById('station-name').textContent = station.name;
    document.getElementById('station-type').textContent = station.type || 'Station';
    document.getElementById('station-sector').textContent = station.sector || 'Tunis';
    document.getElementById('station-next-train').textContent = nextHour.toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'});
    document.getElementById('station-arrival').textContent = arrivalTime.toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'});
    document.getElementById('station-departure').textContent = departureTime.toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'});
    
    // Info supplémentaire
    document.getElementById('station-additional-info').textContent = station.info || "Informations supplémentaires non disponibles";
    
    // Afficher le panel
    panel.style.display = 'block';
}

// Masquer le panel d'information
function hideStationInfo() {
    document.getElementById('station-info-panel').style.display = 'none';
}
    </script>
</body>
</html>