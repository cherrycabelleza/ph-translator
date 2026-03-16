<?php
require_once "config.php";
session_start();

// Check if deletion is confirmed via GET parameter
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    if (!isset($_GET['id'])) {
        die("Invalid request.");
    }

    $id = intval($_GET['id']);

    /* =========================
       FETCH RECORD TO LOG BEFORE DELETE
    ========================= */
    $stmt = mysqli_prepare($link, "SELECT * FROM translations WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$data) {
        die("Translation not found.");
    }

    /* =========================
       DELETE RECORD
    ========================= */
    $stmt = mysqli_prepare($link, "DELETE FROM translations WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    /* =========================
       LOG DELETE ACTION
    ========================= */
    $log_stmt = mysqli_prepare(
        $link,
        "INSERT INTO logs 
         (action, source_lang, target_lang, word, translation, log_date)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );

    $action = "DELETE";

    mysqli_stmt_bind_param(
        $log_stmt,
        "sssss",
        $action,
        $data['source_lang'],
        $data['target_lang'],
        $data['word'],
        $data['translation']
    );

    mysqli_stmt_execute($log_stmt);
    mysqli_stmt_close($log_stmt);

    /* =========================
       REDIRECT BACK
    ========================= */
    header("Location: TranslationPage.php");
    exit;
}

// If no confirm parameter, show the modal
if (!isset($_GET['id'])) {
    die("Invalid request.");
}

$id = intval($_GET['id']);

// Fetch the record to display in modal
$stmt = mysqli_prepare($link, "SELECT * FROM translations WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$data) {
    die("Translation not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Translation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f6f8;
            --card: #ffffff;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --text: #1f2937;
            --text-secondary: #6b7280;
            --border: #e5e7eb;
            --danger: #ef4444;
            --danger-hover: #dc2626;
            --overlay: rgba(0, 0, 0, 0.5);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--overlay);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.2s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Modal Container */
        .modal-container {
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            width: 90%;
            max-width: 420px;
            padding: 32px;
            animation: slideUp 0.3s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Modal Header */
        .modal-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .modal-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #fee2e2;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .modal-icon .material-icons-outlined {
            font-size: 24px;
            color: var(--danger);
        }

        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
            margin: 0;
        }

        /* Modal Body */
        .modal-body {
            margin-bottom: 28px;
        }

        .modal-message {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .translation-preview {
            background: #f9fafb;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
        }

        .translation-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
        }

        .translation-row:not(:last-child) {
            border-bottom: 1px solid var(--border);
        }

        .translation-label {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .translation-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--text);
        }

        .translation-word {
            font-weight: 600;
            color: var(--primary);
        }

        /* Modal Footer */
        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            border: none;
            font-family: inherit;
        }

        .btn-cancel {
            background: var(--card);
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        .btn-cancel:hover {
            background: #f9fafb;
            border-color: var(--text-secondary);
            color: var(--text);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: var(--danger-hover);
        }

        /* Accessibility */
        .btn:focus,
        .modal-overlay:focus {
            outline: none;
        }

        .btn:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .modal-container {
                padding: 24px;
                margin: 16px;
            }

            .modal-header {
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }

            .modal-footer {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal-container">
            <div class="modal-header">
                <div class="modal-icon">
                    <span class="material-icons-outlined">warning</span>
                </div>
                <h2 class="modal-title" id="modal-title">Delete Translation</h2>
            </div>
            
            <div class="modal-body">
                <p class="modal-message">
                    Are you sure you want to delete this translation? This action cannot be undone and will be logged for security purposes.
                </p>
                
                <div class="translation-preview">
                    <div class="translation-row">
                        <span class="translation-label">Word</span>
                        <span class="translation-value translation-word"><?php echo htmlspecialchars($data['word']); ?></span>
                    </div>
                    <div class="translation-row">
                        <span class="translation-label">Translation</span>
                        <span class="translation-value"><?php echo htmlspecialchars($data['translation']); ?></span>
                    </div>
                    <div class="translation-row">
                        <span class="translation-label">Language Pair</span>
                        <span class="translation-value"><?php echo htmlspecialchars($data['source_lang'] . ' → ' . $data['target_lang']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <a href="TranslationPage.php" class="btn btn-cancel">Cancel</a>
                <a href="DeleteTranslation.php?id=<?php echo $id; ?>&confirm=yes" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</body>
</html>
