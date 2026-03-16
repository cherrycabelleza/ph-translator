<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Philippine Languages Translator</title>
  <link rel="stylesheet" href="plt-main.css" />
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#2c3e50">
</head>
<body>

<?php
require_once "config.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $word   = trim($_POST['word'] ?? '');
    $source = trim($_POST['source_lang'] ?? '');
    $target = trim($_POST['target_lang'] ?? '');

    if ($word && $source && $target) {

        // 1. CHECK IF EXISTS
        $check = "SELECT id FROM unknown_words WHERE word = ? AND source_lang = ? AND target_lang = ? LIMIT 1";
        $stmtCheck = mysqli_prepare($link, $check);
        mysqli_stmt_bind_param($stmtCheck, "sss", $word, $source, $target);
        mysqli_stmt_execute($stmtCheck);
        mysqli_stmt_store_result($stmtCheck);

        if (mysqli_stmt_num_rows($stmtCheck) > 0) {
            echo "EXISTING";  // Already in DB → do NOT insert
            exit;
        }

        // 2. INSERT IF NOT EXISTING
        $sql = "INSERT INTO unknown_words (word, source_lang, target_lang, date_added)
                VALUES (?, ?, ?, NOW())";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "sss", $word, $source, $target);

        if (mysqli_stmt_execute($stmt)) {
            echo "INSERTED";
        } else {
            echo "ERROR: " . mysqli_error($link);
        }

    } else {
        echo "MISSING";
    }
    exit;
}
?>
<?php
// Check if user is logged in
if(isset($_SESSION['email'])){
    $email = $_SESSION['email'];

    // Fetch current status
    $stmt = mysqli_prepare($link, "SELECT status FROM users WHERE email=?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    // If user not found or banned, log them out
    if(!$user || $user['status'] === "banned"){
        session_unset();
        session_destroy();

        // Optional: redirect to login with message
        session_start();
        $_SESSION['login_error'] = "Your account has been banned. You cannot log in.";
        header("Location: PLT_MainPage.php"); // change this to your login page
        exit;
    }
}
?>
  <script src="inputLimiter.js"></script>
  <script src="dialectDictionary.js"></script>
  <script src="tts-assistant.js"> </script>
  <script src="autodetect.js"> </script>

  <div class="viewport">

  <div class="main-layout">

    <div class="container">
      <div class="layout">
        <div>
          <header class="header-card">
            <img src="Logo.png" alt="Philippines Languages Translator Logo" class="header-logo" />
            <div class="header-text">
              <h1>Philippine Languages Translator</h1>
              <p class="sub">Speech-to-Text • Dialect Translation • Text-to-Speech</p>
              <button id="installApp" class="btn btn-outline" style="display:none; margin-top: 10px; height: 36px; padding: 0 12px; font-size: 13px;">
                <svg viewBox="0 0 24 24" width="16" height="16" style="margin-right: 6px; fill: currentColor;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14h2v2h-2v-2zm0-10h2v8h-2V6z"/></svg>
                Install App
              </button>
            </div>
          </header>

          <section class="lang-card" aria-label="Language selection">
            <div class="lang-row">
              <div class="select-wrap">
                <select id="fromLang" aria-label="Source language">
                  <option value="auto">Detect language</option>
                  <option value="ilo">Ilocano</option>
                  <option value="ceb">Cebuano</option>
                  <option value="hil">Hiligaynon</option>
                  <option value="tl">Tagalog (Filipino)</option>
                  <option value="en">English</option>

                </select>
              </div>

              <button id="swapBtn" class="swap-btn" type="button" title="Swap languages" aria-label="Swap languages">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M7 7h11l-2.6-2.6a1 1 0 1 1 1.4-1.4l4.3 4.3a1 1 0 0 1 0 1.4l-4.3 4.3a1 1 0 0 1-1.4-1.4L18 9H7a1 1 0 0 1 0-2Zm10 10H6l2.6 2.6a1 1 0 1 1-1.4 1.4L2.9 16.7a1 1 0 0 1 0-1.4L7.2 11a1 1 0 0 1 1.4 1.4L6 15h11a1 1 0 1 1 0 2Z"/>
                </svg>
              </button>

              <div class="select-wrap">
                <select id="toLang" aria-label="Target language">
                  <option value="ilo">Ilocano</option>
                  <option value="ceb">Cebuano</option>
                  <option value="hil">Hiligaynon</option>
                  <option value="tl">Tagalog (Filipino)</option>
                  <option value="en">English</option>
                  
                </select>
              </div>
            </div>
          </section>

          <section class="translator-card" aria-label="Translator">
            <div class="panes" aria-label="Translation panes">
              <div class="field" aria-label="Input">
                <textarea id="inputText" placeholder="Enter text or speak…" aria-label="Input text"></textarea>
                <button class="icon-inside icon-top-right" id="clearBtn" type="button" title="Clear input" aria-label="Clear">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M18.3 5.7a1 1 0 0 1 0 1.4L13.4 12l4.9 4.9a1 1 0 1 1-1.4 1.4L12 13.4l-4.9 4.9a1 1 0 0 1-1.4-1.4l4.9-4.9-4.9-4.9a1 1 0 0 1 1.4-1.4L12 10.6l4.9-4.9a1 1 0 0 1 1.4 0Z"/>
                  </svg>
                </button>
                <button class="icon-inside icon-bottom-right" id="micBtn" type="button" title="Start/Stop recording" aria-label="Speak">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 1 0-6 0v5a3 3 0 0 0 3 3Zm-1 6v-2.06A7.002 7.002 0 0 1 5 11a1 1 0 1 1 2 0 5 5 0 1 0 10 0 1 1 0 1 1 2 0 7.002 7.002 0 0 1-6 6.94V20h3a1 1 0 1 1 0 2H8a1 1 0 1 1 0-2h3Z"/>
                  </svg>
                </button>
              </div>

              <div class="field" aria-label="Output">
                <button class="icon-inside icon-top-right" id="copyBtn" type="button" title="Copy translation" aria-label="Copy translation">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M8 7a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-9a2 2 0 0 1-2-2V7Zm2 0v11h9V7h-9ZM3 6a2 2 0 0 1 2-2h1a1 1 0 1 1 0 2H5v12h1a1 1 0 1 1 0 2H5a2 2 0 0 1-2-2V6Z"/>
                  </svg>
                </button>

                <div id="result" class="output-box" aria-live="polite"></div>

                <button class="icon-inside icon-bottom-right" id="playBtn" type="button" title="Speak translation" aria-label="Speak translation">
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M3 10a2 2 0 0 1 2-2h3.2l4.7-3.5A1 1 0 0 1 14 5v14a1 1 0 0 1-1.1.99 1 1 0 0 1-.5-.2L8.2 16H5a2 2 0 0 1-2-2v-4Zm2 0v4h3.6a1 1 0 0 1 .6.2L12 16.3V7.7L9.2 9.8a1 1 0 0 1-.6.2H5Zm13.5 2a4.5 4.5 0 0 0-1.2-3.1 1 1 0 0 1 1.5-1.3A6.5 6.5 0 0 1 21 12a6.5 6.5 0 0 1-2.2 4.4 1 1 0 1 1-1.5-1.3A4.5 4.5 0 0 0 18.5 12Zm-2.8 0a2 2 0 0 0-.6-1.4 1 1 0 0 1 1.4-1.4A4 4 0 0 1 17.7 12a4 4 0 0 1-1.2 2.8 1 1 0 0 1-1.4-1.4 2 2 0 0 0 .6-1.4Z"/>
                  </svg>
                </button>
              </div>
            </div>

            <div class="meta-output">
              <div id="status">Ready</div>
              <div class="meta-right">
                <div id="audioWarning" class="audio-warning"> The Phrase has been added to our database. </div>
              </div>
            </div>

            <div class="suggestions-container">
              <div class="suggestions-title">Suggestions</div>
              <div id="languageIndicators" class="indicators"></div>
              <div id="suggestionBox" class="suggestions-scroll">
                <!-- Suggestions will be populated by JavaScript -->
              </div>
            </div>

            <!-- Word Variants -->
            <div class="word-variants-container" id="wordVariantsContainer" style="display:none;">
              <div class="word-variants-title">Word Variants</div>
              <div id="wordVariantsBox" class="word-variants-scroll"></div>
            </div>

            <button class="btn btn-primary" id="translateBtn" type="button" style="display:none;">Translate</button>
          </section>
        </div>

        <aside aria-label="Translation history (desktop)">
          <div id="history" class="history-panel">
            <div class="history-panel-label">Translation History</div>
            <!-- History items will be populated by JavaScript -->
          </div>
        </aside>
      </div>
      <!-- Mobile: Floating history button + modal (kept inside main card) -->

      <!-- Mobile: Floating history button + modal (kept inside main card) -->
      <input id="historyToggle" type="checkbox" aria-hidden="true" />
      <label for="historyToggle" class="history-fab" title="Translation History" aria-label="Translation History">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M12 8a1 1 0 0 1 1 1v2.6l2.1 1.2a1 1 0 1 1-1 1.7l-2.6-1.5A1 1 0 0 1 11 12V9a1 1 0 0 1 1-1Zm0-6a10 10 0 1 1-9.95 11H2a1 1 0 0 1 0-2h3.2a1 1 0 0 1 1 1A8 8 0 1 0 12 4a7.95 7.95 0 0 0-5.6 2.3 1 1 0 1 1-1.4-1.4A9.95 9.95 0 0 1 12 2Z"/>
        </svg>
      </label>
      <label for="historyToggle" class="history-overlay" aria-hidden="true"></label>
      <aside class="history-modal" role="dialog" aria-label="Translation History" aria-modal="true">
        <div class="history-head">
          <p class="history-title">Translation History</p>
          <label for="historyToggle" class="history-close" title="Close" aria-label="Close history">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.3 5.7a1 1 0 0 1 0 1.4L13.4 12l4.9 4.9a1 1 0 1 1-1.4 1.4L12 13.4l-4.9 4.9a1 1 0 0 1-1.4-1.4l4.9-4.9-4.9-4.9a1 1 0 0 1 1.4-1.4L12 10.6l4.9-4.9a1 1 0 0 1 1.4 0Z"/></svg>
          </label>
        </div>
        <div class="history-body">
          <div class="history-empty">No history yet. Your recent translations will appear here.</div>
          <div id="historyMobile"></div>
        </div>
      </aside>

      
    </div>

<div class="side-panel">

<div class="card info-card">
<h2 class="card-title">Contribute Translations</h2>

<p class="card-sub">
Using the translator does not require an account. However,
if you want to contribute translations you may register.
</p>

<ul class="check-list">
<li><span aria-hidden="true">✓</span> Submit new translations</li>
<li><span aria-hidden="true">✓</span> Help expand language library</li>
<li><span aria-hidden="true">✓</span> Improve translation accuracy</li>
</ul>
</div>

<?php if(!isset($_SESSION['email'])): ?>

<div class="card auth-card">

<div class="tabs" role="tablist" aria-label="Authentication">
<button id="loginTab" role="tab" aria-controls="loginBox" aria-selected="true" onclick="showLogin()" class="tab active">Login</button>
<button id="registerTab" role="tab" aria-controls="registerBox" aria-selected="false" onclick="showRegister()" class="tab">Register</button>
</div>

<div id="loginBox" class="tab-panel">
<form action="login.php" method="POST" novalidate>

<div class="form-control">
<label for="login_email">Email</label>
<input id="login_email" type="email" name="email" placeholder="Email" required>
</div>

<div class="form-control">
<label for="login_password">Password</label>
<input id="login_password" type="password" name="password" placeholder="Password" required>
</div>

<button type="submit" class="btn btn-primary btn-block">
Login
</button>
</form>
</div>

<div id="registerBox" class="tab-panel" hidden>

<form action="register.php" method="POST" novalidate>

<div class="form-control">
<label for="reg_name">Full Name</label>
<input id="reg_name" type="text" name="name" placeholder="Full Name" required>
</div>

<div class="form-control">
<label for="reg_email">Email</label>
<input id="reg_email" type="email" name="email" placeholder="Email" required>
</div>

<div class="form-control">
<label for="reg_password">Password</label>
<input id="reg_password" type="password" name="password" placeholder="Password" required>
</div>

<button type="submit" class="btn btn-primary btn-block">
Register
</button>

</form>
</div>
</div>

</div>

<?php else: ?>

<!-- USER PANEL -->
<div class="card user-card">
  <h2 class="card-title">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h2>
  <p class="card-sub">You are currently logged in. Access your contributions or sign out below.</p>

  <div class="user-actions">
    <a href="user_translations.php" class="btn btn-green btn-block" style="text-decoration:none; margin-bottom: 12px;">
      Add/View My Translations
    </a>

    <a href="logout.php" class="btn btn-red btn-block" style="text-decoration:none;">
      Logout Account
    </a>
  </div>
</div>

<?php endif; ?>

</div> <!-- closes side-panel -->
</div> <!-- closes main-layout -->

  <footer>
    <p class="small">Divided by land, culture, and dialect, yet united at heart — may this system help Filipinos understand each other.</p>
  </footer>

</div> <!-- closes viewport -->

  
  <script>
    // UI elements
    const micBtn = document.getElementById('micBtn');
    const translateBtn = document.getElementById('translateBtn');
    const swapBtn = document.getElementById('swapBtn');
    const playBtn = document.getElementById('playBtn');
    const playBtn2 = document.getElementById('playBtn2');
    const copyBtn = document.getElementById('copyBtn');
    const clearBtn = document.getElementById('clearBtn');
    const inputText = document.getElementById('inputText');
    const resultEl = document.getElementById('result');
    const statusEl = document.getElementById('status');
    const fromLang = document.getElementById('fromLang');
    const toLang = document.getElementById('toLang');
    const audioWarningEl = document.getElementById('audioWarning');
    const autoToggleBtn = document.getElementById('autoToggleBtn');
    
    // Auto-detect toggle functionality
    let autoDetectEnabled = true; // Default: auto-detect is ON
    
    function updateAutoToggleUI() {
        if (!autoToggleBtn) return;
        if (autoDetectEnabled) {
            autoToggleBtn.style.background = '#eef6ff';
            autoToggleBtn.style.borderColor = 'rgba(11,120,255,0.3)';
            autoToggleBtn.querySelector('svg').style.fill = 'var(--accent)';
            autoToggleBtn.title = 'Auto-detect: ON - Click to disable';
        } else {
            autoToggleBtn.style.background = '#f1f4f9';
            autoToggleBtn.style.borderColor = 'rgba(148,163,184,0.28)';
            autoToggleBtn.querySelector('svg').style.fill = '#64748b';
            autoToggleBtn.title = 'Auto-detect: OFF - Click to enable';
        }
    }
    
    if (autoToggleBtn) {
        autoToggleBtn.addEventListener('click', () => {
            autoDetectEnabled = !autoDetectEnabled;
            updateAutoToggleUI();
            
            // If enabling auto-detect, reset to auto mode
            if (autoDetectEnabled) {
                fromLang.value = 'auto';
                if (languageIndicatorsEl) languageIndicatorsEl.textContent = 'Auto-detect enabled';
            }
            
            // Trigger re-translation if there's input
            if (inputText.value.trim()) {
                setTimeout(() => {
                    translateBtn.click();
                }, 100);
            }
        });
        
        // Initialize UI
        updateAutoToggleUI();
    }

    // Check if dictionary has any translations and show/hide warning
    function checkDictionaryAndShowWarning() {
        // Hide warning by default - it will be shown during translation if needed
        if (audioWarningEl) {
            audioWarningEl.style.display = 'none';
        }
    }
    checkDictionaryAndShowWarning();

    function setMicRecordingUI(isOn) {
      if (!micBtn) return;
      micBtn.classList.toggle('is-recording', Boolean(isOn));
      micBtn.setAttribute('aria-pressed', isOn ? 'true' : 'false');
    }

    // ---------------- SUGGESTIONS FUNCTIONALITY ---------------- 
    const suggestionPhrases = {
      hiligaynon: [
        "kamusta",
        "maayong aga",
        "maayong hapon",
        "maayong gab-i",
        "salamat gid",
        "madamo gid nga salamat",
        "indi",
        "huo",
        "sa diin ang banyo",
        "buligi ako",
        "tagpila ni",
        "nasuboan ko",
        "masadya ako",
        "namit gid",
        "kadto na ko",
        // Additional formal phrases
        "ano ang balita",
        "ano ang ngalan mo",
        "anong oras na",
        "nagkaon ka na",
        "gutom na ako",
        "halong ka",
        "hulat ka",
        "sa diin ka makadto",
        "sa diin ka naga-istar",
        "paborito ko gid ni",
        "tani magkit anay ta liwat",
        "ang akon ngalan ay",
        "pasayloa ko",
        "wala kaso ah"
      ],
      ilocano: [
        "kumusta",
        "naimbag nga bigat",
        "naimbag nga malem",
        "naimbag nga rabii",
        "agyamanak",
        "agyamanak unay",
        "haan",
        "wen",
        "ayan ti banyo",
        "tulongannak",
        "mano daytoy",
        "nalidayak",
        "naragsakak",
        "naimas",
        "agpakadaak",
        // Additional formal phrases
        "anya ti damag",
        "anya ti nagan mo",
        "anya ti oras",
        "nangan kan",
        "mabisinakon",
        "ingat ka",
        "aguray",
        "sadinnu ka mapan",
        "sadinnu ka agtaeng",
        "paboritok daytoy",
        "agkita tan tu manen",
        "tinagan ko ket",
        "dispensar",
        "awan ti aniaman"
      ],
      cebuano: [
        "kamusta",
        "maayong buntag",
        "maayong hapon",
        "maayong gabie",
        "salamat",
        "daghang salamat",
        "dili",
        "oo",
        "asa ang kasilyas",
        "tabangi ko",
        "pila ni",
        "naguol ko",
        "malipayon ko",
        "lami",
        "babay",
        // Additional formal phrases
        "unsa ang balita",
        "unsa imong ngalan",
        "unsa na oras",
        "nikaon na ka",
        "gigutom na ko",
        "amping",
        "paghulat",
        "asa ka paingon",
        "asa ka nagpuyo",
        "paborito na ko ni",
        "hinaut magkita ta pag-usab",
        "ang akong ngalan kay",
        "pasensya",
        "way sapayan"
      ]
    };

    function populateSuggestions() {
      const suggestionBox = document.getElementById('suggestionBox');
      if (!suggestionBox) return;

      // Only populate static suggestions when input is empty
      // When user is typing, let autodetect.js handle dynamic suggestions
      if (inputText.value.trim().length > 0) {
        return; // Let dynamic suggestions from autodetect.js show
      }

      // Get phrases based on selected source language
      const sourceLang = fromLang.value;
      let phrases = [];

      if (sourceLang === 'hil' || sourceLang === 'hiligaynon') {
        phrases = suggestionPhrases.hiligaynon;
      } else if (sourceLang === 'ilo' || sourceLang === 'ilocano') {
        phrases = suggestionPhrases.ilocano;
      } else if (sourceLang === 'ceb' || sourceLang === 'cebuano') {
        phrases = suggestionPhrases.cebuano;
      } else {
        // Default: show mix of all three dialects
        phrases = [
          ...suggestionPhrases.cebuano.slice(0, 5),
          ...suggestionPhrases.hiligaynon.slice(0, 5),
          ...suggestionPhrases.ilocano.slice(0, 5)
        ];
      }

      suggestionBox.innerHTML = phrases.map(phrase => 
        `<span class="chip" data-phrase="${phrase}">${phrase}</span>`
      ).join('');

      // Add click handlers
      suggestionBox.querySelectorAll('.chip').forEach(chip => {
        chip.addEventListener('click', () => {
          const phrase = chip.getAttribute('data-phrase');
          inputText.value = phrase;
          inputText.focus();
          // Auto-translate after a short delay
          setTimeout(() => {
            if (!recording) autoTranslateFromInput();
          }, 300);
        });
      });
    }

    // ---------------- WORD VARIANTS (PREFIX SEARCH) ----------------
    function populateWordVariants() {
      const wordVariantsContainer = document.getElementById('wordVariantsContainer');
      const wordVariantsBox = document.getElementById('wordVariantsBox');
      if (!wordVariantsContainer || !wordVariantsBox) return;

      const inputTextVal = inputText.value.trim().toLowerCase();
      
      // Only search if input is at least 2 characters
      if (inputTextVal.length < 2) {
        wordVariantsContainer.style.display = 'none';
        return;
      }

      // Get the appropriate dictionary based on source/target language
      let dictKey = '';
      if (fromLang.value === 'tl' && toLang.value === 'ceb') dictKey = 'tagalog_cebuano';
      else if (fromLang.value === 'ceb' && toLang.value === 'tl') dictKey = 'cebuano_tagalog';
      else if (fromLang.value === 'tl' && toLang.value === 'hil') dictKey = 'tagalog_hiligaynon';
      else if (fromLang.value === 'hil' && toLang.value === 'tl') dictKey = 'hiligaynon_tagalog';
      else if (fromLang.value === 'tl' && toLang.value === 'ilo') dictKey = 'tagalog_ilocano';
      else if (fromLang.value === 'ilo' && toLang.value === 'tl') dictKey = 'ilocano_tagalog';
      else if (fromLang.value === 'en' && toLang.value === 'tl') dictKey = 'english_tagalog';
      else if (fromLang.value === 'tl' && toLang.value === 'en') dictKey = 'tagalog_english';
      else if (fromLang.value === 'ceb' && toLang.value === 'ilo') dictKey = 'cebuano_ilocano';
      else if (fromLang.value === 'ilo' && toLang.value === 'ceb') dictKey = 'ilocano_cebuano';
      else if (fromLang.value === 'ceb' && toLang.value === 'hil') dictKey = 'cebuano_hiligaynon';
      else if (fromLang.value === 'hil' && toLang.value === 'ceb') dictKey = 'hiligaynon_cebuano';
      else if (fromLang.value === 'hil' && toLang.value === 'ilo') dictKey = 'hiligaynon_ilocano';
      else if (fromLang.value === 'ilo' && toLang.value === 'hil') dictKey = 'ilocano_hiligaynon';
      else if (fromLang.value === 'en' && toLang.value === 'ceb') dictKey = 'english_cebuano';
      else if (fromLang.value === 'ceb' && toLang.value === 'en') dictKey = 'cebuano_english';
      else if (fromLang.value === 'en' && toLang.value === 'hil') dictKey = 'english_hiligaynon';
      else if (fromLang.value === 'hil' && toLang.value === 'en') dictKey = 'hiligaynon_english';
      else if (fromLang.value === 'en' && toLang.value === 'ilo') dictKey = 'english_ilocano';
      else if (fromLang.value === 'ilo' && toLang.value === 'en') dictKey = 'ilocano_english';

      const dict = window.dialectDictionaries ? window.dialectDictionaries[dictKey] : null;
      
      if (!dict) {
        wordVariantsContainer.style.display = 'none';
        return;
      }

      // Search for words that start with the input prefix
      const matchingWords = [];
      for (const word in dict) {
        if (word.startsWith(inputTextVal)) {
          matchingWords.push(word);
        }
      }

      // Limit to 10 results
      const limitedWords = matchingWords.slice(0, 10);

      if (limitedWords.length === 0) {
        wordVariantsContainer.style.display = 'none';
        return;
      }

      // Display the matching words
      wordVariantsBox.innerHTML = limitedWords.map(word => 
        `<span class="chip" data-word="${word}">${word}</span>`
      ).join('');

      // Show the container
      wordVariantsContainer.style.display = 'block';

      // Add click handlers
      wordVariantsBox.querySelectorAll('.chip').forEach(chip => {
        chip.addEventListener('click', () => {
          const word = chip.getAttribute('data-word');
          inputText.value = word;
          inputText.focus();
          // Auto-translate after a short delay
          setTimeout(() => {
            if (!recording) autoTranslateFromInput();
          }, 300);
        });
      });
    }

    // Populate suggestions on page load and when language changes
    populateSuggestions();
    fromLang.addEventListener('change', populateSuggestions);

    // ---------------- DICTIONARY-BASED LANGUAGE DETECTION ----------------
    const languageIndicatorsEl = document.getElementById('languageIndicators');

    const detectionPhrases = {
      // indigenous
      hil: suggestionPhrases.hiligaynon,
      ilo: suggestionPhrases.ilocano,
      ceb: suggestionPhrases.cebuano,
      // basic Tagalog / English starters (extend as needed)
      tl: [
        "Kamusta",
        "Magandang umaga",
        "Magandang hapon",
        "Magandang gabi",
        "Maraming salamat",
        "Salamat",
        "Nasaan ang banyo?",
        "Anong pangalan mo?",
        "Anong oras na?",
        // Additional formal Tagalog phrases
        "Kumusta po",
        "Mabuti po",
        "Salamat po",
        "Maraming salamat po",
        "Pasensya na po",
        "Walang anuman po",
        "Maupo po kayo",
        "Tulungan nyo po ako",
        "Puwede po ba",
        "Naku po",
        "Iyong po",
        "Po ang pangalan ko",
        "Anong maipaglilingkod ko?",
        "May kailangan po ba kayo?",
        "Pakisabi po",
        "Sige po",
        "Opo",
        "opo",
        "Mamaya na lang po",
        "Ayos lang po",
        "Okay lang po"
      ],
      en: [
        "Hello",
        "How are you?",
        "Good morning",
        "Good afternoon",
        "Good evening",
        "Thank you",
        "Where is the bathroom?",
        "What is your name?",
        "What time is it?",
        // Additional formal English phrases
        "Good day",
        "How do you do?",
        "Pleasant evening to you",
        "Many thanks",
        "Thank you very much",
        "Much obliged",
        "I appreciate it",
        "Pardon me",
        "Excuse me",
        "I apologize",
        "You're welcome",
        "Not at all",
        "My pleasure",
        "Certainly",
        "Of course",
        "Please",
        "May I?",
        "Would you mind?",
        "I beg your pardon",
        "At your service",
        "How may I assist you?",
        "What can I do for you?"
      ]
    };

    function normalizeForDetect(s) {
      return String(s || '')
        .toLowerCase()
        .replace(/[.,!?;:()[\]{}"“”‘’]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
    }

    function scorePhraseMatch(input, phrase) {
      if (!input || !phrase) return 0;
      if (input === phrase) return 1;

      // containment
      if (input.includes(phrase) || phrase.includes(input)) {
        const minLen = Math.min(input.length, phrase.length);
        const maxLen = Math.max(input.length, phrase.length);
        return Math.min(0.9, 0.55 + (minLen / maxLen) * 0.35);
      }

      // token overlap (soft match)
      const inTok = input.split(' ').filter(Boolean);
      const phTok = phrase.split(' ').filter(Boolean);
      if (inTok.length === 0 || phTok.length === 0) return 0;
      const inSet = new Set(inTok);
      let common = 0;
      for (const t of phTok) if (inSet.has(t)) common++;
      return (common / phTok.length) * 0.6;
    }

    function detectSourceLanguage(text) {
      const input = normalizeForDetect(text);
      if (!input) return { lang: null, confidence: 0 };

      let bestLang = null;
      let bestScore = 0;

      for (const [lang, phrases] of Object.entries(detectionPhrases)) {
        let langScore = 0;
        for (const p of phrases) {
          const phrase = normalizeForDetect(p);
          langScore = Math.max(langScore, scorePhraseMatch(input, phrase));
          if (langScore >= 1) break;
        }
        if (langScore > bestScore) {
          bestScore = langScore;
          bestLang = lang;
        }
      }

      // minimum confidence threshold
      if (bestScore < 0.35) return { lang: null, confidence: bestScore };
      return { lang: bestLang, confidence: bestScore };
    }

    function applyDetectedLanguage(text) {
      // Skip auto-detection if user has manually selected a language (auto-detect is disabled)
      if (!autoDetectEnabled) {
        if (languageIndicatorsEl) {
            const labelMap = { hil: 'Hiligaynon', ilo: 'Ilocano', ceb: 'Cebuano', tl: 'Tagalog', en: 'English' };
            const currentLang = fromLang.value === 'auto' ? 'Not set' : (labelMap[fromLang.value] || fromLang.value);
            languageIndicatorsEl.textContent = `Manual: ${currentLang}`;
        }
        return null;
      }
      
      const { lang, confidence } = detectSourceLanguage(text);
      if (!lang) {
        if (languageIndicatorsEl) languageIndicatorsEl.textContent = '';
        return null;
      }

      // auto-select detected language in source dropdown
      if (fromLang.value !== lang) {
        fromLang.value = lang;
        enforceFromToExclusion();
        populateSuggestions();
      }

      if (languageIndicatorsEl) {
        const labelMap = { hil: 'Hiligaynon', ilo: 'Ilocano', ceb: 'Cebuano', tl: 'Tagalog', en: 'English' };
        languageIndicatorsEl.textContent = `Detected: ${labelMap[lang] || lang} (${Math.round(confidence * 100)}%)`;
      }

      return lang;
    }

    // ---------------- SECRET ADMIN REDIRECT ----------------
    function adminSecretGate() {
        const ADMIN_SECRET = 'Cookie admin'; // ← change password here

        const secretInput = inputText.value.trim().toLowerCase();

        if (
            fromLang.value === 'ceb' &&
            toLang.value === 'tl' &&
            secretInput === ADMIN_SECRET.toLowerCase()
        ) {
            window.location.href = 'AdminMainPage.php';
            return true; // stop everything
        }
        return false;
    }
    // ------------------------------------------------------


    // ---------------- AUTO-TRANSLATE FUNCTION ----------------
    function autoTranslateFromInput() {
        if (adminSecretGate()) return; // stop if admin redirect triggered      
        const text = inputText.value.trim();
        if (!text) {
            resultEl.textContent = '';
            if (languageIndicatorsEl) languageIndicatorsEl.textContent = '';
            return;
        }

        applyDetectedLanguage(text);

        // Trigger your existing translate button logic
        translateBtn.click();
    }

    // ---------------- AUTO-TRANSLATE ON TYPING ----------------
    let typingTimer;
    const TYPING_DELAY = 1500; // 1.5 seconds
    inputText.addEventListener('input', () => {
        // Search for word variants as user types
        populateWordVariants();
        
        // Restore static suggestions when input becomes empty
        if (!inputText.value.trim()) {
            populateSuggestions();
        }
        
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
            if (!recording) autoTranslateFromInput(); // only trigger if mic is NOT recording
        }, TYPING_DELAY);
    });



    // --- Prevent selecting same language for from/to ---
    function enforceFromToExclusion() {
      const from = fromLang.value;
      Array.from(toLang.options).forEach(opt => {
        if (opt.value === from) {
          opt.disabled = true;
          opt.hidden = true;
        } else {
          opt.disabled = false;
          opt.hidden = false;
        }
      });
      // Safety: if current toLang became invalid, switch it
      if (toLang.value === from) {
        const fallback = Array.from(toLang.options).find(o => !o.disabled);
        if (fallback) toLang.value = fallback.value;
      }
    }
    fromLang.addEventListener('change', enforceFromToExclusion);
    fromLang.addEventListener('change', () => {
        // When user manually selects a language, disable auto-detect
        if (fromLang.value !== 'auto') {
            autoDetectEnabled = false;
            updateAutoToggleUI();
        } else {
            autoDetectEnabled = true;
            updateAutoToggleUI();
        }
        populateSuggestions();
        if (inputText.value.trim()) {
            autoTranslateFromInput();
        }
    });
    toLang.addEventListener('change', () => {
        if (inputText.value.trim()) {
            autoTranslateFromInput();
        }
    });
    enforceFromToExclusion(); // run once on page load


    // --- Speech-to-Text (Web Speech API) ---
    let recognition;
    let recording = false;
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
      const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
      recognition = new SR();
      recognition.lang = 'en-US';
      recognition.interimResults = true;
      recognition.continuous = false;

      recognition.onstart = () => {
        recording = true;
        statusEl.textContent = 'Listening...';
        setMicRecordingUI(true);
      };
      recognition.onend = () => {
          recording = false;
          statusEl.textContent = 'Ready';
          setMicRecordingUI(false);

          if (inputText.value.trim()) {
              autoTranslateFromInput(); // translate only after mic stops
          }
      };
      recognition.onerror = (e) => { console.error(e); statusEl.textContent = 'STT error'; }

      recognition.onresult = (event) => {
          let interim = '';
          let final = '';
          for (let i = 0; i < event.results.length; i++) {
              const t = event.results[i][0].transcript;
              if (event.results[i].isFinal) final += t;
              else interim += t;
          }
          inputText.value = final || interim || inputText.value;
      };
    } else {
      micBtn.disabled = true;
      micBtn.title = 'Speech recognition not supported in this browser.';
    }

    /* START: local-dialect recognition (Ilocano / Cebuano / Hiligaynon)
       This adds a local recognition path for the three dialects so the app
       uses the browser STT and a simple syllable-breaker instead of remote STT.
    */
    const localDialects = ['ilo','ceb','hil','ilocano','cebuano','hiligaynon'];

    function breakAndConstructWords(text) {
      const vowels = ['a','e','i','o','u'];
      let result = "";
      for (let word of (text || '').split(/\s+/)) {
        let broken = "";
        for (let i = 0; i < word.length; i++) {
          broken += word[i];
          if (vowels.includes(word[i])) broken += " ";
        }
        result += broken.trim() + " ";
      }
      return result.trim();
    }

    let localRecognition = null;
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
      const SRLocal = window.SpeechRecognition || window.webkitSpeechRecognition;
      localRecognition = new SRLocal();
      localRecognition.continuous = false;
      localRecognition.interimResults = false;
      localRecognition.lang = 'en-US'; // broad capture; we'll post-process locally
      localRecognition.onresult = (event) => {
          try {
              const raw = (event.results[0][0].transcript || '').toLowerCase();
              const broken = breakAndConstructWords(raw);
              window._lastBroken = broken;
              inputText.value = raw;
              statusEl.textContent = 'Recognized (local)';

              // ✅ Auto-translate after local mic input
              autoTranslateFromInput();
          } catch (e) {
              console.warn('localRecognition onresult', e);
          }
      };
      localRecognition.onerror = (e) => { console.warn('localRecognition error', e); statusEl.textContent = 'Local STT error'; };
      localRecognition.onend = () => {
          if (window._localStream) {
              try { window._localStream.getTracks().forEach(t => t.stop()); } catch(e){}
              window._localStream = null;
          }
          setMicRecordingUI(false);
          recording = false;
          statusEl.textContent = 'Ready';
          delete window.stopLocalDialectRecording;

          if (inputText.value.trim()) {
              autoTranslateFromInput(); // translate only after local mic stops
          }
      };
    }

    micBtn.addEventListener('click', async ()=>{
      const dialect = (fromLang.value || '').toLowerCase();
      const isLocal = localDialects.includes(dialect);

      // if there is an active local stop function, call it to stop local recording
      if (typeof window.stopLocalDialectRecording === 'function') {
        window.stopLocalDialectRecording();
        return;
      }

      if (isLocal) {
        if (!localRecognition) {
          alert('Speech recognition not supported in this browser.');
          return;
        }
        try {
          const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
          window._localStream = stream;

          const recorder = new MediaRecorder(stream);
          const chunks = [];
          recorder.ondataavailable = e => chunks.push(e.data);
          recorder.onstop = () => { window.latestDialectAudio = new Blob(chunks, { type: 'audio/webm' }); };
          recorder.start();

          try { localRecognition.start(); } catch(e){ console.warn(e); }

          window.stopLocalDialectRecording = () => {
            try { localRecognition.stop(); } catch(e){ console.warn(e); }
            try { if (recorder && recorder.state !== 'inactive') recorder.stop(); } catch(e){ console.warn(e); }
            try { if (window._localStream) { window._localStream.getTracks().forEach(t => t.stop()); window._localStream = null; } } catch(e){}
            setMicRecordingUI(false);
            recording = false;
            statusEl.textContent = 'Ready';
            delete window.stopLocalDialectRecording;
          };

          setMicRecordingUI(true);
          recording = true;
          statusEl.textContent = '🎤 Listening (local recognition)...';
        } catch (err) {
          console.error('local STT error', err);
          alert('Microphone access is required for local recognition.');
        }
      } else {
        // non-local: existing recognition behaviour
        if (!recognition) return alert('Speech recognition not supported in this browser.');
        if (!recording) {
          const fl = fromLang.value === 'auto' ? 'en-US' : (fromLang.value === 'tl' ? 'tl-PH' : (fromLang.value==='ceb' ? 'ceb-PH' : 'en-US'));
          try { recognition.lang = fl; } catch(e){}
          recognition.start();
        } else {
          recognition.stop();
        }
      }
    });
    /* END: local-dialect recognition */

    clearBtn.addEventListener('click', ()=>{
      inputText.value='';
      resultEl.textContent='';
      if (languageIndicatorsEl) languageIndicatorsEl.textContent = '';
      // Hide the warning when clearing input
      const warningEl = document.getElementById('audioWarning');
      if (warningEl) warningEl.style.display = 'none';
      // Hide word variants when clearing input
      const wordVariantsContainer = document.getElementById('wordVariantsContainer');
      if (wordVariantsContainer) wordVariantsContainer.style.display = 'none';
      // Restore static suggestions when input is cleared
      populateSuggestions();
    });

    // --- Swap languages ---
    swapBtn.addEventListener('click', ()=>{
      const a = fromLang.value; const b = toLang.value;
      fromLang.value = b === 'auto' ? 'auto' : b; // keep auto detect
      toLang.value = a === 'auto' ? 'auto' : a;
      const tmp = inputText.value; inputText.value = resultEl.textContent; resultEl.textContent = tmp;
    });

    // ---------------- CHECK IF WORD EXISTS IN DICTIONARY ----------------
    // Helper function to check if a word exists as a key in a specific dictionary
    function wordExistsInDictionary(word, fromLangVal, toLangVal) {
        if (!window.dialectDictionaries) return false;
        
        // Build the dictionary key based on language pair
        let dictKey = '';
        if (fromLangVal === 'tl' && toLangVal === 'ceb') dictKey = 'tagalog_cebuano';
        else if (fromLangVal === 'ceb' && toLangVal === 'tl') dictKey = 'cebuano_tagalog';
        else if (fromLangVal === 'tl' && toLangVal === 'hil') dictKey = 'tagalog_hiligaynon';
        else if (fromLangVal === 'hil' && toLangVal === 'tl') dictKey = 'hiligaynon_tagalog';
        else if (fromLangVal === 'tl' && toLangVal === 'ilo') dictKey = 'tagalog_ilocano';
        else if (fromLangVal === 'ilo' && toLangVal === 'tl') dictKey = 'ilocano_tagalog';
        else if (fromLangVal === 'en' && toLangVal === 'tl') dictKey = 'english_tagalog';
        else if (fromLangVal === 'tl' && toLangVal === 'en') dictKey = 'tagalog_english';
        else if (fromLangVal === 'ceb' && toLangVal === 'ilo') dictKey = 'cebuano_ilocano';
        else if (fromLangVal === 'ilo' && toLangVal === 'ceb') dictKey = 'ilocano_cebuano';
        else if (fromLangVal === 'ceb' && toLangVal === 'hil') dictKey = 'cebuano_hiligaynon';
        else if (fromLangVal === 'hil' && toLangVal === 'ceb') dictKey = 'hiligaynon_cebuano';
        else if (fromLangVal === 'hil' && toLangVal === 'ilo') dictKey = 'hiligaynon_ilocano';
        else if (fromLangVal === 'ilo' && toLangVal === 'hil') dictKey = 'ilocano_hiligaynon';
        else if (fromLangVal === 'en' && toLangVal === 'ceb') dictKey = 'english_cebuano';
        else if (fromLangVal === 'ceb' && toLangVal === 'en') dictKey = 'cebuano_english';
        else if (fromLangVal === 'en' && toLangVal === 'hil') dictKey = 'english_hiligaynon';
        else if (fromLangVal === 'hil' && toLangVal === 'en') dictKey = 'hiligaynon_english';
        else if (fromLangVal === 'en' && toLangVal === 'ilo') dictKey = 'english_ilocano';
        else if (fromLangVal === 'ilo' && toLangVal === 'en') dictKey = 'ilocano_english';
        
        // Check if dictionary exists and if the word is a key in it
        const dict = window.dialectDictionaries[dictKey];
        if (dict && dict[word] !== undefined) {
            return true;
        }
        
        return false;
    }

    // Function to show/hide warning based on translation availability
    function updateTranslationWarning(word, fromLangVal, toLangVal, translatedText) {
        const warningEl = document.getElementById('audioWarning');
        if (!warningEl) return;
        
        // Check if word exists in the dictionary
        const wordFound = wordExistsInDictionary(word, fromLangVal, toLangVal);
        
        // Also check if the translation is a valid translation (not a "no translation" message)
        const hasValidTranslation = translatedText && 
            !translatedText.includes('No translation') && 
            !translatedText.includes('Walang translation') &&
            !translatedText.includes('Walay translation') &&
            !translatedText.includes('[Walang translation]');
        
        // Show warning only if word is not in dictionary AND no valid translation
        if (!wordFound && !hasValidTranslation) {
            warningEl.style.display = 'block';
        } else {
            warningEl.style.display = 'none';
        }
    }

    translateBtn.addEventListener('click', async () => {
    if (adminSecretGate()) return;
    const text = inputText.value.trim();;
    if (!text) {
    statusEl.textContent = 'No input to read.';
    // Hide warning when there's no input
    const warningEl = document.getElementById('audioWarning');
    if (warningEl) warningEl.style.display = 'none';
    return;
}

    // Auto-detect language from typed/spoken phrase before translating
    applyDetectedLanguage(text);

    statusEl.textContent = 'Translating...';
    let translated = '';
    let foundInDictionary = false; // Track if we found a valid dictionary translation
    const lowerText = text.toLowerCase().trim();
    
    // Debug logging
    console.log('Input text:', text);
    console.log('Lower text:', lowerText);
    console.log('Source lang:', fromLang.value);
    console.log('Target lang:', toLang.value);
    console.log('dialectDictionaries available:', typeof dialectDictionaries);
    
    // Get current language values after auto-detection
    const sourceLang = fromLang.value;
    const targetLang = toLang.value;
    
    console.log('Using sourceLang:', sourceLang, 'targetLang:', targetLang);
    
    // Skip dictionary lookup if auto-detect is still active (not detected)
    if (sourceLang === 'auto') {
        // Try to find translation by checking all dictionaries for the target language
        console.log('Auto-detect mode - searching all dictionaries');
        translated = findTranslationInAllDictionaries(lowerText, targetLang);
        console.log('Auto-detect result:', translated);
        if (translated) {
            foundInDictionary = true;
        }
    } else {
        // Use the selected language pair
        const dictKey = getDictKey(sourceLang, targetLang);
        console.log('Looking up dictionary:', dictKey);
        const dict = dialectDictionaries[dictKey];
        console.log('Dictionary:', dict ? 'Found' : 'Not found');
        
        if (dict) {
            console.log('Looking for:', lowerText);
            console.log('Available keys:', Object.keys(dict).slice(0, 5));
            if (dict[lowerText]) {
                translated = dict[lowerText];
                foundInDictionary = true;
                console.log('Found translation:', translated);
            }
        }
    }
    
    // If no translation found from dictionary, try fallback
    if (!translated || !foundInDictionary) {
        // Try dialect engine first
        if (window.translateDialect) {
            const dialectResult = translateDialect(lowerText, sourceLang === 'auto' ? 'tl' : sourceLang, targetLang);

            if (
                dialectResult &&
                !['[Walang translation]', '[Walang available dictionary]'].includes(dialectResult)
            ) {
                translated = dialectResult;
                foundInDictionary = true; // Dialect engine provided a translation
            }
        }

        // Final fallback - show original text if no translation found
        if (!translated || !foundInDictionary) {
            translated = `${text}`;
        }
    }

    // Hide warning initially during translation
    const warningEl = document.getElementById('audioWarning');
    if (warningEl) warningEl.style.display = 'none';

        // ---------------- UPDATE WARNING BASED ON TRANSLATION ----------------
        // Show warning if no valid translation was found in dictionary or fallback
        if (warningEl) {
            if (!foundInDictionary) {
                warningEl.style.display = 'block';
            } else {
                warningEl.style.display = 'none';
            }
        }

        // ---------------- DISPLAY RESULT ----------------
        resultEl.textContent = translated;
        statusEl.textContent = 'Done';

        // ---------------- HISTORY PANEL ---------------- 
        const historyEl = document.getElementById('history');
        const historyMobileEl = document.getElementById('historyMobile');
        const time = new Date().toLocaleTimeString();
        
        const itemHTML = `
            <div class="history-item">
                <span><strong>From:</strong> ${fromLang.options[fromLang.selectedIndex].text} → <strong>To:</strong> ${toLang.options[toLang.selectedIndex].text}</span>
                <span><strong>Input:</strong> ${text}</span>
                <span><strong>Output:</strong> ${translated}</span>
                <span class="history-meta">${time}</span>
            </div>
        `;
        
        // Add to desktop fixed panel
        if (historyEl) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = itemHTML;
            const newItem = tempDiv.firstElementChild;
            // Insert after the label
            const label = historyEl.querySelector('.history-panel-label');
            if (label) {
                historyEl.insertBefore(newItem, label.nextSibling);
            } else {
                historyEl.appendChild(newItem);
            }
        }
        
        // Add to mobile modal
        if (historyMobileEl) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = itemHTML;
            historyMobileEl.appendChild(tempDiv.firstElementChild);
        }

        // ---------------- DATABASE INSERT ----------------
        const fd = new FormData();
        fd.append('word', text);
        fd.append('source_lang', fromLang.value);
        fd.append('target_lang', toLang.value);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(res => res.text())
            .then(txt => console.log('Word inserted:', txt))
            .catch(err => console.warn('Failed to insert word:', err));
});

