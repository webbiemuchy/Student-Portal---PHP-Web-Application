<?php
require_once __DIR__ . '/../config.php';
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

if (!$studentInfo) {
    header('Location: dashboard.php');
    exit;
}

// Get filter parameters
$courseFilter = $_GET['course'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';

// Build the WHERE clause for filters
$whereConditions = ["e.student_id = ?"];
$params = [$studentInfo['id']];

if ($courseFilter) {
    $whereConditions[] = "c.id = ?";
    $params[] = $courseFilter;
}

if ($typeFilter) {
    $whereConditions[] = "a.assignment_type = ?";
    $params[] = $typeFilter;
}

// Status filter logic
if ($statusFilter === 'submitted') {
    $whereConditions[] = "g.submitted_at IS NOT NULL";
} elseif ($statusFilter === 'graded') {
    $whereConditions[] = "g.points_earned IS NOT NULL";
} elseif ($statusFilter === 'pending') {
    $whereConditions[] = "g.submitted_at IS NULL";
} elseif ($statusFilter === 'overdue') {
    $whereConditions[] = "a.due_date < NOW() AND g.submitted_at IS NULL";
}

$whereClause = implode(' AND ', $whereConditions);

// Get assignments with grades and submission info
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        c.course_name,
        c.course_code,
        g.points_earned,
        g.submitted_at,
        g.graded_at,
        g.feedback,
        CASE 
            WHEN g.submitted_at IS NOT NULL AND g.points_earned IS NOT NULL THEN 'graded'
            WHEN g.submitted_at IS NOT NULL THEN 'submitted'
            WHEN a.due_date < NOW() THEN 'overdue'
            ELSE 'pending'
        END as status
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    JOIN enrollments e ON (e.course_id = c.id AND e.status = 'enrolled')
    LEFT JOIN grades g ON (g.assignment_id = a.id AND g.student_id = e.student_id)
    WHERE $whereClause
    ORDER BY a.due_date ASC
");
$stmt->execute($params);
$assignments = $stmt->fetchAll();

// Get courses for filter dropdown
$stmt = $pdo->prepare("
    SELECT c.id, c.course_name, c.course_code
    FROM courses c
    JOIN enrollments e ON (e.course_id = c.id AND e.student_id = ? AND e.status = 'enrolled')
    ORDER BY c.course_name
");
$stmt->execute([$studentInfo['id']]);
$courses = $stmt->fetchAll();

// Calculate statistics
$totalAssignments = count($assignments);
$submittedCount = count(array_filter($assignments, fn($a) => $a['submitted_at'] !== null));
$gradedCount = count(array_filter($assignments, fn($a) => $a['points_earned'] !== null));
$overdueCount = count(array_filter($assignments, fn($a) => $a['status'] === 'overdue'));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments - <?php echo APP_NAME; ?></title>
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
        
        .stats-card.danger {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
        }
        
        .assignment-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }
        
        .assignment-card:hover {
            transform: translateY(-3px);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        
        .assignment-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 1rem;
        }
        
        .due-date {
            font-size: 0.9rem;
        }
        
        .overdue {
            color: #dc3545;
            font-weight: bold;
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
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
                            <a href="grades.php" class="nav-link">
                                <i class="fas fa-chart-bar me-2"></i>Grades
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="assignments.php" class="nav-link active">
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
                <div class="pt-3 pb-2 mb-3">
                    <h1 class="h2">
                        <i class="fas fa-tasks me-2"></i>My Assignments
                    </h1>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h4><i class="fas fa-clipboard-list me-2"></i><?php echo $totalAssignments; ?></h4>
                            <p class="mb-0">Total Assignments</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card info">
                            <h4><i class="fas fa-check me-2"></i><?php echo $submittedCount; ?></h4>
                            <p class="mb-0">Submitted</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card warning">
                            <h4><i class="fas fa-star me-2"></i><?php echo $gradedCount; ?></h4>
                            <p class="mb-0">Graded</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card danger">
                            <h4><i class="fas fa-exclamation-triangle me-2"></i><?php echo $overdueCount; ?></h4>
                            <p class="mb-0">Overdue</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-card p-3">
                    <form method="GET" action="assignments.php">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="course" class="form-label">Filter by Course</label>
                                <select class="form-select" id="course" name="course">
                                    <option value="">All Courses</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" <?php echo $courseFilter == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo $course['course_code'] . ' - ' . $course['course_name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="type" class="form-label">Assignment Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Types</option>
                                    <option value="homework" <?php echo $typeFilter === 'homework' ? 'selected' : ''; ?>>Homework</option>
                                    <option value="quiz" <?php echo $typeFilter === 'quiz' ? 'selected' : ''; ?>>Quiz</option>
                                    <option value="exam" <?php echo $typeFilter === 'exam' ? 'selected' : ''; ?>>Exam</option>
                                    <option value="project" <?php echo $typeFilter === 'project' ? 'selected' : ''; ?>>Project</option>
                                    <option value="participation" <?php echo $typeFilter === 'participation' ? 'selected' : ''; ?>>Participation</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="submitted" <?php echo $statusFilter === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                                    <option value="graded" <?php echo $statusFilter === 'graded' ? 'selected' : ''; ?>>Graded</option>
                                    <option value="overdue" <?php echo $statusFilter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-2"></i>Apply Filters
                                    </button>
                                    <a href="assignments.php" class="btn btn-outline-secondary">Clear</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Assignments List -->
                <div class="row">
                    <?php if (empty($assignments)): ?>
                    <div class="col-12">
                        <div class="assignment-card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No assignments found</h5>
                                <p class="text-muted">Try adjusting your filters or check back later.</p>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($assignments as $assignment): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="assignment-card">
                                <div class="assignment-header">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                            <small><?php echo $assignment['course_code']; ?></small>
                                        </div>
                                        <span class="badge badge-pill bg-light text-dark">
                                            <?php echo ucfirst($assignment['assignment_type']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="card-body">
                                    <?php if ($assignment['description']): ?>
                                    <p class="card-text text-muted mb-2" style="font-size: 0.9rem;">
                                        <?php echo htmlspecialchars(substr($assignment['description'], 0, 100)) . (strlen($assignment['description']) > 100 ? '...' : ''); ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted">Course:</small><br>
                                        <strong><?php echo htmlspecialchars($assignment['course_name']); ?></strong>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted">Due Date:</small><br>
                                        <span class="due-date <?php echo $assignment['status'] === 'overdue' ? 'overdue' : ''; ?>">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo formatDateTime($assignment['due_date']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <small class="text-muted">Points:</small>
                                        <strong><?php echo $assignment['max_points']; ?></strong>
                                    </div>
                                    
                                    <!-- Status and Grade Info -->
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="status-badge badge 
                                            <?php 
                                            switch($assignment['status']) {
                                                case 'graded': echo 'bg-success'; break;
                                                case 'submitted': echo 'bg-info'; break;
                                                case 'overdue': echo 'bg-danger'; break;
                                                default: echo 'bg-warning text-dark';
                                            }
                                            ?>">
                                            <?php echo ucfirst($assignment['status']); ?>
                                        </span>
                                        
                                        <?php if ($assignment['points_earned'] !== null): ?>
                                        <div class="text-end">
                                            <strong><?php echo $assignment['points_earned'] . '/' . $assignment['max_points']; ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo number_format(($assignment['points_earned'] / $assignment['max_points']) * 100, 1); ?>%
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($assignment['feedback']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Feedback:</small>
                                        <p class="small bg-light p-2 rounded"><?php echo nl2br(htmlspecialchars($assignment['feedback'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($assignment['submitted_at']): ?>
                                    <div class="mt-2">
                                        <small class="text-success">
                                            <i class="fas fa-check me-1"></i>
                                            Submitted on <?php echo formatDateTime($assignment['submitted_at']); ?>
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>