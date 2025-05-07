<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get filter parameters
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Build query
$sql = "SELECT a.*, j.title as job_title, j.employer_id, e.company_name, e.logo, r.title as resume_title
        FROM applications a 
        JOIN jobs j ON a.job_id = j.job_id 
        JOIN employers e ON j.employer_id = e.employer_id
        JOIN resumes r ON a.resume_id = r.resume_id
        WHERE a.user_id = ?";

$params = [$user_id];
$types = "i";

if ($status) {
    $sql .= " AND a.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($search) {
    $sql .= " AND (j.title LIKE ? OR e.company_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$sql .= " ORDER BY a.applied_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get application statistics
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed,
    SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'hired' THEN 1 ELSE 0 END) as hired
    FROM applications 
    WHERE user_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - JPost</title>
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
                        <a class="nav-link" href="jobs.php">Browse Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="applications.php">My Applications</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            Profile
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Edit Profile</a></li>
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
        <!-- Application Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $stats['total']; ?></h3>
                        <p class="card-text">Total Applications</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $stats['pending']; ?></h3>
                        <p class="card-text">Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $stats['reviewed']; ?></h3>
                        <p class="card-text">Reviewed</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $stats['shortlisted']; ?></h3>
                        <p class="card-text">Shortlisted</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-danger text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $stats['rejected']; ?></h3>
                        <p class="card-text">Rejected</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?php echo $stats['hired']; ?></h3>
                        <p class="card-text">Hired</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-6">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Job title or company name">
                    </div>
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="reviewed" <?php echo $status === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                            <option value="shortlisted" <?php echo $status === 'shortlisted' ? 'selected' : ''; ?>>Shortlisted</option>
                            <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="hired" <?php echo $status === 'hired' ? 'selected' : ''; ?>>Hired</option>
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

        <!-- Applications List -->
        <?php if (empty($applications)): ?>
            <div class="alert alert-info">
                No applications found matching your criteria.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Company</th>
                            <th>Resume Used</th>
                            <th>Applied Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $application): ?>
                            <tr>
                                <td>
                                    <a href="job_details.php?id=<?php echo $application['job_id']; ?>" 
                                       class="text-decoration-none">
                                        <?php echo htmlspecialchars($application['job_title']); ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($application['logo']): ?>
                                            <img src="../<?php echo htmlspecialchars($application['logo']); ?>" 
                                                 alt="<?php echo htmlspecialchars($application['company_name']); ?>" 
                                                 class="rounded me-2" style="width: 32px; height: 32px; object-fit: contain;">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($application['company_name']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($application['resume_title']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($application['applied_date'])); ?></td>
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
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#applicationModal<?php echo $application['application_id']; ?>">
                                        View Details
                                    </button>
                                </td>
                            </tr>

                            <!-- Application Details Modal -->
                            <div class="modal fade" id="applicationModal<?php echo $application['application_id']; ?>" 
                                 tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Application Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-4">
                                                <h6>Job Details</h6>
                                                <p class="mb-1">
                                                    <strong>Title:</strong> 
                                                    <?php echo htmlspecialchars($application['job_title']); ?>
                                                </p>
                                                <p class="mb-1">
                                                    <strong>Company:</strong> 
                                                    <?php echo htmlspecialchars($application['company_name']); ?>
                                                </p>
                                                <p class="mb-0">
                                                    <strong>Applied Date:</strong> 
                                                    <?php echo date('F d, Y', strtotime($application['applied_date'])); ?>
                                                </p>
                                            </div>

                                            <div class="mb-4">
                                                <h6>Cover Letter</h6>
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?></p>
                                            </div>

                                            <div class="mb-4">
                                                <h6>Resume Used</h6>
                                                <p class="mb-0"><?php echo htmlspecialchars($application['resume_title']); ?></p>
                                            </div>

                                            <div>
                                                <h6>Application Status</h6>
                                                <p class="mb-0">
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
                                                </p>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <a href="job_details.php?id=<?php echo $application['job_id']; ?>" 
                                               class="btn btn-primary">View Job</a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 