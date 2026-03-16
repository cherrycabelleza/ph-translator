<?php
require_once "config.php";
session_start();

if (!isset($_GET['id']) && !isset($_POST['id'])) {
    die("Invalid request.");
}

$id = isset($_GET['id']) ? intval($_GET['id']) : intval($_POST['id']);
$msg = "";

/* =========================
   HANDLE UPDATE
========================= */
if (isset($_POST['updateTranslation'])) {

    $word        = trim($_POST['word']);
    $translation = trim($_POST['translation']);
    $source_lang = trim($_POST['source_lang']);
    $target_lang = trim($_POST['target_lang']);

    if ($word === "" || $translation === "" || $source_lang === "" || $target_lang === "") {
        $msg = "All fields are required.";
    } else {

        /* -------- UPDATE TRANSLATION -------- */
        $stmt = mysqli_prepare(
            $link,
            "UPDATE translations 
             SET word = ?, translation = ?, source_lang = ?, target_lang = ?
             WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ssssi",
            $word,
            $translation,
            $source_lang,
            $target_lang,
            $id
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        /* -------- INSERT LOG -------- */
        $log_stmt = mysqli_prepare(
            $link,
            "INSERT INTO logs 
             (action, source_lang, target_lang, word, translation, log_date)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );

        $action = "UPDATED";

        mysqli_stmt_bind_param(
            $log_stmt,
            "sssss",
            $action,
            $source_lang,
            $target_lang,
            $word,
            $translation
        );

        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);

        $_SESSION['success_message'] = "Translation updated successfully!";
        header("Location: TranslationPage.php");
        exit;
    }
}

/* =========================
   FETCH CURRENT DATA
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
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Translation</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ---------------- CSS VARIABLES ---------------- */
:root {
  --bg: #f7f9fc;
  --card: #ffffff;
  --primary: #1a73e8;
  --primary-hover: #1557b0;
  --accent: #e8f0fe;
  --text: #1f2937;
  --text-secondary: #6b7280;
  --border: #e5e7eb;
  --shadow: rgba(15, 23, 42, 0.04);
}

/* ---------------- BASE ---------------- */
body {
  font-family: 'Inter', Arial, Helvetica, sans-serif;
  background: var(--bg);
  color: var(--text);
  margin: 0;
  min-height: 100vh;
}

/* ---------------- LAYOUT ---------------- */
.edit-container {
  max-width: 700px;
  margin: 0 auto;
  padding: 24px;
  min-height: calc(100vh - 48px);
}

/* ---------------- HEADER ---------------- */
.page-header {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border);
}

.page-title {
  font-size: 22px;
  font-weight: 600;
  color: var(--primary);
  margin: 0;
}

.page-subtitle {
  font-size: 14px;
  color: var(--text-secondary);
  margin: 8px 0 0 0;
}

/* ---------------- FORM CARD ---------------- */
.form-card {
  background: var(--card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px var(--shadow);
  border: 1px solid var(--border);
}

/* ---------------- FORM ELEMENTS ---------------- */
.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: var(--text);
  margin-bottom: 8px;
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  color: var(--text);
  background: var(--card);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
  box-sizing: border-box;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--accent);
}

.form-group textarea {
  resize: vertical;
  min-height: 80px;
}

/* ---------------- BUTTONS ---------------- */
.form-actions {
  display: flex;
  gap: 12px;
  align-items: center;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--border);
}

.btn-primary {
  padding: 10px 24px;
  border-radius: 8px;
  border: none;
  background: var(--primary);
  color: white;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-primary:hover {
  background: var(--primary-hover);
}

.btn-secondary {
  padding: 10px 20px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--card);
  color: var(--text-secondary);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-secondary:hover {
  border-color: var(--primary);
  color: var(--primary);
  background: var(--accent);
}

/* ---------------- MESSAGES ---------------- */
.error-message {
  background: #fef2f2;
  color: #dc2626;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 16px;
}

.success-message {
  background: #dcfce7;
  color: #166534;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 16px;
}

/* ---------------- RESPONSIVE ---------------- */
@media (max-width: 600px) {
  .edit-container {
    padding: 16px;
  }
  
  .form-card {
    padding: 16px;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .btn-primary,
  .btn-secondary {
    width: 100%;
    justify-content: center;
  }
}
</style>
</head>
<body>

<div class="edit-container">
  <header class="page-header">
    <h1 class="page-title">Edit Translation</h1>
    <p class="page-subtitle">Update the translation details below</p>
  </header>

  <?php if (isset($_SESSION['success_message'])): ?>
    <div class="success-message"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
  <?php endif; ?>

  <div class="form-card">
    <?php if ($msg): ?>
      <div class="error-message"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="id" value="<?= htmlspecialchars($data['id']) ?>">

        <div class="form-group">
            <label for="source_lang">Source Language</label>
            <input type="text" name="source_lang" id="source_lang" value="<?= htmlspecialchars($data['source_lang']) ?>" required>
        </div>

        <div class="form-group">
            <label for="target_lang">Target Language</label>
            <input type="text" name="target_lang" id="target_lang" value="<?= htmlspecialchars($data['target_lang']) ?>" required>
        </div>

        <div class="form-group">
            <label for="word">Word</label>
            <input type="text" name="word" id="word" value="<?= htmlspecialchars($data['word']) ?>" required>
        </div>

        <div class="form-group">
            <label for="translation">Translation</label>
            <textarea name="translation" id="translation" rows="3" required><?= htmlspecialchars($data['translation']) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" name="updateTranslation" class="btn-primary">
                <span class="material-icons-outlined" style="font-size:18px">save</span>
                Save Changes
            </button>
            <a href="TranslationPage.php" class="btn-secondary">
                <span class="material-icons-outlined" style="font-size:18px">close</span>
                Cancel
            </a>
        </div>
    </form>
  </div>
</div>

</body>
</html>
