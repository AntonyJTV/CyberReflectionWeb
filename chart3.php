<?php
// Imposta il fuso orario italiano
date_default_timezone_set('Europe/Rome');

// --- DB setup ---
$host = 'localhost';
$db    = 'arduino_data';
$user = 'root';         // <-- Cambia con il tuo utente
$pass = 'piwo6t6j';     // <-- Cambia con la tua password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Errore DB: " . $e->getMessage());
}

// --- Parameters: Get range from GET request, default to 'today' ---
// Use filter_input for safer GET variable retrieval
$range = filter_input(INPUT_GET, 'range', FILTER_SANITIZE_STRING);

// Validate and set default if invalid or not set
if (!in_array($range, ['all', 'yesterday_today', 'today'])) {
    $range = 'today'; // Default to today's data
}

// --- Time filter based on selected range ---
$where = "1"; // Default to all
switch ($range) {
    case 'today':
        $where = "DATE(timestamp) = CURDATE()";
        break;
    case 'yesterday_today':
        $where = "DATE(timestamp) = CURDATE() OR DATE(timestamp) = CURDATE() - INTERVAL 1 DAY";
        break;
    case 'all':
        $where = "1"; // All data, no specific date filter
        break;
}

// --- Query data ---
$sensor_data = $pdo->query("SELECT timestamp, temp_dht, hum_dht FROM sensor_data WHERE $where ORDER BY timestamp")->fetchAll();
$weather_data = $pdo->query("SELECT timestamp, temperature, humidity, pressure, wind_speed, apparent_temperature, weather_code FROM weather_api_data WHERE $where ORDER BY timestamp")->fetchAll();

// --- Prepare JS data ---
$sensorLabels = json_encode(array_column($sensor_data, 'timestamp'));
$tempDHT = json_encode(array_column($sensor_data, 'temp_dht'));
$humDHT = json_encode(array_column($sensor_data, 'hum_dht'));
$apiLabels = json_encode(array_column($weather_data, 'timestamp'));
$apiTemp = json_encode(array_column($weather_data, 'temperature'));
$apiHum = json_encode(array_column($weather_data, 'humidity'));
$apiPressure = json_encode(array_column($weather_data, 'pressure'));
$apiWind = json_encode(array_column($weather_data, 'wind_speed'));
$apiAppTemp = json_encode(array_column($weather_data, 'apparent_temperature'));
$apiCode = json_encode(array_column($weather_data, 'weather_code'));

