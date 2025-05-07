<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an employer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: ../login.php");
    exit();
}

// Get employer details
$employer_sql = "SELECT * FROM employers WHERE user_id = ?";
$employer_stmt = $conn->prepare($employer_sql);
$employer_stmt->bind_param("i", $_SESSION['user_id']);
$employer_stmt->execute();
$employer = $employer_stmt->get_result()->fetch_assoc();

// Get statistics
$stats = [
    'total_jobs' => $conn->query("SELECT COUNT(*) as count FROM jobs WHERE employer_id = " . $employer['employer_id'])->fetch_assoc()['count'],
    'active_jobs' => $conn->query("SELECT COUNT(*) as count FROM jobs WHERE employer_id = " . $employer['employer_id'] . " AND status = 'open'")->fetch_assoc()['count'],
    'total_applications' => $conn->query("
        SELECT COUNT(*) as count 
        FROM applications a 
        JOIN jobs j ON a.job_id = j.job_id 
        WHERE j.employer_id = " . $employer['employer_id'])->fetch_assoc()['count'],
    'pending_applications' => $conn->query("
        SELECT COUNT(*) as count 
        FROM applications a 
        JOIN jobs j ON a.job_id = j.job_id 
        WHERE j.employer_id = " . $employer['employer_id'] . " 
        AND a.status = 'pending'")->fetch_assoc()['count'],
    'shortlisted_candidates' => $conn->query("
        SELECT COUNT(*) as count 
        FROM applications a 
        JOIN jobs j ON a.job_id = j.job_id 
        WHERE j.employer_id = " . $employer['employer_id'] . " 
        AND a.status = 'shortlisted'")->fetch_assoc()['count'],
    'hired_candidates' => $conn->query("
        SELECT COUNT(*) as count 
        FROM applications a 
        JOIN jobs j ON a.job_id = j.job_id 
        WHERE j.employer_id = " . $employer['employer_id'] . " 
        AND a.status = 'hired'")->fetch_assoc()['count']
];

// Get recent jobs
$recent_jobs_sql = "SELECT * FROM jobs WHERE employer_id = ? ORDER BY posted_date DESC LIMIT 5";
$recent_jobs_stmt = $conn->prepare($recent_jobs_sql);
$recent_jobs_stmt->bind_param("i", $employer['employer_id']);
$recent_jobs_stmt->execute();
$recent_jobs = $recent_jobs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent applications
$recent_applications_sql = "
    SELECT a.*, j.title as job_title, 
           CONCAT(js.first_name, ' ', js.last_name) as applicant_name,
           js.profile_image as profile_picture, r.title as resume_title
    FROM applications a 
    JOIN jobs j ON a.job_id = j.job_id 
    JOIN jobseekers js ON a.user_id = js.user_id
    LEFT JOIN resumes r ON a.resume_id = r.resume_id
    WHERE j.employer_id = ? 
    ORDER BY a.application_date DESC 
    LIMIT 5";
$recent_applications_stmt = $conn->prepare($recent_applications_sql);
$recent_applications_stmt->bind_param("i", $employer['employer_id']);
$recent_applications_stmt->execute();
$recent_applications = $recent_applications_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get applications by status
$applications_by_status_sql = "
    SELECT a.status, COUNT(*) as count 
    FROM applications a 
    JOIN jobs j ON a.job_id = j.job_id 
    WHERE j.employer_id = ? 
    GROUP BY a.status";
$applications_by_status_stmt = $conn->prepare($applications_by_status_sql);
$applications_by_status_stmt->bind_param("i", $employer['employer_id']);
$applications_by_status_stmt->execute();
$applications_by_status = $applications_by_status_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Prepare data for the applications chart
$status_labels = [];
$status_data = [];
foreach ($applications_by_status as $status) {
    $status_labels[] = ucfirst($status['status']);
    $status_data[] = $status['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard - JPost</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <a class="nav-link" href="jobs.php">Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="applications.php">Applications</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Company Profile</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($employer['company_name']); ?>
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
        <!-- Welcome Section -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <?php if ($employer['logo']): ?>
                        <img src="../uploads/company/<?php echo htmlspecialchars($employer['logo']); ?>" 
                             class="rounded me-3" style="width: 64px; height: 64px; object-fit: cover;">
                    <?php endif; ?>
                    <div>
                        <h4 class="mb-1">Welcome, <?php echo htmlspecialchars($employer['company_name']); ?>!</h4>
                        <p class="text-muted mb-0">Here's what's happening with your job postings and applications.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Jobs</h5>
                        <h2 class="mb-0"><?php echo $stats['total_jobs']; ?></h2>
                        <p class="mb-0">
                            <small><?php echo $stats['active_jobs']; ?> Active Jobs</small>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Applications</h5>
                        <h2 class="mb-0"><?php echo $stats['total_applications']; ?></h2>
                        <p class="mb-0">
                            <small><?php echo $stats['pending_applications']; ?> Pending</small>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Shortlisted</h5>
                        <h2 class="mb-0"><?php echo $stats['shortlisted_candidates']; ?></h2>
                        <p class="mb-0">
                            <small>Potential Candidates</small>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Hired</h5>
                        <h2 class="mb-0"><?php echo $stats['hired_candidates']; ?></h2>
                        <p class="mb-0">
                            <small>Successful Placements</small>
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
                            <p class="text-muted">No jobs posted yet.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recent_jobs as $job): ?>
                                    <a href="job_details.php?id=<?php echo $job['job_id']; ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h6>
                                            <small><?php echo date('M d, Y', strtotime($job['posted_date'])); ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <span class="badge bg-<?php echo $job['status'] === 'open' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($job['status']); ?>
                                            </span>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($job['type']); ?></span>
                                        </p>
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
                            <p class="text-muted">No applications received yet.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recent_applications as $application): ?>
                                    <a href="application_details.php?id=<?php echo $application['application_id']; ?>" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex align-items-center">
                                            <?php if ($application['profile_picture']): ?>
                                                <img src="../uploads/profile/<?php echo htmlspecialchars($application['profile_picture']); ?>" 
                                                     class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-secondary rounded-circle me-3" 
                                                     style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="bi bi-person-fill text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($application['applicant_name']); ?></h6>
                                                    <small><?php echo date('M d, Y', strtotime($application['application_date'])); ?></small>
                                                </div>
                                                <p class="mb-1"><?php echo htmlspecialchars($application['job_title']); ?></p>
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
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applications Chart -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-4">Applications Overview</h5>
                <canvas id="applicationsChart"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Applications Chart
        new Chart(document.getElementById('applicationsChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($status_data); ?>,
                    backgroundColor: [
                        '#ffc107', // pending
                        '#0dcaf0', // reviewed
                        '#0d6efd', // shortlisted
                        '#dc3545', // rejected
                        '#198754'  // hired
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html> 