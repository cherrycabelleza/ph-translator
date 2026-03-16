<?php
require_once "config.php";
session_start();

/* =========================================
   LOG HELPER — MATCHES NEW TABLE
   - id AUTO_INCREMENT (ignored)
   - log_date auto (DATE)
========================================= */
function logTranslationAction($link, $action, $row){
    $stmt = mysqli_prepare($link,
        "INSERT INTO logs
         (action, source_lang, target_lang, word, translation)
         VALUES (?, ?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $action,
        $row['source_lang'],
        $row['target_lang'],
        $row['word'],
        $row['translation']
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$translation = [];

/* =========================================
   FETCH UNKNOWN WORD
========================================= */
if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id = trim($_GET['id']);

    $sql_word = "SELECT * FROM unknown_words WHERE id = ?";
    $stmt_word = mysqli_prepare($link, $sql_word);

    if ($stmt_word) {
        mysqli_stmt_bind_param($stmt_word, "i", $id);
        if (mysqli_stmt_execute($stmt_word)) {
            $result_word = mysqli_stmt_get_result($stmt_word);
            if (mysqli_num_rows($result_word) == 1) {
                $translation = mysqli_fetch_assoc($result_word);
            } else {
                echo "<div class='error-message'>Word not found.</div>";
                exit();
            }
        } else {
            echo "<div class='error-message'>Error fetching word.</div>";
            exit();
        }
    } else {
        echo "<div class='error-message'>Error preparing query.</div>";
        exit();
    }
} else {
    echo "<div class='error-message'>Invalid request. Please select a word to translate.</div>";
    exit();
}

/* =========================================
   INSERT TRANSLATION
========================================= */
if (isset($_POST['btnsubmit'])) {

    if (isset($_POST['translation'], $_POST['id']) && !empty(trim($_POST['translation']))) {

        $translation_text = trim($_POST['translation']);
        $id = trim($_POST['id']);

        $word        = $translation['word'];
        $source_lang = $translation['source_lang'];
        $target_lang = $translation['target_lang'];

        $sql_insert = "INSERT INTO translations
                       (word, source_lang, target_lang, translation, date_added)
                       VALUES (?, ?, ?, ?, NOW())";

        if ($stmt_insert = mysqli_prepare($link, $sql_insert)) {

            mysqli_stmt_bind_param(
                $stmt_insert,
                "ssss",
                $word,
                $source_lang,
                $target_lang,
                $translation_text
            );

            if (mysqli_stmt_execute($stmt_insert)) {

                /* =========================================
                   LOG: ADD TRANSLATION (NEW SCHEMA)
                ========================================= */
                logTranslationAction($link, 'ADD', [
                    'word'        => $word,
                    'source_lang' => $source_lang,
                    'target_lang' => $target_lang,
                    'translation' => $translation_text
                ]);

                /* =========================================
                   DELETE FROM unknown_words
                ========================================= */
                $del_sql = "DELETE FROM unknown_words WHERE id = ? LIMIT 1";
                if ($stmtDel = mysqli_prepare($link, $del_sql)) {
                    mysqli_stmt_bind_param($stmtDel, "i", $id);
                    mysqli_stmt_execute($stmtDel);
                }

                $_SESSION['success_message'] = "Translation added successfully!";
                header("location: AdminMainPage.php");
                exit();

            } else {
                echo "<div class='error-message'>Error inserting translation.</div>";
            }
        } else {
            echo "<div class='error-message'>Error preparing insert query.</div>";
        }
    } else {
        echo "<div class='error-message'>Translation is required.</div>";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Add Translation — Unknown Words</title>
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
.add-container {
  max-width: 700px;
  margin: 0 auto;
  padding: 24px;
  min-height: calc(100vh - 48px);
}

/* ---------------- HEADER ---------------- */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
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

/* ---------------- NAV BUTTONS ---------------- */
.nav-buttons {
  display: flex;
  gap: 8px;
}

.nav-btn {
  padding: 8px 20px;
  border-radius: 20px;
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

.nav-btn:hover {
  background: var(--accent);
  color: var(--primary);
  border-color: var(--primary);
}

.nav-btn.active {
  background: var(--primary);
  color: white;
  border-color: var(--primary);
}

/* ---------------- FORM CARD ---------------- */
.form-card {
  background: var(--card);
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 1px 3px var(--shadow);
  border: 1px solid var(--border);
}

.form-card-header {
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border);
}

.form-card-header h2 {
  font-size: 18px;
  font-weight: 600;
  color: var(--text);
  margin: 0 0 8px 0;
}

.form-card-header p {
  font-size: 14px;
  color: var(--text-secondary);
  margin: 0;
}

/* ---------------- INFO BOX ---------------- */
.info-box {
  background: var(--accent);
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 20px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;
  border-bottom: 1px solid var(--border);
}

.info-row:last-child {
  border-bottom: none;
}

.info-label {
  font-size: 13px;
  color: var(--text-secondary);
  font-weight: 500;
}

.info-value {
  font-size: 14px;
  color: var(--text);
  font-weight: 600;
}

.info-word {
  font-size: 16px;
  color: var(--primary);
  font-weight: 600;
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

.form-group textarea,
.form-group input[type="text"] {
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

.form-group textarea:focus,
.form-group input[type="text"]:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px var(--accent);
}

.form-group textarea {
  resize: vertical;
  min-height: 100px;
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
.success-message {
  background: #dcfce7;
  color: #166534;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  margin-bottom: 16px;
  text-align: center;
}

.error-message {
  background: #fef2f2;
  color: #dc2626;
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  margin: 20px auto;
  max-width: 700px;
}

/* ---------------- RESPONSIVE ---------------- */
@media (max-width: 600px) {
  .add-container {
    padding: 16px;
  }
  
  .page-header {
    flex-direction: column;
    gap: 12px;
    align-items: flex-start;
  }
  
  .form-card {
    padding: 16px;
  }
  
  .info-row {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
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

<div class="add-container">
  <header class="page-header">
    <h1 class="page-title">Add Translation</h1>
    <div class="nav-buttons">
      <a href="AdminMainPage.php" class="nav-btn" id="navUnknown">
        <span class="material-icons-outlined" style="font-size:18px">translate</span>
        Unknown Words
      </a>
      <a href="PLT_MainPage.php" class="nav-btn" id="navMain">
        <span class="material-icons-outlined" style="font-size:18px">home</span>
        Main Translator
      </a>
    </div>
  </header>

  <div class="form-card">
    <div class="form-card-header">
      <h2>Translate Unknown Word</h2>
      <p>Add a translation for the unknown word below</p>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
      <div class="success-message"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars(basename($_SERVER['REQUEST_URI'])); ?>" method="POST">
      
      <div class="info-box">
        <div class="info-row">
          <span class="info-label">Unknown Word</span>
          <span class="info-word"><?php echo htmlspecialchars($translation['word']); ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Source Language</span>
          <span class="info-value"><?php echo strtoupper(htmlspecialchars($translation['source_lang'])); ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Target Language</span>
          <span class="info-value"><?php echo strtoupper(htmlspecialchars($translation['target_lang'])); ?></span>
        </div>
      </div>

      <div class="form-group">
        <label for="translation">Translated Output</label>
        <textarea name="translation" id="translation" rows="4" placeholder="Enter the translation..." required></textarea>
      </div>

      <input type="hidden" name="id" value="<?php echo $translation['id']; ?>">

      <div class="form-actions">
        <button type="submit" name="btnsubmit" class="btn-primary">
          <span class="material-icons-outlined" style="font-size:18px">add</span>
          Add Translation
        </button>
        <a href="AdminMainPage.php" class="btn-secondary">
          <span class="material-icons-outlined" style="font-size:18px">close</span>
          Cancel
        </a>
      </div>

    </form>
  </div>
</div>

<script>
// Highlight active nav
const currentPage = window.location.pathname.split('/').pop();
if (currentPage === 'AdminMainPage.php') {
  document.getElementById('navUnknown').classList.add('active');
} else if (currentPage === 'PLT_MainPage.php') {
  document.getElementById('navMain').classList.add('active');
}
</script>

</body>
</html>