// Define available chart types for JS
$chartTypes = json_encode([
    "temp_arduino",
    "temp_api",
    "temp_compare",
    "hum_compare",
    "pressure",
    "wind",
    "apparent",
    "code"
]);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Chart - Dati Sensori e API</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            background: black;
            height: 100%;
            overflow: hidden; /* Prevent scrollbars from appearing */
            font-family: Arial, sans-serif; /* A basic font for readability */
        }
        #chart-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            padding-top: 80px; /* Increased space for title and range buttons */
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        canvas {
            display: block;
            width: 100%;
            height: 100%;
            max-height: calc(100% - 80px); /* Adjust based on new padding-top */
            flex-grow: 1;
        }
        .nav-button {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(255, 255, 255, 0.5);
            color: black;
            border: none;
            padding: 10px 15px;
            font-size: 24px;
            cursor: pointer;
            z-index: 100;
            user-select: none;
        }
        #prev-button {
            left: 10px;
        }
        #next-button {
            right: 10px;
        }
        #chart-title {
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            font-size: 20px;
            z-index: 100;
            white-space: nowrap;
        }
        #range-selection {
            position: absolute;
            top: 45px; /* Positioned below the main title */
            left: 50%;
            transform: translateX(-50%);
            z-index: 100;
            display: flex;
            gap: 10px; /* Space between buttons */
        }
        .range-button {
            background-color: rgba(255, 255, 255, 0.3);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 5px 10px;
            font-size: 14px;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.2s, border-color 0.2s;
        }
        .range-button:hover {
            background-color: rgba(255, 255, 255, 0.5);
        }
        .range-button.active {
            background-color: #007bff; /* Highlight active button */
            border-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .no-data-message {
            color: white;
            text-align: center;
            padding-top: 100px; /* Enough space to avoid overlapping with fixed elements */
            font-size: 18px;
        }
    </style>
</head>
<body>
<div id="chart-container">
    <div id="chart-title"></div>
    <div id="range-selection">
        <button class="range-button" data-range="all">Storico</button>
        <button class="range-button" data-range="yesterday_today">Ieri e Oggi</button>
        <button class="range-button" data-range="today">Oggi</button>
    </div>
    <button id="prev-button" class="nav-button">&lt;</button>
    <canvas id="chart"></canvas>
    <button id="next-button" class="nav-button">&gt;</button>
</div>

<script>
// PHP data injected into JavaScript
const sensorLabels = <?= $sensorLabels ?>;
const tempDHT = <?= $tempDHT ?>;
const humDHT = <?= $humDHT ?>;
const apiLabels = <?= $apiLabels ?>;
const apiTemp = <?= $apiTemp ?>;
const apiHum = <?= $apiHum ?>;
const apiPressure = <?= $apiPressure ?>;
const apiWind = <?= $apiWind ?>;
const apiAppTemp = <?= $apiAppTemp ?>;
const apiCode = <?= $apiCode ?>;
const chartTypes = <?= $chartTypes ?>;

// Current selected range from PHP
const currentRange = "<?= $range ?>"; // Injects the PHP variable into JS

// Debugging: Log injected data to console
console.log("Injected PHP Data:");
console.log("sensorLabels:", sensorLabels);
console.log("tempDHT:", tempDHT);
console.log("humDHT:", humDHT);
console.log("apiLabels:", apiLabels);
console.log("apiTemp:", apiTemp);
console.log("apiHum:", apiHum);
console.log("apiPressure:", apiPressure);
console.log("apiWind:", apiWind);
console.log("apiAppTemp:", apiAppTemp);
console.log("apiCode:", apiCode);
console.log("chartTypes:", chartTypes);
console.log("currentRange:", currentRange);


// Check if any data exists to prevent Chart.js errors with empty arrays
const hasSensorData = sensorLabels.length > 0 && tempDHT.length > 0;
const hasApiData = apiLabels.length > 0 && apiTemp.length > 0;

if (!hasSensorData && !hasApiData) {
    // If no data, display a message and disable navigation
    document.getElementById('chart-container').innerHTML = "<p class='no-data-message'>Nessun dato disponibile per i grafici nell'intervallo selezionato.</p>" +
                                                        "<div id='range-selection' style='position:relative; top:auto; transform:none; margin-top:20px;'></div>"; // Re-add range selection below message
    // Re-select the range-selection div to re-attach buttons
    const rangeSelectionDiv = document.getElementById('range-selection');
    const chartContainer = document.getElementById('chart-container');
    // Move range buttons from original chart-container to the new div
    const originalRangeSelectionDiv = document.querySelector('#range-selection');
    if (originalRangeSelectionDiv) {
        // Clear old content and add new message
        chartContainer.innerHTML = "<p class='no-data-message'>Nessun dato disponibile per i grafici nell'intervallo selezionato.</p>";
        chartContainer.appendChild(originalRangeSelectionDiv); // Append the actual div
    }

    // Disable navigation buttons as there's no chart
    document.getElementById('prev-button').style.display = 'none';
    document.getElementById('next-button').style.display = 'none';
    // Add event listeners to the range buttons
    document.querySelectorAll('.range-button').forEach(button => {
        button.addEventListener('click', function() {
            window.location.href = '?range=' + this.dataset.range;
        });
        if (button.dataset.range === currentRange) {
            button.classList.add('active');
        }
    });

    throw new Error("No data available. Stopping chart rendering.");
}


let currentChartIndex = 0;
let myChart; // To store the Chart.js instance

const chartTitles = {
    "temp_arduino": "Temperatura DHT (Arduino)",
    "temp_api": "Temperatura API",
    "temp_compare": "Comparazione Temperatura",
    "hum_compare": "Comparazione Umidità",
    "pressure": "Pressione API",
    "wind": "Velocità Vento API",
    "apparent": "Temperatura Percepita API",
    "code": "Codice Meteo API"
};

function updateChartTitle(type) {
    document.getElementById('chart-title').innerText = chartTitles[type] || "Tipo non valido";
}

function createChart(labels, datasets) {
    console.log("Attempting to create chart...");
    console.log("Labels:", labels);
    console.log("Datasets:", datasets);

    // Basic validation for labels and data
    if (!labels || labels.length === 0 || !datasets || datasets.length === 0 || datasets.every(ds => !ds.data || ds.data.length === 0)) {
        console.warn("Cannot create chart: Labels or datasets are empty. Hiding canvas.");
        if (myChart) {
            myChart.destroy();
            myChart = null; // Clear reference
        }
        document.getElementById('chart').style.display = 'none'; // Hide canvas if no data
        return;
    } else {
        document.getElementById('chart').style.display = 'block'; // Ensure canvas is visible
    }

    if (myChart) {
        myChart.destroy(); // Destroy existing chart before creating a new one
        console.log("Destroyed previous chart instance.");
    }
    const ctx = document.getElementById('chart').getContext('2d');
    if (!ctx) {
        console.error("Failed to get 2D context for canvas!");
        return;
    }

    myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: false // Disable Chart.js built-in title
                },
                legend: {
                    labels: { color: "white" },
                    position: 'top',
                    align: 'center'
                }
            },
            scales: {
                x: {
                    ticks: { maxTicksLimit: 25, color: "white" },
                    grid: { color: 'rgba(255, 255, 255, 0.1)' } // Light grid lines
                },
                y: {
                    ticks: { color: "white" },
                    grid: { color: 'rgba(255, 255, 255, 0.1)' } // Light grid lines
                }
            },
            layout: {
                padding: {
                    top: 10,
                    bottom: 0,
                    left: 0,
                    right: 0
                }
            }
        }
    });
    console.log("Chart created successfully.");
}

