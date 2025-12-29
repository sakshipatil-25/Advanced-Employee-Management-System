<?php
// Database Configuration
$host = 'localhost';
$dbname = 'teco_energy_ems';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Create database and tables if they don't exist
$createDB = "
CREATE DATABASE IF NOT EXISTS $dbname;
USE $dbname;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'employee') DEFAULT 'employee',
    department VARCHAR(50),
    position VARCHAR(100),
    base_salary DECIMAL(10,2) DEFAULT 0,
    performance_rating INT DEFAULT 3,
    join_date DATE DEFAULT CURDATE(),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'half_day') DEFAULT 'present',
    time_in TIME,
    time_out TIME,
    marked_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (marked_by) REFERENCES users(id),
    UNIQUE KEY unique_user_date (user_id, date)
);

CREATE TABLE IF NOT EXISTS performance_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comments TEXT,
    review_date DATE DEFAULT CURDATE(),
    reviewed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS monthly_salaries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    month INT NOT NULL,
    year INT NOT NULL,
    base_salary DECIMAL(10,2),
    performance_rating INT,
    working_days INT DEFAULT 22,
    present_days INT DEFAULT 0,
    half_days INT DEFAULT 0,
    absent_days INT DEFAULT 0,
    attendance_percentage DECIMAL(5,2),
    performance_bonus DECIMAL(10,2) DEFAULT 0,
    attendance_deduction DECIMAL(10,2) DEFAULT 0,
    final_salary DECIMAL(10,2),
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user_month_year (user_id, month, year)
);

-- Insert default admin user
INSERT IGNORE INTO users (first_name, last_name, email, password, role, department, position, base_salary, performance_rating) 
VALUES ('Admin', 'User', 'admin@tecoenergy.com', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin', 'IT', 'System Administrator', 95000, 5);

-- Insert demo employees
INSERT IGNORE INTO users (first_name, last_name, email, password, role, department, position, base_salary, performance_rating) 
VALUES 
('Vivek', 'Patil', 'vp@tecoenergy.com', '" . password_hash('emp123', PASSWORD_DEFAULT) . "', 'employee', 'Engineering', 'Software Engineer', 75000, 4),
('Abhijit', 'Deshmukh', 'amd@tecoenergy.com', '" . password_hash('emp123', PASSWORD_DEFAULT) . "', 'employee', 'HR', 'HR Manager', 85000, 5),
('Pratik', 'Patil', 'pp@tecoenergy.com', '" . password_hash('emp123', PASSWORD_DEFAULT) . "', 'employee', 'Operations', 'Operations Manager', 80000, 4),
('Srushti', 'Deshmukh', 'sd@tecoenergy.com', '" . password_hash('emp123', PASSWORD_DEFAULT) . "', 'employee', 'Finance', 'Financial Analyst', 70000, 3);
";

// Session management
session_start();

// Handle AJAX requests
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'login':
            handleLogin($pdo);
            break;
        case 'register':
            handleRegister($pdo);
            break;
        case 'get_dashboard_data':
            getDashboardData($pdo);
            break;
        case 'get_employees':
            getEmployees($pdo);
            break;
        case 'save_employee':
            saveEmployee($pdo);
            break;
        case 'delete_employee':
            deleteEmployee($pdo);
            break;
        case 'mark_attendance_admin':
            markAttendanceAdmin($pdo);
            break;
        case 'get_monthly_salary':
            getMonthlySalary($pdo);
            break;
        case 'calculate_monthly_salary':
            calculateMonthlySalary($pdo);
            break;
        case 'get_attendance':
            getAttendance($pdo);
            break;
        case 'update_performance':
            updatePerformance($pdo);
            break;
        case 'get_salary_data':
            getSalaryData($pdo);
            break;
        case 'update_salary':
            updateSalary($pdo);
            break;
    }
    exit;
}

// PHP Functions
function handleLogin($pdo) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = TRUE");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
}

function handleRegister($pdo) {
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $department = $_POST['department'];
    $position = $_POST['position'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, department, position) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$firstName, $lastName, $email, $password, $department, $position]);
        echo json_encode(['success' => true, 'message' => 'Registration successful']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
    }
}

function getDashboardData($pdo) {
    // Get total employees
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = TRUE AND role = 'employee'");
    $totalEmployees = $stmt->fetch()['total'];
    
    // Get present today
    $stmt = $pdo->query("SELECT COUNT(*) as present FROM attendance WHERE date = CURDATE() AND status = 'present'");
    $presentToday = $stmt->fetch()['present'];
    
    // Get average performance
    $stmt = $pdo->query("SELECT AVG(performance_rating) as avg_perf FROM users WHERE is_active = TRUE AND role = 'employee'");
    $avgPerformance = round($stmt->fetch()['avg_perf'], 1);
    
    // Get total payroll
    $stmt = $pdo->query("SELECT SUM(base_salary + (base_salary * performance_rating * 0.1)) as total_payroll FROM users WHERE is_active = TRUE AND role = 'employee'");
    $totalPayroll = $stmt->fetch()['total_payroll'];
    
    echo json_encode([
        'totalEmployees' => $totalEmployees,
        'presentToday' => $presentToday,
        'avgPerformance' => $avgPerformance,
        'totalPayroll' => number_format($totalPayroll, 0)
    ]);
}

function getEmployees($pdo) {
    $stmt = $pdo->query("SELECT * FROM users WHERE is_active = TRUE ORDER BY id DESC");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($employees);
}

function saveEmployee($pdo) {
    $id = $_POST['id'] ?? null;
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $email = $_POST['email'];
    $department = $_POST['department'];
    $position = $_POST['position'];
    $baseSalary = $_POST['base_salary'];
    $performance = $_POST['performance'];
    
    try {
        if ($id) {
            // Update existing employee
            $stmt = $pdo->prepare("UPDATE users SET first_name=?, last_name=?, email=?, department=?, position=?, base_salary=?, performance_rating=? WHERE id=?");
            $stmt->execute([$firstName, $lastName, $email, $department, $position, $baseSalary, $performance, $id]);
        } else {
            // Create new employee
            $password = password_hash('emp123', PASSWORD_DEFAULT); // Default password
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, department, position, base_salary, performance_rating) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$firstName, $lastName, $email, $password, $department, $position, $baseSalary, $performance]);
        }
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function deleteEmployee($pdo) {
    $id = $_POST['id'];
    $stmt = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}

