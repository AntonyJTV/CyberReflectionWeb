
#include <WiFi.h>
#include <ArduinoHttpClient.h>
#include "DHT.h"

// Configurazione Wi-Fi
const char* ssid = "NOME_RETE_WIFI";     // Sostituisci con il nome della tua rete Wi-Fi
const char* password = "PASSWORD_WIFI"; // Sostituisci con la password della tua rete Wi-Fi

// Configurazione Server
const char* serverIP = "IP_SERVER"; // Sostituisci con l'indirizzo IP del tuo server
const int serverPort = 80;         // Porta del server (generalmente 80 per HTTP)
const char* httpEndpoint = "/percorso/script_server.php"; // Sostituisci con il percorso dello script PHP sul server

// Sensori
#define DHTPIN 5  // Pin a cui è collegato il sensore DHT11
#define DHTTYPE DHT11 // Tipo di sensore DHT (DHT11 o DHT22)

DHT dht(DHTPIN, DHTTYPE);
WiFiClient wifiClient;
HttpClient client = HttpClient(wifiClient, serverIP, serverPort);

void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("Connessione a Wi-Fi...");
  WiFi.begin(ssid, password);
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }
  Serial.println("\nConnesso!");
  Serial.print("Indirizzo IP: ");
  Serial.println(WiFi.localIP());

  dht.begin();
}

void loop() {
  // Lettura DHT11
  float tempDHT = dht.readTemperature();
  float humDHT = dht.readHumidity();

  if (isnan(tempDHT) || isnan(humDHT)) {
    Serial.println("Errore lettura DHT11.");
  } else {
    // Costruzione dei dati POST
    String postData = "temp_dht=" + String(tempDHT, 1) +
                        "&hum_dht=" + String(humDHT, 1);

    Serial.println("Invio dati al server:");
    Serial.println(postData);

    client.post(httpEndpoint, "application/x-www-form-urlencoded", postData);

    int statusCode = client.responseStatusCode();
    String response = client.responseBody();

    Serial.print("Codice HTTP: ");
    Serial.println(statusCode);
    Serial.print("Risposta server: ");
    Serial.println(response);
  }

  delay(60000); // Attesa tra invii (60 secondi)
}
     