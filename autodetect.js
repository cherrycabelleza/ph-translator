/* =========================================================
   AUTO LANGUAGE DETECTION SYSTEM
   - Suggestions & indicators strictly based on textbox content
   - Multiple language matches displayed individually (deduplicated)
   - Prefix-based suggestion on keys for Auto mode
   - Substring-based filtering for selected language
   ========================================================= */

document.addEventListener("DOMContentLoaded", function() {
  const inputField = document.getElementById("inputText");
  const fromLangDropdown = document.getElementById("fromLang");

  if (!inputField || !fromLangDropdown || typeof dialectDictionaries === "undefined") return;

  inputField.addEventListener("input", function() {
    const text = this.value.trim();

    // Clear suggestions and indicators if textbox is empty
    if (!text) {
      document.getElementById("suggestionBox").innerHTML = "";
      document.getElementById("languageIndicators").innerHTML = "";
      return;
    }

    // Analyze language indicators
    const analysis = analyzeMixedLanguage(text);
    renderLanguageIndicators(analysis);

    // Suggest based on selected language
    const selectedLang = fromLangDropdown.value.toLowerCase();
    if (selectedLang === "tagalog" || selectedLang === "tl") renderTagalogSuggestions(text);
    else if (selectedLang === "english" || selectedLang === "en") renderEnglishSuggestions(text);
    else if (selectedLang === "cebuano" || selectedLang === "ceb") renderCebuanoSuggestions(text);
    else if (selectedLang === "hiligaynon" || selectedLang === "hil") renderHiligaynonSuggestions(text);
    else if (selectedLang === "ilocano" || selectedLang === "ilo") renderIlocanoSuggestions(text);
    else renderSuggestions(text); // Auto mode
  });
});

/* ================================
   WORD-LEVEL LANGUAGE ANALYSIS
   ================================ */
function analyzeMixedLanguage(text) {
  const words = text.toLowerCase().trim().split(/\s+/);
  const results = [];

  const normalize = (code) => {
    if (code === "tagalog") return "tl";
    if (code === "english") return "en";
    if (code === "cebuano") return "ceb";
    if (code === "hiligaynon") return "hil";
    if (code === "ilocano") return "ilo";
    return code;
  };

  for (const word of words) {
    const foundIn = new Set();
    for (const dictKey in dialectDictionaries) {
      const parts = dictKey.split("_");
      if (parts.length !== 2) continue;
      const from = normalize(parts[0]);
      const dict = dialectDictionaries[dictKey];
      for (const phrase in dict) {
        const keyWords = phrase.toLowerCase().split(" ");
        if (keyWords.includes(word)) foundIn.add(from);
      }
    }
    results.push({ word, languages: [...foundIn] });
  }

  return results;
}

/* ================================
   LANGUAGE INDICATOR RENDER
   ================================ */
function renderLanguageIndicators(analysis) {
  const container = document.getElementById("languageIndicators");
  if (!container) return;
  container.innerHTML = "";

  const added = new Set();
  analysis.forEach(item => {
    item.languages.forEach(lang => {
      const key = `${item.word}__${lang}`;
      if (!added.has(key)) {
        added.add(key);
        const span = document.createElement("span");
        span.style.marginRight = "10px";
        span.style.fontSize = "12px";
        span.style.color = "blue";
        span.textContent = `${item.word} (${lang})`;
        container.appendChild(span);
      }
    });
  });
}

/* ================================
   AUTO MODE SUGGESTIONS (PREFIX)
   ================================ */
function renderSuggestions(text) {
  const box = document.getElementById("suggestionBox");
  if (!box) return;
  box.innerHTML = "";

  const input = text.toLowerCase().trim();
  if (!input) return;

  const inputWords = input.split(/\s+/);
  const suggestions = [];
  const added = new Set();
  const selectedLang = document.getElementById("fromLang").value.toLowerCase();
  const langColors = { tl:"blue", en:"red", ceb:"green", hil:"purple", ilo:"orange" };

  for (const dictKey in dialectDictionaries) {
    const parts = dictKey.split("_");
    if (parts.length !== 2) continue;

    const fromLangCode = parts[0].toLowerCase();
    if (selectedLang !== "auto" && fromLangCode !== selectedLang) continue;

    const dict = dialectDictionaries[dictKey];
    for (const phrase in dict) {
      const phraseLower = phrase.toLowerCase().trim();
      const phraseWords = phraseLower.split(/\s+/);

      // Prefix match only for Auto mode
      if (inputWords.length <= phraseWords.length) {
        let match = true;
        for (let i=0; i<inputWords.length; i++){
          if (!phraseWords[i].startsWith(inputWords[i])){
            match = false; break;
          }
        }
        const key = `${phraseLower}__${fromLangCode}`;
        if (match && !added.has(key)){
          added.add(key);
          suggestions.push({ sentence: phrase, language: fromLangCode });
        }
      }
    }
  }

  renderSuggestionBox(suggestions, langColors);
}

/* ================================
   FULL-LIBRARY SUGGESTIONS WITH SUBSTRING FILTER
   ================================ */
function renderFullLanguageBySubstring(langName, langCode, input) {
  const box = document.getElementById("suggestionBox");
  if (!box) return;
  box.innerHTML = "";
  if (!input) return;

  const added = new Set();
  const inputLower = input.toLowerCase();
  const langColors = { tl:"blue", en:"red", ceb:"green", hil:"purple", ilo:"orange" };

  for (const dictKey in dialectDictionaries){
    const parts = dictKey.split("_");
    if (parts.length !== 2) continue;

    const fromLangCode = parts[0].toLowerCase();
    if (fromLangCode !== langName && fromLangCode !== langCode) continue;

    const dict = dialectDictionaries[dictKey];
    for (const phrase in dict){
      const phraseLower = phrase.toLowerCase();
      if (!phraseLower.includes(inputLower)) continue; // substring match

      const key = `${phraseLower}__${fromLangCode}`;
      if (!added.has(key)){
        added.add(key);

        const div = document.createElement("div");
        div.textContent = `${phrase} (${fromLangCode})`;
        div.style.cursor = "pointer";
        div.style.padding = "4px";
        div.style.color = langColors[fromLangCode] || "black";

        div.addEventListener("click", function(){
          document.getElementById("inputText").value = phrase;
          box.innerHTML = "";
        });

        box.appendChild(div);
      }
    }
  }
}

/* ================================
   LANGUAGE WRAPPERS
   ================================ */
function renderTagalogSuggestions(input){ renderFullLanguageBySubstring("tagalog","tl", input); }
function renderEnglishSuggestions(input){ renderFullLanguageBySubstring("english","en", input); }
function renderCebuanoSuggestions(input){ renderFullLanguageBySubstring("cebuano","ceb", input); }
function renderHiligaynonSuggestions(input){ renderFullLanguageBySubstring("hiligaynon","hil", input); }
function renderIlocanoSuggestions(input){ renderFullLanguageBySubstring("ilocano","ilo", input); }

/* ================================
   COMMON RENDER FUNCTION
   ================================ */
function renderSuggestionBox(suggestions, langColors){
  const box = document.getElementById("suggestionBox");
  box.innerHTML = "";
  suggestions.forEach(s => {
    const div = document.createElement("div");
    div.textContent = `${s.sentence} (${s.language})`;
    div.style.cursor = "pointer";
    div.style.padding = "4px";
    div.style.color = langColors[s.language] || "black";

    div.addEventListener("click", function() {
      document.getElementById("inputText").value = s.sentence;
      box.innerHTML = "";
    });

    box.appendChild(div);
  });
}
