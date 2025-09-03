<?php
require_once __DIR__ . '/../config.php';
requireLogin();
requireUserType('teacher');

$userId = $_SESSION['user_id'];

// Get teacher info
$stmt = $pdo->prepare("
    SELECT t.* FROM teachers t 
    WHERE t.user_id = ?
");
$stmt->execute([$userId]);
$teacherInfo = $stmt->fetch();

// Get teacher's courses
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

// Handle course selection
$selectedCourseId = $_GET['course_id'] ?? ($courses[0]['id'] ?? null);
$selectedCourse = null;
$students = [];
$assignments = [];

if ($selectedCourseId) {
    // Get selected course info
    foreach ($courses as $course) {
        if ($course['id'] == $selectedCourseId) {
            $selectedCourse = $course;
            break;
        }
    }
    
    // Get enrolled students
    $stmt = $pdo->prepare("
        SELECT s.*, u.email, e.id as enrollment_id
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE e.course_id = ? AND e.status = 'enrolled'
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->execute([$selectedCourseId]);
    $students = $stmt->fetchAll();
    
    // Get assignments for the course
    $stmt = $pdo->prepare("
        SELECT * FROM assignments 
        WHERE course_id = ? 
        ORDER BY due_date DESC
    ");
    $stmt->execute([$selectedCourseId]);
    $assignments = $stmt->fetchAll();
    
    // Get all grades for the course
    $stmt = $pdo->prepare("
        SELECT g.*, a.title as assignment_title, a.max_points, s.id as student_id
        FROM grades g
        JOIN assignments a ON g.assignment_id = a.id
        JOIN students s ON g.student_id = s.id
        JOIN enrollments e ON (e.student_id = s.id AND e.course_id = a.course_id)
        WHERE a.course_id = ? AND e.status = 'enrolled'
    ");
    $stmt->execute([$selectedCourseId]);
    $allGrades = $stmt->fetchAll();
    
    // Organize grades by student and assignment
    $gradeMatrix = [];
    foreach ($allGrades as $grade) {
        $gradeMatrix[$grade['student_id']][$grade['assignment_id']] = $grade;
    }
}

// Handle grade updates via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_grade') {
    header('Content-Type: application/json');
    
    $studentId = $_POST['student_id'];
    $assignmentId = $_POST['assignment_id'];
    $pointsEarned = $_POST['points_earned'] !== '' ? floatval($_POST['points_earned']) : null;
    $feedback = $_POST['feedback'] ?? '';
    
    try {
        // Check if grade exists
        $stmt = $pdo->prepare("
            SELECT id FROM grades 
            WHERE student_id = ? AND assignment_id = ?
        ");
        $stmt->execute([$studentId, $assignmentId]);
        $existingGrade = $stmt->fetch();
        
        if ($existingGrade) {
            // Update existing grade
            $stmt = $pdo->prepare("
                UPDATE grades 
                SET points_earned = ?, feedback = ?, graded_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$pointsEarned, $feedback, $existingGrade['id']]);
        } else {
            // Insert new grade
            $stmt = $pdo->prepare("
                INSERT INTO grades (student_id, assignment_id, points_earned, feedback, graded_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$studentId, $assignmentId, $pointsEarned, $feedback]);
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gradebook - <?php echo APP_NAME; ?></title>
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
        
        .gradebook-table {
            font-size: 0.9rem;
        }
        
        .gradebook-table th {
            background-color: var(--primary-color);
            color: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .grade-input {
            width: 80px;
            padding: 0.25rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        
        .grade-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .student-row {
            background-color: #f8f9fa;
        }
        
        .assignment-header {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            min-width: 100px;
            height: 120px;
        }
        
        .course-selector {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
                            <a href="gradebook.php" class="nav-link active">
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
                <div class="pt-3 pb-2 mb-3">
                    <h1 class="h2">
                        <i class="fas fa-clipboard-list me-2"></i>Gradebook
                    </h1>
                </div>

                <!-- Course Selection -->
                <div class="course-selector">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <label for="courseSelect" class="form-label fw-bold">Select Course:</label>
                            <select class="form-select" id="courseSelect" onchange="changeCourse()">
                                <option value="">Choose a course...</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo $course['id'] == $selectedCourseId ? 'selected' : ''; ?>>
                                        <?php echo $course['course_code'] . ' - ' . $course['course_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($selectedCourse): ?>
                        <div class="col-md-6 text-end">
                            <strong><?php echo $selectedCourse['enrolled_students']; ?> students enrolled</strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($selectedCourse && !empty($students)): ?>
                <!-- Gradebook Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            <?php echo $selectedCourse['course_code'] . ' - ' . $selectedCourse['course_name']; ?>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-bordered gradebook-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="position: sticky; left: 0; z-index: 11; background-color: var(--primary-color);">Student</th>
                                        <?php foreach ($assignments as $assignment): ?>
                                        <th class="text-center assignment-header" title="<?php echo $assignment['title']; ?>">
                                            <div class="assignment-header">
                                                <small><?php echo substr($assignment['title'], 0, 15); ?></small><br>
                                                <small class="text-light">(<?php echo $assignment['max_points']; ?> pts)</small>
                                            </div>
                                        </th>
                                        <?php endforeach; ?>
                                        <th class="text-center" style="background-color: #28a745;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                    <tr class="student-row">
                                        <td style="position: sticky; left: 0; background-color: #f8f9fa; z-index: 10;">
                                            <strong><?php echo $student['last_name'] . ', ' . $student['first_name']; ?></strong><br>
                                            <small class="text-muted"><?php echo $student['student_id']; ?></small>
                                        </td>
                                        <?php 
                                        $totalEarned = 0;
                                        $totalPossible = 0;
                                        foreach ($assignments as $assignment): 
                                            $grade = $gradeMatrix[$student['id']][$assignment['id']] ?? null;
                                            $points = $grade['points_earned'] ?? '';
                                            if ($points !== '') {
                                                $totalEarned += floatval($points);
                                            }
                                            $totalPossible += $assignment['max_points'];
                                        ?>
                                        <td class="text-center">
                                            <input type="number" 
                                                   class="grade-input" 
                                                   value="<?php echo $points; ?>"
                                                   min="0" 
                                                   max="<?php echo $assignment['max_points']; ?>"
                                                   step="0.1"
                                                   data-student-id="<?php echo $student['id']; ?>"
                                                   data-assignment-id="<?php echo $assignment['id']; ?>"
                                                   onchange="updateGrade(this)">
                                            <?php if ($grade && $grade['feedback']): ?>
                                            <div class="mt-1">
                                                <i class="fas fa-comment text-info" 
                                                   title="<?php echo htmlspecialchars($grade['feedback']); ?>"></i>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <?php endforeach; ?>
                                        <td class="text-center fw-bold" style="background-color: #e8f5e8;">
                                            <?php 
                                            $percentage = $totalPossible > 0 ? ($totalEarned / $totalPossible) * 100 : 0;
                                            echo $totalEarned . '/' . $totalPossible . '<br>';
                                            echo '<small>(' . number_format($percentage, 1) . '%)</small>';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <?php elseif ($selectedCourse): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No students are currently enrolled in this course.
                </div>
                <?php endif; ?>

                <?php if (empty($courses)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    You are not currently assigned to teach any courses.
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Grade Details Modal -->
    <div class="modal fade" id="gradeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Grade Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="gradeForm">
                        <input type="hidden" id="modalStudentId">
                        <input type="hidden" id="modalAssignmentId">
                        
                        <div class="mb-3">
                            <label for="modalPoints" class="form-label">Points Earned</label>
                            <input type="number" class="form-control" id="modalPoints" step="0.1" min="0">
                        </div>
                        
                        <div class="mb-3">
                            <label for="modalFeedback" class="form-label">Feedback</label>
                            <textarea class="form-control" id="modalFeedback" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveGradeDetails()">Save</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changeCourse() {
            const courseId = document.getElementById('courseSelect').value;
            if (courseId) {
                window.location.href = 'gradebook.php?course_id=' + courseId;
            } else {
                window.location.href = 'gradebook.php';
            }
        }

        function updateGrade(input) {
            const studentId = input.dataset.studentId;
            const assignmentId = input.dataset.assignmentId;
            const pointsEarned = input.value;

            // Simple update for quick entry
            fetch('gradebook.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'update_grade',
                    student_id: studentId,
                    assignment_id: assignmentId,
                    points_earned: pointsEarned,
                    feedback: ''
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    input.style.borderColor = '#28a745';
                    setTimeout(() => {
                        input.style.borderColor = '#ddd';
                    }, 1000);
                    
                    // Refresh the page to update totals
                    location.reload();
                } else {
                    alert('Error saving grade: ' + (data.error || 'Unknown error'));
                    input.style.borderColor = '#dc3545';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving grade');
                input.style.borderColor = '#dc3545';
            });
        }

        // Double-click to open detailed grade modal
        document.addEventListener('DOMContentLoaded', function() {
            const gradeInputs = document.querySelectorAll('.grade-input');
            gradeInputs.forEach(input => {
                input.addEventListener('dblclick', function() {
                    openGradeModal(this);
                });
            });
        });

        function openGradeModal(input) {
            document.getElementById('modalStudentId').value = input.dataset.studentId;
            document.getElementById('modalAssignmentId').value = input.dataset.assignmentId;
            document.getElementById('modalPoints').value = input.value;
            
            const modal = new bootstrap.Modal(document.getElementById('gradeModal'));
            modal.show();
        }

        function saveGradeDetails() {
            const studentId = document.getElementById('modalStudentId').value;
            const assignmentId = document.getElementById('modalAssignmentId').value;
            const pointsEarned = document.getElementById('modalPoints').value;
            const feedback = document.getElementById('modalFeedback').value;

            fetch('gradebook.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'update_grade',
                    student_id: studentId,
                    assignment_id: assignmentId,
                    points_earned: pointsEarned,
                    feedback: feedback
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('gradeModal')).hide();
                    location.reload();
                } else {
                    alert('Error saving grade: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving grade');
            });
        }
    </script>
</body>
</html>