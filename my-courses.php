<?php
require_once __DIR__ .'/../config.php';
requireLogin();

// Ensure only teachers can access this page
if (getUserType() !== 'teacher') {
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Get teacher info
$stmt = $pdo->prepare("
    SELECT t.* FROM teachers t 
    WHERE t.user_id = ?
");
$stmt->execute([$userId]);
$teacherInfo = $stmt->fetch();

if (!$teacherInfo) {
    die('Teacher information not found.');
}

// Get teacher's courses with enrollment statistics
$stmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(CASE WHEN e.status = 'enrolled' THEN 1 END) as enrolled_students,
           COUNT(CASE WHEN e.status = 'completed' THEN 1 END) as completed_students,
           COUNT(CASE WHEN e.status = 'dropped' THEN 1 END) as dropped_students,
           AVG(CASE WHEN e.final_grade IS NOT NULL THEN e.final_grade END) as average_grade
    FROM courses c
    LEFT JOIN enrollments e ON c.id = e.course_id
    WHERE c.teacher_id = ?
    GROUP BY c.id
    ORDER BY c.year DESC, c.semester DESC, c.course_name
");
$stmt->execute([$teacherInfo['id']]);
$courses = $stmt->fetchAll();

// Get total assignment count for each course
$courseIds = array_column($courses, 'id');
$assignmentCounts = [];
if (!empty($courseIds)) {
    $placeholders = str_repeat('?,', count($courseIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT course_id, COUNT(*) as assignment_count
        FROM assignments 
        WHERE course_id IN ($placeholders)
        GROUP BY course_id
    ");
    $stmt->execute($courseIds);
    $assignmentCounts = array_column($stmt->fetchAll(), 'assignment_count', 'course_id');
}
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
        
        .stats-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            display: inline-block;
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
                            <a href="my-courses.php" class="nav-link active">
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
                        <i class="fas fa-chalkboard me-2"></i>My Courses
                        <span class="text-muted fs-6"><?php echo count($courses); ?> course(s)</span>
                    </h1>
                </div>

                <?php if (empty($courses)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chalkboard fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">No courses assigned</h4>
                        <p class="text-muted">Contact your administrator to get courses assigned to you.</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($courses as $course): ?>
                            <div class="col-lg-6 col-xl-4 mb-4">
                                <div class="card course-card">
                                    <div class="course-header">
                                        <h5 class="mb-1"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                                        <p class="mb-2 opacity-75"><?php echo htmlspecialchars($course['course_code']); ?></p>
                                        <div>
                                            <span class="stats-badge">
                                                <i class="fas fa-users me-1"></i><?php echo $course['enrolled_students']; ?> Students
                                            </span>
                                            <span class="stats-badge">
                                                <i class="fas fa-credit-card me-1"></i><?php echo $course['credits']; ?> Credits
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text text-muted mb-3">
                                            <?php echo $course['description'] ? htmlspecialchars(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '...' : '') : 'No description available.'; ?>
                                        </p>
                                        
                                        <div class="row text-center mb-3">
                                            <div class="col-4">
                                                <strong class="text-primary"><?php echo $course['enrolled_students']; ?></strong>
                                                <br><small class="text-muted">Enrolled</small>
                                            </div>
                                            <div class="col-4">
                                                <strong class="text-success"><?php echo $course['completed_students']; ?></strong>
                                                <br><small class="text-muted">Completed</small>
                                            </div>
                                            <div class="col-4">
                                                <strong class="text-info"><?php echo isset($assignmentCounts[$course['id']]) ? $assignmentCounts[$course['id']] : 0; ?></strong>
                                                <br><small class="text-muted">Assignments</small>
                                            </div>
                                        </div>

                                        <?php if ($course['average_grade']): ?>
                                            <div class="mb-3">
                                                <small class="text-muted">Class Average:</small>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $course['average_grade']; ?>%"
                                                         aria-valuenow="<?php echo $course['average_grade']; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <small class="text-muted"><?php echo number_format($course['average_grade'], 1); ?>%</small>
                                            </div>
                                        <?php endif; ?>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <?php echo $course['semester'] . ' ' . $course['year']; ?>
                                            </small>
                                            <div class="btn-group" role="group">
                                                <a href="course-details.php?id=<?php echo $course['id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="gradebook.php?course_id=<?php echo $course['id']; ?>" 
                                                   class="btn btn-outline-success btn-sm">
                                                    <i class="fas fa-clipboard-list"></i> Grades
                                                </a>
                                                <a href="manage-assignments.php?course_id=<?php echo $course['id']; ?>" 
                                                   class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-tasks"></i> Assignments
                                                </a>
                                            </div>
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