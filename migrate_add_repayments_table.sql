-- Migration: Add repayments table for loan repayments
CREATE TABLE IF NOT EXISTS repayments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    loan_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    repaid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (loan_id) REFERENCES loans(id)
);
