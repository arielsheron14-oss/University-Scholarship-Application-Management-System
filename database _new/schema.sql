CREATE DATABASE IF NOT EXISTS scholarship_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE scholarship_system;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  date_of_birth DATE NULL,
  address TEXT NULL,
  profile_picture VARCHAR(255) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','staff','admin') NOT NULL DEFAULT 'student',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE student_profiles (
  profile_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  student_number VARCHAR(30),
  faculty VARCHAR(120),
  degree_program VARCHAR(150),
  study_year TINYINT,
  current_gpa DECIMAL(3,2) DEFAULT 0,
  achievements TEXT,
  household_income_range VARCHAR(40) NULL,
  household_size TINYINT UNSIGNED NULL,
  dependents_count TINYINT UNSIGNED NULL,
  guardian_occupation VARCHAR(150) NULL,
  employment_status VARCHAR(40) NULL,
  receives_financial_aid TINYINT(1) NULL,
  financial_circumstances TEXT NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE scholarship_categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  category_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE scholarships (
  scholarship_id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  provider_name VARCHAR(150),
  benefits TEXT,
  min_gpa DECIMAL(3,2) DEFAULT 0,
  required_documents TEXT,
  opening_date DATE NOT NULL,
  closing_date DATE NOT NULL,
  status ENUM('draft','published','closed','archived') DEFAULT 'draft',
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES scholarship_categories(category_id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE applications (
  application_id INT AUTO_INCREMENT PRIMARY KEY,
  scholarship_id INT NOT NULL,
  student_id INT NOT NULL,
  applicant_statement TEXT NOT NULL,
  document_path VARCHAR(255),
  profile_snapshot LONGTEXT NULL,
  status ENUM('Submitted','Under Review','Shortlisted','Rejected','Awarded') DEFAULT 'Submitted',
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_application (scholarship_id, student_id),
  FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE scholarship_requirements (
  requirement_id INT AUTO_INCREMENT PRIMARY KEY,
  scholarship_id INT NOT NULL,
  requirement_name VARCHAR(180) NOT NULL,
  description VARCHAR(500) NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  allowed_file_types VARCHAR(100) NOT NULL DEFAULT 'pdf,jpg,jpeg,png',
  max_file_size_mb TINYINT UNSIGNED NOT NULL DEFAULT 5,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (scholarship_id) REFERENCES scholarships(scholarship_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE application_documents (
  document_id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  requirement_id INT NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE CASCADE,
  FOREIGN KEY (requirement_id) REFERENCES scholarship_requirements(requirement_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO scholarship_categories(category_name)
VALUES ('Academic'),('Sports'),('Leadership'),('Financial Need'),('Other');
