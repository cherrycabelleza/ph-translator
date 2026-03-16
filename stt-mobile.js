/* =========================================================
   MOBILE STT DIALECT COMPATIBILITY LAYER
   Cebuano / Ilocano / Hiligaynon recognition
   Works with existing web STT, browser-only
   ========================================================= */

/* ---------- MOBILE DETECTION ---------- */
const IS_MOBILE = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
const DIALECTS = ['ceb','hil','ilo'];

/* ---------- MOBILE DIALECT CORRECTION ---------- */
function levenshtein(a, b) {
  if (!a || !b) return Infinity;
  const m = a.length, n = b.length;
  const dp = Array.from({ length: m + 1 }, () => Array(n + 1).fill(0));
  for (let i = 0; i <= m; i++) dp[i][0] = i;
  for (let j = 0; j <= n; j++) dp[0][j] = j;
  for (let i = 1; i <= m; i++) {
    for (let j = 1; j <= n; j++) {
      dp[i][j] = Math.min(
        dp[i-1][j]+1,
        dp[i][j-1]+1,
        dp[i-1][j-1]+(a[i-1]===b[j-1]?0:1)
      );
    }
  }
  return dp[m][n];
}

// Recover a single word using dialect dictionary
function recoverDialectWord(word, dictionary) {
  if (!dictionary) return word;
  let best = word, minDist = Infinity;
  for (const key in dictionary) {
    const d = levenshtein(word, key);
    if (d < minDist && d <= 2) { // small error tolerance
      minDist = d;
      best = key;
    }
  }
  return best;
}

// Recover entire sentence
function recoverDialectSentence(sentence, dictionary) {
  if (!sentence || !dictionary) return sentence;
  return sentence.toLowerCase().split(/\s+/).map(w => recoverDialectWord(w, dictionary)).join(' ');
}

/* ---------- MOBILE-AWARE MEDIARECORDER ---------- */
function createMediaRecorderSafe(stream) {
  if (IS_MOBILE) return null; // mobile won't record locally, we use live recognition
  try { return new MediaRecorder(stream); } catch(e) { return null; }
}

/* ---------- MOBILE DIALECT PROCESSOR ---------- */
function correctDialect(rawText, fromLang) {
  if (!IS_MOBILE || !DIALECTS.includes(fromLang)) return rawText;
  if (!window.dialectDictionaries) return rawText;

  const dictKeys = Object.keys(window.dialectDictionaries).filter(k => k.startsWith(fromLang+'_'));
  let corrected = rawText;
  dictKeys.forEach(k => {
    corrected = recoverDialectSentence(corrected, window.dialectDictionaries[k]);
  });
  return corrected;
}

/* ---------- EXPORT TO GLOBAL ---------- */
window.STTMobile = {
  IS_MOBILE,
  createMediaRecorderSafe,
  correctDialect
};
