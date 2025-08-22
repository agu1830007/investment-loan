-- FULL DATABASE REBUILD SCRIPT
-- 1. Main schema

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    balance DECIMAL(12,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Payments Table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method VARCHAR(50),
    status ENUM('pending','confirmed','approved','completed','rejected') DEFAULT 'pending',
    note VARCHAR(255),
    name VARCHAR(100),
    email VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Withdrawals Table
CREATE TABLE IF NOT EXISTS withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pending','confirmed','approved','completed','rejected') DEFAULT 'pending',
    account_number VARCHAR(20),
    bank_name VARCHAR(100),
    account_name VARCHAR(100),
    note VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Investments Table
CREATE TABLE IF NOT EXISTS investments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan VARCHAR(50) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    total_repayment DECIMAL(12,2),
    status ENUM('active','matured','completed','cancelled') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    next_payout DATETIME,
    note VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Loans Table
CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50),
    amount DECIMAL(12,2) NOT NULL,
    interest DECIMAL(12,2),
    total_repayment DECIMAL(12,2),
    duration INT, -- weeks or months
    status ENUM('pending','approved','active','repaid','rejected') DEFAULT 'pending',
    due_date DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    id_type VARCHAR(20),
    id_value VARCHAR(50),
    purpose VARCHAR(255),
    processed_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Notifications Table (optional)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 2. Migrations

-- Add total_repayment column to investments table (if not already present)
ALTER TABLE investments ADD COLUMN IF NOT EXISTS total_repayment DECIMAL(12,2) GENERATED ALWAYS AS (amount + (amount * interest_rate * 4 / 100)) STORED;

-- Add repayments table for loan repayments
CREATE TABLE IF NOT EXISTS repayments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    loan_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    repaid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (loan_id) REFERENCES loans(id)
);

-- Add interest_rate to investments if missing
ALTER TABLE investments ADD COLUMN IF NOT EXISTS interest_rate DECIMAL(5,2) NOT NULL DEFAULT 5.00;

-- Add duration, interest_rate, purpose, id_type, id_value to loans if missing
ALTER TABLE loans ADD COLUMN IF NOT EXISTS duration INT NOT NULL DEFAULT 4;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS interest_rate DECIMAL(5,2) DEFAULT 5.00;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS purpose TEXT;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS id_type VARCHAR(10);
ALTER TABLE loans ADD COLUMN IF NOT EXISTS id_value VARCHAR(30);
