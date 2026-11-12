/**
 * ที่พักข้อความ DOT ของคลินิกวัณโรค — Google Apps Script
 * ------------------------------------------------------------
 * ทำไมต้องมี: เครื่องคลินิกเปิด จ.-ศ. 8.30-16.30 และต้อง login captive portal ก่อนออกเน็ต
 * เวลาเครื่องปิด LINE ยิง webhook มาไม่ถึง และ LINE ไม่เก็บย้อนหลังให้ข้ามวัน
 * คลิป DOT ที่คนไข้ส่งเสาร์-อาทิตย์จึงหายถาวร
 *
 * สคริปต์นี้รับ webhook แทน 24 ชม. → โหลดไฟล์จาก LINE ทันที (LINE เก็บไฟล์ให้ช่วงสั้น ๆ เท่านั้น)
 * → เก็บลง Google Drive ของคลินิกวัณโรค → รอเครื่องคลินิกมาดึงแล้วสั่งลบทิ้ง
 *
 * วิธีติดตั้ง
 *   1. drive.google.com (บัญชีของคลินิกวัณโรค) → สร้างโฟลเดอร์ เช่น "TB DOT inbox" → คัดลอกรหัสโฟลเดอร์จาก URL
 *   2. script.google.com → New project → วางไฟล์นี้ทั้งไฟล์
 *   3. Project Settings → Script properties เพิ่ม 3 ค่า:
 *        LINE_CHANNEL_ACCESS_TOKEN = token ของ OA (ตัวเดียวกับที่คลินิกใช้อยู่)
 *        DRIVE_FOLDER_ID           = รหัสโฟลเดอร์จากข้อ 1
 *        RELAY_SECRET              = รหัสลับที่ตั้งเอง ต้องตรงกับ TB_DOT_RELAY_SECRET ใน .env ของเครื่องคลินิก
 *   4. Deploy → New deployment → Web app
 *        Execute as: Me · Who has access: Anyone
 *        คัดลอก URL ที่ได้ไปใส่ใน .env ของเครื่องคลินิก (TB_DOT_RELAY_URL)
 *        และไปตั้งเป็น Webhook URL ใน LINE Developers Console
 *   5. ทดสอบ: ส่งคลิปเข้า OA แล้วเปิดดูโฟลเดอร์ใน Drive ว่ามีไฟล์เข้ามาไหม
 *
 * ข้อควรรู้
 *   - ข้อความที่ไม่ใช่รูป/วิดีโอ (เช่น รหัสผูก 6 หลัก) ถูกพักไว้เหมือนกัน เครื่องคลินิกมาดึงไปทำต่อ
 *   - ผู้ป่วยได้ข้อความตอบกลับทันทีด้วย reply token ซึ่งไม่กินโควตาข้อความของ OA
 *   - ไฟล์ใน Drive ถูกลบทิ้งเมื่อเครื่องคลินิกดึงสำเร็จ (และมีตัวเก็บกวาดไฟล์ค้างเกิน 14 วัน)
 */

var PROPS = PropertiesService.getScriptProperties();
var SHEET_NAME = 'inbox';

/* ============ 1) รับ webhook จาก LINE ============ */
function doPost(e) {
  try {
    var body = JSON.parse(e.postData.contents);
    (body.events || []).forEach(handleEvent_);
  } catch (err) {
    log_('doPost error: ' + err);
  }
  // ตอบ 200 เสมอ ไม่งั้น LINE จะ retry ซ้ำ ๆ
  return ContentService.createTextOutput('OK');
}

function handleEvent_(ev) {
  if (ev.type !== 'message') { return; }

  var msg = ev.message || {};
  var row = {
    id: msg.id,
    type: msg.type,
    userId: (ev.source || {}).userId || '',
    timestamp: ev.timestamp || Date.now(),
    text: msg.text || '',
    fileId: '',
    mime: '',
    size: 0,
  };

  if (msg.type === 'image' || msg.type === 'video') {
    var saved = saveContent_(msg.id, msg.type);
    if (saved) {
      row.fileId = saved.id;
      row.mime = saved.mime;
      row.size = saved.size;
    }
    reply_(ev.replyToken,
      'ได้รับคลิป/รูปการกินยาแล้วค่ะ ขอบคุณค่ะ 🙏\n'
      + 'เจ้าหน้าที่จะตรวจสอบในวันทำการ (จันทร์-ศุกร์ 8.30-16.30 น.)');
  }

  appendRow_(row);
}

/* โหลดไฟล์จาก LINE ทันที — LINE เก็บไฟล์ให้ช่วงสั้น ๆ ถ้าช้าไฟล์จะหาย */
function saveContent_(messageId, type) {
  try {
    var res = UrlFetchApp.fetch('https://api-data.line.me/v2/bot/message/' + messageId + '/content', {
      headers: { Authorization: 'Bearer ' + PROPS.getProperty('LINE_CHANNEL_ACCESS_TOKEN') },
      muteHttpExceptions: true,
    });
    if (res.getResponseCode() !== 200) {
      log_('content fetch ' + res.getResponseCode() + ' for ' + messageId);
      return null;
    }

    var blob = res.getBlob();
    var ext = type === 'image' ? 'jpg' : 'mp4';
    blob.setName(messageId + '.' + ext);

    var folder = DriveApp.getFolderById(PROPS.getProperty('DRIVE_FOLDER_ID'));
    var file = folder.createFile(blob);

    return { id: file.getId(), mime: blob.getContentType(), size: blob.getBytes().length };
  } catch (err) {
    log_('saveContent error: ' + err);
    return null;
  }
}

