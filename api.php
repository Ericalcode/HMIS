<?php
/**
 * PulseCare Enterprise HMIS - Backend API
 * Handles database connection and all REST endpoints for frontend AJAX requests.
 */

// Headers for JSON and CORS support
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database configuration (reads from environment variables if set, defaults to local)
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'pulsecare_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$port = getenv('DB_PORT') ?: '3306';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\PDOException $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ==========================================
// GET REQUESTS (FETCH DATA)
// ==========================================
if ($method === 'GET') {
    if ($action === 'get_all_data') {
        try {
            $patients = $pdo->query("SELECT * FROM patients ORDER BY registered_at DESC")->fetchAll();
            $doctors = $pdo->query("SELECT * FROM doctors")->fetchAll();
            
            $appointments = $pdo->query("
                SELECT a.*, p.name as patientName, d.name as doctorName 
                FROM appointments a 
                LEFT JOIN patients p ON a.patient_id = p.id 
                LEFT JOIN doctors d ON a.doctor_id = d.id 
                ORDER BY a.app_date DESC, a.app_time DESC
            ")->fetchAll();
            
            $inventory = $pdo->query("SELECT * FROM inventory")->fetchAll();
            
            $labTests = $pdo->query("
                SELECT l.*, p.name as patientName 
                FROM lab_tests l 
                LEFT JOIN patients p ON l.patient_id = p.id 
                ORDER BY l.test_date DESC
            ")->fetchAll();
            
            $beds = $pdo->query("
                SELECT b.*, p.name as patientName 
                FROM beds b 
                LEFT JOIN patients p ON b.patient_id = p.id
            ")->fetchAll();
            
            $invoices = $pdo->query("
                SELECT i.*, p.name as patientName 
                FROM invoices i 
                LEFT JOIN patients p ON i.patient_id = p.id 
                ORDER BY i.invoice_date DESC
            ")->fetchAll();

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

// ==========================================
// POST REQUESTS (CREATE / UPDATE DATA)
// ==========================================
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // 1. Save or Update Patient
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
            
            $stmt->execute([
                $id, $name, $age, $gender, $phone, $email, 
                $blood, $insurance, $address, $allergies, $emergency, $registeredAt
            ]);

            echo json_encode(['success' => true, 'id' => $id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // 2. Save Appointment
    if ($action === 'save_appointment') {
        $id = 'APP-' . rand(500, 999);
        $patientId = $input['patientId'] ?? '';
        $doctorId = $input['doctorId'] ?? '';
        $date = $input['date'] ?? date('Y-m-d');
        $time = $input['time'] ?? '09:00';
        $reason = $input['reason'] ?? '';

        try {
            $stmt = $pdo->prepare("
                INSERT INTO appointments (id, patient_id, doctor_id, app_date, app_time, reason, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'Scheduled')
            ");
            $stmt->execute([$id, $patientId, $doctorId, $date, $time, $reason]);

            echo json_encode(['success' => true, 'id' => $id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Fallback response for unmatched routes
echo json_encode(['success' => false, 'error' => 'Invalid action or request method.']);
