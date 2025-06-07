<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /?page=login');
    exit;
}
$user = is_string($_SESSION['user']) ? json_decode($_SESSION['user'], true) : $_SESSION['user'];
$success = false;
$error = '';
$passwordChanged = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $addressLine1 = trim($_POST['address_line1'] ?? '');
    $addressLine2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zipCode = trim($_POST['zip_code'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $userId = $user['id'] ?? $user['userId'] ?? '';
    if (!$firstName || !$lastName || !$email) {
        $error = 'First name, last name, and email are required.';
    } else {
        // Update user in MySQL via API
        $apiBase = 'https://whimsicalfrog.us';
        $payload = [
            'userId' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'address_line1' => $addressLine1,
            'address_line2' => $addressLine2,
            'city' => $city,
            'state' => $state,
            'zip_code' => $zipCode,
            'phone_number' => $phoneNumber
        ];
        if ($password) $payload['password'] = $password;
        $ch = curl_init($apiBase . '/api/update-user');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $response = curl_exec($ch);
        $result = json_decode($response, true);
        curl_close($ch);
        if ($result && !empty($result['success'])) {
            $success = true;
            // Update session user info
            $_SESSION['user']['first_name'] = $firstName;
            $_SESSION['user']['last_name'] = $lastName;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['address_line1'] = $addressLine1;
            $_SESSION['user']['address_line2'] = $addressLine2;
            $_SESSION['user']['city'] = $city;
            $_SESSION['user']['state'] = $state;
            $_SESSION['user']['zip_code'] = $zipCode;
            $_SESSION['user']['phone_number'] = $phoneNumber;
            if ($password) $_SESSION['user']['password'] = $password;
            if ($password) {
                $passwordChanged = true;
            }
        } else {
            $error = $result['error'] ?? 'Failed to update account.';
        }
    }
}
?>
<style>
  .admin-form-label {
    color: #222 !important;
  }
  .admin-form-input {
    color: #c00 !important;
    border-color: #c00 !important;
  }
</style>
<div class="bg-white shadow rounded-lg p-6 max-w-lg mx-auto mt-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Account Settings</h2>
    <?php if ($success): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">Account updated successfully.</div>
    <?php elseif ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST" autocomplete="off">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">First Name</label>
            <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Last Name</label>
            <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Address Line 1</label>
            <input type="text" name="address_line1" value="<?php echo htmlspecialchars($user['address_line1'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Address Line 2</label>
            <input type="text" name="address_line2" value="<?php echo htmlspecialchars($user['address_line2'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">City</label>
            <input type="text" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">State</label>
            <input type="text" name="state" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Zip Code</label>
            <input type="text" name="zip_code" value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Phone Number</label>
            <input type="text" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">New Password <span class="text-xs text-gray-500">(leave blank to keep current)</span></label>
            <input type="password" name="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
        </div>
        <div class="flex justify-end">
            <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded">Save Changes</button>
        </div>
    </form>
    <?php if (!empty($passwordChanged)): ?>
        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mt-4">Password changed successfully. Please use your new password next time you log in.</div>
    <?php endif; ?>
</div>
<script>
// --- Scroll to account settings form on page load ---
(function() {
  const form = document.querySelector('form[method="POST"]');
  if (form) {
    setTimeout(() => form.scrollIntoView({behavior: 'smooth', block: 'center'}), 100);
    form.querySelector('input,select,textarea').focus();
  }
})();
// --- Phone number formatting for phone input ---
function formatPhoneInput(input) {
  input.addEventListener('input', function(e) {
    let val = input.value.replace(/\D/g, '');
    if (val.length > 10) val = val.slice(0, 10);
    let formatted = val;
    if (val.length > 6) formatted = `(${val.slice(0,3)}) ${val.slice(3,6)}-${val.slice(6)}`;
    else if (val.length > 3) formatted = `(${val.slice(0,3)}) ${val.slice(3)}`;
    else if (val.length > 0) formatted = `(${val}`;
    input.value = formatted;
  });
  // On submit, strip formatting
  input.form && input.form.addEventListener('submit', function() {
    input.value = input.value.replace(/\D/g, '');
  });
}
document.querySelectorAll('input[name="phone_number"]').forEach(formatPhoneInput);
// --- Format phone number in display section (if any) ---
function formatPhoneDisplay(num) {
  if (!num) return '';
  const val = num.replace(/\D/g, '');
  if (val.length === 10) return `(${val.slice(0,3)}) ${val.slice(3,6)}-${val.slice(6)}`;
  return num;
}
document.querySelectorAll('.customer-phone-display').forEach(function(span) {
  span.textContent = formatPhoneDisplay(span.textContent);
});
</script> 