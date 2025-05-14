<?php
// Connessione al database
$host = 'localhost';
$db   = 'arduino_data';
$user = 'root'; // Cambia con il tuo utente
$pass = '****'; // Cambia con la tua password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Errore di connessione al DB: " . $e->getMessage());
}

// Gestione dell'intervallo di dati (oggi, ieri, ultima ora)
$filter = $_GET['range'] ?? 'today';
switch ($filter) {
    case 'hour':
        $where = "timestamp >= NOW() - INTERVAL 1 HOUR";
        break;
    case 'yesterday':
        $where = "DATE(timestamp) = CURDATE() - INTERVAL 1 DAY";
        break;
    case 'week':
        $where = "timestamp >= NOW() - INTERVAL 1 WEEK";
        break;
    case 'month':
        $where = "timestamp >= NOW() - INTERVAL 1 MONTH";
        break;
    case 'all':
        $where = "1"; // Mostra tutti i dati
        break;
    case 'today':
    default:
        $where = "DATE(timestamp) = CURDATE()";
        break;
}

// Recupera i dati dal database
$stmt1 = $pdo->query("SELECT timestamp, temp_dht, hum_dht FROM sensor_data WHERE $where ORDER BY timestamp");
$sensor_data = $stmt1->fetchAll();

$stmt2 = $pdo->query("SELECT timestamp, temperature, humidity, pressure, wind_speed, apparent_temperature, weather_code FROM weather_api_data WHERE $where ORDER BY timestamp");
$weather_data = $stmt2->fetchAll();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Meteo</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300..700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Quicksand', sans-serif;
            background-color: #111;
            color: #f0f0f0;
            line-height: 1.7;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background-color: #1e1e1e;
        }
        .logo {
            display: flex;
            align-items: center;
        }
        .logo img {
            height: 40px;
            margin-right: 1rem;
            display: none;
        }
        .logo h1 {
            font-size: 2em;
            margin: 0;
        }
        nav ul {
            list-style: none;
            display: flex;
            gap: 1.5rem;
            margin: 0;
            padding: 0;
        }
        nav a {
            color: #f0f0f0;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }
        nav a:hover {
            color: #00bcd4;
        }
        main {
            padding: 2rem;
            max-width: 1000px;
            margin: 0 auto;
        }
        section {
            margin-bottom: 3rem;
        }
        section h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid #00bcd4;
            padding-bottom: 0.5rem;
        }
        section p {
            font-size: 1.1rem;
            color: #ccc;
        }
        .chart-container {
            background-color: #1e1e1e;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        footer {
            text-align: center;
            padding: 1rem;
            background-color: #1e1e1e;
            margin-top: 4rem;
            font-size: 0.9rem;
            color: #888;
        }
        select {
            margin: 1rem 0 2rem;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid #444;
            background-color: #2a2a2a;
            color: #f0f0f0;
            font-size: 1.1rem;
        }
        select:focus {
            outline: none;
            border-color: #00bcd4;
            box-shadow: 0 0 5px rgba(0, 188, 212, 0.3);
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #ddd;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <h1>Dashboard Meteo</h1>
        </div>
        <nav>
            <ul>
                <li><a href="index.html">Introduzione</a></li>
                <li><a href="docs/architettura.html">Architettura</a></li>
                <li><a href="docs/rpi_server.html">Server RPi</a></li>
                <li><a href="docs/rpi_client.html">Client RPi</a></li>
                <li><a href="docs/arduino.html">Arduino</a></li>
                 <li><a href="docs/configurazione.html">Configurazione</a></li>
                <li><a href="#contatti">Contatti</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <section>
            <h2>Visualizzazione Dati Meteo</h2>
            <p>Seleziona un intervallo di tempo per visualizzare i dati raccolti dai sensori.</p>
            <form method="get">
                <label for="range">Seleziona intervallo:</label>
                <select name="range" id="range" onchange="this.form.submit()">
                    <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>Oggi</option>
                    <option value="hour" <?= $filter === 'hour' ? 'selected' : '' ?>>Ultima ora</option>
                    <option value="yesterday" <?= $filter === 'yesterday' ? 'selected' : '' ?>>Ieri</option>
                    <option value="week" <?= $filter === 'week' ? 'selected' : '' ?>>Ultima settimana</option>
                    <option value="month" <?= $filter === 'month' ? 'selected' : '' ?>>Ultimo mese</option>
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Tutto</option>
                </select>
            </form>
        </section>

        <section>
            <div class="chart-container">
                <canvas id="arduinoTempChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="apiTempChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="compareTempChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="compareHumChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="pressureChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="windChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="apparentTempChart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="weatherCodeChart"></canvas>
            </div>
        </section>
    </main>
    <footer>
        <p>&copy; 2025 Magic Mirror Project | Dashboard Meteo</p>
    </footer>

    <script>
    // Converte i dati PHP in JSON JavaScript
    const sensorLabels = <?= json_encode(array_column($sensor_data, 'timestamp')) ?>;
    const tempDHT = <?= json_encode(array_column($sensor_data, 'temp_dht')) ?>;
    const humDHT = <?= json_encode(array_column($sensor_data, 'hum_dht')) ?>;

    const apiLabels = <?= json_encode(array_column($weather_data, 'timestamp')) ?>;
    const apiTemp = <?= json_encode(array_column($weather_data, 'temperature')) ?>;
    const apiHum = <?= json_encode(array_column($weather_data, 'humidity')) ?>;
    const apiPressure = <?= json_encode(array_column($weather_data, 'pressure')) ?>;
    const apiWind = <?= json_encode(array_column($weather_data, 'wind_speed')) ?>;
    const apiAppTemp = <?= json_encode(array_column($weather_data, 'apparent_temperature')) ?>;
    const apiCode = <?= json_encode(array_column($weather_data, 'weather_code')) ?>;

    // Funzione per creare grafici generici
    function createChart(id, labels, datasets) {
        new Chart(document.getElementById(id), {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: {
                        ticks: { autoSkip: true, maxTicksLimit: 25 },
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            color: '#f0f0f0',
                            font: {
                                size: 14
                            }
                        }
                    }
                }
            }
        });
    }

    // Grafico temperature Arduino
    createChart('arduinoTempChart', sensorLabels, [
        { label: 'Temperatura DHT', data: tempDHT, borderColor: '#00bcd4', backgroundColor: 'rgba(0, 188, 212, 0.2)', fill: true, pointRadius: 0 },
    ]);

    // Grafico temperatura API
    createChart('apiTempChart', apiLabels, [
        { label: 'Temperatura API', data: apiTemp, borderColor: '#4caf50', backgroundColor: 'rgba(76, 175, 80, 0.2)', fill: true, pointRadius: 0 }
    ]);

    // Grafico confronto temperature
    createChart('compareTempChart', sensorLabels, [
        { label: 'Temperatura DHT (Arduino)', data: tempDHT, borderColor: '#ff9800', backgroundColor: 'rgba(255, 152, 0, 0.2)', fill: true, pointRadius: 0 },
        { label: 'Temperatura API', data: apiTemp.slice(0, tempDHT.length), borderColor: '#4caf50', backgroundColor: 'rgba(76, 175, 80, 0.2)', fill: true, pointRadius: 0 }
    ]);

    // Grafico confronto umidità
    createChart('compareHumChart', sensorLabels, [
        { label: 'Umidità DHT (Arduino)', data: humDHT, borderColor: '#2196f3', backgroundColor: 'rgba(33, 150, 243, 0.2)', fill: true, pointRadius: 0 },
        { label: 'Umidità API', data: apiHum.slice(0, humDHT.length), borderColor: '#9c27b0', backgroundColor: 'rgba(156, 39, 176, 0.2)', fill: true, pointRadius: 0 }
    ]);

    // Pressione atmosferica
    createChart('pressureChart', apiLabels, [
        { label: 'Pressione', data: apiPressure, borderColor: '#795548', backgroundColor: 'rgba(121, 85, 72, 0.2)', fill: true, pointRadius: 0 }
    ]);

    // Velocità del vento
    createChart('windChart', apiLabels, [
        { label: 'Velocità Vento', data: apiWind, borderColor: '#607d8b', backgroundColor: 'rgba(96, 125, 139, 0.2)', fill: true, pointRadius: 0 }
    ]);

    // Temperatura percepita
    createChart('apparentTempChart', apiLabels, [
        { label: 'Temperatura Percepita', data: apiAppTemp, borderColor: '#f44336', backgroundColor: 'rgba(244, 67, 54, 0.2)', fill: true, pointRadius: 0 }
    ]);

    // Codice meteo (con linea spezzata)
    createChart('weatherCodeChart', apiLabels, [
        { label: 'Codice Meteo', data: apiCode, borderColor: '#000000', backgroundColor: 'rgba(0, 0, 0, 0.2)', stepped: true, fill: true, pointRadius: 0 }
    ]);
    </script>
</body>
</html>
