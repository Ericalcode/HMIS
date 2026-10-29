<?php
/**
 * PulseCare Enterprise HMIS - Backend API with TiDB SSL Support
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Environment variables from Render
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'pulsecare_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$port = getenv('DB_PORT') ?: '4000';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    
    // Options array including SSL settings for cloud databases like TiDB
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false // Bypasses self-signed certificate validation errors on cloud hosts
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

} catch (\PDOException $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// --- GET REQUESTS ---
if ($method === 'GET') {
    if ($action === 'get_all_data') {
        try {
            $patients = $pdo->query("SELECT * FROM patients ORDER BY registered_at DESC")->fetchAll();
            $doctors = $pdo->query("SELECT * FROM doctors")->fetchAll();
            $appointments = $pdo->query("SELECT a.*, p.name as patientName, d.name as doctorName FROM appointments a LEFT JOIN patients p ON a.patient_id = p.id LEFT JOIN doctors d ON a.doctor_id = d.id ORDER BY a.app_date DESC")->fetchAll();
            $inventory = $pdo->query("SELECT * FROM inventory")->fetchAll();
            $labTests = $pdo->query("SELECT l.*, p.name as patientName FROM lab_tests l LEFT JOIN patients p ON l.patient_id = p.id")->fetchAll();
            $beds = $pdo->query("SELECT b.*, p.name as patientName FROM beds b LEFT JOIN patients p ON b.patient_id = p.id")->fetchAll();
            $invoices = $pdo->query("SELECT i.*, p.name as patientName FROM invoices i LEFT JOIN patients p ON i.patient_id = p.id")->fetchAll();

            echo json_encode([
                'success' => true,
                'data' => compact('patients', 'doctors', 'appointments', 'inventory', 'labTests', 'beds', 'invoices')
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// --- POST REQUESTS ---
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'save_patient') {
        $id = !empty($input['id']) ? $input['id'] : 'PAT-' . rand(1000, 9999);
        $name = $input['name'] ?? '';
        $age = $input['age'] ?? 0;
        $gender = $input['gender'] ?? 'Other';
        $phone = $input['phone'] ?? '';
        $email = $input['email'] ?? '';
        $blood = $input['blood'] ?? 'O+';
        $insurance = $input['insurance'] ?? 'Self-Pay';
        $address = $input['address'] ?? '';
        $allergies = $input['allergies'] ?? 'None';
        $emergency = $input['emergency'] ?? '';
        $registeredAt = date('Y-m-d');

        try {
            $stmt = $pdo->prepare("
                INSERT INTO patients (id, name, age, gender, phone, email, blood, insurance, address, allergies, emergency, registered_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    name=VALUES(name), age=VALUES(age), gender=VALUES(gender), 
                    phone=VALUES(phone), email=VALUES(email), blood=VALUES(blood), 
                    insurance=VALUES(insurance), address=VALUES(address), 
                    allergies=VALUES(allergies), emergency=VALUES(emergency)
            ");
            
            $stmt->execute([$id, $name, $age, $gender, $phone, $email, $blood, $insurance, $address, $allergies, $emergency, $registeredAt]);
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action or request method.']);
