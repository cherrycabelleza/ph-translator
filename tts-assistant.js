/* =========================================================
   FILIPINO HYBRID SMART ADAPTIVE TTS BOT
   Word-first + Connector-aware, Rule-aware syllable fallback
   Only plays toLang audio using SENTENCE_CODE
   NG+VC sentence-ending rule implemented
   ========================================================= */
(function () {

  // ---------------- PHONOLOGY ----------------
  const VOWELS = ['a','e','i','o','u'];
  const CONSONANTS = ['b','k','d','g','h','l','m','n','p','r','s','t','w','y'];

  const BASE_NG_SYLLABLES = [
    'ang','eng','ing','ong','ung',
    'nga','nge','ngi','ngo','ngu',
    'ngan','ngen','ngin','ngon','ngun',
    'ngat','nget','ngit','ngot','ngut'
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

  // ---------------- SUPABASE STORAGE ----------------
  const SUPABASE_AUDIO_BASE = "https://owqcypobhddssaofmzcf.supabase.co/storage/v1/object/public/tts-audio/";

  const VOICEPACKS = {
    words: "ccg-words-voicepack/",
    syllables: "mj-syllable_voicepack/"
  };

  function getVoicepack(mode){
    return SUPABASE_AUDIO_BASE + (VOICEPACKS[mode] || VOICEPACKS.words);
  }

  function getAudioUrl(unit, code, voicepack){
    return code 
      ? `${voicepack}${unit}-${code}.mp3`
      : `${voicepack}${unit}.mp3`;
  }

  // ---------------- AUDIO FETCH ----------------
  async function checkWordAudio(word, code) {
    const voicepack = getVoicepack("words");
    const url = getAudioUrl(word, code, voicepack);
    try {
      const r = await fetch(url, { method: 'HEAD' });
      return r.ok;
    } catch { return false; }
  }

  async function playWord(word, code){
    const voicepack = getVoicepack("words");
    const url = getAudioUrl(word, code, voicepack);
    return new Promise((resolve,reject)=>{
      const audio = new Audio(`${url}?v=${Date.now()}`);
      audio.preload = 'auto';
      audio.onended = resolve;
      audio.onerror = () => reject(word);
      audio.play().catch(()=>reject(word));
    });
  }

  async function playSyllable(syl){
    const voicepack = getVoicepack("syllables");
    const url = getAudioUrl(syl, null, voicepack);
    return new Promise((resolve,reject)=>{
      const audio = new Audio(`${url}?v=${Date.now()}`);
      audio.preload = 'auto';
      audio.onended = resolve;
      audio.onerror = () => reject(syl);
      audio.play().catch(()=>reject(syl));
    });
  }

  // ---------------- NG+VC END RULE ----------------
  function handleNGVCEndOfSentence(word){
    if(word.length >= 4){
      const last4 = word.slice(-4);       // last 4 letters
      const last2 = last4.slice(0,2);     // ng
      const lastVC = last4.slice(2,4);    // vc
      if(last2 === 'ng' && isVowel(lastVC[0]) && isConsonant(lastVC[1])){
        const prefix = word.slice(0, word.length - 4);
        const prefixSegs = prefix.length ? segmentWord(prefix) : [];
        return [...prefixSegs, last4];     // treat ngvc as one syllable
      }
    }
    return null;
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

  // ---------------- NORMALIZE ----------------
function normalize(text){
  return (text || '')
    .toLowerCase()
    .normalize("NFKD")         // normalize unicode
    .replace(/[\u2018\u2019']/g, '')   // remove all apostrophe types
    .replace(/[^a-z]/g, ' ')   // remove everything except letters
    .replace(/\s+/g, ' ')
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
        // FALLBACK TO SYLLABLES
        for (const w of words){
          const ngvcSegs = handleNGVCEndOfSentence(w);
          const segs = ngvcSegs || segmentWord(w);
          for (const s of segs){
            await playSyllable(s);
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