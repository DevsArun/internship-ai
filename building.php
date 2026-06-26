<?php
session_name('ai_studio_session');
session_start();
if (!isset($_SESSION['studio_user_id'])) { header('Location: index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Building — AI Studio</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #1F2A44; font-family: 'Segoe UI', sans-serif; color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 40px; width: 100%; max-width: 720px; }
.prog-bg   { background: rgba(255,255,255,0.1); border-radius: 999px; height: 8px; overflow: hidden; }
.prog-fill { height: 100%; border-radius: 999px; background: #fff; transition: width 0.4s ease; }
.stat-box  { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 16px; text-align: center; }
.day-log { height: 320px; overflow-y: auto; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px; font-family: 'Courier New', monospace; font-size: 12px; }
.day-log::-webkit-scrollbar { width: 4px; }
.day-log::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }
.log-ok   { color: #4ade80; }
.log-wait { color: rgba(255,255,255,0.25); }
.log-spin { color: #93c5fd; }
.log-err  { color: #f87171; }
.log-warn { color: #fbbf24; }
.btn-retry { background: rgba(239,68,68,0.2); border: 1px solid rgba(239,68,68,0.4); color: #fca5a5; border-radius: 8px; padding: 9px 18px; font-size: 13px; cursor: pointer; }
</style>
</head>
<body>
<div class="card">
  <div style="text-align:center;margin-bottom:32px">
    <div id="topIcon" style="font-size:48px;margin-bottom:12px">⚡</div>
    <h1 id="mainTitle" style="font-size:22px;font-weight:800;margin-bottom:6px">Course Generate Ho Raha Hai...</h1>
    <p id="mainSub" style="color:rgba(255,255,255,0.4);font-size:14px">Page band mat karo — content generate ho raha hai</p>
  </div>
  <div style="margin-bottom:24px">
    <div style="display:flex;justify-content:space-between;margin-bottom:8px">
      <span style="color:rgba(255,255,255,0.5);font-size:13px" id="progLabel">Initializing...</span>
      <span style="color:#fff;font-size:13px;font-weight:700" id="progPct">0%</span>
    </div>
    <div class="prog-bg"><div class="prog-fill" id="progBar" style="width:0%"></div></div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:24px">
    <div class="stat-box"><div id="sDone" style="color:#4ade80;font-size:22px;font-weight:800">0</div><div style="color:rgba(255,255,255,0.4);font-size:11px;margin-top:2px">Done</div></div>
    <div class="stat-box"><div id="sTotal" style="font-size:22px;font-weight:800">—</div><div style="color:rgba(255,255,255,0.4);font-size:11px;margin-top:2px">Total Days</div></div>
    <div class="stat-box"><div id="sBatch" style="color:#93c5fd;font-size:22px;font-weight:800">—</div><div style="color:rgba(255,255,255,0.4);font-size:11px;margin-top:2px">Batch</div></div>
    <div class="stat-box"><div id="sETA" style="color:#fbbf24;font-size:22px;font-weight:800">—</div><div style="color:rgba(255,255,255,0.4);font-size:11px;margin-top:2px">ETA</div></div>
  </div>
  <div class="day-log" id="dayLog"><div class="log-wait">System initializing...</div></div>
  <div id="errBox" style="display:none;margin-top:16px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:12px;padding:14px">
    <p style="color:#fca5a5;font-size:13px;margin-bottom:10px" id="errMsg"></p>
    <button class="btn-retry" onclick="retryFailed()">🔄 Retry Failed Days</button>
  </div>
</div>

<script>
// ─── STATE ───────────────────────────────────────────────────────────────────
var syllabus = [], meta = {}, allContent = {}, failedDays = [];
var AI_SETTINGS = null;

// ─── PROVIDER CONFIG ──────────────────────────────────────────────────────────
// Default model lists per provider (fallback if no saved model)
var PROVIDER_MODELS = {
  gemini:     ['gemini-2.5-flash','gemini-2.0-flash','gemini-2.5-pro','gemini-2.0-flash-lite'],
  groq:       ['llama-3.3-70b-versatile','llama-3.1-8b-instant','gemma2-9b-it','qwen/qwen3-32b','openai/gpt-oss-20b'],
  deepseek:   ['deepseek-chat','deepseek-reasoner'],
  openrouter: ['deepseek/deepseek-chat-v3-0324:free','meta-llama/llama-3.3-70b-instruct:free','qwen/qwen-2.5-72b-instruct:free','google/gemini-2.0-flash-exp:free','mistralai/mistral-small-3.1-24b-instruct:free'],
  cerebras:   ['llama-3.3-70b','qwen-3-32b','llama3.1-8b'],
  openai:     ['gpt-4o-mini','gpt-4o','gpt-3.5-turbo'],
  grok:       ['grok-3-fast','grok-3','grok-2']
};

// Max output tokens — 8000 gives room for a deep, premium 1500-2500 word lesson.
// Smaller fallback models that can't handle this (HTTP 413) are auto-skipped,
// so the big, capable models (llama-3.3-70b, gemini, deepseek, etc.) do the work.
var MAX_OUTPUT_TOKENS = 8000;

// ─── UTILS ────────────────────────────────────────────────────────────────────
function sleep(ms){ return new Promise(function(r){ setTimeout(r, ms); }); }

function log(msg, type){
  type = type || 'wait';
  var el  = document.getElementById('dayLog');
  var div = document.createElement('div');
  div.className   = 'log-' + type;
  div.textContent = msg || '\u00a0';
  el.appendChild(div);
  el.scrollTop = el.scrollHeight;
}

function setProgress(pct, label){
  document.getElementById('progBar').style.width   = pct + '%';
  document.getElementById('progPct').textContent   = pct + '%';
  document.getElementById('progLabel').textContent = label;
}

// ─── BUILD PROVIDER CHAIN ─────────────────────────────────────────────────────
// Returns array of {provider, keys[], models[]} in priority order
// Primary provider first, then others as fallback
function buildProviderChain(settings){
  var primary = settings.active_ai_provider || 'gemini';

  // All providers in order: primary first, then the rest as fallback
  var order = [primary];
  ['gemini','groq','deepseek','openrouter','cerebras','openai','grok'].forEach(function(p){
    if(p !== primary) order.push(p);
  });

  var chain = [];
  order.forEach(function(p){
    var keys = [
      (settings[p+'_api_key']   || '').trim(),
      (settings[p+'_api_key_2'] || '').trim(),
      (settings[p+'_api_key_3'] || '').trim()
    ].filter(function(k){ return k.length > 0; });

    if(keys.length === 0) return; // skip if no keys configured

    var savedModel = (settings[p+'_model'] || '').trim();
    var models = [];
    if(savedModel) models.push(savedModel);
    // Add default models as fallback (skip duplicates)
    (PROVIDER_MODELS[p] || []).forEach(function(m){
      if(models.indexOf(m) === -1) models.push(m);
    });

    chain.push({ provider: p, keys: keys, models: models, deadKeys: [], deadModels: [] });
  });

  return chain;
}

// ─── API CALLERS ──────────────────────────────────────────────────────────────
function callGemini(apiKey, model, prompt){
  var ctrl  = new AbortController();
  var timer = setTimeout(function(){ ctrl.abort(); }, 50000);
  return fetch(
    'https://generativelanguage.googleapis.com/v1beta/models/' + model + ':generateContent?key=' + apiKey,
    { method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ contents:[{parts:[{text:prompt}]}], generationConfig:{temperature:0.6, maxOutputTokens:MAX_OUTPUT_TOKENS} }),
      signal: ctrl.signal }
  ).then(function(res){
    clearTimeout(timer);
    if(res.status === 429) return {ok:false, code:429, msg:'RATE_LIMIT'};
    if(res.status === 503) return {ok:false, code:503, msg:'OVERLOADED'};
    if(!res.ok)            return {ok:false, code:res.status, msg:'HTTP_'+res.status};
    return res.json().then(function(j){
      var text = '';
      try { text = j.candidates[0].content.parts[0].text; } catch(e){}
      return text.trim() ? {ok:true, text:text} : {ok:false, code:0, msg:'EMPTY'};
    });
  }).catch(function(e){
    clearTimeout(timer);
    return {ok:false, code:408, msg: e.name==='AbortError'?'TIMEOUT':e.message};
  });
}

function callOpenAIStyle(endpoint, apiKey, model, prompt){
  var ctrl  = new AbortController();
  var timer = setTimeout(function(){ ctrl.abort(); }, 50000);
  return fetch(endpoint, {
    method:'POST',
    headers:{'Content-Type':'application/json', 'Authorization':'Bearer '+apiKey},
    body: JSON.stringify({ model:model, messages:[{role:'user',content:prompt}], max_tokens:MAX_OUTPUT_TOKENS, temperature:0.6 }),
    signal: ctrl.signal
  }).then(function(res){
    clearTimeout(timer);
    if(res.status === 429) return {ok:false, code:429, msg:'RATE_LIMIT'};
    if(res.status === 401) return {ok:false, code:401, msg:'INVALID_KEY'};
    if(res.status === 404) return {ok:false, code:404, msg:'MODEL_NOT_FOUND'};
    if(res.status === 503) return {ok:false, code:503, msg:'OVERLOADED'};
    if(!res.ok)            return {ok:false, code:res.status, msg:'HTTP_'+res.status};
    return res.json().then(function(j){
      var text = '';
      try { text = j.choices[0].message.content; } catch(e){}
      return text.trim() ? {ok:true, text:text} : {ok:false, code:0, msg:'EMPTY'};
    });
  }).catch(function(e){
    clearTimeout(timer);
    return {ok:false, code:408, msg: e.name==='AbortError'?'TIMEOUT':e.message};
  });
}

function callAPI(provider, apiKey, model, prompt){
  if(provider === 'gemini')     return callGemini(apiKey, model, prompt);
  if(provider === 'groq')       return callOpenAIStyle('https://api.groq.com/openai/v1/chat/completions', apiKey, model, prompt);
  if(provider === 'deepseek')   return callOpenAIStyle('https://api.deepseek.com/v1/chat/completions', apiKey, model, prompt);
  if(provider === 'openrouter') return callOpenAIStyle('https://openrouter.ai/api/v1/chat/completions', apiKey, model, prompt);
  if(provider === 'cerebras')   return callOpenAIStyle('https://api.cerebras.ai/v1/chat/completions', apiKey, model, prompt);
  if(provider === 'openai')     return callOpenAIStyle('https://api.openai.com/v1/chat/completions', apiKey, model, prompt);
  if(provider === 'grok')       return callOpenAIStyle('https://api.x.ai/v1/chat/completions', apiKey, model, prompt);
  return Promise.resolve({ok:false, code:0, msg:'UNKNOWN_PROVIDER'});
}

// ─── PROMPT BUILDERS ──────────────────────────────────────────────────────────
// Two distinct modes:
//   1. CONTENT DAY  -> rich, beginner-friendly, in-depth lesson (NO quiz)
//   2. QUIZ DAY     -> ONLY a 10-question quiz on the previous 6 days (NO lesson content)

function buildContentPrompt(topic, level, language, dayObj, totalDays){
  var topics = (dayObj.topics || []).join(', ');
  var lang   = language || 'English';

  // EVERY course is a full beginner→advanced journey (level is optional).
  // Depth ramps as the course progresses, but quality is ALWAYS premium.
  var pct = dayObj.day / Math.max(totalDays || dayObj.day, 1);
  var effLevel, phaseNote;
  if(pct <= 0.34){
    effLevel  = 'Beginner / Foundations';
    phaseNote = 'This day is in the FOUNDATIONS phase of a zero-to-professional journey. Assume the learner is a complete beginner: introduce each idea from first principles, define every term, and build intuition step by step — but still go genuinely deep (always explain the WHY, not just the WHAT). Never be shallow even though it is beginner level.';
  } else if(pct <= 0.67){
    effLevel  = 'Intermediate';
    phaseNote = 'This day is in the INTERMEDIATE phase. The learner already mastered the basics in earlier days, so teach deeper mechanics, how things work under the hood, trade-offs, and realistic industry-style examples that combine multiple concepts.';
  } else {
    effLevel  = 'Advanced / Professional';
    phaseNote = 'This day is in the ADVANCED / PROFESSIONAL phase. The learner now has solid fundamentals, so teach at an expert level: internals, edge cases, performance, security, design patterns, best practices, and real-world production-grade application — the kind of depth a senior professional needs.';
  }

  return 'ROLE: You are a PRINCIPAL-level expert in "' + topic + '" and a world-renowned instructor whose paid premium courses sell for a high price. '
    + 'You are writing ONE day of a flagship, premium "' + topic + '" course that takes a learner from absolute beginner to job-ready professional.\n\n'
    + 'LANGUAGE: Write the ENTIRE lesson — every heading, sentence, list item, and code comment/explanation — in ' + lang + '. '
    + 'Do NOT mix languages. Keep technical keywords/code in English (as is standard), but all teaching prose must be in ' + lang + '.\n\n'
    + 'DEPTH TARGET FOR TODAY: ' + effLevel + '.\n' + phaseNote + '\n\n'
    + 'DAY ' + dayObj.day + ' — ' + dayObj.title + '\n'
    + 'Topics to cover completely and masterfully: ' + topics + '\n\n'
    + 'Write a COMPLETE, in-depth, premium lesson using clean semantic HTML, structured EXACTLY like this:\n'
    + '1. <h3>Introduction</h3> — why this topic matters, where it is used in the real world / industry, and what the learner will be able to do after this lesson.\n'
    + '2. For EACH topic above: a <h3>topic name</h3>, then teach it thoroughly — a clear definition, the intuition behind it, a relatable real-world analogy, HOW it actually works (the mechanics), and WHEN/WHY to use it.\n'
    + '3. <h3>Worked Examples</h3> — one or more concrete, realistic examples. Where code applies, give a complete, well-commented <pre><code>...</code></pre> block, then explain it line-by-line in plain language so a beginner fully understands.\n'
    + '4. <h3>Real-World Application</h3> — how professionals use this on real projects; a short scenario or case.\n'
    + '5. <h3>Best Practices & Pro Tips</h3> — a <ul> of expert tips and conventions used in industry.\n'
    + '6. <h3>Common Mistakes</h3> — a <ul> of 3-5 mistakes beginners make and exactly how to avoid each.\n'
    + '7. <h3>Practice Task</h3> — 1-3 hands-on exercises (increasing difficulty) the learner should try, with a hint for each.\n'
    + '8. <h3>Key Takeaways</h3> — a <ul> crisp one-line summary of the most important points.\n\n'
    + 'QUALITY BAR (this is PAID, premium content — treat it as worth real money):\n'
    + '- Be genuinely deep, precise and insightful — match the quality of the best technical books and top-rated instructors. NEVER generic, NEVER filler, NEVER shallow.\n'
    + '- Target 1500-2500 words of real teaching. Explain the "why" and the "how", with reasoning, not just definitions.\n'
    + '- Every concept must be understandable to a beginner yet complete enough to satisfy a professional. Define terms on first use.\n'
    + '- Use concrete numbers, realistic examples, and where relevant, comparisons/trade-offs. Connect today\'s topic to what was learned earlier.\n'
    + '- Short, readable paragraphs. Use <strong> for key terms, <ul>/<li> for lists, <pre><code> for code, <blockquote> for important notes.\n'
    + '- Use ONLY these tags: <h3>,<h4>,<p>,<ul>,<ol>,<li>,<strong>,<em>,<code>,<pre>,<blockquote>. NEVER output <html>,<head>,<body>,<style>,<script>, or markdown fences.\n\n'
    + 'Return ONLY valid JSON (no markdown, no backticks). The "content" value must be a single HTML string with all quotes properly escaped:\n'
    + '{"day":' + dayObj.day + ',"content":"<h3>Introduction</h3>...","image_query":"2-4 word image search query","quiz":[]}';
}

function buildQuizPrompt(topic, level, language, dayObj, weekTopics, weekNum){
  var lang = language || 'English';
  return 'You are an expert ' + topic + ' instructor creating a WEEKLY REVISION QUIZ.\n'
    + 'This is the quiz for WEEK ' + weekNum + ' of a premium "' + topic + '" course.\n'
    + 'Write EVERY question, option and explanation entirely in ' + lang + ' (keep code/keywords in English as standard).\n\n'
    + 'The quiz MUST test ONLY what the learner studied in the last 6 days of this week. Those topics are:\n'
    + weekTopics + '\n\n'
    + 'Create EXACTLY 10 high-quality multiple-choice questions that fairly cover ONLY the topics listed above '
    + '(do NOT ask anything outside these topics, and do NOT teach any new content).\n'
    + 'Make the questions meaningful and conceptual (test real understanding, not trivia). '
    + 'Mix the difficulty: about 4 easy, 4 medium, 2 challenging. '
    + 'Each question must have exactly 4 options, exactly one correct answer, and a clear explanation of why it is correct.\n\n'
    + 'Return ONLY valid JSON, no markdown, no backticks. Keep "content" an empty string (this is a quiz-only day):\n'
    + '{"day":' + dayObj.day + ',"content":"","image_query":"",'
    + '"quiz":[{"question":"...","options":["opt A","opt B","opt C","opt D"],"correct_index":0,"explanation":"..."}]}';
}

// ─── JSON PARSER ─────────────────────────────────────────────────────────────
function parseOneDayJSON(raw){
  var text = raw.replace(/```json\s*/gi,'').replace(/```\s*/gi,'').trim();
  // Try direct parse
  try { var p = JSON.parse(text); if(p && p.day) return p; } catch(e){}
  // Try extract from first { to last }
  var s = text.indexOf('{'), en = text.lastIndexOf('}');
  if(s !== -1 && en > s){
    try { var p2 = JSON.parse(text.substring(s, en+1)); if(p2 && p2.day) return p2; } catch(e){}
  }
  // Regex fallback
  try {
    var dm  = text.match(/"day"\s*:\s*(\d+)/);
    var iqm = text.match(/"image_query"\s*:\s*"([^"]+)"/);
    var day = dm ? parseInt(dm[1]) : 0;
    var iq  = iqm ? iqm[1] : '';
    var cm  = text.match(/"content"\s*:\s*"([\s\S]+?)(?=",\s*"(?:image_query|quiz|day))/);
    var content = cm ? cm[1].replace(/\\n/g,'\n').replace(/\\"/g,'"') : '';
    if(day && content) return {day:day, content:content, image_query:iq, quiz:[]};
  } catch(e){}
  return null;
}

// ─── CORE: GENERATE ONE DAY WITH FULL PROVIDER CHAIN FALLBACK ─────────────────
// This is the KEY fix — it properly cycles through ALL providers, not just retrying same one
async function generateOneDay(chain, dayObj, meta, weekTopics){
  var topic    = meta.topic;
  var level    = meta.level;
  var language = meta.language;

  // Quiz day ONLY when include_quiz is on AND the day number is a multiple of 7.
  var isQuizDay = !!(meta.include_quiz && dayObj.has_quiz);
  var weekNum   = Math.ceil(dayObj.day / 7);

  var prompt = isQuizDay
    ? buildQuizPrompt(topic, level, language, dayObj, weekTopics, weekNum)
    : buildContentPrompt(topic, level, language, dayObj, (meta.days || syllabus.length));

  // Track per-provider rate limit cooldowns
  var rateLimitUntil = {}; // provider -> timestamp when usable again

  var MAX_CALLS  = 26;   // max REAL api calls (cooldown waits don't count)
  var callCount  = 0;    // counts only actual API calls
  var loopGuard  = 0;    // hard safety against infinite loops
  var quizRetryCount = 0;

  // Helper: live (non-dead) keys / models for a provider entry
  function liveKeysOf(c){   return c.keys.filter(function(k){ return c.deadKeys.indexOf(k) === -1; }); }
  function liveModelsOf(c){ return c.models.filter(function(m){ return c.deadModels.indexOf(m) === -1; }); }

  while(callCount < MAX_CALLS && loopGuard < 300){
    loopGuard++;

    // Find the best available provider right now (live key + live model + not cooling down)
    var selectedProvider = null;
    var selectedChain    = null;

    for(var ci = 0; ci < chain.length; ci++){
      var c = chain[ci];
      if(liveKeysOf(c).length === 0)   continue; // all keys dead
      if(liveModelsOf(c).length === 0) continue; // all models dead/unusable
      if(Date.now() < (rateLimitUntil[c.provider] || 0)) continue; // cooling down
      selectedProvider = c;
      selectedChain    = ci;
      break;
    }

    // No provider ready — wait for the earliest cooldown (does NOT consume a call)
    if(!selectedProvider){
      var earliest = Infinity;
      chain.forEach(function(c){
        if(liveKeysOf(c).length > 0 && liveModelsOf(c).length > 0 && rateLimitUntil[c.provider]){
          earliest = Math.min(earliest, rateLimitUntil[c.provider]);
        }
      });
      if(earliest === Infinity){
        log('  ❌ All providers exhausted / no valid keys or models', 'err');
        return {success: false};
      }
      var waitMs = Math.max(0, earliest - Date.now()) + 1000;
      log('  ⏳ All providers cooling down — wait ' + Math.ceil(waitMs/1000) + 's...', 'warn');
      await sleep(waitMs);
      continue;
    }

    // Pick a live key + live model (rotate as real calls accumulate)
    var liveKeys   = liveKeysOf(selectedProvider);
    var liveModels = liveModelsOf(selectedProvider);
    var keyIdx     = callCount % liveKeys.length;
    var modelIdx   = Math.floor(callCount / liveKeys.length) % liveModels.length;
    var apiKey     = liveKeys[keyIdx];
    var model      = liveModels[modelIdx];

    callCount++;
    log('  Day ' + dayObj.day + ' | Try ' + callCount + ': [' + selectedProvider.provider.toUpperCase() + '] ' + model + ' (Key ' + (keyIdx+1) + '/' + liveKeys.length + ')', 'spin');

    var res = await callAPI(selectedProvider.provider, apiKey, model, prompt);

    if(res.ok){
      var parsed = parseOneDayJSON(res.text);
      if(!parsed || !parsed.day){
        log('  Day ' + dayObj.day + ' — JSON parse fail, retry...', 'warn');
        await sleep(1000);
        continue;
      }

      // Validate quiz on quiz days — they MUST have a real quiz
      if(isQuizDay){
        var quiz = parsed.quiz || [];
        if(!Array.isArray(quiz) || quiz.length < 5){
          quizRetryCount++;
          if(quizRetryCount <= 3){
            log('  ⚠️ Quiz empty/incomplete (' + (quiz ? quiz.length : 0) + 'Q) — retry ' + quizRetryCount + '/3...', 'warn');
            await sleep(1500);
            continue;
          } else {
            log('  ⚠️ Quiz still failing after 3 tries — accepting as-is', 'warn');
          }
        } else {
          log('  📝 Quiz: ' + quiz.length + ' questions ready (Week ' + weekNum + ' revision)', 'ok');
        }
        // Normalize every quiz item: ensure a numeric `correct` index exists
        parsed.quiz = (parsed.quiz || []).map(function(q){
          var ci = (typeof q.correct_index === 'number') ? q.correct_index
                 : (typeof q.correct === 'number')       ? q.correct
                 : parseInt(q.correct_index, 10);
          if(isNaN(ci) || ci < 0 || ci > 3) ci = 0;
          q.correct       = ci;
          q.correct_index = ci;
          return q;
        });
        // Quiz day = NO learning content
        parsed.content     = '';
        parsed.image_query = '';
      } else {
        // Content day = never carry a quiz
        parsed.quiz = [];
      }

      return {success: true, data: parsed};
    }

    // ─── Handle errors ───────────────────────────────────────────────────────
    if(res.code === 401){
      // Dead key — permanently remove
      log('  ❌ Key ' + (keyIdx+1) + ' invalid (401) — permanently skip kiya', 'err');
      selectedProvider.deadKeys.push(apiKey);
      // Check if all keys dead for this provider
      var remainingKeys = selectedProvider.keys.filter(function(k){ return selectedProvider.deadKeys.indexOf(k) === -1; });
      if(remainingKeys.length === 0){
        log('  🔴 ' + selectedProvider.provider.toUpperCase() + ' — all keys dead, provider skip', 'err');
      }
      await sleep(500);
      continue;
    }

    if(res.code === 429){
      // Rate limited — set cooldown for THIS provider and SWITCH to next
      var cooldownMs = 60000; // 60 second default cooldown
      rateLimitUntil[selectedProvider.provider] = Date.now() + cooldownMs;
      log('  ⚠️ ' + selectedProvider.provider.toUpperCase() + ' rate limited — 60s cooldown, switching provider...', 'warn');

      // Try to find another provider immediately
      var nextAvailable = null;
      for(var ni = 0; ni < chain.length; ni++){
        if(ni === selectedChain) continue;
        var nc = chain[ni];
        var nliveKeys = nc.keys.filter(function(k){ return nc.deadKeys.indexOf(k) === -1; });
        if(nliveKeys.length === 0) continue;
        var ncooldown = rateLimitUntil[nc.provider] || 0;
        if(Date.now() >= ncooldown){ nextAvailable = nc; break; }
      }

      if(nextAvailable){
        log('  🔄 Switched to: ' + nextAvailable.provider.toUpperCase(), 'spin');
      } else {
        log('  ⏳ No provider available — waiting...', 'warn');
      }
      await sleep(500);
      continue;
    }

    if(res.code === 404 || res.code === 413 || res.code === 400){
      // Model permanently unusable for this run (decommissioned / payload too big /
      // bad request). Add it to the skip-list so we never waste another call on it.
      log('  ⚠️ ' + model + ' unusable (' + res.code + ') — skipping this model', 'warn');
      if(selectedProvider.deadModels.indexOf(model) === -1) selectedProvider.deadModels.push(model);
      var liveLeft = liveModelsOf(selectedProvider).length;
      if(liveLeft === 0){
        log('  🔴 ' + selectedProvider.provider.toUpperCase() + ' — no usable models left, provider skip', 'err');
      }
      await sleep(400);
      continue;
    }

    if(res.code === 503 || res.code === 408 || res.code === 500){
      // Temporary server error — short wait, try next model/provider
      log('  ⚠️ ' + res.msg + ' — 5s wait, retry...', 'warn');
      await sleep(5000);
      continue;
    }

    // Unknown error
    log('  ❌ Error ' + res.code + ': ' + res.msg, 'err');
    await sleep(2000);
  }

  log('  ❌ Day ' + dayObj.day + ' — max attempts reached', 'err');
  return {success: false};
}

// ─── MAIN FLOW ────────────────────────────────────────────────────────────────
async function loadSettings(){
  try {
    var r = await fetch('api/ai/get-settings.php');
    var d = await r.json();
    if(d.success){ AI_SETTINGS = d.settings; return true; }
  } catch(e){ console.warn('Settings load error:', e); }
  return false;
}

// ─── WEEK TOPICS COLLECTOR ─────────────────────────────────────────────────────
// For a quiz day at syllabus index `idx`, gather the titles + topics of the
// content days that belong to the SAME week (the previous up-to-6 days).
function getWeekTopics(idx){
  var quizDay = syllabus[idx].day;
  var lines   = [];
  for(var j = idx - 1; j >= 0; j--){
    var prev = syllabus[j];
    if(prev.has_quiz) break;            // reached the previous week's quiz day
    if(quizDay - prev.day > 6) break;   // safety: stay within this week
    lines.unshift('Day ' + prev.day + ': ' + (prev.title || '') + ' — ' + (prev.topics || []).join(', '));
  }
  return lines.length ? lines.join('\n') : ('Topics covered in the previous days of "' + (meta.topic || '') + '".');
}

async function generateAll(chain, meta){
  failedDays = [];
  var doneDays = 0, t0 = Date.now();
  var BS = parseInt(meta.batch_size) || 7;
  var totalBatches = Math.ceil(syllabus.length / BS);

  for(var i = 0; i < syllabus.length; i++){
    var dayObj = syllabus[i];

    if(i % BS === 0){
      var bNum  = Math.floor(i / BS) + 1;
      var bEnd  = Math.min(i + BS - 1, syllabus.length - 1);
      document.getElementById('sBatch').textContent = bNum + '/' + totalBatches;
      log('── Batch ' + bNum + '/' + totalBatches + ': Day ' + syllabus[i].day + '–' + syllabus[bEnd].day + ' ──', 'spin');
    }

    var weekTopics = (meta.include_quiz && dayObj.has_quiz) ? getWeekTopics(i) : '';
    var res = await generateOneDay(chain, dayObj, meta, weekTopics);

    if(res.success){
      var item = res.data;
      allContent[item.day] = {
        day:         item.day,
        title:       dayObj.title || 'Day ' + item.day,
        topics:      dayObj.topics || [],
        has_quiz:    dayObj.has_quiz || false,
        content:     item.content || '',
        image_query: item.image_query || (dayObj.image_query || meta.topic),
        quiz:        item.quiz || []
      };
      doneDays++;
      var qInfo = (item.quiz && item.quiz.length > 0) ? ' [Quiz: ' + item.quiz.length + 'Q]' : '';
      log('✅ Day ' + item.day + ': ' + (dayObj.title || '') + qInfo, 'ok');
    } else {
      log('❌ Day ' + dayObj.day + ' failed — will retry later', 'err');
      failedDays.push(i);
    }

    // Update stats
    var pct = Math.round((doneDays / syllabus.length) * 100);
    setProgress(pct, 'Day ' + doneDays + '/' + syllabus.length + ' complete');
    document.getElementById('sDone').textContent  = doneDays;

    if(doneDays > 0){
      var el  = (Date.now() - t0) / 1000;
      var rem = ((syllabus.length - doneDays) * el) / doneDays;
      document.getElementById('sETA').textContent = rem > 60 ? Math.ceil(rem/60) + 'm' : Math.ceil(rem) + 's';
    }

    // Batch boundary pause (every BS days) — but only between batches, not after last day
    if((i + 1) % BS === 0 && i < syllabus.length - 1){
      log('⏸️ Batch complete — 30s wait...', 'wait');
      await sleep(30000);
      log('▶️ Next batch shuru...', 'spin');
    } else {
      await sleep(600); // small delay between days
    }
  }

  if(failedDays.length > 0){
    document.getElementById('errBox').style.display = 'block';
    document.getElementById('errMsg').textContent   = failedDays.length + ' day(s) fail hue. Retry karo.';
  } else {
    finalize();
  }
}

async function retryFailed(){
  document.getElementById('errBox').style.display = 'none';
  var toRetry = failedDays.slice();
  failedDays  = [];
  var chain   = window._chain;
  // Reset dead keys, dead models and rate limits for a fresh retry
  chain.forEach(function(c){ c.deadKeys = []; c.deadModels = []; });

  log('🔄 Retrying ' + toRetry.length + ' failed day(s)...', 'spin');

  for(var ri = 0; ri < toRetry.length; ri++){
    var idx    = toRetry[ri];
    var dayObj = syllabus[idx];
    log('── Retry: Day ' + dayObj.day + ' ──', 'spin');
    var weekTopics = (meta.include_quiz && dayObj.has_quiz) ? getWeekTopics(idx) : '';
    var res = await generateOneDay(chain, dayObj, meta, weekTopics);
    if(res.success){
      var item = res.data;
      allContent[item.day] = {
        day:         item.day,
        title:       dayObj.title || 'Day ' + item.day,
        topics:      dayObj.topics || [],
        has_quiz:    dayObj.has_quiz || false,
        content:     item.content || '',
        image_query: item.image_query || meta.topic,
        quiz:        item.quiz || []
      };
      log('✅ Day ' + item.day + ' retry OK', 'ok');
      document.getElementById('sDone').textContent = parseInt(document.getElementById('sDone').textContent) + 1;
    } else {
      failedDays.push(idx);
      log('❌ Day ' + dayObj.day + ' still failing', 'err');
    }
    await sleep(1500);
  }

  if(failedDays.length > 0){
    document.getElementById('errBox').style.display = 'block';
    document.getElementById('errMsg').textContent   = failedDays.length + ' day(s) abhi bhi fail. Dobara retry karo.';
  } else {
    finalize();
  }
}

function finalize(){
  setProgress(100, 'Complete!');
  document.getElementById('sETA').textContent   = 'Done';
  document.getElementById('sBatch').textContent = 'Done';
  document.getElementById('topIcon').textContent = '🎉';
  document.getElementById('mainTitle').textContent = 'Course Ready!';
  document.getElementById('mainSub').innerHTML = '<span style="color:#4ade80;font-weight:600">Sab content ready! Preview pe ja raha hoon...</span>';
  log('🎉 All done! Saving...', 'ok');
  var arr = Object.values(allContent).sort(function(a,b){ return a.day - b.day; });
  sessionStorage.setItem('ai_course_data', JSON.stringify({ course:arr, total_days:arr.length }));
  setTimeout(function(){ window.location.href = 'preview.php'; }, 1500);
}

// ─── INIT ─────────────────────────────────────────────────────────────────────
async function init(){
  var rawS = sessionStorage.getItem('ai_syllabus');
  var rawM = sessionStorage.getItem('ai_meta');
  if(!rawS || !rawM){ window.location.href = 'generate.php'; return; }

  syllabus = JSON.parse(rawS);
  meta     = JSON.parse(rawM);

  document.getElementById('sTotal').textContent    = syllabus.length;
  document.getElementById('mainTitle').textContent = '"' + meta.topic + '" — ' + meta.days + ' Days';
  log('📋 Syllabus: ' + syllabus.length + ' days | Level: ' + meta.level, 'ok');

  log('⏳ Settings load ho rahi hai...', 'wait');
  var ok = await loadSettings();
  if(!ok || !AI_SETTINGS){
    log('❌ Settings load fail — Settings page pe jao aur save karo', 'err');
    return;
  }

  var settingCount = Object.keys(AI_SETTINGS).length;
  log('✅ Settings loaded (' + settingCount + ' keys)', 'ok');

  // Build provider chain — THIS is what fixes the infinite loop
  var chain = buildProviderChain(AI_SETTINGS);
  if(chain.length === 0){
    log('❌ Koi bhi provider configured nahi! Settings mein API key daalo.', 'err');
    return;
  }

  window._chain = chain; // save for retry

  var primary = chain[0];
  log('🤖 Primary Provider: ' + primary.provider.toUpperCase() + ' (' + primary.keys.length + ' keys)', 'ok');
  log('🔗 Provider Chain: ' + chain.map(function(c){ return c.provider.toUpperCase()+'('+c.keys.length+'k)'; }).join(' → '), 'ok');
  log('📝 Quiz: ' + (meta.include_quiz ? '10 questions per quiz day' : 'Disabled') + ' (' + meta.level + ', ' + meta.days + ' days)', 'ok');
  log('🔗 Models: ' + primary.models.slice(0,3).join(' → ') + '...', 'ok');

  await generateAll(chain, meta);
}

init();
</script>
</body>
</html>