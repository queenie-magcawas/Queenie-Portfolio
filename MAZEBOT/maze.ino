// Ultrasonic pins
const int trigPin1 = A1; // First ultrasonic sensor
const int echoPin1 = A0;

const int trigPin2 = A3; // Second ultrasonic sensor
const int echoPin2 = A2;

const int trigPin3 = A5; // Third ultrasonic sensor
const int echoPin3 = A4;

// Motor control pins (now with PWM speed control)
const int LeftMotorForward = 7;
const int LeftMotorBackward = 6;
const int RightMotorForward = 5;
const int RightMotorBackward = 4;
const int LeftMotorEnable = 9;  // PWM pin for left motor speed
const int RightMotorEnable = 10; // PWM pin for right motor speed

int obstacleThreshold = 20; // Distance (cm) to avoid
int motorSpeed = 150;       // Speed value (0-255, lower is slower)

void setup() {
  Serial.begin(9600);

  // Ultrasonic sensor setup
  pinMode(trigPin1, OUTPUT);
  pinMode(echoPin1, INPUT);
  pinMode(trigPin2, OUTPUT);
  pinMode(echoPin2, INPUT);
  pinMode(trigPin3, OUTPUT);
  pinMode(echoPin3, INPUT);

  // Motor control setup
  pinMode(LeftMotorForward, OUTPUT);
  pinMode(LeftMotorBackward, OUTPUT);
  pinMode(RightMotorForward, OUTPUT);
  pinMode(RightMotorBackward, OUTPUT);
  pinMode(LeftMotorEnable, OUTPUT);
  pinMode(RightMotorEnable, OUTPUT);

  moveStop();
}

void loop() {
  int distance1 = getDistance(trigPin1, echoPin1);
  int distance2 = getDistance(trigPin2, echoPin2);
  int distance3 = getDistance(trigPin3, echoPin3);

  Serial.print("Distance 1: ");
  Serial.print(distance1);
  Serial.print(" cm, Distance 2: ");
  Serial.print(distance2);
  Serial.print(" cm, Distance 3: ");
  Serial.print(distance3);
  Serial.println(" cm");

  if (distance1 > obstacleThreshold && distance2 > obstacleThreshold && distance3 > obstacleThreshold) {
    moveForward();
  } else {
    moveStop();
    delay(200);
    moveBackward();
    delay(500);
    moveRight();
    delay(600);
  }

  delay(100);
}

// Distance reading from ultrasonic sensor
int getDistance(int trigPin, int echoPin) {
  digitalWrite(trigPin, LOW);
  delayMicroseconds(2);
  digitalWrite(trigPin, HIGH);
  delayMicroseconds(10);
  digitalWrite(trigPin, LOW);

  long duration = pulseIn(echoPin, HIGH);
  int distance = duration * 0.034 / 2;
  return distance;
}

// Movement functions with speed control
void moveForward() {
  analogWrite(LeftMotorEnable, motorSpeed);
  analogWrite(RightMotorEnable, motorSpeed);
  digitalWrite(LeftMotorForward, HIGH);
  digitalWrite(LeftMotorBackward, LOW);
  digitalWrite(RightMotorForward, HIGH);
  digitalWrite(RightMotorBackward, LOW);
}

void moveBackward() {
  analogWrite(LeftMotorEnable, motorSpeed);
  analogWrite(RightMotorEnable, motorSpeed);
  digitalWrite(LeftMotorForward, LOW);
  digitalWrite(LeftMotorBackward, HIGH);
  digitalWrite(RightMotorForward, LOW);
  digitalWrite(RightMotorBackward, HIGH);
}

void moveRight() {
  analogWrite(LeftMotorEnable, motorSpeed);
  analogWrite(RightMotorEnable, motorSpeed);
  digitalWrite(LeftMotorForward, HIGH);
  digitalWrite(LeftMotorBackward, LOW);
  digitalWrite(RightMotorForward, LOW);
  digitalWrite(RightMotorBackward, HIGH);
}

void moveStop() {
  analogWrite(LeftMotorEnable, 0);
  analogWrite(RightMotorEnable, 0);
  digitalWrite(LeftMotorForward, LOW);
  digitalWrite(LeftMotorBackward, LOW);
  digitalWrite(RightMotorForward, LOW);
  digitalWrite(RightMotorBackward, LOW);
}