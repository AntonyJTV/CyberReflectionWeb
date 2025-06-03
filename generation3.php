<?php
// generation.php - Script per la generazione di dati simulati per sensore DHT11, basati su dati già presenti in weather_api_data

// Configurazione del database
$host = 'localhost';
$db    = 'arduino_data';
$user = 'root';        // <-- CAMBIA QUESTO con il tuo utente del database MySQL
$pass = 'piwo6t6j';    // <-- CAMBIA QUESTO con la tua password del database MySQL
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // Abilita la segnalazione degli errori PDO
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // Imposta la modalità di fetch predefinita
    PDO::ATTR_EMULATE_PREPARES => false,                // Disabilita l'emulazione delle prepared statements per maggiore sicurezza e performance
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Gestione dell'errore di connessione al database
    die("Errore di connessione al DB: " . $e->getMessage() . "\n");
}

// Imposta il fuso orario (essenziale per calcoli accurati basati sull'ora del giorno/anno)
date_default_timezone_set('Europe/Rome');

// Intervallo di tempo tra le rilevazioni simulate (5 minuti in secondi)
$interval_seconds = 5 * 60; // For sensor_data, which could be more frequent

// Prepare the SQL query for inserting new data into 'sensor_data' table
$sql_insert = "INSERT INTO sensor_data (temp_dht, hum_dht, timestamp) VALUES (?, ?, ?)";
$stmt_insert = $pdo->prepare($sql_insert);

// Global cache for weather_api_data to avoid redundant DB queries for the same day/hour
// This will store data fetched in hourly blocks for efficiency
$weather_api_data_cache = [];

/**
 * Fetches relevant data from weather_api_data table for a given timestamp.
 * Caches results to avoid repeated DB queries for the same hour.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $timestamp_unix The Unix timestamp to find data for.
 * @return array|null Returns an associative array of data (temperature, humidity, apparent_temperature) or null if not found.
 */
function getWeatherDataFromDB(PDO $pdo, int $timestamp_unix): ?array {
    global $weather_api_data_cache;

    // Normalize timestamp to the hour for caching
    $hourly_timestamp_str = date('Y-m-d H:00:00', $timestamp_unix);
    $cache_key = $hourly_timestamp_str;

    if (isset($weather_api_data_cache[$cache_key])) {
        return $weather_api_data_cache[$cache_key];
    }

    $sql = "SELECT temperature, humidity, apparent_temperature FROM weather_api_data WHERE timestamp = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$hourly_timestamp_str]);
    $data = $stmt->fetch();

    if ($data) {
        $weather_api_data_cache[$cache_key] = $data;
        return $data;
    }

    return null; // No data found for this timestamp
}


/**
 * Genera la temperatura DHT simulata basata su temperatura (o temperatura percepita) da weather_api_data.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $timestamp_unix Il timestamp Unix del punto di tempo per cui generare il dato.
 * @return float La temperatura simulata in gradi Celsius.
 */
function generateDHTTemperature(PDO $pdo, int $timestamp_unix): float {
    $weather_data = getWeatherDataFromDB($pdo, $timestamp_unix);

    // Prefer apparent_temperature if available, otherwise fall back to temperature
    if ($weather_data && isset($weather_data['apparent_temperature'])) {
        $base_temp = (float)$weather_data['apparent_temperature'];
    } elseif ($weather_data && isset($weather_data['temperature'])) {
        $base_temp = (float)$weather_data['temperature'];
    } else {
        // Fallback if no weather_api_data is found for the timestamp
        // This will be a simple, less realistic fallback, but prevents errors.
        error_log("WARNING: No weather_api_data found for " . date('Y-m-d H:i:s', $timestamp_unix) . ". Using fallback temperature.");
        $hour = (int)date('G', $timestamp_unix);
        return round(($hour >= 6 && $hour <= 18) ? rand(18, 25) : rand(10, 17), 1);
    }

    // Sottrai un numero casuale tra 0 e 5 (nuovo intervallo)
    $subtraction = mt_rand(0, 50) / 10.0; // Random float from 0.0 to 5.0
    $dht_temp = $base_temp - $subtraction;

    // Aggiungi un piccolo rumore intrinseco del sensore DHT11 (es. +/- 0.5 gradi)
    $sensor_noise = mt_rand(-5, 5) / 10.0; // +/- 0.5
    $dht_temp += $sensor_noise;

    // Clamping per valori ragionevoli del DHT11
    return round(max(-10.0, min(50.0, $dht_temp)), 1);
}

/**
 * Genera l'umidità DHT simulata basata su umidità da weather_api_data.
 *
 * @param PDO $pdo The PDO database connection object.
 * @param int $timestamp_unix Il timestamp Unix del punto di tempo per cui generare il dato.
 * @return float L'umidità simulata in percentuale.
 */
