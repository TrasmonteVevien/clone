<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get statistics
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'total_jobs' => $conn->query("SELECT COUNT(*) as count FROM jobs")->fetch_assoc()['count'],
    'total_applications' => $conn->query("SELECT COUNT(*) as count FROM applications")->fetch_assoc()['count'],
    'active_jobseekers' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'jobseeker' AND status = 'active'")->fetch_assoc()['count'],
    'active_employers' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employer' AND status = 'active'")->fetch_assoc()['count'],
    'pending_jobs' => $conn->query("SELECT COUNT(*) as count FROM jobs WHERE status = 'open'")->fetch_assoc()['count']
];

// Get recent activities
$recent_jobs = $conn->query("
    SELECT j.*, e.company_name 
    FROM jobs j 
    JOIN employers e ON j.employer_id = e.employer_id 
    ORDER BY j.posted_date DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$recent_applications = $conn->query("
    SELECT a.*, j.title as job_title, js.first_name, js.last_name 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.job_id 
    JOIN jobseekers js ON a.jobseeker_id = js.jobseeker_id 
    ORDER BY a.application_date DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - JPost</title>
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
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="jobs.php">Jobs</a>
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
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Users</h5>
                        <h2 class="card-text"><?php echo $stats['total_users']; ?></h2>
                        <p class="card-text">
                            <small>
                                <?php echo $stats['active_jobseekers']; ?> Jobseekers<br>
                                <?php echo $stats['active_employers']; ?> Employers
                            </small>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Jobs</h5>
                        <h2 class="card-text"><?php echo $stats['total_jobs']; ?></h2>
                        <p class="card-text">
                            <small>
                                <?php echo $stats['pending_jobs']; ?> Active Jobs
                            </small>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Applications</h5>
                        <h2 class="card-text"><?php echo $stats['total_applications']; ?></h2>
                        <p class="card-text">
                            <small>All Time Applications</small>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Jobs -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Jobs</h5>
                        <a href="jobs.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_jobs)): ?>
                            <p class="text-muted">No recent jobs found.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recent_jobs as $job): ?>
                                    <a href="job_details.php?id=<?php echo $job['job_id']; ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h6>
                                            <small><?php echo date('M d, Y', strtotime($job['posted_date'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                        <small>
                                            <span class="badge bg-<?php echo $job['status'] === 'open' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($job['status']); ?>
                                            </span>
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Applications -->
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Applications</h5>
                        <a href="applications.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_applications)): ?>
                            <p class="text-muted">No recent applications found.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recent_applications as $application): ?>
                                    <a href="application_details.php?id=<?php echo $application['application_id']; ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($application['job_title']); ?></h6>
                                            <small><?php echo date('M d, Y', strtotime($application['application_date'])); ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                                        </p>
                                        <small>
                                            <span class="badge bg-<?php 
                                                echo match($application['status']) {
                                                    'pending' => 'warning',
                                                    'reviewed' => 'info',
                                                    'shortlisted' => 'primary',
                                                    'rejected' => 'danger',
                                                    'hired' => 'success',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($application['status']); ?>
                                            </span>
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 