<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

// Get jobseeker information
$user_id = $_SESSION['user_id'];
$sql = "SELECT j.*, u.email 
        FROM jobseekers j 
        JOIN users u ON j.user_id = u.user_id 
        WHERE j.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$jobseeker = $stmt->get_result()->fetch_assoc();

// Get active resume
$resume_sql = "SELECT * FROM resumes WHERE jobseeker_id = ? AND is_active = 1";
$resume_stmt = $conn->prepare($resume_sql);
$resume_stmt->bind_param("i", $jobseeker['jobseeker_id']);
$resume_stmt->execute();
$active_resume = $resume_stmt->get_result()->fetch_assoc();

// Get recent applications
$applications_sql = "SELECT a.*, j.title, e.company_name, j.location 
                    FROM applications a 
                    JOIN jobs j ON a.job_id = j.job_id 
                    JOIN employers e ON j.employer_id = e.employer_id
                    WHERE a.jobseeker_id = ? 
                    ORDER BY a.application_date DESC 
                    LIMIT 5";
$applications_stmt = $conn->prepare($applications_sql);
$applications_stmt->bind_param("i", $jobseeker['jobseeker_id']);
$applications_stmt->execute();
$recent_applications = $applications_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent job listings
$jobs_sql = "SELECT j.*, e.company_name 
             FROM jobs j 
             JOIN employers e ON j.employer_id = e.employer_id 
             WHERE j.status = 'open' 
             ORDER BY j.posted_date DESC 
             LIMIT 5";
$jobs_result = $conn->query($jobs_sql);
$recent_jobs = $jobs_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobseeker Dashboard - JPost</title>
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
                        <a class="nav-link" href="jobs.php">Browse Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="applications.php">My Applications</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="resume.php">Resume</a></li>
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
        <div class="row">
            <!-- Profile Summary -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Profile Summary</h5>
                        <p class="card-text">
                            <strong>Name:</strong> <?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?><br>
                            <strong>Email:</strong> <?php echo htmlspecialchars($jobseeker['email']); ?><br>
                            <strong>Phone:</strong> <?php echo htmlspecialchars($jobseeker['phone']); ?>
                        </p>
                        <a href="profile.php" class="btn btn-outline-primary btn-sm">Edit Profile</a>
                    </div>
                </div>

                <!-- Resume Status -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Resume Status</h5>
                        <?php if($active_resume): ?>
                            <p class="text-success">
                                <i class="bi bi-check-circle-fill"></i> Active resume uploaded
                            </p>
                            <a href="resume.php" class="btn btn-outline-primary btn-sm">Update Resume</a>
                        <?php else: ?>
                            <p class="text-danger">
                                <i class="bi bi-exclamation-circle-fill"></i> No active resume
                            </p>
                            <a href="resume.php" class="btn btn-primary btn-sm">Upload Resume</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Applications -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Recent Applications</h5>
                        <?php if($recent_applications): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Job Title</th>
                                            <th>Company</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recent_applications as $application): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($application['title']); ?></td>
                                                <td><?php echo htmlspecialchars($application['company_name']); ?></td>
                                                <td>
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
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($application['application_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="applications.php" class="btn btn-outline-primary btn-sm">View All Applications</a>
                        <?php else: ?>
                            <p class="text-muted">No applications yet.</p>
                            <a href="jobs.php" class="btn btn-primary btn-sm">Browse Jobs</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Job Listings -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Recent Job Listings</h5>
                        <?php if($recent_jobs): ?>
                            <div class="list-group">
                                <?php foreach($recent_jobs as $job): ?>
                                    <a href="job-details.php?id=<?php echo $job['job_id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h6>
                                            <small><?php echo date('M d, Y', strtotime($job['posted_date'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                        <small class="text-muted">
                                            <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($job['location']); ?> |
                                            <i class="bi bi-briefcase"></i> <?php echo ucfirst($job['job_type']); ?>
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <a href="jobs.php" class="btn btn-outline-primary btn-sm mt-3">View All Jobs</a>
                        <?php else: ?>
                            <p class="text-muted">No recent job listings available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 