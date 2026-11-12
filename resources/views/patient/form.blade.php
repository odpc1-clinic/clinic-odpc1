<div class="row g-4">
    <div class="form-group">
    <label for="photo">รูปผู้ป่วย</label><br>

    {{-- ปุ่มเลือกไฟล์จากเครื่อง --}}
    <input type="file" accept="image/*" capture="environment" name="photo" id="photoInput" class="form-control mb-2">

    {{-- แสดงภาพตัวอย่าง --}}
    <img id="preview" src="#" alt="Preview" style="display:none; max-height: 200px;" class="img-thumbnail">

    {{-- พื้นที่วางรูป (Paste Image) --}}
    <div id="pasteArea" contenteditable="true" 
         style="border: 2px dashed #ccc; padding: 10px; min-height: 80px; margin-bottom: 10px; cursor: text;">
        คลิกที่นี่แล้วเสียบบัตรประชาชน เพื่อกรอกข้อมูล
    </div>

    {{-- ปุ่มเปิดกล้อง --}}
    <button type="button" class="btn btn-secondary mt-2" onclick="openCamera()">📷 ถ่ายด้วยกล้อง</button>

    {{-- วิดีโอกล้อง --}}
    <video id="camera" autoplay style="display: none; max-height: 240px;"></video>

    {{-- ปุ่มถ่ายรูป --}}
    <button type="button" class="btn btn-success mt-2" id="snap" style="display: none;" onclick="takeSnapshot()">📸 ถ่ายภาพ</button>

    {{-- บอกความละเอียดที่กล้องให้มาจริง ถ้าได้ต่ำกว่าที่ขอจะได้รู้ทันทีว่าติดที่กล้อง ไม่ใช่ที่โค้ด --}}
    <div id="camInfo" style="display:none; font-size:.8rem; color:#666; margin-top:4px;"></div>

    {{-- canvas เก็บภาพกล้อง --}}
    <canvas id="snapshot" name="canvas" style="display: none;"></canvas>
</div>

<script>
    const video = document.getElementById('camera');
    const canvas = document.getElementById('snapshot');
    const photoInput = document.getElementById('photoInput');
    const preview = document.getElementById('preview');
    const pasteArea = document.getElementById('pasteArea');

    // ความกว้างสูงสุดของรูปที่เก็บ — เดิม 800 ซึ่งได้ไฟล์เฉลี่ยแค่ 11 KB
    // 1280 ยังเล็กมากเทียบกับพื้นที่ที่มี (820 รูปเดิมรวมกันแค่ 8.7 MB) แต่คมขึ้นชัด
    const PHOTO_MAX_WIDTH = 1280;
    const PHOTO_QUALITY = 0.87;

    // เดิมสั่งแค่ { video: true } ซึ่งเบราว์เซอร์จะให้ 640x480 เป็นค่าเริ่มต้น
    // ทั้งที่กล้องถ่ายได้ 1920x1080 — รูปคนไข้จึงนุ่มเบลอมาตลอด แยกไม่ออกจากโฟกัสไม่เข้า
    // ใช้ ideal ไม่ใช่ exact เพื่อให้กล้องที่ทำไม่ได้ยังเปิดได้ตามปกติ ไม่ใช่เปิดไม่ติดเลย
    function openCamera() {
        navigator.mediaDevices.getUserMedia({
            video: {
                width:  { ideal: 1920 },
                height: { ideal: 1080 },
                facingMode: 'user'
            }
        })
            .then(stream => {
                video.srcObject = stream;
                video.style.display = 'block';
                document.getElementById('snap').style.display = 'inline-block';

                // โชว์ความละเอียดที่กล้องให้มาจริง จะได้รู้ทันทีว่าได้ตามที่ขอไหม
                // (กล้องบางตัวยอมแค่ 1280x720 หรือไดรเวอร์จำกัดไว้)
                video.onloadedmetadata = () => {
                    const info = document.getElementById('camInfo');
                    if (info) {
                        info.textContent = `กล้องให้ภาพ ${video.videoWidth}\u00d7${video.videoHeight}`;
                        info.style.display = 'block';
                    }
                };
            })
            .catch(err => alert("ไม่สามารถเข้าถึงกล้องได้: " + err));
    }

    function resizeImage(image, maxWidth, callback) {
        const canvasResize = document.createElement('canvas');
        const ctx = canvasResize.getContext('2d');

        let width = image.width;
        let height = image.height;

        if (width > maxWidth) {
            height = height * (maxWidth / width);
            width = maxWidth;
        }

        canvasResize.width = width;
        canvasResize.height = height;

        ctx.drawImage(image, 0, 0, width, height);

        canvasResize.toBlob(blob => {
            callback(blob);
        }, 'image/jpeg', PHOTO_QUALITY);
    }

    function takeSnapshot() {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);

        // ย่อจาก canvas ตรงๆ ไม่ผ่าน toDataURL ก่อน
        // ของเดิมเข้ารหัส JPEG สองรอบ (canvas -> dataURL -> ย่อ -> JPEG อีกที) เสียรายละเอียดฟรีๆ
        resizeImage(canvas, PHOTO_MAX_WIDTH, (blob) => {
            const file = new File([blob], "photo.jpg", { type: "image/jpeg" });
            const dt = new DataTransfer();
            dt.items.add(file);
            photoInput.files = dt.files;

            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';

            video.style.display = 'none';
            document.getElementById('snap').style.display = 'none';

            // ปิดกล้องหลังถ่ายเสร็จ ไฟกล้องจะได้ดับ ไม่ค้างว่ากำลังถ่ายอยู่
            if (video.srcObject) {
                video.srcObject.getTracks().forEach(t => t.stop());
                video.srcObject = null;
            }
        });
    }

    // แก้ไข event change ของ input ไฟล์ เพื่อ resize ก่อนแสดง preview
    photoInput.addEventListener("change", function () {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            const img = new Image();
            const url = URL.createObjectURL(file);

            img.onload = () => {
                resizeImage(img, PHOTO_MAX_WIDTH, (blob) => {
                    const resizedFile = new File([blob], file.name, { type: "image/jpeg" });
                    const dt = new DataTransfer();
                    dt.items.add(resizedFile);
                    photoInput.files = dt.files;

                    preview.src = URL.createObjectURL(resizedFile);
                    preview.style.display = 'block';

                    URL.revokeObjectURL(url);
                });
            };

            img.src = url;
        }
    });

    // เพิ่ม event listener สำหรับ paste image ลงใน pasteArea
    pasteArea.addEventListener('paste', function (e) {
        const items = (e.clipboardData || e.originalEvent.clipboardData).items;
        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            if (item.kind === 'file') {
                const blob = item.getAsFile();

                const reader = new FileReader();
                reader.onload = function (event) {
                    const base64data = event.target.result;

                    const img = new Image();
                    img.onload = () => {
                        resizeImage(img, PHOTO_MAX_WIDTH, (blobResized) => {
                            const file = new File([blobResized], "pasted_image.jpg", { type: "image/jpeg" });
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            photoInput.files = dt.files;

                            preview.src = URL.createObjectURL(file);
                            preview.style.display = 'block';

                            // ล้างข้อความใน pasteArea (optional)
                            pasteArea.innerHTML = '';
                        });
                    };
                    img.src = base64data;
                };
                reader.readAsDataURL(blob);

                e.preventDefault();
                break; // รับแค่ภาพแรกจาก clipboard
            }
        }
    });
