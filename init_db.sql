-- Users table (for authentication)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('student', 'teacher', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Students table
CREATE TABLE IF NOT EXISTS students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE,
    phone VARCHAR(15),
    address TEXT,
    enrollment_date DATE NOT NULL,
    status ENUM('active', 'inactive', 'graduated') DEFAULT 'active',
    gpa DECIMAL(3,2) DEFAULT 0.00,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Teachers table
CREATE TABLE IF NOT EXISTS teachers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    teacher_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    department VARCHAR(100),
    phone VARCHAR(15),
    office_location VARCHAR(100),
    hire_date DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Courses table
CREATE TABLE IF NOT EXISTS courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_code VARCHAR(20) UNIQUE NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    description TEXT,
    credits INT NOT NULL DEFAULT 3,
    teacher_id INT,
    semester VARCHAR(20) NOT NULL,
    year INT NOT NULL,
    max_students INT DEFAULT 30,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- Enrollments table
CREATE TABLE IF NOT EXISTS enrollments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('enrolled', 'dropped', 'completed') DEFAULT 'enrolled',
    final_grade DECIMAL(5,2),
    letter_grade ENUM('A', 'B', 'C', 'D', 'E', 'F', 'U') DEFAULT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, course_id)
);

-- Assignments table
CREATE TABLE IF NOT EXISTS assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    due_date DATETIME NOT NULL,
    max_points INT NOT NULL DEFAULT 100,
    assignment_type ENUM('homework', 'quiz', 'exam', 'project', 'practical') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Grades table
CREATE TABLE IF NOT EXISTS grades (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    assignment_id INT NOT NULL,
    points_earned DECIMAL(5,2),
    submitted_at TIMESTAMP NULL,
    graded_at TIMESTAMP NULL,
    feedback TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_grade (student_id, assignment_id)
);

-- Attendance table
CREATE TABLE IF NOT EXISTS attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
    notes TEXT,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (student_id, course_id, attendance_date)
);

-- Announcements table
CREATE TABLE IF NOT EXISTS announcements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    author_id INT NOT NULL,
    target_audience ENUM('all', 'students', 'teachers') DEFAULT 'all',
    course_id INT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Sample data (Chitova Academy)

INSERT INTO users (username, email, password, user_type) VALUES
('admin', 'admin@chitova.ac.zw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('tawanda.moyo', 'tawanda.moyo@student.chitova.ac.zw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('ruvarashe.chikafu', 'ruvarashe.chikafu@student.chitova.ac.zw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
('mr_chibanda', 'chibanda@chitova.ac.zw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
('dr_ncube', 'ncube@chitova.ac.zw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher');

INSERT INTO students (user_id, student_id, first_name, last_name, date_of_birth, phone, address, enrollment_date, gpa) VALUES
(2, 'CHITOVA2024-001', 'Tawanda', 'Moyo', '2001-04-10', '+263771234567', 'Bulawayo, Zimbabwe', '2023-08-15', 3.45),
(3, 'CHITOVA2024-002', 'Ruvarashe', 'Chikafu', '2000-11-22', '+263783456789', 'Harare, Zimbabwe', '2023-08-15', 3.78);

INSERT INTO teachers (user_id, teacher_id, first_name, last_name, department, phone, office_location, hire_date) VALUES
(4, 'TCH001', 'Tendai', 'Chibanda', 'Computer Science', '+263772223334', 'CS Block 2 Office 14', '2018-09-01'),
(5, 'TCH002', 'Vimbai', 'Ncube', 'Mathematics', '+263774445556', 'Maths Dept Office 7', '2015-07-01');

INSERT INTO courses (course_code, course_name, description, credits, teacher_id, semester, year) VALUES
('CSC101', 'Introduction to Programming', 'Introduction to programming concepts using Python', 3, 1, 'Semester 1', 2024),
('MTH201', 'Calculus II', 'Integral calculus and applications in real life', 4, 2, 'Semester 1', 2024),
('CSC201', 'Data Structures', 'In-depth study of data structures and algorithms', 3, 1, 'Semester 2', 2024);

INSERT INTO enrollments (student_id, course_id, final_grade, letter_grade) VALUES
(1, 1, 75.0, 'B'),
(1, 2, 82.0, 'A'),
(2, 1, 90.0, 'A'),
(2, 3, 68.0, 'C');

INSERT INTO assignments (course_id, title, description, due_date, max_points, assignment_type) VALUES
(1, 'Python Basics Test', 'Test covering variables, loops, and functions', '2024-09-15 23:59:00', 50, 'quiz'),
(1, 'Final Programming Project', 'Develop a web system using Django', '2024-12-10 23:59:00', 200, 'project'),
(2, 'Mid-Sem Exam', 'Exam on advanced integration techniques', '2024-10-20 14:00:00', 150, 'exam');

INSERT INTO announcements (title, content, author_id, target_audience, priority) VALUES
('Welcome to Semester 1', 'Makadii! Welcome to the new semester at Chitova University. Please check your course registration online.', 1, 'students', 'high'),
('ZESA Load Shedding Notice', 'Some evening classes will be adjusted due to power cuts. Check timetable for updates.', 1, 'all', 'medium');
