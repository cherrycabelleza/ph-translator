<?php
require_once "config.php";
session_start();

/* OPTIONAL HARD GUARD
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
*/

$result = mysqli_query(
    $link,
    "SELECT * FROM logs ORDER BY log_date DESC"
);

// If query failed, try without log_date (backward compatibility)
if (!$result) {
    $result = mysqli_query(
        $link,
        "SELECT * FROM logs ORDER BY id DESC"
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Translation Logs</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="admin.css" rel="stylesheet">
</head>
<body>

<div class="admin-container">
  <header class="admin-header">
    <h1 class="admin-title">Translation Logs</h1>
    <div class="nav-buttons">
      <a href="PLT_MainPage.php" class="nav-btn" id="navMain">
        <span class="material-icons-outlined" style="font-size:16px">home</span>
        Main Page
      </a>
      <a href="AdminMainPage.php" class="nav-btn" id="navUnknown">
        <span class="material-icons-outlined" style="font-size:16px">list</span>
        Unknown Words
      </a>
      <a href="TranslationPage.php" class="nav-btn" id="navTranslations">
        <span class="material-icons-outlined" style="font-size:16px">translate</span>
        Translations
      </a>
      <a href="users-translations-adminview.php" class="nav-btn" id="navModeration">
        <span class="material-icons-outlined" style="font-size:16px">gavel</span>
        Moderation
      </a>
      <a href="TranslationLogs.php" class="nav-btn" id="navLogs">
        <span class="material-icons-outlined" style="font-size:16px">history</span>
        Logs
      </a>
      <a href="libraries.html" class="nav-btn" id="navLibraries">
        <span class="material-icons-outlined" style="font-size:16px">menu_book</span>
        Library
      </a>
      <a href="tts-tester.html" class="nav-btn" id="navTTS">
        <span class="material-icons-outlined" style="font-size:16px">volume_up</span>
        TTS Tester
      </a>
      <a href="admin_word_checker.html" class="nav-btn" id="navWordCheck">
        <span class="material-icons-outlined" style="font-size:16px">music_note</span>
        Word Audio
      </a>
      <a href="admin_syllable_checker.html" class="nav-btn" id="navSyllableCheck">
        <span class="material-icons-outlined" style="font-size:16px">grid_on</span>
        Syllable Audio
      </a>
    </div>
  </header>

  <p class="admin-subtitle">Translation activity log grouped by action type.</p>

  <?php 
  // Debug: Check if query failed
  if (!$result) {
      echo "<div class='error-message'>Query Error: " . mysqli_error($link) . "</div>";
      echo "<pre>Please check your database table 'logs' column names match the query.</pre>";
  } else {
      // Debug: Show row count
      $row_count = mysqli_num_rows($result);
      echo "<div style='padding:10px; background:#e3f2fd; border-radius:5px; margin-bottom:15px; font-size:14px;'>📊 Debug: Found <strong>$row_count</strong> rows in logs table</div>";
      
      if($row_count > 0): 
  ?>
    <table>
      <thead>
        <tr>
          <th>Date & Time</th>
          <th>Action</th>
          <th>Language Pair</th>
          <th>Word</th>
          <th>Translation</th>
        </tr>
      </thead>
      <tbody>
        <?php while($log = mysqli_fetch_assoc($result)): ?>
        <tr>
          <td><?= $log['log_date'] ?? $log['date'] ?? $log['created_at'] ?? '-' ?></td>
          <td><span class="badge <?= $log['action'] ?>"><?= $log['action'] ?></span></td>
          <td><?= htmlspecialchars($log['source_lang']) ?> → <?= htmlspecialchars($log['target_lang']) ?></td>
          <td><?= htmlspecialchars($log['word']) ?></td>
          <td><?= htmlspecialchars($log['translation']) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="empty-state">
      <span class="material-icons-outlined" style="font-size:48px">inbox</span>
      <p>No translation logs found.</p>
    </div>
    <?php endif; 
    } // end else
    ?>
  </div>
</div>

<script>
// Set active nav
const currentPage = window.location.pathname.split('/').pop();
if (currentPage === 'PLT_MainPage.php') {
  document.getElementById('navMain').classList.add('active');
} else if (currentPage === 'AdminMainPage.php') {
  document.getElementById('navUnknown').classList.add('active');
} else if (currentPage === 'TranslationPage.php') {
  document.getElementById('navTranslations').classList.add('active');
} else if (currentPage === 'TranslationLogs.php') {
  document.getElementById('navLogs').classList.add('active');
} else if (currentPage === 'libraries.html') {
  document.getElementById('navLibraries').classList.add('active');
} else if (currentPage === 'tts-tester.html') {
  document.getElementById('navTTS').classList.add('active');
} else if (currentPage === 'admin_word_checker.html') {
  document.getElementById('navWordCheck').classList.add('active');
} else if (currentPage === 'admin_syllable_checker.html') {
  document.getElementById('navSyllableCheck').classList.add('active');
}
</script>

</body>
</html>
