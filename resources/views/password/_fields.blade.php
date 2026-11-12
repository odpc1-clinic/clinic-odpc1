{{-- ช่องกรอกรหัสผ่าน + ตัวตรวจเกณฑ์สด ๆ ขณะพิมพ์
     ใช้ร่วมกันทั้งหน้าที่ผู้ใช้เปลี่ยนเอง และหน้าที่แอดมินเปิดให้
     ตรวจฝั่งหน้าจอเพื่อให้เห็นทันที แต่ของจริงตรวจซ้ำที่เซิร์ฟเวอร์เสมอ (App\Rules\StrongPassword) --}}
<div class="mb-3">
    <label class="form-label fw-bold">รหัสผ่านใหม่</label>
    <input type="password" name="password" id="pw1" class="form-control form-control-lg" autocomplete="new-password" required autofocus>
</div>
<div class="mb-3">
    <label class="form-label fw-bold">พิมพ์รหัสผ่านใหม่อีกครั้ง</label>
    <input type="password" name="password_confirmation" id="pw2" class="form-control form-control-lg" autocomplete="new-password" required>
</div>

<div class="mb-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="pwShow">
        <label class="form-check-label" for="pwShow">แสดงรหัสผ่านที่พิมพ์</label>
    </div>
</div>

<ul class="list-unstyled small mb-3" id="pwRules" style="line-height:1.9">
    <li data-r="len"><span class="mark">○</span> ยาวอย่างน้อย 12 ตัวอักษร</li>
    <li data-r="upper"><span class="mark">○</span> มีตัวพิมพ์ใหญ่ A-Z</li>
    <li data-r="lower"><span class="mark">○</span> มีตัวพิมพ์เล็ก a-z</li>
    <li data-r="digit"><span class="mark">○</span> มีตัวเลข 0-9</li>
    <li data-r="special"><span class="mark">○</span> มีอักขระพิเศษ เช่น ! @ # $</li>
    <li data-r="match"><span class="mark">○</span> สองช่องตรงกัน</li>
</ul>

<div class="alert alert-light border small">
    <b>ตั้งยังไงให้จำได้และผ่านเกณฑ์</b><br>
    ใช้คำอังกฤษที่ตัวเองจำได้ (ขึ้นต้นตัวใหญ่) + ตัวเลขที่ไม่ใช่วันเกิด + อักขระพิเศษ 1 ตัว<br>
    ตัวอย่างรูปแบบ — <code>Sunflower2569!</code> · <code>ChiangMai#Rain26</code> · <code>MyCat@Home2569</code><br>
    <span class="text-danger">อย่าใช้ตัวอย่างข้างบนตรง ๆ นะคะ ให้เปลี่ยนเป็นคำของตัวเอง</span>
</div>

<script>
(function () {
    var p1 = document.getElementById('pw1'), p2 = document.getElementById('pw2');
    var box = document.getElementById('pwRules'), btn = document.getElementById('pwSubmit');
    document.getElementById('pwShow').addEventListener('change', function () {
        var t = this.checked ? 'text' : 'password';
        p1.type = t; p2.type = t;
    });
    function check() {
        var v = p1.value;
        var r = {
            len:     v.length >= 12,
            upper:   /[A-Z]/.test(v),
            lower:   /[a-z]/.test(v),
            digit:   /[0-9]/.test(v),
            special: /[^A-Za-z0-9]/.test(v),
            match:   v.length > 0 && v === p2.value
        };
        var all = true;
        box.querySelectorAll('li').forEach(function (li) {
            var ok = r[li.dataset.r];
            if (!ok) { all = false; }
            li.querySelector('.mark').textContent = ok ? '✔' : '○';
            li.style.color = ok ? '#198754' : '#6c757d';
        });
        // หาปุ่มตอนตรวจ ไม่ใช่ตอนสคริปต์เริ่ม — หน้าเปลี่ยนรหัสของตัวเองวางปุ่มไว้ "หลัง" ไฟล์นี้
        // ตอนสคริปต์รันปุ่มยังไม่เกิด ได้ null แล้วปุ่มเป็นสีจางกดไม่ได้ตลอด (ผู้ใช้เจอ 2569-11-02)
        var b = btn || document.getElementById('pwSubmit');
        if (b) { b.disabled = !all; }
    }
    p1.addEventListener('input', check);
    p2.addEventListener('input', check);
    // เบราว์เซอร์เติมรหัสให้เอง (autofill) บางทีไม่ยิง input — ตรวจซ้ำตอน change และหลังหน้าโหลดเสร็จ
    p1.addEventListener('change', check);
    p2.addEventListener('change', check);
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', check); } else { setTimeout(check, 0); }
    check();
})();
</script>
