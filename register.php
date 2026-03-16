<?php
require_once "config.php";
session_start();

$name = $email = "";
$errors = [];

/* =========================================
   HANDLE REGISTER FORM
========================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    /* =========================
       NAME VALIDATION
    ========================= */
    if (empty($name)) {
        $errors[] = "Full name is required.";
    } elseif (strlen($name) < 3) {
        $errors[] = "Full name must be at least 3 characters.";
    }

    /* =========================
       EMAIL VALIDATION
    ========================= */
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {

        $stmt = mysqli_prepare($link, "SELECT email FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = "Email is already registered.";
            header("Location: login.php");
            exit();
        }

        mysqli_stmt_close($stmt);
    }

    /* =========================
       PASSWORD VALIDATION
    ========================= */
    $passPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W]).{8,}$/';

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (!preg_match($passPattern, $password)) {
        $errors[] = "Password must be 8+ characters and include uppercase, lowercase, number, and special character.";
    }

    /* =========================
       INSERT USER
    ========================= */
    if (empty($errors)) {

        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $defaultRole   = "user-editor";
        $defaultStatus = "active";

        $stmt = mysqli_prepare($link,
            "INSERT INTO users (email, name, usertype, status, password, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "sssss",
            $email,
            $name,
            $defaultRole,
            $defaultStatus,
            $hashed
        );

        if (mysqli_stmt_execute($stmt)) {

            $_SESSION['success_message'] = "Registration successful! You may now log in.";
            header("Location: PLT_MainPage.php");
            exit();

        } else {
            $errors[] = "Registration failed. Please try again.";
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Create Account | Philippine Languages Translator</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a73e8;
            --primary-hover: #1557b0;
            --bg-light: #f0f4ff;
            --card-bg: #ffffff;
            --text-main: #1a2a3a;
            --text-muted: #5c7a94;
            --border-color: #e0e6ed;
            --error-bg: #fef2f2;
            --error-text: #dc2626;
            --radius: 12px;
            --shadow: 0 10px 25px rgba(26, 115, 232, 0.1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-light);
            color: var(--text-main);
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* Desktop Fixed Layout */
        @media (min-width: 1024px) {
            html, body {
                height: 100vh;
                overflow: hidden;
            }
        }

        .register-card {
            width: 100%;
            max-width: 480px;
            background: var(--card-bg);
            padding: 40px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-container i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 15px;
        }

        h2 {
            margin: 0 0 10px 0;
            color: var(--text-main);
            font-weight: 700;
            font-size: 1.5rem;
            text-align: center;
        }

        .subtitle {
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
            font-size: 0.875rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            transition: color 0.2s;
        }

        input {
            width: 100%;
            padding: 12px 14px 12px 40px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 0.95rem;
            box-sizing: border-box;
            background: #fdfdfe;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
            background: #fff;
        }

        input:focus + i {
            color: var(--primary);
        }

        button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background: var(--primary);
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        button:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(26, 115, 232, 0.2);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            margin-bottom: 0;
        }

        .btn-outline:hover {
            background: #f8fbff;
            color: var(--primary);
            border-color: var(--primary);
            box-shadow: none;
        }

        .error-container {
            margin-bottom: 20px;
        }

        .error-message {
            background: var(--error-bg);
            color: var(--error-text);
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(220, 38, 38, 0.1);
            margin-bottom: 10px;
        }

        .footer-links {
            text-align: center;
            margin-top: 25px;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .footer-links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }

        .password-hint {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 6px;
            line-height: 1.4;
        }

        @media (max-width: 480px) {
            .register-card {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>

<div class="register-card">
    <div class="logo-container">
        <i class="fas fa-user-plus"></i>
        <h2>Create Account</h2>
        <p class="subtitle">Join our community of contributors</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="error-container">
            <?php foreach ($errors as $err): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($err); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="name">Full Name</label>
            <div class="input-wrapper">
                <input type="text" name="name" id="name" 
                       value="<?php echo htmlspecialchars($name); ?>" 
                       placeholder="Juan Dela Cruz" required>
                <i class="fas fa-user"></i>
            </div>
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <div class="input-wrapper">
                <input type="email" name="email" id="email" 
                       value="<?php echo htmlspecialchars($email); ?>" 
                       placeholder="juan@example.com" required>
                <i class="fas fa-envelope"></i>
            </div>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrapper">
                <input type="password" name="password" id="password" 
                       placeholder="••••••••" required>
                <i class="fas fa-lock"></i>
            </div>
            <p class="password-hint">
                Must be 8+ characters with uppercase, lowercase, number, and special character.
            </p>
        </div>

        <button type="submit">
            <i class="fas fa-user-check"></i> Register Account
        </button>
        
        <button type="button" class="btn-outline" onclick="window.location.href='PLT_MainPage.php'">
            <i class="fas fa-arrow-left"></i> Back to Main Page
        </button>
    </form>

    <div class="footer-links">
        Already have an account? <a href="login.php">Log in here</a>
    </div>
</div>

</body>
</html>