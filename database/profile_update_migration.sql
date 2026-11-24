-- Run this once in phpMyAdmin for an existing scholarship_system database.
USE scholarship_system;
ALTER TABLE users
  ADD COLUMN phone VARCHAR(30) NULL AFTER email,
  ADD COLUMN date_of_birth DATE NULL AFTER phone,
  ADD COLUMN address TEXT NULL AFTER date_of_birth,
  ADD COLUMN profile_picture VARCHAR(255) NULL AFTER address;