</script>


    <div class="col-2 form-group {{ $errors->has('code') ? 'has-error' : '' }}">
        <label for="code" class="control-label">{{ 'หมายเลขประจำตัว(HN.)' }}</label>
        <input class="form-control" name="code" type="text" id="code" value="{{ isset($patient->code) ? $patient->code : App\Models\Patient::calNH() }}">
        {!! $errors->first('code', '<p class="help-block">:message</p>') !!}
    </div>
    <!-- <div class="col-2  form-group {{ $errors->has('prefix') ? 'has-error' : '' }}">
        <label for="prefix" class="control-label">{{ 'คำนำหน้าชื่อ' }}</label>
        {{-- <input class="form-control" name="prefix" type="number" id="prefix" value="{{ isset($patient->prefix) ? $patient->prefix : ''}}" required> --}}
        <select id="prefix" name="prefix" class="form-control">
    @foreach(\App\Models\Patient::$prefixOption as $key => $label)
        <option value="{{ $key }}">{{ $label }}</option>
    @endforeach
</select>
        {!! $errors->first('prefix', '<p class="help-block">:message</p>') !!}
    </div> -->
    <div class="col-2  form-group {{ $errors->has('prefix') ? 'has-error' : '' }}">
        <label for="prefix" class="control-label">{{ 'คำนำหน้าชื่อ' }}</label>
        {{-- <input class="form-control" name="prefix" type="number" id="prefix" value="{{ isset($patient->prefix) ? $patient->prefix : ''}}" required> --}}
        <select class="form-control" name="prefix" id="prefix" required>
            @foreach (App\Models\Patient::$prefixOption as $key => $value)
            <option value="{{ $key }}" @if (isset($patient->prefix) && $key == $patient->prefix) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {{-- อื่นๆ (99) = พิมพ์เอง เช่น ว่าที่ ร.ต., พ.ต.อ., Prof. (2569-10-14) · ช่องยังไม่มีในฐาน = ไม่โชว์ --}}
        @if(\Illuminate\Support\Facades\Schema::hasColumn('patients', 'prefix_other'))
            <input type="text" name="prefix_other" id="prefix_other" maxlength="50"
                   class="form-control form-control-sm mt-1 {{ (int) ($patient->prefix ?? 0) === 99 ? '' : 'd-none' }}"
                   value="{{ $patient->prefix_other ?? '' }}" placeholder="พิมพ์คำนำหน้า">
        @endif
        <div class="small text-warning d-none" id="prefix-age-hint"></div>
        {!! $errors->first('prefix', '<p class="help-block">:message</p>') !!}
    </div>
    <div class="col-4 form-group {{ $errors->has('name') ? 'has-error' : '' }}">
        <label for="name" class="control-label">{{ 'ชื่อ' }}</label>
        <input class="form-control" name="name" type="text" id="name" value="{{ isset($patient->name) ? $patient->name : '' }}" required>
        {!! $errors->first('name', '<p class="help-block">:message</p>') !!}
    </div>
    <div class="col-4 form-group {{ $errors->has('surname') ? 'has-error' : '' }}">
        <label for="surname" class="control-label">{{ 'นามสกุล' }}</label>
        <input class="form-control" name="surname" type="text" id="surname" value="{{ isset($patient->surname) ? $patient->surname : '' }}" required>
        {!! $errors->first('surname', '<p class="help-block">:message</p>') !!}
    </div>
