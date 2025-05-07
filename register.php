<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';
$type = isset($_GET['type']) ? $_GET['type'] : 'jobseeker';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $type;

    // Check if username or email already exists
    $check_sql = "SELECT * FROM users WHERE username = ? OR email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $error = "Username or email already exists";
    } else {
        // Begin transaction
        $conn->begin_transaction();

        try {
            // Insert into users table
            $user_sql = "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)";
            $user_stmt = $conn->prepare($user_sql);
            $user_stmt->bind_param("ssss", $username, $email, $password, $role);
            $user_stmt->execute();
            $user_id = $conn->insert_id;

            if ($role == 'jobseeker') {
                $first_name = $conn->real_escape_string($_POST['first_name']);
                $last_name = $conn->real_escape_string($_POST['last_name']);
                $phone = $conn->real_escape_string($_POST['phone']);
                $address = $conn->real_escape_string($_POST['address']);

                $jobseeker_sql = "INSERT INTO jobseekers (user_id, first_name, last_name, phone, address) 
                                VALUES (?, ?, ?, ?, ?)";
                $jobseeker_stmt = $conn->prepare($jobseeker_sql);
                $jobseeker_stmt->bind_param("issss", $user_id, $first_name, $last_name, $phone, $address);
                $jobseeker_stmt->execute();
            } else {
                $company_name = $conn->real_escape_string($_POST['company_name']);
                $company_description = $conn->real_escape_string($_POST['company_description']);
                $industry = $conn->real_escape_string($_POST['industry']);
                $company_size = $conn->real_escape_string($_POST['company_size']);
                $website = $conn->real_escape_string($_POST['website']);
                $location = $conn->real_escape_string($_POST['location']);

                $employer_sql = "INSERT INTO employers (user_id, company_name, company_description, industry, 
                                company_size, website, location) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $employer_stmt = $conn->prepare($employer_sql);
                $employer_stmt->bind_param("issssss", $user_id, $company_name, $company_description, 
                                         $industry, $company_size, $website, $location);
                $employer_stmt->execute();
            }

            $conn->commit();
            $success = "Registration successful! You can now login.";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - JPost</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Register as <?php echo ucfirst($type); ?></h2>
                        
                        <?php if($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>

                            <!-- Terms and Agreement Checkbox -->
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                                    </label>
                                </div>
                            </div>

                            <?php if($type == 'jobseeker'): ?>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required>
                                </div>
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                                </div>
                            <?php else: ?>
                                <div class="mb-3">
                                    <label for="company_name" class="form-label">Company Name</label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="company_description" class="form-label">Company Description</label>
                                    <textarea class="form-control" id="company_description" name="company_description" rows="3" required></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="industry" class="form-label">Industry</label>
                                        <input type="text" class="form-control" id="industry" name="industry" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="company_size" class="form-label">Company Size</label>
                                        <select class="form-select" id="company_size" name="company_size" required>
                                            <option value="1-10">1-10 employees</option>
                                            <option value="11-50">11-50 employees</option>
                                            <option value="51-200">51-200 employees</option>
                                            <option value="201-500">201-500 employees</option>
                                            <option value="501+">501+ employees</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="website" class="form-label">Website</label>
                                        <input type="url" class="form-control" id="website" name="website">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="location" class="form-label">Location</label>
                                        <input type="text" class="form-control" id="location" name="location" required>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary">Register</button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                            <?php if($type == 'jobseeker'): ?>
                                <p>Are you an employer? <a href="register.php?type=employer">Register as Employer</a></p>
                            <?php else: ?>
                                <p>Are you a jobseeker? <a href="register.php?type=jobseeker">Register as Jobseeker</a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h2>1. Acceptance of Terms</h2>
                    <p>By accessing and using JPost, you accept and agree to be bound by the terms and provision of this agreement.</p>

                    <h2>2. User Accounts</h2>
                    <p>Users must provide accurate and complete information when creating an account. Users are responsible for maintaining the confidentiality of their account credentials.</p>

                    <h2>3. Jobseeker Responsibilities</h2>
                    <p>Jobseekers agree to:</p>
                    <ul>
                        <li>Provide accurate and truthful information in their profile and resume</li>
                        <li>Not submit fraudulent applications</li>
                        <li>Maintain the confidentiality of their account</li>
                        <li>Notify employers promptly of any changes in their availability</li>
                    </ul>

                    <h2>4. Employer Responsibilities</h2>
                    <p>Employers agree to:</p>
                    <ul>
                        <li>Post accurate and legitimate job opportunities</li>
                        <li>Maintain the confidentiality of applicant information</li>
                        <li>Respond to applications in a timely manner</li>
                        <li>Not discriminate against applicants based on protected characteristics</li>
                    </ul>

                    <h2>5. Privacy Policy</h2>
                    <p>We collect and process personal data in accordance with our Privacy Policy. By using JPost, you consent to such processing.</p>

                    <h2>6. Intellectual Property</h2>
                    <p>All content on JPost is protected by copyright and other intellectual property rights. Users may not copy, modify, or distribute content without permission.</p>

                    <h2>7. Termination</h2>
                    <p>We reserve the right to terminate or suspend accounts that violate these terms or engage in fraudulent activity.</p>

                    <h2>8. Limitation of Liability</h2>
                    <p>JPost is not liable for any damages arising from the use or inability to use our services.</p>

                    <h2>9. Changes to Terms</h2>
                    <p>We reserve the right to modify these terms at any time. Users will be notified of significant changes.</p>

                    <h2>10. Contact Information</h2>
                    <p>For questions about these terms, please contact us at support@jpost.com</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="terms.php" class="btn btn-primary" target="_blank">View Full Terms</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
            
            if (!terms) {
                e.preventDefault();
                alert('Please agree to the Terms and Conditions');
            }
        });
    </script>
</body>
</html> 