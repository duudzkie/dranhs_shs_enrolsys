CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'evaluator', 'encoder') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$7vIslO6P4erEc7DxqxArb.x603s3FrT.GY4EwyxaMVE0qqbuY7TBO', 'admin'),
('evaluator', '$2y$10$7vIslO6P4erEc7DxqxArb.x603s3FrT.GY4EwyxaMVE0qqbuY7TBO', 'evaluator'),
('encoder', '$2y$10$7vIslO6P4erEc7DxqxArb.x603s3FrT.GY4EwyxaMVE0qqbuY7TBO', 'encoder')
ON DUPLICATE KEY UPDATE password = VALUES(password), role = VALUES(role);
