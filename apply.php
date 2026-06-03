<?php
// JOB APPLICATION FORM - WITH FILE UPLOAD VALIDATION
session_start();
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}
if ($_SESSION['role'] !== 'applicant') {
    die('Only applicants can submit job applications');
}

require_once 'config/db.php';
require_once 'config/security.php';
setSecurityHeaders();

$error = '';
$success = '';

// Get available jobs
$jobs = $pdo->query("SELECT id, title FROM jobs WHERE status = 'open'")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
    $job_id = $_POST['job_id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $cover_letter = trim($_POST['cover_letter']);
    
    // File upload validation (Project.pdf page 5)
    $cv_path = '';
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] === 0) {
        $allowed = ['pdf', 'docx'];
        $filename = $_FILES['cv']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $file_size = $_FILES['cv']['size'];
        
        // Validate file type
        if (!in_array($ext, $allowed)) {
            $error = 'Only PDF and DOCX files are allowed';
        }
        // Validate file size (max 5MB)
        elseif ($file_size > 5 * 1024 * 1024) {
            $error = 'File size must be less than 5MB';
        } else {
            $cv_path = 'uploads/' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
            move_uploaded_file($_FILES['cv']['tmp_name'], $cv_path);
        }
    } else {
        $error = 'Please upload your CV';
    }
    
    if (empty($error)) {
        $stmt = $pdo->prepare("INSERT INTO applications (user_id, job_id, full_name, email, phone, cover_letter, cv_path)
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$_SESSION['user_id'], $job_id, $full_name, $email, $phone, $cover_letter, $cv_path])) {
            $success = 'Application submitted successfully!';
        } else {
            $error = 'Submission failed';
        }
    }
    } // end else (csrf valid)
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Apply for Job</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="header">
        <h1>Job Application Form</h1>
        <a href="dashboard.php">Back to Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
    <div class="card">
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <label>Select Job*</label>
            <select name="job_id" required>
                <option value="">-- Select a position --</option>
                <?php foreach ($jobs as $job): ?>
                    <option value="<?php echo $job['id']; ?>"><?php echo htmlspecialchars($job['title']); ?></option>
                <?php endforeach; ?>
            </select>
            
            <label>Full Name*</label>
            <input type="text" name="full_name" required>
            
            <label>Email*</label>
            <input type="email" name="email" required>
            
            <label>Phone Number*</label>
            <input type="tel" name="phone" required>
            
            <label>Cover Letter</label>
            <textarea name="cover_letter" rows="5"></textarea>
            
            <label>Upload CV (PDF or DOCX, max 5MB)*</label>
            <input type="file" name="cv" accept=".pdf,.docx" required>
            
            <button type="submit">Submit Application</button>
        </form>
    </div>
</body>
</html>