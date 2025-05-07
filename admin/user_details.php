<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header("Location: users.php");
    exit();
}

// Get user details
$sql = "SELECT u.*, 
        CASE 
            WHEN u.role = 'jobseeker' THEN CONCAT(j.first_name, ' ', j.last_name)
            WHEN u.role = 'employer' THEN e.company_name
            ELSE 'Admin'
        END as display_name,
        j.*, e.*
        FROM users u
        LEFT JOIN jobseekers j ON u.user_id = j.user_id
        LEFT JOIN employers e ON u.user_id = e.user_id
        WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: users.php");
    exit();
}

// Get additional information based on role
if ($user['role'] === 'jobseeker') {
    // Get resumes
    $resumes_sql = "SELECT * FROM resumes WHERE user_id = ? ORDER BY is_default DESC, created_at DESC";
    $resumes_stmt = $conn->prepare($resumes_sql);
    $resumes_stmt->bind_param("i", $user_id);
    $resumes_stmt->execute();
    $resumes = $resumes_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get applications
    $applications_sql = "SELECT a.*, j.title as job_title, e.company_name 
                        FROM applications a 
                        JOIN jobs j ON a.job_id = j.job_id 
                        JOIN employers e ON j.employer_id = e.employer_id 
                        WHERE a.user_id = ? 
                        ORDER BY a.created_at DESC";
    $applications_stmt = $conn->prepare($applications_sql);
    $applications_stmt->bind_param("i", $user_id);
    $applications_stmt->execute();
    $applications = $applications_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} elseif ($user['role'] === 'employer') {
    // Get posted jobs
    $jobs_sql = "SELECT * FROM jobs WHERE employer_id = ? ORDER BY created_at DESC";
    $jobs_stmt = $conn->prepare($jobs_sql);
    $jobs_stmt->bind_param("i", $user_id);
    $jobs_stmt->execute();
    $jobs = $jobs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details - JPost</title>
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
                        <a class="nav-link active" href="users.php">Users</a>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">User Details</h1>
            <a href="users.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
        </div>

        <!-- User Information -->
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <?php if ($user['role'] === 'jobseeker' && $user['profile_picture']): ?>
                            <img src="../uploads/profile/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                 class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                        <?php elseif ($user['role'] === 'employer' && $user['logo']): ?>
                            <img src="../uploads/company/<?php echo htmlspecialchars($user['logo']); ?>" 
                                 class="mb-3" style="max-width: 150px; max-height: 150px;">
                        <?php else: ?>
                            <div class="bg-secondary rounded-circle mb-3 mx-auto" 
                                 style="width: 150px; height: 150px; display: flex; align-items: center; justify-content: center;">
                                <i class="bi bi-person-fill text-white" style="font-size: 4rem;"></i>
                            </div>
                        <?php endif; ?>

                        <h5 class="card-title"><?php echo htmlspecialchars($user['display_name']); ?></h5>
                        <p class="text-muted">
                            <span class="badge bg-<?php 
                                echo match($user['role']) {
                                    'admin' => 'danger',
                                    'employer' => 'primary',
                                    'jobseeker' => 'success',
                                    default => 'secondary'
                                };
                            ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </p>
                        <p class="mb-1">
                            <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                        </p>
                        <p class="mb-1">
                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($user['username']); ?>
                        </p>
                        <p class="mb-1">
                            <i class="bi bi-calendar"></i> Joined <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                        </p>
                        <p class="mb-0">
                            <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </p>
                    </div>
                </div>

                <?php if ($user['role'] === 'jobseeker'): ?>
                    <!-- Jobseeker Additional Info -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Contact Information</h6>
                            <p class="mb-1">
                                <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?>
                            </p>
                            <p class="mb-1">
                                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?>
                            </p>
                            <?php if ($user['linkedin_url']): ?>
                                <p class="mb-1">
                                    <i class="bi bi-linkedin"></i> 
                                    <a href="<?php echo htmlspecialchars($user['linkedin_url']); ?>" target="_blank">LinkedIn Profile</a>
                                </p>
                            <?php endif; ?>
                            <?php if ($user['github_url']): ?>
                                <p class="mb-1">
                                    <i class="bi bi-github"></i> 
                                    <a href="<?php echo htmlspecialchars($user['github_url']); ?>" target="_blank">GitHub Profile</a>
                                </p>
                            <?php endif; ?>
                            <?php if ($user['portfolio_url']): ?>
                                <p class="mb-1">
                                    <i class="bi bi-link-45deg"></i> 
                                    <a href="<?php echo htmlspecialchars($user['portfolio_url']); ?>" target="_blank">Portfolio</a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($user['role'] === 'employer'): ?>
                    <!-- Employer Additional Info -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Company Information</h6>
                            <p class="mb-1">
                                <i class="bi bi-building"></i> <?php echo htmlspecialchars($user['company_name']); ?>
                            </p>
                            <p class="mb-1">
                                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?>
                            </p>
                            <p class="mb-1">
                                <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?>
                            </p>
                            <?php if ($user['website']): ?>
                                <p class="mb-1">
                                    <i class="bi bi-globe"></i> 
                                    <a href="<?php echo htmlspecialchars($user['website']); ?>" target="_blank">Company Website</a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-8">
                <?php if ($user['role'] === 'jobseeker'): ?>
                    <!-- Jobseeker Resumes -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Resumes</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($resumes)): ?>
                                <p class="text-muted mb-0">No resumes uploaded yet.</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($resumes as $resume): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($resume['title']); ?></h6>
                                                    <p class="text-muted mb-0">
                                                        Uploaded <?php echo date('M d, Y', strtotime($resume['created_at'])); ?>
                                                        <?php if ($resume['is_default']): ?>
                                                            <span class="badge bg-primary ms-2">Default</span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <a href="../uploads/resumes/<?php echo htmlspecialchars($resume['file_path']); ?>" 
                                                   class="btn btn-sm btn-outline-primary" target="_blank">
                                                    <i class="bi bi-download"></i> Download
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Jobseeker Applications -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Job Applications</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($applications)): ?>
                                <p class="text-muted mb-0">No job applications yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Job Title</th>
                                                <th>Company</th>
                                                <th>Status</th>
                                                <th>Applied</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($applications as $application): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($application['job_title']); ?></td>
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
                                                    <td><?php echo date('M d, Y', strtotime($application['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($user['role'] === 'employer'): ?>
                    <!-- Employer Posted Jobs -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Posted Jobs</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($jobs)): ?>
                                <p class="text-muted mb-0">No jobs posted yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Category</th>
                                                <th>Type</th>
                                                <th>Status</th>
                                                <th>Posted</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($jobs as $job): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($job['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($job['category']); ?></td>
                                                    <td><?php echo htmlspecialchars($job['type']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $job['status'] === 'open' ? 'success' : 'secondary'; 
                                                        ?>">
                                                            <?php echo ucfirst($job['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($job['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 