#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>


const char* ssid     = "JM17";
const char* password = "hinagane";


const char* serverUrl = "http://192.168.212.62/Babala_Baha/api_update_status.php";

// ---------- PINS ----------
const int analogInPin  = A0;  // water level sensor analog output
const int greenLedPin  = D2;  // NORMAL
const int yellowLedPin = D3;  // ALERT
const int redLedPin    = D4;  // CRITICAL
const int buzzerPin    = D5;  // BUZZER (CRITICAL)

// ---------- STATUS ----------
String currentStatus = "NONE";
String lastStatus    = "NONE";
int sensorValue      = 0;

unsigned long loopCounter = 0;

void setup() {
  Serial.begin(115200);
  delay(100);

  Serial.println();
  Serial.println("===== BabalaBaha ESP8266 START =====");

  pinMode(greenLedPin,  OUTPUT);
  pinMode(yellowLedPin, OUTPUT);
  pinMode(redLedPin,    OUTPUT);
  pinMode(buzzerPin,    OUTPUT);

  digitalWrite(greenLedPin,  LOW);
  digitalWrite(yellowLedPin, LOW);
  digitalWrite(redLedPin,    LOW);
  digitalWrite(buzzerPin,    LOW);

  Serial.print("[WIFI] Connecting to SSID: ");
  Serial.println(ssid);
  WiFi.begin(ssid, password);

  int wifiAttempt = 0;
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    wifiAttempt++;
    Serial.print(".");
    if (wifiAttempt > 40) {
      Serial.println();
      Serial.println("[WIFI] WARNING: Not connected after 20 seconds.");
      break;
    }
  }

  Serial.println();
  if (WiFi.status() == WL_CONNECTED) {
    Serial.print("[WIFI] Connected. IP: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("[WIFI] Not connected at setup.");
  }
}

void loop() {
  loopCounter++;
  Serial.println();
  Serial.println("===== LOOP START =====");
  Serial.print("[LOOP] Loop counter: ");
  Serial.println(loopCounter);

  Serial.print("[LOOP] WiFi status: ");
  Serial.println(WiFi.status() == WL_CONNECTED ? "CONNECTED" : "NOT CONNECTED");

  // 1. Read sensor
  sensorValue = analogRead(analogInPin);
  Serial.print("[SENSOR] Raw analog value: ");
  Serial.println(sensorValue);


  if (sensorValue < 250) {
    currentStatus = "NORMAL";
  } else if (sensorValue < 500) {
    currentStatus = "ALERT";
  } else {
    currentStatus = "CRITICAL";
  }

  Serial.print("[STATUS] Current status decided: ");
  Serial.println(currentStatus);

  // 3. Update indicators
  updateIndicators(currentStatus);

  // 4. Send only when status changes
  if (currentStatus != lastStatus) {
    Serial.println("[STATUS] Detected status CHANGE!");
    Serial.print("[STATUS] Last: ");
    Serial.print(lastStatus);
    Serial.print("  ->  New: ");
    Serial.println(currentStatus);

    sendStatusToServer(sensorValue, currentStatus);
    lastStatus = currentStatus;
  } else {
    Serial.println("[STATUS] No status change, not sending to server.");
  }

  Serial.print("[DEBUG] Free heap: ");
  Serial.println(ESP.getFreeHeap());
  Serial.println("===== LOOP END =====");

  delay(1000);
}

// ---------------- FUNCTIONS ----------------

void updateIndicators(const String& status) {
  Serial.print("[INDICATORS] Applying status: ");
  Serial.println(status);

  if (status == "NORMAL") {
    Serial.println("[INDICATORS] NORMAL: Green ON");
    digitalWrite(greenLedPin,  HIGH);
    digitalWrite(yellowLedPin, LOW);
    digitalWrite(redLedPin,    LOW);
    digitalWrite(buzzerPin,    LOW);
  } else if (status == "ALERT") {
    Serial.println("[INDICATORS] ALERT: Yellow ON");
    digitalWrite(greenLedPin,  LOW);
    digitalWrite(yellowLedPin, HIGH);
    digitalWrite(redLedPin,    LOW);
    digitalWrite(buzzerPin,    LOW);
  } else if (status == "CRITICAL") {
    Serial.println("[INDICATORS] CRITICAL: Red + Buzzer ON");
    digitalWrite(greenLedPin,  LOW);
    digitalWrite(yellowLedPin, LOW);
    digitalWrite(redLedPin,    HIGH);
    digitalWrite(buzzerPin,    HIGH);
  } else {
    Serial.println("[INDICATORS] UNKNOWN: All OFF");
    digitalWrite(greenLedPin,  LOW);
    digitalWrite(yellowLedPin, LOW);
    digitalWrite(redLedPin,    LOW);
    digitalWrite(buzzerPin,    LOW);
  }
}

void sendStatusToServer(int value, const String& status) {
  Serial.println("[HTTP] sendStatusToServer() start");

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[HTTP] WiFi not connected, cannot send.");
    return;
  }

  WiFiClient client;
  HTTPClient http;

  Serial.print("[HTTP] Target URL: ");
  Serial.println(serverUrl);

  if (!http.begin(client, serverUrl)) {
    Serial.println("[HTTP] ERROR: http.begin() failed");
    return;
  }

  http.addHeader("Content-Type", "application/x-www-form-urlencoded");

  String postData = "sensor_value=" + String(value) +
                    "&status=" + status;

  Serial.print("[HTTP] POST data: ");
  Serial.println(postData);

  int httpCode = http.POST(postData);

  if (httpCode > 0) {
    Serial.print("[HTTP] Response code: ");
    Serial.println(httpCode);
    String payload = http.getString();
    Serial.print("[HTTP] Server says: ");
    Serial.println(payload);
  } else {
    Serial.print("[HTTP] POST failed, code: ");
    Serial.println(httpCode);
  }

  http.end();
  Serial.println("[HTTP] sendStatusToServer() end");
}
