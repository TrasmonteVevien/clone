<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

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
        WHERE j.job_id = ? AND j.status = 'open'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    header("Location: jobs.php");
    exit();
}

// Check if user has already applied
$check_sql = "SELECT * FROM applications WHERE user_id = ? AND job_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $user_id, $job_id);
$check_stmt->execute();
$existing_application = $check_stmt->get_result()->fetch_assoc();

// Get user's resumes
$resumes_sql = "SELECT * FROM resumes WHERE user_id = ? ORDER BY is_default DESC";
$resumes_stmt = $conn->prepare($resumes_sql);
$resumes_stmt->bind_param("i", $user_id);
$resumes_stmt->execute();
$resumes = $resumes_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle application submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$existing_application) {
    $resume_id = (int)$_POST['resume_id'];
    $cover_letter = $conn->real_escape_string($_POST['cover_letter']);

    // Verify resume belongs to user
    $verify_sql = "SELECT * FROM resumes WHERE resume_id = ? AND user_id = ?";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("ii", $resume_id, $user_id);
    $verify_stmt->execute();
    
    if ($verify_stmt->get_result()->num_rows > 0) {
        $apply_sql = "INSERT INTO applications (user_id, job_id, resume_id, cover_letter, status) 
                      VALUES (?, ?, ?, ?, 'pending')";
        $apply_stmt = $conn->prepare($apply_sql);
        $apply_stmt->bind_param("iiis", $user_id, $job_id, $resume_id, $cover_letter);
        
        if ($apply_stmt->execute()) {
            $success_message = "Application submitted successfully!";
            $existing_application = [
                'status' => 'pending',
                'resume_id' => $resume_id,
                'cover_letter' => $cover_letter
            ];
        } else {
            $error_message = "Error submitting application. Please try again.";
        }
    } else {
        $error_message = "Invalid resume selected.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> - JPost</title>
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
                        <a class="nav-link" href="applications.php">My Applications</a>
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
        <div class="row">
            <div class="col-md-8">
                <!-- Job Details -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <?php if ($job['logo']): ?>
                                <img src="../<?php echo htmlspecialchars($job['logo']); ?>" 
                                     alt="<?php echo htmlspecialchars($job['company_name']); ?>" 
                                     class="rounded me-3" style="width: 64px; height: 64px; object-fit: contain;">
                            <?php endif; ?>
                            <div>
                                <h4 class="card-title mb-1"><?php echo htmlspecialchars($job['title']); ?></h4>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($job['company_name']); ?></p>
                            </div>
                        </div>

                        <div class="mb-4">
                            <span class="badge bg-primary me-2"><?php echo htmlspecialchars($job['type']); ?></span>
                            <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($job['category']); ?></span>
                            <span class="badge bg-info me-2"><?php echo htmlspecialchars($job['location']); ?></span>
                            <span class="badge bg-success">$<?php echo number_format($job['salary'], 2); ?></span>
                        </div>

                        <h5>Job Description</h5>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>

                        <h5>Requirements</h5>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($job['requirements'])); ?></p>

                        <h5>Benefits</h5>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($job['benefits'])); ?></p>

                        <div class="mt-4">
                            <small class="text-muted">
                                Posted on <?php echo date('F d, Y', strtotime($job['posted_date'])); ?>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Company Information -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">About <?php echo htmlspecialchars($job['company_name']); ?></h5>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($job['company_description'])); ?></p>
                        <?php if ($job['website']): ?>
                            <a href="<?php echo htmlspecialchars($job['website']); ?>" 
                               class="btn btn-outline-primary" target="_blank">
                                Visit Company Website
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Application Status or Form -->
                <div class="card">
                    <div class="card-body">
                        <?php if($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>

                        <?php if($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>

                        <?php if($existing_application): ?>
                            <h5 class="card-title">Application Status</h5>
                            <div class="mb-3">
                                <span class="badge bg-<?php 
                                    echo match($existing_application['status']) {
                                        'pending' => 'warning',
                                        'reviewed' => 'info',
                                        'shortlisted' => 'primary',
                                        'rejected' => 'danger',
                                        'hired' => 'success',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo ucfirst($existing_application['status']); ?>
                                </span>
                            </div>
                            <p class="card-text">
                                You applied for this position on 
                                <?php echo date('F d, Y', strtotime($existing_application['applied_date'])); ?>
                            </p>
                        <?php else: ?>
                            <h5 class="card-title">Apply for this Position</h5>
                            <?php if(empty($resumes)): ?>
                                <div class="alert alert-warning">
                                    You need to create a resume before applying for jobs.
                                    <a href="resume.php" class="alert-link">Create Resume</a>
                                </div>
                            <?php else: ?>
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label for="resume_id" class="form-label">Select Resume</label>
                                        <select class="form-select" id="resume_id" name="resume_id" required>
                                            <?php foreach($resumes as $resume): ?>
                                                <option value="<?php echo $resume['resume_id']; ?>"
                                                        <?php echo $resume['is_default'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($resume['title']); ?>
                                                    <?php echo $resume['is_default'] ? ' (Default)' : ''; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="cover_letter" class="form-label">Cover Letter</label>
                                        <textarea class="form-control" id="cover_letter" name="cover_letter" 
                                                  rows="5" required></textarea>
                                        <small class="text-muted">
                                            Explain why you're a good fit for this position
                                        </small>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">Submit Application</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 