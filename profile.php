<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$userType = getUserType();
$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($userType === 'student') {
            $stmt = $pdo->prepare("
                UPDATE students s 
                JOIN users u ON s.user_id = u.id 
                SET s.first_name = ?, s.last_name = ?, s.phone = ?, s.address = ?, u.email = ?
                WHERE s.user_id = ?
            ");
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['phone'],
                $_POST['address'],
                $_POST['email'],
                $userId
            ]);
        } elseif ($userType === 'teacher') {
            $stmt = $pdo->prepare("
                UPDATE teachers t 
                JOIN users u ON t.user_id = u.id 
                SET t.first_name = ?, t.last_name = ?, t.department = ?, t.phone = ?, t.office_location = ?, u.email = ?
                WHERE t.user_id = ?
            ");
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['department'],
                $_POST['phone'],
                $_POST['office_location'],
                $_POST['email'],
                $userId
            ]);
        }
        
        $message = 'Profile updated successfully!';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Error updating profile: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get current user data
if ($userType === 'student') {
    $stmt = $pdo->prepare("
        SELECT s.*, u.email, u.username 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.user_id = ?
    ");
    $stmt->execute([$userId]);
    $userInfo = $stmt->fetch();
} elseif ($userType === 'teacher') {
    $stmt = $pdo->prepare("
        SELECT t.*, u.email, u.username 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.user_id = ?
    ");
    $stmt->execute([$userId]);
    $userInfo = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userInfo = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }
        
        .profile-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
        }
        
        .sidebar {
            background: white;
            min-height: calc(100vh - 76px);
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .nav-pills .nav-link {
            color: #6c757d;
            margin-bottom: 0.5rem;
            border-radius: 10px;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
        
        .nav-pills .nav-link:hover:not(.active) {
            background-color: rgba(102, 126, 234, 0.1);
            color: var(--primary-color);
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            margin: 0 auto 1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i><?php echo APP_NAME; ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-2"></i><?php echo $_SESSION['username']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav nav-pills flex-column mb-auto">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <?php if ($userType === 'student'): ?>
                        <li class="nav-item">
                            <a href="courses.php" class="nav-link">
                                <i class="fas fa-book me-2"></i>My Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="grades.php" class="nav-link">
                                <i class="fas fa-chart-bar me-2"></i>Grades
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="assignments.php" class="nav-link">
                                <i class="fas fa-tasks me-2"></i>Assignments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="attendance.php" class="nav-link">
                                <i class="fas fa-calendar-check me-2"></i>Attendance
                            </a>
                        </li>
                        <?php elseif ($userType === 'teacher'): ?>
                        <li class="nav-item">
                            <a href="my-courses.php" class="nav-link">
                                <i class="fas fa-chalkboard me-2"></i>My Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="gradebook.php" class="nav-link">
                                <i class="fas fa-clipboard-list me-2"></i>Gradebook
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="manage-assignments.php" class="nav-link">
                                <i class="fas fa-plus-square me-2"></i>Assignments
                            </a>
                        </li>
                        <?php elseif ($userType === 'admin'): ?>
                        <li class="nav-item">
                            <a href="manage-users.php" class="nav-link">
                                <i class="fas fa-users me-2"></i>Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="manage-courses.php" class="nav-link">
                                <i class="fas fa-school me-2"></i>Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="reports.php" class="nav-link">
                                <i class="fas fa-chart-line me-2"></i>Reports
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a href="announcements.php" class="nav-link">
                                <i class="fas fa-bullhorn me-2"></i>Announcements
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="pt-3 pb-2 mb-3">
                    <h1 class="h2">
                        <i class="fas fa-user me-2"></i>Profile
                        <span class="text-muted fs-6">Manage your personal information</span>
                    </h1>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Profile Overview -->
                    <div class="col-md-4 mb-4">
                        <div class="card profile-card">
                            <div class="card-header text-center">
                                <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Profile Overview</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="profile-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <h4><?php echo $userInfo['first_name'] . ' ' . $userInfo['last_name']; ?></h4>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-envelope me-2"></i><?php echo $userInfo['email']; ?>
                                </p>
                                <p class="text-muted mb-2">
                                    <i class="fas fa-user-tag me-2"></i><?php echo ucfirst($userType); ?>
                                </p>
                                <?php if ($userType === 'student'): ?>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-id-badge me-2"></i>ID: <?php echo $userInfo['student_id']; ?>
                                    </p>
                                    <p class="text-muted">
                                        <i class="fas fa-star me-2"></i>Class Rank: <?php echo $userInfo['class_rank']; ?>
                                    </p>
                                <?php elseif ($userType === 'teacher'): ?>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-id-badge me-2"></i>ID: <?php echo $userInfo['teacher_id']; ?>
                                    </p>
                                    <p class="text-muted">
                                        <i class="fas fa-building me-2"></i><?php echo $userInfo['department']; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Profile Form -->
                    <div class="col-md-8">
                        <div class="card profile-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Profile</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="first_name" class="form-label">First Name</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                   value="<?php echo htmlspecialchars($userInfo['first_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="last_name" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                   value="<?php echo htmlspecialchars($userInfo['last_name']); ?>" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($userInfo['email']); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($userInfo['phone'] ?? ''); ?>">
                                    </div>

                                    <?php if ($userType === 'student'): ?>
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($userInfo['address'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Student ID</label>
                                            <input type="text" class="form-control" value="<?php echo $userInfo['student_id']; ?>" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Enrollment Date</label>
                                            <input type="text" class="form-control" value="<?php echo formatDate($userInfo['enrollment_date']); ?>" readonly>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Date of Birth</label>
                                            <input type="text" class="form-control" value="<?php echo $userInfo['date_of_birth'] ? formatDate($userInfo['date_of_birth']) : 'Not set'; ?>" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Class Rank</label>
                                            <input type="text" class="form-control" value="<?php echo $userInfo['class_rank']; ?>" readonly>
                                        </div>
                                    </div>
                                    <?php elseif ($userType === 'teacher'): ?>
                                    <div class="mb-3">
                                        <label for="department" class="form-label">Department</label>
                                        <input type="text" class="form-control" id="department" name="department" 
                                               value="<?php echo htmlspecialchars($userInfo['department'] ?? ''); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label for="office_location" class="form-label">Office Location</label>
                                        <input type="text" class="form-control" id="office_location" name="office_location" 
                                               value="<?php echo htmlspecialchars($userInfo['office_location'] ?? ''); ?>">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Teacher ID</label>
                                            <input type="text" class="form-control" value="<?php echo $userInfo['teacher_id']; ?>" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Hire Date</label>
                                            <input type="text" class="form-control" value="<?php echo formatDate($userInfo['hire_date']); ?>" readonly>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Username</label>
                                            <input type="text" class="form-control" value="<?php echo $userInfo['username']; ?>" readonly>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">User Type</label>
                                            <input type="text" class="form-control" value="<?php echo ucfirst($userType); ?>" readonly>
                                        </div>
                                    </div>

                                    <hr class="my-4">

                                    <div class="d-flex justify-content-between">
                                        <a href="dashboard.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Update Profile
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>