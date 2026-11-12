/**
 * ที่พักรหัสผูก LINE จากฟอร์มใน LINE (LIFF) — Google Apps Script (2569-10-22)
 * ------------------------------------------------------------
 * ทำไมต้องมี: server คลินิกอยู่วงในของกรม มือถือคนไข้ยิงเข้าไปไม่ถึง
 * ฟอร์มจึงส่งมาพักที่นี่ แล้ว server ออกมาดึงเอง (php artisan line:pull-links ทุกนาที)
 *
 * รหัส 6 หลักที่คนไข้กรอกถูกเข้ารหัสในมือถือก่อนส่ง — สคริปต์นี้และ Google อ่านไม่ออก
 * ที่นี่เห็นแค่ LINE userId ที่ตรวจกับ LINE แล้วว่าเป็นของคนส่งจริง (กันคนปลอมตัวเป็นบัญชีอื่น)
 *
 * เก็บใน CacheService ไม่ใช้ Google Sheet (เปลี่ยน 2569-10-22 — ผู้ดูแลกังวลข้อมูลค้าง)
 *   - หมดอายุเองใน 15 นาที (รหัสจากเจ้าหน้าที่ใช้ได้ 10 นาที เก็บนานกว่านั้นก็ใช้ไม่ได้อยู่ดี)
 *   - ไม่ลงไฟล์ใน Drive ไม่มีประวัติเวอร์ชันแบบ Sheet · server ดึงแล้วลบทันที
 *
 * วิธีติดตั้ง / อัปเดต
 *   1. script.google.com (บัญชีของคลินิก) → วางไฟล์นี้ทั้งไฟล์แทนของเดิม
 *   2. Project Settings → Script properties:
 *        LOGIN_CHANNEL_ID = Channel ID ของ LINE Login channel ที่สร้าง LIFF
 *        RELAY_SECRET     = รหัสลับ ต้องตรงกับ LINE_LINK_RELAY_SECRET ใน .env ของ server
 *        (SHEET_ID ของรุ่นเก่า ลบทิ้งได้)
 *   3. Deploy → Manage deployments → ดินสอ → Version: New version → Deploy (URL เดิม ไม่ต้องแก้ .env)
 *   4. Triggers: ลบ trigger "cleanupOld" ของรุ่นเก่า (ไม่ต้องใช้แล้ว)
 *   5. (เพิ่ม 2569-10-31) ตั้งเป็น Webhook ของ LINE ด้วย — ที่อยู่เดียวกัน (…/exec)
 *      LINE Developers → Messaging API → Webhook URL = ที่อยู่ Deploy นี้ · Use webhook = เปิด
 *      ปุ่ม Verify อาจขึ้นว่าไม่ใช่ 200 (Apps Script ตอบแบบ redirect) แต่ข้อความยังมาถึงปกติ
 *
 * ทำไมรับ webhook ที่นี่: server ย้ายเข้าวงในของกรม IT เปิดทางเข้าให้ไม่ได้ (2569-10-31)
 * LINE ส่งเหตุการณ์ (เพิ่มเพื่อน/บล็อก/พิมพ์ข้อความ) มาพักไว้ แล้ว server ดึงไปเอง (line:pull-webhook ทุกนาที)
 * Apps Script อ่าน header ลายเซ็นของ LINE ไม่ได้ — server จึงตรวจกับ LINE เองทุกเหตุการณ์ก่อนทำอะไร
 * ที่นี่ไม่ตอบคนไข้เอง (ไม่ต้องเก็บ token ของ LINE ไว้ใน Google) — server ตอบเมื่อดึงไปแล้ว
 */

var PROPS = PropertiesService.getScriptProperties();
var TTL = 900;          // วินาที — 15 นาที
var IDX = 'idx';        // รายการ id ฟอร์มที่รออยู่
var WIDX = 'widx';      // รายการ id เหตุการณ์ LINE ที่รออยู่
var WMAX = 500;         // เก็บค้างได้ไม่เกินนี้ (กันโดนยิงขยะจนเต็ม)

