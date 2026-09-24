<?php
/**
 * LMS School Database Import Script
 *
 * This script imports the complete database schema.
 * Run this once to set up the database from scratch.
 *
 * Usage:
 *   1. Place this file in the database/ folder
 *   2. Access via browser: http://localhost/lms-school/database/import.php
 *   3. Click "Import Database" button
 *   4. Wait for completion message
 */

define('IMPORT_ALLOWED', true);
define('SCHEMA_FILE', __DIR__ . '/complete_schema.sql');

// Simple security check
if (!defined('IMPORT_ALLOWED') || !IMPORT_ALLOWED) {
    die('Import script is disabled.');
}

if (!file_exists(SCHEMA_FILE)) {
    die('Schema file not found: ' . SCHEMA_FILE);
}

// Process import if form submitted
$import_result = null;
$error_message = null;
$success_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_import'])) {
    if (!isset($_POST['confirm']) || $_POST['confirm'] !== '1') {
        $error_message = 'You must confirm the import action.';
    } else {
        try {
            // Database credentials (from environment or config)
            $db_host = isset($_POST['db_host']) ? trim($_POST['db_host']) : 'localhost';
            $db_user = isset($_POST['db_user']) ? trim($_POST['db_user']) : 'root';
            $db_pass = isset($_POST['db_pass']) ? trim($_POST['db_pass']) : '';
            $db_name = isset($_POST['db_name']) ? trim($_POST['db_name']) : 'lms_school';

            if (empty($db_name)) {
                throw new Exception('Database name is required.');
            }

            // Create connection to MySQL server (without database selection first)
            $pdo = new PDO(
                "mysql:host=" . $db_host . ";charset=utf8mb4",
                $db_user,
                $db_pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );

            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . $db_name . "`");
            $pdo->exec("USE `" . $db_name . "`");

            // Read schema file
            $schema_sql = file_get_contents(SCHEMA_FILE);

            if (!$schema_sql) {
                throw new Exception('Failed to read schema file.');
            }

            // Execute the schema
            // Split by semicolon, but be careful with comments
            $statements = preg_split('/;(?=([^\']*\'[^\']*\')*[^\']*$)/', $schema_sql);

            $executed = 0;
            $errors = [];

            foreach ($statements as $statement) {
                $statement = trim($statement);

                // Skip empty statements and comments
                if (empty($statement) || strpos($statement, '--') === 0) {
                    continue;
                }

                // Skip multi-line comments
                if (strpos($statement, '/*') === 0) {
                    continue;
                }

                try {
                    $pdo->exec($statement);
                    $executed++;
                } catch (PDOException $e) {
                    // Some errors are OK (like table already exists)
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        $errors[] = substr($statement, 0, 100) . '... - Error: ' . $e->getMessage();
                    }
                }
            }

            if (count($errors) > 0 && $executed < 10) {
                throw new Exception('Import failed with errors: ' . implode(' | ', array_slice($errors, 0, 3)));
            }

            $success_message = "✓ Database imported successfully! Executed {$executed} statements.";
            $import_result = 'success';

        } catch (PDOException $e) {
            $error_message = 'Database Connection Error: ' . $e->getMessage();
        } catch (Exception $e) {
            $error_message = 'Error: ' . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Database - LMS School</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: 'Courier New', monospace;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            font-weight: 400;
            cursor: pointer;
            margin-right: 10px;
        }

        .button-group {
            display: flex;
            gap: 10px;
        }

        button {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-import {
            background-color: #667eea;
            color: white;
        }

        .btn-import:hover:not(:disabled) {
            background-color: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-import:disabled {
            background-color: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-reset {
            background-color: #6c757d;
            color: white;
        }

        .btn-reset:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }

        .info-box {
            background-color: #f0f7ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.6;
            color: #333;
        }

        .info-box strong {
            color: #667eea;
        }

        .default-creds {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .default-creds strong {
            color: #856404;
        }

        .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .processing {
            display: flex;
            align-items: center;
        }

        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }

            .header h1 {
                font-size: 20px;
            }

            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 LMS School</h1>
            <p>Database Setup & Import</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($import_result !== 'success'): ?>
            <div class="info-box">
                <strong>📝 Info:</strong><br>
                This script will create a complete LMS database with all necessary tables, including:
                <ul style="margin-top: 10px; margin-right: 20px;">
                    <li>Student and user management</li>
                    <li>Course and class structure</li>
                    <li>Attendance and grades</li>
                    <li>Financial system</li>
                    <li>Notifications and messaging</li>
                </ul>
            </div>

            <div class="default-creds">
                <strong>⚠️ Default Credentials:</strong><br>
                Username: <code>admin</code><br>
                Password: <code>admin123</code><br>
                <small style="color: #666;">Change immediately in production!</small>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>

                <div class="form-group">
                    <label for="db_user">Database User</label>
                    <input type="text" id="db_user" name="db_user" value="root" required>
                </div>

                <div class="form-group">
                    <label for="db_pass">Database Password</label>
                    <input type="password" id="db_pass" name="db_pass" value="">
                </div>

                <div class="form-group">
                    <label for="db_name">Database Name</label>
                    <input type="text" id="db_name" name="db_name" value="lms_school" required>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="confirm" name="confirm" value="1" required>
                    <label for="confirm">I understand this will create/overwrite the database</label>
                </div>

                <div class="button-group">
                    <button type="submit" name="do_import" class="btn-import" onclick="showSpinner(this)">
                        <span class="spinner"></span>
                        <span>Import Database</span>
                    </button>
                    <button type="reset" class="btn-reset">Reset</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function showSpinner(btn) {
            if (document.getElementById('confirm').checked) {
                const spinner = btn.querySelector('.spinner');
                if (spinner) {
                    spinner.style.display = 'inline-block';
                }
                btn.disabled = true;
            }
        }

        // Auto-focus on database name field
        document.addEventListener('DOMContentLoaded', function() {
            const dbNameField = document.getElementById('db_name');
            if (dbNameField) {
                dbNameField.focus();
            }
        });
    </script>
</body>
</html>
