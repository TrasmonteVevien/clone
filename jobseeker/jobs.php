<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a jobseeker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get search parameters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$location = isset($_GET['location']) ? $conn->real_escape_string($_GET['location']) : '';
$category = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';
$type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';

// Build query
$sql = "SELECT j.*, e.company_name, e.logo 
        FROM jobs j 
        JOIN employers e ON j.employer_id = e.employer_id 
        WHERE j.status = 'open'";

$params = [];
$types = "";

if ($search) {
    $sql .= " AND (j.title LIKE ? OR j.description LIKE ? OR e.company_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($location) {
    $sql .= " AND j.location LIKE ?";
    $params[] = "%$location%";
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

$sql .= " ORDER BY j.posted_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories for filter
$categories_sql = "SELECT DISTINCT category FROM jobs WHERE status = 'open' ORDER BY category";
$categories = $conn->query($categories_sql)->fetch_all(MYSQLI_ASSOC);

// Get job types for filter
$types_sql = "SELECT DISTINCT type FROM jobs WHERE status = 'open' ORDER BY type";
$job_types = $conn->query($types_sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Jobs - JPost</title>
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
                        <a class="nav-link active" href="jobs.php">Browse Jobs</a>
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
        <!-- Search Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Job title, company, or keywords">
                    </div>
                    <div class="col-md-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location" 
                               value="<?php echo htmlspecialchars($location); ?>" 
                               placeholder="City or remote">
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
                        <label for="type" class="form-label">Job Type</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">All Types</option>
                            <?php foreach ($job_types as $job_type): ?>
                                <option value="<?php echo htmlspecialchars($job_type['type']); ?>"
                                        <?php echo $type === $job_type['type'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($job_type['type']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Job Listings -->
        <div class="row">
            <?php if (empty($jobs)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        No jobs found matching your criteria. Try adjusting your search filters.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($jobs as $job): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <?php if ($job['logo']): ?>
                                        <img src="../<?php echo htmlspecialchars($job['logo']); ?>" 
                                             alt="<?php echo htmlspecialchars($job['company_name']); ?>" 
                                             class="rounded me-3" style="width: 50px; height: 50px; object-fit: contain;">
                                    <?php endif; ?>
                                    <div>
                                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($job['title']); ?></h5>
                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <span class="badge bg-primary me-2"><?php echo htmlspecialchars($job['type']); ?></span>
                                    <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($job['category']); ?></span>
                                    <span class="badge bg-info"><?php echo htmlspecialchars($job['location']); ?></span>
                                </div>

                                <p class="card-text">
                                    <?php 
                                    $description = htmlspecialchars($job['description']);
                                    echo strlen($description) > 150 ? substr($description, 0, 150) . '...' : $description;
                                    ?>
                                </p>

                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        Posted <?php echo date('M d, Y', strtotime($job['posted_date'])); ?>
                                    </small>
                                    <a href="job_details.php?id=<?php echo $job['job_id']; ?>" 
                                       class="btn btn-outline-primary">View Details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 