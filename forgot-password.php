<?php
require_once __DIR__ . '/../config.php';

$message = '';
$messageType = '';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $message = 'Please enter your email address.';
        $messageType = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'danger';
    } else {
        // Check if email exists in database
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate password reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
            
            // Store token in database (you may want to create a separate table for this)
            // For now, we'll store it in a temporary way
            $stmt = $pdo->prepare("
                INSERT INTO password_resets (user_id, token, expires_at, created_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                token = VALUES(token), 
                expires_at = VALUES(expires_at), 
                created_at = NOW()
            ");
            
            try {
                $stmt->execute([$user['id'], $token, $expires]);
                
                // Send email with reset link
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;
                
                $subject = "Password Reset Request - " . APP_NAME;
                $emailBody = "
                Hello {$user['username']},

                You have requested to reset your password for " . APP_NAME . ".

                Click the link below to reset your password:
                {$resetLink}

                This link will expire in 1 hour.

                If you did not request this password reset, please ignore this email.

                Best regards,
                " . APP_NAME . " Team
                ";
                
                $headers = "From: " . ADMIN_EMAIL . "\r\n";
                $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                
                if (mail($email, $subject, $emailBody, $headers)) {
                    $message = 'Password reset instructions have been sent to your email address.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to send email. Please contact the administrator.';
                    $messageType = 'danger';
                }
            } catch (PDOException $e) {
                // Create the password_resets table if it doesn't exist
                $createTableSQL = "
                    CREATE TABLE IF NOT EXISTS password_resets (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        token VARCHAR(64) NOT NULL,
                        expires_at DATETIME NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        used BOOLEAN DEFAULT FALSE,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        UNIQUE KEY unique_user (user_id),
                        INDEX idx_token (token)
                    )
                ";
                $pdo->exec($createTableSQL);
                
                // Try again
                $stmt->execute([$user['id'], $token, $expires]);
                
                // Send email
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;
                
                $subject = "Password Reset Request - " . APP_NAME;
                $emailBody = "
                Hello {$user['username']},

                You have requested to reset your password for " . APP_NAME . ".

                Click the link below to reset your password:
                {$resetLink}

                This link will expire in 1 hour.

                If you did not request this password reset, please ignore this email.

                Best regards,
                " . APP_NAME . " Team
                ";
                
                $headers = "From: " . ADMIN_EMAIL . "\r\n";
                $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                
                if (mail($email, $subject, $emailBody, $headers)) {
                    $message = 'Password reset instructions have been sent to your email address.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to send email. Please contact the administrator.';
                    $messageType = 'danger';
                }
            }
        } else {
            // Don't reveal that email doesn't exist for security reasons
            $message = 'If an account with that email exists, password reset instructions have been sent.';
            $messageType = 'info';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .forgot-password-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            text-align: center;
            padding: 2rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 600;
            transition: transform 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .back-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="forgot-password-card mx-auto">
                    <div class="card-header">
                        <h3 class="mb-0">
                            <i class="fas fa-key me-2"></i>Forgot Password
                        </h3>
                        <p class="mb-0 mt-2 opacity-75">Enter your email to reset your password</p>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-4">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-2"></i>Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="Enter your email address" required>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Send Reset Instructions
                                </button>
                            </div>
                        </form>

                        <div class="text-center">
                            <a href="index.php" class="back-link">
                                <i class="fas fa-arrow-left me-2"></i>Back to Login
                            </a>
                        </div>

                        <hr class="my-4">

                        <div class="text-center">
                            <small class="text-muted">
                                Need help? <a href="contact-admin.php" class="back-link">Contact Administrator</a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>