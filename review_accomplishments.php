<?php
require 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Dean' || !isset($_SESSION['department_id'])) {
    header("Location: login.php");
    exit;
}
$department_id = $_SESSION['department_id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $accomplishment_id = $_POST['accomplishment_id'] ?? null;
        $action = $_POST['action'] ?? null;
        if (!$accomplishment_id || !in_array($action, ['approve', 'reject'])) {
            $error = "Invalid action.";
        } else {
            $status = $action === 'approve' ? 'Approved' : 'Rejected';
            try {
                $stmt = $pdo->prepare("
                    UPDATE Accomplishment 
                    SET status = ?, updated_at = NOW() 
                    WHERE accomplishment_id = ? 
                    AND user_id IN (SELECT user_id FROM Users WHERE department_id = ?)
                ");
                $stmt->execute([$status, $accomplishment_id, $department_id]);
                if ($stmt->rowCount() === 0) {
                    $error = "No submission found or unauthorized.";
                } else {
                    $success = "Submission " . ucfirst($status) . " successfully.";
                    error_log("Dean {$_SESSION['user_id']} $status accomplishment $accomplishment_id");
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
                error_log("Review error: " . $e->getMessage());
            }
        }
    }
}
$stmt = $pdo->prepare("
    SELECT a.accomplishment_id, a.description, a.month, a.file_path, a.status, 
           k.kra_name, u.first_name, u.last_name, u.employee_number 
    FROM Accomplishment a 
    JOIN KRA k ON a.kra_id = k.kra_id 
    JOIN Users u ON a.user_id = u.user_id 
    WHERE u.department_id = ? AND a.status = 'Pending' 
    ORDER BY a.month DESC, u.last_name
");
$stmt->execute([$department_id]);
$submissions = $stmt->fetchAll();

// Prepare unique months and faculty for filters
$months = array_unique(array_map(function($sub) {
    return date('F Y', strtotime($sub['month']));
}, $submissions));
sort($months);
$faculty = array_unique(array_map(function($sub) {
    return $sub['first_name'] . ' ' . $sub['last_name'] . ' (' . $sub['employee_number'] . ')';
}, $submissions));
sort($faculty);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Submissions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        }
        .card-title {
            font-weight: 600;
            color: #2c3e50;
        }
        .table {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
        }
        .table thead {
            background: #3498db;
            color: #fff;
        }
        .table tbody tr:hover {
            background: #f1f6ff;
        }
        .btn-gradient-success {
            background: linear-gradient(45deg, #28a745, #218838);
            border: none;
            color: #fff;
            border-radius: 25px;
            transition: all 0.3s;
        }
        .btn-gradient-danger {
            background: linear-gradient(45deg, #dc3545, #c82333);
            border: none;
            color: #fff;
            border-radius: 25px;
            transition: all 0.3s;
        }
        .btn-gradient-success:hover {
            background: linear-gradient(45deg, #218838, #28a745);
            transform: scale(1.05);
        }
        .btn-gradient-danger:hover {
            background: linear-gradient(45deg, #c82333, #dc3545);
            transform: scale(1.05);
        }
        .btn-gradient-reset {
            background: linear-gradient(45deg, #6c757d, #5a6268);
            border: none;
            color: #fff;
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
        .badge {
            font-size: 0.9rem;
        }
        .filter-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-group select, .filter-group input {
            border-radius: 10px;
            padding: 0.5rem;
            border: 1px solid #ced4da;
        }
        .filter-group select:focus, .filter-group input:focus {
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
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
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
<div class="sidebar">
        <div class="sidebar-header">
            Dean Dashboard
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="review_accomplishments.php" class="nav-link active">
                    <i class="fas fa-check-circle"></i>
                    <span>Review Submissions</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="report_graphs.php" class="nav-link">
                    <i class="fas fa-chart-line"></i>
                    <span>Performance Graphs</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="report_statistics.php" class="nav-link">
                    <i class="fas fa-table"></i>
                    <span>Performance Statistics</span>
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

    <div class="content">
        <h2 class="mb-4"><i class="fas fa-check-circle me-2"></i>Review Submissions</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="filter-group mb-3">
                    <select id="monthFilter" class="form-select">
                        <option value="">All Months</option>
                        <?php foreach ($months as $month): ?>
                            <option value="<?php echo htmlspecialchars($month); ?>"><?php echo htmlspecialchars($month); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="facultyFilter" class="form-select">
                        <option value="">All Faculty</option>
                        <?php foreach ($faculty as $fac): ?>
                            <option value="<?php echo htmlspecialchars($fac); ?>"><?php echo htmlspecialchars($fac); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group" style="max-width: 300px;">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Search submissions...">
                    </div>
                    <button id="resetFilters" class="btn btn-gradient-reset">Reset</button>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="submissionsTable">
                        <thead>
                            <tr>
                                <th>Faculty</th>
                                <th>KRA</th>
                                <th>Description</th>
                                <th>Month</th>
                                <th>File</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($submissions)): ?>
                                <tr>
                                    <td colspan="7" class="text-muted text-center">No pending submissions found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($submissions as $submission): ?>
                                    <tr data-faculty="<?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name'] . ' (' . $submission['employee_number'] . ')'); ?>" data-month="<?php echo htmlspecialchars(date('F Y', strtotime($submission['month']))); ?>">
                                        <td><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name'] . ' (' . $submission['employee_number'] . ')'); ?></td>
                                        <td><?php echo htmlspecialchars($submission['kra_name']); ?></td>
                                        <td><?php echo htmlspecialchars($submission['description']); ?></td>
                                        <td><?php echo htmlspecialchars(date('F Y', strtotime($submission['month']))); ?></td>
                                        <td>
                                            <?php if ($submission['file_path']): ?>
                                                <a href="/project/<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank" class="btn btn-link btn-sm"><i class="fas fa-download"></i> Download</a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning"><?php echo htmlspecialchars($submission['status']); ?></span>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-flex gap-2">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="accomplishment_id" value="<?php echo $submission['accomplishment_id']; ?>">
                                                <button type="submit" name="action" value="approve" class="btn btn-gradient-success btn-sm"><i class="fas fa-check me-1"></i>Approve</button>
                                                <button type="submit" name="action" value="reject" class="btn btn-gradient-danger btn-sm"><i class="fas fa-times me-1"></i>Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const monthFilter = document.getElementById('monthFilter');
            const facultyFilter = document.getElementById('facultyFilter');
            const searchInput = document.getElementById('searchInput');
            const resetButton = document.getElementById('resetFilters');
            const tableRows = document.querySelectorAll('#submissionsTable tbody tr:not(.text-muted)');

            function filterTable() {
                const monthValue = monthFilter.value.toLowerCase();
                const facultyValue = facultyFilter.value.toLowerCase();
                const searchValue = searchInput.value.toLowerCase();

                tableRows.forEach(row => {
                    const month = row.dataset.month.toLowerCase();
                    const faculty = row.dataset.faculty.toLowerCase();
                    const kra = row.cells[1].textContent.toLowerCase();
                    const description = row.cells[2].textContent.toLowerCase();

                    const matchesMonth = !monthValue || month === monthValue;
                    const matchesFaculty = !facultyValue || faculty === facultyValue;
                    const matchesSearch = !searchValue || 
                        faculty.includes(searchValue) || 
                        kra.includes(searchValue) || 
                        description.includes(searchValue);

                    row.style.display = matchesMonth && matchesFaculty && matchesSearch ? '' : 'none';
                });

                // Show "No submissions" row if no rows are visible
                const noSubmissionsRow = document.querySelector('#submissionsTable tbody tr.text-muted');
                if (noSubmissionsRow) {
                    noSubmissionsRow.style.display = tableRows.length === 0 || Array.from(tableRows).every(row => row.style.display === 'none') ? '' : 'none';
                }
            }

            monthFilter.addEventListener('change', filterTable);
            facultyFilter.addEventListener('change', filterTable);
            searchInput.addEventListener('input', filterTable);

            resetButton.addEventListener('click', () => {
                monthFilter.value = '';
                facultyFilter.value = '';
                searchInput.value = '';
                filterTable();
            });

            // Initial filter call
            filterTable();
        });
    </script>
</body>
</html>