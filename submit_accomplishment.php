<?php
require 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Faculty') {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$kra_stmt = $pdo->query("SELECT kra_id, kra_name FROM KRA ORDER BY kra_name");
$kras = $kra_stmt->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $kra_id = $_POST['kra_id'];
        $description = trim($_POST['description']);
        $month = $_POST['month'];
        $file_path = null;
        // Normalize month to YYYY-MM-01
        if (!empty($month)) {
            $month = date('Y-m-01', strtotime($month));
        }
        if (empty($kra_id) || empty($description) || empty($month)) {
            $error = "KRA, description, and month are required.";
        } else {
            $requires_file = in_array($kra_id, [2, 3]);
            if ($requires_file && empty($_FILES['file']['name'])) {
                $error = "A file is required for Research or Extension.";
            } elseif (!empty($_FILES['file']['name'])) {
                $file = $_FILES['file'];
                $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
                $max_size = 5 * 1024 * 1024;
                if ($file['size'] > $max_size || !in_array($file['type'], $allowed_types)) {
                    $error = "Invalid file. Must be PDF, JPEG, or PNG, and under 5MB.";
                } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                    $error = "File upload error.";
                } else {
                    $file_name = uniqid() . '_' . basename($file['name']);
                    $file_path = 'Uploads/' . $file_name;
                    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                        $error = "Failed to upload file.";
                    }
                }
            }
            if (!isset($error)) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO Accomplishment (user_id, kra_id, description, month, file_path, status) 
                        VALUES (?, ?, ?, ?, ?, 'Pending')
                    ");
                    $stmt->execute([$user_id, $kra_id, $description, $month, $file_path]);
                    $success = "Accomplishment submitted successfully.";
                    error_log("Submission by user $user_id: kra_id=$kra_id, month=$month, file_path=" . ($file_path ?? 'NULL'));
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                    error_log("Submission error: " . $e->getMessage());
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Accomplishment</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', sans-serif;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 250px;
            background: #2c3e50;
            color: #fff;
            transition: all 0.3s;
            overflow-y: auto;
            z-index: 1000;
        }
        .sidebar .sidebar-header {
            padding: 20px;
            background: #1a252f;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .sidebar .nav-link {
            color: #bdc3c7;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #34495e;
            color: #fff;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding-left: 2.5rem;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }
        .input-group-text {
            background: #f8f9fa;
            border-radius: 10px 0 0 10px;
            border: none;
            color: #2c3e50;
        }
        .btn-gradient {
            background: linear-gradient(45deg, #3498db, #2980b9);
            border: none;
            color: #fff;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s;
        }
        .btn-gradient:hover {
            background: linear-gradient(45deg, #2980b9, #3498db);
            transform: scale(1.05);
        }
        .btn-gradient-reset {
            background: linear-gradient(45deg, #6c757d, #5a6268);
            border: none;
            color: #fff;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s;
        }
        .btn-gradient-reset:hover {
            background: linear-gradient(45deg, #5a6268, #6c757d);
            transform: scale(1.05);
        }
        .alert {
            border-radius: 10px;
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        h2 {
            color: #2c3e50;
            font-weight: 700;
        }
        .form-label {
            color: #2c3e50;
            font-weight: 500;
        }
        .is-invalid {
            border-color: #dc3545 !important;
        }
        .invalid-feedback {
            display: none;
            color: #dc3545;
            font-size: 0.875rem;
        }
        .is-invalid ~ .invalid-feedback {
            display: block;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            .sidebar .sidebar-header, .sidebar .nav-link span {
                display: none;
            }
            .content {
                margin-left: 70px;
            }
            .sidebar:hover {
                width: 250px;
            }
            .sidebar:hover .sidebar-header, .sidebar:hover .nav-link span {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            Faculty Dashboard
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="submit_accomplishment.php" class="nav-link active">
                    <i class="fas fa-plus-circle"></i>
                    <span>Submit Accomplishment</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="view_accomplishments.php" class="nav-link">
                    <i class="fas fa-list"></i>
                    <span>View Accomplishments</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="content">
        <h2 class="mb-4"><i class="fas fa-plus-circle me-2"></i>Submit Accomplishment</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="accomplishmentForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-3">
                        <label for="kra_id" class="form-label">KRA</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-tag"></i></span>
                            <select class="form-select" id="kra_id" name="kra_id" required>
                                <option value="">Select KRA</option>
                                <?php foreach ($kras as $kra): ?>
                                    <option value="<?php echo $kra['kra_id']; ?>">
                                        <?php echo htmlspecialchars($kra['kra_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="invalid-feedback">Please select a KRA.</div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                            <textarea class="form-control" id="description" name="description" rows="4" placeholder="Describe your accomplishment..." required></textarea>
                        </div>
                        <div class="invalid-feedback">Please provide a description.</div>
                    </div>
                    <div class="mb-3">
                        <label for="month" class="form-label">Month</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            <input type="month" class="form-control" id="month" name="month" required>
                        </div>
                        <div class="invalid-feedback">Please select a month.</div>
                    </div>
                    <div class="mb-3">
                        <label for="file" class="form-label" id="fileLabel">File (PDF/JPEG/PNG, max 5MB)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-file-upload"></i></span>
                            <input type="file" class="form-control" id="file" name="file" accept=".pdf,.jpeg,.jpg,.png">
                        </div>
                        <div class="invalid-feedback">A file is required for Research or Extension.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-gradient">Submit</button>
                        <button type="button" class="btn btn-gradient-reset" id="resetForm">Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('accomplishmentForm');
            const kraSelect = document.getElementById('kra_id');
            const fileInput = document.getElementById('file');
            const fileLabel = document.getElementById('fileLabel');
            const resetButton = document.getElementById('resetForm');

            // Update file requirement based on KRA
            function updateFileRequirement() {
                const kraId = kraSelect.value;
                const requiresFile = ['2', '3'].includes(kraId);
                fileLabel.textContent = requiresFile 
                    ? 'File (Required for Research/Extension, PDF/JPEG/PNG, max 5MB)' 
                    : 'File (Optional, PDF/JPEG/PNG, max 5MB)';
                fileInput.required = requiresFile;
            }

            kraSelect.addEventListener('change', updateFileRequirement);

            // Client-side validation
            form.addEventListener('submit', function (event) {
                let isValid = true;
                const fields = [kraSelect, document.getElementById('description'), document.getElementById('month'), fileInput];

                fields.forEach(field => {
                    if (!field.value && field.required) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });

                // File validation for Research/Extension
                if (['2', '3'].includes(kraSelect.value) && !fileInput.files.length) {
                    fileInput.classList.add('is-invalid');
                    isValid = false;
                } else {
                    fileInput.classList.remove('is-invalid');
                }

                // File type and size validation
                if (fileInput.files.length) {
                    const file = fileInput.files[0];
                    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    if (!allowedTypes.includes(file.type) || file.size > maxSize) {
                        fileInput.classList.add('is-invalid');
                        fileInput.nextElementSibling.textContent = 'Invalid file. Must be PDF, JPEG, or PNG, and under 5MB.';
                        isValid = false;
                    }
                }

                if (!isValid) {
                    event.preventDefault();
                }
            });

            // Reset form
            resetButton.addEventListener('click', function () {
                form.reset();
                kraSelect.classList.remove('is-invalid');
                document.getElementById('description').classList.remove('is-invalid');
                document.getElementById('month').classList.remove('is-invalid');
                fileInput.classList.remove('is-invalid');
                updateFileRequirement();
            });

            // Initial file requirement check
            updateFileRequirement();
        });
    </script>
</body>
</body>
</html>