function markAttendanceAdmin($pdo) {
    $userId = $_POST['user_id'];
    $date = $_POST['date'];
    $status = $_POST['status'];
    $timeIn = $_POST['time_in'] ?? null;
    $timeOut = $_POST['time_out'] ?? null;
    $markedBy = $_SESSION['user_id'];
    
    try {
        // Check if attendance already exists for this user and date
        $stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
        $stmt->execute([$userId, $date]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing attendance
            $stmt = $pdo->prepare("UPDATE attendance SET status = ?, time_in = ?, time_out = ?, marked_by = ? WHERE user_id = ? AND date = ?");
            $stmt->execute([$status, $timeIn, $timeOut, $markedBy, $userId, $date]);
        } else {
            // Insert new attendance record
            $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, status, time_in, time_out, marked_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $date, $status, $timeIn, $timeOut, $markedBy]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Attendance marked successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getMonthlySalary($pdo) {
    $month = $_POST['month'] ?? date('n');
    $year = $_POST['year'] ?? date('Y');
    
    $stmt = $pdo->query("
        SELECT ms.*, u.first_name, u.last_name, u.department, u.position
        FROM monthly_salaries ms 
        JOIN users u ON ms.user_id = u.id 
        WHERE ms.month = $month AND ms.year = $year
        ORDER BY u.first_name, u.last_name
    ");
    $salaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($salaries);
}

function calculateMonthlySalary($pdo) {
    $month = $_POST['month'] ?? date('n');
    $year = $_POST['year'] ?? date('Y');
    $workingDays = $_POST['working_days'] ?? 22;
    
    // Get all active employees
    $stmt = $pdo->prepare("SELECT * FROM users WHERE is_active = TRUE AND role = 'employee'");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($employees as $employee) {
        $userId = $employee['id'];
        $baseSalary = $employee['base_salary'];
        $performanceRating = $employee['performance_rating'];
        
        // Get attendance data for the month
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_days,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days
            FROM attendance 
            WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
        ");
        $stmt->execute([$userId, $month, $year]);
        $attendance = $stmt->fetch();
        
        $presentDays = $attendance['present_days'];
        $halfDays = $attendance['half_days'];
        $absentDays = $attendance['absent_days'];
        
        // Calculate attendance percentage (half day = 0.5 day)
        $effectiveDays = $presentDays + ($halfDays * 0.5);
        $attendancePercentage = ($effectiveDays / $workingDays) * 100;
        
        // Performance bonus calculation (5% to 25% based on rating)
        $performanceBonus = $baseSalary * ($performanceRating * 0.05);
        
        // Attendance deduction (10% deduction for each day below 80% attendance)
        $attendanceDeduction = 0;
        if ($attendancePercentage < 80) {
            $deductionRate = (80 - $attendancePercentage) / 100;
            $attendanceDeduction = $baseSalary * $deductionRate;
        }
        
        // Calculate final salary
        $finalSalary = $baseSalary + $performanceBonus - $attendanceDeduction;
        $finalSalary = max($finalSalary, 0); // Ensure salary is not negative
        
        // Insert or update monthly salary record
        $stmt = $pdo->prepare("
            INSERT INTO monthly_salaries 
            (user_id, month, year, base_salary, performance_rating, working_days, present_days, half_days, absent_days, attendance_percentage, performance_bonus, attendance_deduction, final_salary)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            base_salary = VALUES(base_salary),
            performance_rating = VALUES(performance_rating),
            working_days = VALUES(working_days),
            present_days = VALUES(present_days),
            half_days = VALUES(half_days),
            absent_days = VALUES(absent_days),
            attendance_percentage = VALUES(attendance_percentage),
            performance_bonus = VALUES(performance_bonus),
            attendance_deduction = VALUES(attendance_deduction),
            final_salary = VALUES(final_salary)
        ");
        
        $stmt->execute([
            $userId, $month, $year, $baseSalary, $performanceRating, $workingDays,
            $presentDays, $halfDays, $absentDays, $attendancePercentage,
            $performanceBonus, $attendanceDeduction, $finalSalary
        ]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Monthly salaries calculated successfully']);
}

function getAttendance($pdo) {
    $stmt = $pdo->query("
        SELECT a.*, u.first_name, u.last_name, admin.first_name as marked_by_name
        FROM attendance a 
        JOIN users u ON a.user_id = u.id 
        LEFT JOIN users admin ON a.marked_by = admin.id
        ORDER BY a.date DESC, a.time_in DESC 
        LIMIT 50
    ");
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($attendance);
}

function updatePerformance($pdo) {
    $userId = $_POST['user_id'];
    $rating = $_POST['rating'];
    $comments = $_POST['comments'];
    $reviewedBy = $_SESSION['user_id'];
    
    // Update user's performance rating
    $stmt = $pdo->prepare("UPDATE users SET performance_rating = ? WHERE id = ?");
    $stmt->execute([$rating, $userId]);
    
    // Insert performance review record
    $stmt = $pdo->prepare("INSERT INTO performance_reviews (user_id, rating, comments, reviewed_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $rating, $comments, $reviewedBy]);
    
    echo json_encode(['success' => true]);
}

function getSalaryData($pdo) {
    $month = $_GET['month'] ?? date('n');
    $year = $_GET['year'] ?? date('Y');
    
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.base_salary, u.performance_rating,
        COALESCE(ms.present_days, 0) as present_days,
        COALESCE(ms.half_days, 0) as half_days,
        COALESCE(ms.absent_days, 0) as absent_days,
        COALESCE(ms.attendance_percentage, 0) as attendance_percentage,
        COALESCE(ms.performance_bonus, 0) as performance_bonus,
        COALESCE(ms.attendance_deduction, 0) as attendance_deduction,
        COALESCE(ms.final_salary, u.base_salary) as final_salary,
        ms.working_days
        FROM users u 
        LEFT JOIN monthly_salaries ms ON u.id = ms.user_id AND ms.month = ? AND ms.year = ?
        WHERE u.is_active = TRUE AND u.role = 'employee'
    ");
    $stmt->execute([$month, $year]);
    $salaryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($salaryData);
}

function updateSalary($pdo) {
    $userId = $_POST['user_id'];
    $baseSalary = $_POST['base_salary'];
    $createdBy = $_SESSION['user_id'];
    
    // Get performance rating for bonus calculation
    $stmt = $pdo->prepare("SELECT performance_rating FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $performance = $stmt->fetch()['performance_rating'];
    
    $bonus = $baseSalary * $performance * 0.1;
    $totalSalary = $baseSalary + $bonus;
    
    // Update base salary
    $stmt = $pdo->prepare("UPDATE users SET base_salary = ? WHERE id = ?");
    $stmt->execute([$baseSalary, $userId]);
    
    // Insert salary adjustment record
    $stmt = $pdo->prepare("INSERT INTO salary_adjustments (user_id, base_salary, performance_bonus, total_salary, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $baseSalary, $bonus, $totalSalary, $createdBy]);
    
    echo json_encode(['success' => true]);
}

// Initialize database on first run
try {
    $pdo->exec($createDB);
} catch (PDOException $e) {
    // Database might already exist
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teco Energy - Employee Management System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%);
        }
        .teco-primary { color: #1e40af; }
        .teco-bg-primary { background-color: #1e40af; }
        .performance-excellent { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .performance-good { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
        .performance-average { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .performance-poor { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
        .animated-counter {
            animation: countUp 1s ease-out;
        }
        @keyframes countUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
        }
        .hidden { display: none !important; }
        
        @media (max-width: 768px) {
            .sidebar {
                position: static !important;
                width: 100% !important;
                min-height: auto;
            }
            .main-content {
                margin-left: 0 !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50">

<!-- Login Page -->
<div id="loginPage" class="min-h-screen gradient-bg flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <div class="mx-auto h-20 w-20 bg-white rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-bolt text-3xl text-blue-600"></i>
            </div>
            <h2 class="text-4xl font-bold text-white">Teco Energy</h2>
            <p class="mt-2 text-xl text-blue-100">Employee Management System</p>
        </div>
        
        <div class="bg-white rounded-lg shadow-xl p-8">
            <div class="mb-6">
                <div class="flex border-b">
                    <button id="loginTab" class="flex-1 py-2 px-4 text-center border-b-2 border-blue-500 text-blue-600 font-medium">Login</button>
                    <button id="registerTab" class="flex-1 py-2 px-4 text-center text-gray-500 font-medium">Register</button>
                </div>
            </div>
            
            <!-- Login Form -->
            <form id="loginForm" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" id="loginEmail" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="admin@tecoenergy.com" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" id="loginPassword" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="admin123" required>
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>
            
            <!-- Register Form -->
            <form id="registerForm" class="space-y-6 hidden">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                        <input type="text" id="regFirstName" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                        <input type="text" id="regLastName" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" id="regEmail" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" id="regPassword" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                    <select id="regDepartment" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="Engineering">Engineering</option>
                        <option value="Operations">Operations</option>
                        <option value="Finance">Finance</option>
                        <option value="HR">Human Resources</option>
                        <option value="IT">Information Technology</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Position</label>
                    <input type="text" id="regPosition" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                </div>
                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md transition duration-300">
                    <i class="fas fa-user-plus me-2"></i>Register
                </button>
            </form>
            
            <div class="mt-4 text-center text-sm text-gray-600">
               
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Page -->
<div id="dashboardPage" class="hidden">
    <!-- Sidebar -->
    <div class="sidebar position-fixed top-0 left-0 h-100 text-white d-none d-md-block" style="width: 250px; z-index: 1000;">
        <div class="p-4">
            <div class="d-flex align-items-center mb-4">
                <i class="fas fa-bolt text-2xl me-2"></i>
                <h4 class="mb-0">Teco Energy</h4>
            </div>
            
            <nav class="nav flex-column">
                <a href="#" class="nav-link text-white mb-2 dashboard-nav active" data-section="overview">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
                <a href="#" class="nav-link text-white mb-2 dashboard-nav admin-only" data-section="employees">
                    <i class="fas fa-users me-2"></i> Employees
                </a>
                <a href="#" class="nav-link text-white mb-2 dashboard-nav admin-only" data-section="attendance">
                    <i class="fas fa-calendar-check me-2"></i> Attendance Management
                </a>
                <a href="#" class="nav-link text-white mb-2 dashboard-nav employee-only" data-section="my-attendance">
                    <i class="fas fa-calendar-check me-2"></i> My Attendance
                </a>
                <a href="#" class="nav-link text-white mb-2 dashboard-nav admin-only" data-section="salary">
                    <i class="fas fa-dollar-sign me-2"></i> Salary Management
                </a>
                <a href="#" class="nav-link text-white mb-2 dashboard-nav admin-only" data-section="performance">
                    <i class="fas fa-chart-line me-2"></i> Performance
                </a>
                <a href="#" class="nav-link text-white mb-2 dashboard-nav" data-section="profile">
                    <i class="fas fa-user me-2"></i> Profile
                </a>
            </nav>
        </div>
        
        <div class="position-absolute bottom-0 w-100 p-4">
            <button id="logoutBtn" class="btn btn-outline-light w-100">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </button>
        </div>
    </div>
    
    <!-- Mobile Navigation -->
    <nav class="navbar navbar-dark sidebar d-md-none">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-bolt me-2"></i>Teco Energy
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mobileNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mobileNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link dashboard-nav active" data-section="overview">Dashboard</a>
                    </li>
                    <li class="nav-item admin-only">
                        <a class="nav-link dashboard-nav" data-section="employees">Employees</a>
                    </li>
                    <li class="nav-item admin-only">
                        <a class="nav-link dashboard-nav" data-section="attendance">Attendance Management</a>
                    </li>
                    <li class="nav-item employee-only">
                        <a class="nav-link dashboard-nav" data-section="my-attendance">My Attendance</a>
                    </li>
                    <li class="nav-item admin-only">
                        <a class="nav-link dashboard-nav" data-section="salary">Salary</a>
                    </li>
                    <li class="nav-item admin-only">
                        <a class="nav-link dashboard-nav" data-section="performance">Performance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link dashboard-nav" data-section="profile">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="mobileLogout">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content" style="margin-left: 0;">
        <div class="d-none d-md-block" style="margin-left: 250px;">
            <!-- Header -->
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
                <div class="container-fluid px-4">
                    <h5 class="navbar-brand mb-0 teco-primary">Welcome, <span id="userWelcome"></span></h5>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-primary me-2" id="userRole"></span>
                        <span class="text-muted" id="currentDate"></span>
                    </div>
                </div>
            </nav>
            
            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Dashboard Overview -->
                <div id="overviewSection" class="dashboard-section p-4">
                    <div class="row">
                        <!-- Stats Cards -->
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="card card-hover h-100 border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="performance-excellent text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                        <i class="fas fa-users text-2xl"></i>
                                    </div>
                                    <h3 class="animated-counter mb-1" id="totalEmployees">0</h3>
                                    <p class="text-muted mb-0">Total Employees</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="card card-hover h-100 border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="performance-good text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                        <i class="fas fa-calendar-check text-2xl"></i>
                                    </div>
                                    <h3 class="animated-counter mb-1" id="presentToday">0</h3>
                                    <p class="text-muted mb-0">Present Today</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="card card-hover h-100 border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="performance-average text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                        <i class="fas fa-star text-2xl"></i>
                                    </div>
                                    <h3 class="animated-counter mb-1" id="avgPerformance">0</h3>
                                    <p class="text-muted mb-0">Avg Performance</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-3 col-md-6 mb-4">
                            <div class="card card-hover h-100 border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="performance-poor text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                                        <i class="fas fa-rupee-sign text-2xl"></i>
                                    </div>
                                    <h3 class="animated-counter mb-1" id="totalSalary">Rs.0</h3>
                                    <p class="text-muted mb-0">Total Payroll</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    
                </div>
                
                <!-- Employees Section -->
                <div id="employeesSection" class="dashboard-section hidden p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Employee Management</h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#employeeModal" onclick="openEmployeeModal()">
                            <i class="fas fa-plus me-2"></i>Add Employee
                        </button>
                    </div>
                    
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="employeesTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Department</th>
                                            <th>Position</th>
                                            <th>Salary</th>
                                            <th>Performance</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="employeesTableBody">
                                        <!-- Employee data will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Section (Admin Only) -->
                <div id="attendanceSection" class="dashboard-section hidden p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Attendance Management</h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#attendanceModal">
                            <i class="fas fa-plus me-2"></i>Mark Attendance
                        </button>
                    </div>
                    
                    <!-- Monthly Salary Calculation -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card shadow-sm">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Monthly Salary Calculation</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">Month</label>
                                            <select class="form-control" id="salaryMonth">
                                                <option value="1">January</option>
                                                <option value="2">February</option>
                                                <option value="3">March</option>
                                                <option value="4">April</option>
                                                <option value="5">May</option>
                                                <option value="6">June</option>
                                                <option value="7">July</option>
                                                <option value="8">August</option>
                                                <option value="9">September</option>
                                                <option value="10">October</option>
                                                <option value="11">November</option>
                                                <option value="12">December</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Year</label>
                                            <input type="number" class="form-control" id="salaryYear" value="2024" min="2020" max="2030">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Working Days</label>
                                            <input type="number" class="form-control" id="workingDays" value="22" min="20" max="31">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">&nbsp;</label>
                                            <button class="btn btn-success d-block w-100" onclick="ems.calculateMonthlySalary()">
                                                <i class="fas fa-calculator me-2"></i>Calculate Salaries
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance History -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Attendance Records</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="attendanceTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Date</th>
                                            <th>Employee</th>
                                            <th>Status</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Marked By</th>
                                        </tr>
                                    </thead>
                                    <tbody id="attendanceTableBody">
                                        <!-- Attendance data will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- My Attendance Section (Employee Only) -->
                <div id="my-attendanceSection" class="dashboard-section hidden p-4">
                    <h4>My Attendance</h4>
                    
                    <!-- Attendance Summary -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="card shadow-sm">
                                <div class="card-body">
                                    <h6>Current Month Summary</h6>
                                    <div id="myAttendanceSummary">
                                        <!-- Summary will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- My Attendance History -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">My Attendance History</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="myAttendanceTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                        </tr>
                                    </thead>
                                    <tbody id="myAttendanceTableBody">
                                        <!-- My attendance data will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Salary Section -->
                <div id="salarySection" class="dashboard-section hidden p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>Monthly Salary Report</h4>
                        <div>
                            <select class="form-control d-inline-block me-2" id="viewSalaryMonth" style="width: 120px;" onchange="ems.loadSalaryData()">
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                            <input type="number" class="form-control d-inline-block" id="viewSalaryYear" value="2024" min="2020" max="2030" style="width: 100px;" onchange="ems.loadSalaryData()">
                        </div>
                    </div>
                    
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="salaryTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Base Salary</th>
                                            <th>Perf.</th>
                                            <th>Work Days</th>
                                            <th>Attendance (P/HD/A)</th>
                                            <th>%</th>
                                            <th>Bonus</th>
                                            <th>Deduction</th>
                                            <th>Final Salary</th>
                                        </tr>
                                    </thead>
                                    <tbody id="salaryTableBody">
                                        <!-- Salary data will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Performance Section -->
                <div id="performanceSection" class="dashboard-section hidden p-4">
                    <h4>Performance Management</h4>
                    
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="performanceTable">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Employee</th>
                                            <th>Current Rating</th>
                                            <th>Department</th>
                                            <th>Last Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="performanceTableBody">
                                        <!-- Performance data will be populated here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Section -->
                <div id="profileSection" class="dashboard-section hidden p-4">
                    <h4>My Profile</h4>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card shadow-sm">
                                <div class="card-body text-center">
                                    <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3 text-white" style="width: 80px; height: 80px;">
                                        <i class="fas fa-user text-3xl"></i>
                                    </div>
                                    <h5 id="profileName"></h5>
                                    <p class="text-muted" id="profileRole"></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="card shadow-sm">
                                <div class="card-body">
                                    <h6>Profile Information</h6>
                                    <div id="profileInfo">
                                        <!-- Profile info will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mobile Content -->
        <div class="d-md-none p-3" style="padding-top: 70px;">
            <!-- Mobile Dashboard sections will be shown here -->
            <div id="mobileContent">
                <!-- Content will be dynamically loaded -->
            </div>
        </div>
    </div>
</div>

<!-- Employee Modal -->
<div class="modal fade" id="employeeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="employeeModalTitle">Add New Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="employeeForm">
                    <input type="hidden" id="employeeId">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" id="empFirstName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="empLastName" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="empEmail" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-control" id="empDepartment" required>
                                <option value="Engineering">Engineering</option>
                                <option value="Operations">Operations</option>
                                <option value="Finance">Finance</option>
                                <option value="HR">Human Resources</option>
                                <option value="IT">Information Technology</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" class="form-control" id="empPosition" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Base Salary</label>
                            <input type="number" class="form-control" id="empSalary" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Performance Rating</label>
                            <select class="form-control" id="empPerformance" required>
                                <option value="5">Excellent (5)</option>
                                <option value="4">Good (4)</option>
                                <option value="3">Average (3)</option>
                                <option value="2">Below Average (2)</option>
                                <option value="1">Poor (1)</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveEmployeeBtn">Save Employee</button>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Modal (Admin Only) -->
<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark Employee Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="attendanceForm">
                    <div class="mb-3">
                        <label class="form-label">Employee</label>
                        <select class="form-control" id="attendanceEmployee" required>
                            <option value="">Select Employee</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" id="attendanceDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-control" id="attendanceStatus" required>
                            <option value="present">Present</option>
                            <option value="half_day">Half Day</option>
                            <option value="absent">Absent</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Time In</label>
                            <input type="time" class="form-control" id="attendanceTimeIn">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Time Out</label>
                            <input type="time" class="form-control" id="attendanceTimeOut">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveAttendanceBtn">Mark Attendance</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="performanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Performance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="performanceForm">
                    <input type="hidden" id="perfEmployeeId">
                    <div class="mb-3">
                        <label class="form-label">Employee Name</label>
                        <input type="text" class="form-control" id="perfEmployeeName" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Performance Rating</label>
                        <select class="form-control" id="perfRating" required>
                            <option value="5">Excellent (5)</option>
                            <option value="4">Good (4)</option>
                            <option value="3">Average (3)</option>
                            <option value="2">Below Average (2)</option>
                            <option value="1">Poor (1)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Comments</label>
                        <textarea class="form-control" id="perfComments" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="savePerformanceBtn">Update Performance</button>
            </div>
        </div>
    </div>
</div>

<!-- Salary Modal -->
<div class="modal fade" id="salaryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Salary</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="salaryForm">
                    <input type="hidden" id="salaryEmployeeId">
                    <div class="mb-3">
                        <label class="form-label">Employee Name</label>
                        <input type="text" class="form-control" id="salaryEmployeeName" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Base Salary</label>
                        <input type="number" class="form-control" id="newBaseSalary" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveSalaryBtn">Update Salary</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>

<script>
// Employee Management System JavaScript
class EmployeeManagementSystem {
    constructor() {
        this.currentUser = null;
        this.currentUserRole = null;
        this.bindEvents();
        this.updateDateTime();
        this.checkSession();
    }

    bindEvents() {
        // Tab switching
        document.getElementById('loginTab').addEventListener('click', () => this.showLoginForm());
        document.getElementById('registerTab').addEventListener('click', () => this.showRegisterForm());
        
        // Form submissions
        document.getElementById('loginForm').addEventListener('submit', (e) => this.handleLogin(e));
        document.getElementById('registerForm').addEventListener('submit', (e) => this.handleRegister(e));
        
        // Dashboard navigation
        document.querySelectorAll('.dashboard-nav').forEach(nav => {
            nav.addEventListener('click', (e) => {
                e.preventDefault();
                this.showSection(nav.dataset.section);
            });
        });
        
        // Logout
        document.getElementById('logoutBtn').addEventListener('click', () => this.logout());
        document.getElementById('mobileLogout').addEventListener('click', () => this.logout());
        
        // Employee management
        document.getElementById('saveEmployeeBtn').addEventListener('click', () => this.saveEmployee());
        document.getElementById('savePerformanceBtn').addEventListener('click', () => this.savePerformance());
        document.getElementById('saveAttendanceBtn').addEventListener('click', () => this.saveAttendance());
        
        // Set default values
        document.getElementById('salaryMonth').value = new Date().getMonth() + 1;
        document.getElementById('viewSalaryMonth').value = new Date().getMonth() + 1;
    }

    checkSession() {
        // Check if user is already logged in (PHP session)
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=check_session'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showDashboard(data.user);
            }
        })
        .catch(() => {
            // Session not found, show login
        });
    }

    showLoginForm() {
        document.getElementById('loginForm').classList.remove('hidden');
        document.getElementById('registerForm').classList.add('hidden');
        document.getElementById('loginTab').className = 'flex-1 py-2 px-4 text-center border-b-2 border-blue-500 text-blue-600 font-medium';
        document.getElementById('registerTab').className = 'flex-1 py-2 px-4 text-center text-gray-500 font-medium';
    }

    showRegisterForm() {
        document.getElementById('loginForm').classList.add('hidden');
        document.getElementById('registerForm').classList.remove('hidden');
        document.getElementById('registerTab').className = 'flex-1 py-2 px-4 text-center border-b-2 border-blue-500 text-blue-600 font-medium';
        document.getElementById('loginTab').className = 'flex-1 py-2 px-4 text-center text-gray-500 font-medium';
    }

    async handleLogin(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('action', 'login');
        formData.append('email', document.getElementById('loginEmail').value);
        formData.append('password', document.getElementById('loginPassword').value);
        
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();
            
            if (data.success) {
                this.showDashboard(data.user);
                this.showAlert('Login successful!', 'success');
            } else {
                this.showAlert(data.message, 'error');
            }
        } catch (error) {
            this.showAlert('Login failed. Please try again.', 'error');
        }
    }

    async handleRegister(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('action', 'register');
        formData.append('first_name', document.getElementById('regFirstName').value);
        formData.append('last_name', document.getElementById('regLastName').value);
        formData.append('email', document.getElementById('regEmail').value);
        formData.append('password', document.getElementById('regPassword').value);
        formData.append('department', document.getElementById('regDepartment').value);
        formData.append('position', document.getElementById('regPosition').value);
        
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();
            
            if (data.success) {
                this.showAlert('Registration successful! Please login.', 'success');
                this.showLoginForm();
                document.getElementById('registerForm').reset();
            } else {
                this.showAlert(data.message, 'error');
            }
        } catch (error) {
            this.showAlert('Registration failed. Please try again.', 'error');
        }
    }

    showDashboard(user) {
        this.currentUser = user;
        this.currentUserRole = user.role;
        
        document.getElementById('loginPage').classList.add('hidden');
        document.getElementById('dashboardPage').classList.remove('hidden');
        
        document.getElementById('userWelcome').textContent = user.first_name + ' ' + user.last_name;
        document.getElementById('userRole').textContent = user.role.toUpperCase();
        
        // Show/hide admin-only and employee-only elements
        document.querySelectorAll('.admin-only').forEach(el => {
            if (user.role === 'admin') {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });
        
        document.querySelectorAll('.employee-only').forEach(el => {
            if (user.role === 'employee') {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        });
        
        this.showSection('overview');
        this.loadDashboardData();
    }

    async loadDashboardData() {
        const formData = new FormData();
        formData.append('action', 'get_dashboard_data');
        
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();
            
            document.getElementById('totalEmployees').textContent = data.totalEmployees;
            document.getElementById('presentToday').textContent = data.presentToday;
            document.getElementById('avgPerformance').textContent = data.avgPerformance;
            document.getElementById('totalSalary').textContent =  data.totalPayroll;
            
        } catch (error) {
            console.error('Error loading dashboard data:', error);
        }
    }

    showSection(section) {
        // Hide all sections
        document.querySelectorAll('.dashboard-section').forEach(s => s.classList.add('hidden'));
        
        // Show selected section
        document.getElementById(section + 'Section').classList.remove('hidden');
        
        // Update navigation active state
        document.querySelectorAll('.dashboard-nav').forEach(nav => {
            nav.classList.remove('active');
            if (nav.dataset.section === section) {
                nav.classList.add('active');
            }
        });
        
        // Load section data
        this.loadSectionData(section);
    }

    async loadSectionData(section) {
        switch (section) {
            case 'employees':
                await this.loadEmployees();
                break;
            case 'attendance':
                await this.loadAttendanceEmployees();
                await this.loadAttendance();
                break;
            case 'my-attendance':
                await this.loadMyAttendance();
                break;
            case 'salary':
                await this.loadSalaryData();
                break;
            case 'performance':
                await this.loadPerformanceData();
                break;
            case 'profile':
                this.loadProfile();
                break;
        }
    }

    async loadEmployees() {
        const formData = new FormData();
        formData.append('action', 'get_employees');
        
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const employees = await response.json();
            
            const tbody = document.getElementById('employeesTableBody');
            tbody.innerHTML = '';
            
            employees.forEach(emp => {
                const performanceColor = this.getPerformanceColor(emp.performance_rating);
                tbody.innerHTML += `
                    <tr>
                        <td>${emp.id}</td>
                        <td>${emp.first_name} ${emp.last_name}</td>
                        <td>${emp.email}</td>
                        <td>${emp.department}</td>
                        <td>${emp.position}</td>
                        <td>${Number(emp.base_salary).toLocaleString()}</td>
                        <td><span class="badge ${performanceColor}">${emp.performance_rating}/5</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="ems.editEmployee(${emp.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="ems.deleteEmployee(${emp.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        } catch (error) {
            console.error('Error loading employees:', error);
        }
    }

    async loadAttendanceEmployees() {
        const formData = new FormData();
        formData.append('action', 'get_employees');
        
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const employees = await response.json();
            
            const select = document.getElementById('attendanceEmployee');
            select.innerHTML = '<option value="">Select Employee</option>';
            
            employees.filter(emp => emp.role === 'employee').forEach(emp => {
                select.innerHTML += `<option value="${emp.id}">${emp.first_name} ${emp.last_name}</option>`;
            });
            
            // Set today's date as default
            document.getElementById('attendanceDate').value = new Date().toISOString().split('T')[0];
        } catch (error) {
            console.error('Error loading employees:', error);
        }
    }

    async loadAttendance() {
        const formData = new FormData();
        formData.append('action', 'get_attendance');
        
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const attendance = await response.json();
            
            const tbody = document.getElementById('attendanceTableBody');
            tbody.innerHTML = '';
            
            attendance.forEach(att => {
                const statusColor = this.getAttendanceStatusColor(att.status);
                tbody.innerHTML += `
                    <tr>
                        <td>${att.date}</td>
                        <td>${att.first_name} ${att.last_name}</td>
                        <td><span class="badge ${statusColor}">${att.status.toUpperCase().replace('_', ' ')}</span></td>
                        <td>${att.time_in || '-'}</td>
                        <td>${att.time_out || '-'}</td>
                        <td>${att.marked_by_name || 'System'}</td>
                    </tr>
                `;
            });
        } catch (error) {
            console.error('Error loading attendance:', error);
        }
    }

    async loadMyAttendance() {
        const formData = new FormData();
        formData.append('action', 'get_attendance');
        
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const allAttendance = await response.json();
            
            // Filter for current user
            const myAttendance = allAttendance.filter(att => att.user_id == this.currentUser.id);
            
            const tbody = document.getElementById('myAttendanceTableBody');
            tbody.innerHTML = '';
            
            myAttendance.forEach(att => {
                const statusColor = this.getAttendanceStatusColor(att.status);
                tbody.innerHTML += `
                    <tr>
                        <td>${att.date}</td>
                        <td><span class="badge ${statusColor}">${att.status.toUpperCase().replace('_', ' ')}</span></td>
                        <td>${att.time_in || '-'}</td>
                        <td>${att.time_out || '-'}</td>
                    </tr>
                `;
            });
            
            // Calculate summary for current month
            const currentMonth = new Date().getMonth() + 1;
            const currentYear = new Date().getFullYear();
            const thisMonth = myAttendance.filter(att => {
                const attDate = new Date(att.date);
                return attDate.getMonth() + 1 === currentMonth && attDate.getFullYear() === currentYear;
            });
            
            const presentDays = thisMonth.filter(att => att.status === 'present').length;
            const halfDays = thisMonth.filter(att => att.status === 'half_day').length;
            const absentDays = thisMonth.filter(att => att.status === 'absent').length;
            const totalMarked = thisMonth.length;
            
            document.getElementById('myAttendanceSummary').innerHTML = `
                <div class="row text-center">
                    <div class="col-3">
                        <h4 class="text-success">${presentDays}</h4>
                        <small>Present</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-warning">${halfDays}</h4>
                        <small>Half Day</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-danger">${absentDays}</h4>
                        <small>Absent</small>
                    </div>
                    <div class="col-3">
                        <h4 class="text-primary">${totalMarked}</h4>
                        <small>Total Marked</small>
                    </div>
                </div>
            `;
        } catch (error) {
            console.error('Error loading my attendance:', error);
        }
    }

    async loadSalaryData() {
        const month = document.getElementById('viewSalaryMonth').value;
        const year = document.getElementById('viewSalaryYear').value;
        
        const formData = new FormData();
        formData.append('action', 'get_salary_data');
        formData.append('month', month);
        formData.append('year', year);
        
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const salaryData = await response.json();
            
            const tbody = document.getElementById('salaryTableBody');
            tbody.innerHTML = '';
            
            if (salaryData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center">No salary data available. Please calculate salaries first.</td>
                    </tr>`;
                return;
            }
            
            salaryData.forEach(emp => {
                const effectiveDays = parseFloat(emp.present_days) + (parseFloat(emp.half_days) * 0.5);
                const attendancePercentage = (effectiveDays / emp.working_days * 100).toFixed(2);
                
                tbody.innerHTML += `
                    <tr>
                        <td>${emp.first_name} ${emp.last_name}</td>
                        <td>${parseInt(emp.base_salary).toLocaleString()}</td>
                        <td>${emp.performance_rating}/5</td>
                        <td>${emp.working_days}</td>
                        <td>
                            P: ${emp.present_days} | 
                            HD: ${emp.half_days} | 
                            A: ${emp.absent_days}
                        </td>
                        <td>${attendancePercentage}%</td>
                        <td>${parseInt(emp.performance_bonus).toLocaleString()}</td>
                        <td>${parseInt(emp.attendance_deduction).toLocaleString()}</td>
                        <td><strong>${parseInt(emp.final_salary).toLocaleString()}</strong></td>
                    </tr>
                `;
            });
        } catch (error) {
            console.error('Error loading salary data:', error);
        }
    }

    async loadPerformanceData() {
        const formData = new FormData();
        formData.append('action', 'get_employees');
        
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const employees = await response.json();
            
            const tbody = document.getElementById('performanceTableBody');
            tbody.innerHTML = '';
            
            employees.filter(emp => emp.role === 'employee').forEach(emp => {
                const performanceColor = this.getPerformanceColor(emp.performance_rating);
                tbody.innerHTML += `
                    <tr>
                        <td>${emp.first_name} ${emp.last_name}</td>
                        <td><span class="badge ${performanceColor}">${emp.performance_rating}/5</span></td>
                        <td>${emp.department}</td>
                        <td>${emp.join_date}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="ems.updatePerformance(${emp.id}, '${emp.first_name} ${emp.last_name}', ${emp.performance_rating})">
                                <i class="fas fa-star"></i> Update
                            </button>
                        </td>
                    </tr>
                `;
            });
        } catch (error) {
            console.error('Error loading performance data:', error);
        }
    }

    loadProfile() {
        document.getElementById('profileName').textContent = this.currentUser.first_name + ' ' + this.currentUser.last_name;
        document.getElementById('profileRole').textContent = this.currentUser.role.toUpperCase();
        
        document.getElementById('profileInfo').innerHTML = `
            <div class="row">
                <div class="col-sm-3"><strong>Email:</strong></div>
                <div class="col-sm-9">${this.currentUser.email}</div>
            </div>
            <hr>
            <div class="row">
                <div class="col-sm-3"><strong>Department:</strong></div>
                <div class="col-sm-9">${this.currentUser.department}</div>
            </div>
            <hr>
            <div class="row">
                <div class="col-sm-3"><strong>Position:</strong></div>
                <div class="col-sm-9">${this.currentUser.position}</div>
            </div>
            <hr>
            <div class="row">
                <div class="col-sm-3"><strong>Salary:</strong></div>
                <div class="col-sm-9">${Number(this.currentUser.base_salary).toLocaleString()}</div>
            </div>
            <hr>
            <div class="row">
                <div class="col-sm-3"><strong>Performance:</strong></div>
                <div class="col-sm-9"><span class="badge ${this.getPerformanceColor(this.currentUser.performance_rating)}">${this.currentUser.performance_rating}/5</span></div>
            </div>
        `;
    }

    async markAttendance() {
        // This function is no longer used as attendance is marked by admin only
        this.showAlert('Attendance can only be marked by administrators', 'error');
    }

    getAttendanceStatusColor(status) {
        switch (status) {
            case 'present': return 'bg-success';
            case 'half_day': return 'bg-warning';
            case 'absent': return 'bg-danger';
            default: return 'bg-secondary';
        }
    }

    openEmployeeModal(employee = null) {
        document.getElementById('employeeModalTitle').textContent = employee ? 'Edit Employee' : 'Add New Employee';
        
        if (employee) {
            document.getElementById('employeeId').value = employee.id;
            document.getElementById('empFirstName').value = employee.first_name;
            document.getElementById('empLastName').value = employee.last_name;
            document.getElementById('empEmail').value = employee.email;
            document.getElementById('empDepartment').value = employee.department;
            document.getElementById('empPosition').value = employee.position;
            document.getElementById('empSalary').value = employee.base_salary;
            document.getElementById('empPerformance').value = employee.performance_rating;
        } else {
            document.getElementById('employeeForm').reset();
            document.getElementById('employeeId').value = '';
        }
    }
    async saveAttendance() {
    const formData = new FormData();
    formData.append('action', 'mark_attendance_admin');
    formData.append('user_id', document.getElementById('attendanceEmployee').value);
    formData.append('date', document.getElementById('attendanceDate').value);
    formData.append('status', document.getElementById('attendanceStatus').value);
    formData.append('time_in', document.getElementById('attendanceTimeIn').value);
    formData.append('time_out', document.getElementById('attendanceTimeOut').value);
    
    try {
        const response = await fetch('', { method: 'POST', body: formData });
        const data = await response.json();
        
        if (data.success) {
            this.showAlert('Attendance marked successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('attendanceModal')).hide();
            this.loadAttendance();  
            this.loadDashboardData();
        } else {
            this.showAlert(data.message, 'error');
        }
    } catch (error) {
        this.showAlert('Failed to mark attendance', 'error');
    }
}

    async saveEmployee() {
        const formData = new FormData();
        formData.append('action', 'save_employee');
        formData.append('id', document.getElementById('employeeId').value);
        formData.append('first_name', document.getElementById('empFirstName').value);
        formData.append('last_name', document.getElementById('empLastName').value);
        formData.append('email', document.getElementById('empEmail').value);
        formData.append('department', document.getElementById('empDepartment').value);
        formData.append('position', document.getElementById('empPosition').value);
        formData.append('base_salary', document.getElementById('empSalary').value);
        formData.append('performance', document.getElementById('empPerformance').value);
        
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();
            
            if (data.success) {
                this.showAlert('Employee saved successfully!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('employeeModal')).hide();
                this.loadEmployees();
                this.loadDashboardData();
            } else {
                this.showAlert(data.message, 'error');
            }
        } catch (error) {
            this.showAlert('Failed to save employee', 'error');
        }
    }

    async editEmployee(id) {
        const formData = new FormData();
        formData.append('action', 'get_employees');
        
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const employees = await response.json();
            const employee = employees.find(emp => emp.id == id);
            
            if (employee) {
                this.openEmployeeModal(employee);
                new bootstrap.Modal(document.getElementById('employeeModal')).show();
            }
        } catch (error) {
            this.showAlert('Failed to load employee data', 'error');
        }
    }

    async deleteEmployee(id) {
        if (confirm('Are you sure you want to delete this employee?')) {
            const formData = new FormData();
            formData.append('action', 'delete_employee');
            formData.append('id', id);
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                
                if (data.success) {
                    this.showAlert('Employee deleted successfully!', 'success');
                    this.loadEmployees();
                    this.loadDashboardData();
                } else {
                    this.showAlert('Failed to delete employee', 'error');
                }
            } catch (error) {
                this.showAlert('Failed to delete employee', 'error');
            }
        }
    }

    updatePerformance(userId, userName, currentRating) {
        document.getElementById('perfEmployeeId').value = userId;
        document.getElementById('perfEmployeeName').value = userName;
        document.getElementById('perfRating').value = currentRating;
        document.getElementById('perfComments').value = '';
        
        new bootstrap.Modal(document.getElementById('performanceModal')).show();
    }

    async savePerformance() {
        const formData = new FormData();
        formData.append('action', 'update_performance');
        formData.append('user_id', document.getElementById('perfEmployeeId').value);
        formData.append('rating', document.getElementById('perfRating').value);
        formData.append('comments', document.getElementById('perfComments').value);
        
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();
            
            if (data.success) {
                this.showAlert('Performance updated successfully!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('performanceModal')).hide();
                this.loadPerformanceData();
                this.loadDashboardData();
            } else {
                this.showAlert('Failed to update performance', 'error');
            }
        } catch (error) {
            this.showAlert('Failed to update performance', 'error');
        }
    }

    updateSalary(userId, userName, currentSalary) {
        // This function is no longer needed as salary is calculated automatically
        this.showAlert('Salaries are calculated automatically based on attendance and performance', 'info');
    }

    async saveSalary() {
        // This function is no longer needed as salary is calculated automatically
        this.showAlert('Salaries are calculated automatically based on attendance and performance', 'info');
    }

    logout() {
        // Clear session
        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=logout'
        })
        .finally(() => {
            this.currentUser = null;
            this.currentUserRole = null;
            document.getElementById('dashboardPage').classList.add('hidden');
            document.getElementById('loginPage').classList.remove('hidden');
            this.showLoginForm();
            document.getElementById('loginForm').reset();
        });
    }

    updateDateTime() {
        const now = new Date();
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        };
        const dateStr = now.toLocaleDateString('en-US', options);
        
        const dateElement = document.getElementById('currentDate');
        if (dateElement) {
            dateElement.textContent = dateStr;
        }
        
        // Update every minute
        setTimeout(() => this.updateDateTime(), 60000);
    }

    getPerformanceColor(rating) {
        switch (parseInt(rating)) {
            case 5: return 'bg-success';
            case 4: return 'bg-primary';
            case 3: return 'bg-warning';
            case 2: return 'bg-danger';
            case 1: return 'bg-dark';
            default: return 'bg-secondary';
        }
    }

    async calculateMonthlySalary() {
        const month = document.getElementById('salaryMonth').value;
        const year = new Date().getFullYear();
        const workingDays = document.getElementById('workingDays').value || 22;
        
        const formData = new FormData();
        formData.append('action', 'calculate_monthly_salary');
        formData.append('month', month);
        formData.append('year', year);
        formData.append('working_days', workingDays);
        
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            const data = await response.json();
            
            if (data.success) {
                alert('Salaries calculated successfully!');
                this.loadSalaryData(); // Refresh the salary data display
            } else {
                alert('Error calculating salaries: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to calculate salaries. Please check the console for details.');
        }
    }

    showAlert(message, type) {
        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 5000);
    }
}

// Global functions for onclick events
function openEmployeeModal() {
    window.ems.openEmployeeModal();
}

// Initialize the system
const ems = new EmployeeManagementSystem();
window.ems = ems; // Make it globally accessible
</script>

<?php
// Handle logout
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// Handle session check
if (isset($_POST['action']) && $_POST['action'] === 'check_session') {
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
?>

</body>
</html>