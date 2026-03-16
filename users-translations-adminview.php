<?php
require_once "config.php";
session_start();

/* OPTIONAL: Admin authentication check
if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: login.php");
    exit();
}
*/

/* ==========================
HANDLE ADMIN ACTIONS
========================== */
if(isset($_GET['action']) && isset($_GET['id'])){

    $id = intval($_GET['id']);

    // fetch submission
    $stmt = mysqli_prepare($link,"SELECT * FROM users_translations WHERE id=?");
    mysqli_stmt_bind_param($stmt,"i",$id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if(!$row){ die("Submission not found"); }

    $email = $row['email'];

    /* ==========================
    UPLOAD TO JS LIBRARY
    ========================== */
    if($_GET['action']=="upload"){

        $word = trim($row['word']);
        $translation = trim($row['translation']);
        $source = strtolower(trim($row['source_lang']));
        $target = strtolower(trim($row['target_lang']));
        $date = date("m/d/y", strtotime($row['date_added']));

        if($word !== "" && $translation !== ""){

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

            // language pair key
            $pair = $source . "_" . $target;

            if(!isset($existing[$pair])) $existing[$pair] = [];

            // add if not exists
            if(!isset($existing[$pair][$word])){
                $existing[$pair][$word] = $translation . " // learned $date";
            }

            // SORT WORDS
            foreach($existing as &$words){
                ksort($words, SORT_NATURAL | SORT_FLAG_CASE);
            }
            unset($words);

            // REBUILD JS FILE
            $jsContent  = "// dialectDictionary.js\n";
            $jsContent .= "// Multi-dialect bidirectional dictionary\n\n";
            $jsContent .= "const dialectDictionaries = {\n";

            foreach($existing as $pairKey => $words){
                $jsContent .= "  // " . ucwords(str_replace('_',' ↔ ',$pairKey)) . "\n";
                $jsContent .= "  $pairKey: {\n";
                foreach($words as $w => $t){
                    if(strpos($t, "// learned") !== false){
                        $parts = explode("// learned", $t);
                        $tVal = addslashes(trim($parts[0]));
                        $comment = "// learned" . trim($parts[1]);
                    } else {
                        $tVal = addslashes($t);
                        $comment = "";
                    }
                    $wEsc = addslashes($w);
                    $jsContent .= "    \"$wEsc\": \"$tVal\", $comment\n";
                }
                $jsContent .= "  },\n\n";
            }
            $jsContent .= "};\n\n";

            // translator function
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

            // REMOVE uploaded submission
            mysqli_query($link,"DELETE FROM users_translations WHERE id='$id'");
        }

        header("Location: users-translations-adminview.php");
        exit;
    }

    /* ==========================
    DELETE SUBMISSION
    ========================== */
    if($_GET['action']=="delete"){
        mysqli_query($link,"DELETE FROM users_translations WHERE id='$id'");
        header("Location: users-translations-adminview.php");
        exit;
    }

    /* ==========================
    WARN USER
    ========================== */
    if($_GET['action']=="warn"){
        $user = mysqli_query($link,"SELECT status FROM users WHERE email='$email'");
        $data = mysqli_fetch_assoc($user);
        $status = $data['status'] ?? "active";

        if($status=="active") $new="warning1";
        elseif($status=="warning1") $new="warning2";
        elseif($status=="warning2") $new="warning2";

        mysqli_query($link,"UPDATE users SET status='$new' WHERE email='$email'");
        header("Location: users-translations-adminview.php");
        exit;
    }

    /* ==========================
    BAN USER
    ========================= */
    if($_GET['action']=="ban"){
        mysqli_query($link,"UPDATE users SET status='banned' WHERE email='$email'");
        header("Location: users-translations-adminview.php");
        exit;
    }
}

/* ==========================
LOAD USER SUBMISSIONS
========================== */
$sql = "
SELECT ut.*, u.status
FROM users_translations ut
LEFT JOIN users u ON ut.email = u.email
ORDER BY ut.date_added DESC
";

$result = mysqli_query($link,$sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>User Translation Moderation</title>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link href="admin.css" rel="stylesheet">
<style>
.status-active{color:var(--success);font-weight:bold;}
.status-warning1{color:#f39c12;font-weight:bold;}
.status-warning2{color:#e67e22;font-weight:bold;}
.status-banned{color:#dc2626;font-weight:bold;}

.action-btn {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s ease;
}

.action-btn.upload { background: #d1fae5; color: #065f46; }
.action-btn.upload:hover { background: #065f46; color: white; }

.action-btn.delete { background: #fee2e2; color: #991b1b; }
.action-btn.delete:hover { background: #991b1b; color: white; }

.action-btn.warn { background: #fef3c7; color: #92400e; }
.action-btn.warn:hover { background: #92400e; color: white; }

.action-btn.ban { background: #374151; color: white; }
.action-btn.ban:hover { background: #111827; }

.table-container {
    display: block; /* Override admin.css display:none */
    max-height: none; /* Allow table to grow as needed */
}

.actions-cell {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
</style>
</head>

<body>

<div class="admin-container">
  <header class="admin-header">
    <h1 class="admin-title">Moderation</h1>
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

  <p class="admin-subtitle">Moderate user-submitted translations and manage user status.</p>

  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>User Email</th>
          <th>Word</th>
          <th>From</th>
          <th>To</th>
          <th>Translation</th>
          <th>User Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while($row = mysqli_fetch_assoc($result)): ?>
        <tr>
          <td><?php echo htmlspecialchars($row['email']); ?></td>
          <td style="font-weight:600"><?php echo htmlspecialchars($row['word']); ?></td>
          <td><span class="badge" style="background:var(--accent); color:var(--primary)"><?php echo strtoupper(htmlspecialchars($row['source_lang'])); ?></span></td>
          <td><span class="badge" style="background:var(--accent); color:var(--primary)"><?php echo strtoupper(htmlspecialchars($row['target_lang'])); ?></span></td>
          <td style="color:var(--primary); font-weight:500"><?php echo htmlspecialchars($row['translation']); ?></td>
          <td>
            <span class="status-<?php echo $row['status'] ?? 'active'; ?>">
              <?php echo ucfirst($row['status'] ?? 'active'); ?>
            </span>
          </td>
          <td class="actions-cell">
            <a href="?action=upload&id=<?php echo $row['id']; ?>" class="action-btn upload" title="Approve and Upload">
              <span class="material-icons-outlined" style="font-size:14px">publish</span> Upload
            </a>
            <a href="?action=delete&id=<?php echo $row['id']; ?>" class="action-btn delete" onclick="return confirm('Delete this submission?')" title="Reject and Delete">
              <span class="material-icons-outlined" style="font-size:14px">delete</span> Delete
            </a>
            <a href="?action=warn&id=<?php echo $row['id']; ?>" class="action-btn warn" title="Warn User">
              <span class="material-icons-outlined" style="font-size:14px">warning</span> Warn
            </a>
            <a href="?action=ban&id=<?php echo $row['id']; ?>" class="action-btn ban" onclick="return confirm('Ban this user?')" title="Ban User">
              <span class="material-icons-outlined" style="font-size:14px">block</span> Ban
            </a>
          </td>
        </tr>
        <?php endwhile; ?>
        
        <?php if(mysqli_num_rows($result) == 0): ?>
        <tr>
          <td colspan="7" class="empty-state">
            <span class="material-icons-outlined">fact_check</span>
            No pending user translations to moderate.
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Set active nav
const navModeration = document.getElementById('navModeration');
if (navModeration) {
    navModeration.classList.add('active');
}
</script>

</body>
</html>