function generateDHTHumidity(PDO $pdo, int $timestamp_unix): float {
    $weather_data = getWeatherDataFromDB($pdo, $timestamp_unix);

    if ($weather_data && isset($weather_data['humidity'])) {
        $base_hum = (float)$weather_data['humidity'];
    } else {
        // Fallback if no weather_api_data is found for the timestamp
        error_log("WARNING: No weather_api_data found for " . date('Y-m-d H:i:s', $timestamp_unix) . ". Using fallback humidity.");
        $hour = (int)date('G', $timestamp_unix);
        return round(($hour >= 20 || $hour < 7) ? rand(70, 90) : rand(40, 60), 1);
    }

    // Variazione casuale tra -10 e +10 (nuovo intervallo per +/- 10)
    $variation = mt_rand(-100, 100) / 10.0; // Random float from -10.0 to 10.0
    $dht_hum = $base_hum + $variation;

    // Aggiungi un piccolo rumore intrinseco del sensore DHT11 (es. +/- 2%)
    $sensor_noise = mt_rand(-20, 20) / 10.0; // +/- 2.0
    $dht_hum += $sensor_noise;

    // Clamping per valori ragionevoli del DHT11
    return round(max(10.0, min(100.0, $dht_hum)), 1);
}


/**
 * Funzione per eseguire la generazione e l'inserimento dei dati.
 *
 * @param int $start_time_unix Il timestamp Unix da cui iniziare a generare.
 * @param int $end_time_unix Il timestamp Unix fino a cui generare.
 * @param int $interval_seconds L'intervallo tra le misurazioni in secondi.
 * @param PDO $pdo The PDO database connection object.
 * @param PDOStatement $stmt_insert Lo statement PDO preparato per l'inserimento.
 * @param string $context Messaggio di contesto per i log.
 * @return int Il numero di record inseriti.
 */
function generateAndInsertData(int $start_time_unix, int $end_time_unix, int $interval_seconds, PDO $pdo, PDOStatement $stmt_insert, string $context = ""): int {
    $inserted_count = 0;
    echo $context . "\n";

    // It's assumed that weather_api_data is already populated for the relevant date range.
    // We don't fetch a huge block here, as getWeatherDataFromDB caches hourly data.

    for ($time_unix = $start_time_unix + $interval_seconds; $time_unix <= $end_time_unix; $time_unix += $interval_seconds) {
        $timestamp_sql = date('Y-m-d H:i:s', $time_unix);

        $temp_dht = generateDHTTemperature($pdo, $time_unix);
        $hum_dht  = generateDHTHumidity($pdo, $time_unix);

        try {
            $stmt_insert->execute([$temp_dht, $hum_dht, $timestamp_sql]);
            $inserted_count++;
            // Feedback ogni 100 record per non sovraccaricare la console
            if ($inserted_count % 100 === 0) {
                echo "    -> Inseriti $inserted_count record. Ultimo: " . $timestamp_sql . "\n";
            }
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') { // SQLSTATE 23000 = violazione di integrità (es. chiave duplicata)
                // Ignora i duplicati, significa che il dato era già presente
            } else {
                echo "    ERRORE CRITICO DB per " . $timestamp_sql . ": " . $e->getMessage() . "\n";
            }
        }
    }
    return $inserted_count;
}

// --- LOGICA PRINCIPALE DELLO SCRIPT ---

echo "Inizio del processo di gestione dati simulati...\n";

// --- FASE 0: PULIZIA DEI DATI VECCHI ---
echo "\n--- FASE 0: Pulizia dati (eliminazione record più vecchi di 7 giorni) ---\n";
$seven_days_ago_midnight = date('Y-m-d 00:00:00', strtotime('-7 days'));
$sql_delete = "DELETE FROM sensor_data WHERE timestamp < ?";
$stmt_delete = $pdo->prepare($sql_delete);

try {
    $stmt_delete->execute([$seven_days_ago_midnight]);
    $deleted_rows = $stmt_delete->rowCount();
    echo "Eliminati $deleted_rows record più vecchi del " . $seven_days_ago_midnight . ".\n";
} catch (\PDOException $e) {
    echo "ERRORE durante l'eliminazione dei dati vecchi: " . $e->getMessage() . "\n";
}


// 1. Ottieni l'ultimo timestamp presente nel database dopo la pulizia
$sql_last_timestamp = "SELECT MAX(timestamp) AS last_timestamp FROM sensor_data";
$stmt_last_timestamp = $pdo->query($sql_last_timestamp);
$result = $stmt_last_timestamp->fetch();
$last_timestamp_db_str = $result['last_timestamp'];

// Inizializza il timestamp di partenza per la generazione (il più antico necessario)
// Questo è 6 giorni fa a mezzanotte (quindi copre 7 giorni incluso oggi)
$target_start_of_generation_unix = strtotime(date('Y-m-d 00:00:00', strtotime('-6 days')));

