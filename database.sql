-- Create the database
CREATE DATABASE IF NOT EXISTS grievance_portal;
USE grievance_portal;

-- Students Table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    roll_no VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    father_name VARCHAR(100) NOT NULL,
    registration_no VARCHAR(20) NOT NULL UNIQUE,
    year INT NOT NULL,
    branch VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- Staff Table
CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_team_lead BOOLEAN DEFAULT FALSE
);

-- Teams Table (to manage teams created by admin)
CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(100) NOT NULL,
    team_lead_id INT NOT NULL,
    FOREIGN KEY (team_lead_id) REFERENCES staff(id)
);

-- Team Members Table (to map staff to teams)
CREATE TABLE IF NOT EXISTS team_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_id INT NOT NULL,
    staff_id INT NOT NULL,
    FOREIGN KEY (team_id) REFERENCES teams(id),
    FOREIGN KEY (staff_id) REFERENCES staff(id)
);

-- Complaints Table
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) NOT NULL,
    status ENUM('pending', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    team_id INT, -- Team assigned to handle the complaint
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (team_id) REFERENCES teams(id)
);

-- Complaint Attachments Table (to store files related to complaints)
CREATE TABLE IF NOT EXISTS complaint_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by ENUM('student', 'staff') NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id)
);

-- Meetings Table (to manage meetings arranged by staff)
CREATE TABLE IF NOT EXISTS meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    scheduled_at DATETIME NOT NULL,
    location VARCHAR(255),
    recording_path VARCHAR(255), -- Path to the recorded meeting
    document_path VARCHAR(255), -- Path to the meeting document
    FOREIGN KEY (complaint_id) REFERENCES complaints(id)
);

-- Meeting Participants Table (to map students and staff to meetings)
CREATE TABLE IF NOT EXISTS meeting_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_id INT NOT NULL,
    student_id INT, -- Can be NULL if only staff are involved
    staff_id INT,   -- Can be NULL if only students are involved
    FOREIGN KEY (meeting_id) REFERENCES meetings(id),
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (staff_id) REFERENCES staff(id)
);

-- Admin Table
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Password Reset Tokens Table (for password recovery)
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('student', 'staff') NOT NULL,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL
);

ALTER TABLE complaints ADD COLUMN proceeding_attachments TEXT;

ALTER TABLE complaints ADD COLUMN final_decision ENUM('pending', 'accepted', 'challenged') DEFAULT 'pending';

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin(id)
);

ALTER TABLE complaints
ADD COLUMN team_lead_id INT,
ADD CONSTRAINT fk_team_lead
FOREIGN KEY (team_lead_id) REFERENCES staff(id);

ALTER TABLE complaints ADD COLUMN submitted_by ENUM('student', 'staff') NOT NULL AFTER student_id;

-- Add resolution notes and timestamp to complaints
ALTER TABLE complaints 
ADD COLUMN resolution_notes TEXT,
ADD COLUMN resolved_at TIMESTAMP NULL DEFAULT NULL;

-- Add added_by to team_members table
ALTER TABLE team_members 
ADD COLUMN added_by INT,
ADD FOREIGN KEY (added_by) REFERENCES staff(id);

ALTER TABLE complaint_attachments MODIFY COLUMN file_path VARCHAR(255) NOT NULL;
ALTER TABLE complaint_attachments ADD COLUMN file_type VARCHAR(50) AFTER file_path;

-- Add uploaded_by_staff_id column to track who uploaded documents
ALTER TABLE complaint_attachments 
ADD COLUMN uploaded_by_staff_id INT NULL AFTER uploaded_by,
ADD CONSTRAINT fk_uploaded_by_staff 
FOREIGN KEY (uploaded_by_staff_id) REFERENCES staff(id);

-- Add uploaded_by_student_id column for student uploads
ALTER TABLE complaint_attachments 
ADD COLUMN uploaded_by_student_id INT NULL AFTER uploaded_by_staff_id,
ADD CONSTRAINT fk_uploaded_by_student 
FOREIGN KEY (uploaded_by_student_id) REFERENCES students(id);

ALTER TABLE complaints ADD COLUMN challenge_reason TEXT AFTER final_decision;