//funzionante
<?php
// Dettagli di connessione al database
$servername = "localhost"; // o IP del Raspberry Pi
$username = "root";        // Sostituisci con il tuo username del DB
$password = "piwo6t6j";       // Sostituisci con la tua password
$dbname = "arduino_data";

// Crea connessione
$conn = new mysqli($servername, $username, $password, $dbname);

// Verifica connessione
if ($conn->connect_error) {
    die("Connessione al database fallita: " . $conn->connect_error);
}

// Ottieni i dati POST
$temp_dht = $_POST['temp_dht'] ?? null;
$hum_dht = $_POST['hum_dht'] ?? null;
$temp_lm35 = $_POST['temp_lm35'] ?? null;

// Verifica che i dati siano validi
if ($temp_dht !== null && $hum_dht !== null && $temp_lm35 !== null) {
    // Query SQL per inserire i dati
    $sql = "INSERT INTO sensor_data (temp_dht, hum_dht, temp_lm35) VALUES (?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Bind dei parametri (d = double)
        $stmt->bind_param("ddd", $temp_dht, $hum_dht, $temp_lm35);

        if ($stmt->execute()) {
            echo "Dati salvati con successo.";
        } else {
            echo "Errore durante il salvataggio: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Errore nella preparazione della query: " . $conn->error;
    }
} else {
    echo "Dati mancanti: temp_dht, hum_dht, temp_lm35 richiesti.";
}

$conn->close();
?>