// Determina il punto di partenza effettivo per la generazione
$actual_start_unix = $target_start_of_generation_unix;
if ($last_timestamp_db_str) {
    $last_timestamp_db_unix = strtotime($last_timestamp_db_str);
    // Se l'ultimo timestamp nel DB è più recente del nostro target di 6 giorni fa,
    // iniziamo da lì. Altrimenti, iniziamo dal target di 6 giorni fa.
    if ($last_timestamp_db_unix > $target_start_of_generation_unix) {
        $actual_start_unix = $last_timestamp_db_unix;
        echo "\nL'ultimo dato presente nel DB è del " . date('Y-m-d H:i:s', $last_timestamp_db_unix) . ".\n";
        echo "Verifico e riempio eventuali gap fino a oggi.\n";
    } else {
        echo "\nNessun dato recente trovato per gli ultimi 6 giorni. Inizio generazione dallo storico.\n";
    }
} else {
    echo "\nIl database 'sensor_data' è vuoto dopo la pulizia. Genero dati a partire da 6 giorni fa.\n";
}

// --- FASE 1: Genera i dati per gli scorsi 6 giorni (fino a mezzanotte di ieri) ---
$yesterday_midnight_unix = strtotime(date('Y-m-d 23:59:59', strtotime('-1 day')));

$initial_generated_count = 0;
// Se `actual_start_unix` è precedente o uguale a mezzanotte di ieri, generiamo per quel periodo.
if ($actual_start_unix <= $yesterday_midnight_unix) {
    echo "\n--- FASE 1: Generazione dati storici (ultimi 6 giorni completi) ---\n";
    $initial_generated_count = generateAndInsertData(
        $actual_start_unix,
        $yesterday_midnight_unix,
        $interval_seconds,
        $pdo, // Pass PDO object to the generation function
        $stmt_insert,
        "Riempiendo storico mancante fino a ieri:"
    );
    if ($initial_generated_count > 0) {
        echo "Generati $initial_generated_count record per i giorni precedenti.\n";
    } else {
        echo "Nessun nuovo record generato per i giorni precedenti (già presenti o non necessari).\n";
    }
} else {
    echo "\n--- FASE 1: Dati storici recenti già presenti o non necessari. ---\n";
}


// --- FASE 2: Chiedi all'utente se riempire il giorno corrente (il 7°) ---
echo "\n--- FASE 2: Generazione dati per il giorno corrente ---\n";
echo "Vuoi riempire i dati per il giorno corrente (oggi)? (s/n): ";
$fill_today_choice = trim(fgets(STDIN));

$today_generated_count = 0;

if (strtolower($fill_today_choice) === 's') {
    echo "Vuoi riempire il giorno corrente fino a:\n";
    echo "    0: Mezzanotte di oggi\n";
    echo "    1: L'ora attuale\n";
    echo "Inserisci la tua scelta (0 o 1): ";
    $end_today_option = trim(fgets(STDIN));

    $end_current_day_unix;
    if ($end_today_option === "0") {
        $end_current_day_unix = strtotime(date('Y-m-d 23:59:59'));
    } elseif ($end_today_option === "1") {
        $end_current_day_unix = time();
    } else {
        echo "Scelta non valida per il giorno corrente. Saltato il riempimento di oggi.\n";
        $end_current_day_unix = 0; // Imposta a 0 per non generare
    }

    // Se l'utente ha scelto una destinazione valida per oggi
    if ($end_current_day_unix > 0) {
        // Aggiorna l'ultimo timestamp nel DB, dato che la FASE 1 potrebbe averne inseriti di nuovi.
        $re_query_last_timestamp = $pdo->query("SELECT MAX(timestamp) AS last_timestamp FROM sensor_data")->fetch();
        $last_data_point_after_history_fill_unix = strtotime($re_query_last_timestamp['last_timestamp']);

        // Assicurati che l'inizio della generazione per oggi sia almeno l'inizio del giorno corrente
        $start_of_today_unix = strtotime(date('Y-m-d 00:00:00'));
        if ($last_data_point_after_history_fill_unix < $start_of_today_unix) {
            $last_data_point_after_history_fill_unix = $start_of_today_unix;
        }

        // Assicurati di non superare l'ora attuale o la mezzanotte di oggi.
        // E che il punto di partenza per oggi non sia già oltre il punto di fine desiderato per oggi.
        if ($last_data_point_after_history_fill_unix < $end_current_day_unix) {
            $today_generated_count = generateAndInsertData(
                $last_data_point_after_history_fill_unix,
                $end_current_day_unix,
                $interval_seconds,
                $pdo, // Pass PDO object to the generation function
                $stmt_insert,
                "Generando dati per oggi fino a " . date('H:i:s', $end_current_day_unix) . ":"
            );
            echo "Generati $today_generated_count record per il giorno corrente.\n";
        } else {
            echo "Dati per il giorno corrente già aggiornati o non necessari.\n";
        }
    }
} else {
    echo "Scelta 'n'. Il giorno corrente non verrà riempito.\n";
}

echo "\n--- PROCESSO COMPLETATO ---\n";
echo "Sono stati inseriti un totale di " . ($initial_generated_count + $today_generated_count) . " nuovi record nel database 'sensor_data'.\n";
echo "Ora puoi aprire la tua pagina web per visualizzare i grafici aggiornati.\n";

?>
