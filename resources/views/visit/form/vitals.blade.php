{{-- ============================================================
     การ์ดสัญญาณชีพ — ใช้ร่วมกันระหว่างคลินิก TMC และ TB
     ย้ายออกมาจาก visit/form/tmc.blade.php (2569-09-18) ตอนเปิดคลินิก TB
     เพราะใบ Form 1 ของวัณโรคก็ต้องกรอก BW/Ht/BMI/T/HR/RR/BP/O2sat เหมือนกัน

     ⚠️ ห้ามใช้ <form> ในไฟล์นี้ — include อยู่ข้างในฟอร์มหลัก (#visit_edit_form)
     ============================================================ --}}
<style>
    [data-tabtoggle][href="#tmcVital"] { background: #0d9488 !important; }
    .tmc-vital input { text-align: center; font-weight: 600; }
</style>

{{-- ---------- สัญญาณชีพ (ห้องซักประวัติ) ---------- --}}
<br>
<div class="d-grid">
    <a class="btn btn-light" data-tabtoggle="1" href="#tmcVital" role="button"
       aria-expanded="false" style="width:100%" aria-controls="tmcVital">
        {{-- ไม่ใส่ชื่อคลินิกในหัวข้อ เพราะการ์ดนี้ใช้ทั้ง TMC และคลินิกวัณโรค (2569-09-29) --}}
        <font size="4"><i class="fa-solid fa-heart-pulse me-1"></i> สัญญาณชีพ</font>
    </a>
</div>
<div class="collapse show" id="tmcVital">
    <div class="card card-body tmc-vital">
        <div class="row g-2">
            @php
                $__vitals = [
                    ['weight',           'น้ำหนัก (กก.)',      '0.01', '0', '400'],   // ทศนิยม 2 ตำแหน่ง ทุกคลินิก (2569-10-29 · คอลัมน์ decimal(5,2))
                    ['height',           'ส่วนสูง (ซม.)',      '0.1', '30', '250'],
                    ['temperature',      'อุณหภูมิ (°C)',      '0.1', '30', '45'],
                    ['pulse',            'ชีพจร (ครั้ง/นาที)',  '1',   '20', '250'],
                    ['systolic',         'ความดันตัวบน',       '1',   '40', '300'],
                    ['diastolic',        'ความดันตัวล่าง',      '1',   '20', '200'],
                    ['respiratory_rate', 'อัตราหายใจ',         '1',   '5',  '80'],
                    ['spo2',             'SpO₂ (%)',           '1',   '50', '100'],
                ];
                // คลินิกวัณโรค: QTcF จาก EKG ที่คลินิก (2569-10-22) — ยังไม่รัน migration = ไม่โชว์
                // DTX ย้ายไปเป็นแล็บในแผง "Lab ในคลินิก" แล้ว (คลินิกขอ — พยาบาลมองว่าเป็นแล็บ) · คอลัมน์ visits.dtx เหลือไว้ ไม่มีข้อมูล
                if ((int) ($visit->clinic ?? 0) === 2 && \Illuminate\Support\Facades\Schema::hasColumn('visits', 'qtcf')) {
                    $__vitals[] = ['qtcf', 'EKG · QTcF (ms)',   '1',   '200', '800'];
                }
            @endphp
            @foreach($__vitals as [$__f, $__label, $__step, $__min, $__max])
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted mb-1" for="{{ $__f }}">{{ $__label }}</label>
                    <input type="number" class="form-control js-vital" id="{{ $__f }}" name="{{ $__f }}"
                           step="{{ $__step }}" min="{{ $__min }}" max="{{ $__max }}"
                           value="{{ old($__f, $visit->$__f) }}">
                    <div class="form-text js-vital-flag" data-for="{{ $__f }}"></div>
                </div>
                @if($__f === 'height')
                    {{-- BMI วางติดส่วนสูง เพราะคิดจากน้ำหนัก/ส่วนสูงสองช่องข้าง ๆ (คลินิกขอ 2569-09-29)
                         เดิมอยู่ในใบคัดกรองวัณโรคด้านล่าง พยาบาลกรอกน้ำหนักข้างบนแล้วต้องเลื่อนลงไปดูผล
                         เป็นช่องแสดงผลเฉย ๆ ไม่มี name — ไม่ถูกส่งไปกับฟอร์มและไม่ได้เก็บลงฐาน
                         (คิดใหม่จากน้ำหนัก/ส่วนสูงทุกครั้ง จะได้ไม่มีค่าเก่าค้างขัดกับตัวเลขจริง)
                         และไม่กวนกติกา _touched ของหน้าตรวจ --}}
                    @php
                        // ส่วนสูงไม่ได้วัดทุกครั้ง (F/U) — ช่องว่างใช้ส่วนสูงล่าสุดของคนไข้คิด BMI แทน (2569-10-14)
                        // จาก visit ก่อน ๆ ก่อน ไม่มีค่อยดูบัตร TB 01 · แค่ใช้คิด ไม่ได้เติมลงช่องส่วนสูง
                        $__lastH = \App\Models\Visit::where('patient_id', $visit->patient_id)->where('id', '!=', $visit->id)
                            ->where('height', '>', 0)->orderByDesc('date')->orderByDesc('id')->value('height');
                        if (! $__lastH && (int) ($visit->clinic ?? 0) === 2) {
                            $__lastH = \App\Models\TbRegistration::where('patient_id', $visit->patient_id)->where('height', '>', 0)->orderByDesc('id')->value('height');
                        }
                        // ตาราง BMI 8 ระดับเป็นเกณฑ์ผู้ใหญ่ — ต่ำกว่า 19 ปีต้องดู BMI ตามอายุ (คลินิกขอ 2569-10-14) · ไม่รู้วันเกิด = ถือเป็นผู้ใหญ่
                        [, $__bmiAge] = \App\Http\Controllers\TbController::doseTypeFor(\App\Models\Patient::find($visit->patient_id));
                        $__bmiKid = $__bmiAge !== null && $__bmiAge < 19;
                    @endphp
                    <div class="col-6 col-md-3">
                        <label class="form-label small text-muted mb-1">BMI (คิดให้เอง)</label>
                        <div class="form-control bg-light text-center fw-bold" id="vital-bmi" style="font-size:1.1rem;"
                             data-last-height="{{ $__lastH ? rtrim(rtrim(number_format((float) $__lastH, 1, '.', ''), '0'), '.') : '' }}"
                             data-child-age="{{ $__bmiKid ? $__bmiAge : '' }}">—</div>
                        <div class="form-text" id="vital-bmi-msg"></div>
                    </div>
                @endif
            @endforeach
        </div>
        <div class="form-text mt-2">
            บันทึกพร้อมกับปุ่ม Save ของหน้าตรวจ · เว้นว่างได้ถ้าไม่ได้วัด ·
            <span class="text-muted">การแจ้งเตือนเป็นเพียงตัวช่วย ไม่ใช่การวินิจฉัย</span>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.__tmcVitalFlagInit) return;
    window.__tmcVitalFlagInit = true;

    // เกณฑ์เตือนผู้ใหญ่ — คร่าวๆ เพื่อสะกิดพยาบาล ไม่ใช่ค่าวินิจฉัย
    function flagByValue(id, v) {
        switch (id) {
            case 'systolic':
                if (v >= 180) return ['ความดันตัวบนสูงมาก (≥180)', 'danger'];
                if (v >= 140) return ['ความดันตัวบนสูง (≥140)', 'warning'];
                if (v < 90)   return ['ความดันตัวบนต่ำ (<90)', 'danger'];
                return null;
            case 'diastolic':
                if (v >= 120) return ['ความดันตัวล่างสูงมาก (≥120)', 'danger'];
                if (v >= 90)  return ['ความดันตัวล่างสูง (≥90)', 'warning'];
                if (v < 60)   return ['ความดันตัวล่างต่ำ (<60)', 'warning'];
                return null;
            case 'spo2':
                if (v < 90)   return ['SpO₂ ต่ำมาก (<90%)', 'danger'];
                if (v < 95)   return ['SpO₂ ต่ำ (<95%)', 'warning'];
                return null;
            case 'pulse':
                if (v > 120)  return ['ชีพจรเร็ว (>120)', 'warning'];
                if (v < 50)   return ['ชีพจรช้า (<50)', 'warning'];
                return null;
            case 'temperature':
                if (v >= 38)  return ['มีไข้ (≥38°C)', 'warning'];
                if (v < 35)   return ['อุณหภูมิต่ำ (<35°C)', 'warning'];
                return null;
            case 'respiratory_rate':
                if (v > 24)   return ['หายใจเร็ว (>24)', 'warning'];
                if (v < 10)   return ['หายใจช้า (<10)', 'warning'];
                return null;
            case 'dtx':
                if (v < 70)   return ['น้ำตาลต่ำ (<70)', 'danger'];
                if (v >= 200) return ['น้ำตาลสูง (≥200)', 'warning'];
                return null;
            // ยาวัณโรคดื้อยา (Bdq, Cfz, Lfx/Mfx, Dlm) ยืด QT — เกิน 500 ms ต้องให้แพทย์ดูก่อนให้ยาต่อ
            case 'qtcf':
                if (v > 500)  return ['QTcF เกิน 500 ms — แจ้งแพทย์ก่อนให้ยาต่อ', 'danger'];
                if (v >= 450) return ['QTcF ยาว (≥450 ms)', 'warning'];
                return null;
        }
        return null;
    }

    function evalField(id) {
        var el = document.getElementById(id);
        var box = document.querySelector('.js-vital-flag[data-for="' + id + '"]');
        if (!el || !box) return;
        var raw = el.value.trim();
        var res = raw === '' || isNaN(parseFloat(raw)) ? null : flagByValue(id, parseFloat(raw));

        el.classList.remove('border-danger', 'border-warning');
        if (res) {
            // ผิดปกติของ TMC = แดงทั้งหมด (ให้พยาบาลเห็นชัด ไม่แยกเหลือง/แดง)
            el.classList.add('border-danger');
            box.className = 'form-text js-vital-flag text-danger fw-semibold';
            box.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i>' + res[0];
        } else {
            box.className = 'form-text js-vital-flag';
            box.textContent = '';
        }
        updateBP();
    }

    function updateBP() {
        var s = parseFloat((document.getElementById('systolic') || {}).value);
        var d = parseFloat((document.getElementById('diastolic') || {}).value);
        var box = document.querySelector('.js-vital-flag[data-for="diastolic"]');
        if (!box || isNaN(s) || isNaN(d)) return;
        if (d >= s) {
            box.className = 'form-text js-vital-flag text-danger fw-semibold';
            box.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i>ตัวล่าง ≥ ตัวบน — ตรวจสอบว่ากรอกสลับหรือไม่';
        }
    }

    // BMI แปลผลตามตารางมาตรฐานเอเชียที่คลินิกให้มา (2569-10-14) — 8 ระดับ
    // ผอมเป็นทั้งปัจจัยเสี่ยงและผลของวัณโรค จึงแยกน้ำหนักน้อยเป็น 3 ระดับ
    // ส่วนสูงว่าง = ใช้ส่วนสูงล่าสุดของคนไข้ (data-last-height) แล้วบอกว่าใช้ค่าไหน
    function bmiGrade(b) {
        if (b < 16)    return ['#b91c1c', 'น้ำหนักน้อยระดับ 3'];
        if (b < 17)    return ['#dc2626', 'น้ำหนักน้อยระดับ 2'];
        if (b < 18.5)  return ['#ea580c', 'น้ำหนักน้อยระดับ 1'];
        if (b < 23)    return ['#15803d', 'น้ำหนักปกติ'];
        if (b < 25)    return ['#ca8a04', 'น้ำหนักเกิน'];
        if (b < 30)    return ['#ea580c', 'โรคอ้วนระดับ 1'];
        if (b < 40)    return ['#dc2626', 'โรคอ้วนระดับ 2'];
        return ['#b91c1c', 'โรคอ้วนระดับ 3'];
    }
    function updateBMI() {
        var box = document.getElementById('vital-bmi');
        var msg = document.getElementById('vital-bmi-msg');
        if (!box || !msg) return;
        var w = parseFloat((document.getElementById('weight') || {}).value);
        var h = parseFloat((document.getElementById('height') || {}).value), usedLast = false;
        if (!(h > 0) && parseFloat(box.dataset.lastHeight) > 0) { h = parseFloat(box.dataset.lastHeight); usedLast = true; }
        if (!(w > 0) || !(h > 0)) {
            box.textContent = '—'; box.style.color = '';
            msg.innerHTML = !(w > 0) ? '' : '<span class="text-muted">กรอกส่วนสูงเพื่อคิด BMI</span>';
            return;
        }
        var bmi = w / Math.pow(h / 100, 2), g = bmiGrade(bmi);
        var lastNote = usedLast ? '<span class="text-muted">คิดจากส่วนสูงครั้งก่อน ' + h + ' ซม.</span>' : '';
        // เด็กต่ำกว่า 19 ปี: ไม่แปลผลด้วยเกณฑ์ผู้ใหญ่ (จะผิด) — บอกให้ดู BMI ตามอายุ พร้อมลิงก์ NutStatCal
        if (box.dataset.childAge !== '') {
            box.textContent = bmi.toFixed(1);
            box.style.color = '';
            msg.innerHTML = '<span class="fw-semibold" style="color:#1d4ed8;">เด็ก (อายุ ' + box.dataset.childAge + ' ปี): ใช้ BMI ตามอายุ</span>'
                + ' <a href="https://nutstatcal.kiddiary.in.th/" target="_blank" rel="noopener">ประเมินที่ NutStatCal</a>'
                + (lastNote ? '<br>' + lastNote : '');
            return;
        }
        box.textContent = bmi.toFixed(1) + ' · ' + g[1];
        box.style.color = g[0];
        msg.innerHTML = lastNote;
    }

    ['weight','height','temperature','pulse','systolic','diastolic','respiratory_rate','spo2','dtx','qtcf']
        .forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', function () { evalField(id); });
            evalField(id);
        });
    ['weight','height'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', updateBMI);
    });
    updateBMI();
})();
</script>

