<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$userType = getUserType();
if ($userType !== 'student') {
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Get student info
$stmt = $pdo->prepare("
    SELECT s.*, u.email 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.user_id = ?
");
$stmt->execute([$userId]);
$studentInfo = $stmt->fetch();

// Get all grades with course and assignment details
$stmt = $pdo->prepare("
    SELECT 
        c.course_name,
        c.course_code,
        a.title as assignment_title,
        a.assignment_type,
        a.max_points,
        g.points_earned,
        g.submitted_at,
        g.graded_at,
        g.feedback,
        CASE 
            WHEN g.points_earned IS NULL THEN 'Not Graded'
            ELSE CONCAT(ROUND((g.points_earned/a.max_points)*100, 1), '%')
        END as percentage
    FROM grades g
    JOIN assignments a ON g.assignment_id = a.id
    JOIN courses c ON a.course_id = c.id
    JOIN enrollments e ON (e.course_id = c.id AND e.student_id = g.student_id)
    WHERE g.student_id = ? AND e.status = 'enrolled'
    ORDER BY c.course_name, a.due_date DESC
");
$stmt->execute([$studentInfo['id']]);
$grades = $stmt->fetchAll();

// Group grades by course
$gradesByCourse = [];
foreach ($grades as $grade) {
    $courseKey = $grade['course_code'] . ' - ' . $grade['course_name'];
    $gradesByCourse[$courseKey][] = $grade;
}

// Calculate course averages
$courseAverages = [];
foreach ($gradesByCourse as $courseKey => $courseGrades) {
    $totalPoints = 0;
    $totalMaxPoints = 0;
    $gradedCount = 0;
    
    foreach ($courseGrades as $grade) {
        if ($grade['points_earned'] !== null) {
            $totalPoints += $grade['points_earned'];
            $totalMaxPoints += $grade['max_points'];
            $gradedCount++;
        }
    }
    
    if ($gradedCount > 0 && $totalMaxPoints > 0) {
        $courseAverages[$courseKey] = round(($totalPoints / $totalMaxPoints) * 100, 2);
    } else {
        $courseAverages[$courseKey] = null;
    }
}

function getGradeColor($percentage) {
    if ($percentage >= 90) return 'success';
    if ($percentage >= 80) return 'info';
    if ($percentage >= 70) return 'warning';
    return 'danger';
}

function getLetterGrade($percentage) {
    if ($percentage >= 97) return 'A+';
    if ($percentage >= 93) return 'A';
    if ($percentage >= 90) return 'A-';
    if ($percentage >= 87) return 'B+';
    if ($percentage >= 83) return 'B';
    if ($percentage >= 80) return 'B-';
    if ($percentage >= 77) return 'C+';
    if ($percentage >= 73) return 'C';
    if ($percentage >= 70) return 'C-';
    if ($percentage >= 67) return 'D+';
    if ($percentage >= 60) return 'D';
    return 'F';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Grades - <?php echo APP_NAME; ?></title>
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
        
        .dashboard-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
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
        
        .grade-item {
            border-left: 4px solid var(--primary-color);
            background: rgba(102, 126, 234, 0.05);
            margin-bottom: 0.5rem;
            padding: 1rem;
            border-radius: 0 10px 10px 0;
        }
        
        .course-average {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .assignment-type-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
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
                            <a href="courses.php" class="nav-link">
                                <i class="fas fa-book me-2"></i>My Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="grades.php" class="nav-link active">
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
                        <i class="fas fa-chart-bar me-2"></i>My Grades
                    </h1>
                </div>

                <!-- Class Rank -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="course-average text-center">
                            <h3><i class="fas fa-star me-2"></i>Class Rank: <?php echo $studentInfo['class_rank']; ?></h3>
                            <p class="mb-0">Current cumulative grade point average</p>
                        </div>
                    </div>
                </div>

                <?php if (empty($gradesByCourse)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card dashboard-card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No Grades Available</h4>
                                <p class="text-muted">Your grades will appear here once assignments are graded.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                
                <!-- Grades by Course -->
                <?php foreach ($gradesByCourse as $courseKey => $courseGrades): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card dashboard-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo $courseKey; ?></h5>
                                <?php if ($courseAverages[$courseKey] !== null): ?>
                                <span class="badge bg-light text-dark fs-6">
                                    Average: <?php echo $courseAverages[$courseKey]; ?>% 
                                    (<?php echo getLetterGrade($courseAverages[$courseKey]); ?>)
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php foreach ($courseGrades as $grade): ?>
                                <div class="grade-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h6 class="mb-1"><?php echo $grade['assignment_title']; ?></h6>
                                            <span class="badge assignment-type-badge bg-secondary">
                                                <?php echo ucfirst($grade['assignment_type']); ?>
                                            </span>
                                        </div>
                                        <div class="col-md-3 text-center">
                                            <?php if ($grade['points_earned'] !== null): ?>
                                                <h5 class="mb-0">
                                                    <?php echo $grade['points_earned']; ?>/<?php echo $grade['max_points']; ?>
                                                </h5>
                                                <?php 
                                                $percentage = ($grade['points_earned'] / $grade['max_points']) * 100;
                                                ?>
                                                <span class="badge bg-<?php echo getGradeColor($percentage); ?>">
                                                    <?php echo $grade['percentage']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Not Graded</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3">
                                            <?php if ($grade['graded_at']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    Graded: <?php echo formatDate($grade['graded_at']); ?>
                                                </small>
                                            <?php elseif ($grade['submitted_at']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Submitted: <?php echo formatDate($grade['submitted_at']); ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-warning">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    Not Submitted
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($grade['feedback']): ?>
                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <small class="text-muted">
                                                <strong>Feedback:</strong> <?php echo nl2br(htmlspecialchars($grade['feedback'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>