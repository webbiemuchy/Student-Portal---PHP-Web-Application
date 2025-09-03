<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$userType = getUserType();
$userId = $_SESSION['user_id'];

// Get course ID from URL
$courseId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$courseId) {
    header('Location: dashboard.php');
    exit;
}

// Get course details
$stmt = $pdo->prepare("
    SELECT c.*, t.first_name as teacher_first, t.last_name as teacher_last, t.department, t.office_location, t.id as teacher_id
    FROM courses c
    LEFT JOIN teachers t ON c.teacher_id = t.id
    WHERE c.id = ?
");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    header('Location: dashboard.php');
    exit;
}

// Check if user has access to this course
$hasAccess = false;
if ($userType === 'admin') {
    $hasAccess = true;
} elseif ($userType === 'teacher') {
    // Check if teacher owns this course
    $stmt = $pdo->prepare("SELECT t.id FROM teachers t WHERE t.user_id = ? AND t.id = ?");
    $stmt->execute([$userId, $course['teacher_id']]);
    $hasAccess = $stmt->fetch() !== false;
} elseif ($userType === 'student') {
    // Check if student is enrolled in this course
    $stmt = $pdo->prepare("
        SELECT e.id FROM enrollments e 
        JOIN students s ON e.student_id = s.id 
        WHERE s.user_id = ? AND e.course_id = ? AND e.status = 'enrolled'
    ");
    $stmt->execute([$userId, $courseId]);
    $hasAccess = $stmt->fetch() !== false;
}

if (!$hasAccess) {
    header('Location: dashboard.php');
    exit;
}

// Get enrolled students
$stmt = $pdo->prepare("
    SELECT s.student_id, s.first_name, s.last_name, s.class_rank, e.enrollment_date, e.final_grade, e.letter_grade, e.status
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    WHERE e.course_id = ?
    ORDER BY s.last_name, s.first_name
");
$stmt->execute([$courseId]);
$students = $stmt->fetchAll();

// Get assignments for this course
$stmt = $pdo->prepare("
    SELECT * FROM assignments 
    WHERE course_id = ? 
    ORDER BY due_date ASC
");
$stmt->execute([$courseId]);
$assignments = $stmt->fetchAll();

// Get recent activity (grades, submissions)
$stmt = $pdo->prepare("
    SELECT a.title as assignment_title, st.first_name, st.last_name, g.points_earned, g.graded_at, a.max_points
    FROM grades g
    JOIN assignments a ON g.assignment_id = a.id
    JOIN students st ON g.student_id = st.id
    WHERE a.course_id = ? AND g.graded_at IS NOT NULL
    ORDER BY g.graded_at DESC
    LIMIT 10
");
$stmt->execute([$courseId]);
$recentActivity = $stmt->fetchAll();

// Calculate course statistics
$totalStudents = count($students);
$activeStudents = count(array_filter($students, function($s) { return $s['status'] === 'enrolled'; }));
$completedAssignments = count(array_filter($assignments, function($a) { return strtotime($a['due_date']) < time(); }));
$upcomingAssignments = count(array_filter($assignments, function($a) { return strtotime($a['due_date']) >= time(); }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Details - <?php echo APP_NAME; ?></title>
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
        
        .course-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .stats-card.warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        
        .stats-card.info {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
        }
        
        .stats-card.danger {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
        }
        
        .table th {
            border-top: none;
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .badge-status {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        
        .assignment-item {
            border-left: 4px solid var(--primary-color);
            background: rgba(102, 126, 234, 0.05);
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0 10px 10px 0;
        }
        
        .activity-item {
            border-left: 4px solid #28a745;
            background: rgba(40, 167, 69, 0.05);
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0 10px 10px 0;
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

    <!-- Course Header -->
    <div class="course-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-6 mb-2"><?php echo htmlspecialchars($course['course_name']); ?></h1>
                    <p class="lead mb-2"><?php echo htmlspecialchars($course['course_code']); ?> • <?php echo $course['credits']; ?> Credits</p>
                    <?php if ($course['teacher_first']): ?>
                    <p class="mb-0">
                        <i class="fas fa-user me-2"></i>
                        <?php echo htmlspecialchars($course['teacher_first'] . ' ' . $course['teacher_last']); ?>
                        <?php if ($course['department']): ?>
                            • <?php echo htmlspecialchars($course['department']); ?>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <a href="dashboard.php" class="btn btn-light btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Course Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <h3><i class="fas fa-users me-2"></i><?php echo $totalStudents; ?></h3>
                    <p class="mb-0">Total Students</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card info">
                    <h3><i class="fas fa-user-check me-2"></i><?php echo $activeStudents; ?></h3>
                    <p class="mb-0">Active Enrolled</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card warning">
                    <h3><i class="fas fa-tasks me-2"></i><?php echo count($assignments); ?></h3>
                    <p class="mb-0">Total Assignments</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card danger">
                    <h3><i class="fas fa-clock me-2"></i><?php echo $upcomingAssignments; ?></h3>
                    <p class="mb-0">Upcoming Due</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Course Description -->
            <?php if ($course['description']): ?>
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Course Description</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Enrolled Students -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Enrolled Students</h5>
                        <?php if ($userType === 'teacher' && $hasAccess): ?>
                        <a href="manage-enrollment.php?course=<?php echo $courseId; ?>" class="btn btn-sm btn-light">
                            <i class="fas fa-user-plus me-1"></i>Manage Enrollment
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($students)): ?>
                            <p class="text-muted">No students enrolled in this course.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Student ID</th>
                                            <th>Name</th>
                                            <th>Status</th>
                                            <th>Enrolled Date</th>
                                            <?php if ($userType === 'teacher'): ?>
                                            <th>Grade</th>
                                            <th>Class Rank</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                            <td>
                                                <span class="badge badge-status <?php 
                                                    echo $student['status'] === 'enrolled' ? 'bg-success' : 
                                                        ($student['status'] === 'dropped' ? 'bg-danger' : 'bg-secondary'); 
                                                ?>">
                                                    <?php echo ucfirst($student['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo formatDate($student['enrollment_date']); ?></td>
                                            <?php if ($userType === 'teacher'): ?>
                                            <td>
                                                <?php if ($student['letter_grade']): ?>
                                                    <span class="badge bg-primary"><?php echo $student['letter_grade']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $student['class_rank']; ?></td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Assignments & Recent Activity -->
            <div class="col-lg-4">
                <!-- Assignments -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Assignments</h5>
                        <?php if ($userType === 'teacher' && $hasAccess): ?>
                        <a href="manage-assignments.php?course=<?php echo $courseId; ?>" class="btn btn-sm btn-light">
                            <i class="fas fa-plus me-1"></i>Add
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($assignments)): ?>
                            <p class="text-muted">No assignments created.</p>
                        <?php else: ?>
                            <?php foreach ($assignments as $assignment): ?>
                            <div class="assignment-item">
                                <h6 class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                <p class="mb-2 small text-muted"><?php echo htmlspecialchars($assignment['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?php echo $assignment['assignment_type'] === 'exam' ? 'danger' : 'primary'; ?>">
                                        <?php echo ucfirst($assignment['assignment_type']); ?>
                                    </span>
                                    <small class="text-muted">
                                        Due: <?php echo formatDateTime($assignment['due_date']); ?>
                                    </small>
                                </div>
                                <div class="mt-2">
                                    <small><strong><?php echo $assignment['max_points']; ?></strong> points</small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($recentActivity)): ?>
                            <p class="text-muted">No recent activity.</p>
                        <?php else: ?>
                            <?php foreach ($recentActivity as $activity): ?>
                            <div class="activity-item">
                                <h6 class="mb-1"><?php echo htmlspecialchars($activity['assignment_title']); ?></h6>
                                <p class="mb-1"><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><?php echo $activity['points_earned'] . '/' . $activity['max_points']; ?></strong>
                                    <small class="text-muted"><?php echo formatDateTime($activity['graded_at']); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>