<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$userId = $_SESSION['user_id'];

// Get statistics for reports
try {
    // Total counts
    $stmt = $pdo->query("SELECT COUNT(*) as total_students FROM students WHERE status = 'active'");
    $totalStudents = $stmt->fetch()['total_students'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_teachers FROM teachers");
    $totalTeachers = $stmt->fetch()['total_teachers'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_courses FROM courses WHERE status = 'active'");
    $totalCourses = $stmt->fetch()['total_courses'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_enrollments FROM enrollments WHERE status = 'enrolled'");
    $totalEnrollments = $stmt->fetch()['total_enrollments'];
    
    // Course enrollment statistics
    $stmt = $pdo->query("
        SELECT c.course_name, c.course_code, COUNT(e.id) as enrollment_count, c.max_students,
               ROUND((COUNT(e.id) / c.max_students) * 100, 1) as fill_percentage
        FROM courses c
        LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
        WHERE c.status = 'active'
        GROUP BY c.id, c.course_name, c.course_code, c.max_students
        ORDER BY enrollment_count DESC
    ");
    $courseEnrollments = $stmt->fetchAll();
    
    // Grade distribution
    $stmt = $pdo->query("
        SELECT letter_grade, COUNT(*) as count
        FROM enrollments 
        WHERE letter_grade IS NOT NULL AND status = 'completed'
        GROUP BY letter_grade
        ORDER BY FIELD(letter_grade, 'A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'F')
    ");
    $gradeDistribution = $stmt->fetchAll();
    
    // Top performing students (by GPA)
    $stmt = $pdo->query("
        SELECT s.student_id, s.first_name, s.last_name, s.gpa,
               COUNT(e.id) as total_courses
        FROM students s
        LEFT JOIN enrollments e ON s.id = e.student_id AND e.status IN ('enrolled', 'completed')
        WHERE s.status = 'active' AND s.gpa > 0
        GROUP BY s.id
        ORDER BY s.gpa DESC
        LIMIT 10
    ");
    $topStudents = $stmt->fetchAll();
    
    // Department statistics
    $stmt = $pdo->query("
        SELECT t.department, COUNT(*) as teacher_count,
               COUNT(DISTINCT c.id) as course_count,
               COUNT(DISTINCT e.student_id) as student_count
        FROM teachers t
        LEFT JOIN courses c ON t.id = c.teacher_id AND c.status = 'active'
        LEFT JOIN enrollments e ON c.id = e.course_id AND e.status = 'enrolled'
        GROUP BY t.department
        ORDER BY teacher_count DESC
    ");
    $departmentStats = $stmt->fetchAll();
    
    // Recent activity (last 30 days)
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM students WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_students,
            (SELECT COUNT(*) FROM enrollments WHERE enrollment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_enrollments,
            (SELECT COUNT(*) FROM grades WHERE graded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_grades,
            (SELECT COUNT(*) FROM announcements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_announcements
    ");
    $recentActivity = $stmt->fetch();
    
    // Attendance statistics
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count,
            ROUND((COUNT(*) / (SELECT COUNT(*) FROM attendance)) * 100, 1) as percentage
        FROM attendance
        WHERE attendance_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY status
        ORDER BY count DESC
    ");
    $attendanceStats = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error in reports: " . $e->getMessage());
    $error = "Error loading report data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .stats-card.students {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
        
        .stats-card.teachers {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .stats-card.courses {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        
        .stats-card.enrollments {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
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
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        .progress-custom {
            height: 20px;
            border-radius: 10px;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
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
                            <a href="reports.php" class="nav-link active">
                                <i class="fas fa-chart-line me-2"></i>Reports
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
                        <i class="fas fa-chart-line me-2"></i>Reports & Analytics
                        <span class="text-muted fs-6">System Overview</span>
                    </h1>
                </div>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                </div>
                <?php endif; ?>

                <!-- Overview Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card students">
                            <h3><i class="fas fa-user-graduate me-2"></i><?php echo $totalStudents; ?></h3>
                            <p class="mb-0">Active Students</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card teachers">
                            <h3><i class="fas fa-chalkboard-teacher me-2"></i><?php echo $totalTeachers; ?></h3>
                            <p class="mb-0">Teachers</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card courses">
                            <h3><i class="fas fa-book me-2"></i><?php echo $totalCourses; ?></h3>
                            <p class="mb-0">Active Courses</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card enrollments">
                            <h3><i class="fas fa-users me-2"></i><?php echo $totalEnrollments; ?></h3>
                            <p class="mb-0">Total Enrollments</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity (Last 30 Days) -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Activity (Last 30 Days)</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <h4 class="text-primary"><?php echo $recentActivity['new_students']; ?></h4>
                                        <small class="text-muted">New Students</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h4 class="text-success"><?php echo $recentActivity['new_enrollments']; ?></h4>
                                        <small class="text-muted">New Enrollments</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h4 class="text-warning"><?php echo $recentActivity['new_grades']; ?></h4>
                                        <small class="text-muted">Grades Entered</small>
                                    </div>
                                    <div class="col-md-3">
                                        <h4 class="text-info"><?php echo $recentActivity['new_announcements']; ?></h4>
                                        <small class="text-muted">Announcements</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <!-- Grade Distribution Chart -->
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Grade Distribution</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="gradeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Statistics -->
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Attendance Statistics (Last 30 Days)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($attendanceStats)): ?>
                                    <?php foreach ($attendanceStats as $stat): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="text-capitalize"><?php echo $stat['status']; ?></span>
                                            <span><strong><?php echo $stat['count']; ?></strong> (<?php echo $stat['percentage']; ?>%)</span>
                                        </div>
                                        <div class="progress progress-custom">
                                            <div class="progress-bar <?php 
                                                echo $stat['status'] === 'present' ? 'bg-success' : 
                                                    ($stat['status'] === 'late' ? 'bg-warning' : 
                                                    ($stat['status'] === 'excused' ? 'bg-info' : 'bg-danger')); 
                                            ?>" 
                                                role="progressbar" 
                                                style="width: <?php echo $stat['percentage']; ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">No attendance data available for the last 30 days.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course Enrollment Statistics -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Course Enrollment Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Course Code</th>
                                                <th>Course Name</th>
                                                <th>Enrolled</th>
                                                <th>Capacity</th>
                                                <th>Fill Rate</th>
                                                <th>Progress</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($courseEnrollments as $course): ?>
                                            <tr>
                                                <td><strong><?php echo $course['course_code']; ?></strong></td>
                                                <td><?php echo $course['course_name']; ?></td>
                                                <td><?php echo $course['enrollment_count']; ?></td>
                                                <td><?php echo $course['max_students']; ?></td>
                                                <td><?php echo $course['fill_percentage']; ?>%</td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar <?php 
                                                            echo $course['fill_percentage'] >= 90 ? 'bg-danger' : 
                                                                ($course['fill_percentage'] >= 75 ? 'bg-warning' : 'bg-success'); 
                                                        ?>" 
                                                            role="progressbar" 
                                                            style="width: <?php echo $course['fill_percentage']; ?>%"></div>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <!-- Top Performing Students -->
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Performing Students</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($topStudents)): ?>
                                    <?php foreach ($topStudents as $index => $student): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 <?php echo $index < 3 ? 'bg-light rounded' : ''; ?>">
                                        <div class="d-flex align-items-center">
                                            <span class="badge <?php 
                                                echo $index === 0 ? 'bg-warning' : 
                                                    ($index === 1 ? 'bg-secondary' : 
                                                    ($index === 2 ? 'bg-dark' : 'bg-light text-dark')); 
                                            ?> me-2"><?php echo $index + 1; ?></span>
                                            <div>
                                                <strong><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></strong><br>
                                                <small class="text-muted">ID: <?php echo $student['student_id']; ?></small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <strong class="text-success"><?php echo $student['gpa']; ?></strong><br>
                                            <small class="text-muted"><?php echo $student['total_courses']; ?> courses</small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">No student data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Department Statistics -->
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-building me-2"></i>Department Statistics</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($departmentStats)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Department</th>
                                                    <th>Teachers</th>
                                                    <th>Courses</th>
                                                    <th>Students</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($departmentStats as $dept): ?>
                                                <tr>
                                                    <td><strong><?php echo $dept['department'] ?: 'Unassigned'; ?></strong></td>
                                                    <td><span class="badge bg-primary"><?php echo $dept['teacher_count']; ?></span></td>
                                                    <td><span class="badge bg-success"><?php echo $dept['course_count']; ?></span></td>
                                                    <td><span class="badge bg-info"><?php echo $dept['student_count']; ?></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No department data available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Grade Distribution Chart
        const gradeData = <?php echo json_encode($gradeDistribution); ?>;
        
        if (gradeData && gradeData.length > 0) {
            const ctx = document.getElementById('gradeChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: gradeData.map(item => item.letter_grade),
                    datasets: [{
                        data: gradeData.map(item => item.count),
                        backgroundColor: [
                            '#28a745', // A+
                            '#20c997', // A
                            '#17a2b8', // A-
                            '#ffc107', // B+
                            '#fd7e14', // B
                            '#e83e8c', // B-
                            '#6f42c1', // C+
                            '#6c757d', // C
                            '#495057', // C-
                            '#dc3545', // D+
                            '#c82333', // D
                            '#721c24'  // F
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return `${context.label}: ${context.parsed} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>