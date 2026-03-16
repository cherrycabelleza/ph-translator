/* =========================================================
   FILIPINO HYBRID SMART ADAPTIVE TTS BOT
   Word-first + Coded-word aware
   Segmentation used ONLY in syllable mode
   ========================================================= */
(function () {

  // ---------------- NETLIFY AUDIO BASE URLS ----------------
  const SYLLABLE_AUDIO_BASE_URL = 'https://ph-translator.netlify.app/mj-syllable-voicepack/';
  const WORD_AUDIO_BASE_URL = 'https://ph-translator.netlify.app/ccg-words-voicepack/';

  // ---------------- VOICEPACK RESOLVER ----------------
  let CURRENT_MODE = null;
  let WORD_CACHE = {};
  let AVAILABLE = null;
  let SENTENCE_CODE = null;

  function getMode() {
    return document.getElementById('ttsMode')?.value || 'words';
  }

  function getVoicepack() {
    const mode = getMode();
    if (mode !== CURRENT_MODE) {
      WORD_CACHE = {};
      AVAILABLE = null;
      SENTENCE_CODE = null;
      CURRENT_MODE = mode;
    }
    return mode === 'words'
      ? WORD_AUDIO_BASE_URL
      : SYLLABLE_AUDIO_BASE_URL;
  }

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

  // ---------------- CODE DETECTION ----------------
  async function wordHasCode(word, code){
    try {
      return await fetch(`${getVoicepack()}${word}-${code}.mp3`, { method:'HEAD' })
        .then(r=>r.ok);
    } catch {
      return false;
    }
  }

  // ✅ FIX: language → filename prefix mapping
  const LANG_TO_PREFIX = {
    tl: 'tl',
    ceb: 'cb',
    ilo: 'il',
    en: 'en',
    hil: 'hi'
  };

  function getSelectedPrefixes(){
    const from = document.getElementById('fromLang')?.value;
    const to   = document.getElementById('toLang')?.value;

    const ordered = [];

    if (from && LANG_TO_PREFIX[from])
      ordered.push(LANG_TO_PREFIX[from]);

    if (to && to !== from && LANG_TO_PREFIX[to])
      ordered.push(LANG_TO_PREFIX[to]);

    return ordered;
  }

  async function resolvePerfectSentenceCode(words, prefixes){
    for (const prefix of prefixes){
      let i = 0;
      while (true){
        const code = `${prefix}${i}`;
        let ok = true;
        for (const w of words){
          if (!(await wordHasCode(w, code))){
            ok = false;
            break;
          }
        }
        if (ok) return code;
        i++;
      }
    }
    return null;
  }

  // ---------------- WORD AUDIO CHECK ----------------
  async function hasWordAudio(word){
    const file = SENTENCE_CODE ? `${word}-${SENTENCE_CODE}` : word;
    const key = getVoicepack() + file;

    if (WORD_CACHE[key] !== undefined) return WORD_CACHE[key];

    try {
      const ok = await fetch(`${getVoicepack()}${file}.mp3`, { method:'HEAD' })
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

    const available = [];
    await Promise.all([...units].map(async syl=>{
      try {
        const ok = await fetch(`${getVoicepack()}${syl}.mp3`, { method:'HEAD' })
          .then(r=>r.ok);
        if (ok) available.push(syl);
      } catch {}
    }));

    AVAILABLE = available.sort((a,b)=>b.length - a.length);
    return AVAILABLE;
  }

  // ---------------- AUDIO PLAYER ----------------
  function play(unit){
    return new Promise((resolve,reject)=>{
      const src = getVoicepack()
        + (SENTENCE_CODE ? `${unit}-${SENTENCE_CODE}` : unit)
        + '.mp3?v=' + Date.now();

      const audio = new Audio(src);
      audio.preload = 'auto';
      audio.onended = resolve;
      audio.onerror = () => reject(unit);
      audio.play().catch(()=>reject(unit));
    });
  }

  // ---------------- SEGMENTATION (FILIPINO RULES) ----------------
  function segmentWord(word){
    const cache = {};

    function helper(start){
      if (start >= word.length) return [[]];
      if (cache[start]) return cache[start];

      const results = [];

      for (const syl of NG_SYLLABLES){
        if (word.startsWith(syl,start)){
          for (const r of helper(start + syl.length))
            results.push([syl,...r]);
        }
      }

      for (let len=2; len<=3; len++){
        if (start+len <= word.length){
          const p = word.slice(start,start+len);
          if (len===2 && isConsonant(p[0]) && isVowel(p[1]))
            results.push(...helper(start+len).map(r=>[p,...r]));
          if (len===3 && isConsonant(p[0]) && isVowel(p[1]) && isConsonant(p[2]))
            results.push(...helper(start+len).map(r=>[p,...r]));
        }
      }

      if (isVowel(word[start]))
        results.push(...helper(start+1).map(r=>[word[start],...r]));

      cache[start] = results;
      return results;
    }

    const segs = helper(0);
    return segs[0] || [word];
  }

  // ---------------- SENTENCE PLANNER ----------------
  async function planSentence(text){
    text = (text||'')
      .toLowerCase()
      .replace(/-/g,' ')
      .replace(/[^a-z\s]/g,'')
      .replace(/\s+/g,' ')
      .trim();

    const words = text.split(/\s+/);

    SENTENCE_CODE = getMode() === 'words'
      ? await resolvePerfectSentenceCode(words, getSelectedPrefixes())
      : null;

    if (getMode() === 'syllables')
      await loadAvailable();

    const units = [];
    for (const word of words){
      if (await hasWordAudio(word)){
        units.push(word);
      } else if (getMode() === 'syllables') {
        units.push(...segmentWord(word));
      } else {
        units.push(word);
      }
    }

    return { input:text, units, code:SENTENCE_CODE };
  }

  // ---------------- BOT API ----------------
  const bot = {
    async plan(text){ return planSentence(text); },
    async speak(text){
      if (!text) return;
      const plan = await planSentence(text);
      for (const u of plan.units){
        try { await play(u); }
        catch { await new Promise(r=>setTimeout(r,120)); }
      }
    }
  };

  // ---------------- EXPORT ----------------
  window.TTS = window.TTS || {};
  window.TTS.bot = bot;
  window.TTS.speak = async t => window.TTS.bot.speak(t);

})();
