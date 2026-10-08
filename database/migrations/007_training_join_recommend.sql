-- Grow/Flow: change training participation from approval workflow to Joined + Recommended.
-- Run once after 006_training_applications.sql.

ALTER TABLE training_applications
    MODIFY source ENUM('employee','hr_nomination','hr_recommendation') NOT NULL DEFAULT 'hr_recommendation',
    MODIFY status ENUM('pending','approved','rejected','joined','recommended','declined') NOT NULL DEFAULT 'recommended';

UPDATE training_applications
SET source = 'hr_recommendation'
WHERE source = 'hr_nomination';

UPDATE training_applications
SET status = 'joined'
WHERE source = 'employee'
  AND status IN ('pending','approved');

UPDATE training_applications
SET status = 'recommended'
WHERE source = 'hr_recommendation'
  AND status IN ('pending','approved');

UPDATE training_applications
SET status = 'declined'
WHERE status = 'rejected';