// Helper function to get dictionary key from language codes
function getDictKey(sourceLang, targetLang) {
    // Map short codes to full names
    const langMap = {
        'tl': 'tagalog',
        'ceb': 'cebuano', 
        'hil': 'hiligaynon',
        'ilo': 'ilocano',
        'en': 'english'
    };
    
    const source = langMap[sourceLang] || sourceLang;
    const target = langMap[targetLang] || targetLang;
    
    return source + '_' + target;
}

// Helper function to find translation when auto-detect is used
function findTranslationInAllDictionaries(text, targetLang) {
    // List of all possible source languages to try
    const sourceLanguages = ['tl', 'ceb', 'hil', 'ilo', 'en'];
    
    console.log('Searching for:', text, 'target:', targetLang);
    
    for (const sourceLang of sourceLanguages) {
        const dictKey = getDictKey(sourceLang, targetLang);
        const dict = dialectDictionaries[dictKey];
        
        if (dict) {
            console.log('Checking dictionary:', dictKey, 'Keys:', Object.keys(dict).slice(0, 3));
            if (dict[text]) {
                console.log('Found match in:', dictKey);
                return dict[text];
            }
        }
    }
    
    console.log('No translation found');
    return null;
}

  
playBtn.addEventListener('click', async () => {
  const output = resultEl.textContent.trim();
  if (!output) { 
    statusEl.textContent = 'Nothing to read.'; 
    return; 
  }

  const lang = toLang.value;
  speechSynthesis.cancel();

  // English → browser TTS
  if (lang === 'en') {
    const u = new SpeechSynthesisUtterance(output);
    u.lang = 'en-US';
    speechSynthesis.speak(u);
    return;
  }

  // Non-English → use TTS bot (auto fallback handled inside planSentence)
  try {
    await window.TTS.speak(output);
  } catch (e) {
    console.error(e);
    statusEl.textContent = 'TTS failed';
  }
});

    // copy button
    copyBtn.addEventListener('click', async ()=>{
      const text = resultEl.textContent.trim();
      if (!text) return;
      try { await navigator.clipboard.writeText(text); statusEl.textContent='Copied'; setTimeout(()=>statusEl.textContent='Ready',1000);} catch(e){statusEl.textContent='Copy failed'}
    });

    // keyboard shortcut: Ctrl+Enter => translate
    inputText.addEventListener('keydown', (e)=>{ if (e.ctrlKey && e.key==='Enter') translateBtn.click(); });



  </script>
  <script>

