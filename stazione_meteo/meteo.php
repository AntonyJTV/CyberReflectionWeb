<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualizzazione Dati Meteo</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
    <h1>Dati Meteo Ultime 10 Giorni</h1>

<?php

    // Configurazione connessione al database (modificare con le proprie credenziali)
    $servername = "localhost"; // Solitamente localhost
    $username = "admin"; // Inserisci il tuo username del database
    $password = "piwo6t6j"; // Inserisci la tua password del database
    $dbname = "schema.sql"; // Inserisci il nome del tuo database

    // Crea la connessione
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Verifica la connessione
    if ($conn->connect_error) {
        die("Connessione al database fallita: " + $conn->connect_error);
    }

    // Imposta il fuso orario per la visualizzazione (Italia)
    mysqli_query($conn, "SET time_zone = '+02:00'"); // CEST

    // Query per selezionare i dati degli ultimi 10 giorni
    $sql = "SELECT timestamp_utc, temperatura_celsius, umidita_percentuale
            FROM dati_meteo
            WHERE timestamp_utc >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 DAY)
            ORDER BY timestamp_utc DESC";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<thead><tr><th>Timestamp (Ora Locale)</th><th>Temperatura (°C)</th><th>Umidità (%)</th></tr></thead>";
        echo "<tbody>";
        while ($row = $result->fetch_assoc()) {
            // Conversione del timestamp UTC all'ora locale (CEST)
            $timestamp_locale = new DateTime($row["timestamp_utc"], new DateTimeZone('UTC'));
            $timestamp_locale->setTimezone(new DateTimeZone('Europe/Rome')); // Fuso orario italiano
            echo "<tr><td>" . $timestamp_locale->format('Y-m-d H:i:s') . "</td><td>" . $row["temperatura_celsius"] . "</td><td>" . $row["umidita_percentuale"] . "</td></tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<p>Nessun dato meteo trovato per gli ultimi 10 giorni.</p>";
    }

    // Chiudi la connessione
    $conn->close();

    ?>

</body>
</html>