<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$userType = getUserType();
$userId = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create' && ($userType === 'teacher' || $userType === 'admin')) {
            $title = $_POST['title'];
            $content = $_POST['content'];
            $target_audience = $_POST['target_audience'];
            $priority = $_POST['priority'];
            $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
            $course_id = !empty($_POST['course_id']) ? $_POST['course_id'] : null;
            
            $stmt = $pdo->prepare("
                INSERT INTO announcements (title, content, author_id, target_audience, course_id, priority, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$title, $content, $userId, $target_audience, $course_id, $priority, $expires_at])) {
                $success = "Announcement created successfully!";
            } else {
                $error = "Error creating announcement.";
            }
        }
        
        if ($_POST['action'] === 'delete' && ($userType === 'teacher' || $userType === 'admin')) {
            $announcement_id = $_POST['announcement_id'];
            
            // Check if user owns the announcement or is admin
            $stmt = $pdo->prepare("SELECT author_id FROM announcements WHERE id = ?");
            $stmt->execute([$announcement_id]);
            $announcement = $stmt->fetch();
            
            if ($announcement && ($announcement['author_id'] == $userId || $userType === 'admin')) {
                $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
                if ($stmt->execute([$announcement_id])) {
                    $success = "Announcement deleted successfully!";
                } else {
                    $error = "Error deleting announcement.";
                }
            } else {
                $error = "You don't have permission to delete this announcement.";
            }
        }
    }
}

// Get user's courses for teachers
$userCourses = [];
if ($userType === 'teacher') {
    $stmt = $pdo->prepare("
        SELECT t.id as teacher_id FROM teachers t 
        WHERE t.user_id = ?
    ");
    $stmt->execute([$userId]);
    $teacherInfo = $stmt->fetch();
    
    if ($teacherInfo) {
        $stmt = $pdo->prepare("
            SELECT id, course_code, course_name 
            FROM courses 
            WHERE teacher_id = ? AND status = 'active'
            ORDER BY course_name
        ");
        $stmt->execute([$teacherInfo['teacher_id']]);
        $userCourses = $stmt->fetchAll();
    }
}

// Get announcements based on user type
if ($userType === 'student') {
    $stmt = $pdo->prepare("
        SELECT a.*, u.username as author_name, c.course_name, c.course_code
        FROM announcements a
        JOIN users u ON a.author_id = u.id
        LEFT JOIN courses c ON a.course_id = c.id
        WHERE (a.target_audience IN ('all', 'students') OR 
               (a.course_id IN (
                   SELECT e.course_id FROM enrollments e 
                   JOIN students s ON e.student_id = s.id 
                   WHERE s.user_id = ? AND e.status = 'enrolled'
               )))
        AND (a.expires_at IS NULL OR a.expires_at > NOW())
        ORDER BY a.priority DESC, a.created_at DESC
    ");
    $stmt->execute([$userId]);
} else {
    $stmt = $pdo->prepare("
        SELECT a.*, u.username as author_name, c.course_name, c.course_code
        FROM announcements a
        JOIN users u ON a.author_id = u.id
        LEFT JOIN courses c ON a.course_id = c.id
        WHERE (a.target_audience IN ('all', 'teachers') OR a.target_audience = ?)
        AND (a.expires_at IS NULL OR a.expires_at > NOW())
        ORDER BY a.priority DESC, a.created_at DESC
    ");
    $stmt->execute([$userType]);
}

$announcements = $stmt->fetchAll();

// Get priority badge class
function getPriorityBadgeClass($priority) {
    switch ($priority) {
        case 'high': return 'bg-danger';
        case 'medium': return 'bg-warning';
        case 'low': return 'bg-secondary';
        default: return 'bg-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - <?php echo APP_NAME; ?></title>
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
        }
        
        .dashboard-card:hover {
            transform: translateY(-2px);
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
        
        .announcement-card {
            border-left: 4px solid;
            margin-bottom: 1rem;
            transition: transform 0.2s ease;
        }
        
        .announcement-card:hover {
            transform: translateX(5px);
        }
        
        .announcement-card.priority-high {
            border-left-color: #dc3545;
        }
        
        .announcement-card.priority-medium {
            border-left-color: #ffc107;
        }
        
        .announcement-card.priority-low {
            border-left-color: #6c757d;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            color: white;
        }
        
        .btn-gradient:hover {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            color: white;
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
                            <a href="announcements.php" class="nav-link active">
                                <i class="fas fa-bullhorn me-2"></i>Announcements
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="pt-3 pb-2 mb-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1 class="h2">
                            <i class="fas fa-bullhorn me-2"></i>Announcements
                        </h1>
                        <?php if ($userType === 'teacher' || $userType === 'admin'): ?>
                        <button class="btn btn-gradient" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                            <i class="fas fa-plus me-2"></i>Create Announcement
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Announcements List -->
                <div class="row">
                    <div class="col-12">
                        <?php if (empty($announcements)): ?>
                            <div class="card dashboard-card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-bullhorn text-muted mb-3" style="font-size: 3rem;"></i>
                                    <h5 class="text-muted">No announcements available</h5>
                                    <p class="text-muted">Check back later for updates.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($announcements as $announcement): ?>
                            <div class="card dashboard-card announcement-card priority-<?php echo $announcement['priority']; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-2">
                                                <h5 class="card-title mb-0 me-3"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                                <span class="badge <?php echo getPriorityBadgeClass($announcement['priority']); ?> me-2">
                                                    <?php echo ucfirst($announcement['priority']); ?>
                                                </span>
                                                <?php if ($announcement['course_name']): ?>
                                                    <span class="badge bg-info">
                                                        <?php echo $announcement['course_code']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                        </div>
                                        <?php if (($userType === 'teacher' || $userType === 'admin') && $announcement['author_id'] == $userId): ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                        <button type="submit" class="dropdown-item text-danger" 
                                                                onclick="return confirm('Are you sure you want to delete this announcement?')">
                                                            <i class="fas fa-trash me-2"></i>Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>Posted by <?php echo $announcement['author_name']; ?>
                                                <?php if ($announcement['course_name']): ?>
                                                    in <?php echo $announcement['course_name']; ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i><?php echo formatDateTime($announcement['created_at']); ?>
                                                <?php if ($announcement['expires_at']): ?>
                                                    <br><i class="fas fa-hourglass-end me-1"></i>Expires: <?php echo formatDateTime($announcement['expires_at']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Announcement Modal -->
    <?php if ($userType === 'teacher' || $userType === 'admin'): ?>
    <div class="modal fade" id="createAnnouncementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="content" class="form-label">Content</label>
                            <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <label for="target_audience" class="form-label">Target Audience</label>
                                <select class="form-select" id="target_audience" name="target_audience" required>
                                    <?php if ($userType === 'admin'): ?>
                                    <option value="all">Everyone</option>
                                    <option value="students">Students Only</option>
                                    <option value="teachers">Teachers Only</option>
                                    <?php else: ?>
                                    <option value="students">Students</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                        
                        <?php if (!empty($userCourses)): ?>
                        <div class="mb-3 mt-3">
                            <label for="course_id" class="form-label">Course (Optional)</label>
                            <select class="form-select" id="course_id" name="course_id">
                                <option value="">General Announcement</option>
                                <?php foreach ($userCourses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo $course['course_code'] . ' - ' . $course['course_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="expires_at" class="form-label">Expiration Date (Optional)</label>
                            <input type="datetime-local" class="form-control" id="expires_at" name="expires_at">
                            <div class="form-text">Leave empty for announcements that don't expire</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-gradient">Create Announcement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>