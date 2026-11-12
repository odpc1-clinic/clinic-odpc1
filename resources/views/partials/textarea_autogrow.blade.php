{{--
    ช่องพิมพ์ข้อความยาว (textarea) ยืดตามเนื้อหาเอง — ใช้ทั้งระบบ

    ทำไมต้องมี: หลายช่องตั้ง rows=2 ไว้ แต่ข้อความจริงยาวหลายบรรทัด (Hx วัณโรค,
    วัตถุประสงค์นัด, รายการก่อนพบแพทย์) พยาบาลต้องเลื่อนสกรอลล์ในช่องเล็ก ๆ
    เพื่ออ่านของที่ตัวเองพิมพ์ไว้ ซึ่งพลาดง่ายเพราะมองไม่เห็นทั้งก้อน

    เดิมมีคนเขียนตัวยืดแบบนี้ไว้แล้ว 3-4 จุด แต่ผูกกับช่องของตัวเองเฉพาะหน้านั้น
    (external_lab, lab-sub/table, field_history_modal) ตัวนี้ทำให้ครบทุกช่องทีเดียว
    ของเดิมปล่อยไว้ได้ ตรรกะเหมือนกันและสั่งซ้ำกี่ครั้งก็ได้ผลเท่าเดิม

    กับดักที่ต้องระวัง 3 ข้อ:

    1. ช่องที่ยัง "ซ่อนอยู่" วัดความสูงไม่ได้ (scrollHeight = 0) หน้าตรวจเรนเดอร์
       ทุกแท็บพร้อมกันแล้วซ่อนด้วย CSS ถ้ายืดตอนโหลดจะได้ความสูงผิดทั้งหมด
       -> ใช้ IntersectionObserver รอจนช่องโผล่มาจริงค่อยยืด

    2. เนื้อหาที่โหลดผ่าน AJAX รัน <script> ที่ติดมาไม่ได้ (ดู CLAUDE.md ข้อ 2)
       -> ผูก event ที่ document + MutationObserver คอยรับช่องที่เพิ่งถูกใส่เข้ามา

    3. ข้อความยาวมาก ๆ ถ้ายืดไม่จำกัดจะดันปุ่มบันทึกตกจอ
       -> จำกัดที่ 60% ของความสูงจอ เกินจากนั้นค่อยให้สกรอลล์ในช่อง

    ไม่อยากให้ช่องไหนยืด ใส่ data-no-autogrow ที่ช่องนั้น
--}}
<script>
(function () {
    'use strict';

    function maxHeight() {
        return Math.max(160, Math.round(window.innerHeight * 0.6));
    }

    function grow(el) {
        if (!el || el.tagName !== 'TEXTAREA') return;
        if (el.hasAttribute('data-no-autogrow')) return;

        // ยังไม่ถูกแสดงผล -> วัดไม่ได้ ปล่อยไว้ให้ observer มาจัดการตอนโผล่
        if (!el.offsetParent && !el.offsetHeight) return;

        // ความสูงเดิมตาม rows = ขั้นต่ำ ไม่ให้ช่องหดเล็กกว่าที่ออกแบบไว้ตอนลบข้อความ
        // วัดครั้งเดียวแล้วจำไว้ เพราะต้องล้าง inline height ก่อนวัดซึ่งทำให้จอกระพริบ
        var min = parseInt(el.getAttribute('data-autogrow-min') || '0', 10);
        if (!min) {
            var keep = el.style.height;
            el.style.height = '';
            min = el.clientHeight || 0;
            el.style.height = keep;
            if (min) el.setAttribute('data-autogrow-min', min);
        }

        // .form-control ของ Bootstrap เป็น border-box แต่ scrollHeight ไม่รวมเส้นขอบ
        // ถ้าไม่บวกคืน จะเหลือสกรอลล์บาง ๆ 1-2 px ค้างอยู่ทุกช่อง
        var cs = window.getComputedStyle(el);
        var edge = cs.boxSizing === 'border-box'
            ? (parseFloat(cs.borderTopWidth) || 0) + (parseFloat(cs.borderBottomWidth) || 0)
            : 0;

        el.style.height = 'auto';
        var want = el.scrollHeight + edge;
        var cap  = maxHeight();

        if (want > cap) {
            el.style.height = cap + 'px';
            el.style.overflowY = 'auto';
        } else {
            el.style.height = Math.max(want, min) + 'px';
            el.style.overflowY = 'hidden';
        }
    }

    var io = ('IntersectionObserver' in window)
        ? new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (!e.isIntersecting) return;
                io.unobserve(e.target);
                grow(e.target);
            });
        })
        : null;

    function register(el) {
        if (el.hasAttribute('data-autogrow-on')) return;
        el.setAttribute('data-autogrow-on', '1');
        if (el.offsetParent || el.offsetHeight) grow(el);
        else if (io) io.observe(el);
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        Array.prototype.forEach.call(root.querySelectorAll('textarea'), register);
    }

    // พิมพ์เอง / สคริปต์อื่นยิง input event มา (เช่นปุ่ม "ใช้แทน" ในกล่องประวัติช่อง)
    document.addEventListener('input', function (e) {
        if (e.target && e.target.tagName === 'TEXTAREA') grow(e.target);
    });

    // กันพลาดกรณีไม่มี IntersectionObserver — อย่างน้อยพอคลิกเข้าช่องก็ยืดให้
    document.addEventListener('focusin', function (e) {
        if (e.target && e.target.tagName === 'TEXTAREA') grow(e.target);
    });

    function boot() {
        scan(document);
        if (!('MutationObserver' in window)) return;
        new MutationObserver(function (muts) {
            for (var i = 0; i < muts.length; i++) {
                var added = muts[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    var n = added[j];
                    if (!n || n.nodeType !== 1) continue;
                    if (n.tagName === 'TEXTAREA') register(n);
                    else scan(n);
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    // ย่อ/ขยายหน้าต่างแล้วเพดานความสูงเปลี่ยน ต้องคิดใหม่ให้ช่องที่ชนเพดานอยู่
    var t = null;
    window.addEventListener('resize', function () {
        clearTimeout(t);
        t = setTimeout(function () {
            Array.prototype.forEach.call(document.querySelectorAll('textarea[data-autogrow-on]'), grow);
        }, 150);
    });
})();
</script>