function displayChart(index) {
    console.log(`Attempting to display chart index: ${index}`);
    const type = chartTypes[index];
    updateChartTitle(type);

    let labels;
    let datasets;

    switch (type) {
        case "temp_arduino":
            if (!hasSensorData) { console.warn("No sensor data for temp_arduino."); labels = []; datasets = []; break; }
            labels = sensorLabels;
            datasets = [
                { label: "Temperatura DHT", data: tempDHT, borderColor: "blue", fill: false }
            ];
            break;
        case "temp_api":
            if (!hasApiData) { console.warn("No API data for temp_api."); labels = []; datasets = []; break; }
            labels = apiLabels;
            datasets = [
                { label: "Temperatura API", data: apiTemp, borderColor: "green", fill: false }
            ];
            break;
        case "temp_compare":
            // For comparison charts, if one dataset is missing, we can still show the other if available
            if (!hasSensorData && !hasApiData) { console.warn("No data for temp_compare."); labels = []; datasets = []; break; }
            labels = sensorLabels.length > apiLabels.length ? sensorLabels : apiLabels; // Use the longer label set for comparisons

            const datasetsCompareTemp = [];
            if (hasSensorData) {
                datasetsCompareTemp.push({ label: "Temperatura DHT", data: tempDHT, borderColor: "orange", fill: false });
            }
            if (hasApiData) {
                // Align API data to sensor labels or vice-versa, or use a common time axis
                // For simplicity here, if labels come from sensor, slice api data to match length
                const comparableApiTemp = labels === sensorLabels ? apiTemp.slice(0, sensorLabels.length) : apiTemp;
                datasetsCompareTemp.push({ label: "Temperatura API", data: comparableApiTemp, borderColor: "green", fill: false });
            }
            datasets = datasetsCompareTemp;
            break;
        case "hum_compare":
            if (!hasSensorData && !hasApiData) { console.warn("No data for hum_compare."); labels = []; datasets = []; break; }
            labels = sensorLabels.length > apiLabels.length ? sensorLabels : apiLabels;

            const datasetsCompareHum = [];
            if (hasSensorData) {
                datasetsCompareHum.push({ label: "Umidità DHT", data: humDHT, borderColor: "blue", fill: false });
            }
            if (hasApiData) {
                const comparableApiHum = labels === sensorLabels ? apiHum.slice(0, humDHT.length) : apiHum;
                datasetsCompareHum.push({ label: "Umidità API", data: comparableApiHum, borderColor: "purple", fill: false });
            }
            datasets = datasetsCompareHum;
            break;
        case "pressure":
            if (!hasApiData) { console.warn("No API data for pressure."); labels = []; datasets = []; break; }
            labels = apiLabels;
            datasets = [
                { label: "Pressione", data: apiPressure, borderColor: "brown", fill: false }
            ];
            break;
        case "wind":
            if (!hasApiData) { console.warn("No API data for wind."); labels = []; datasets = []; break; }
            labels = apiLabels;
            datasets = [
                { label: "Velocità Vento", data: apiWind, borderColor: "gray", fill: false }
            ];
            break;
        case "apparent":
            if (!hasApiData) { console.warn("No API data for apparent."); labels = []; datasets = []; break; }
            labels = apiLabels;
            datasets = [
                { label: "Temp Percepita", data: apiAppTemp, borderColor: "red", fill: false }
            ];
            break;
        case "code":
            if (!hasApiData) { console.warn("No API data for code."); labels = []; datasets = []; break; }
            labels = apiLabels;
            datasets = [
                { label: "Codice Meteo", data: apiCode, borderColor: "white", stepped: true, fill: false }
            ];
            break;
        default:
            document.getElementById('chart-container').innerHTML = "<p class='no-data-message'>Tipo di grafico non valido.</p>";
            if (myChart) { myChart.destroy(); }
            return;
    }

    if (labels && labels.length > 0 && datasets && datasets.some(ds => ds.data && ds.data.length > 0)) {
        createChart(labels, datasets);
    } else {
        console.warn(`No valid data for chart type: ${type}. Displaying empty state.`);
        if (myChart) { myChart.destroy(); myChart = null; }
        document.getElementById('chart').style.display = 'none'; // Hide canvas
        // No need to update chart-title here, it's handled by updateChartTitle at the start of displayChart
    }
}

function showNextChart() {
    currentChartIndex = (currentChartIndex + 1) % chartTypes.length;
    displayChart(currentChartIndex);
}

function showPrevChart() {
    currentChartIndex = (currentChartIndex - 1 + chartTypes.length) % chartTypes.length;
    displayChart(currentChartIndex);
}

// Event listeners for chart navigation
document.getElementById('next-button').addEventListener('click', showNextChart);
document.getElementById('prev-button').addEventListener('click', showPrevChart);

// Event listeners for range selection buttons
document.querySelectorAll('.range-button').forEach(button => {
    button.addEventListener('click', function() {
        window.location.href = '?range=' + this.dataset.range; // Reload page with new range
    });
    // Add active class to the currently selected button
    if (button.dataset.range === currentRange) {
        button.classList.add('active');
    }
});

// Wheel event listener
document.addEventListener('wheel', (event) => {
    if (event.deltaY > 0) {
        showNextChart();
    } else {
        showPrevChart();
    }
});

// Initial chart display
displayChart(currentChartIndex);
</script>
</body>
</html>
