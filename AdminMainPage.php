<?php
require_once "config.php";
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>AdminMainPage — Unknown Words</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="admin.css" rel="stylesheet">
</head>
<body>

<div class="admin-container">
  <header class="admin-header">
    <h1 class="admin-title">Unknown Words</h1>
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

  <div class="toolbar">
    <div></div>
    <button class="refresh-btn" id="btnRefresh">
      <span class="material-icons-outlined">refresh</span>
      Refresh
    </button>
  </div>

  <?php
  // Fetch unknown words grouped by source → target
  $sql = "SELECT source_lang, target_lang, id, word, date_added 
          FROM unknown_words 
          ORDER BY source_lang, target_lang, date_added DESC";
  $result = mysqli_query($link, $sql);

  $groups = [];
  while($row = mysqli_fetch_assoc($result)){
      $key = $row['source_lang'] . " → " . $row['target_lang'];
      $groups[$key][] = $row;
  }

  if($groups){
      $firstGroup = true;
      foreach($groups as $lang => $words){
          $isExpanded = $firstGroup ? 'expanded' : '';
          $toggleIcon = $firstGroup ? 'expand_less' : 'expand_more';
          $firstGroup = false;
          
          echo "<div class='lang-group $isExpanded' data-lang='$lang'>";
          echo "<div class='lang-header' onclick=\"toggleGroup(this.parentElement)\">";
          echo "<span class='lang-direction'>$lang</span>";
          echo "<span class='material-icons-outlined lang-toggle'>$toggleIcon</span>";
          echo "</div>";
          echo "<div class='table-container'>";
          echo "<table><tr><th style='width:60px'>ID</th><th>Word</th><th style='width:180px'>Date Added</th></tr>";
          foreach($words as $w){
              echo "<tr onclick=\"window.location='AddTranslation.php?id={$w['id']}'\">";
              echo "<td>{$w['id']}</td>";
              echo "<td>".htmlspecialchars($w['word'])."</td>";
              echo "<td>{$w['date_added']}</td></tr>";
          }
          echo "</table></div>";
          echo "</div>";
      }
  } else {
      echo "<div class='empty-state'>No unfamiliar words yet.</div>";
  }
  ?>
</div>

<script>
let expandedGroup = null;

function toggleGroup(group) {
  const isExpanded = group.classList.contains('expanded');
  
  // Close currently expanded group
  if (expandedGroup && expandedGroup !== group) {
    expandedGroup.classList.remove('expanded');
    expandedGroup.querySelector('.lang-toggle').textContent = 'expand_more';
  }
  
  if (isExpanded) {
    // Collapse this group
    group.classList.remove('expanded');
    group.querySelector('.lang-toggle').textContent = 'expand_more';
    expandedGroup = null;
  } else {
    // Expand this group
    group.classList.add('expanded');
    group.querySelector('.lang-toggle').textContent = 'expand_less';
    expandedGroup = group;
  }
}

const btnRefresh = document.getElementById('btnRefresh');
async function loadUnknowns(){
  try{
    const resp = await fetch(window.location.href);
    const html = await resp.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html,"text/html");
    const newContent = doc.querySelector('.admin-container').innerHTML;
    document.querySelector('.admin-container').innerHTML = newContent;
    
    // Re-attach toggle functionality
    expandedGroup = null;
  }catch(e){console.error(e);}
}
btnRefresh.addEventListener('click',loadUnknowns);

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
