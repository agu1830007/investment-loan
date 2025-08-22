-- Migration: Add total_repayment column to investments table
ALTER TABLE investments ADD COLUMN total_repayment DECIMAL(12,2) GENERATED ALWAYS AS (amount + (amount * interest_rate * 4 / 100)) STORED;
