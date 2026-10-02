-- ============================================
-- TPS SYSTEM - Database Setup
-- ============================================

CREATE DATABASE IF NOT EXISTS tps_system;
USE tps_system;

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('student', 'teacher') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- QUESTIONS TABLE (Multiple Choice)
-- ============================================
CREATE TABLE questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_answer ENUM('A', 'B', 'C', 'D') NOT NULL,
    week VARCHAR(50),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- GAMES TABLE
-- ============================================
CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status ENUM('waiting', 'active', 'pairing', 'chatting', 'ended') DEFAULT 'waiting',
    started_at DATETIME DEFAULT NULL,
    question_end_time DATETIME DEFAULT NULL,
    current_question_order INT DEFAULT 0,
    total_questions INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- GAME_ENTRIES TABLE
-- ============================================
CREATE TABLE game_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('waiting', 'answering', 'ready_for_pairing', 'paired', 'chatting') DEFAULT 'waiting',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_entry (game_id, student_id)
);

-- ============================================
-- ASSIGNED_QUESTIONS TABLE
-- ============================================
CREATE TABLE assigned_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    student_id INT NOT NULL,
    question_id INT NOT NULL,
    student_answer ENUM('A', 'B', 'C', 'D'),
    is_correct TINYINT(1) DEFAULT NULL,
    answered_at DATETIME DEFAULT NULL,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- ============================================
-- PAIRS TABLE
-- ============================================
CREATE TABLE pairs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    student1_id INT NOT NULL,
    student2_id INT NOT NULL,
    pair_answer ENUM('A', 'B', 'C', 'D'),
    answered_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (student1_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student2_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- MESSAGES TABLE
-- ============================================
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pair_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pair_id) REFERENCES pairs(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- INSERT DEFAULT DATA
-- ============================================

-- Default teacher account (password: password)
INSERT INTO users (username, password, full_name, role) VALUES 
('teacher', '$2y$10$JsdtlLMKPzQ2pwHfhGOH6uX7Y3Zobh4.fUU1.tCG9/3Y8gt87..b2', 'Default Teacher', 'teacher');

-- Sample student accounts (password: password)
INSERT INTO users (username, password, full_name, role) VALUES 
('student1', '$2y$10$JsdtlLMKPzQ2pwHfhGOH6uX7Y3Zobh4.fUU1.tCG9/3Y8gt87..b2', 'John Doe', 'student'),
('student2', '$2y$10$JsdtlLMKPzQ2pwHfhGOH6uX7Y3Zobh4.fUU1.tCG9/3Y8gt87..b2', 'Jane Smith', 'student'),
('student3', '$2y$10$JsdtlLMKPzQ2pwHfhGOH6uX7Y3Zobh4.fUU1.tCG9/3Y8gt87..b2', 'Bob Wilson', 'student'),
('student4', '$2y$10$JsdtlLMKPzQ2pwHfhGOH6uX7Y3Zobh4.fUU1.tCG9/3Y8gt87..b2', 'Alice Brown', 'student');

-- ============================================
-- QUESTIONS (27 Questions)
-- ============================================

-- Week 1 – Decimals & Money (5 questions)
INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, correct_answer, week, created_by) VALUES
('Maria bought 3 pencils at ₱8.75 each and an eraser at ₱12.50. How much did she pay?', '₱38.75', '₱39.75', '₱40.75', '₱41.00', 'A', 'Week 1', 1),
('A bag costs ₱525.75. If you pay ₱1,000.00, how much change will you receive?', '₱474.25', '₱475.00', '₱476.25', '₱473.75', 'A', 'Week 1', 1),
('Compute: ₱250.00 – ₱125.75 = ?', '₱124.25', '₱125.00', '₱126.25', '₱123.75', 'A', 'Week 1', 1),
('A toy costs ₱99.50. If you buy 4 toys, how much will you pay?', '₱398.00', '₱399.50', '₱400.00', '₱401.00', 'A', 'Week 1', 1),
('Solve: ₱1,200.00 ÷ 8 = ?', '₱150.00', '₱160.00', '₱140.00', '₱125.00', 'A', 'Week 1', 1),

