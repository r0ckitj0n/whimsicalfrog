-- SQL Migration Script: update_orders_table_payment_fields.sql
-- Adds new fields to the `orders` table for enhanced payment tracking,
-- including options for cash and check payments, check numbers, payment dates, and payment-specific notes.
-- This script uses individual ALTER TABLE statements for compatibility.
-- It's recommended to run this script in an environment where errors can be handled
-- or to manually check for column existence if running multiple times.

-- Add 'checkNumber' column to store check numbers for check payments
-- Placed after 'paymentMethod' for logical grouping.
ALTER TABLE `orders`
ADD COLUMN `checkNumber` VARCHAR(64) NULL DEFAULT NULL COMMENT 'Check number if payment method is Check' AFTER `paymentMethod`;

-- Add 'paymentDate' column to store the date when the payment was received/processed
-- Placed after 'paymentStatus' as it relates to the status update.
ALTER TABLE `orders`
ADD COLUMN `paymentDate` DATE NULL DEFAULT NULL COMMENT 'Date when the payment was received or processed' AFTER `paymentStatus`;

-- Add 'paymentNotes' column to store any specific notes related to the payment transaction
-- Placed after 'paymentDate'. This is distinct from the general order 'notes'.
ALTER TABLE `orders`
ADD COLUMN `paymentNotes` TEXT NULL DEFAULT NULL COMMENT 'Specific notes related to the payment transaction' AFTER `paymentDate`;

-- End of script
