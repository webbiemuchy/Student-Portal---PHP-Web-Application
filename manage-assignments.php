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
$stmt = $pdo->prepare("SELECT t.* FROM teachers t WHERE t.user_id = ?");
$stmt->execute([$userId]);
$teacherInfo = $stmt->fetch();

if (!$teacherInfo) {
    die('Teacher information not found.');
}

// Handle assignment creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
    $courseId = $_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $dueDate = $_POST['due_date'];
    $maxPoints = $_POST['max_points'];
    $assignmentType = $_POST['assignment_type'];
    
    // Verify the course belongs to this teacher
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$courseId, $teacherInfo['id']]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("
            INSERT INTO assignments (course_id, title, description, due_date, max_points, assignment_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$courseId, $title, $description, $dueDate, $maxPoints, $assignmentType])) {
            $newAssignmentId = $pdo->lastInsertId();
            
            // Create grade records for all enrolled students
            $stmt = $pdo->prepare("
                INSERT INTO grades (student_id, assignment_id)
                SELECT e.student_id, ?
                FROM enrollments e
                WHERE e.course_id = ? AND e.status = 'enrolled'
            ");
            $stmt->execute([$newAssignmentId, $courseId]);
            
            $success = "Assignment created successfully!";
        } else {
            $error = "Failed to create assignment.";
        }
    } else {
        $error = "Invalid course selection.";
    }
}

// Handle assignment deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assignment'])) {
    $assignmentId = $_POST['assignment_id'];
    
    // Verify the assignment belongs to teacher's course
    $stmt = $pdo->prepare("
        SELECT a.* FROM assignments a
        JOIN courses c ON a.course_id = c.id
        WHERE a.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$assignmentId, $teacherInfo['id']]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
        if ($stmt->execute([$assignmentId])) {
            $success = "Assignment deleted successfully!";
        } else {
            $error = "Failed to delete assignment.";
        }
    } else {
        $error = "Invalid assignment.";
    }
}

// Get selected course
$selectedCourseId = $_GET['course_id'] ?? null;

