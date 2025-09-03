<?php
require_once __DIR__ . '/../config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $userType = $_POST['user_type'];
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $studentId = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
    $teacherId = isset($_POST['teacher_id']) ? trim($_POST['teacher_id']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $phone = trim($_POST['phone']);
    $dateOfBirth = isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $officeLocation = isset($_POST['office_location']) ? trim($_POST['office_location']) : '';
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($userType, ['student', 'teacher'])) {
        $error = 'Invalid user type selected.';
    } elseif ($userType === 'student' && empty($studentId)) {
        $error = 'Student ID is required for student registration.';
    } elseif ($userType === 'teacher' && (empty($teacherId) || empty($department))) {
        $error = 'Teacher ID and Department are required for teacher registration.';
    } else {
        try {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                // Check if student_id or teacher_id already exists
                if ($userType === 'student') {
                    $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
                    $stmt->execute([$studentId]);
                    if ($stmt->fetch()) {
                        $error = 'Student ID already exists.';
                    }
                } elseif ($userType === 'teacher') {
                    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE teacher_id = ?");
                    $stmt->execute([$teacherId]);
                    if ($stmt->fetch()) {
                        $error = 'Teacher ID already exists.';
                    }
                }
                
                if (!$error) {
                    // Begin transaction
                    $pdo->beginTransaction();
                    
                    try {
                        // Insert into users table
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            INSERT INTO users (username, email, password, user_type) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$username, $email, $hashedPassword, $userType]);
                        $userId = $pdo->lastInsertId();
                        
                        // Insert into specific user type table
                        if ($userType === 'student') {
                            $stmt = $pdo->prepare("
                                INSERT INTO students (user_id, student_id, first_name, last_name, date_of_birth, phone, address, enrollment_date) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([$userId, $studentId, $firstName, $lastName, $dateOfBirth ?: null, $phone, $address]);
                        } elseif ($userType === 'teacher') {
                            $stmt = $pdo->prepare("
                                INSERT INTO teachers (user_id, teacher_id, first_name, last_name, department, phone, office_location, hire_date) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([$userId, $teacherId, $firstName, $lastName, $department, $phone, $officeLocation]);
                        }
                        
                        $pdo->commit();
                        $success = 'Registration successful! Please wait for admin approval before logging in.';
                        
                        // Clear form data
                        $_POST = array();
                        
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Registration failed. Please try again.';
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 800px;
            width: 100%;
        }
        
        .register-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .register-form {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-register {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: transform 0.3s ease;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .user-type-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .user-type-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .user-type-card.selected {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.1);
        }
        
        .user-type-card:hover {
            border-color: var(--primary-color);
        }
        
        .conditional-fields {
            display: none;
        }
        
        .conditional-fields.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="register-container">
                    <div class="register-header">
                        <h2><i class="fas fa-graduation-cap me-2"></i><?php echo APP_NAME; ?></h2>
                        <p class="mb-0">Create Your Account</p>
                    </div>
                    
                    <div class="register-form">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="registerForm">
                            <!-- User Type Selection -->
                            <div class="mb-4">
                                <label class="form-label">I am registering as:</label>
                                <div class="user-type-cards">
                                    <div class="user-type-card" data-type="student">
                                        <i class="fas fa-user-graduate fa-2x mb-2 text-primary"></i>
                                        <h5>Student</h5>
                                        <p class="text-muted mb-0">Access courses, grades, and assignments</p>
                                        <input type="radio" name="user_type" value="student" style="display: none;">
                                    </div>
                                    <div class="user-type-card" data-type="teacher">
                                        <i class="fas fa-chalkboard-teacher fa-2x mb-2 text-primary"></i>
                                        <h5>Teacher</h5>
                                        <p class="text-muted mb-0">Manage courses and student progress</p>
                                        <input type="radio" name="user_type" value="teacher" style="display: none;">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Basic Information -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                            
                            <!-- Student-specific fields -->
                            <div id="studentFields" class="conditional-fields">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="student_id" class="form-label">Student ID *</label>
                                        <input type="text" class="form-control" id="student_id" name="student_id" 
                                               value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                               value="<?php echo isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Teacher-specific fields -->
                            <div id="teacherFields" class="conditional-fields">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="teacher_id" class="form-label">Teacher ID *</label>
                                        <input type="text" class="form-control" id="teacher_id" name="teacher_id" 
                                               value="<?php echo isset($_POST['teacher_id']) ? htmlspecialchars($_POST['teacher_id']) : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="department" class="form-label">Department *</label>
                                        <input type="text" class="form-control" id="department" name="department" 
                                               value="<?php echo isset($_POST['department']) ? htmlspecialchars($_POST['department']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="office_location" class="form-label">Office Location</label>
                                    <input type="text" class="form-control" id="office_location" name="office_location" 
                                           value="<?php echo isset($_POST['office_location']) ? htmlspecialchars($_POST['office_location']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-register">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </button>
                            </div>
                            
                            <div class="text-center">
                                <p class="text-muted">Already have an account? 
                                    <a href="index.php" class="text-decoration-none">Sign in here</a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userTypeCards = document.querySelectorAll('.user-type-card');
            const studentFields = document.getElementById('studentFields');
            const teacherFields = document.getElementById('teacherFields');
            
            userTypeCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    userTypeCards.forEach(c => c.classList.remove('selected'));
                    // Add selected class to clicked card
                    this.classList.add('selected');
                    
                    // Set the radio button
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    
                    // Show/hide conditional fields
                    const userType = this.dataset.type;
                    if (userType === 'student') {
                        studentFields.classList.add('show');
                        teacherFields.classList.remove('show');
                        // Make student fields required
                        document.getElementById('student_id').required = true;
                        document.getElementById('teacher_id').required = false;
                        document.getElementById('department').required = false;
                    } else if (userType === 'teacher') {
                        teacherFields.classList.add('show');
                        studentFields.classList.remove('show');
                        // Make teacher fields required
                        document.getElementById('teacher_id').required = true;
                        document.getElementById('department').required = true;
                        document.getElementById('student_id').required = false;
                    }
                });
            });
            
            // Password confirmation validation
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePassword() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords don't match");
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            password.addEventListener('change', validatePassword);
            confirmPassword.addEventListener('keyup', validatePassword);
        });
    </script>
</body>
</html>