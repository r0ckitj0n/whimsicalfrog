import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const root = process.cwd();
const read = (relativePath) => readFileSync(join(root, relativePath), 'utf8');

const failures = [];

const whoami = read('api/whoami.php');
if (whoami.includes("header('Access-Control-Allow-Origin: ' . $origin)") || whoami.includes('header("Access-Control-Allow-Origin: $origin")')) {
  failures.push('api/whoami.php must not reflect arbitrary credentialed CORS origins.');
}
if (whoami.includes("header('Access-Control-Allow-Origin: *')") && whoami.includes("header('Access-Control-Allow-Credentials: true')")) {
  failures.push('api/whoami.php must not combine wildcard CORS with credentials.');
}

const authSessionHelper = read('includes/helpers/AuthSessionHelper.php');
if (authSessionHelper.includes("$_SESSION['user'] = ['user_id' => $uid]")) {
  failures.push('Auth session recovery must not authenticate a cookie without a live users row.');
}
if (authSessionHelper.includes("@setcookie('WF_LOGOUT_IN_PROGRESS', '', $opts)")) {
  failures.push('Session recovery must not clear the logout guard on the first guarded request.');
}

const addInventory = read('api/add_inventory.php');
if (!addInventory.includes('SELECT cost_price, retail_price FROM items WHERE sku = ? LIMIT 1')) {
  failures.push('Default breakdown seeding must read current item prices before inserting manual rows.');
}
if (addInventory.includes("VALUES (?, ?, ?, 0, 'manual', NOW(), NOW())")) {
  failures.push('Default cost factors must not seed all-zero rows that overwrite item cost_price.');
}
if (addInventory.includes("VALUES (?, 'Manual Retail', 0, 'final', '', 'manual', NOW())")) {
  failures.push('Default price factors must not seed a zero row that overwrites item retail_price.');
}

if (failures.length > 0) {
  console.error('Critical regression guard failed:');
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  process.exit(1);
}

console.log('Critical regression guard passed.');
