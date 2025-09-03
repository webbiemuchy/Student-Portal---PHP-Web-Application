<?php
require_once __DIR__ .'/../config.php';
requireLogin();

// Ensure only students can access this page
if (getUserType() !== 'student') {
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Get student info
$stmt = $pdo->prepare("
    SELECT s.* 
    FROM students s 
    WHERE s.user_id = ?
");
$stmt->execute([$userId]);
$studentInfo = $stmt->fetch();

// Get enrolled courses with detailed information
$stmt = $pdo->prepare("
    SELECT c.*, e.final_grade, e.letter_grade, e.enrollment_date, e.status as enrollment_status,
           t.first_name as teacher_first, t.last_name as teacher_last, t.department,
           COUNT(DISTINCT a.id) as total_assignments,
           COUNT(DISTINCT g.id) as completed_assignments
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN assignments a ON c.id = a.course_id
    LEFT JOIN grades g ON (a.id = g.assignment_id AND g.student_id = e.student_id AND g.points_earned IS NOT NULL)
    WHERE e.student_id = ?
    GROUP BY c.id, e.id
    ORDER BY c.course_name
");
$stmt->execute([$studentInfo['id']]);
$courses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - <?php echo APP_NAME; ?></title>
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
        
        .course-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .course-card:hover {
            transform: translateY(-5px);
        }
        
        .course-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1.5rem;
        }
        
        .grade-display {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .progress-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 1.2rem;
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
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.8rem;
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
                        <li class="nav-item">
                            <a href="courses.php" class="nav-link active">
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
                <div class="pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-book me-2"></i>My Courses
                        <span class="text-muted fs-6"><?php echo count($courses); ?> enrolled course<?php echo count($courses) !== 1 ? 's' : ''; ?></span>
                    </h1>
                </div>

                <?php if (empty($courses)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                        <h3 class="text-muted">No Courses Enrolled</h3>
                        <p class="text-muted">You are not currently enrolled in any courses.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($courses as $course): ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card course-card">
                                <div class="course-header">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="mb-1"><?php echo htmlspecialchars($course['course_code']); ?></h5>
                                            <h6 class="mb-2 opacity-75"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                            <small class="opacity-75">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($course['teacher_first'] . ' ' . $course['teacher_last']); ?>
                                            </small>
                                        </div>
                                        <?php if ($course['letter_grade']): ?>
                                            <div class="grade-display">
                                                <?php echo $course['letter_grade']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Credits</small>
                                            <div class="fw-bold"><?php echo $course['credits']; ?></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Semester</small>
                                            <div class="fw-bold"><?php echo $course['semester'] . ' ' . $course['year']; ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Department</small>
                                            <div class="fw-bold"><?php echo htmlspecialchars($course['department'] ?? 'N/A'); ?></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Status</small>
                                            <div>
                                                <?php
                                                $statusClass = 'bg-success';
                                                if ($course['enrollment_status'] === 'dropped') $statusClass = 'bg-danger';
                                                elseif ($course['enrollment_status'] === 'completed') $statusClass = 'bg-info';
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?> status-badge">
                                                    <?php echo ucfirst($course['enrollment_status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Progress -->
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div>
                                            <small class="text-muted">Assignment Progress</small>
                                            <div class="fw-bold">
                                                <?php echo $course['completed_assignments']; ?>/<?php echo $course['total_assignments']; ?>
                                            </div>
                                        </div>
                                        <div class="progress-circle bg-primary">
                                            <?php
                                            $percentage = $course['total_assignments'] > 0 
                                                ? round(($course['completed_assignments'] / $course['total_assignments']) * 100) 
                                                : 0;
                                            echo $percentage . '%';
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($course['description']): ?>
                                    <p class="text-muted small mb-3">
                                        <?php echo htmlspecialchars(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '...' : ''); ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted">Enrolled</small>
                                            <div class="small"><?php echo formatDate($course['enrollment_date']); ?></div>
                                        </div>
                                        <?php if ($course['final_grade']): ?>
                                        <div class="col-6">
                                            <small class="text-muted">Final Grade</small>
                                            <div class="fw-bold"><?php echo $course['final_grade']; ?>%</div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-transparent">
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="course-details.php?id=<?php echo $course['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                        <a href="assignments.php?course=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-tasks me-1"></i>Assignments
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>