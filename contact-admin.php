<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$userType = getUserType();
$userId = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle form submission
if ($_POST) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $category = $_POST['category'] ?? 'general';
    
    // Validation
    if (empty($subject)) {
        $error = 'Subject is required.';
    } elseif (empty($message)) {
        $error = 'Message is required.';
    } elseif (strlen($subject) > 200) {
        $error = 'Subject must be less than 200 characters.';
    } elseif (strlen($message) > 2000) {
        $error = 'Message must be less than 2000 characters.';
    } else {
        try {
            // Insert the contact message into announcements table with admin target
            $stmt = $pdo->prepare("
                INSERT INTO announcements (title, content, author_id, target_audience, priority, created_at)
                VALUES (?, ?, ?, 'admin', ?, NOW())
            ");
            
            $fullMessage = "Category: " . ucfirst($category) . "\n\n" . $message . "\n\n---\nSent by: " . $_SESSION['username'] . " (" . ucfirst($userType) . ")";
            $fullSubject = "[Contact Form] " . $subject;
            
            $stmt->execute([$fullSubject, $fullMessage, $userId, $priority]);
            
            $success = 'Your message has been sent successfully! An administrator will review it and respond if necessary.';
            
            // Clear form data after successful submission
            $_POST = array();
            
        } catch (PDOException $e) {
            $error = 'An error occurred while sending your message. Please try again.';
        }
    }
}

// Get user info for display
if ($userType === 'student') {
    $stmt = $pdo->prepare("
        SELECT s.first_name, s.last_name, u.email 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.user_id = ?
    ");
    $stmt->execute([$userId]);
    $userInfo = $stmt->fetch();
} elseif ($userType === 'teacher') {
    $stmt = $pdo->prepare("
        SELECT t.first_name, t.last_name, u.email 
        FROM teachers t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.user_id = ?
    ");
    $stmt->execute([$userId]);
    $userInfo = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT username as first_name, '' as last_name, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userInfo = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Admin - <?php echo APP_NAME; ?></title>
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
        
        .main-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
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
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            transform: translateY(-1px);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .contact-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 1.5rem;
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
                        <li class="nav-item">
                            <a href="contact-admin.php" class="nav-link active">
                                <i class="fas fa-envelope me-2"></i>Contact Admin
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ms-sm-auto px-md-4">
                <div class="pt-3 pb-2 mb-3">
                    <h1 class="h2">
                        <i class="fas fa-envelope me-2"></i>Contact Administrator
                    </h1>
                    <p class="text-muted">Send a message to the system administrators</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <!-- Contact Form -->
                        <div class="card main-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Send Message</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="category" class="form-label">Category</label>
                                            <select class="form-select" id="category" name="category" required>
                                                <option value="general" <?php echo ($_POST['category'] ?? '') === 'general' ? 'selected' : ''; ?>>General Inquiry</option>
                                                <option value="technical" <?php echo ($_POST['category'] ?? '') === 'technical' ? 'selected' : ''; ?>>Technical Support</option>
                                                <option value="academic" <?php echo ($_POST['category'] ?? '') === 'academic' ? 'selected' : ''; ?>>Academic Issue</option>
                                                <option value="account" <?php echo ($_POST['category'] ?? '') === 'account' ? 'selected' : ''; ?>>Account Problem</option>
                                                <option value="billing" <?php echo ($_POST['category'] ?? '') === 'billing' ? 'selected' : ''; ?>>Billing Question</option>
                                                <option value="feature" <?php echo ($_POST['category'] ?? '') === 'feature' ? 'selected' : ''; ?>>Feature Request</option>
                                                <option value="bug" <?php echo ($_POST['category'] ?? '') === 'bug' ? 'selected' : ''; ?>>Bug Report</option>
                                                <option value="other" <?php echo ($_POST['category'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="priority" class="form-label">Priority</label>
                                            <select class="form-select" id="priority" name="priority" required>
                                                <option value="low" <?php echo ($_POST['priority'] ?? 'medium') === 'low' ? 'selected' : ''; ?>>Low</option>
                                                <option value="medium" <?php echo ($_POST['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                                <option value="high" <?php echo ($_POST['priority'] ?? 'medium') === 'high' ? 'selected' : ''; ?>>High</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="subject" name="subject" 
                                               value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" 
                                               maxlength="200" required 
                                               placeholder="Brief description of your inquiry">
                                        <div class="form-text">Maximum 200 characters</div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="message" name="message" rows="8" 
                                                  maxlength="2000" required 
                                                  placeholder="Please provide detailed information about your inquiry..."><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                        <div class="form-text">Maximum 2000 characters</div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="reset" class="btn btn-outline-secondary me-md-2">
                                            <i class="fas fa-undo me-2"></i>Clear Form
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i>Send Message
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Contact Information -->
                        <div class="contact-info">
                            <h5><i class="fas fa-info-circle me-2"></i>Your Information</h5>
                            <hr>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($userInfo['first_name'] . ' ' . $userInfo['last_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($userInfo['email']); ?></p>
                            <p><strong>User Type:</strong> <?php echo ucfirst($userType); ?></p>
                            <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?></p>
                        </div>

                        <!-- Help Information -->
                        <div class="card main-card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Need Help?</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6><i class="fas fa-clock me-2"></i>Response Time</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li><span class="badge bg-danger me-2">High</span>Within 4 hours</li>
                                        <li><span class="badge bg-warning me-2">Medium</span>Within 24 hours</li>
                                        <li><span class="badge bg-success me-2">Low</span>Within 3 days</li>
                                    </ul>
                                </div>
                                
                                <div class="mb-3">
                                    <h6><i class="fas fa-lightbulb me-2"></i>Tips</h6>
                                    <ul class="small">
                                        <li>Be as specific as possible</li>
                                        <li>Include error messages if any</li>
                                        <li>Mention your browser and device</li>
                                        <li>Check announcements first</li>
                                    </ul>
                                </div>

                                <div class="alert alert-info mb-0">
                                    <small>
                                        <i class="fas fa-info-circle me-1"></i>
                                        For urgent technical issues, contact your IT support team directly.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character counter for subject
        document.getElementById('subject').addEventListener('input', function() {
            const maxLength = 200;
            const currentLength = this.value.length;
            const remaining = maxLength - currentLength;
            
            let helpText = this.parentNode.querySelector('.form-text');
            helpText.textContent = `${remaining} characters remaining`;
            
            if (remaining < 20) {
                helpText.classList.add('text-warning');
            } else {
                helpText.classList.remove('text-warning');
            }
        });

        // Character counter for message
        document.getElementById('message').addEventListener('input', function() {
            const maxLength = 2000;
            const currentLength = this.value.length;
            const remaining = maxLength - currentLength;
            
            let helpText = this.parentNode.querySelector('.form-text');
            helpText.textContent = `${remaining} characters remaining`;
            
            if (remaining < 100) {
                helpText.classList.add('text-warning');
            } else {
                helpText.classList.remove('text-warning');
            }
        });

        // Auto-dismiss success alerts after 5 seconds
        setTimeout(function() {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    </script>
</body>
</html>