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
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Build query
$sql = "SELECT a.*, j.title as job_title, j.employer_id, e.company_name, e.logo,
        u.email, u.username,
        CASE 
            WHEN u.role = 'jobseeker' THEN CONCAT(js.first_name, ' ', js.last_name)
            ELSE 'Unknown'
        END as applicant_name,
        r.title as resume_title
        FROM applications a 
        JOIN jobs j ON a.job_id = j.job_id 
        JOIN employers e ON j.employer_id = e.employer_id
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN jobseekers js ON u.user_id = js.user_id
        LEFT JOIN resumes r ON a.resume_id = r.resume_id
        WHERE 1=1";

$params = [];
$types = "";

if ($status) {
    $sql .= " AND a.status = ?";
    $params[] = $status;
    $types .= "s";
}

if ($search) {
    $sql .= " AND (j.title LIKE ? OR e.company_name LIKE ? OR 
                  CASE 
                      WHEN u.role = 'jobseeker' THEN CONCAT(js.first_name, ' ', js.last_name)
                      ELSE 'Unknown'
                  END LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle application status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['application_id']) && isset($_POST['status'])) {
    $application_id = (int)$_POST['application_id'];
    $new_status = $conn->real_escape_string($_POST['status']);
    
    $update_sql = "UPDATE applications SET status = ? WHERE application_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_status, $application_id);
    
    if ($update_stmt->execute()) {
        header("Location: applications.php?success=1");
        exit();
    } else {
        header("Location: applications.php?error=1");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Applications - JPost</title>
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
                        <a class="nav-link" href="jobs.php">Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="applications.php">Applications</a>
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

        <!-- Search and Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-6">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Job title, company, or applicant name">
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
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Job</th>
                                <th>Company</th>
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
                                        <a href="job_details.php?id=<?php echo $application['job_id']; ?>" 
                                           class="text-decoration-none">
                                            <?php echo htmlspecialchars($application['job_title']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($application['logo']): ?>
                                                <img src="../uploads/company/<?php echo htmlspecialchars($application['logo']); ?>" 
                                                     class="rounded me-2" style="width: 30px; height: 30px; object-fit: cover;">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($application['company_name']); ?>
                                        </div>
                                    </td>
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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 