</div>
@if(\App\Models\Patient::englishNameReady())
{{-- ชื่อภาษาอังกฤษ (2569-10-07) — ใช้ในใบรับรองแพทย์ภาษาอังกฤษ/สมุดวัคซีน สะกดตามพาสปอร์ต · ไม่บังคับ --}}
<div class="row">
    <div class="col-2"></div>
    <div class="col-4 form-group">
        <label for="name_en" class="control-label">ชื่อ (ภาษาอังกฤษ) <span class="small text-muted">ตามพาสปอร์ต</span></label>
        <input class="form-control" name="name_en" type="text" id="name_en" maxlength="150" autocomplete="off"
               value="{{ old('name_en', $patient->name_en ?? '') }}" placeholder="First name" style="text-transform:uppercase;">
    </div>
    <div class="col-4 form-group">
        <label for="surname_en" class="control-label">นามสกุล (ภาษาอังกฤษ)</label>
        <input class="form-control" name="surname_en" type="text" id="surname_en" maxlength="150" autocomplete="off"
               value="{{ old('surname_en', $patient->surname_en ?? '') }}" placeholder="Last name" style="text-transform:uppercase;">
    </div>
    <div class="col-12 small text-muted" style="margin-top:-.5rem;">
        <i class="fa fa-info-circle"></i> ชาวต่างชาติที่ไม่มีชื่อไทย: ช่องชื่อ/นามสกุลไทยใส่ "-" ได้ — บันทึกแล้วระบบใช้ชื่ออังกฤษแทนทุกหน้า (ตารางค้นหา คิว สติกเกอร์ ใบนัด)
    </div>
</div>
@endif

<!-- <div class="row g-4">
    <div class="col-2  form-group {{ $errors->has('sex') ? 'has-error' : '' }}">
        <label for="sex" class="control-label">{{ 'เพศ' }}</label>
        {{-- <input class="form-control" name="sex" type="number" id="sex" value="{{ isset($patient->sex) ? $patient->sex : ''}}" > --}}
        <select id="sex" name="sex" class="form-control">
    @foreach(\App\Models\Patient::$sexOption as $key => $label)
        <option value="{{ $key }}">{{ $label }}</option>
    @endforeach
</select>
        {!! $errors->first('sex', '<p class="help-block">:message</p>') !!}
    </div> -->
<div class="row g-4">
    <div class="col-2  form-group {{ $errors->has('sex') ? 'has-error' : '' }}">
        <label for="sex" class="control-label">{{ 'เพศ' }}</label>
        {{-- <input class="form-control" name="sex" type="number" id="sex" value="{{ isset($patient->sex) ? $patient->sex : ''}}" > --}}
        <select class="form-control" name="sex" id="sex" required>
            @foreach (App\Models\Patient::$sexOption as $key => $value)
            <option value="{{ $key }}" @if (isset($patient->sex) && $key == $patient->sex) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('sex', '<p class="help-block">:message</p>') !!}
    </div>
    <div class="col-2  form-group {{ $errors->has('first_visit') ? 'has-error' : '' }}">
        <label for="first_visit" class="control-label">{{ 'วันที่มาครั้งแรก' }}</label>
        {{-- <input class="form-control" name="first_visit" type="date" id="first_visit" value="{{ isset($patient->first_visit) ? $patient->first_visit : ''}}" > --}}
        <input class="form-control date" name="first_visit" type="text" id="first_visit" value="{{ isset($patient->first_visit) ? $patient->first_visit : date('d').'/'.date('m').'/'.(date('Y')+543) }}">

        {!! $errors->first('first_visit', '<p class="help-block">:message</p>') !!}
    </div>
    {{-- เลขบัตรประชาชน กับ Passport แยกช่อง (คลินิก TMC ขอ 2569-10-07 — รายงานส่งกรมฯ ใช้เฉพาะเลขพาสปอร์ต
         เดิมช่องเดียวพยาบาลพิมพ์รวมกัน เช่น "AA1234567 1234567890123") · ต้องมีอย่างน้อยหนึ่งช่อง (ตรวจที่ controller) --}}
    <div class="col-3 form-group {{ $errors->has('id_card_number') || $errors->has('passport') ? 'has-error' : '' }}">
    <label for="id_card_number" class="control-label">เลขบัตรประชาชน (13 หลัก)</label>
    <input class="form-control" name="id_card_number" type="text" id="id_card_number" inputmode="numeric" maxlength="20"
        value="{{ old('id_card_number', $patient->id_card_number ?? '') }}" placeholder="คนไทย / บัตรต่างด้าว">
    {!! $errors->first('id_card_number', '<p class="help-block">:message</p>') !!}
    <small id="id_card_feedback" style="color: red;"></small>
    <label for="passport" class="control-label small mt-1 mb-0">เลขพาสปอร์ต (Passport No.)</label>
    <input class="form-control form-control-sm" name="passport" type="text" id="passport" maxlength="30"
        value="{{ old('passport', $patient->passport ?? '') }}" placeholder="เช่น AA1234567" style="text-transform:uppercase;">
    {!! $errors->first('passport', '<p class="help-block">:message</p>') !!}
