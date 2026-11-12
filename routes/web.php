<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Controllers
use App\Http\Controllers\HomeController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\LabSubController;
use App\Http\Controllers\LabController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\LabExportController;
use App\Http\Controllers\VisitFollowupController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\ContactPersonController;
use App\Http\Controllers\LabItemController;
use App\Http\Controllers\LabBloodController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\VisitMedicineController;
use App\Http\Controllers\DiagnosisController;
use App\Http\Controllers\R506Controller;
use App\Http\Controllers\ContactStiController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\RihisVctController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\CardReaderController;
use App\Exports\VisitExport;
use App\Http\Controllers\PharmacyController;
use App\Http\Controllers\FinanceController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('welcome');
});

Route::get('/queues', function () {
    return view('queues');
});

// หน้าสรุปพัฒนาการของระบบ — เปิดสาธารณะ ไม่ต้อง login (เป็นตัวเลขรวมล้วน ไม่มีชื่อ/HN คนไข้)
// ส่วนที่อ้างถึงคนไข้รายบุคคลถูกกันไว้ใน @auth ในไฟล์ roadmap.blade.php แล้ว
Route::get('/roadmap', [App\Http\Controllers\RoadmapController::class, 'index'])->name('roadmap');

// Webhook รับข้อความจาก LINE Official Account (ไม่ต้อง login เพราะ LINE เป็นคนเรียกเข้ามาเอง ตรวจสอบตัวตนด้วย signature แทน)
Route::post('/line/webhook', [App\Http\Controllers\LineWebhookController::class, 'handle'])->name('line.webhook');
// เส้นทางสำหรับระบบห้องยา/ห้องการเงิน + เวชระเบียน/สถิติ/ผลแล็บ (ต้อง login เท่านั้น เพราะเป็นข้อมูลผู้ป่วยที่อ่อนไหว)
// ห้องยา (จ่ายยา/ตัดสต๊อกเท่านั้น) กับห้องการเงิน (คิดเงิน/รับเงิน/พิมพ์ใบเสร็จ) แยกคุมคนละ controller ตั้งแต่ 2569-09-03
Route::middleware('auth')->group(function () {
    Route::get('/pharmacy', [PharmacyController::class, 'index'])->name('pharmacy.index');
    Route::get('/pharmacy/{visit_id}/edit', [PharmacyController::class, 'edit']);
    Route::post('/pharmacy/{id}/update', [PharmacyController::class, 'update']);
    Route::post('/pharmacy/{id}/status', [PharmacyController::class, 'updateStatus']);
    Route::post('/pharmacy/{id}/call-queue', [PharmacyController::class, 'callQueue'])->name('pharmacy.callQueue');

    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('/finance/{visit_id}/edit', [FinanceController::class, 'edit']);
    Route::post('/finance/{id}/update', [FinanceController::class, 'update']);
    Route::get('/finance/{id}/print', [FinanceController::class, 'print'])->name('finance.print');
    Route::post('/finance/{id}/log-print', [FinanceController::class, 'logPrint'])->name('finance.logPrint');
    Route::post('/finance/{id}/status', [FinanceController::class, 'updateStatus']);
    Route::post('/finance/{id}/call-queue', [FinanceController::class, 'callQueue'])->name('finance.callQueue');
    Route::get('/finance/history', [FinanceController::class, 'history'])->name('finance.history');

    Route::get('/visit/{id}/medical-certificate', [App\Http\Controllers\VisitController::class, 'medicalCertificate'])->name('visit.medicalCertificate');
    Route::get('/visit/{id}/medical-certificate-en', [App\Http\Controllers\VisitController::class, 'medicalCertificateEn'])->name('visit.medicalCertificateEn');
    Route::post('/visit/{id}/medical-certificate', [App\Http\Controllers\VisitController::class, 'storeMedicalCertificate'])->name('visit.medicalCertificate.store');
    Route::get('/visit/medical-certificate/{certificateId}/view', [App\Http\Controllers\VisitController::class, 'viewMedicalCertificate'])->name('visit.medicalCertificate.view');
    // ใบนัดหมาย (แบบราชการ)
    Route::get('/visit/{id}/appointment-card', [App\Http\Controllers\VisitController::class, 'appointmentCard'])->name('visit.appointmentCard');
    // แบบฟอร์มส่งผู้ป่วยรับการรักษาต่อ (ครึ่ง A4) — ใช้กับเคสส่งไปตรวจ CD4 / รักษาต่อ
    Route::get('/visit/{id}/refer-form', [App\Http\Controllers\VisitController::class, 'referForm'])->name('visit.referForm');
    // ใบสรุปรายการยากลับบ้าน/ส่งต่อสถานพยาบาลใกล้บ้าน (คลินิก TB ขอ 2569-10-06)
    Route::get('/visit/{id}/drug-summary', [App\Http\Controllers\DrugSummaryController::class, 'show'])->name('visit.drugSummary');
    // Autosave ช่องข้อความคลินิก (AJAX) ในหน้า visit/edit
    // สลับคลินิกที่กำลังทำงานอยู่ (STI / TB / TMC)
    Route::get('/clinic/switch/{clinic}', [App\Http\Controllers\ClinicContextController::class, 'switch'])->name('clinic.switch');

    Route::post('/visit/{id}/autosave', [App\Http\Controllers\VisitController::class, 'autosave'])->name('visit.autosave');
    // แผนนัดฉีดวัคซีน TMC บันทึกเองทันทีที่แก้ (2569-10-05)
    Route::post('/visit/{id}/vaccine-plan', [App\Http\Controllers\VisitController::class, 'saveVaccinePlan'])->name('visit.vaccinePlan');
    // Lab ส่งตรวจภายนอก (N Health ฯลฯ) — แยกจากงาน Lab ในบ้าน
    Route::post('/visit/{visit}/external-lab', [App\Http\Controllers\ExternalLabController::class, 'store'])->name('external-lab.store');
    Route::post('/visit/{visit}/external-lab/update', [App\Http\Controllers\ExternalLabController::class, 'update'])->name('external-lab.update');
    Route::delete('/visit/{visit}/external-lab/{id}', [App\Http\Controllers\ExternalLabController::class, 'destroy'])->name('external-lab.destroy');
    Route::get('/visit/{visit}/external-lab/print', [App\Http\Controllers\ExternalLabController::class, 'print'])->name('external-lab.print');

    // บันทึกการฉีดวัคซีน — คลินิก TMC
    Route::post('/visit/{visit}/vaccine', [App\Http\Controllers\VisitVaccineController::class, 'store'])->name('visit-vaccine.store');
    Route::delete('/visit/{visit}/vaccine/{id}', [App\Http\Controllers\VisitVaccineController::class, 'destroy'])->name('visit-vaccine.destroy');
    Route::post('/visit/{visit}/vaccine/{id}/update', [App\Http\Controllers\VisitVaccineController::class, 'update'])->name('visit-vaccine.update');
    Route::get('/visit/{visit}/vaccine/certificate', [App\Http\Controllers\VisitVaccineController::class, 'certificate'])->name('visit-vaccine.certificate');
    Route::get('/visit/{visit}/vaccine/certificate-word', [App\Http\Controllers\VisitVaccineController::class, 'certificateDocx'])->name('visit-vaccine.certificateDocx');
    // ไฟล์แนบแผนการเดินทาง TMC (ตั๋ว/กำหนดการ/จดหมายเชิญ) — อัปโหลดด้วย fetch เพราะกล่องอยู่ในฟอร์มหลักของหน้าตรวจ (2569-10-07)
    Route::post('/visit/{visit}/travel-files', [App\Http\Controllers\TmcTravelFileController::class, 'store'])->name('tmc.travelFile.store');
    Route::get('/visit/{visit}/travel-files/{fileId}', [App\Http\Controllers\TmcTravelFileController::class, 'show'])->name('tmc.travelFile.show');
    Route::delete('/visit/{visit}/travel-files/{fileId}', [App\Http\Controllers\TmcTravelFileController::class, 'destroy'])->name('tmc.travelFile.destroy');
    // ไฟล์ผลตรวจจากที่อื่น ในหน้าตรวจทุกคลินิก (ใช้ตารางเดียวกับบัตร TB)
    Route::post('/visit/{visit}/lab-files', [App\Http\Controllers\VisitLabFileController::class, 'store'])->name('visit.labFile.store');
    Route::get('/visit/{visit}/lab-files/{fileId}', [App\Http\Controllers\VisitLabFileController::class, 'show'])->name('visit.labFile.show');
    Route::delete('/visit/{visit}/lab-files/{fileId}', [App\Http\Controllers\VisitLabFileController::class, 'destroy'])->name('visit.labFile.destroy');
    Route::post('/visit/{visit}/lab-files/{fileId}/update', [App\Http\Controllers\VisitLabFileController::class, 'update'])->name('visit.labFile.update');

    // ประวัติย้อนหลังของช่องข้อความคลินิก (popup + ปุ่มดึงข้อความเดิมมาใช้)
    Route::get('/visit/{id}/field-history/{field}', [App\Http\Controllers\VisitController::class, 'fieldHistory'])->name('visit.fieldHistory');
    // Dashboard สถิติใหม่
    Route::get('/stats', [App\Http\Controllers\VisitController::class, 'stats'])->name('stats.index');
    Route::get('/visit/{visit}/print', [App\Http\Controllers\VisitController::class, 'print'])->name('visit.print');
});