function showLogin(){
var lb=document.getElementById("loginBox");
var rb=document.getElementById("registerBox");
if(lb){ lb.hidden=false; lb.style.display="block"; }
if(rb){ rb.hidden=true; rb.style.display="none"; }
var lt=document.getElementById("loginTab");
var rt=document.getElementById("registerTab");
if(lt){ lt.classList.add("active"); lt.setAttribute("aria-selected","true"); }
if(rt){ rt.classList.remove("active"); rt.setAttribute("aria-selected","false"); }
}

function showRegister(){
var rb=document.getElementById("registerBox");
var lb=document.getElementById("loginBox");
if(rb){ rb.hidden=false; rb.style.display="block"; }
if(lb){ lb.hidden=true; lb.style.display="none"; }
var lt=document.getElementById("loginTab");
var rt=document.getElementById("registerTab");
if(rt){ rt.classList.add("active"); rt.setAttribute("aria-selected","true"); }
if(lt){ lt.classList.remove("active"); lt.setAttribute("aria-selected","false"); }
}

document.addEventListener("DOMContentLoaded",function(){ showLogin(); });
</script>
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('service-worker.js')
          .then((registration) => {
            console.log('ServiceWorker registration successful with scope: ', registration.scope);
          }, (err) => {
            console.log('ServiceWorker registration failed: ', err);
          });
      });
    }
  </script>
  <script>
    let deferredPrompt;
    const installBtn = document.getElementById('installApp');

    window.addEventListener('beforeinstallprompt', (e) => {
      // Prevent the mini-infobar from appearing on mobile
      e.preventDefault();
      // Stash the event so it can be triggered later.
      deferredPrompt = e;
      // Update UI notify the user they can install the PWA
      installBtn.style.display = 'inline-flex';

      installBtn.addEventListener('click', async () => {
        // Hide the app provided install promotion
        installBtn.style.display = 'none';
        // Show the install prompt
        deferredPrompt.prompt();
        // Wait for the user to respond to the prompt
        const { outcome } = await deferredPrompt.userChoice;
        // Optionally, send analytics event with outcome of user choice
        console.log(`User response to the install prompt: ${outcome}`);
        // We've used the prompt, and can't use it again, throw it away
        deferredPrompt = null;
      });
    });

    window.addEventListener('appinstalled', (event) => {
      console.log('👍', 'appinstalled', event);
      // Clear the deferredPrompt so it can be garbage collected
      deferredPrompt = null;
      // Hide the install button
      installBtn.style.display = 'none';
    });
  </script>
</body>
</html>
