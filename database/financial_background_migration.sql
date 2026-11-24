-- Run this ONCE in phpMyAdmin for an existing scholarship_system database.
USE scholarship_system;
ALTER TABLE student_profiles
  ADD COLUMN household_income_range VARCHAR(40) NULL AFTER achievements,
  ADD COLUMN household_size TINYINT UNSIGNED NULL AFTER household_income_range,
  ADD COLUMN dependents_count TINYINT UNSIGNED NULL AFTER household_size,
  ADD COLUMN guardian_occupation VARCHAR(150) NULL AFTER dependents_count,
  ADD COLUMN employment_status VARCHAR(40) NULL AFTER guardian_occupation,
  ADD COLUMN receives_financial_aid TINYINT(1) NULL AFTER employment_status,
  ADD COLUMN financial_circumstances TEXT NULL AFTER receives_financial_aid;
