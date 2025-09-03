<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$userType = getUserType();
$userId = $_SESSION['user_id'];

// Only allow students to access this page
if ($userType !== 'student') {
    header('Location: dashboard.php');
    exit;
}

// Get student info
$stmt = $pdo->prepare("
    SELECT s.*, u.email 
    FROM students s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.user_id = ?
");
$stmt->execute([$userId]);
$studentInfo = $stmt->fetch();

if (!$studentInfo) {
    header('Location: dashboard.php');
    exit;
}

// Get filter parameters
$selectedCourse = $_GET['course_id'] ?? '';
$selectedMonth = $_GET['month'] ?? date('Y-m');

// Get enrolled courses for filter dropdown
$stmt = $pdo->prepare("
    SELECT c.id, c.course_code, c.course_name
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.student_id = ? AND e.status = 'enrolled'
    ORDER BY c.course_name
");
$stmt->execute([$studentInfo['id']]);
$enrolledCourses = $stmt->fetchAll();

// Build attendance query with filters
$whereClause = "WHERE a.student_id = ?";
$params = [$studentInfo['id']];

if ($selectedCourse) {
    $whereClause .= " AND a.course_id = ?";
    $params[] = $selectedCourse;
}

if ($selectedMonth) {
    $whereClause .= " AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?";
    $params[] = $selectedMonth;
}

// Get attendance records
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        c.course_name,
        c.course_code,
        u.username as recorded_by_name
    FROM attendance a
    JOIN courses c ON a.course_id = c.id
    LEFT JOIN users u ON a.recorded_by = u.id
    $whereClause
    ORDER BY a.attendance_date DESC, c.course_name
");
$stmt->execute($params);
$attendanceRecords = $stmt->fetchAll();

// Calculate attendance statistics
$stats = [
    'total' => 0,
    'present' => 0,
    'absent' => 0,
    'late' => 0,
    'excused' => 0
];

foreach ($attendanceRecords as $record) {
    $stats['total']++;
    $stats[$record['status']]++;
}

// Calculate attendance percentage
$attendanceRate = $stats['total'] > 0 ? 
    round((($stats['present'] + $stats['excused']) / $stats['total']) * 100, 1) : 0;

// Get attendance summary by course
$courseStatsQuery = "
    SELECT 
        c.course_name,
        c.course_code,
        COUNT(*) as total_records,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
        SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_count
    FROM attendance a
    JOIN courses c ON a.course_id = c.id
    WHERE a.student_id = ?
";

$courseStatsParams = [$studentInfo['id']];

if ($selectedMonth) {
    $courseStatsQuery .= " AND DATE_FORMAT(a.attendance_date, '%Y-%m') = ?";
    $courseStatsParams[] = $selectedMonth;
}

$courseStatsQuery .= " GROUP BY c.id, c.course_name, c.course_code ORDER BY c.course_name";

$stmt = $pdo->prepare($courseStatsQuery);
$stmt->execute($courseStatsParams);
$courseStats = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - <?php echo APP_NAME; ?></title>
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
        
        .stats-card.danger {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
        }
        
        .stats-card.info {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
        }
        
        .attendance-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }
        
        .badge-present {
            background-color: #28a745;
        }
        
        .badge-absent {
            background-color: #dc3545;
        }
        
        .badge-late {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-excused {
            background-color: #6c757d;
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
        
        .filter-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .progress-custom {
            height: 25px;
            border-radius: 15px;
        }
        
        .attendance-record {
            border-left: 4px solid;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0 10px 10px 0;
            background: rgba(255, 255, 255, 0.8);
        }
        
        .attendance-record.present {
            border-left-color: #28a745;
            background: rgba(40, 167, 69, 0.05);
        }
        
        .attendance-record.absent {
            border-left-color: #dc3545;
            background: rgba(220, 53, 69, 0.05);
        }
        
        .attendance-record.late {
            border-left-color: #ffc107;
            background: rgba(255, 193, 7, 0.05);
        }
        
        .attendance-record.excused {
            border-left-color: #6c757d;
            background: rgba(108, 117, 125, 0.05);
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
                            <a href="assignments.php" class="nav-link">
                                <i class="fas fa-tasks me-2"></i>Assignments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="attendance.php" class="nav-link active">
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
                        <i class="fas fa-calendar-check me-2"></i>Attendance
                        <span class="text-muted fs-6">Track your class attendance</span>
                    </h1>
                </div>

                <!-- Filters -->
                <div class="filter-card">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="course_id" class="form-label">
                                <i class="fas fa-book me-1"></i>Course
                            </label>
                            <select class="form-select" id="course_id" name="course_id">
                                <option value="">All Courses</option>
                                <?php foreach ($enrolledCourses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" 
                                        <?php echo $selectedCourse == $course['id'] ? 'selected' : ''; ?>>
                                    <?php echo $course['course_code'] . ' - ' . $course['course_name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="month" class="form-label">
                                <i class="fas fa-calendar me-1"></i>Month
                            </label>
                            <input type="month" class="form-control" id="month" name="month" 
                                   value="<?php echo $selectedMonth; ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i>Filter
                            </button>
                            <a href="attendance.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Attendance Statistics -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="stats-card">
                            <h4><i class="fas fa-calendar me-2"></i><?php echo $stats['total']; ?></h4>
                            <p class="mb-0">Total Records</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card">
                            <h4><i class="fas fa-check me-2"></i><?php echo $stats['present']; ?></h4>
                            <p class="mb-0">Present</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card danger">
                            <h4><i class="fas fa-times me-2"></i><?php echo $stats['absent']; ?></h4>
                            <p class="mb-0">Absent</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card warning">
                            <h4><i class="fas fa-clock me-2"></i><?php echo $stats['late']; ?></h4>
                            <p class="mb-0">Late</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card info">
                            <h4><i class="fas fa-user-check me-2"></i><?php echo $stats['excused']; ?></h4>
                            <p class="mb-0">Excused</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card">
                            <h4><i class="fas fa-percentage me-2"></i><?php echo $attendanceRate; ?>%</h4>
                            <p class="mb-0">Attendance Rate</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Course-wise Attendance Summary -->
                    <?php if (!$selectedCourse && !empty($courseStats)): ?>
                    <div class="col-12 mb-4">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Attendance by Course</h5>
                            </div>
                            <div class="card-body">
                                <?php foreach ($courseStats as $courseStat): ?>
                                    <?php 
                                    $courseAttendanceRate = $courseStat['total_records'] > 0 ? 
                                        round((($courseStat['present_count'] + $courseStat['excused_count']) / $courseStat['total_records']) * 100, 1) : 0;
                                    ?>
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0"><?php echo $courseStat['course_code'] . ' - ' . $courseStat['course_name']; ?></h6>
                                        <span class="badge bg-primary"><?php echo $courseAttendanceRate; ?>%</span>
                                    </div>
                                    <div class="progress progress-custom mb-2">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?php echo $courseAttendanceRate; ?>%">
                                        </div>
                                    </div>
                                    <div class="row text-center">
                                        <div class="col">
                                            <small class="text-success"><i class="fas fa-check"></i> <?php echo $courseStat['present_count']; ?></small>
                                        </div>
                                        <div class="col">
                                            <small class="text-danger"><i class="fas fa-times"></i> <?php echo $courseStat['absent_count']; ?></small>
                                        </div>
                                        <div class="col">
                                            <small class="text-warning"><i class="fas fa-clock"></i> <?php echo $courseStat['late_count']; ?></small>
                                        </div>
                                        <div class="col">
                                            <small class="text-info"><i class="fas fa-user-check"></i> <?php echo $courseStat['excused_count']; ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Attendance Records -->
                    <div class="col-12">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Attendance Records</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($attendanceRecords)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No attendance records found</h5>
                                        <p class="text-muted">Try adjusting your filters to see more records.</p>
                                    </div>
                                <?php else: ?>
                                    <?php 
                                    $currentDate = '';
                                    foreach ($attendanceRecords as $record): 
                                        $recordDate = date('Y-m-d', strtotime($record['attendance_date']));
                                        if ($recordDate !== $currentDate):
                                            $currentDate = $recordDate;
                                    ?>
                                    <h6 class="mt-4 mb-3 text-muted">
                                        <i class="fas fa-calendar me-2"></i><?php echo formatDate($record['attendance_date']); ?>
                                    </h6>
                                    <?php endif; ?>
                                    
                                    <div class="attendance-record <?php echo $record['status']; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-2">
                                                    <?php echo $record['course_code']; ?> - <?php echo $record['course_name']; ?>
                                                </h6>
                                                <?php if ($record['notes']): ?>
                                                <p class="mb-2 small text-muted">
                                                    <i class="fas fa-sticky-note me-1"></i><?php echo htmlspecialchars($record['notes']); ?>
                                                </p>
                                                <?php endif; ?>
                                                <?php if ($record['recorded_by_name']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>Recorded by <?php echo $record['recorded_by_name']; ?>
                                                </small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end">
                                                <span class="attendance-badge badge badge-<?php echo $record['status']; ?>">
                                                    <?php 
                                                    $statusIcons = [
                                                        'present' => 'fas fa-check',
                                                        'absent' => 'fas fa-times',
                                                        'late' => 'fas fa-clock',
                                                        'excused' => 'fas fa-user-check'
                                                    ];
                                                    ?>
                                                    <i class="<?php echo $statusIcons[$record['status']]; ?> me-1"></i>
                                                    <?php echo ucfirst($record['status']); ?>
                                                </span>
                                            </div>
                                        </div>
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