<?php
require 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Faculty') {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT a.*, k.kra_name, u.first_name, u.last_name, u.employee_number 
                       FROM Accomplishment a 
                       JOIN KRA k ON a.kra_id = k.kra_id 
                       LEFT JOIN Users u ON a.reviewer_id = u.user_id 
                       WHERE a.user_id = ? 
                       ORDER BY a.created_at DESC");
$stmt->execute([$user_id]);
$accomplishments = $stmt->fetchAll();

// Prepare unique months, KRAs, and statuses for filters
$months = array_unique(array_map(function($acc) {
    return date('F Y', strtotime($acc['month']));
}, $accomplishments));
sort($months);
$kras = array_unique(array_map(function($acc) {
    return $acc['kra_name'];
}, $accomplishments));
sort($kras);
$statuses = array_unique(array_map(function($acc) {
    return $acc['status'];
}, $accomplishments));
sort($statuses);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Accomplishments</title>
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
        h2 {
            color: #2c3e50;
            font-weight: 700;
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
        .no-data-card {
            text-align: center;
            padding: 2rem;
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
                <a href="submit_accomplishment.php" class="nav-link">
                    <i class="fas fa-plus-circle"></i>
                    <span>Submit Accomplishment</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="view_accomplishments.php" class="nav-link active">
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
        <h2 class="mb-4"><i class="fas fa-list me-2"></i>My Accomplishments</h2>
        <?php if (empty($accomplishments)): ?>
            <div class="card no-data-card">
                <div class="card-body">
                    <p class="text-muted">No accomplishments found.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="filter-group mb-3">
                        <select id="monthFilter" class="form-select">
                            <option value="">All Months</option>
                            <?php foreach ($months as $month): ?>
                                <option value="<?php echo htmlspecialchars($month); ?>"><?php echo htmlspecialchars($month); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="kraFilter" class="form-select">
                            <option value="">All KRAs</option>
                            <?php foreach ($kras as $kra): ?>
                                <option value="<?php echo htmlspecialchars($kra); ?>"><?php echo htmlspecialchars($kra); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="statusFilter" class="form-select">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="input-group" style="max-width: 300px;">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search accomplishments...">
                        </div>
                        <button id="resetFilters" class="btn btn-gradient-reset">Reset</button>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="accomplishmentsTable">
                            <thead>
                                <tr>
                                    <th>KRA</th>
                                    <th>Description</th>
                                    <th>Month</th>
                                    <th>Status</th>
                                    <th>File</th>
                                    <th>Reviewer (Emp#)</th>
                                    <th>Comments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accomplishments as $acc): ?>
                                    <tr data-month="<?php echo htmlspecialchars(date('F Y', strtotime($acc['month']))); ?>" 
                                        data-kra="<?php echo htmlspecialchars($acc['kra_name']); ?>" 
                                        data-status="<?php echo htmlspecialchars($acc['status']); ?>">
                                        <td><?php echo htmlspecialchars($acc['kra_name']); ?></td>
                                        <td><?php echo htmlspecialchars($acc['description']); ?></td>
                                        <td><?php echo htmlspecialchars(date('F Y', strtotime($acc['month']))); ?></td>
                                        <td>
                                            <span class="badge <?php echo $acc['status'] === 'Approved' ? 'bg-success' : ($acc['status'] === 'Rejected' ? 'bg-danger' : 'bg-warning'); ?>">
                                                <?php echo htmlspecialchars($acc['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($acc['file_path']): ?>
                                                <a href="/project/<?php echo htmlspecialchars($acc['file_path']); ?>" target="_blank" class="btn btn-link btn-sm"><i class="fas fa-download"></i> Download</a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $acc['reviewer_id'] ? htmlspecialchars($acc['first_name'] . ' ' . $acc['last_name'] . ' (' . $acc['employee_number'] . ')') : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($acc['review_comments'] ?: '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap 5 JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const monthFilter = document.getElementById('monthFilter');
            const kraFilter = document.getElementById('kraFilter');
            const statusFilter = document.getElementById('statusFilter');
            const searchInput = document.getElementById('searchInput');
            const resetButton = document.getElementById('resetFilters');
            const tableRows = document.querySelectorAll('#accomplishmentsTable tbody tr');

            function filterTable() {
                const monthValue = monthFilter.value.toLowerCase();
                const kraValue = kraFilter.value.toLowerCase();
                const statusValue = statusFilter.value.toLowerCase();
                const searchValue = searchInput.value.toLowerCase();

                let visibleRows = 0;
                tableRows.forEach(row => {
                    const month = row.dataset.month.toLowerCase();
                    const kra = row.dataset.kra.toLowerCase();
                    const status = row.dataset.status.toLowerCase();
                    const description = row.cells[1].textContent.toLowerCase();
                    const reviewer = row.cells[5].textContent.toLowerCase();
                    const comments = row.cells[6].textContent.toLowerCase();

                    const matchesMonth = !monthValue || month === monthValue;
                    const matchesKra = !kraValue || kra === kraValue;
                    const matchesStatus = !statusValue || status === statusValue;
                    const matchesSearch = !searchValue || 
                        kra.includes(searchValue) || 
                        description.includes(searchValue) || 
                        reviewer.includes(searchValue) || 
                        comments.includes(searchValue);

                    if (matchesMonth && matchesKra && matchesStatus && matchesSearch) {
                        row.style.display = '';
                        visibleRows++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Toggle no-data card visibility
                const noDataCard = document.querySelector('.no-data-card');
                if (noDataCard) {
                    noDataCard.style.display = visibleRows === 0 && tableRows.length > 0 ? '' : 'none';
                }
            }

            monthFilter.addEventListener('change', filterTable);
            kraFilter.addEventListener('change', filterTable);
            statusFilter.addEventListener('change', filterTable);
            searchInput.addEventListener('input', filterTable);

            resetButton.addEventListener('click', () => {
                monthFilter.value = '';
                kraFilter.value = '';
                statusFilter.value = '';
                searchInput.value = '';
                filterTable();
            });

            // Initial filter call
            filterTable();
        });
    </script>
</body>
</html>