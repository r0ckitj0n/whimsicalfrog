-- Migration: Shift room_number values for room_category_assignments
-- Adjusts legacy room numbering (2-6) to new numbering (1-5)

BEGIN;

-- Only affect numeric room numbers 2 through 6
UPDATE room_category_assignments
SET room_number = room_number - 1
WHERE room_number BETWEEN 2 AND 6;

COMMIT;
