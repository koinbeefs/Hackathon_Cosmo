<?php
require 'config.php';
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $employee_number = trim($_POST['employee_number']);
        $role = $_POST['role'];
        $department_id = $_POST['department_id'];
        $password = $_POST['password'];
        // Validate employee number
        if (!preg_match('/^EMP\d{4}$/', $employee_number)) {
            $error = "Employee number must be 'EMP' followed by 4 digits (e.g., EMP1001).";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } else {
            // Check if employee number exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE employee_number = ?");
            $stmt->execute([$employee_number]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Employee number already registered.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO Users (first_name, last_name, employee_number, role, department_id, password_hash) VALUES (?, ?, ?, ?, ?, ?)");
                try {
                    $stmt->execute([$first_name, $last_name, $employee_number, $role, $department_id, $password_hash]);
                    $success = "Registration successful! Please log in.";
                } catch (PDOException $e) {
                    $error = "Registration failed: " . $e->getMessage();
                }
            }
        }
    }
}
$stmt = $pdo->query("SELECT * FROM Department");
$departments = $stmt->fetchAll();
?>
<?php include 'header.php'; ?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <h2 class="mb-4">Register</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" class="form-control" id="first_name" name="first_name" required>
            </div>
            <div class="mb-3">
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" class="form-control" id="last_name" name="last_name" required>
            </div>
            <div class="mb-3">
                <label for="employee_number" class="form-label">Employee Number</label>
                <input type="text" class="form-control" id="employee_number" name="employee_number" required pattern="EMP\d{4}" title="Format: EMP followed by 4 digits (e.g., EMP1001)">
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="Faculty">Faculty</option>
                    <option value="Dean">Dean</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="department_id" class="form-label">Department</label>
                <select class="form-select" id="department_id" name="department_id" required>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['department_id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Register</button>
        </form>
    </div>
</div>
<?php include 'footer.php'; ?>