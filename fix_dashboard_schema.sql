-- ALTER TABLE commands to match your PHP code and fix dashboard updates

-- Add interest_rate to investments if missing
ALTER TABLE investments ADD COLUMN interest_rate DECIMAL(5,2) NOT NULL DEFAULT 5.00;

-- Add duration, interest_rate, purpose, id_type, id_value to loans if missing
ALTER TABLE loans ADD COLUMN duration INT NOT NULL DEFAULT 4;
ALTER TABLE loans ADD COLUMN interest_rate DECIMAL(5,2) DEFAULT 5.00;
ALTER TABLE loans ADD COLUMN purpose TEXT;
ALTER TABLE loans ADD COLUMN id_type VARCHAR(10);
ALTER TABLE loans ADD COLUMN id_value VARCHAR(30);
