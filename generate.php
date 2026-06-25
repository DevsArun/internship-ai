<?php
session_name('ai_studio_session');
session_start();
if (!isset($_SESSION['studio_user_id'])) { header('Location: index.php'); exit; }
$userName = $_SESSION['studio_name'] ?? 'User';
$userRole = $_SESSION['studio_role'] ?? 'generator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Generate — AI Studio</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #1F2A44; font-family: 'Segoe UI', sans-serif; color: #fff; }
.sidebar { background: rgba(0,0,0,0.25); border-right: 1px solid rgba(255,255,255,0.08); width: 240px; min-height: 100vh; position: fixed; top: 0; left: 0; z-index: 50; display: flex; flex-direction: column; padding: 24px 16px; }
.nav-link { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; color: rgba(255,255,255,0.5); font-size: 14px; text-decoration: none; transition: all 0.2s; }
.nav-link:hover { background: rgba(255,255,255,0.08); color: #fff; }
.nav-link.active { background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.15); }
.main { margin-left: 240px; padding: 40px; min-height: 100vh; }
.card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; }
.input-f { background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.15); color: #fff; border-radius: 10px; padding: 12px 16px; width: 100%; font-size: 14px; outline: none; transition: border-color 0.2s; }
.input-f:focus { border-color: rgba(255,255,255,0.5); }
.input-f::placeholder { color: rgba(255,255,255,0.25); }
select.input-f option { background: #1F2A44; }
label { color: rgba(255,255,255,0.6); font-size: 13px; display: block; margin-bottom: 7px; }
.type-card { background: rgba(255,255,255,0.05); border: 2px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 20px; cursor: pointer; transition: all 0.2s; text-align: center; }
.type-card:hover { border-color: rgba(255,255,255,0.3); background: rgba(255,255,255,0.08); }
.type-card.selected { border-color: #fff; background: rgba(255,255,255,0.12); }
.toggle { position: relative; width: 48px; height: 26px; background: rgba(255,255,255,0.15); border-radius: 999px; cursor: pointer; transition: background 0.3s; }
.toggle.on { background: #22c55e; }
.toggle-dot { position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; background: #fff; border-radius: 50%; transition: left 0.3s; }
.toggle.on .toggle-dot { left: 25px; }
.btn-primary { background: #fff; color: #1F2A44; border: none; border-radius: 10px; padding: 14px 24px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all 0.2s; width: 100%; }
.btn-primary:hover { background: #f0f0f0; transform: translateY(-1px); }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
.step-label { color: rgba(255,255,255,0.4); font-size: 11px; font-weight: 700; letter-spacing: 1.5px; margin-bottom: 14px; }
#progressBox { display: none; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 24px; margin-bottom: 20px; }
.prog-bar-wrap { background: rgba(255,255,255,0.08); border-radius: 999px; height: 8px; margin: 14px 0 10px; overflow: hidden; }
.prog-bar { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #22c55e, #4ade80); transition: width 0.5s ease; }
#logBox { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.06); border-radius: 10px; padding: 12px 14px; margin-top: 14px; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px; color: rgba(255,255,255,0.6); }
#logBox p { margin-bottom: 3px; line-height: 1.5; }
.log-ok   { color: #4ade80 !important; }
.log-warn { color: #fbbf24 !important; }
.log-err  { color: #f87171 !important; }
.log-info { color: rgba(255,255,255,0.5) !important; }
</style>
</head>
<body>

<div class="sidebar">
  <div style="margin-bottom:32px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
      <span style="font-size:22px">🤖</span>
      <span style="color:#fff;font-weight:800;font-size:17px">AI Studio</span>
    </div>
    <p style="color:rgba(255,255,255,0.3);font-size:11px;padding-left:32px">InternshipADDA</p>
  </div>
  <nav style="flex:1;display:flex;flex-direction:column;gap:4px">
    <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
    <a href="generate.php"  class="nav-link active">✨ Generate</a>
    <?php if($userRole==='admin'): ?>
    <a href="settings.php"  class="nav-link">⚙️ Settings</a>
    <?php endif; ?>
  </nav>
  <div style="border-top:1px solid rgba(255,255,255,0.08);padding-top:16px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
      <div style="width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px"><?= strtoupper(substr($userName,0,1)) ?></div>
      <div>
        <p style="color:#fff;font-size:13px;font-weight:600"><?= htmlspecialchars($userName) ?></p>
        <p style="color:rgba(255,255,255,0.35);font-size:11px"><?= ucfirst($userRole) ?></p>
      </div>
    </div>
    <a href="logout.php" style="display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;color:rgba(255,100,100,0.8);font-size:13px;text-decoration:none" onmouseover="this.style.background='rgba(239,68,68,0.1)'" onmouseout="this.style.background='transparent'">🚪 Logout</a>
  </div>
</div>

<div class="main">
  <div style="max-width:680px;margin:0 auto">
    <div style="margin-bottom:36px">
      <h1 style="font-size:26px;font-weight:800;margin-bottom:6px">✨ Generate New Course</h1>
      <p style="color:rgba(255,255,255,0.4);font-size:14px">AI se complete day-wise course ya internship banao</p>
    </div>

    <div id="errBox" style="display:none;background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.35);border-radius:12px;padding:14px 16px;color:#fca5a5;font-size:14px;margin-bottom:20px"></div>

    <div id="progressBox">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
        <span style="color:#fff;font-weight:700;font-size:15px">🔄 Generating...</span>
        <span id="progPercent" style="color:#4ade80;font-weight:800;font-size:15px">0%</span>
      </div>
      <div id="progMsg" style="color:rgba(255,255,255,0.7);font-size:13px">Starting...</div>
      <div class="prog-bar-wrap"><div class="prog-bar" id="progBar" style="width:0%"></div></div>
      <div id="progCount" style="color:#4ade80;font-size:12px;font-weight:700;margin-top:4px"></div>
      <div id="logBox"></div>
    </div>

    <!-- Step 1 -->
    <div class="card" style="padding:24px;margin-bottom:16px">
      <p class="step-label">STEP 1 — KISKE LIYE GENERATE KARNA HAI?</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="type-card selected" id="typeCourse" onclick="selectType('course')">
          <div style="font-size:30px;margin-bottom:8px">📚</div>
          <div style="font-weight:700;font-size:15px">Course</div>
          <div style="color:rgba(255,255,255,0.4);font-size:12px;margin-top:4px">LMS Courses mein save hoga</div>
        </div>
        <div class="type-card" id="typeInternship" onclick="selectType('internship')">
          <div style="font-size:30px;margin-bottom:8px">💼</div>
          <div style="font-weight:700;font-size:15px">Internship</div>
          <div style="color:rgba(255,255,255,0.4);font-size:12px;margin-top:4px">LMS Internships mein save hoga</div>
        </div>
      </div>
    </div>

    <!-- Step 2 -->
    <div class="card" style="padding:24px;margin-bottom:16px">
      <p class="step-label">STEP 2 — COURSE DETAILS</p>
      <div style="display:flex;flex-direction:column;gap:16px">
        <div>
          <label>Topic / Subject *</label>
          <input type="text" id="topic" class="input-f" placeholder="e.g. Python, Java, Digital Marketing, React JS...">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
          <div>
            <label>Duration *</label>
            <select id="days" class="input-f">
              <option value="7">7 Days</option>
              <option value="14">14 Days</option>
              <option value="21">21 Days</option>
              <option value="30" selected>30 Days</option>
              <option value="45">45 Days</option>
              <option value="60">60 Days</option>
              <option value="90">90 Days</option>
              <option value="120">120 Days</option>
              <option value="180">180 Days</option>
            </select>
          </div>
          <div>
            <label>Level <span style="color:rgba(255,255,255,0.4);font-weight:400">(optional)</span></label>
            <select id="level" class="input-f">
              <option value="Beginner to Advanced">Beginner to Advanced (recommended)</option>
              <option value="Beginner">Beginner</option>
              <option value="Intermediate">Intermediate</option>
              <option value="Advanced">Advanced</option>
            </select>
            <p style="color:rgba(255,255,255,0.4);font-size:11px;margin-top:6px">Koi bhi chuno - har course beginner se complete advanced tak hi banega.</p>
          </div>
          <div>
            <label>Language</label>
            <select id="language" class="input-f">
              <option value="English">English</option>
              <option value="Hindi">Hindi</option>
              <option value="Hinglish">Hinglish</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Step 3 -->
    <div class="card" style="padding:24px;margin-bottom:24px">
      <p class="step-label">STEP 3 — OPTIONS</p>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px">
        <div>
          <p style="font-weight:600;font-size:14px;margin-bottom:3px">📝 Quiz Include Karo</p>
          <p style="color:rgba(255,255,255,0.4);font-size:12px">Sirf har 7ve din quiz (us week ka revision) — us din koi naya content nahi</p>
        </div>
        <div class="toggle on" id="quizToggle" onclick="toggleQuiz()">
          <div class="toggle-dot"></div>
        </div>
      </div>
    </div>

    <button class="btn-primary" id="genBtn" onclick="startGenerate()">🚀 Generate Course with AI</button>
  </div>
</div>

<script>
let selectedType = 'course';
let includeQuiz  = true;
let AI_SETTINGS  = null;

// ── Gemini fallback chain ──────────────────────────
const GEMINI_FALLBACKS = [
    'gemini-2.5-flash','gemini-2.0-flash','gemini-1.5-flash',
    'gemini-1.5-flash-8b','gemini-1.5-pro','gemini-2.0-flash-lite'
];

// ── Groq fallback chain (removed deprecated models) ──
const GROQ_FALLBACKS = [
    'llama-3.3-70b-versatile',
    'llama-3.1-8b-instant',
    'qwen/qwen3-32b',
    'meta-llama/llama-4-scout',
    'gemma2-9b-it'
];

const OPENAI_FALLBACKS = ['gpt-4o-mini','gpt-4o','gpt-3.5-turbo'];
const GROK_FALLBACKS   = ['grok-3-fast','grok-3','grok-2-1212'];

// ── Load settings on page load ─────────────────────
async function loadSettings() {
    try {
        var r       = await fetch('../api/ai/get-settings.php');
        var rawText = await r.text();
        var data    = JSON.parse(rawText);
        if (data.success && data.settings) {
            AI_SETTINGS = data.settings;
            console.log('[INIT] Settings loaded. Provider:', AI_SETTINGS.active_ai_provider);
        } else {
            console.warn('[INIT] Settings load failed:', data.message);
        }
    } catch(e) {
        console.warn('[INIT] get-settings.php error:', e.message);
    }
}
loadSettings();

function selectType(t) {
    selectedType = t;
    document.getElementById('typeCourse').className     = 'type-card' + (t==='course'     ? ' selected' : '');
    document.getElementById('typeInternship').className = 'type-card' + (t==='internship' ? ' selected' : '');
}
function toggleQuiz() {
    includeQuiz = !includeQuiz;
    document.getElementById('quizToggle').className = 'toggle' + (includeQuiz ? ' on' : '');
}
function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }
function log(msg, cls) {
    cls = cls || 'log-info';
    var box = document.getElementById('logBox');
    var p   = document.createElement('p');
    p.textContent = msg; p.className = cls;
    box.appendChild(p); box.scrollTop = box.scrollHeight;
}
function updateProgress(msg, done, total) {
    var pct = total > 0 ? Math.round((done/total)*100) : 0;
    document.getElementById('progMsg').textContent     = msg;
    document.getElementById('progBar').style.width     = pct + '%';
    document.getElementById('progPercent').textContent = pct + '%';
    document.getElementById('progCount').textContent   = done + ' / ' + total + ' days complete';
}
function showErr(msg) {
    var el = document.getElementById('errBox');
    el.innerHTML = '❌ ' + msg; el.style.display = 'block';
    document.getElementById('progressBox').style.display = 'none';
}
function hideErr() { document.getElementById('errBox').style.display = 'none'; }
function resetBtn() {
    var btn = document.getElementById('genBtn');
    btn.disabled = false; btn.textContent = '🚀 Generate Course with AI';
}

// ── Build model list (saved first, then fallbacks) ──
function buildModels(provider, savedModel) {
    var fallbacks = {
        gemini: GEMINI_FALLBACKS,
        groq:   GROQ_FALLBACKS,
        openai: OPENAI_FALLBACKS,
        grok:   GROK_FALLBACKS
    }[provider] || GEMINI_FALLBACKS;

    var list = [];
    if (savedModel && savedModel.trim()) list.push(savedModel.trim());
    fallbacks.forEach(function(m) { if (list.indexOf(m) === -1) list.push(m); });
    return list;
}

// ── Build API keys list ────────────────────────────
function buildKeys(provider, s) {
    var keyMap = {
        gemini: [s.gemini_api_key||'', s.gemini_api_key_2||'', s.gemini_api_key_3||''],
        groq:   [s.groq_api_key||'',   s.groq_api_key_2||'',   s.groq_api_key_3||''],
        openai: [s.openai_api_key||'',  s.openai_api_key_2||'', s.openai_api_key_3||''],
        grok:   [s.grok_api_key||'',   s.grok_api_key_2||'',   s.grok_api_key_3||'']
    };
    return (keyMap[provider] || []).filter(function(k) { return k.trim(); });
}

// ── Gemini API call ────────────────────────────────
async function callGemini(apiKey, model, prompt) {
    var controller = new AbortController();
    var timer = setTimeout(function() { controller.abort(); }, 50000);
    try {
        var res = await fetch(
            'https://generativelanguage.googleapis.com/v1beta/models/'+model+':generateContent?key='+apiKey,
            { method:'POST', headers:{'Content-Type':'application/json'},
              body: JSON.stringify({contents:[{parts:[{text:prompt}]}],generationConfig:{temperature:0.7,maxOutputTokens:6000}}),
              signal: controller.signal }
        );
        clearTimeout(timer);
        if (res.status===429) return {ok:false,code:429,msg:'RATE_LIMIT'};
        if (res.status===503) return {ok:false,code:503,msg:'MODEL_OVERLOADED'};
        if (!res.ok) return {ok:false,code:res.status,msg:'HTTP_'+res.status};
        var json = await res.json();
        var text = (json&&json.candidates&&json.candidates[0]&&json.candidates[0].content&&json.candidates[0].content.parts&&json.candidates[0].content.parts[0]&&json.candidates[0].content.parts[0].text) || '';
        if (!text.trim()) return {ok:false,code:0,msg:'EMPTY_RESPONSE'};
        return {ok:true,text:text};
    } catch(e) {
        clearTimeout(timer);
        if (e.name==='AbortError') return {ok:false,code:408,msg:'TIMEOUT'};
        return {ok:false,code:0,msg:e.message};
    }
}

// ── Groq / OpenAI / Grok API call ─────────────────
async function callOpenAIFormat(provider, apiKey, model, prompt) {
    var urls = {
        groq:   'https://api.groq.com/openai/v1/chat/completions',
        openai: 'https://api.openai.com/v1/chat/completions',
        grok:   'https://api.x.ai/v1/chat/completions'
    };
    var url = urls[provider];
    if (!url) return {ok:false,code:0,msg:'Unknown provider: '+provider};

    var controller = new AbortController();
    var timer = setTimeout(function() { controller.abort(); }, 50000);
    try {
        var res = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type':'application/json','Authorization':'Bearer '+apiKey},
            body: JSON.stringify({model:model,messages:[{role:'user',content:prompt}],max_tokens:6000,temperature:0.7}),
            signal: controller.signal
        });
        clearTimeout(timer);
        if (res.status===429) return {ok:false,code:429,msg:'RATE_LIMIT'};
        if (res.status===404) return {ok:false,code:404,msg:'MODEL_NOT_FOUND'};
        if (!res.ok) return {ok:false,code:res.status,msg:'HTTP_'+res.status};
        var json = await res.json();
        var text = (json&&json.choices&&json.choices[0]&&json.choices[0].message&&json.choices[0].message.content) || '';
        if (!text.trim()) return {ok:false,code:0,msg:'EMPTY_RESPONSE'};
        return {ok:true,text:text};
    } catch(e) {
        clearTimeout(timer);
        if (e.name==='AbortError') return {ok:false,code:408,msg:'TIMEOUT'};
        return {ok:false,code:0,msg:e.message};
    }
}

// ── Parse JSON array from AI response ─────────────
function parseJSON(raw) {
    var text = raw.replace(/```json\s*/gi,'').replace(/```\s*/gi,'').trim();
    try { var p = JSON.parse(text); if (Array.isArray(p)) return p; } catch(e) {}
    var m = text.match(/\[[\s\S]+\]/m);
    if (m) try { var p2 = JSON.parse(m[0]); if (Array.isArray(p2)) return p2; } catch(e) {}
    return null;
}

// ── Build syllabus prompt ──────────────────────────
function buildPrompt(topic, totalDays, startDay, endDay, level, language, type, quiz) {
    var n    = endDay - startDay + 1;
    var role = type==='internship'
        ? 'internship program designer. Create practical daily tasks for a '+totalDays+'-day "'+topic+'" internship.'
        : 'course curriculum designer. Create a '+totalDays+'-day "'+topic+'" course.';

    // ── Difficulty progression ──────────────────────────────────────────
    // EVERY course is now a single continuous "Zero to Advanced" journey,
    // no matter which level the user picked. The level dropdown is optional;
    // the syllabus ALWAYS ramps from absolute-beginner foundations to fully
    // advanced, real-world mastery.
    var levelRule =
          'DIFFICULTY JOURNEY (MANDATORY for every course): This is a single "Zero to COMPLETE Advanced" journey of '+totalDays+' days. '
        + 'Ignore any single fixed level — ALWAYS ramp the difficulty smoothly across the full course: '
        + 'roughly the first third = absolute-beginner FOUNDATIONS (core concepts, vocabulary, simple examples, assume zero prior knowledge); '
        + 'the middle third = INTERMEDIATE (deeper concepts, real use-cases, combining ideas); '
        + 'the final third = COMPLETE ADVANCED (complex topics, internals, best practices, performance/optimization, and real-world projects so the learner reaches true mastery). '
        + 'You are generating days '+startDay+'-'+endDay+' of '+totalDays+', so choose topics that match exactly where these days '
        + 'fall in that beginner→advanced journey, building on everything taught before. By the last day the learner must be at an expert/advanced level.';

    // ── Quiz rule (STRICT) ──────────────────────────────────────────────
    // Quiz comes ONLY on every 7th day (7, 14, 21, 28 ...). Those days are
    // pure REVISION + QUIZ days — NO brand-new concept is taught on them.
    // All other days teach fresh content with has_quiz:false.
    var quizRule = quiz
        ? 'QUIZ RULE (follow EXACTLY): A day is a quiz day ONLY if its day number is a multiple of 7 (7,14,21,28,...). '
          + 'For those quiz days set "has_quiz":true, give the title as "Week N — Revision & Quiz", '
          + 'and DO NOT introduce any new concept on them (they only revise the previous 6 days). '
          + 'For EVERY other day set "has_quiz":false and teach fresh topics. Never put a quiz on a non-multiple-of-7 day.'
        : 'Set "has_quiz":false for every single day.';

    return 'You are an expert '+role+'\n'
        + levelRule + '\n'
        + 'Language: '+language+'\n'
        + 'Generate ONLY days '+startDay+' to '+endDay+' (exactly '+n+' days).\n'
        + 'Build a logical learning progression: each fresh day should cover 2-3 focused, clearly-scoped topics that build on earlier days.\n'
        + quizRule + '\n'
        + 'Return ONLY a valid JSON array, no markdown:\n'
        + '[{"day":'+startDay+',"title":"...","topics":["t1","t2","t3"],"image_query":"...","has_quiz":false}]';
}

// ── Master call with full key+model fallback ───────
async function callWithFallback(provider, apiKeys, models, prompt, batchNum) {
    var keyIdx = 0, modelIdx = 0, attempts = 0;
    var max = apiKeys.length * models.length * 3;

    while (attempts < max) {
        attempts++;
        var apiKey = apiKeys[keyIdx % apiKeys.length];
        var model  = models[modelIdx % models.length];

        log('🔁 Try '+attempts+': Key'+(keyIdx%apiKeys.length+1)+' + '+model, 'log-info');

        var res = provider === 'gemini'
            ? await callGemini(apiKey, model, prompt)
            : await callOpenAIFormat(provider, apiKey, model, prompt);

        if (res.ok) {
            var parsed = parseJSON(res.text);
            if (parsed && parsed.length > 0) {
                log('✅ Batch '+batchNum+' done — '+model, 'log-ok');
                return {success:true, data:parsed};
            }
            log('⚠️ Parse fail — next model...', 'log-warn');
            modelIdx++; await sleep(1000); continue;
        }

        if (res.code === 429) {
            log('⏳ Rate limit Key'+(keyIdx%apiKeys.length+1)+' — rotate key', 'log-warn');
            keyIdx++;
            if (keyIdx % apiKeys.length === 0) { log('⏳ All keys limited — 15s wait...','log-warn'); await sleep(15000); }
            else { await sleep(2000); }
        } else if (res.code===503||res.code===500||res.code===408) {
            log('⚠️ '+model+' '+res.msg+' — next model', 'log-warn');
            modelIdx++; await sleep(3000);
        } else if (res.code===404) {
            log('❌ '+model+' not found — skip', 'log-err');
            modelIdx++; await sleep(1000);
        } else {
            log('❌ Error '+res.code+': '+res.msg, 'log-err');
            modelIdx++; keyIdx++; await sleep(2000);
        }

        if (modelIdx >= models.length && keyIdx >= apiKeys.length) {
            log('⏳ All combos tried — 20s cooldown...', 'log-warn');
            modelIdx = 0; keyIdx = 0; await sleep(20000);
        }
    }
    return {success:false, error:'Saare models aur keys fail ho gaye.'};
}

// ── MAIN ──────────────────────────────────────────
async function startGenerate() {
    var topic    = document.getElementById('topic').value.trim();
    var days     = parseInt(document.getElementById('days').value);
    var level    = document.getElementById('level').value;
    var language = document.getElementById('language').value;

    if (!topic) { showErr('Topic enter karo!'); return; }

    // Settings reload karo agar nahi hai
    if (!AI_SETTINGS) {
        try {
            var r       = await fetch('../api/ai/get-settings.php');
            var rawText = await r.text();
            var d       = JSON.parse(rawText);
            if (!d.success || !d.settings) { showErr('Settings load nahi hui — page refresh karo.'); return; }
            AI_SETTINGS = d.settings;
        } catch(e) { showErr('Settings fetch error: '+e.message); return; }
    }

    var provider = (AI_SETTINGS.active_ai_provider || 'gemini').toLowerCase().trim();
    console.log('[GEN] Provider:', provider);
    console.log('[GEN] All settings keys:', Object.keys(AI_SETTINGS));

    // ✅ FIXED — sabhi providers support
    var apiKeys = buildKeys(provider, AI_SETTINGS);
    console.log('[GEN] API Keys found:', apiKeys.length, 'for provider:', provider);

    if (!apiKeys.length) {
        showErr('❌ '+provider.toUpperCase()+' API key set nahi hai! <a href="settings.php" style="color:#fff;text-decoration:underline">Settings mein jao →</a>');
        return;
    }

    var savedModel = AI_SETTINGS[provider+'_model'] || '';
    var models     = buildModels(provider, savedModel);
    console.log('[GEN] Models:', models);

    var btn = document.getElementById('genBtn');
    btn.disabled = true; btn.textContent = '⏳ Generating...';
    hideErr();
    document.getElementById('progressBox').style.display = 'block';
    document.getElementById('logBox').innerHTML = '';

    var batchSize  = parseInt(AI_SETTINGS.batch_size || '7');
    var totalBatch = Math.ceil(days / batchSize);
    var allSyllabus = [];

    log('🚀 Provider: '+provider.toUpperCase()+' | Days: '+days+' | Batches: '+totalBatch, 'log-ok');
    log('🔑 Keys: '+apiKeys.length+' | Model: '+models[0], 'log-info');

    try {
        for (var b = 0; b < totalBatch; b++) {
            var startDay = b * batchSize + 1;
            var endDay   = Math.min(startDay + batchSize - 1, days);

            updateProgress('Batch '+(b+1)+'/'+totalBatch+' — Day '+startDay+'–'+endDay+'...', allSyllabus.length, days);
            if (b > 0) await sleep(1500);

            var prompt = buildPrompt(topic, days, startDay, endDay, level, language, selectedType, includeQuiz);
            var result = await callWithFallback(provider, apiKeys, models, prompt, b+1);

            if (!result.success) {
                showErr('Batch '+(b+1)+' fail: '+result.error);
                resetBtn(); return;
            }

            allSyllabus = allSyllabus.concat(result.data);
            updateProgress('✅ Batch '+(b+1)+'/'+totalBatch+' done!', allSyllabus.length, days);
        }

        // ── ENFORCE quiz ONLY on every 7th day (deterministic, AI-proof) ──
        // Chahe AI kuch bhi return kare, final faisla yahi code karega:
        // quiz sirf day 7,14,21,28... pe. Baaki har din pure content.
        allSyllabus.forEach(function(d) {
            d.day      = parseInt(d.day, 10);
            d.has_quiz = includeQuiz && (d.day % 7 === 0);
        });

        // History save
        updateProgress('💾 Saving...', days, days);
        var historyId = 0;
        try {
            var hRes  = await fetch('../api/ai/save-history.php', {
                method:'POST', headers:{'Content-Type':'application/json'},
                body: JSON.stringify({topic:topic,total_days:days,level:level,language:language,type:selectedType,provider:provider,model:models[0]})
            });
            var hRaw  = await hRes.text();
            var hData = JSON.parse(hRaw);
            if (hData.success) historyId = hData.history_id;
        } catch(e) { console.warn('History save failed:', e); }

        // sessionStorage mein save karo
        sessionStorage.setItem('ai_syllabus', JSON.stringify(allSyllabus));
        sessionStorage.setItem('ai_meta', JSON.stringify({
            topic:topic, days:days, level:level, language:language,
            type:selectedType, include_quiz:includeQuiz,
            history_id:historyId, provider:provider,
            model:models[0], batch_size:batchSize
        }));

        updateProgress('🎉 Done! Redirecting...', days, days);
        log('🎉 Syllabus ready! Building page pe ja raha hoon...', 'log-ok');
        await sleep(800);
        window.location.href = 'building.php';

    } catch(e) {
        showErr('Unexpected error: '+e.message);
        console.error(e); resetBtn();
    }
}
</script>
</body>
</html>