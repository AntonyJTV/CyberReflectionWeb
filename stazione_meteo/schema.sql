-- Creazione della tabella per memorizzare i dati di temperatura e umidità
CREATE TABLE IF NOT EXISTS dati_meteo (
    timestamp_utc DATETIME PRIMARY KEY, -- Timestamp in formato UTC per evitare problemi di fuso orario
    temperatura_celsius DECIMAL(4, 2), -- Temperatura in gradi Celsius (4 cifre totali, 2 decimali)
    umidita_percentuale DECIMAL(3, 1)  -- Umidità relativa in percentuale (3 cifre totali, 1 decimale)
);

-- Funzione per popolare la tabella con dati simulati degli ultimi 10 giorni (una lettura oraria)
-- NOTA: Questa è una funzione per scopi dimostrativi. In un ambiente reale,
-- i dati verrebbero inseriti da un sensore o da un'altra fonte di dati.
DELIMITER //
CREATE PROCEDURE popola_dati_meteo_ultimi_10_giorni()
BEGIN
    DECLARE i INT DEFAULT 0;
    DECLARE now_utc DATETIME;

    -- Imposta il fuso orario a UTC per coerenza
    SET time_zone = '+00:00';
    SET now_utc = UTC_TIMESTAMP();

    WHILE i < 240 DO -- 10 giorni * 24 ore/giorno = 240 record
        INSERT INTO dati_meteo (timestamp_utc, temperatura_celsius, umidita_percentuale)
        VALUES (
            DATE_SUB(now_utc, INTERVAL i HOUR),
            ROUND(RAND() * 30 + 5, 2),    -- Temperatura simulata tra 5 e 35 gradi Celsius
            ROUND(RAND() * 60 + 30, 1)     -- Umidità simulata tra 30% e 90%
        );
        SET i = i + 1;
    END WHILE;
END //
DELIMITER ;

-- Chiamata alla procedura per popolare la tabella (da eseguire una sola volta per l'inizializzazione)
-- CALL popola_dati_meteo_ultimi_10_giorni();

-- Query per selezionare tutti i dati degli ultimi 10 giorni
SELECT timestamp_utc, temperatura_celsius, umidita_percentuale
FROM dati_meteo
WHERE timestamp_utc >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 DAY)
ORDER BY timestamp_utc DESC;

-- Query per selezionare la temperatura e l'umidità dell'ora corrente (UTC)
SELECT temperatura_celsius, umidita_percentuale
FROM dati_meteo
WHERE timestamp_utc = (SELECT MAX(timestamp_utc) FROM dati_meteo);

-- Query per selezionare la temperatura e l'umidità di un'ora specifica (sostituisci 'YYYY-MM-DD HH:MM:SS')
-- SELECT temperatura_celsius, umidita_percentuale
-- FROM dati_meteo
-- WHERE timestamp_utc = 'YYYY-MM-DD HH:MM:SS';