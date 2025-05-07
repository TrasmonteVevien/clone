<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get filter parameters
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$category = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';
$type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Build query
$sql = "SELECT j.*, e.company_name, e.logo 
        FROM jobs j 
        JOIN employers e ON j.employer_id = e.employer_id 
        WHERE 1=1";

$params = [];
$types = "";

if ($status) {
    $sql .= " AND j.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($category) {
    $sql .= " AND j.category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($type) {
    $sql .= " AND j.type = ?";
    $params[] = $type;
    $types .= "s";
}

if ($search) {
    $sql .= " AND (j.title LIKE ? OR j.description LIKE ? OR e.company_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$sql .= " ORDER BY j.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get distinct categories and types for filters
$categories_sql = "SELECT DISTINCT category FROM jobs ORDER BY category";
$categories = $conn->query($categories_sql)->fetch_all(MYSQLI_ASSOC);

$types_sql = "SELECT DISTINCT type FROM jobs ORDER BY type";
$types = $conn->query($types_sql)->fetch_all(MYSQLI_ASSOC);

// Handle job status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['job_id']) && isset($_POST['status'])) {
    $job_id = (int)$_POST['job_id'];
    $new_status = $conn->real_escape_string($_POST['status']);
    
    $update_sql = "UPDATE jobs SET status = ? WHERE job_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $job_id);
    
    if ($update_stmt->execute()) {
        header("Location: jobs.php?success=1");
        exit();
    } else {
        header("Location: jobs.php?error=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - JPost</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">JPost</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="jobs.php">Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="applications.php">Applications</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            Admin
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-4">
        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success">Job status updated successfully!</div>
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-danger">Error updating job status. Please try again.</div>
        <?php endif; ?>

        <!-- Search and Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Job title, description, or company">
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="open" <?php echo $status === 'open' ? 'selected' : ''; ?>>Open</option>
                            <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                        <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">All Types</option>
                            <?php foreach ($types as $t): ?>
                                <option value="<?php echo htmlspecialchars($t['type']); ?>" 
                                        <?php echo $type === $t['type'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Jobs List -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Company</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Posted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($job['title']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($job['logo']): ?>
                                                <img src="../uploads/company/<?php echo htmlspecialchars($job['logo']); ?>" 
                                                     class="rounded me-2" style="width: 30px; height: 30px; object-fit: cover;">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($job['company_name']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($job['category']); ?></td>
                                    <td><?php echo htmlspecialchars($job['type']); ?></td>
                                    <td>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                            <input type="hidden" name="status" 
                                                   value="<?php echo $job['status'] === 'open' ? 'closed' : 'open'; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php 
                                                echo $job['status'] === 'open' ? 'success' : 'secondary'; 
                                            ?>">
                                                <?php echo ucfirst($job['status']); ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                                    <td>
                                        <a href="job_details.php?id=<?php echo $job['job_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 