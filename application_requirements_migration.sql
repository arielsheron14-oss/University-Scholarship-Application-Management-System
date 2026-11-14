-- Run once in phpMyAdmin after selecting the scholarship_system database.
-- Adds configurable scholarship document requirements and per-application uploads.

ALTER TABLE applications
  ADD COLUMN profile_snapshot LONGTEXT NULL;

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
  CONSTRAINT fk_requirement_scholarship FOREIGN KEY (scholarship_id)
    REFERENCES scholarships(scholarship_id) ON DELETE CASCADE
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
  CONSTRAINT fk_appdoc_application FOREIGN KEY (application_id)
    REFERENCES applications(application_id) ON DELETE CASCADE,
  CONSTRAINT fk_appdoc_requirement FOREIGN KEY (requirement_id)
    REFERENCES scholarship_requirements(requirement_id) ON DELETE SET NULL
) ENGINE=InnoDB;