</div>

      
    <div class="col-3 form-group {{ $errors->has('birth_date') ? 'has-error' : '' }}">
        <label for="birth_date" class="control-label">{{ 'วันเกิด' }}</label>
        <input class="form-control date" name="birth_date" type="text" id="birth_date" value="{{ isset($patient->birth_date) ? $patient->birth_date : '' }}" required
               placeholder="วว/ดด/ปปปป" autocomplete="off">
        {{-- ระบบเก็บวันเกิดเป็น พ.ศ. แต่คนไข้ต่างชาติ (TMC) พกพาสปอร์ตที่เป็น ค.ศ. มา
             จึงรับได้ทั้งสองแบบแล้วแปลงเป็น พ.ศ. ให้ พร้อมโชว์อายุกำกับไว้ให้ตรวจซ้ำ --}}
        <div id="birth_date_hint" class="form-text mt-1"></div>

        {{-- คนไข้ที่จำวันเกิดไม่ได้ บอกได้แต่อายุ (ข้อมูลเก่าของ TMC ก็มีแต่ "45ปี 6เดือน")
             tabindex="-1" ทั้งคู่ เพื่อไม่ให้ขวางการกด Tab ไล่กรอกฟอร์มตามปกติ — ตั้งใจให้คลิกเอง --}}
        <div class="input-group input-group-sm mt-1" style="max-width:15rem;">
            <span class="input-group-text" title="ใช้เมื่อคนไข้จำวันเกิดไม่ได้">ไม่ทราบวันเกิด? อายุ</span>
            <input type="number" class="form-control" id="age_to_birth" min="0" max="130"
                   placeholder="ปี" tabindex="-1" autocomplete="off">
            <button class="btn btn-outline-secondary" type="button" id="age_to_birth_btn" tabindex="-1">คำนวณ</button>
        </div>

        {!! $errors->first('birth_date', '<p class="help-block">:message</p>') !!}
    </div>
<div class="col-2 form-group {{ $errors->has('tel') ? 'has-error' : '' }} {{ isset($patient) && $patient->phone_changed ? 'border-red' : '' }}">
    <label for="tel" class="control-label">{{ 'เบอร์โทร' }}</label>
    <input class="form-control" name="tel" type="text" id="tel" value="{{ isset($patient->tel) ? $patient->tel : '' }}" required>
    {!! $errors->first('tel', '<p class="help-block">:message</p>') !!}

    {{-- อีเมล (2569-09-30) — ใช้ติดตามคนไข้ที่ไม่มีเบอร์ไทย/ไลน์ เช่น คนไข้ TMC ต่างชาติ · ไม่บังคับ --}}
    <label for="email" class="control-label small mt-1 mb-0">อีเมล</label>
    <input class="form-control form-control-sm" name="email" type="email" id="email" maxlength="191"
           value="{{ old('email', $patient->email ?? '') }}" placeholder="name@example.com">
    {!! $errors->first('email', '<p class="help-block">:message</p>') !!}

    <div class="row">
        <!-- ช่องติ๊กแจ้งเปลี่ยนเบอร์โทร -->
        <div class="col-12 form-group">
            <input type="hidden" name="phone_changed" value="0"> <!-- ค่าพื้นฐานเป็น 0 -->
            <input type="checkbox" name="phone_changed" id="phone_changed" value="1" 
                   @if (isset($patient) && $patient->phone_changed) checked @endif>
            <label for="phone_changed" class="control-label">แจ้งเปลี่ยนเบอร์โทร
            <small class="text-success">ติ๊กออกเมื่อมีการเปลี่ยนเบอร์โทรแล้ว</small>
            </label>
        </div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('id_card_number');
    const feedback = document.getElementById('id_card_feedback');

    // ตรวจสอบว่ามีตัวแปร $patient อยู่หรือไม่ (เพื่อจะรู้ว่าเป็นหน้า create หรือ edit)
    @php
        $patientIdForRoute = isset($patient) ? $patient->id : 0;
    @endphp

    // เลขบัตร / พาสปอร์ตตรงกับคนไข้ที่มีอยู่ = คนเดิม → ขึ้นปุ่มไปคนนั้นเลย (2569-10-28 · ไม่เทียบชื่อ เพราะชื่อซ้ำได้)
    const ppInput = document.getElementById('passport');
    function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function checkDup() {
        const idCard = input.value.trim();
        const pp = ppInput ? ppInput.value.trim() : '';
        if (!idCard && !pp) { feedback.innerHTML = ''; input.classList.remove('is-invalid'); return; }
        fetch("{{ route('check.id_card', ['id' => $patientIdForRoute]) }}", {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": "{{ csrf_token() }}", "Accept": "application/json" },
            body: JSON.stringify({ id_card_number: idCard, passport: pp })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.exists) { feedback.innerHTML = ''; input.classList.remove('is-invalid'); return; }
            input.classList.add('is-invalid');
            feedback.innerHTML = '<div class="alert alert-warning py-2 px-2 mt-1 mb-0 small" style="color:#7c2d12;">'
                + '<b>' + esc(data.by) + 'นี้เป็นของคนไข้ที่มีอยู่แล้ว</b><br>HN <b>' + esc(data.code) + '</b> ' + esc(data.name)
                + (data.last ? '<br>มาครั้งล่าสุด ' + esc(data.last) : '')
                + '<div class="d-flex flex-wrap gap-1 mt-1">'
                + '<a class="btn btn-sm btn-primary" href="' + esc(data.list_url) + '">ไปที่คนไข้นี้ (เปิด visit)</a>'
                + '<a class="btn btn-sm btn-outline-secondary" href="' + esc(data.edit_url) + '">ดู/แก้ข้อมูลคนไข้</a>'
                + '</div></div>';
        })
        .catch(() => {});
    }
    input.addEventListener('blur', checkDup);
    if (ppInput) ppInput.addEventListener('blur', checkDup);
});
</script>


