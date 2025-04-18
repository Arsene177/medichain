<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Suspended - MediChain</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #00b4db, #0083B0);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        .suspended-container {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        .warning-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1.5rem;
        }
        .contact-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1.5rem 0;
        }
        .phone-number {
            font-size: 1.5rem;
            color: #0083B0;
            font-weight: bold;
        }
        .btn-primary {
            background-color: #0083B0;
            border-color: #0083B0;
            padding: 0.75rem 2rem;
        }
        .btn-primary:hover {
            background-color: #006d94;
            border-color: #006d94;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="suspended-container">
            <i class="bi bi-exclamation-triangle-fill warning-icon"></i>
            <h1 class="mb-4">Account Suspended</h1>
            <p class="lead mb-4">Your account has been suspended. Please contact the administrator for assistance.</p>
            
            <div class="contact-info">
                <h5 class="mb-3">Contact Administrator</h5>
                <p class="phone-number mb-0">658692978</p>
            </div>

            <?php if(isset($_SESSION["suspension_reason"])): ?>
                <div class="alert alert-warning mt-4">
                    <strong>Reason for Suspension:</strong><br>
                    <?php echo nl2br(htmlspecialchars($_SESSION["suspension_reason"])); ?>
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <a href="index.php" class="btn btn-primary">
                    <i class="bi bi-house"></i> Return to Home
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 