-- Week 2 – MDAS Rule (5 questions)
('Simplify: (15 – 3) × 2 + 8', '32', '28', '30', '26', 'A', 'Week 2', 1),
('Solve: 36 ÷ (4 + 2)', '6', '8', '9', '12', 'A', 'Week 2', 1),
('Simplify: (25 – 5) ÷ 5', '4', '5', '6', '7', 'A', 'Week 2', 1),
('Compute: 10 + (6 × 3)', '28', '25', '30', '27', 'A', 'Week 2', 1),
('Solve: (48 ÷ 8) + 15', '21', '20', '22', '18', 'A', 'Week 2', 1),

-- Week 3 – Solid Figures (4 questions)
('Identify the solid figure with 2 circular faces and 1 curved surface.', 'Cone', 'Cylinder', 'Sphere', 'Pyramid', 'B', 'Week 3', 1),
('Name the solid figure with 1 circular face and a pointed top.', 'Cone', 'Cylinder', 'Sphere', 'Prism', 'A', 'Week 3', 1),
('Which solid figure has 6 rectangular faces?', 'Cube', 'Rectangular Prism', 'Pyramid', 'Cylinder', 'B', 'Week 3', 1),
('Identify the solid figure with 8 triangular faces.', 'Octahedron', 'Cube', 'Cone', 'Prism', 'A', 'Week 3', 1),

-- Week 4 – Prisms & Pyramids (4 questions)
('Which has two parallel bases?', 'Pyramid', 'Prism', 'Cone', 'Sphere', 'B', 'Week 4', 1),
('How many faces does a rectangular prism have?', '4', '6', '8', '12', 'B', 'Week 4', 1),
('How many edges does a triangular pyramid have?', '6', '8', '10', '12', 'A', 'Week 4', 1),
('Which solid figure has only one base and triangular faces?', 'Prism', 'Pyramid', 'Cone', 'Cylinder', 'B', 'Week 4', 1),

-- Week 5 – Constructing Models (2 questions)
('How many squares are needed for a cube net?', '4', '5', '6', '8', 'C', 'Week 5', 1),
('Which solid figure can be formed from a net with 2 triangles and 3 rectangles?', 'Cube', 'Triangular Prism', 'Square Pyramid', 'Cone', 'B', 'Week 5', 1),

-- Week 6 – Surface Area of Rectangular Prisms (2 questions)
('A rectangular prism has dimensions 6 cm × 4 cm × 2 cm. Find its surface area.', '52 cm²', '88 cm²', '100 cm²', '104 cm²', 'B', 'Week 6', 1),
('A cube has an edge of 7 cm. Find its surface area.', '294 cm²', '343 cm²', '392 cm²', '420 cm²', 'A', 'Week 6', 1),

-- Week 7 – Rectangular Prisms & Cubes (3 questions)
('How many faces does a rectangular prism have?', '4', '6', '8', '12', 'B', 'Week 7', 1),
('How many edges does a cube have?', '8', '10', '12', '14', 'C', 'Week 7', 1),
('Compare: How are the vertices of a cube similar to those of a rectangular prism?', 'Both have 6 vertices', 'Both have 8 vertices', 'Both have 10 vertices', 'Both have 12 vertices', 'B', 'Week 7', 1),

-- Week 8 – Rotation (2 questions)
('If a square is rotated 90° clockwise about a point, what does the image look like?', 'Same as original', 'Upside down', 'Rotated but same shape', 'Distorted', 'C', 'Week 8', 1),
('If a rectangle is rotated 180°, what does the image look like?', 'Same as original', 'Upside down', 'Mirror image', 'Distorted', 'A', 'Week 8', 1);

-- Sample game
INSERT INTO games (name, status, created_by) VALUES 
('Sample Game Session', 'waiting', 1);