<!-- เพิ่ม CSS สำหรับ border สีแดง -->
<style>
    .border-red {
        border: 2px solid red;
    }

    #phone_changed:checked + label {
        color: red; /* เปลี่ยนสี label เป็นสีแดงเมื่อ checkbox ถูกติ๊ก */
    }
</style>

</div>
    </div>

</div>

<div class="row">
  <div class="col-3 form-group {{ $errors->has('nationality') ? 'has-error' : '' }}">
    <label for="nationality" class="control-label">{{ 'สัญชาติ' }}</label>
    <input type="text" class="form-control" name="nationality" id="nationality" placeholder="ค้นหา Country ID" value="{{ isset($patient->nationality) ? $patient->nationality : '' }}" required>
    {!! $errors->first('nationality', '<p class="help-block">:message</p>') !!}
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // คำนำหน้า -> เพศ (ตาราง Patient::$prefixSex — เพิ่ม ด.ช./ด.ญ./พระ/สามเณร/แม่ชี/Master 2569-10-14) · ไม่มีในตาราง = ไม่เปลี่ยนเพศ
    const prefixToSex = @json(\App\Models\Patient::$prefixSex);
    const prefixSelect = document.getElementById('prefix');
    const sexSelect = document.getElementById('sex');
    const prefixOther = document.getElementById('prefix_other');
    const ageHint = document.getElementById('prefix-age-hint');

    // เด็กอายุต่ำกว่า 15 ปี ใช้ ด.ช./ด.ญ. — เตือนเฉย ๆ ไม่เปลี่ยนให้เอง
    function checkAge() {
        if (!ageHint) return;
        const bd = document.getElementById('birth_date');
        const m = bd ? /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec((bd.value || '').trim()) : null;
        let msg = '';
        if (m) {
            const y = +m[3] - (+m[3] > 2400 ? 543 : 0);
            const born = new Date(y, +m[2] - 1, +m[1]), now = new Date();
            let age = now.getFullYear() - born.getFullYear();
            if (now < new Date(now.getFullYear(), born.getMonth(), born.getDate())) age--;
            const v = parseInt(prefixSelect.value);
            if (age >= 0 && age < 15 && [1, 2, 3].indexOf(v) !== -1) msg = 'อายุ ' + age + ' ปี — เด็กต่ำกว่า 15 ปีใช้ ด.ช. / ด.ญ.';
            if (age >= 15 && [8, 9].indexOf(v) !== -1) msg = 'อายุ ' + age + ' ปี — 15 ปีขึ้นไปใช้ นาย / นางสาว';
        }
        ageHint.textContent = msg;
        ageHint.classList.toggle('d-none', !msg);
    }

    prefixSelect.addEventListener('change', function () {
        const v = parseInt(this.value);
        if (prefixToSex[v] !== undefined) sexSelect.value = prefixToSex[v];
        if (prefixOther) {
            prefixOther.classList.toggle('d-none', v !== 99);
            if (v === 99) prefixOther.focus();
        }
        checkAge();
    });
    document.addEventListener('change', function (e) { if (e.target && e.target.id === 'birth_date') checkAge(); });
    document.addEventListener('focusout', function (e) { if (e.target && e.target.id === 'birth_date') checkAge(); });
    // ปฏิทินเลือกวัน (jQuery) ยิง change แบบ jQuery — ตัวฟังแบบ native ข้างบนไม่ได้ยิน
    if (window.jQuery) window.jQuery(document).on('change', '#birth_date', checkAge);
    checkAge();
});
</script>

