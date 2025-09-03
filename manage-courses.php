<?php
require_once __DIR__ . '/../config.php';
requireLogin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_course') {
            $course_code = $_POST['course_code'];
            $course_name = $_POST['course_name'];
            $description = $_POST['description'];
            $credits = $_POST['credits'];
            $teacher_id = !empty($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
            $semester = $_POST['semester'];
            $year = $_POST['year'];
            $max_students = $_POST['max_students'];
            $status = $_POST['status'];
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO courses (course_code, course_name, description, credits, teacher_id, semester, year, max_students, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$course_code, $course_name, $description, $credits, $teacher_id, $semester, $year, $max_students, $status]);
                $success = "Course added successfully!";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Course code already exists!";
                } else {
                    $error = "Error adding course: " . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'edit_course') {
            $course_id = $_POST['course_id'];
            $course_code = $_POST['course_code'];
            $course_name = $_POST['course_name'];
            $description = $_POST['description'];
            $credits = $_POST['credits'];
            $teacher_id = !empty($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
            $semester = $_POST['semester'];
            $year = $_POST['year'];
            $max_students = $_POST['max_students'];
            $status = $_POST['status'];
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE courses 
                    SET course_code=?, course_name=?, description=?, credits=?, teacher_id=?, semester=?, year=?, max_students=?, status=?
                    WHERE id=?
                ");
                $stmt->execute([$course_code, $course_name, $description, $credits, $teacher_id, $semester, $year, $max_students, $status, $course_id]);
                $success = "Course updated successfully!";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Course code already exists!";
                } else {
                    $error = "Error updating course: " . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'delete_course') {
            $course_id = $_POST['course_id'];
            
            try {
                // Check if course has enrollments
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
                $stmt->execute([$course_id]);
                $enrollment_count = $stmt->fetchColumn();
                
                if ($enrollment_count > 0) {
                    $error = "Cannot delete course with existing enrollments!";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
                    $stmt->execute([$course_id]);
                    $success = "Course deleted successfully!";
                }
            } catch (PDOException $e) {
                $error = "Error deleting course: " . $e->getMessage();
            }
        }
    }
}

