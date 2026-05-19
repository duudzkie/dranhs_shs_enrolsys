-- PostgreSQL version of setup_users table
-- Compatible with Railway PostgreSQL

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin', 'evaluator', 'encoder')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default users (passwords are pre-hashed with bcrypt)
-- Default password for all: password123
INSERT INTO users (username, password, role) VALUES
('admin', '$2y$10$7vIslO6P4erEc7DxqxArb.x603s3FrT.GY4EwyxaMVE0qqbuY7TBO', 'admin'),
('evaluator', '$2y$10$7vIslO6P4erEc7DxqxArb.x603s3FrT.GY4EwyxaMVE0qqbuY7TBO', 'evaluator'),
('encoder', '$2y$10$7vIslO6P4erEc7DxqxArb.x603s3FrT.GY4EwyxaMVE0qqbuY7TBO', 'encoder')
ON CONFLICT (username) DO UPDATE SET password = EXCLUDED.password, role = EXCLUDED.role;
