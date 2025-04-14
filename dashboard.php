<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$department_id = $_SESSION['department_id'];

$stmt = $pdo->prepare("SELECT a.*, k.kra_name FROM Accomplishment a JOIN KRA k ON a.kra_id = k.kra_id WHERE a.user_id = ? ORDER BY a.created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_submissions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT k.kra_name, COUNT(a.accomplishment_id) as count 
                       FROM KRA k 
                       LEFT JOIN Accomplishment a ON k.kra_id = a.kra_id AND a.user_id = ? 
                       GROUP BY k.kra_id");
$stmt->execute([$user_id]);
$kra_coverage = $stmt->fetchAll();

$pending_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Accomplishment WHERE user_id = ? AND status = 'Pending'");
$pending_stmt->execute([$user_id]);
$pending_faculty = $pending_stmt->fetch()['count'];

$dean_pending_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Accomplishment JOIN Users ON Accomplishment.user_id = Users.user_id WHERE Users.department_id = ? AND status = 'Pending'");
$dean_pending_stmt->execute([$department_id]);
$pending_dean = $dean_pending_stmt->fetch()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($role); ?></title>
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
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-title {
            font-weight: 600;
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
            color: #fff;
            transform: scale(1.05);
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
        h2 {
            color: #2c3e50;
            font-weight: 700;
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
    <div class="sidebar">
        <div class="sidebar-header">
            Dashboard
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <?php if ($role === 'Faculty'): ?>
                <li class="nav-item">
                    <a href="submit_accomplishment.php" class="nav-link">
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
            <?php elseif ($role === 'Dean'): ?>
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
                    <a href="report_statistics.php" class="nav-link">
                        <i class="fas fa-table"></i>
                        <span>Performance Statistics</span>
                    </a>
                </li>
            <?php endif; ?>
            <li class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="content">
        <h2 class="mb-4">Welcome, <?php echo htmlspecialchars($_SESSION['role']); ?>!</h2>
        <div class="row g-4">
            <?php if ($role === 'Faculty'): ?>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-plus-circle me-2"></i>Submit Accomplishment</h5>
                            <p class="card-text">Log your monthly achievements.</p>
                            <a href="submit_accomplishment.php" class="btn btn-gradient">Submit Now</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-list me-2"></i>View Accomplishments</h5>
                            <p class="card-text">Check your submission history.</p>
                            <a href="view_accomplishments.php" class="btn btn-gradient">View History</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-hourglass-half me-2"></i>Pending Approvals</h5>
                            <p class="card-text"><?php echo $pending_faculty; ?> pending submissions.</p>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-clock me-2"></i>Recent Submissions</h5>
                            <?php if (empty($recent_submissions)): ?>
                                <p class="text-muted">No recent submissions.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>KRA</th>
                                                <th>Description</th>
                                                <th>Month</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_submissions as $sub): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($sub['kra_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($sub['description']); ?></td>
                                                    <td><?php echo date('F Y', strtotime($sub['month'])); ?></td>
                                                    <td><?php echo htmlspecialchars($sub['status']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
               

                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-chart-pie me-2"></i>KRA Coverage</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>KRA</th>
                                            <th>Submissions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($kra_coverage as $kra): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($kra['kra_name']); ?></td>
                                                <td><?php echo $kra['count']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($role === 'Dean'): ?>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-check-circle me-2"></i>Review Submissions</h5>
                            <p class="card-text">Approve or reject faculty submissions.</p>
                            <a href="review_accomplishments.php" class="btn btn-gradient">Review Now</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-chart-bar me-2"></i>Performance Reports</h5>
                            <p class="card-text">View department trends and gaps.</p>
                            <a href="reports.php" class="btn btn-gradient">View Reports</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-hourglass-half me-2"></i>Pending Reviews</h5>
                            <p class="card-text"><?php echo $pending_dean; ?> submissions to review.</p>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-users me-2"></i>Department Summary</h5>
                            <?php
                            $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, u.employee_number, COUNT(a.accomplishment_id) as count 
                                                   FROM Users u 
                                                   LEFT JOIN Accomplishment a ON u.user_id = a.user_id 
                                                   WHERE u.department_id = ? AND u.role = 'Faculty' 
                                                   GROUP BY u.user_id 
                                                   ORDER BY count DESC 
                                                   LIMIT 5");
                            $stmt->execute([$department_id]);
                            $top_contributors = $stmt->fetchAll();
                            ?>
                            <h6>Top Contributors</h6>
                            <?php if (empty($top_contributors)): ?>
                                <p class="text-muted">No submissions yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Faculty (Emp#)</th>
                                                <th>Submissions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_contributors as $contributor): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($contributor['first_name'] . ' ' . $contributor['last_name'] . ' (' . $contributor['employee_number'] . ')'); ?></td>
                                                    <td><?php echo $contributor['count']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>