// ปิดหน้าสมัครสมาชิกเอง + ลืมรหัสผ่านทางอีเมล (2569-10-20) — เดิมใครก็สมัครบัญชีเองได้ แล้วเปิดเวชระเบียนได้ทันที
// บัญชีใหม่ให้ Admin สร้างที่ เมนูผู้ดูแล → ผู้ใช้ · ลืมรหัสผ่าน: Admin ตั้งรหัสใหม่ให้ (ระบบไม่ได้ต่ออีเมลจริงอยู่แล้ว)
Auth::routes(['register' => false, 'reset' => false]);

Route::get('/home', [HomeController::class, 'index'])->name('home');

// แดชบอร์ดสถิติรายปี — เป็นตัวเลขรวมล้วน ไม่มีชื่อ/HN คนไข้ จึงเปิดให้ฉายในห้องประชุมด้วย "ลิงก์ลับ" ได้
// เข้าได้ 2 ทาง: login ตามปกติ หรือแนบ ?key=<token ใน configs.public_report_token>
// ลิงก์หลุดเมื่อไหร่ให้เปลี่ยนค่า public_report_token ลิงก์เก่าจะตายทันที
Route::middleware('report.link')
    ->get('/report/yearly-dashboard', [ReportController::class, 'yearlyDashboard'])
    ->name('report.yearly');

// ประกาศความเป็นส่วนตัว (PDPA) — ไม่มีข้อมูลคนไข้ เปิดอ่านได้โดยไม่ต้อง login (คนไข้อ่านจากจอ/มือถือ)
Route::get('/privacy', [App\Http\Controllers\PatientConsentController::class, 'notice'])->name('privacy.notice');
Route::view('/privacy/poster', 'privacy.poster')->name('privacy.poster');   // ป้ายประกาศติดหน้าคลินิก (A4)