// Get all courses with teacher information
$stmt = $pdo->prepare("
    SELECT c.*, 
           t.first_name as teacher_first, 
           t.last_name as teacher_last,
           COUNT(e.id) as enrolled_students
    FROM courses c
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN enrollments e ON (c.id = e.course_id AND e.status = 'enrolled')
    GROUP BY c.id
    ORDER BY c.year DESC, c.semester, c.course_code
");
$stmt->execute();
$courses = $stmt->fetchAll();

// Get all teachers for dropdown
$stmt = $pdo->prepare("
    SELECT t.id, t.first_name, t.last_name, t.department
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.last_name, t.first_name
");
$stmt->execute();
$teachers = $stmt->fetchAll();

// Get course for editing if requested
$editCourse = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editCourse = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - <?php echo APP_NAME; ?></title>
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
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
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
                            <a href="manage-courses.php" class="nav-link active">
                                <i class="fas fa-school me-2"></i>Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="reports.php" class="nav-link">
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
                <div class="pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-school me-2"></i>Manage Courses
                    </h1>
                </div>

                <!-- Alerts -->
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

                <!-- Add/Edit Course Form -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-plus me-2"></i><?php echo $editCourse ? 'Edit Course' : 'Add New Course'; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="<?php echo $editCourse ? 'edit_course' : 'add_course'; ?>">
                                    <?php if ($editCourse): ?>
                                        <input type="hidden" name="course_id" value="<?php echo $editCourse['id']; ?>">
                                    <?php endif; ?>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="course_code" class="form-label">Course Code</label>
                                            <input type="text" class="form-control" id="course_code" name="course_code" 
                                                   value="<?php echo $editCourse ? $editCourse['course_code'] : ''; ?>" required>
                                        </div>
                                        <div class="col-md-8 mb-3">
                                            <label for="course_name" class="form-label">Course Name</label>
                                            <input type="text" class="form-control" id="course_name" name="course_name" 
                                                   value="<?php echo $editCourse ? $editCourse['course_name'] : ''; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo $editCourse ? $editCourse['description'] : ''; ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-2 mb-3">
                                            <label for="credits" class="form-label">Credits</label>
                                            <input type="number" class="form-control" id="credits" name="credits" min="1" max="6"
                                                   value="<?php echo $editCourse ? $editCourse['credits'] : '3'; ?>" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="teacher_id" class="form-label">Assigned Teacher</label>
                                            <select class="form-select" id="teacher_id" name="teacher_id">
                                                <option value="">Select Teacher (Optional)</option>
                                                <?php foreach ($teachers as $teacher): ?>
                                                    <option value="<?php echo $teacher['id']; ?>" 
                                                            <?php echo ($editCourse && $editCourse['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                                        <?php echo $teacher['first_name'] . ' ' . $teacher['last_name'] . ' (' . $teacher['department'] . ')'; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="semester" class="form-label">Semester</label>
                                            <select class="form-select" id="semester" name="semester" required>
                                                <option value="Fall" <?php echo ($editCourse && $editCourse['semester'] == 'Fall') ? 'selected' : ''; ?>>Fall</option>
                                                <option value="Spring" <?php echo ($editCourse && $editCourse['semester'] == 'Spring') ? 'selected' : ''; ?>>Spring</option>
                                                <option value="Summer" <?php echo ($editCourse && $editCourse['semester'] == 'Summer') ? 'selected' : ''; ?>>Summer</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="year" class="form-label">Year</label>
                                            <input type="number" class="form-control" id="year" name="year" min="2020" max="2030"
                                                   value="<?php echo $editCourse ? $editCourse['year'] : date('Y'); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="max_students" class="form-label">Max Students</label>
                                            <input type="number" class="form-control" id="max_students" name="max_students" min="1" max="200"
                                                   value="<?php echo $editCourse ? $editCourse['max_students'] : '30'; ?>" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select" id="status" name="status" required>
                                                <option value="active" <?php echo ($editCourse && $editCourse['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo ($editCourse && $editCourse['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i><?php echo $editCourse ? 'Update Course' : 'Add Course'; ?>
                                        </button>
                                        <?php if ($editCourse): ?>
                                            <a href="manage-courses.php" class="btn btn-secondary">
                                                <i class="fas fa-times me-2"></i>Cancel
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Courses List -->
                <div class="row">
                    <div class="col-12">
                        <div class="card dashboard-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Courses (<?php echo count($courses); ?>)</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($courses)): ?>
                                    <p class="text-muted text-center py-4">No courses found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Course Code</th>
                                                    <th>Course Name</th>
                                                    <th>Teacher</th>
                                                    <th>Credits</th>
                                                    <th>Semester</th>
                                                    <th>Enrolled</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($courses as $course): ?>
                                                <tr>
                                                    <td><strong><?php echo $course['course_code']; ?></strong></td>
                                                    <td>
                                                        <?php echo $course['course_name']; ?>
                                                        <?php if ($course['description']): ?>
                                                            <br><small class="text-muted"><?php echo substr($course['description'], 0, 50) . '...'; ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($course['teacher_first']): ?>
                                                            <?php echo $course['teacher_first'] . ' ' . $course['teacher_last']; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Not assigned</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $course['credits']; ?></td>
                                                    <td><?php echo $course['semester'] . ' ' . $course['year']; ?></td>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?php echo $course['enrolled_students']; ?>/<?php echo $course['max_students']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge status-badge <?php echo $course['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                            <?php echo ucfirst($course['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <a href="manage-courses.php?edit=<?php echo $course['id']; ?>" 
                                                               class="btn btn-outline-primary btn-sm">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-outline-danger btn-sm" 
                                                                    onclick="deleteCourse(<?php echo $course['id']; ?>, '<?php echo addslashes($course['course_name']); ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
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
            </main>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the course "<span id="courseName"></span>"?</p>
                    <p class="text-warning"><small><i class="fas fa-warning me-1"></i>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_course">
                        <input type="hidden" name="course_id" id="deleteCourseId">
                        <button type="submit" class="btn btn-danger">Delete Course</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteCourse(courseId, courseName) {
            document.getElementById('deleteCourseId').value = courseId;
            document.getElementById('courseName').textContent = courseName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>