/* ============ 1) รับจากหน้าฟอร์ม / จาก LINE ============ */
// หน้าฟอร์มส่งเป็น text/plain (ไม่ให้เบราว์เซอร์ยิง preflight ที่ Apps Script ตอบไม่ได้)
function doPost(e) {
  try {
    var body = JSON.parse(e.postData.contents);
    // LINE webhook: มี destination + events — แยกไปเก็บอีกกอง
    if (body && typeof body.destination === 'string' && Object.prototype.toString.call(body.events) === '[object Array]') {
      return json_(keepWebhook_(body));
    }
    var box = body.box || {};
    if (!box.k || !box.iv || !box.d || String(box.d).length > 20000) {
      return json_({ ok: false, error: 'bad payload' });
    }

    var userId = verifyIdToken_(body.idToken);
    if (!userId) { return json_({ ok: false, error: 'verify' }); }

    var cache = CacheService.getScriptCache();
    // กันกดรัว/สแปม: บัญชีละไม่เกิน 5 ครั้งต่อชั่วโมง
    var n = Number(cache.get('n_' + userId) || 0);
    if (n >= 5) { return json_({ ok: false, error: 'too_many' }); }
    cache.put('n_' + userId, String(n + 1), 3600);

    var id = Utilities.getUuid();
    withLock_(function () {
      cache.put('i_' + id, JSON.stringify({ id: id, ts: Date.now(), userId: userId, box: box }), TTL);
      var idx = readIdx_(cache);
      idx.push(id);
      cache.put(IDX, JSON.stringify(idx.slice(-200)), TTL);
    });
    return json_({ ok: true });
  } catch (err) {
    console.log('doPost error: ' + err);
    return json_({ ok: false, error: 'server' });
  }
}

/* ตรวจกับ LINE ว่า ID token ออกให้ช่องทางของเราจริง และยังไม่หมดอายุ → คืน userId */
function verifyIdToken_(idToken) {
  if (!idToken) { return null; }
  var res = UrlFetchApp.fetch('https://api.line.me/oauth2/v2.1/verify', {
    method: 'post',
    payload: { id_token: String(idToken), client_id: PROPS.getProperty('LOGIN_CHANNEL_ID') },
    muteHttpExceptions: true,
  });
  if (res.getResponseCode() !== 200) { return null; }
  var data = JSON.parse(res.getContentText());
  return data.sub || null;
}

