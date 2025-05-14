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
    <title>Dashboard Meteo</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: sans-serif;
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }
        .chart-container {
            position: relative;
            width: 100%;
            height: 400px;
            margin-bottom: 50px;
        }
        select {
            margin: 10px 0 30px;
            padding: 6px;
        }
    </style>
</head>
<body>

<h1>📊 Dashboard Meteo</h1>

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

<div class="chart-container"><canvas id="arduinoTempChart"></canvas></div>
<div class="chart-container"><canvas id="apiTempChart"></canvas></div>
<div class="chart-container"><canvas id="compareTempChart"></canvas></div>
<div class="chart-container"><canvas id="compareHumChart"></canvas></div>
<div class="chart-container"><canvas id="pressureChart"></canvas></div>
<div class="chart-container"><canvas id="windChart"></canvas></div>
<div class="chart-container"><canvas id="apparentTempChart"></canvas></div>
<div class="chart-container"><canvas id="weatherCodeChart"></canvas></div>

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
            }
        }
    });
}

// Grafico temperature Arduino
createChart('arduinoTempChart', sensorLabels, [
    { label: 'Temp DHT', data: tempDHT, borderColor: 'blue', fill: false },
]);

// Grafico temperatura API
createChart('apiTempChart', apiLabels, [
    { label: 'Temperatura API', data: apiTemp, borderColor: 'green', fill: false }
]);

// Grafico confronto temperature
createChart('compareTempChart', sensorLabels, [
    { label: 'Temp DHT (Arduino)', data: tempDHT, borderColor: 'orange', fill: false },
    { label: 'Temp API', data: apiTemp.slice(0, tempDHT.length), borderColor: 'green', fill: false }
]);

// Grafico confronto umidità
createChart('compareHumChart', sensorLabels, [
    { label: 'Umidità DHT (Arduino)', data: humDHT, borderColor: 'blue', fill: false },
    { label: 'Umidità API', data: apiHum.slice(0, humDHT.length), borderColor: 'purple', fill: false }
]);

// Pressione atmosferica
createChart('pressureChart', apiLabels, [
    { label: 'Pressione', data: apiPressure, borderColor: 'brown', fill: false }
]);

// Velocità del vento
createChart('windChart', apiLabels, [
    { label: 'Velocità Vento', data: apiWind, borderColor: 'gray', fill: false }
]);

// Temperatura percepita
createChart('apparentTempChart', apiLabels, [
    { label: 'Temp Percepita', data: apiAppTemp, borderColor: 'red', fill: false }
]);

// Codice meteo (con linea spezzata)
createChart('weatherCodeChart', apiLabels, [
    { label: 'Codice Meteo', data: apiCode, borderColor: 'black', stepped: true, fill: false }
]);
</script>

</body>
</html>