// Get teacher's courses
$stmt = $pdo->prepare("
    SELECT c.* FROM courses c 
    WHERE c.teacher_id = ? 
    ORDER BY c.year DESC, c.semester DESC, c.course_name
");
$stmt->execute([$teacherInfo['id']]);
$courses = $stmt->fetchAll();

$assignments = [];
$selectedCourse = null;

if ($selectedCourseId) {
    // Verify the course belongs to this teacher
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$selectedCourseId, $teacherInfo['id']]);
    $selectedCourse = $stmt->fetch();
    
    if ($selectedCourse) {
        // Get course assignments with submission statistics
        $stmt = $pdo->prepare("
            SELECT a.*,
                   COUNT(g.id) as total_students,
                   COUNT(CASE WHEN g.points_earned IS NOT NULL THEN 1 END) as graded_count,
                   AVG(CASE WHEN g.points_earned IS NOT NULL THEN g.points_earned END) as avg_score
            FROM assignments a
            LEFT JOIN grades g ON a.id = g.assignment_id
            WHERE a.course_id = ?
            GROUP BY a.id
            ORDER BY a.due_date DESC
        ");
        $stmt->execute([$selectedCourseId]);
        $assignments = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assignments - <?php echo APP_NAME; ?></title>
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
        
        .assignment-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        .assignment-card:hover {
            transform: translateY(-2px);
        }
        
        .assignment-type-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .due-date {
            color: #dc3545;
            font-weight: 600;
        }
        
        .due-date.past-due {
            background-color: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
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
                            <a href="manage-assignments.php" class="nav-link active">
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
                <div class="pt-3 pb-2 mb-3 border-bottom d-flex justify-content-between align-items-center">
                    <h1 class="h2">
                        <i class="fas fa-plus-square me-2"></i>Manage Assignments
                    </h1>
                    
                    <div class="d-flex gap-3 align-items-center">
                        <!-- Course Selection -->
                        <select id="courseSelect" class="form-select" onchange="location.href='manage-assignments.php?course_id=' + this.value" style="min-width: 250px;">
                            <option value="">Select a Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" <?php echo ($selectedCourseId == $course['id']) ? 'selected' : ''; ?>>
                                    <?php echo $course['course_code'] . ' - ' . $course['course_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php if ($selectedCourse): ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAssignmentModal">
                                <i class="fas fa-plus me-2"></i>New Assignment
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!$selectedCourseId): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-tasks fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">Select a Course</h4>
                        <p class="text-muted">Choose a course from the dropdown above to manage assignments.</p>
                    </div>
                <?php elseif (!$selectedCourse): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Course not found or you don't have access to this course.
                    </div>
                <?php else: ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0">
                                        <?php echo $selectedCourse['course_code'] . ' - ' . $selectedCourse['course_name']; ?>
                                        <span class="badge bg-light text-dark ms-2"><?php echo count($assignments); ?> assignments</span>
                                    </h5>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($assignments)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">No Assignments Created</h4>
                            <p class="text-muted">Start by creating your first assignment for this course.</p>
                            <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createAssignmentModal">
                                <i class="fas fa-plus me-2"></i>Create First Assignment
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($assignments as $assignment): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card assignment-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                <span class="badge assignment-type-badge <?php 
                                                    switch($assignment['assignment_type']) {
                                                        case 'quiz': echo 'bg-info'; break;
                                                        case 'exam': echo 'bg-danger'; break;
                                                        case 'project': echo 'bg-success'; break;
                                                        case 'participation': echo 'bg-secondary'; break;
                                                        default: echo 'bg-primary';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($assignment['assignment_type']); ?>
                                                </span>
                                            </div>
                                            
                                            <?php if ($assignment['description']): ?>
                                                <p class="card-text text-muted small">
                                                    <?php echo htmlspecialchars(substr($assignment['description'], 0, 100)) . (strlen($assignment['description']) > 100 ? '...' : ''); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <div class="row text-center mb-3">
                                                <div class="col-4">
                                                    <strong class="text-primary"><?php echo $assignment['max_points']; ?></strong>
                                                    <br><small class="text-muted">Points</small>
                                                </div>
                                                <div class="col-4">
                                                    <strong class="text-success"><?php echo $assignment['graded_count']; ?>/<?php echo $assignment['total_students']; ?></strong>
                                                    <br><small class="text-muted">Graded</small>
                                                </div>
                                                <div class="col-4">
                                                    <strong class="text-info">
                                                        <?php echo $assignment['avg_score'] ? number_format($assignment['avg_score'], 1) : '-'; ?>
                                                    </strong>
                                                    <br><small class="text-muted">Avg Score</small>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <small class="text-muted">Due Date:</small><br>
                                                <span class="<?php echo (strtotime($assignment['due_date']) < time()) ? 'due-date past-due' : 'due-date'; ?>">
                                                    <?php echo formatDateTime($assignment['due_date']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between">
                                                <a href="gradebook.php?course_id=<?php echo $selectedCourseId; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-clipboard-list me-1"></i>Grade
                                                </a>
                                                
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <button class="dropdown-item" onclick="editAssignment(<?php echo $assignment['id']; ?>)">
                                                                <i class="fas fa-edit me-2"></i>Edit
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <button class="dropdown-item text-danger" onclick="confirmDelete(<?php echo $assignment['id']; ?>, '<?php echo addslashes($assignment['title']); ?>')">
                                                                <i class="fas fa-trash me-2"></i>Delete
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Create Assignment Modal -->
    <div class="modal fade" id="createAssignmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Assignment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="course_id" value="<?php echo $selectedCourseId; ?>">
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="title" class="form-label">Assignment Title *</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="assignment_type" class="form-label">Type *</label>
                                <select class="form-select" name="assignment_type" required>
                                    <option value="homework">Homework</option>
                                    <option value="quiz">Quiz</option>
                                    <option value="exam">Exam</option>
                                    <option value="project">Project</option>
                                    <option value="participation">Participation</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="due_date" class="form-label">Due Date *</label>
                                <input type="datetime-local" class="form-control" name="due_date" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="max_points" class="form-label">Max Points *</label>
                                <input type="number" class="form-control" name="max_points" min="1" value="100" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4" placeholder="Assignment instructions and details..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_assignment" class="btn btn-primary">Create Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Assignment</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the assignment "<span id="deleteAssignmentTitle"></span>"?</p>
                        <p class="text-danger"><strong>Warning:</strong> This will also delete all associated grades and cannot be undone.</p>
                        <input type="hidden" name="assignment_id" id="deleteAssignmentId">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_assignment" class="btn btn-danger">Delete Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(assignmentId, assignmentTitle) {
            document.getElementById('deleteAssignmentId').value = assignmentId;
            document.getElementById('deleteAssignmentTitle').textContent = assignmentTitle;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        function editAssignment(assignmentId) {
            // This would typically open an edit modal or redirect to an edit page
            // For now, we'll just show an alert
            alert('Edit functionality would be implemented here for assignment ID: ' + assignmentId);
        }
    </script>
</body>
</html>