<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

<script type="text/javascript">
    $(function() {
        var countries = @json($countries);
        
        $("#nationality").autocomplete({
            source: countries.map(country => ({
                label: country.country_id + " - " + (country.nation_name_th || 'ไม่มีข้อมูล'), // แสดงข้อมูลสัญชาติภาษาไทย
                value: country.country_id,
                nationality: country.nation_name_th
            })),
            select: function(event, ui) {
                $('#nationality').val(ui.item.value);
                return false;
            }
        });
    });
</script>

    <div class="col-3 form-group {{ $errors->has('education') ? 'has-error' : '' }}">
        <label for="education" class="control-label">{{ 'การศึกษาสูงสุด' }}</label>
        {{-- <input class="form-control" name="education" type="number" id="education"
            value="{{ isset($patient->education) ? $patient->education : '' }}"> --}}

        <select class="form-control" name="education" id="education" required>
            @foreach (App\Models\Patient::$educationOption as $key => $value)
            <option value="{{ $key }}" @if (isset($patient->education) && $key == $patient->education) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('education', '<p class="help-block">:message</p>') !!}
    </div>



    {{-- สิทธิการรักษา — เก็บที่ทะเบียนเพื่อให้ใบนัดดึงไปพิมพ์ได้เอง (คลินิกวัณโรคขอ 2569-09-29)
         ช่อง รพ. ซ่อนเองเมื่อเลือกสิทธิที่ไม่ผูกกับสถานพยาบาลหลัก (เบิกราชการ / จ่ายเอง)
         ซ่อนอย่างเดียว ไม่ล้างค่า เผื่อผู้ใช้กดพลาดแล้วเลือกกลับ ข้อมูลเดิมจะได้ไม่หาย --}}
    <div class="col-3 form-group {{ $errors->has('health_right') ? 'has-error' : '' }}">
        <label for="health_right" class="control-label">สิทธิการรักษา</label>
        <select class="form-control" name="health_right" id="health_right">
            @foreach (App\Models\Patient::$healthRightOption as $key => $value)
            <option value="{{ $key }}" @if (isset($patient->health_right) && $key == $patient->health_right) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('health_right', '<p class="help-block">:message</p>') !!}
    </div>

    <div class="col-3 form-group {{ $errors->has('health_right_hospital') ? 'has-error' : '' }}" id="health_right_hospital_wrap">
        <label for="health_right_hospital" class="control-label">สถานพยาบาลหลัก</label>
        <input class="form-control" name="health_right_hospital" type="text" id="health_right_hospital"
               maxlength="255" placeholder="เช่น รพ.วังเหนือ"
               value="{{ isset($patient->health_right_hospital) ? $patient->health_right_hospital : '' }}">
        {!! $errors->first('health_right_hospital', '<p class="help-block">:message</p>') !!}
    </div>

    <script>
    (function () {
        var sel  = document.getElementById('health_right');
        var wrap = document.getElementById('health_right_hospital_wrap');
        if (!sel || !wrap) return;
        var noHospital = @json(App\Models\Patient::$healthRightNoHospital);
        function sync() {
            wrap.style.display = noHospital.indexOf(sel.value) !== -1 ? 'none' : '';
        }
        sel.addEventListener('change', sync);
        sync();
    })();
    </script>

    <div class="col-3 form-group {{ $errors->has('marital_status') ? 'has-error' : '' }}">
        <label for="marital_status" class="control-label">{{ 'สถานภาพ' }}</label>
        {{-- <input class="form-control" name="marital_status" type="number" id="marital_status"
        value="{{ isset($patient->marital_status) ? $patient->marital_status : '' }}"> --}}
        <select class="form-control" name="marital_status" id="marital_status">
            @foreach (App\Models\Patient::$maritalStatusOption as $key => $value)
            <option value="{{ $key }}" @if (isset($patient->marital_status) && $key == $patient->marital_status) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('marital_status', '<p class="help-block">:message</p>') !!}
    </div>

    <div class="col-3 form-group {{ $errors->has('status') ? 'has-error' : '' }}">
        <label for="status" class="control-label">{{ 'Status' }}</label>
        {{-- <input class="form-control" name="status" type="number" id="status"
            value="{{ isset($patient->status) ? $patient->status : '' }}"> --}}

        <select class="form-control" name="status" id="status">
            @foreach (App\Models\Patient::$statusOption as $key => $value)
            <option value="{{ $key }}" @if (isset($patient->status) && $key == $patient->status) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('status', '<p class="help-block">:message</p>') !!}
    </div>
</div>

