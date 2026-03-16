<?php
require_once "config.php";
session_start();

if(isset($_POST['uploadTranslations'])){

    $jsFile = __DIR__ . '/dialectDictionary.js'; 
    $existing = [];

    // READ EXISTING JS FILE
    if(file_exists($jsFile)){
        $content = file_get_contents($jsFile);

        if(preg_match('/const\s+dialectDictionaries\s*=\s*\{([\s\S]*?)\};/m', $content, $matches)){
            $raw = $matches[1];
            $lines = explode("\n", $raw);
            $currentPair = null;
            foreach($lines as $line){
                if(preg_match('/^\s*([a-zA-Z0-9_]+)\s*:\s*\{/', $line, $m)){
                    $currentPair = $m[1];
                    $existing[$currentPair] = [];
                } elseif(preg_match('/^\s*"(.+)"\s*:\s*"(.+)"/', $line, $m) && $currentPair){
                    $existing[$currentPair][$m[1]] = $m[2];
                }
            }
        }
    }

    // FETCH NEW TRANSLATIONS
    $sql = "SELECT * FROM translations WHERE translation <> '' ORDER BY source_lang, target_lang, date_added ASC";
    $result = mysqli_query($link, $sql);
    $addedIds = [];

   while($row = mysqli_fetch_assoc($result)){
    $pair = strtolower(trim($row['source_lang'])) . "_" . strtolower(trim($row['target_lang']));
    $word = trim($row['word']);
    $translation = trim($row['translation']);
    $date = date("m/d/y", strtotime($row['date_added']));

    if($word === "" || $translation === "") continue;

    if(!isset($existing[$pair])) $existing[$pair] = [];

    if(!isset($existing[$pair][$word])){
        $existing[$pair][$word] = $translation . " // learned $date";
        $addedIds[] = $row['id'];
    }
}

// SORT WORDS ALPHABETICALLY
foreach($existing as &$words){
    ksort($words, SORT_NATURAL | SORT_FLAG_CASE);
}
unset($words);

// REBUILD JS FILE
$jsContent  = "// dialectDictionary.js\n";
$jsContent .= "// Multi-dialect bidirectional dictionary\n\n";
$jsContent .= "const dialectDictionaries = {\n";

foreach($existing as $pair => $words){
    $jsContent .= "  // " . ucwords(str_replace('_',' ↔ ',$pair)) . "\n";
    $jsContent .= "  $pair: {\n";
    foreach($words as $word => $translation){
        // Separate comment from translation
        if(strpos($translation, "// learned") !== false){
            $parts = explode("// learned", $translation);
            $t = addslashes(trim($parts[0]));           // translation only
            $comment = "// learned" . trim($parts[1]);  // comment outside quotes
        } else {
            $t = addslashes($translation);
            $comment = "";
        }

        $w = addslashes($word);
        $jsContent .= "    \"$w\": \"$t\", $comment\n";
    }
    $jsContent .= "  },\n\n";
}

$jsContent .= "};\n\n";


    // Translator function
    $jsContent .= "function translateDialect(text, fromLang, toLang) {\n";
    $jsContent .= "  text = text.toLowerCase().trim();\n";
    $jsContent .= "  const key = fromLang + '_' + toLang;\n";
    $jsContent .= "  const reverseKey = toLang + '_' + fromLang;\n\n";
    $jsContent .= "  if(dialectDictionaries[key]) return dialectDictionaries[key][text] || '[Walang translation]';\n";
    $jsContent .= "  if(dialectDictionaries[reverseKey]) {\n";
    $jsContent .= "    const dict = dialectDictionaries[reverseKey];\n";
    $jsContent .= "    for(const k in dict) if(dict[k].toLowerCase().trim() === text) return k;\n";
    $jsContent .= "    return '[Walang translation]';\n";
    $jsContent .= "  }\n";
    $jsContent .= "  return '[Walang available dictionary]';\n";
    $jsContent .= "};\n";

    // WRITE JS FILE
    file_put_contents($jsFile, $jsContent);

    // DELETE UPLOADED ROWS
    if(!empty($addedIds)){
        $deleteSQL = "DELETE FROM translations WHERE id IN (" . implode(",", $addedIds) . ")";
        mysqli_query($link, $deleteSQL);
    }

    $uploadMsg = "Translations uploaded successfully to dialectDictionary.js!";
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Translations — Admin Page</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="admin.css" rel="stylesheet">
</head>
<body>

<div class="admin-container">
  <header class="admin-header">
    <h1 class="admin-title">Translations</h1>
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

  <p class="admin-subtitle" style="font-size:14px;color:#6b7280;margin:-12px 0 20px 0;">Translations grouped by language pair.</p>

  <?php if(isset($uploadMsg)): ?>
    <div class="upload-msg"><?php echo $uploadMsg; ?></div>
  <?php endif; ?>

  <div class="toolbar">
    <form method="post" style="margin:0">
      <button type="submit" name="uploadTranslations" class="btn btn-primary">
        Upload All Translations
      </button>
    </form>
    <button class="btn btn-secondary" id="refreshBtn">
      Refresh
    </button>
  </div>

  <div class="main-card">
    <?php
    // Fetch all translations
    $sql = "SELECT * FROM translations ORDER BY source_lang, target_lang, date_added DESC";
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
            echo "<table>";
            echo "<tr><th style='width:60px'>ID</th><th>Word</th><th>Translation</th><th style='width:180px'>Date Added</th><th style='width:120px'>Actions</th></tr>";
            foreach($words as $w){
                echo "<tr>";
                echo "<td>{$w['id']}</td>";
                echo "<td>".htmlspecialchars($w['word'])."</td>";
                echo "<td>".htmlspecialchars($w['translation'])."</td>";
                echo "<td>{$w['date_added']}</td>";
                echo "<td class='action-links'>
                        <a href='EditTranslation.php?id={$w['id']}'>Edit</a>
                        <a href='DeleteTranslation.php?id={$w['id']}'>Delete</a>
                      </td>";
                echo "</tr>";
            }
            echo "</table></div>";
            echo "</div>";
        }
    } else {
        echo "<div class='empty-state'>";
        echo "<span class='material-icons-outlined'>history</span>";
        echo "<h3>No translations yet</h3>";
        echo "<p>Added translations will appear here.</p>";
        echo "</div>";
    }
    ?>
  </div>
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

// Refresh functionality
async function reloadPage() {
  try {
    const resp = await fetch(window.location.href);
    const html = await resp.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, "text/html");
    const newContent = doc.querySelector('.main-card').innerHTML;
    document.querySelector('.main-card').innerHTML = newContent;
    expandedGroup = null;
  } catch(e) {
    console.error(e);
    location.reload();
  }
}

document.getElementById('refreshBtn').addEventListener('click', reloadPage);

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
