/* =========================================================
   FILIPINO HYBRID SMART ADAPTIVE TTS BOT
   Word-first + Connector-aware, Rule-aware syllable fallback
   Only plays toLang audio using SENTENCE_CODE
   ========================================================= */
(function () {

  // ---------------- NETLIFY AUDIO BASE URLS ----------------
  const SYLLABLE_AUDIO_BASE_URL = 'https://ph-translator.netlify.app/mj-syllable-voicepack/';
  const WORD_AUDIO_BASE_URL = 'https://ph-translator.netlify.app/ccg-words-voicepack/';

  // ---------------- PHONOLOGY ----------------
  const VOWELS = ['a','e','i','o','u'];
  const CONSONANTS = ['b','k','d','g','h','l','m','n','p','r','s','t','w','y'];

  const BASE_NG_SYLLABLES = [
    'ang','eng','ing','ong','ung',
    'nga','nge','ngi','ngo','ngu',
    'ngan','ngen','ngin','ngon','ngun'
  ];

  const CV_NG_SYLLABLES = [];
  for (const c of CONSONANTS)
    for (const v of VOWELS)
      CV_NG_SYLLABLES.push(c + v + 'ng');

  const NG_SYLLABLES = [...BASE_NG_SYLLABLES, ...CV_NG_SYLLABLES]
    .sort((a,b)=>b.length - a.length);

  function isVowel(c){ return VOWELS.includes(c); }
  function isConsonant(c){ return CONSONANTS.includes(c) || c === 'ng'; }

  // ---------------- LANG PREFIX ----------------
  const LANG_TO_PREFIX = {
    tl: 'tl',
    ceb: 'cb',
    ilo: 'il',
    en: 'en',
    hil: 'hi'
  };

  function getToLangPrefix(){
    const to = document.getElementById('toLang')?.value;
    return to && LANG_TO_PREFIX[to] ? LANG_TO_PREFIX[to] : null;
  }

  // ---------------- AUDIO FETCH ----------------
  let AVAILABLE_SYLLABLES = null;

  async function loadAvailableSyllables(){
    if (AVAILABLE_SYLLABLES) return AVAILABLE_SYLLABLES;
    
    const units = new Set();
    VOWELS.forEach(v => units.add(v));
    CONSONANTS.forEach(c => VOWELS.forEach(v => units.add(c+v)));
    CONSONANTS.forEach(c => VOWELS.forEach(v =>
      CONSONANTS.forEach(c2 => units.add(c+v+c2))
    ));
    NG_SYLLABLES.forEach(s => units.add(s));

    const list = Array.from(units);
    const available = [];

    await Promise.all(list.map(async syl => {
      try {
        const exists = await fetch(`${SYLLABLE_AUDIO_BASE_URL}${syl}.mp3`, { method:'HEAD' })
          .then(r=>r.ok);
        if (exists) available.push(syl);
      } catch {}
    }));

    AVAILABLE_SYLLABLES = available.sort((a,b)=>b.length - a.length);
    return AVAILABLE_SYLLABLES;
  }

  async function checkWordAudio(word, code) {
    try {
      const r = await fetch(`${WORD_AUDIO_BASE_URL}${word}-${code}.mp3`, { method:'HEAD' });
      return r.ok;
    } catch { return false; }
  }

  async function playWord(word, code){
    return new Promise((resolve,reject)=>{
      const audio = new Audio(`${WORD_AUDIO_BASE_URL}${word}-${code}.mp3?v=${Date.now()}`);
      audio.preload = 'auto';
      audio.onended = resolve;
      audio.onerror = () => reject(word);
      audio.play().catch(()=>reject(word));
    });
  }

  async function playSyllable(syl){
    return new Promise((resolve,reject)=>{
      const audio = new Audio(`${SYLLABLE_AUDIO_BASE_URL}${syl}.mp3?v=${Date.now()}`);
      audio.preload = 'auto';
      audio.onended = resolve;
      audio.onerror = () => reject(syl);
      audio.play().catch(()=>reject(syl));
    });
  }

  // ---------------- SEGMENTATION ----------------
  function segmentWord(word){
    const cache = {};

    function helper(start){
      if (start >= word.length) return [[]];
      if (cache[start]) return cache[start];

      const results = [];

      for (const syl of NG_SYLLABLES){
        if (word.startsWith(syl, start)){
          for (const r of helper(start + syl.length))
            results.push([syl, ...r]);
        }
      }

      for (let len = 2; len <= 3; len++){
        if (start + len <= word.length){
          const p = word.slice(start, start + len);
          if (len === 2 && isConsonant(p[0]) && isVowel(p[1]))
            results.push(...helper(start + len).map(r => [p, ...r]));
          if (len === 3 && isConsonant(p[0]) && isVowel(p[1]) && isConsonant(p[2]))
            results.push(...helper(start + len).map(r => [p, ...r]));
        }
      }

      if (isVowel(word[start])){
        results.push(...helper(start + 1).map(r => [word[start], ...r]));
      }

      if (start + 2 <= word.length){
        const vc = word.slice(start, start + 2);
        if (isVowel(vc[0]) && isConsonant(vc[1]) &&
           (start === 0 || start + 2 === word.length)){
          results.push(...helper(start + 2).map(r => [vc, ...r]));
        }
      }

      cache[start] = results;
      return results;
    }

    let segs = helper(0).filter(seg =>
      !seg.some(s => s.length === 1 && !isVowel(s))
    );

    if (!segs.length) {
      segs = [word.match(/(ng|[bcdfghjklmnpqrstvwxyz]?[aeiou][bcdfghjklmnpqrstvwxyz]?)/gi) || [word]];
    }

    segs.sort((a,b)=>{
      const score = seg=>{
        let s = 0;
        seg.forEach((sy,i)=>{
          if (NG_SYLLABLES.includes(sy)) s -= 10;
          else if (sy.length===2 && isConsonant(sy[0]) && isVowel(sy[1])) s -= 5;
          else if (sy.length===3 && isConsonant(sy[0]) && isVowel(sy[1]) && isConsonant(sy[2])) s -= 4;
          else if (sy.length===2 && isVowel(sy[0]) && isConsonant(sy[1]) && i>0 && i<seg.length-1) s += 10;
          else if (sy.length===1 && !isVowel(sy)) s += 20;
        });
        return s;
      };
      return score(a)-score(b);
    });

    return segs[0] || [word];
  }

  // ---------------- BREAK DOWN UNAVAILABLE SYLLABLE ----------------
  function breakDownSyllable(syl, available) {
    // If syllable is available, return it as-is
    if (!available || available.includes(syl)) return [syl];
    
    // If it's a single vowel, return it (we'll try to play it anyway)
    if (syl.length === 1 && isVowel(syl)) return [syl];
    
    // If it's 2 characters (CV or VC)
    if (syl.length === 2) {
      const parts = [];
      // Try CV split
      if (isConsonant(syl[0]) && isVowel(syl[1])) {
        parts.push(syl[0], syl[1]);
      }
      // Try VC split  
      else if (isVowel(syl[0]) && isConsonant(syl[1])) {
        parts.push(syl[0], syl[1]);
      }
      // If no valid split, just return the original
      if (parts.length === 2) return parts;
    }
    
    // If it's 3 characters (CVC)
    if (syl.length === 3) {
      // Try CV + C split
      if (isConsonant(syl[0]) && isVowel(syl[1]) && isConsonant(syl[2])) {
        return [syl.slice(0,2), syl[2]];
      }
    }
    
    // If nothing works, return original (will fail gracefully)
    return [syl];
  }

  // ---------------- NORMALIZE ----------------
  function normalize(text){
    return (text||'').toLowerCase()
      .replace(/-/g,' ')
      .replace(/[^a-z\s]/g,'')
      .replace(/\s+/g,' ')
      .trim()
      .split(/\s+/);
  }

  // ---------------- BOT ----------------
  const bot = {
    async speak(text){
      const words = normalize(text);
      const prefix = getToLangPrefix();
      let sentenceCode = null;

      // ===== TRY WORDS FIRST =====
      if (prefix) {
        for (let i=0; i<50; i++) {
          const code = `${prefix}${i}`;
          let ok = true;
          for (const w of words){
            if (!(await checkWordAudio(w, code))){
              ok = false;
              break;
            }
          }
          if (ok){
            sentenceCode = code;
            break;
          }
        }
      }

      if (sentenceCode){
        // PLAY WORDS
        for (const w of words){
          await playWord(w, sentenceCode);
        }
      } else {
        // FALLBACK TO SYLLABLES - with availability check
        const available = await loadAvailableSyllables();
        
        for (const w of words){
          const segs = segmentWord(w);
          for (const s of segs){
            // Check if syllable is available, if not break it down
            let parts = breakDownSyllable(s, available);
            
            for (const p of parts) {
              // Try to play each part
              try {
                await playSyllable(p);
              } catch {
                // If individual part fails, try single characters
                if (p.length > 1) {
                  for (const char of p) {
                    try {
                      await playSyllable(char);
                    } catch {
                      // Skip unavailable characters
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  };

  // ---------------- EXPORT ----------------
  window.TTS = window.TTS || {};
  window.TTS.bot = bot;
  window.TTS.speak = async t => window.TTS.bot.speak(t);

})();
