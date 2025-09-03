<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$userType = getUserType();
$userId = $_SESSION['user_id'];

// Get user-specific data based on user type
if ($userType === 'student') {
    // Get student info
    $stmt = $pdo->prepare("
        SELECT s.*, u.email 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.user_id = ?
    ");
    $stmt->execute([$userId]);
    $studentInfo = $stmt->fetch();
    
    // Get enrolled courses
    $stmt = $pdo->prepare("
        SELECT c.*, e.final_grade, e.letter_grade, t.first_name as teacher_first, t.last_name as teacher_last
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        LEFT JOIN teachers t ON c.teacher_id = t.id
        WHERE e.student_id = ? AND e.status = 'enrolled'
        ORDER BY c.course_name
    ");
    $stmt->execute([$studentInfo['id']]);
    $courses = $stmt->fetchAll();
    
    // Get recent grades
    $stmt = $pdo->prepare("
        SELECT a.title, a.max_points, g.points_earned, c.course_name, g.graded_at
        FROM grades g
        JOIN assignments a ON g.assignment_id = a.id
        JOIN courses c ON a.course_id = c.id
        JOIN enrollments e ON (e.course_id = c.id AND e.student_id = g.student_id)
        WHERE g.student_id = ? AND g.points_earned IS NOT NULL
        ORDER BY g.graded_at DESC
        LIMIT 5
    ");
    $stmt->execute([$studentInfo['id']]);
    $recentGrades = $stmt->fetchAll();
    
    // Get upcoming assignments
    $stmt = $pdo->prepare("
        SELECT a.title, a.due_date, c.course_name, c.course_code
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        JOIN enrollments e ON (e.course_id = c.id AND e.student_id = ?)
        WHERE a.due_date > NOW() AND e.status = 'enrolled'
        ORDER BY a.due_date ASC
        LIMIT 5
    ");
    $stmt->execute([$studentInfo['id']]);
    $upcomingAssignments = $stmt->fetchAll();
    
} elseif ($userType === 'teacher') {
    // Get teacher info
    $stmt = $pdo->prepare("
        SELECT t.*, u.email 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.user_id = ?
    ");
    $stmt->execute([$userId]);
    $teacherInfo = $stmt->fetch();
    
    // Get teaching courses
    $stmt = $pdo->prepare("
        SELECT c.*, COUNT(e.id) as enrolled_students
        FROM courses c
        LEFT JOIN enrollments e ON (c.id = e.course_id AND e.status = 'enrolled')
        WHERE c.teacher_id = ?
        GROUP BY c.id
        ORDER BY c.course_name
    ");
    $stmt->execute([$teacherInfo['id']]);
    $courses = $stmt->fetchAll();
}

// Get recent announcements
$stmt = $pdo->prepare("
    SELECT a.*, u.username as author_name
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    WHERE (a.target_audience = 'all' OR a.target_audience = ?) 
    AND (a.expires_at IS NULL OR a.expires_at > NOW())
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute([$userType === 'student' ? 'students' : 'teachers']);
$announcements = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
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
        
        .stats-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .stats-card.warning {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        
        .stats-card.info {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
        }
        
        .grade-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
        
        .assignment-item {
            border-left: 4px solid var(--primary-color);
            background: rgba(102, 126, 234, 0.05);
            margin-bottom: 0.5rem;
            padding: 1rem;
            border-radius: 0 10px 10px 0;
        }
        
        .announcement-item {
            border-left: 4px solid #28a745;
            background: rgba(40, 167, 69, 0.05);
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 0 10px 10px 0;
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
                            <a href="dashboard.php" class="nav-link active">
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
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        <span class="text-muted fs-6">Welcome back, <?php echo ucfirst($userType); ?>!</span>
                    </h1>
                </div>

                <?php if ($userType === 'student'): ?>
                <!-- Student Dashboard -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h4><i class="fas fa-book me-2"></i><?php echo count($courses); ?></h4>
                            <p class="mb-0">Enrolled Courses</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card info">
                            <h4><i class="fas fa-star me-2"></i><?php echo $studentInfo['class_rank']; ?></h4>
                            <p class="mb-0">Class Rank</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card warning">
                            <h4><i class="fas fa-clock me-2"></i><?php echo count($upcomingAssignments); ?></h4>
                            <p class="mb-0">Due Soon</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h4><i class="fas fa-calendar me-2"></i><?php echo count($recentGrades); ?></h4>
                            <p class="mb-0">Recent Grades</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Enrolled Courses -->
                    <div class="col-md-6 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-book me-2"></i>My Courses</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($courses)): ?>
                                    <p class="text-muted">No courses enrolled.</p>
                                <?php else: ?>
                                    <?php foreach ($courses as $course): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 border-bottom">
                                        <div>
                                            <h6 class="mb-1"><?php echo $course['course_name']; ?></h6>
                                            <small class="text-muted">
                                                <?php echo $course['course_code']; ?> • 
                                                <?php echo $course['teacher_first'] . ' ' . $course['teacher_last']; ?>
                                            </small>
                                        </div>
                                        <?php if ($course['letter_grade']): ?>
                                            <span class="badge grade-badge bg-primary"><?php echo $course['letter_grade']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Grades -->
                    <div class="col-md-6 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Recent Grades</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentGrades)): ?>
                                    <p class="text-muted">No grades available.</p>
                                <?php else: ?>
                                    <?php foreach ($recentGrades as $grade): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h6 class="mb-1"><?php echo $grade['title']; ?></h6>
                                            <small class="text-muted"><?php echo $grade['course_name']; ?></small>
                                        </div>
                                        <div class="text-end">
                                            <strong><?php echo $grade['points_earned'] . '/' . $grade['max_points']; ?></strong><br>
                                            <small class="text-muted"><?php echo formatDate($grade['graded_at']); ?></small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Assignments -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Upcoming Assignments</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcomingAssignments)): ?>
                                    <p class="text-muted">No upcoming assignments.</p>
                                <?php else: ?>
                                    <?php foreach ($upcomingAssignments as $assignment): ?>
                                    <div class="assignment-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo $assignment['title']; ?></h6>
                                                <small class="text-muted"><?php echo $assignment['course_name']; ?></small>
                                            </div>
                                            <div class="text-end">
                                                <strong>Due: <?php echo formatDateTime($assignment['due_date']); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php elseif ($userType === 'teacher'): ?>
                <!-- Teacher Dashboard -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <h4><i class="fas fa-chalkboard me-2"></i><?php echo count($courses); ?></h4>
                            <p class="mb-0">Teaching Courses</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card info">
                            <h4><i class="fas fa-users me-2"></i><?php echo array_sum(array_column($courses, 'enrolled_students')); ?></h4>
                            <p class="mb-0">Total Students</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card warning">
                            <h4><i class="fas fa-building me-2"></i><?php echo $teacherInfo['department']; ?></h4>
                            <p class="mb-0">Department</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Teaching Courses -->
                    <div class="col-12 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chalkboard me-2"></i>My Courses</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($courses)): ?>
                                    <p class="text-muted">No courses assigned.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Course Code</th>
                                                    <th>Course Name</th>
                                                    <th>Credits</th>
                                                    <th>Enrolled Students</th>
                                                    <th>Semester</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($courses as $course): ?>
                                                <tr>
                                                    <td><strong><?php echo $course['course_code']; ?></strong></td>
                                                    <td><?php echo $course['course_name']; ?></td>
                                                    <td><?php echo $course['credits']; ?></td>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo $course['enrolled_students']; ?>/<?php echo $course['max_students']; ?></span>
                                                    </td>
                                                    <td><?php echo $course['semester'] . ' ' . $course['year']; ?></td>
                                                    <td>
                                                        <a href="course-details.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php else: ?>
                <!-- Admin Dashboard -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h4><i class="fas fa-users me-2"></i>Users</h4>
                            <p class="mb-0">Manage System Users</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card info">
                            <h4><i class="fas fa-school me-2"></i>Courses</h4>
                            <p class="mb-0">Manage Courses</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card warning">
                            <h4><i class="fas fa-chart-line me-2"></i>Reports</h4>
                            <p class="mb-0">View Analytics</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h4><i class="fas fa-cog me-2"></i>Settings</h4>
                            <p class="mb-0">System Configuration</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Announcements (For all users) -->
                <div class="row">
                    <div class="col-12">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-bullhorn me-2"></i>Recent Announcements</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($announcements)): ?>
                                    <p class="text-muted">No announcements available.</p>
                                <?php else: ?>
                                    <?php foreach ($announcements as $announcement): ?>
                                    <div class="announcement-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-1"><?php echo $announcement['title']; ?></h6>
                                            <small class="text-muted"><?php echo formatDateTime($announcement['created_at']); ?></small>
                                        </div>
                                        <p class="mb-2"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i>Posted by <?php echo $announcement['author_name']; ?>
                                        </small>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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