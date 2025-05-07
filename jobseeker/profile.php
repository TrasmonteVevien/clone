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

// Get jobseeker information
$sql = "SELECT j.*, u.email 
        FROM jobseekers j 
        JOIN users u ON j.user_id = u.user_id 
        WHERE j.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$jobseeker = $stmt->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);
    $skills = $conn->real_escape_string($_POST['skills']);
    $education = $conn->real_escape_string($_POST['education']);
    $experience = $conn->real_escape_string($_POST['experience']);
    $bio = $conn->real_escape_string($_POST['bio']);
    $linkedin_url = $conn->real_escape_string($_POST['linkedin_url']);
    $github_url = $conn->real_escape_string($_POST['github_url']);
    $portfolio_url = $conn->real_escape_string($_POST['portfolio_url']);

    // Handle profile picture upload
    $profile_picture = $jobseeker['profile_picture']; // Keep existing picture by default
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
            $upload_path = '../uploads/profiles/' . $new_filename;
            
            if (!is_dir('../uploads/profiles')) {
                mkdir('../uploads/profiles', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                // Delete old profile picture if exists
                if ($jobseeker['profile_picture'] && file_exists('../' . $jobseeker['profile_picture'])) {
                    unlink('../' . $jobseeker['profile_picture']);
                }
                $profile_picture = 'uploads/profiles/' . $new_filename;
            }
        }
    }

    // Update profile
    $update_sql = "UPDATE jobseekers SET 
                   first_name = ?, 
                   last_name = ?, 
                   phone = ?, 
                   address = ?, 
                   skills = ?, 
                   education = ?, 
                   experience = ?, 
                   bio = ?,
                   linkedin_url = ?,
                   github_url = ?,
                   portfolio_url = ?,
                   profile_picture = ?
                   WHERE user_id = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssssssssssssi", 
        $first_name, $last_name, $phone, $address, 
        $skills, $education, $experience, $bio,
        $linkedin_url, $github_url, $portfolio_url,
        $profile_picture, $user_id
    );

    if ($update_stmt->execute()) {
        $success_message = "Profile updated successfully!";
        // Refresh jobseeker data
        $stmt->execute();
        $jobseeker = $stmt->get_result()->fetch_assoc();
    } else {
        $error_message = "Error updating profile. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - JPost</title>
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
                            <?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item active" href="profile.php">Profile</a></li>
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
            <div class="col-md-4">
                <!-- Profile Picture -->
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <?php if($jobseeker['profile_picture']): ?>
                            <img src="../<?php echo htmlspecialchars($jobseeker['profile_picture']); ?>" 
                                 alt="Profile Picture" class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <img src="../assets/images/default-profile.png" alt="Default Profile" 
                                 class="rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                        <?php endif; ?>
                        <h4><?php echo htmlspecialchars($jobseeker['first_name'] . ' ' . $jobseeker['last_name']); ?></h4>
                        <p class="text-muted"><?php echo htmlspecialchars($jobseeker['email']); ?></p>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Quick Links</h5>
                        <div class="list-group">
                            <a href="resume.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-file-earmark-text"></i> Manage Resume
                            </a>
                            <a href="applications.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-briefcase"></i> View Applications
                            </a>
                            <a href="jobs.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-search"></i> Browse Jobs
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <!-- Profile Form -->
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Edit Profile</h4>

                        <?php if($success_message): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>

                        <?php if($error_message): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($jobseeker['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($jobseeker['last_name']); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($jobseeker['email']); ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($jobseeker['phone']); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($jobseeker['address']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo htmlspecialchars($jobseeker['bio']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="skills" class="form-label">Skills</label>
                                <textarea class="form-control" id="skills" name="skills" rows="3"><?php echo htmlspecialchars($jobseeker['skills']); ?></textarea>
                                <small class="text-muted">Separate skills with commas</small>
                            </div>

                            <div class="mb-3">
                                <label for="education" class="form-label">Education</label>
                                <textarea class="form-control" id="education" name="education" rows="3"><?php echo htmlspecialchars($jobseeker['education']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="experience" class="form-label">Experience</label>
                                <textarea class="form-control" id="experience" name="experience" rows="3"><?php echo htmlspecialchars($jobseeker['experience']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="profile_picture" class="form-label">Profile Picture</label>
                                <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                                <small class="text-muted">Max file size: 2MB. Allowed formats: JPG, JPEG, PNG, GIF</small>
                            </div>

                            <div class="mb-3">
                                <label for="linkedin_url" class="form-label">LinkedIn URL</label>
                                <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" 
                                       value="<?php echo htmlspecialchars($jobseeker['linkedin_url']); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="github_url" class="form-label">GitHub URL</label>
                                <input type="url" class="form-control" id="github_url" name="github_url" 
                                       value="<?php echo htmlspecialchars($jobseeker['github_url']); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="portfolio_url" class="form-label">Portfolio URL</label>
                                <input type="url" class="form-control" id="portfolio_url" name="portfolio_url" 
                                       value="<?php echo htmlspecialchars($jobseeker['portfolio_url']); ?>">
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 