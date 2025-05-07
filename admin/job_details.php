<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get job ID from URL
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$job_id) {
    header("Location: jobs.php");
    exit();
}

// Get job details
$sql = "SELECT j.*, e.company_name, e.logo, e.website, e.company_description
        FROM jobs j 
        JOIN employers e ON j.employer_id = e.employer_id 
        WHERE j.job_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    header("Location: jobs.php");
    exit();
}

// Get applications for this job
$applications_sql = "SELECT a.*, u.email, u.username,
                    CASE 
                        WHEN u.role = 'jobseeker' THEN CONCAT(j.first_name, ' ', j.last_name)
                        ELSE 'Unknown'
                    END as applicant_name,
                    r.title as resume_title
                    FROM applications a 
                    JOIN users u ON a.user_id = u.user_id
                    LEFT JOIN jobseekers j ON u.user_id = j.user_id
                    LEFT JOIN resumes r ON a.resume_id = r.resume_id
                    WHERE a.job_id = ?
                    ORDER BY a.created_at DESC";

$applications_stmt = $conn->prepare($applications_sql);
$applications_stmt->bind_param("i", $job_id);
$applications_stmt->execute();
$applications = $applications_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle application status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['application_id']) && isset($_POST['status'])) {
    $application_id = (int)$_POST['application_id'];
    $new_status = $conn->real_escape_string($_POST['status']);
    
    $update_sql = "UPDATE applications SET status = ? WHERE application_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $application_id);
    
    if ($update_stmt->execute()) {
        header("Location: job_details.php?id=$job_id&success=1");
        exit();
    } else {
        header("Location: job_details.php?id=$job_id&error=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Details - JPost</title>
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
            <div class="alert alert-success">Application status updated successfully!</div>
        <?php endif; ?>

        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-danger">Error updating application status. Please try again.</div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">Job Details</h1>
            <a href="jobs.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Jobs
            </a>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- Job Information -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h2 class="h4 mb-1"><?php echo htmlspecialchars($job['title']); ?></h2>
                                <p class="text-muted mb-0">
                                    <i class="bi bi-building"></i> <?php echo htmlspecialchars($job['company_name']); ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-<?php echo $job['status'] === 'open' ? 'success' : 'secondary'; ?> mb-2">
                                    <?php echo ucfirst($job['status']); ?>
                                </span>
                                <p class="text-muted mb-0">
                                    Posted <?php echo date('M d, Y', strtotime($job['created_at'])); ?>
                                </p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <p class="mb-1">
                                    <i class="bi bi-briefcase"></i> <?php echo htmlspecialchars($job['type']); ?>
                                </p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1">
                                    <i class="bi bi-tag"></i> <?php echo htmlspecialchars($job['category']); ?>
                                </p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1">
                                    <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($job['location']); ?>
                                </p>
                            </div>
                        </div>

                        <h5 class="mb-3">Description</h5>
                        <div class="mb-4">
                            <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                        </div>

                        <h5 class="mb-3">Requirements</h5>
                        <div class="mb-4">
                            <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                        </div>

                        <?php if ($job['benefits']): ?>
                            <h5 class="mb-3">Benefits</h5>
                            <div>
                                <?php echo nl2br(htmlspecialchars($job['benefits'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Applications -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Applications (<?php echo count($applications); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($applications)): ?>
                            <p class="text-muted mb-0">No applications yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Applicant</th>
                                            <th>Resume</th>
                                            <th>Status</th>
                                            <th>Applied</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applications as $application): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($application['applicant_name']); ?></strong>
                                                        <div class="text-muted small">
                                                            <?php echo htmlspecialchars($application['email']); ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($application['resume_title']): ?>
                                                        <a href="../uploads/resumes/<?php echo htmlspecialchars($application['file_path']); ?>" 
                                                           class="text-decoration-none" target="_blank">
                                                            <?php echo htmlspecialchars($application['resume_title']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">No resume</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                                        <select name="status" class="form-select form-select-sm" 
                                                                onchange="this.form.submit()" style="width: auto;">
                                                            <option value="pending" <?php echo $application['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="reviewed" <?php echo $application['status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                                            <option value="shortlisted" <?php echo $application['status'] === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                                                            <option value="rejected" <?php echo $application['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                            <option value="hired" <?php echo $application['status'] === 'hired' ? 'selected' : ''; ?>>Hired</option>
                                                        </select>
                                                    </form>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($application['created_at'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#coverLetterModal<?php echo $application['application_id']; ?>">
                                                        View Cover Letter
                                                    </button>
                                                </td>
                                            </tr>

                                            <!-- Cover Letter Modal -->
                                            <div class="modal fade" id="coverLetterModal<?php echo $application['application_id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Cover Letter</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <?php if ($application['cover_letter']): ?>
                                                                <?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?>
                                                            <?php else: ?>
                                                                <p class="text-muted mb-0">No cover letter provided.</p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Company Information -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Company Information</h5>
                        <div class="text-center mb-3">
                            <?php if ($job['logo']): ?>
                                <img src="../uploads/company/<?php echo htmlspecialchars($job['logo']); ?>" 
                                     class="mb-3" style="max-width: 150px; max-height: 150px;">
                            <?php endif; ?>
                            <h6><?php echo htmlspecialchars($job['company_name']); ?></h6>
                        </div>
                        <?php if ($job['company_description']): ?>
                            <p class="mb-3"><?php echo nl2br(htmlspecialchars($job['company_description'])); ?></p>
                        <?php endif; ?>
                        <?php if ($job['website']): ?>
                            <a href="<?php echo htmlspecialchars($job['website']); ?>" 
                               class="btn btn-outline-primary w-100" target="_blank">
                                <i class="bi bi-globe"></i> Visit Website
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Job Statistics -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Job Statistics</h5>
                        <div class="row text-center">
                            <div class="col-6 mb-3">
                                <h3 class="h4 mb-1"><?php echo count($applications); ?></h3>
                                <p class="text-muted mb-0">Total Applications</p>
                            </div>
                            <div class="col-6 mb-3">
                                <h3 class="h4 mb-1">
                                    <?php 
                                    echo count(array_filter($applications, function($app) {
                                        return $app['status'] === 'pending';
                                    }));
                                    ?>
                                </h3>
                                <p class="text-muted mb-0">Pending</p>
                            </div>
                            <div class="col-6">
                                <h3 class="h4 mb-1">
                                    <?php 
                                    echo count(array_filter($applications, function($app) {
                                        return $app['status'] === 'shortlisted';
                                    }));
                                    ?>
                                </h3>
                                <p class="text-muted mb-0">Shortlisted</p>
                            </div>
                            <div class="col-6">
                                <h3 class="h4 mb-1">
                                    <?php 
                                    echo count(array_filter($applications, function($app) {
                                        return $app['status'] === 'hired';
                                    }));
                                    ?>
                                </h3>
                                <p class="text-muted mb-0">Hired</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 