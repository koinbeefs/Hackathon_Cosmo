<?php
require 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Dean') {
    header("Location: login.php");
    exit;
}
$department_id = $_SESSION['department_id'];

// Fetch all departments
$dept_stmt = $pdo->query("SELECT department_id, department_name FROM Department ORDER BY department_name");
$departments = $dept_stmt->fetchAll();

// Fetch all KRAs
$kra_stmt = $pdo->query("SELECT kra_id, kra_name FROM KRA ORDER BY kra_name");
$kras = $kra_stmt->fetchAll();

// Initialize filter variables
$month_filter = isset($_POST['month']) ? $_POST['month'] : '';
$faculty_filter = isset($_POST['faculty']) ? $_POST['faculty'] : '';

// Validate CSRF token for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    }
}

// Fetch faculty for dropdown (all departments)
$faculty_stmt = $pdo->query("SELECT user_id, first_name, last_name, employee_number, department_id 
                             FROM Users 
                             WHERE role = 'Faculty' 
                             ORDER BY first_name, last_name");
$faculty_list = $faculty_stmt->fetchAll();

// Prepare data for individual departments
$report_data = [];
foreach ($departments as $dept) {
    $dept_id = $dept['department_id'];
    $dept_data = [];
    foreach ($kras as $kra) {
        $kra_id = $kra['kra_id'];
        $query = "SELECT COUNT(a.accomplishment_id) as count 
                  FROM KRA k 
                  LEFT JOIN Accomplishment a ON k.kra_id = a.kra_id 
                  JOIN Users u ON a.user_id = u.user_id 
                  WHERE k.kra_id = ? AND u.department_id = ?";
        $params = [$kra_id, $dept_id];
        
        if ($month_filter) {
            $query .= " AND DATE_FORMAT(a.month, '%Y-%m') = ?";
            $params[] = $month_filter;
        }
        if ($faculty_filter && $faculty_filter !== 'all') {
            $query .= " AND a.user_id = ?";
            $params[] = $faculty_filter;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $count = $stmt->fetchColumn();
        
        $dept_data[$kra_id] = [
            'kra_name' => $kra['kra_name'],
            'count' => $count
        ];
    }
    $report_data[$dept_id] = [
        'dept_name' => $dept['department_name'],
        'kras' => $dept_data
    ];
}

// Prepare data for "All Departments"
$all_depts_data = [];
foreach ($kras as $kra) {
    $kra_id = $kra['kra_id'];
    // Submission counts by department
    $query = "SELECT d.department_id, d.department_name, COUNT(a.accomplishment_id) as count 
              FROM Department d 
              LEFT JOIN Users u ON u.department_id = d.department_id 
              LEFT JOIN Accomplishment a ON a.user_id = u.user_id AND a.kra_id = ? 
              WHERE u.role = 'Faculty'";
    $params = [$kra_id];
    
    if ($month_filter) {
        $query .= " AND DATE_FORMAT(a.month, '%Y-%m') = ?";
        $params[] = $month_filter;
    }
    if ($faculty_filter && $faculty_filter !== 'all') {
        $query .= " AND a.user_id = ?";
        $params[] = $faculty_filter;
    }
    
    $query .= " GROUP BY d.department_id, d.department_name ORDER BY count DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $dept_counts = $stmt->fetchAll();
    
    // Non-submitting faculty by department
    $non_submit_counts = [];
    foreach ($departments as $dept) {
        $dept_id = $dept['department_id'];
        $non_submit_query = "SELECT COUNT(DISTINCT u.user_id) as non_submitters 
                             FROM Users u 
                             LEFT JOIN Accomplishment a ON a.user_id = u.user_id AND a.kra_id = ? 
                             WHERE u.role = 'Faculty' AND u.department_id = ? AND a.accomplishment_id IS NULL";
        $non_submit_params = [$kra_id, $dept_id];
        
        if ($month_filter) {
            $non_submit_query .= " AND DATE_FORMAT(a.month, '%Y-%m') = ?";
            $non_submit_params[] = $month_filter;
        }
        if ($faculty_filter && $faculty_filter !== 'all') {
            $non_submit_query .= " AND u.user_id = ?";
            $non_submit_params[] = $faculty_filter;
        }
        
        $non_submit_stmt = $pdo->prepare($non_submit_query);
        $non_submit_stmt->execute($non_submit_params);
        $non_submitters = $non_submit_stmt->fetchColumn();
        
        $non_submit_counts[$dept_id] = [
            'department_name' => $dept['department_name'],
            'non_submitters' => $non_submitters
        ];
    }
    
    $all_depts_data[$kra_id] = [
        'kra_name' => $kra['kra_name'],
        'dept_counts' => $dept_counts,
        'non_submit_counts' => $non_submit_counts
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Statistics</title>
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
        .nav-tabs .nav-link {
            border: none;
            border-radius: 10px 10px 0 0;
            color: #2c3e50;
            transition: all 0.3s;
        }
        .nav-tabs .nav-link:hover {
            background: #e9ecef;
        }
        .nav-tabs .nav-link.active {
            background: #3498db;
            color: #fff;
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding-left: 2.5rem;
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
                <a href="review_accomplishments.php" class="nav-link">
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
                <a href="report_statistics.php" class="nav-link active">
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

    <!-- Main Content -->
    <div class="content">
        <h2 class="mb-4"><i class="fas fa-table me-2"></i>Performance Statistics</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <!-- Filter Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="col-md-4">
                        <label for="month" class="form-label">Month</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            <input type="month" class="form-control" id="month" name="month" value="<?php echo htmlspecialchars($month_filter); ?>" placeholder="Select month">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="faculty" class="form-label">Faculty</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <select class="form-select" id="faculty" name="faculty">
                                <option value="all" <?php echo $faculty_filter === 'all' || !$faculty_filter ? 'selected' : ''; ?>>All Faculty</option>
                                <?php foreach ($faculty_list as $faculty): ?>
                                    <option value="<?php echo $faculty['user_id']; ?>" <?php echo $faculty_filter === $faculty['user_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name'] . ' (' . $faculty['employee_number'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-gradient"><i class="fas fa-filter me-1"></i>Apply Filters</button>
                        <button type="button" class="btn btn-gradient-reset" onclick="document.getElementById('month').value='';document.getElementById('faculty').value='all';this.form.submit();"><i class="fas fa-undo me-1"></i>Reset</button>
                    </div>
                </form>
            </div>
        </div>
        <!-- Department Tabs -->
        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs mb-4" id="deptTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" 
                                id="all-depts-tab" 
                                data-bs-toggle="tab" 
                                data-bs-target="#all-depts" 
                                type="button" 
                                role="tab" 
                                aria-controls="all-depts" 
                                aria-selected="true">
                            All Departments
                        </button>
                    </li>
                    <?php foreach ($departments as $index => $dept): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" 
                                    id="dept-<?php echo $dept['department_id']; ?>-tab" 
                                    data-bs-toggle="tab" 
                                    data-bs-target="#dept-<?php echo $dept['department_id']; ?>" 
                                    type="button" 
                                    role="tab" 
                                    aria-controls="dept-<?php echo $dept['department_id']; ?>" 
                                    aria-selected="false">
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="tab-content" id="deptTabsContent">
                    <!-- All Departments Tab -->
                    <div class="tab-pane fade show active" 
                         id="all-depts" 
                         role="tabpanel" 
                         aria-labelledby="all-depts-tab">
                        <!-- KRA Tabs -->
                        <ul class="nav nav-tabs mb-4" id="kraTabs-all" role="tablist">
                            <?php foreach ($kras as $kra_index => $kra): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo $kra_index === 0 ? 'active' : ''; ?>" 
                                            id="kra-all-<?php echo $kra['kra_id']; ?>-tab" 
                                            data-bs-toggle="tab" 
                                            data-bs-target="#kra-all-<?php echo $kra['kra_id']; ?>" 
                                            type="button" 
                                            role="tab" 
                                            aria-controls="kra-all-<?php echo $kra['kra_id']; ?>" 
                                            aria-selected="<?php echo $kra_index === 0 ? 'true' : 'false'; ?>">
                                        <?php echo htmlspecialchars($kra['kra_name']); ?>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="tab-content" id="kraTabsContent-all">
                            <?php foreach ($kras as $kra_index => $kra): ?>
                                <?php
                                $kra_id = $kra['kra_id'];
                                $dept_counts = $all_depts_data[$kra_id]['dept_counts'];
                                $non_submit_counts = $all_depts_data[$kra_id]['non_submit_counts'];
                                ?>
                                <div class="tab-pane fade <?php echo $kra_index === 0 ? 'show active' : ''; ?>" 
                                     id="kra-all-<?php echo $kra['kra_id']; ?>" 
                                     role="tabpanel" 
                                     aria-labelledby="kra-all-<?php echo $kra['kra_id']; ?>-tab">
                                    <div class="table-responsive mb-4">
                                        <table class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Department</th>
                                                    <th>KRA</th>
                                                    <th>Submissions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($dept_counts)): ?>
                                                    <tr>
                                                        <td colspan="3" class="text-muted">No submissions found.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($dept_counts as $dept_count): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($dept_count['department_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($kra['kra_name']); ?></td>
                                                            <td><?php echo $dept_count['count']; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Department</th>
                                                    <th>KRA</th>
                                                    <th>Non-Submitters</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($non_submit_counts)): ?>
                                                    <tr>
                                                        <td colspan="3" class="text-muted">No non-submitters found.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($non_submit_counts as $non_submit): ?>
                                                        <?php if ($non_submit['non_submitters'] > 0): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($non_submit['department_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($kra['kra_name']); ?></td>
                                                                <td><?php echo $non_submit['non_submitters']; ?></td>
                                                            </tr>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!-- Individual Department Tabs -->
                    <?php foreach ($departments as $index => $dept): ?>
                        <div class="tab-pane fade" 
                             id="dept-<?php echo $dept['department_id']; ?>" 
                             role="tabpanel" 
                             aria-labelledby="dept-<?php echo $dept['department_id']; ?>-tab">
                            <!-- KRA Tabs -->
                            <ul class="nav nav-tabs mb-4" id="kraTabs-<?php echo $dept['department_id']; ?>" role="tablist">
                                <?php foreach ($kras as $kra_index => $kra): ?>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link <?php echo $kra_index === 0 ? 'active' : ''; ?>" 
                                                id="kra-<?php echo $dept['department_id']; ?>-<?php echo $kra['kra_id']; ?>-tab" 
                                                data-bs-toggle="tab" 
                                                data-bs-target="#kra-<?php echo $dept['department_id']; ?>-<?php echo $kra['kra_id']; ?>" 
                                                type="button" 
                                                role="tab" 
                                                aria-controls="kra-<?php echo $dept['department_id']; ?>-<?php echo $kra['kra_id']; ?>" 
                                                aria-selected="<?php echo $kra_index === 0 ? 'true' : 'false'; ?>">
                                            <?php echo htmlspecialchars($kra['kra_name']); ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div class="tab-content" id="kraTabsContent-<?php echo $dept['department_id']; ?>">
                                <?php foreach ($kras as $kra_index => $kra): ?>
                                    <div class="tab-pane fade <?php echo $kra_index === 0 ? 'show active' : ''; ?>" 
                                         id="kra-<?php echo $dept['department_id']; ?>-<?php echo $kra['kra_id']; ?>" 
                                         role="tabpanel" 
                                         aria-labelledby="kra-<?php echo $dept['department_id']; ?>-<?php echo $kra['kra_id']; ?>-tab">
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>KRA</th>
                                                        <th>Submissions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($kra['kra_name']); ?></td>
                                                        <td><?php echo $report_data[$dept['department_id']]['kras'][$kra['kra_id']]['count']; ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>