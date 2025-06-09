# Whimsical Frog E-Commerce: Payment System Guide

This guide provides an overview of the payment management features within the Whimsical Frog admin panel, specifically focusing on handling Cash and Check payments.

## 1. Overview

The payment system allows administrators to track and update the payment status of orders, particularly for manual payment methods like cash and check. It provides tools to record payment details, ensuring accurate financial records.

## 2. Key Features

*   **Manual Payment Tracking**: Admins can manage orders paid via "Cash" or "Check".
*   **Payment Status Updates**: Clearly mark orders as "Pending" or "Received".
*   **Check Detail Recording**: Store check numbers for "Check" payments.
*   **Payment Notes**: Add specific notes or details related to a payment (e.g., "Cash received by Jane Doe", "Check #123 awaiting deposit").
*   **Payment Date Logging**: Record the date when a payment was received.
*   **Integrated Admin Interface**: All payment management is done within the "Admin Orders" section.
*   **Quick Updates**: Toggle payment status directly from the main order list.
*   **Detailed Editing**: A dedicated form for comprehensive payment information updates within the order view.
*   **Conditional Fields**: The "Check Number" field only appears when "Check" is selected as the payment method.
*   **Automatic Date**: If a payment is marked "Received" without a specific date, today's date is automatically recorded as the `paymentDate`.
*   **Visual Cues**: Payment statuses are color-coded for easy identification. New toggle buttons use brand colors for consistency.

## 3. How to Use the Payment System

All payment management is handled via the **Admin Dashboard -> Orders** section (`/?page=admin_orders`).

### 3.1. Viewing Payment Information in the Order List

The main orders list table now includes dedicated columns:

*   **Payment Method**: Displays the method used for the order (e.g., "Credit Card", "Cash", "Check").
*   **Payment Status**: Shows if the payment is "Pending" or "Received", typically styled with a color-coded badge.
*   **Payment Actions**: Contains a quick action button to toggle the payment status.

### 3.2. Quick Payment Update (from Order List)

For quick updates directly from the order list:

1.  Locate the order in the list.
2.  In the **"Payment Actions"** column, you'll find a button:
    *   If the status is "Pending", the button will say **"Mark Paid"** (styled in brand green).
    *   If the status is "Received", the button will say **"Mark Unpaid"** (styled in yellow/orange).
3.  Clicking this button will instantly toggle the `paymentStatus` between "Pending" and "Received".
    *   If marking as "Paid" and no `paymentDate` was previously set, today's date will be automatically recorded.
    *   If marking as "Unpaid", the `paymentDate` will be cleared.
    *   This quick toggle does *not* change `paymentMethod`, `checkNumber`, or `paymentNotes`. For those, use the detailed edit form.

### 3.3. Viewing Detailed Payment Information (Order View)

To see more details or make comprehensive changes:

1.  From the order list, click **"View"** for the desired order.
2.  Scroll down to the **"Payment Information"** section. Here you'll see:
    *   Current Payment Status (e.g., "Received")
    *   Payment Method (e.g., "Check")
    *   Check Number (if applicable)
    *   Payment Date (if set)
    *   Payment Notes (if any)

### 3.4. Editing Payment Information (Order View)

1.  In the "Payment Information" section of the order view, click the **"Edit Payment Info"** button (styled with the brand green color).
2.  The display area will be replaced by an editable form with the following fields:
    *   **Payment Method**: Dropdown select with options:
        *   `Credit Card`
        *   `Cash`
        *   `Check`
        *   `Other`
    *   **Check Number**: A text input field. This field is **only visible if "Check" is selected** as the Payment Method.
    *   **Payment Date**: A date picker. You can leave this blank; if "Mark Payment as Received" is checked and no date is provided, today's date will be used.
    *   **Mark Payment as Received**: A checkbox.
        *   Checked: Sets `paymentStatus` to "Received".
        *   Unchecked: Sets `paymentStatus` to "Pending".
    *   **Payment Notes**: A textarea for any relevant details about the payment.
3.  Make the necessary changes.
4.  Click **"Update Payment Information"** (brand green button) to save.
5.  Click **"Cancel"** to discard changes and revert to the display view.

**Note on Order Status vs. Payment Status:**
The "Update Status" link on the main order list (or the "Update Status" button in the order view) manages the *overall order fulfillment status* (e.g., Pending, Processing, Shipped, Delivered). The "Shipped" status for an order can only be selected if its `paymentStatus` is "Received".

## 4. Database Structure

Payment information is stored within the `orders` table. Key columns related to payments include:

*   `id` (VARCHAR(16)): The unique identifier for the order (Primary Key).
*   `paymentMethod` (VARCHAR(50)): Stores the method of payment. Examples: "Credit Card", "Cash", "Check", "PayPal", "Other". Defaults to "Credit Card".
*   `paymentStatus` (VARCHAR(20)): The current status of the payment. Primarily "Pending" or "Received" for manual admin updates. Other potential values (e.g., "Refunded", "Failed") might be used by automated systems in the future. Defaults to "Pending".
*   `checkNumber` (VARCHAR(64), NULLABLE): Stores the check number if the payment method is "Check". Otherwise, it's `NULL`.
*   `paymentDate` (DATE, NULLABLE): The date the payment was officially received. `NULL` if pending or not yet recorded.
*   `paymentNotes` (TEXT, NULLABLE): Allows administrators to store any relevant notes about the payment transaction.

## 5. Styling

*   The updates to the payment system were designed to integrate seamlessly with the existing admin panel styling, primarily using Tailwind CSS.
*   Custom CSS classes were added for the new payment toggle buttons in `css/styles.css` to ensure they use the brand's green color (`#87ac3a`) for "Mark Paid" actions and a contrasting yellow/orange for "Mark Unpaid" actions, enhancing visual consistency.
    *   `.payment-toggle-btn`
    *   `.payment-toggle-btn.paid` (Brand Green)
    *   `.payment-toggle-btn.pending` (Yellow/Orange)
*   The main "Edit Payment Info" and "Update Payment Information" buttons also use the `.brand-button` class for consistent green styling.

This guide should help in managing payments effectively. For any further assistance, refer to the `sections/admin_orders.php` file for the frontend logic and the database schema for backend details.