function reply_(replyToken, text) {
  if (!replyToken) { return; }
  try {
    UrlFetchApp.fetch('https://api.line.me/v2/bot/message/reply', {
      method: 'post',
      contentType: 'application/json',
      headers: { Authorization: 'Bearer ' + PROPS.getProperty('LINE_CHANNEL_ACCESS_TOKEN') },
      payload: JSON.stringify({ replyToken: replyToken, messages: [{ type: 'text', text: text }] }),
      muteHttpExceptions: true,
    });
  } catch (err) {
    log_('reply error: ' + err);
  }
}

/* ============ 2) ให้เครื่องคลินิกมาดึง ============ */
/**
 * GET ?secret=...&action=list            -> รายการที่ยังไม่ถูกดึง (สูงสุด 20 รายการ)
 * GET ?secret=...&action=file&id=<fileId> -> ไฟล์ (base64)
 * GET ?secret=...&action=done&ids=a,b,c   -> ทำเครื่องหมายว่าดึงแล้ว + ลบไฟล์ใน Drive
 */
function doGet(e) {
  var p = e.parameter || {};
  if (p.secret !== PROPS.getProperty('RELAY_SECRET')) {
    return json_({ ok: false, error: 'unauthorized' });
  }

  if (p.action === 'file') { return json_(readFile_(p.id)); }
  if (p.action === 'done') { return json_(markDone_((p.ids || '').split(','))); }

  return json_({ ok: true, items: pending_() });
}

function pending_() {
  var sh = sheet_();
  var values = sh.getDataRange().getValues();
  var out = [];

  for (var i = 1; i < values.length && out.length < 20; i++) {
    if (values[i][7]) { continue; }   // ดึงไปแล้ว
    out.push({
      row: i + 1,
      messageId: values[i][0],
      type: values[i][1],
      userId: values[i][2],
      timestamp: values[i][3],
      text: values[i][4],
      fileId: values[i][5],
      mime: values[i][6],
    });
  }
  return out;
}

function readFile_(fileId) {
  if (!fileId) { return { ok: false, error: 'no file id' }; }
  try {
    var file = DriveApp.getFileById(fileId);
    return {
      ok: true,
      mime: file.getMimeType(),
      size: file.getSize(),
      base64: Utilities.base64Encode(file.getBlob().getBytes()),
    };
  } catch (err) {
    return { ok: false, error: String(err) };
  }
}

function markDone_(ids) {
  var sh = sheet_();
  var values = sh.getDataRange().getValues();
  var done = 0;

  for (var i = 1; i < values.length; i++) {
    if (ids.indexOf(String(values[i][0])) === -1) { continue; }

    sh.getRange(i + 1, 8).setValue(new Date());      // คอลัมน์ pulled_at
    if (values[i][5]) { trashFile_(values[i][5]); }  // ดึงสำเร็จแล้วลบไฟล์ใน Drive ทันที
    done++;
  }
  return { ok: true, done: done };
}

function trashFile_(fileId) {
  try { DriveApp.getFileById(fileId).setTrashed(true); } catch (err) { log_('trash error: ' + err); }
}

/* ============ 3) ตัวช่วย ============ */
function sheet_() {
  var ss = SpreadsheetApp.openById(PROPS.getProperty('SHEET_ID') || createSheet_());
  var sh = ss.getSheetByName(SHEET_NAME);
  if (!sh) {
    sh = ss.insertSheet(SHEET_NAME);
    sh.appendRow(['message_id', 'type', 'user_id', 'timestamp', 'text', 'file_id', 'mime', 'pulled_at']);
  }
  return sh;
}

function createSheet_() {
  var ss = SpreadsheetApp.create('TB DOT inbox');
  PROPS.setProperty('SHEET_ID', ss.getId());
  return ss.getId();
}

function appendRow_(r) {
  sheet_().appendRow([r.id, r.type, r.userId, r.timestamp, r.text, r.fileId, r.mime, '']);
}

function json_(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

function log_(msg) {
  console.log(msg);
}

/* ============ 4) เก็บกวาดไฟล์ค้าง ============
 * ตั้ง Trigger รายวันให้ฟังก์ชันนี้ กันไฟล์ค้างใน Drive กรณีเครื่องคลินิกไม่ได้ดึงนาน ๆ
 */
function cleanupOldFiles() {
  var sh = sheet_();
  var values = sh.getDataRange().getValues();
  var limit = Date.now() - 14 * 24 * 60 * 60 * 1000;   // 14 วัน

  for (var i = 1; i < values.length; i++) {
    if (!values[i][5] || values[i][7]) { continue; }
    if (Number(values[i][3]) > limit) { continue; }

    trashFile_(values[i][5]);
    sh.getRange(i + 1, 8).setValue('expired');
  }
}