// =========================================================================
// Backend / Admin Routes
// =========================================================================
Route::prefix('backend')->middleware('auth')->group(function () {
    Route::get('/', function () {
        if(!Auth::user()->hasRole('Admin')){
            return redirect('/');
        }
        return view('backend.welcome');
    });

    Route::resource('users', App\Http\Controllers\Backend\UserController::class);
    Route::resource('roles', App\Http\Controllers\Backend\RoleController::class);
    Route::resource('config', App\Http\Controllers\Backend\ConfigController::class);
});
// =========================================================================
// Authenticated User Routes
// =========================================================================
Route::middleware('auth')->group(function () {
    Route::get('/export-lab-list', [App\Http\Controllers\ReportController::class, 'exportLabList'])->name('export.lab.list');
    // Excel รายชื่อ + วินิจฉัย + สัญชาติ (ตารางสรุป) จากแดชบอร์ดรายปี — มีชื่อคนไข้ ต้อง login (2569-10-30)
    Route::get('/report/export-visit-diagnosis', [App\Http\Controllers\ReportController::class, 'exportVisitDiagnosis'])->name('export.visit.diagnosis');
    // ค้น ICD-10-TM ชุดเต็มตอนพิมพ์ในตัวเลือกรหัสโรค (2569-10-30)
    Route::get('/icd10/search', [App\Http\Controllers\Icd10Controller::class, 'search'])->name('icd10.search');
    Route::post('/visit/{id}/update-queue', [App\Http\Controllers\VisitController::class, 'updateQueue'])->name('visit.updateQueue');
    Route::post('/visit/{id}/receive', [VisitController::class, 'receivePatient'])->name('visit.receive');
    // --- System / Utility ---
    Route::post('/mode', function (Request $request) {
        $as = (string) $request->input('as', '');
        $allowed = ['registration','nurse','doctor','lab','pharmacy'];
        if (!in_array($as, $allowed, true)) {
            return back()->with('error', 'โหมดไม่ถูกต้อง');
        }
        session(['acting_stage' => $as]);
        return back();
    })->name('mode.set');

    Route::get('/read-card', [CardReaderController::class, 'readCard']);

    // --- Queue ---
    Route::post('/queue', [QueueController::class, 'createQueue']);
    Route::get('/queues-data', [QueueController::class, 'getQueues']); // เปลี่ยนชื่อ route เพื่อไม่ให้ชนกับ view

    // --- ปุ่มลัดเข้าห้อง (ไว้ติดตั้งที่เครื่องลูกข่าย) ---
    Route::get('/rooms', [App\Http\Controllers\RoomShortcutController::class, 'index'])->name('rooms.index');
    Route::get('/rooms/shortcut/{key}', [App\Http\Controllers\RoomShortcutController::class, 'download'])->name('rooms.shortcut');

    // --- ทะเบียนวัณโรค TB 01 (คลินิก TB) ---
    // แยกจากหน้าตรวจ เพราะข้อมูลเป็นของ "คอร์สการรักษา" ที่ยาว 6 เดือน–2 ปี ไม่ใช่ของการมาครั้งเดียว
    Route::get('/tb', [App\Http\Controllers\TbController::class, 'index'])->name('tb.index');
    // เกณฑ์ขนาดยาตามน้ำหนัก (ผู้ดูแลระบบแก้เองได้ ไม่ต้องแก้โค้ด) — ต้องมาก่อน /tb/{id} ไม่งั้นโดนจับเป็น id
    // หน้ารวมคนไข้ DOT รายวัน — ต้องมาก่อน /tb/{id} ไม่งั้น dot-board จะถูกจับเป็น id
    Route::get('/tb/dot-board', [App\Http\Controllers\TbController::class, 'dotBoard'])->name('tb.dotBoard');
    // รายงานประจำเดือนคลินิกวัณโรค (2569-09-30) — ต้องมาก่อน /tb/{id}
    Route::get('/tb/report', [App\Http\Controllers\TbController::class, 'monthlyReport'])->name('tb.report');
    // วันที่ผิดรูปแบบในบัตร TB 01 ให้พยาบาลไล่แก้ (2569-10-05) — ต้องมาก่อน /tb/{id}
    Route::get('/tb/date-check', [App\Http\Controllers\TbDateCheckController::class, 'index'])->name('tb.dateCheck');
    Route::post('/tb/dot-board', [App\Http\Controllers\TbController::class, 'saveDotBoard'])->name('tb.dotBoard.save');
    // หมายเหตุรายวันบันทึกเองทันทีที่พิมพ์ (พยาบาลไม่มั่นใจว่าต้องกดบันทึกไหม + ออกจากหน้าแล้วหาย — 2569-09-30)
    Route::post('/tb/dot-board/note', [App\Http\Controllers\TbController::class, 'saveDotNote'])->name('tb.dotBoard.note');
    Route::post('/tb/dot-board/mark', [App\Http\Controllers\TbController::class, 'saveDotMark'])->name('tb.dotBoard.mark');
    // ส่งเวรรายคนไข้ — ค้างจนกว่าจะติ๊กสิ้นสุด (2569-09-30)
    Route::post('/tb/dot-handover', [App\Http\Controllers\TbController::class, 'addDotHandover'])->name('tb.dotHandover.add');
    Route::post('/tb/dot-handover/{id}/resolve', [App\Http\Controllers\TbController::class, 'resolveDotHandover'])->name('tb.dotHandover.resolve');
    Route::post('/tb/dot-handover/{id}/update', [App\Http\Controllers\TbController::class, 'updateDotHandover'])->name('tb.dotHandover.update');
    Route::get('/tb/dot-media/{id}', [App\Http\Controllers\TbController::class, 'dotMedia'])->name('tb.dotMedia');
    Route::get('/tb/{id}/xray-request', [App\Http\Controllers\TbController::class, 'xrayRequest'])->name('tb.xrayRequest');
    Route::get('/tb/{id}/labels', [App\Http\Controllers\TbController::class, 'labels'])->name('tb.labels');
    Route::get('/tb/{id}/med-sheet', [App\Http\Controllers\TbController::class, 'medSheet'])->name('tb.medSheet');
    Route::get('/tb/dose-rules', [App\Http\Controllers\TbController::class, 'doseRules'])->name('tb.doseRules');
    Route::post('/tb/dose-rules', [App\Http\Controllers\TbController::class, 'saveDoseRules'])->name('tb.doseRules.save');
    Route::get('/tb/create', [App\Http\Controllers\TbController::class, 'create'])->name('tb.create');
    Route::post('/tb', [App\Http\Controllers\TbController::class, 'store'])->name('tb.store');
    Route::get('/tb/{id}', [App\Http\Controllers\TbController::class, 'show'])->name('tb.show');
    Route::put('/tb/{id}', [App\Http\Controllers\TbController::class, 'update'])->name('tb.update');
    Route::post('/tb/{id}/sputum', [App\Http\Controllers\TbController::class, 'saveSputum'])->name('tb.sputum');
    Route::get('/tb/family-tree/{visit}', [App\Http\Controllers\TbController::class, 'familyTree'])->name('tb.familyTree');
    Route::post('/tb/{id}/xray', [App\Http\Controllers\TbController::class, 'saveXrays'])->name('tb.xray');
    Route::post('/tb/{id}/dose', [App\Http\Controllers\TbController::class, 'saveDoses'])->name('tb.dose');
    Route::post('/tb/{id}/dst', [App\Http\Controllers\TbController::class, 'saveDst'])->name('tb.dst');
    // สั่ง/ลงผลส่งตรวจทางห้องปฏิบัติการของ visit วันนี้จากบัตร TB 01 (2569-10-02) — สิทธิ์เท่าหน้าตรวจ (auth)
    Route::post('/tb/{id}/lab-order', [App\Http\Controllers\TbController::class, 'saveLabOrder'])->name('tb.labOrder');
    Route::get('/tb/{id}/lab-order/summary', [App\Http\Controllers\TbController::class, 'labOrderSummary'])->name('tb.labOrder.summary');
    Route::get('/tb/{id}/lab-order/{part}', [App\Http\Controllers\TbController::class, 'labOrderPart'])
        ->where('part', 'form|external|inhouse')->name('tb.labOrder.part');
    Route::post('/tb/{id}/regimen', [App\Http\Controllers\TbController::class, 'saveRegimen'])->name('tb.regimen');
    // วิธีใช้ยาแต่ละตัว (Sig) — แยกจากสูตรยา เพราะแก้วิธีใช้ต้องไม่กลายเป็นการเปลี่ยนสูตร (2569-09-29)
    Route::post('/tb/{id}/sig', [App\Http\Controllers\TbController::class, 'saveSigs'])->name('tb.sig');
    // กล่อง "สั่งยาครั้งนี้" — สูตร/ระยะ + รอบสั่งยา + วิธีใช้ ในการกดบันทึกครั้งเดียว (2569-10-02)
    Route::post('/tb/{id}/order', [App\Http\Controllers\TbController::class, 'saveOrder'])->name('tb.order');
    // กล่องเดียวกันในหน้าตรวจ OPD — ลงรายการยาของ visit (+ บัตร TB 01 ถ้ามี) ตอบ JSON (2569-10-05)
    Route::post('/tb/visit-order/{visit}', [App\Http\Controllers\TbController::class, 'saveVisitOrder'])->name('tb.visitOrder');
    // เปิด/ปิด DOT — ทำจากหน้าตรวจเท่านั้น (คลินิกขอ 2569-09-30 เอาออกจากหน้าติดตาม DOT)
    Route::post('/tb/{id}/dot-status', [App\Http\Controllers\TbController::class, 'saveDotStatus'])->name('tb.dotStatus');
    Route::post('/tb/{id}/post-followup', [App\Http\Controllers\TbController::class, 'bookPostFollowup'])->name('tb.postFollowup');
    Route::post('/tb/{id}/discharge', [App\Http\Controllers\TbController::class, 'dischargeFromVisit'])->name('tb.discharge');
    Route::delete('/tb/{id}/regimen/{changeId}', [App\Http\Controllers\TbController::class, 'deleteRegimen'])->name('tb.regimen.delete');
    Route::put('/tb/{id}/regimen/{changeId}', [App\Http\Controllers\TbController::class, 'updateRegimen'])->name('tb.regimen.update');
    Route::post('/tb/{id}/dot', [App\Http\Controllers\TbController::class, 'saveDot'])->name('tb.dot');
    Route::delete('/tb/{id}', [App\Http\Controllers\TbController::class, 'destroy'])->name('tb.destroy');
    // แท็บ "ติดตาม" ของบัตร TB 01 — ตารางติดตามการรักษา / ตรวจสายตา / คัดกรองซึมเศร้า (2569-10-05)
    Route::get('/tb/{id}/monitor/html', [App\Http\Controllers\TbMonitorController::class, 'html'])->name('tb.monitor.html');
    Route::post('/tb/{id}/monitor/check', [App\Http\Controllers\TbMonitorController::class, 'check'])->name('tb.monitor.check');
    // ตารางติดตามที่แพทย์กำหนดเอง (สูตร Individualized) — 2569-10-22
    Route::post('/tb/{id}/monitor/plan', [App\Http\Controllers\TbMonitorController::class, 'savePlan'])->name('tb.monitor.plan');
    Route::delete('/tb/{id}/monitor/plan', [App\Http\Controllers\TbMonitorController::class, 'resetPlan'])->name('tb.monitor.plan.reset');
    Route::delete('/tb/{id}/monitor/check', [App\Http\Controllers\TbMonitorController::class, 'uncheck'])->name('tb.monitor.uncheck');
    Route::post('/tb/{id}/monitor/vision', [App\Http\Controllers\TbMonitorController::class, 'vision'])->name('tb.monitor.vision');
    Route::delete('/tb/{id}/monitor/vision/{rowId}', [App\Http\Controllers\TbMonitorController::class, 'deleteVision'])->name('tb.monitor.vision.delete');
    Route::post('/tb/{id}/monitor/depression', [App\Http\Controllers\TbMonitorController::class, 'depression'])->name('tb.monitor.depression');
    Route::delete('/tb/{id}/monitor/depression/{rowId}', [App\Http\Controllers\TbMonitorController::class, 'deleteDepression'])->name('tb.monitor.depression.delete');
    // ไฟล์ผลตรวจจากที่อื่น (สแกน/รูป/PDF) — เก็บเข้ารหัสใน storage/app/private เปิดดูผ่าน route นี้เท่านั้น (2569-10-05)
    Route::post('/tb/{id}/lab-files', [App\Http\Controllers\TbLabFileController::class, 'store'])->name('tb.labFile.store');
    Route::get('/tb/{id}/lab-files/{fileId}', [App\Http\Controllers\TbLabFileController::class, 'show'])->name('tb.labFile.show');
    Route::delete('/tb/{id}/lab-files/{fileId}', [App\Http\Controllers\TbLabFileController::class, 'destroy'])->name('tb.labFile.destroy');
    Route::post('/tb/{id}/lab-files/{fileId}/update', [App\Http\Controllers\TbLabFileController::class, 'update'])->name('tb.labFile.update');

    // ผล HIV ในบัตร TB 01 / หน้าตรวจ TB ถูกปิดไว้ — กดดูทีละครั้งแล้วบันทึกว่าใครเปิดดูเมื่อไร (2569-10-06)
    // ผลไม่อยู่ใน HTML ของหน้าเลย ต้องขอผ่าน POST นี้ (มี CSRF) · พิมพ์ผล / ใบ refer ก็ผ่านที่นี่เพื่อให้มีบันทึก
    Route::post('/patient/{id}/hiv-reveal', [App\Http\Controllers\HivRevealController::class, 'reveal'])->name('hiv.reveal');
    Route::get('/hiv-result/{sub}/print', [App\Http\Controllers\HivRevealController::class, 'printResult'])->name('hiv.print');
    Route::get('/hiv-result/refer/{visit}', [App\Http\Controllers\HivRevealController::class, 'refer'])->name('hiv.refer');

    // --- Report / Dashboard ---
    Route::get('/report/dashboard', [ReportController::class, 'dashboard'])->name('report.dashboard');
    // ดึงรายชื่อคนไข้เองจากตัวกรองสำเร็จรูป (แอดมิน/พยาบาล/หมอ) — เป็นข้อมูลคนไข้ จึงจำกัดสิทธิ์
    Route::middleware('role:Admin|Nurse|Doctor')->group(function () {
        Route::get('/report/explore', [App\Http\Controllers\ReportExploreController::class, 'index'])->name('report.explore');
        Route::get('/report/explore/export', [App\Http\Controllers\ReportExploreController::class, 'export'])->name('report.explore.export');
        // รายงาน TMC รายเดือน — วัคซีนนับจากบันทึกการฉีด (2569-11-03) · มีรายชื่อคนไข้ จึงอยู่ในกลุ่มจำกัดสิทธิ์
        Route::get('/report/tmc-monthly', [App\Http\Controllers\TmcReportController::class, 'index'])->name('report.tmc');
        // แบบรายงานส่ง ผอ. รวมทั้งปีงบประมาณ (แยกรายเดือน + รวม) — 2569-11-04
        Route::get('/report/tmc-yearly', [App\Http\Controllers\TmcReportController::class, 'year'])->name('report.tmc.year');
        // รายงาน ก.1 คลินิก STI รายเดือน — นับให้ + แก้ทับได้ทุกช่อง (2569-11-10)
        Route::get('/report/sti-k1', [App\Http\Controllers\StiK1ReportController::class, 'index'])->name('report.sti-k1');
        Route::post('/report/sti-k1/save', [App\Http\Controllers\StiK1ReportController::class, 'save'])->name('report.sti-k1.save');
    });
    // หน้าคุมลิงก์ลับของแดชบอร์ด (เปิดก่อนประชุม ปิดหลังประชุม)
    Route::get('/report/share', [App\Http\Controllers\ReportShareController::class, 'index'])->name('report.share');
    Route::post('/report/share', [App\Http\Controllers\ReportShareController::class, 'update'])->name('report.share.update');

    // --- Patient ---
    Route::get('/patient/photo/{id}', [PatientController::class, 'showPhoto'])->name('patient.showPhoto');
    Route::delete('patient/{id}/delete-photo', [PatientController::class, 'deletePhoto'])->name('patient.deletePhoto');
    Route::post('/check-id-card/{id?}', [PatientController::class, 'checkIdCard'])->name('check.id_card');
    Route::get('/get-patient-details/{id}', [PatientController::class, 'getPatientDetails']);
    Route::post('/patient/{id}/line-link-code', [PatientController::class, 'generateLineLinkCode'])->name('patient.lineLinkCode');
    Route::put('/patient/{id}/update-phone-changed', [PatientController::class, 'updatePhoneChanged'])->name('patient.updatePhoneChanged');
    Route::post('/patient/create-visit', [PatientController::class, 'createVisit'])->name('patient.createVisit');
    Route::get('/search/countries', [PatientController::class, 'searchCountries'])->name('search.countries');
    Route::resource('patient', PatientController::class);

    // --- Visit (จัดลำดับสำคัญมาก: Specific ก่อน Resource) ---
    // 1. Export (ต้องอยู่ก่อน resource)
    Route::get('/visit/export', [VisitController::class, 'export'])->name('visit.export');
    
    // 2. Appointment & Rooms
    // ปิด/เปิดนัด (เคสคนไข้มาก่อนวันนัด) — เรียกจากหน้าตรวจและหน้าห้องบัตร
    Route::post('/visit-appointment/{id}/close', [VisitController::class, 'closeAppointment'])->name('visit.appointment.close');
    // โน้ตส่งเวรใส่นัดเดิม โดยไม่สร้างนัดใหม่ซ้อน
    Route::post('/visit-appointment/{id}/note', [VisitController::class, 'noteAppointment'])->name('visit.appointment.note');
    Route::get('/visit/appointment', [VisitController::class, 'appointment'])->name('visit.appointment');
    Route::get('/visit/appointments', [VisitController::class, 'appointment'])->name('appointments'); // Alias
    // ต้องอยู่ก่อน /visit/room/{status} ไม่งั้น "counts" ถูกตีความเป็นเลขห้อง
    Route::get('/visit/room-counts', [VisitController::class, 'roomCounts'])->name('visit.roomCounts');
    Route::get('/visit/room/{status}', [VisitController::class, 'room'])->name('visit.room');
    // รายการคนไข้วันนี้ของคลินิกที่เลือกอยู่ (ทุกสถานะ) — ทางเข้าหลักของ TMC/TB
    Route::get('/visit/today', [VisitController::class, 'today'])->name('visit.today');
    // ส่งคนไข้ข้ามคลินิกโดยเปิด visit ใหม่ให้ (พยาบาลส่งเองได้ ไม่ต้องกลับเวชระเบียน)
    Route::post('/visit/{id}/send-to-clinic', [VisitController::class, 'sendToClinic'])->name('visit.sendToClinic');
    
    // 3. Actions
    Route::get('/visit/medicine/{id}', [VisitController::class, 'medicine'])->name('visit.medicine');
    Route::get('/visit/lab/{id}', [VisitController::class, 'lab'])->name('visit.lab');
    Route::get('/visit/{id}/queue-ticket', [VisitController::class, 'queueTicket'])->name('visit.queueTicket');
    Route::post('/visit/change-status/{room}', [VisitController::class, 'changeStatus'])->name('visit.change-status');
    Route::post('/visit/add-contact-person/{id}', [VisitController::class, 'addContactPerson'])->name('visit.addContactPerson');
    Route::post('/visit/{id}/select-queue', [VisitController::class, 'selectQueue'])->name('visit.selectQueue');
    Route::post('/visit/{id}/call-queue', [VisitController::class, 'callQueue'])->name('visit.callQueue');
    Route::get('/queue-system/available-queues', [VisitController::class, 'availableQueues'])->name('queue.available');

    // ประวัติข้อความ LINE ที่ระบบส่งออก (audit log)
    Route::get('/line/logs', [App\Http\Controllers\LineMessageLogController::class, 'index'])->name('line.logs');

    // คลังยา (lot / วันหมดอายุ / รับเข้า / FEFO)
    Route::get('/medicine-lot', [App\Http\Controllers\MedicineLotController::class, 'index'])->name('medicine-lot.index');
    Route::get('/medicine-lot-report', [App\Http\Controllers\MedicineLotController::class, 'report'])->name('medicine-lot.report');
    Route::post('/medicine-lot-toggle-block', [App\Http\Controllers\MedicineLotController::class, 'toggleBlockMode'])->name('medicine-lot.toggleBlock');

    // วันหยุดราชการ — ล็อกปฏิทินนัดหมาย และกันไม่ให้ระบบส่ง LINE เตือนวันที่คลินิกปิด
    Route::get('/holiday', [App\Http\Controllers\HolidayController::class, 'index'])->name('holiday.index');
    Route::post('/holiday', [App\Http\Controllers\HolidayController::class, 'store'])->name('holiday.store');
    Route::put('/holiday/{id}', [App\Http\Controllers\HolidayController::class, 'update'])->name('holiday.update');
    Route::post('/holiday/{id}/toggle', [App\Http\Controllers\HolidayController::class, 'toggle'])->name('holiday.toggle');
    Route::delete('/holiday/{id}', [App\Http\Controllers\HolidayController::class, 'destroy'])->name('holiday.destroy');

    // จัดการค่าบริการ (master)
    Route::get('/service-price', [App\Http\Controllers\ServicePriceController::class, 'index'])->name('service-price.index');
    // ตารางระยะนัดวัคซีน — ระบบใช้นัดเข็มถัดไปอัตโนมัติ (TMC 2569-09-30)
    Route::get('/vaccine-schedule', [App\Http\Controllers\VaccineScheduleController::class, 'index'])->name('vaccine-schedule.index');
    Route::post('/vaccine-schedule', [App\Http\Controllers\VaccineScheduleController::class, 'save'])->name('vaccine-schedule.save');
    Route::post('/service-price', [App\Http\Controllers\ServicePriceController::class, 'store'])->name('service-price.store');
    Route::put('/service-price/{id}', [App\Http\Controllers\ServicePriceController::class, 'update'])->name('service-price.update');
    Route::post('/service-price/{id}/toggle', [App\Http\Controllers\ServicePriceController::class, 'toggle'])->name('service-price.toggle');
    Route::delete('/service-price/{id}', [App\Http\Controllers\ServicePriceController::class, 'destroy'])->name('service-price.destroy');

    // ตั้งราคากลาง Lab (master) — ทำให้ราคาเด้งเองในหน้าคิดเงิน
    // ราคากลางตามประกาศ สคร.1 + หน้าจับคู่ราคาเข้ายาในระบบ
    Route::get('/standard-price', [App\Http\Controllers\StandardPriceController::class, 'index'])->name('standard-price.index');
    Route::post('/standard-price', [App\Http\Controllers\StandardPriceController::class, 'update'])->name('standard-price.update');
    Route::post('/standard-price/add', [App\Http\Controllers\StandardPriceController::class, 'store'])->name('standard-price.store');
    Route::get('/standard-price/match', [App\Http\Controllers\StandardPriceController::class, 'matchMedicines'])->name('standard-price.match');
    Route::post('/standard-price/match', [App\Http\Controllers\StandardPriceController::class, 'applyMedicines'])->name('standard-price.applyMedicines');

    Route::get('/lab-price', [App\Http\Controllers\LabPriceController::class, 'index'])->name('lab-price.index');
    Route::post('/lab-price', [App\Http\Controllers\LabPriceController::class, 'save'])->name('lab-price.save');

    // QZ Tray: เซ็น cert/signature ให้พิมพ์เข้าเครื่องที่ระบุแบบเงียบ (ไม่เด้ง dialog)
    Route::get('/qz/certificate', [App\Http\Controllers\QzController::class, 'certificate'])->name('qz.certificate');
    Route::get('/qz/sign', [App\Http\Controllers\QzController::class, 'sign'])->name('qz.sign');

    // ประวัติการแก้ไขทั้งระบบ (Admin เท่านั้น — บังคับสิทธิ์ใน controller)
    Route::get('/activity-log', [App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-log.index');
    // ประวัติการแก้ไขเฉพาะเคสของคนไข้ (เจ้าหน้าที่ที่ล็อกอินดูได้)
    Route::get('/activity-log/patient/{patientId}', [App\Http\Controllers\ActivityLogController::class, 'forPatient'])->name('activity-log.patient');

    Route::get('/medicine-lot/{medicineId}', [App\Http\Controllers\MedicineLotController::class, 'show'])->name('medicine-lot.show');
    Route::post('/medicine-lot/{medicineId}/receive', [App\Http\Controllers\MedicineLotController::class, 'store'])->name('medicine-lot.store');
    Route::put('/medicine-lot/lot/{lotId}', [App\Http\Controllers\MedicineLotController::class, 'update'])->name('medicine-lot.update');
    Route::post('/medicine-lot/lot/{lotId}/adjust', [App\Http\Controllers\MedicineLotController::class, 'adjust'])->name('medicine-lot.adjust');
    Route::delete('/medicine-lot/lot/{lotId}', [App\Http\Controllers\MedicineLotController::class, 'destroy'])->name('medicine-lot.destroy');

    // หน้าตรวจสุขภาพระบบแจ้งเตือนนัดหมายทาง LINE
    Route::get('/line/health', [App\Http\Controllers\LineHealthController::class, 'index'])->name('line.health');

    // --- Patient Notes (popup note ติดตัวคนไข้ทุก visit) ---
    // แถบแจ้งเตือนความเคลื่อนไหวคนไข้ (ทุกเครื่อง poll ทุก 10 วิ)
    Route::get('/notifications/recent', [\App\Http\Controllers\NotificationController::class, 'recent'])->name('notifications.recent');

    Route::post('/patient/{patient}/yellow-book', [PatientController::class, 'saveYellowBook'])->name('patient.yellowBook');
    Route::post('/patient/{patient}/yellow-book/issue', [PatientController::class, 'issueYellowBook'])->name('patient.yellowBook.issue');
    // เคสเก่าจากทะเบียนกระดาษ คลินิกวัณโรค (2569-11-05)
    Route::post('/patient/{patient}/tb-paper-old', [PatientController::class, 'saveTbPaperOld'])->name('patient.tbPaperOld');
    // สมุดเหลือง "ครั้งนี้" ต่อ visit TMC: ใช้เล่มเดิม/ออกใหม่/ทดแทน/สมุดเสีย (2569-11-04)
    Route::post('/visit/{visit}/yellow-book', [App\Http\Controllers\TmcYellowBookController::class, 'save'])->name('tmc.yellowBook.save');
    Route::post('/visit/{visit}/yellow-book/history-delete', [App\Http\Controllers\TmcYellowBookController::class, 'deleteHistory'])->name('tmc.yellowBook.historyDelete');
    Route::post('/patient/{patient}/notes', [App\Http\Controllers\PatientNoteController::class, 'store'])->name('patient.notes.store');
    // ความยินยอม PDPA (2569-10-29)
    Route::post('/patient/{patient}/consent', [App\Http\Controllers\PatientConsentController::class, 'store'])->name('patient.consent.store');
    Route::get('/patient-consent/{consent}/attachment', [App\Http\Controllers\PatientConsentController::class, 'attachment'])->name('patient.consent.attachment');
    Route::post('/patient/{patient}/notes/mark-read', [App\Http\Controllers\PatientNoteController::class, 'markRead'])->name('patient.notes.markRead');
    Route::post('/patient-notes/{id}/resolve', [App\Http\Controllers\PatientNoteController::class, 'resolve'])->name('patient.notes.resolve');

    // 4. Resource (สุดท้าย)
    Route::resource('visit', VisitController::class);

    // --- Visit Followups ---
    Route::get('/visit_followups/{visit_id}', [VisitFollowupController::class, 'index'])->name('visit_followups.index');
    Route::post('/visit_followups/store', [VisitFollowupController::class, 'store'])->name('visit_followups.store');
    Route::get('/followups/create/{visit}', [VisitFollowupController::class, 'create'])->name('followups.create');
    Route::get('/followups/{followup}/edit', [VisitFollowupController::class, 'edit'])->name('followups.edit');
    Route::put('/followups/{followup}', [VisitFollowupController::class, 'update'])->name('followups.update');
    Route::delete('/followups/{followup}', [VisitFollowupController::class, 'destroy'])->name('followups.destroy');

    // --- Lab & Lab Sub ---
    Route::post('/print-labs', [LabController::class, 'printLabs'])->name('print.labs');
    Route::post('/lab/{id}/update-all-sub', [LabController::class, 'updateAllSub'])->name('lab-sub.updateAllSub');
    // อนุมัติ/ถอนอนุมัติผล Lab (ผลจะแสดงให้พยาบาล/หมอเห็นเมื่ออนุมัติแล้วเท่านั้น)
    Route::post('/lab/{id}/approve', [LabController::class, 'approve'])->name('lab.approve');
    // flow ห้อง Lab ใหม่ 2569-09-30: รับ Lab / Report (คนที่ login + เวลาจริง)
    Route::post('/lab/{id}/receive', [LabController::class, 'receive'])->name('lab.receive');
    // ชื่อ lab.report ถูกใช้แล้ว (GET /lab/report) — ใช้ชื่อแยก ไม่งั้นลิงก์ปุ่มกลายเป็น /lab/report?id แล้ว 405
    Route::post('/lab/{id}/report-result', [LabController::class, 'reportResult'])->name('lab.reportResult');
    Route::get('/lab/{id}/approve-status', [LabController::class, 'approveStatus'])->name('lab.approveStatus');
    // ผลย้อนหลังของรายการตรวจเดียว (popup เทียบค่าเดิม เช่น RPR ลงถึงไหนแล้ว)
    Route::get('/lab/trend/{patient}/{method}', [LabController::class, 'trend'])->name('lab.trend');
    Route::get('/lab/room', [LabController::class, 'room'])->name('lab.room');
    Route::get('/lab/report', [LabController::class, 'report'])->name('lab.report');
    // แบบบันทึกผลการทดสอบพิสูจน์เชื้อหนองใน FM-STI-WI08-02 (ห้อง Lab ขอ 2569-10-02) · ต้องมาก่อน resource('lab')
    Route::get('/lab/gc-identification', [App\Http\Controllers\LabGcIdController::class, 'index'])->name('lab.gcId');
    Route::get('/lab/gc-identification/print', [App\Http\Controllers\LabGcIdController::class, 'print'])->name('lab.gcId.print');
    Route::post('/lab/gc-identification/save', [App\Http\Controllers\LabGcIdController::class, 'save'])->name('lab.gcId.save');
    Route::post('/lab/gc-identification/stamp', [App\Http\Controllers\LabGcIdController::class, 'stamp'])->name('lab.gcId.stamp');
    
    // Lab Export (มี 2 แบบ เลือกใช้ตามสะดวก หรือเปิดทั้งคู่คนละ URL)
    Route::post('/lab/export-post', [LabExportController::class, 'export'])->name('lab.export.post');
    Route::get('/lab/export', [LabController::class, 'export'])->name('lab.export');

    Route::resource('lab', LabController::class);
    Route::resource('lab-item', LabItemController::class);
    Route::resource('lab-blood', LabBloodController::class);

    Route::get('/lab-sub/add', [LabSubController::class, 'add'])->name('lab-sub.add');
    Route::get('/lab-sub/config', [LabSubController::class, 'config'])->name('lab-sub.config');
    Route::post('/lab-sub/save-config', [LabSubController::class, 'saveConfig'])->name('lab-sub.save-config');
    // แผง Lab ในคลินิกของคลินิก TB (บัตร TB 01 / หน้าตรวจ TB) — สั่ง/ถอดเฉพาะส่วนต่าง กติกาเดียวกับ save-config (2569-10-02)
    Route::post('/visit/{visit}/lab-quick', [LabSubController::class, 'quickOrder'])->name('lab-sub.quick');
    Route::get('/lab-sub/{id}/result', [LabSubController::class, 'result'])->name('lab-sub.result');
    // ผลความไวต่อยาของ GC (Culture = Growth) — ห้อง Lab ขอ 2569-09-30 · ต้องมาก่อน resource
    Route::get('/lab-sub/{id}/ast', [App\Http\Controllers\LabAstController::class, 'edit'])->name('lab-sub.ast');
    Route::post('/lab-sub/{id}/ast', [App\Http\Controllers\LabAstController::class, 'save'])->name('lab-sub.ast.save');
    Route::get('/lab-sub/{id}/ast/print', [App\Http\Controllers\LabAstController::class, 'print'])->name('lab-sub.ast.print');
    Route::get('/lab-sub/print/{id}', [LabSubController::class, 'print'])->name('lab-sub.print');
    Route::get('/lab-sub/table/{patient_id}', [LabSubController::class, 'table'])->name('lab-sub.table');
    Route::resource('lab-sub', LabSubController::class);

    // --- Medicine & Diagnosis ---
    Route::get('/visit-medicine/add', [VisitMedicineController::class, 'add'])->name('visit-medicine.add');
    // ยาเดิมของคนไข้: ดู popup แล้วดึงมาใส่ใบสั่งยาครั้งนี้ได้
    Route::get('/visit-medicine/history/{visit}', [VisitMedicineController::class, 'history'])->name('visit-medicine.history');
    Route::post('/visit-medicine/copy/{visit}', [VisitMedicineController::class, 'copy'])->name('visit-medicine.copy');
    Route::resource('visit-medicine', VisitMedicineController::class);
    // วินิจฉัยเดิมของคนไข้: ดู popup แล้วดึงมาใส่ visit ครั้งนี้ได้
    Route::get('/diagnosis/history/{visit}', [DiagnosisController::class, 'history'])->name('diagnosis.history');
    Route::post('/diagnosis/copy/{visit}', [DiagnosisController::class, 'copy'])->name('diagnosis.copy');
    // ติ๊ก "ส่ง 506" ต่อวินิจฉัย (ยิงด้วย JS เพราะตารางอยู่ในฟอร์มหลัก ห้าม nested form)
    Route::post('/diagnosis/{diagnosi}/toggle506', [DiagnosisController::class, 'toggle506'])->name('diagnosis.toggle506');
    Route::post('/diagnosis/hiv-counsel/{visit}', [DiagnosisController::class, 'hivCounsel'])->name('diagnosis.hivCounsel');
    Route::resource('diagnosis', DiagnosisController::class);

    // --- รายงานเฝ้าระวังโรค 506 (STI -> DBF สำหรับ FoxPro) ---
    Route::get('/r506', [R506Controller::class, 'index'])->name('r506.index');
    Route::post('/r506/select', [R506Controller::class, 'saveSelection'])->name('r506.select');
    Route::post('/r506/onset', [R506Controller::class, 'saveOnset'])->name('r506.onset');
    Route::post('/r506/export', [R506Controller::class, 'export'])->name('r506.export');

    // --- เพิ่มตัวเลือกสาเหตุการมา TMC (master list) ---
    Route::post('/tmc-reason', [\App\Http\Controllers\TmcReasonController::class, 'store'])->name('tmc-reason.store');

    // --- ทะเบียนติดตามผู้ป่วย 5 โรคหลัก + คู่สัมผัส (ContactSTI) ---
    // ทะเบียน RIHIS-VCT (บริการปรึกษาและตรวจเอชไอวี) — กรอกเอง + ส่งออก Excel ตามฟอร์มส่วนกลาง
    // จัดการแคตตาล็อกรายการตรวจแล็บภายนอก — เดิมแก้ได้เฉพาะในไฟล์ seeder
    Route::get('/external-lab-test',        [App\Http\Controllers\ExternalLabTestController::class, 'index'])->name('external-lab-test.index');
    Route::post('/external-lab-test',       [App\Http\Controllers\ExternalLabTestController::class, 'save'])->name('external-lab-test.save');
    Route::post('/external-lab-test/store', [App\Http\Controllers\ExternalLabTestController::class, 'store'])->name('external-lab-test.store');

    // แคตตาล็อกแล็บรวม + ตัวเลือก "สั่งแล็บ" (2569-10-02) — ตารางยังไม่ขึ้น = endpoint ตอบ 404 หน้าจอเดิมทำงานต่อ
    Route::get('/lab-catalog',        [App\Http\Controllers\LabCatalogController::class, 'index'])->name('lab-catalog.index');
    Route::post('/lab-catalog',       [App\Http\Controllers\LabCatalogController::class, 'save'])->name('lab-catalog.save');
    Route::post('/lab-catalog/sync',  [App\Http\Controllers\LabCatalogController::class, 'sync'])->name('lab-catalog.sync');
    Route::get('/visit/{visit}/lab-catalog',        [App\Http\Controllers\LabCatalogController::class, 'picker'])->name('lab-catalog.picker');
    Route::post('/visit/{visit}/lab-catalog/order', [App\Http\Controllers\LabCatalogController::class, 'order'])->name('lab-catalog.order');
    // ลงผลรายการตรวจข้างเตียง (DTX / EKG) ในหน้าต่างสั่ง — 2569-10-22
    Route::post('/visit/{visit}/lab-catalog/result', [App\Http\Controllers\LabCatalogController::class, 'result'])->name('lab-catalog.result');

    // ===== เปลี่ยนรหัสผ่าน (หนังสือ สธ 0418.17/719 ลว. 27 ส.ค. 2569) =====
    // แยกจากหน้าแก้ไขผู้ใช้เดิม เพราะหน้านั้นมีช่อง Roles ปนอยู่ และไม่ตรวจเกณฑ์รหัสผ่าน
    Route::get('/account/password',  [App\Http\Controllers\PasswordPolicyController::class, 'mine'])->name('password.mine');
    Route::post('/account/password', [App\Http\Controllers\PasswordPolicyController::class, 'updateMine'])->name('password.mine.update');
    Route::get('/backend/password-status', [App\Http\Controllers\PasswordPolicyController::class, 'status'])->name('password.status');
    Route::get('/backend/users/{id}/password',  [App\Http\Controllers\PasswordPolicyController::class, 'forUser'])->name('password.for-user');
    Route::post('/backend/users/{id}/password', [App\Http\Controllers\PasswordPolicyController::class, 'updateForUser'])->name('password.for-user.update');

    Route::get('/rihis-vct', [RihisVctController::class, 'index'])->name('rihis-vct.index');
    Route::get('/rihis-vct/export', [RihisVctController::class, 'export'])->name('rihis-vct.export');
    Route::post('/rihis-vct', [RihisVctController::class, 'store'])->name('rihis-vct.store');
    Route::post('/rihis-vct/save', [RihisVctController::class, 'save'])->name('rihis-vct.save');
    Route::post('/rihis-vct/sync', [RihisVctController::class, 'sync'])->name('rihis-vct.sync');
    Route::delete('/rihis-vct/{id}', [RihisVctController::class, 'destroy'])->name('rihis-vct.destroy');

    // บันทึกการเปลี่ยนแปลงระบบ ให้พยาบาลตามทันว่าอะไรเปลี่ยนไปบ้าง
    Route::get('/whats-new', [ChangelogController::class, 'index'])->name('changelog.index');
    // Dashboard งานพัฒนา + ความปลอดภัย + ผลทดสอบ (กลุ่ม IT ขอ 2569-10-19) — เฉพาะ Admin
    Route::get('/dev-dashboard', [App\Http\Controllers\DevDashboardController::class, 'index'])
        ->middleware('role:Admin')->name('dev.dashboard');
    // ตั้งค่าเอฟเฟกต์เทศกาล/ฤดูบนแถบเมนู (แอดมินขอแก้เองได้ 2569-11-03)
    Route::middleware('role:Admin')->group(function () {
        Route::get('/backend/season-fx', [App\Http\Controllers\SeasonFxController::class, 'index'])->name('season-fx.index');
        Route::post('/backend/season-fx', [App\Http\Controllers\SeasonFxController::class, 'save'])->name('season-fx.save');
    });
    // ผู้รับแจ้งเตือนระบบล่มทาง LINE (กล่องในหน้า Dashboard งานพัฒนา)
    Route::middleware('role:Admin')->prefix('health-alert')->name('health.')->group(function () {
        Route::post('/code', [App\Http\Controllers\HealthController::class, 'linkCode'])->name('code');
        Route::post('/test', [App\Http\Controllers\HealthController::class, 'test'])->name('test');
        Route::post('/remove', [App\Http\Controllers\HealthController::class, 'remove'])->name('remove');
    });

    Route::get('/contact-sti', [ContactStiController::class, 'index'])->name('contact-sti.index');
    Route::get('/contact-sti/export', [ContactStiController::class, 'exportExcel'])->name('contact-sti.export');
    Route::post('/contact-sti/save', [ContactStiController::class, 'save'])->name('contact-sti.save');

    Route::resource('medicine', MedicineController::class);

    // --- Address & Contact ---
    Route::resource('address', AddressController::class);
    // ดึงคู่สัมผัสเดิมของคนไข้มาใช้ซ้ำ (แบบเดียวกับ "ยาเดิม") — ต้องมาก่อน resource ไม่งั้นชนกับ contact-person/{id}
    Route::get('/contact-person/history/{visit}', [ContactPersonController::class, 'history'])->name('contact-person.history');
    Route::post('/contact-person/copy/{visit}', [ContactPersonController::class, 'copy'])->name('contact-person.copy');
    Route::resource('contact-person', ContactPersonController::class);

    // --- Test Excel (Closure) ---
    Route::get('excel-test', function(){
        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        $activeWorksheet->setCellValue('A1', 'Hello World !');
        $writer = new Xlsx($spreadsheet);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="myfile.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
});
});
