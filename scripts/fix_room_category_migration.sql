-- Safe Migration: Remove conflicting assignments and shift room numbers
-- Adjusts legacy room numbering (2-6) to new numbering (1-5)

BEGIN;

-- Delete duplicate assignments that would collide after shift
DELETE rca1 FROM room_category_assignments rca1
JOIN room_category_assignments rca2
  ON rca1.room_number - 1 = rca2.room_number
  AND rca1.category_id = rca2.category_id
WHERE rca1.room_number BETWEEN 2 AND 6;

-- Shift room numbers down by 1
UPDATE room_category_assignments
SET room_number = room_number - 1
WHERE room_number BETWEEN 2 AND 6;

COMMIT;
