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
            width: 50%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9em;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 4px 6px;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .container {
        display: flex;
        justify-content: center; /* Centra orizzontalmente gli elementi figli */
        /* Altre proprietà di stile per il contenitore se necessario */
    }

    canvas {
        max-width: 80%;
        margin-top: 40px;
        /* Non è necessario margin-left e margin-right auto qui */
    }
    </style>
</head>
<body>
    <h1>Dati Meteo Ultimi 10 Giorni</h1>

    <?php
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "meteo";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connessione al database fallita: " . $conn->connect_error);
    }

    mysqli_query($conn, "SET time_zone = '+02:00'");

    $sql = "SELECT timestamp_utc, temperatura_celsius, umidita_percentuale
            FROM dati_meteo
            WHERE timestamp_utc >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 DAY)
            ORDER BY timestamp_utc ASC";
    $result = $conn->query($sql);

    $labels = [];
    $temperature = [];
    $humidity = [];

    if ($result->num_rows > 0) {
        echo "<table>";
        echo "<thead><tr><th>Timestamp (Ora Locale)</th><th>Temperatura (°C)</th><th>Umidità (%)</th></tr></thead>";
        echo "<tbody>";
        while ($row = $result->fetch_assoc()) {
            $timestamp_locale = new DateTime($row["timestamp_utc"], new DateTimeZone('UTC'));
            $timestamp_locale->setTimezone(new DateTimeZone('Europe/Rome'));
            $ts = $timestamp_locale->format('Y-m-d H:i');
            echo "<tr><td>$ts</td><td>{$row['temperatura_celsius']}</td><td>{$row['umidita_percentuale']}</td></tr>";

            $labels[] = $ts;
            $temperature[] = $row['temperatura_celsius'];
            $humidity[] = $row['umidita_percentuale'];
        }
        echo "</tbody></table>";
    } else {
        echo "<p>Nessun dato meteo trovato per gli ultimi 10 giorni.</p>";
    }

    $conn->close();
    ?>
    <div class="container">
    <!-- Canvas per il grafico -->
    <canvas id="meteoChart"></canvas>

    <!-- Chart.js da CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('meteoChart').getContext('2d');
        const meteoChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [
                    {
                        label: 'Temperatura (°C)',
                        data: <?php echo json_encode($temperature); ?>,
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: 0.3
                    },
                    {
                        label: 'Umidità (%)',
                        data: <?php echo json_encode($humidity); ?>,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: { ticks: { maxTicksLimit: 10 } },
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</div>
</body>
</html>