<div class="row">
    <div class="col-4 form-group {{ $errors->has('occupation') ? 'has-error' : '' }}">
        <label for="occupation" class="control-label">{{ 'อาชีพ' }}</label>
        {{-- <input class="form-control" name="occupation" type="number" id="occupation"
            value="{{ isset($patient->occupation) ? $patient->occupation : '' }}"> --}}
        <select class="form-control" name="occupation" id="occupation">
            @foreach (App\Models\Patient::$occupationOption as $key => $value)
            <option value="{{ $key }}" @if (isset($patient->occupation) && $key == $patient->occupation) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('occupation', '<p class="help-block">:message</p>') !!}
    </div>

    <div id="other_occupation_div" class="col-8 form-group {{ $errors->has('other_occupation') ? 'has-error' : '' }}" style="display:none" >
        <label for="other_occupation" class="control-label">{{ 'อาชีพ' }}</label>
        <input class="form-control" name="other_occupation" type="text" id="other_occupation" value="{{ isset($patient->other_occupation) ? $patient->other_occupation : '' }}" >
        {!! $errors->first('other_occupation', '<p class="help-block">:message</p>') !!}
    </div>

    <div id="organization_div" class="col-8 orm-group {{ $errors->has('organization') ? 'has-error' : '' }}" style="{{ isset($patient->occupation)  && $patient->occupation ==10  ? '' : 'display:none' }}">
        <label for="organization" class="control-label">{{ 'ชื่อสถานประกอบการ' }}</label>
        <input class="form-control" name="organization" type="text" id="organization" value="{{ isset($patient->organization) ? $patient->organization : '' }}">
        {!! $errors->first('organization', '<p class="help-block">:message</p>') !!}
    </div>
</div>




<div class="form-group {{ $errors->has('congenitaldisease') ? 'has-error' : '' }}">
    <label for="congenitaldisease" class="control-label">{{ 'โรคประจำตัว' }}</label>
    <input class="form-control" name="congenitaldisease" type="text" id="congenitaldisease" 
        value="{{ isset($patient->congenitaldisease) ? $patient->congenitaldisease : '' }}" 
        required onblur="autoFillDefault(this)">
    {!! $errors->first('congenitaldisease', '<p class="help-block">:message</p>') !!}
</div>

<div class="form-group {{ $errors->has('drug_allergy') ? 'has-error' : '' }}">
    <label for="drug_allergy" class="control-label">{{ 'ประวัติแพ้ยา' }}</label>
    <input class="form-control" name="drug_allergy" type="text" id="drug_allergy" 
        value="{{ isset($patient->drug_allergy) ? $patient->drug_allergy : '' }}" 
        required onblur="autoFillDefault(this)">
    {!! $errors->first('drug_allergy', '<p class="help-block">:message</p>') !!}
</div>

{{-- ประวัติวัคซีน STIs (2569-10-21) — ค่าเดียวกับช่องในหน้าตรวจ แก้ที่ไหนก็เห็นทุกที่ --}}
<div class="form-group">
    <label for="sti_vaccine_history" class="control-label">ประวัติการได้รับวัคซีน STIs</label>
    <textarea class="form-control" name="sti_vaccine_history" id="sti_vaccine_history" rows="2" maxlength="1000"
        placeholder="เช่น HPV 2 เข็ม (ปี 67) · ไวรัสตับอักเสบบี ครบ 3 เข็ม · Mpox 1 เข็ม · ไม่เคยได้รับ">{{ $patient->sti_vaccine_history ?? '' }}</textarea>
</div>

<script>
function autoFillDefault(inputField) {
    if (inputField.value.trim() === "") {
        inputField.value = "ปฏิเสธ";
    }
}
</script>




<!-- ลบปุ่ม submit ที่นี่ -->
<input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>
<!-- <script src="/js/jquery.js"></script>
<script src="/js/jquery.datetimepicker.full.min.js"></script> -->

<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>


