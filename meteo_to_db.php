<?php
// Configura i dati di accesso al database
$host = 'localhost';
$db   = 'arduino_data';
$user = 'root';
$pass = 'piwo6t6j';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Errore di connessione al DB: " . $e->getMessage());
}

// Coordinate esempio (puoi cambiarle)
$latitude = 40.85;
$longitude = 17.36;

// Costruisci URL dell'API Open-Meteo (con i parametri corretti)
$apiUrl = "https://api.open-meteo.com/v1/forecast?latitude=$latitude&longitude=$longitude&current=temperature_2m,relative_humidity_2m,is_day,weather_code,surface_pressure,wind_speed_10m,apparent_temperature";

// Aggiungi un User-Agent per evitare il 403 Forbidden
$opts = [
    "http" => [
        "method" => "GET",
        "header" => "User-Agent: Mozilla/5.0\r\n"
    ]
];
$context = stream_context_create($opts);
$response = file_get_contents($apiUrl, false, $context);

if ($response === FALSE) {
    die("Errore nella richiesta all'API");
}

$data = json_decode($response, true);
if (!isset($data['current'])) {
    die("Risposta API non valida");
}

$current = $data['current'];

$timestamp = date('Y-m-d H:i:s');
$temperature = $current['temperature_2m'];
$humidity = $current['relative_humidity_2m'];
$is_day = $current['is_day'];
$weather_code = $current['weather_code'];
$pressure = $current['surface_pressure'];
$wind_speed = $current['wind_speed_10m'];
$apparent_temperature = $current['apparent_temperature'];

// Inserisci nel DB
$sql = "INSERT INTO weather_api_data
    (timestamp, temperature, humidity, is_day, weather_code, pressure, wind_speed, apparent_temperature)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    $timestamp,
    $temperature,
    $humidity,
    $is_day,
    $weather_code,
    $pressure,
    $wind_speed,
    $apparent_temperature
]);

echo "Dati meteo inseriti con successo!";
?>