/* เก็บเหตุการณ์จาก LINE — เอาเฉพาะช่องที่ server ใช้ ข้อความตัดที่ 500 ตัวอักษร */
function keepWebhook_(body) {
  var events = body.events.slice(0, 50);
  if (!events.length) { return { ok: true, kept: 0 }; }   // ปุ่ม Verify ของ LINE ส่ง events ว่าง
  var cache = CacheService.getScriptCache();
  var kept = 0;
  withLock_(function () {
    var idx = readIdxKey_(cache, WIDX);
    // server ไม่ได้มาดึงนาน ๆ — ตัด id ที่หมดอายุไปเองออกก่อน ไม่งั้นรายการเต็มแล้วรับเหตุการณ์ใหม่ไม่ได้
    if (idx.length >= WMAX / 2) {
      var alive = cache.getAll(idx.map(function (id) { return 'w_' + id; }));
      idx = idx.filter(function (id) { return alive['w_' + id]; });
    }
    events.forEach(function (ev) {
      var src = ev && ev.source || {};
      if (src.type !== 'user' || !/^U[0-9a-f]{32}$/.test(String(src.userId || ''))) { return; }
      if (['follow', 'unfollow', 'message'].indexOf(ev.type) === -1) { return; }
      if (idx.length >= WMAX) { return; }
      var msg = ev.message || {};
      // บัญชีนี้มีแอดมินคุยกับคนไข้ (LINE_STAFFED=true) — ข้อความแชททั่วไประบบไม่ใช้อยู่แล้ว
      // เก็บเฉพาะข้อความที่เป็น "รหัส" (คนไข้ 6 หลัก / ผู้ดูแล 8 หลัก) แชทส่วนตัวของคนไข้จะไม่ถูกพักไว้ที่ Google เลย
      // ถ้าวันหน้าเปลี่ยนเป็นบัญชีแจ้งเตือนล้วน (บอทตอบทุกข้อความ) ตั้ง Script property KEEP_ALL_TEXT = 1
      if (ev.type === 'message') {
        var t = String(msg.text || '').replace(/[\s\-]+/g, '');
        var isCode = msg.type === 'text' && /^[#＃]?(\d{6}|\d{8})$/.test(t);
        if (!isCode && PROPS.getProperty('KEEP_ALL_TEXT') !== '1') { return; }
      }
      var slim = {
        type: ev.type,
        webhookEventId: String(ev.webhookEventId || '').slice(0, 64),
        timestamp: Number(ev.timestamp || 0),
        replyToken: String(ev.replyToken || '').slice(0, 64),
        source: { type: 'user', userId: src.userId },
        message: ev.type === 'message' ? { type: String(msg.type || ''), text: String(msg.text || '').slice(0, 500) } : undefined
      };
      var id = Utilities.getUuid();
      cache.put('w_' + id, JSON.stringify({ id: id, ts: Date.now(), destination: body.destination, event: slim }), TTL);
      idx.push(id);
      kept++;
    });
    if (idx.length) { cache.put(WIDX, JSON.stringify(idx), TTL); }
  });
  return { ok: true, kept: kept };
}

/* ============ 2) ให้ server มาดึง ============ */
/**
 * GET ?secret=...&action=list           -> คำขอจากฟอร์มที่รออยู่
 * GET ?secret=...&action=done&ids=a,b   -> ลบคำขอที่ดึงแล้ว
 * GET ?secret=...&action=wlist          -> เหตุการณ์ LINE ที่รออยู่
 * GET ?secret=...&action=wdone&ids=a,b  -> ลบเหตุการณ์ที่ดึงแล้ว
 */
function doGet(e) {
  var p = e.parameter || {};
  if (!PROPS.getProperty('RELAY_SECRET') || p.secret !== PROPS.getProperty('RELAY_SECRET')) {
    return json_({ ok: false, error: 'unauthorized' });
  }
  if (p.action === 'done') { return json_(markDone_((p.ids || '').split(','))); }
  if (p.action === 'wlist') { return json_({ ok: true, events: pendingKey_(WIDX, 'w_') }); }
  if (p.action === 'wdone') { return json_(markDoneKey_(WIDX, 'w_', (p.ids || '').split(','))); }
  return json_({ ok: true, items: pending_() });
}

function pendingKey_(idxKey, prefix) {
  var cache = CacheService.getScriptCache();
  var idx = readIdxKey_(cache, idxKey);
  if (!idx.length) { return []; }
  var got = cache.getAll(idx.map(function (id) { return prefix + id; }));
  var out = [];
  idx.forEach(function (id) {
    var raw = got[prefix + id];
    if (raw) { try { out.push(JSON.parse(raw)); } catch (err) {} }
  });
  return out;
}

function markDoneKey_(idxKey, prefix, ids) {
  var cache = CacheService.getScriptCache();
  ids = ids.filter(function (x) { return x; });
  cache.removeAll(ids.map(function (id) { return prefix + id; }));
  withLock_(function () {
    var idx = readIdxKey_(cache, idxKey);
    var alive = cache.getAll(idx.map(function (id) { return prefix + id; }));
    var left = idx.filter(function (id) { return ids.indexOf(id) === -1 && alive[prefix + id]; });
    if (left.length) { cache.put(idxKey, JSON.stringify(left), TTL); } else { cache.remove(idxKey); }
  });
  return { ok: true, done: ids.length };
}

function readIdxKey_(cache, key) {
  try { return JSON.parse(cache.get(key) || '[]'); } catch (err) { return []; }
}

function pending_() {
  var cache = CacheService.getScriptCache();
  var idx = readIdx_(cache);
  if (!idx.length) { return []; }
  var got = cache.getAll(idx.map(function (id) { return 'i_' + id; }));
  var out = [];
  idx.forEach(function (id) {
    var raw = got['i_' + id];
    if (raw) { try { out.push(JSON.parse(raw)); } catch (err) {} }
  });
  return out;
}

function markDone_(ids) {
  var cache = CacheService.getScriptCache();
  ids = ids.filter(function (x) { return x; });
  cache.removeAll(ids.map(function (id) { return 'i_' + id; }));
  withLock_(function () {
    // ตัด id ที่ลบแล้ว + id ที่หมดอายุไปเอง ออกจากรายการ
    var idx = readIdx_(cache);
    var alive = cache.getAll(idx.map(function (id) { return 'i_' + id; }));
    var left = idx.filter(function (id) { return ids.indexOf(id) === -1 && alive['i_' + id]; });
    if (left.length) { cache.put(IDX, JSON.stringify(left), TTL); } else { cache.remove(IDX); }
  });
  return { ok: true, done: ids.length };
}

/* ============ 3) ตัวช่วย ============ */
function readIdx_(cache) {
  try { return JSON.parse(cache.get(IDX) || '[]'); } catch (err) { return []; }
}

function withLock_(fn) {
  var lock = LockService.getScriptLock();
  lock.waitLock(10000);
  try { fn(); } finally { lock.releaseLock(); }
}

function json_(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

/* รุ่นเก่าใช้ trigger รายวันเรียกฟังก์ชันนี้ — เหลือไว้กันอีเมลแจ้ง error ถ้ายังไม่ได้ลบ trigger */
function cleanupOld() {}
