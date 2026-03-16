/* =========================================================
   FILIPINO HYBRID SMART ADAPTIVE TTS BOT
   Word-first + Connector-aware, Rule-aware syllable fallback
   Speed-based voicepack selection (words vs syllables)
   ========================================================= */
(function () {

  // ---------------- NETLIFY AUDIO BASE URLS ----------------
  const SYLLABLE_AUDIO_BASE_URL = 'https://ph-translator.netlify.app/mj-syllable-voicepack/';
  const WORD_AUDIO_BASE_URL = 'https://ph-translator.netlify.app/ccg-words-voicepack/';

  // ---------------- VOICEPACK RESOLVER ----------------
  let CURRENT_MODE = null; // track current TTS mode
  let WORD_CACHE = {};     // global cache for word audio HEAD check
  let AVAILABLE = null;    // cache for syllables

  function getVoicepack() {
    const mode = document.getElementById('ttsMode')?.value || 'words';
    if (mode !== CURRENT_MODE) {
      WORD_CACHE = {};
      AVAILABLE = null;
      CURRENT_MODE = mode;
    }
    return mode === 'words'
      ? WORD_AUDIO_BASE_URL
      : SYLLABLE_AUDIO_BASE_URL;
  }

  // ---------------- PHONOLOGY ----------------
  const VOWELS = ['a','e','i','o','u'];
  const CONSONANTS = ['b','k','d','g','h','l','m','n','p','r','s','t','w','y'];

  // Base NG syllables
  const BASE_NG_SYLLABLES = [
    'ang','eng','ing','ong','ung',
    'nga','nge','ngi','ngo','ngu',
    'ngan','ngen','ngin','ngon','ngun'
  ];

  // Generate CV+NG syllables
  const CV_NG_SYLLABLES = [];
  for (const c of CONSONANTS) {
    for (const v of VOWELS) {
      CV_NG_SYLLABLES.push(c + v + 'ng');
    }
  }

  const NG_SYLLABLES = [...BASE_NG_SYLLABLES, ...CV_NG_SYLLABLES]
    .sort((a,b)=>b.length - a.length);

  function isVowel(c){ return VOWELS.includes(c); }
  function isConsonant(c){ return CONSONANTS.includes(c) || c === 'ng'; }

  // ---------------- WORD AUDIO CHECK ----------------
  async function hasWordAudio(word){
    const key = getVoicepack() + word;
    if (WORD_CACHE[key] !== undefined) return WORD_CACHE[key];

    try {
      const ok = await fetch(`${getVoicepack()}${word}.mp3`, { method:'HEAD' })
        .then(r=>r.ok);
      WORD_CACHE[key] = ok;
      return ok;
    } catch {
      WORD_CACHE[key] = false;
      return false;
    }
  }

  // ---------------- LOAD AVAILABLE SYLLABLES ----------------
  async function loadAvailable(){
    if (AVAILABLE) return AVAILABLE;

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
        const exists = await fetch(`${getVoicepack()}${syl}.mp3`, { method:'HEAD' })
          .then(r=>r.ok);
        if (exists) available.push(syl);
      } catch {}
    }));

    AVAILABLE = available.sort((a,b)=>b.length - a.length);
    return AVAILABLE;
  }

  // ---------------- AUDIO PLAYER ----------------
  function play(unit){
    return new Promise((resolve,reject)=>{
      const src = getVoicepack() + unit + '.mp3' + '?v=' + Date.now();
      const audio = new Audio(src);
      audio.preload = 'auto';
      audio.oncanplaythrough = () => audio.play().catch(()=>reject(unit));
      audio.onended = resolve;
      audio.onerror = () => reject(unit);
    });
  }

  // ---------------- RULE-AWARE SEGMENTATION ----------------
  function segmentWord(word, available){
    const cache = {};

    function helper(start){
      if (start >= word.length) return [[]];
      if (cache[start]) return cache[start];

      const results = [];

      // Prioritize NG syllables that are available
      for (const syl of NG_SYLLABLES){
        if (word.startsWith(syl,start)){
          // Only use NG syllable if it's available or we have no available list
          if (!available || available.includes(syl)) {
            for (const r of helper(start + syl.length)){
              results.push([syl, ...r]);
            }
          }
        }
      }

      for (let len = 2; len <= 3; len++){
        if (start + len <= word.length){
          const part = word.slice(start, start+len);
          // Only use CV/CVC if available or we have no available list
          if (!available || available.includes(part)) {
            if (len === 2 && isConsonant(part[0]) && isVowel(part[1]))
              results.push(...helper(start+len).map(r=>[part,...r]));
            if (len === 3 && isConsonant(part[0]) && isVowel(part[1]) && isConsonant(part[2]))
              results.push(...helper(start+len).map(r=>[part,...r]));
          }
        }
      }

      // Single vowels
      if (isVowel(word[start])){
        if (!available || available.includes(word[start])) {
          results.push(...helper(start+1).map(r=>[word[start],...r]));
        }
      }

      if (start + 2 <= word.length){
        const vc = word.slice(start,start+2);
        if (isVowel(vc[0]) && isConsonant(vc[1]) &&
           (start === 0 || start + 2 === word.length)){
          if (!available || available.includes(vc)) {
            results.push(...helper(start+2).map(r=>[vc,...r]));
          }
        }
      }

      cache[start] = results;
      return results;
    }

    let segs = helper(0).filter(seg =>
      !seg.some(s => s.length === 1 && !isVowel(s))
    );

    if (segs.length === 0){
      segs = [word.match(/(ng|[bcdfghjklmnpqrstvwxyz]?[aeiou][bcdfghjklmnpqrstvwxyz]?)/gi) || [word]];
    }

    segs.sort((a,b)=>{
      const score = seg=>{
        let s=0;
        seg.forEach((sy,i)=>{
          if(NG_SYLLABLES.includes(sy)) s-=10;
          else if(sy.length===2 && isConsonant(sy[0]) && isVowel(sy[1])) s-=5;
          else if(sy.length===3 && isConsonant(sy[0]) && isVowel(sy[1]) && isConsonant(sy[2])) s-=4;
          else if(sy.length===2 && isVowel(sy[0]) && isConsonant(sy[1]) && i>0 && i<seg.length-1) s+=10;
          else if(sy.length===1 && !isVowel(sy)) s+=20;
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

  // ---------------- SENTENCE PLANNER ----------------
  async function planSentence(text){
    const available = await loadAvailable();

    // ✅ HYHEN HANDLING IMPLEMENTED HERE
    text = (text || '')
      .toLowerCase()
      .replace(/-/g, ' ')        // treat hyphen as word boundary
      .replace(/[^a-z\s]/g, '')  // remove other non-letters
      .replace(/\s+/g, ' ')      // normalize spacing
      .trim();

    const words = text.split(/\s+/);
    const units = [];

    for (const word of words){
      if (await hasWordAudio(word)){
        units.push(word);
      } else {
        units.push(...segmentWord(word, available));
      }
    }

    return { input:text, units };
  }

  // ---------------- BOT API ----------------
  const bot = {
    async plan(text){ return planSentence(text); },
    async speak(text){
      if (!text) return;
      const plan = await planSentence(text);
      const available = await loadAvailable();
      
      for (const u of plan.units){
        try { 
          await play(u); 
        } catch {
          // If unit fails (syllable not available), try to break it down
          const parts = breakDownSyllable(u, available);
          for (const p of parts) {
            try {
              await play(p);
            } catch {
              // If single part fails, try individual characters
              if (p.length > 1) {
                for (const char of p) {
                  try {
                    await play(char);
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
  };

  // ---------------- EXPORT ----------------
  window.TTS = window.TTS || {};
  window.TTS.bot = bot;
  window.TTS.speak = async text => window.TTS.bot.speak(text);

})();