<script type="text/javascript">
    $(function() {


        $('.date').datepicker({
            language: 'th-th',
        });

        // ---------- วันเกิด: รับได้ทั้ง พ.ศ. และ ค.ศ. แล้วบอกอายุให้ ----------
        // ฐานข้อมูลเก็บ birth_date เป็น พ.ศ. (Utillity::th2dbDate ไม่ได้ลบ 543)
        // ถ้าปล่อยให้กรอก ค.ศ. เข้าไปตรงๆ อายุจะเพี้ยนไป 543 ปี
        (function () {
            var $inp  = $('#birth_date');
            var $hint = $('#birth_date_hint');
            if (!$inp.length) return;

            function parse(v) {
                var m = String(v || '').trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{3,4})$/);
                if (!m) return null;
                var d = +m[1], mo = +m[2], y = +m[3];
                if (mo < 1 || mo > 12 || d < 1 || d > 31) return null;

                // ปีเกิดจริงไม่มีทางคาบเกี่ยวกัน: พ.ศ. 2400+ ส่วน ค.ศ. 1900-2100
                var wasAD = false;
                if (y < 2400) { y += 543; wasAD = true; }
                return { d: d, m: mo, be: y, ad: y - 543, wasAD: wasAD };
            }

            function show(msg, cls) { $hint.attr('class', 'form-text mt-1 ' + cls).html(msg); }

            function update(convert) {
                var p = parse($inp.val());
                if (!p) { $hint.attr('class', 'form-text mt-1').text(''); return; }

                // เขียนกลับเป็น พ.ศ. เสมอ เพื่อให้ค่าที่ส่งไปเซิร์ฟเวอร์ถูกต้อง
                if (convert && p.wasAD) {
                    $inp.val(('0'+p.d).slice(-2) + '/' + ('0'+p.m).slice(-2) + '/' + p.be);
                }

                var bd = new Date(p.ad, p.m - 1, p.d);
                if (bd.getDate() !== p.d || (bd.getMonth() + 1) !== p.m) {
                    show('<i class="fa fa-triangle-exclamation me-1"></i>วันที่ไม่ถูกต้อง', 'text-danger');
                    return;
                }

                var now = new Date();
                if (bd > now) { show('<i class="fa fa-triangle-exclamation me-1"></i>วันเกิดอยู่ในอนาคต', 'text-danger'); return; }

                var yrs = now.getFullYear() - bd.getFullYear();
                var mos = now.getMonth() - bd.getMonth();
                if (now.getDate() < bd.getDate()) mos--;
                if (mos < 0) { yrs--; mos += 12; }
                // วันที่เกินจากเดือนเต็ม — ยืมจำนวนวันของเดือนก่อนหน้า (เกิด 22/09 ถึง 08/10 = 16 วัน) · คลินิกขอ 2569-10-08
                // นับจาก "วันครบเดือนล่าสุด" ถึงวันนี้ — เกิดวันที่ 31 แต่เดือนนั้นมีแค่ 28/30 วัน ให้ใช้วันสุดท้ายของเดือนแทน (ไม่ติดลบ)
                var am = bd.getMonth() + mos, ay = bd.getFullYear() + yrs + Math.floor(am / 12); am = am % 12;
                var anchor = new Date(ay, am, Math.min(bd.getDate(), new Date(ay, am + 1, 0).getDate()));
                var today0 = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                var days = Math.round((today0 - anchor) / 86400000);

                if (yrs > 130) { show('<i class="fa fa-triangle-exclamation me-1"></i>อายุ ' + yrs + ' ปี — ตรวจสอบปีอีกครั้ง', 'text-danger'); return; }

                var txt = '<strong>อายุ ' + yrs + ' ปี ' + mos + ' เดือน ' + days + ' วัน</strong>'
                        + ' · พ.ศ. ' + p.be + ' / ค.ศ. ' + p.ad;
                if (p.wasAD && convert) txt += ' <span class="text-success">(แปลงจาก ค.ศ. ให้แล้ว)</span>';
                show(txt, 'text-muted');
            }

            $inp.on('input', function () { update(false); });      // พิมพ์อยู่ ยังไม่แปลง กันเลขกระโดดกลางคัน
            $inp.on('blur change', function () { update(true); }); // ออกจากช่อง/เลือกจากปฏิทิน ค่อยแปลง
            $inp.on('changeDate', function () { update(true); });  // ปฏิทินไทยยิง event นี้
            update(false);                                         // ตอนเปิดหน้า (โหมดแก้ไข)

            // ---------- กรอกอายุ -> เดาวันเกิดย้อนกลับ ----------
            // ได้แค่ "ปีเกิด" เท่านั้น จึงลงเป็น 1 ม.ค. ของปีนั้น และบอกให้ชัดว่าเป็นค่าประมาณ
            // (ห้ามเดาวัน/เดือนมั่ว เพราะจะดูเหมือนข้อมูลจริงทั้งที่ไม่ใช่)
            function fromAge() {
                var age = parseInt($('#age_to_birth').val(), 10);
                if (isNaN(age) || age < 0 || age > 130) {
                    show('<i class="fa fa-triangle-exclamation me-1"></i>กรอกอายุเป็นตัวเลข 0-130', 'text-danger');
                    return;
                }
                var beYear = (new Date().getFullYear() + 543) - age;
                $inp.val('01/01/' + beYear);
                show('<strong>อายุ ' + age + ' ปี</strong> · พ.ศ. ' + beYear + ' / ค.ศ. ' + (beYear - 543)
                   + ' <span style="color:#b45309;">(ค่าประมาณ — ลงเป็น 1 ม.ค. เพราะทราบแค่อายุ)</span>',
                     'text-muted');
            }

            $('#age_to_birth_btn').on('click', fromAge);
            $('#age_to_birth').on('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); fromAge(); }   // กัน Enter ไป submit ฟอร์มทั้งใบ
            });
        })();

        $('#occupation').change(function() {
            $('#organization_div').hide()
            $('#other_occupation_div').hide()
            $('#organization').val("")
            $('#other_occupation').val("")
            switch ($('#occupation').val()) {
                case '10':
                    $('#organization_div').show()
                    
                    
                    break;
                case '11':
                    $('#other_occupation_div').show()
                    
                    break;
            }
        })

    });
</script>
