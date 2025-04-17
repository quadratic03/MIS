-- Military Inventory System Database Structure

-- Create database
CREATE DATABASE IF NOT EXISTS military_inventory;
USE military_inventory;

-- Create Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    role ENUM('admin', 'inventory_manager', 'field_officer', 'viewer') NOT NULL,
    status ENUM('pending', 'active', 'suspended', 'archived') NOT NULL DEFAULT 'pending',
    profile_image VARCHAR(255),
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Vehicles table
CREATE TABLE IF NOT EXISTS vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_code VARCHAR(20) UNIQUE NOT NULL,
    category ENUM('combat', 'transport', 'support', 'specialized') NOT NULL,
    name VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    manufacturer VARCHAR(100) NOT NULL,
    year_manufactured INT NOT NULL,
    status ENUM('operational', 'maintenance', 'repair', 'decommissioned') NOT NULL,
    current_location VARCHAR(100),
    fuel_capacity DECIMAL(10,2),
    mileage INT,
    last_maintenance DATE,
    next_maintenance DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Ammunition table
CREATE TABLE IF NOT EXISTS ammunition (
    ammo_id INT AUTO_INCREMENT PRIMARY KEY,
    ammo_code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    category ENUM('small_arms', 'artillery', 'explosive', 'specialized') NOT NULL,
    caliber VARCHAR(50),
    quantity INT NOT NULL,
    manufacture_date DATE,
    expiration_date DATE,
    storage_location VARCHAR(100) NOT NULL,
    status ENUM('available', 'reserved', 'depleted', 'expired') NOT NULL,
    reorder_level INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Personnel table
CREATE TABLE IF NOT EXISTS personnel (
    personnel_id INT AUTO_INCREMENT PRIMARY KEY,
    service_number VARCHAR(20) UNIQUE NOT NULL,
    rank VARCHAR(50) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    dob DATE NOT NULL,
    joining_date DATE NOT NULL,
    unit VARCHAR(100) NOT NULL,
    position VARCHAR(100),
    specialization VARCHAR(100),
    status ENUM('active', 'leave', 'training', 'deployed', 'retired') NOT NULL,
    contact_number VARCHAR(20),
    email VARCHAR(100),
    blood_group VARCHAR(5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Maintenance Log table
CREATE TABLE IF NOT EXISTS maintenance_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT,
    personnel_id INT,
    maintenance_type ENUM('routine', 'repair', 'inspection', 'upgrade') NOT NULL,
    description TEXT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    cost DECIMAL(10,2),
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(vehicle_id) ON DELETE SET NULL,
    FOREIGN KEY (personnel_id) REFERENCES personnel(personnel_id) ON DELETE SET NULL
);

-- Create Inventory Transactions table
CREATE TABLE IF NOT EXISTS inventory_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('acquisition', 'deployment', 'transfer', 'decommission', 'consumption') NOT NULL,
    item_type ENUM('vehicle', 'ammunition', 'equipment') NOT NULL,
    item_id INT NOT NULL,
    quantity INT,
    from_location VARCHAR(100),
    to_location VARCHAR(100),
    personnel_id INT,
    transaction_date DATETIME NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (personnel_id) REFERENCES personnel(personnel_id) ON DELETE SET NULL
);

-- Create Settings table
CREATE TABLE IF NOT EXISTS settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (username, password, full_name, email, role, status) 
VALUES ('admin', '$2y$10$93.0dT21w9L9yMnbiDFTHOw6Hs2uUL9M4WDQGR5eifAkqtavVhTnO', 'System Administrator', 'admin@military-inventory.com', 'admin', 'active');

-- Insert some example data
INSERT INTO vehicles (vehicle_code, category, name, model, manufacturer, year_manufactured, status, current_location, fuel_capacity, mileage, last_maintenance, next_maintenance)
VALUES 
('VEH-001', 'combat', 'Main Battle Tank', 'M1A2 Abrams', 'General Dynamics', 2020, 'operational', 'Base Alpha', 500.00, 1200, '2023-10-15', '2024-04-15'),
('VEH-002', 'transport', 'Armored Personnel Carrier', 'M113', 'BAE Systems', 2018, 'operational', 'Base Alpha', 300.00, 2500, '2023-11-20', '2024-05-20'),
('VEH-003', 'combat', 'Infantry Fighting Vehicle', 'Bradley M2', 'BAE Systems', 2019, 'maintenance', 'Workshop Delta', 400.00, 1800, '2024-01-10', '2024-07-10'),
('VEH-004', 'support', 'Medical Evacuation Vehicle', 'M997 HMMWV', 'AM General', 2020, 'operational', 'Field Hospital Charlie', 95.00, 850, '2023-12-05', '2024-06-05');

INSERT INTO ammunition (ammo_code, name, category, caliber, quantity, manufacture_date, expiration_date, storage_location, status, reorder_level)
VALUES 
('AMM-001', '5.56mm NATO Rounds', 'small_arms', '5.56mm', 50000, '2023-06-01', '2033-06-01', 'Armory Alpha-1', 'available', 10000),
('AMM-002', '120mm Tank Shells', 'artillery', '120mm', 1200, '2023-05-15', '2033-05-15', 'Heavy Weapons Depot', 'available', 300),
('AMM-003', 'Hand Grenades M67', 'explosive', 'N/A', 2500, '2023-07-01', '2028-07-01', 'Secure Storage Bravo-2', 'available', 500),
('AMM-004', '9mm Parabellum', 'small_arms', '9mm', 75000, '2023-08-01', '2033-08-01', 'Armory Alpha-2', 'available', 15000);

INSERT INTO personnel (service_number, rank, first_name, last_name, dob, joining_date, unit, position, specialization, status, contact_number, email, blood_group)
VALUES 
('MIL-001', 'Colonel', 'James', 'Smith', '1980-05-15', '2000-06-10', 'Command Unit', 'Unit Commander', 'Strategic Operations', 'active', '123-456-7890', 'j.smith@milforce.org', 'O+'),
('MIL-002', 'Captain', 'Sarah', 'Johnson', '1985-10-20', '2008-03-15', 'Tank Battalion Alpha', 'Battalion Leader', 'Armored Warfare', 'active', '123-456-7891', 's.johnson@milforce.org', 'A-'),
('MIL-003', 'Lieutenant', 'Michael', 'Williams', '1990-02-28', '2012-07-22', 'Infantry Division Charlie', 'Squad Leader', 'Combat Tactics', 'deployed', '123-456-7892', 'm.williams@milforce.org', 'B+'),
('MIL-004', 'Sergeant', 'Robert', 'Davis', '1992-11-10', '2010-09-30', 'Maintenance Division', 'Chief Mechanic', 'Vehicle Maintenance', 'active', '123-456-7893', 'r.davis@milforce.org', 'AB+'); 