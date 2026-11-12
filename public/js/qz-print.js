/**
 * QZ Tray helper — สั่งพิมพ์เข้าเครื่องที่ระบุแบบเงียบ (ไม่เด้ง dialog)
 * ต้องโหลด /js/qz-tray.js ก่อนไฟล์นี้ (คัดลอกมาจากโฟลเดอร์ติดตั้ง QZ Tray)
 * ถ้า qz-tray.js ไม่ถูกโหลด หรือ QZ Tray ไม่ได้เปิด -> window.QZPrint = null (โค้ดหน้าเว็บจะ fallback ไป window.print())
 */
(function () {
    if (typeof qz === 'undefined') {
        console.warn('[QZ] qz-tray.js ยังไม่ถูกโหลด — จะใช้ window.print() แทน');
        window.QZPrint = null;
        return;
    }

    // ให้ QZ เชื่อถือเว็บนี้ผ่าน cert+signature ที่เซ็นฝั่ง Laravel (จะได้ไม่เด้ง Allow ทุกครั้ง)
    qz.security.setCertificatePromise(function (resolve, reject) {
        fetch('/qz/certificate', { credentials: 'same-origin' })
            .then(function (r) { return r.text(); }).then(resolve).catch(reject);
    });
    try { qz.security.setSignatureAlgorithm('SHA512'); } catch (e) { /* qz เก่า */ }
    qz.security.setSignaturePromise(function (toSign) {
        return function (resolve, reject) {
            fetch('/qz/sign?request=' + encodeURIComponent(toSign), { credentials: 'same-origin' })
                .then(function (r) { return r.text(); }).then(resolve).catch(reject);
        };
    });

    function connect() {
        if (qz.websocket.isActive()) return Promise.resolve();
        return qz.websocket.connect({ retries: 1, delay: 1 });
    }

    function resolvePrinter(printerName) {
        return printerName ? qz.printers.find(printerName) : qz.printers.getDefault();
    }

    window.QZPrint = {
        connect: connect,

        // พิมพ์ HTML string เข้าเครื่องที่ระบุ (ไม่ระบุ = เครื่อง default)
        // opts.width/height (นิ้ว) = ขนาดหน้ากระดาษ ให้ QZ แบ่งหน้าถูก (สำคัญกับ label ต่อเนื่อง)
        html: function (printerName, html, opts) {
            opts = opts || {};
            return connect()
                .then(function () { return resolvePrinter(printerName); })
                .then(function (p) {
                    var cfgOpts = { margins: 0 };
                    if (opts.width && opts.height) {
                        cfgOpts.size = { width: opts.width, height: opts.height };
                        cfgOpts.units = 'in';
                        cfgOpts.rasterize = true; // render เป็นภาพตามขนาด กันเพี้ยน/ตัดครึ่ง
                        // density = DPI ของเครื่อง (ZD220 = 203) ให้ภาพขนาดตรง ไม่ย่อไปกระจุกซ้าย
                        if (opts.density) { cfgOpts.density = opts.density; }
                    }
                    var cfg = qz.configs.create(p, cfgOpts);
                    return qz.print(cfg, [{ type: 'html', format: 'plain', data: html }]);
                });
        },

        // พิมพ์หน้าปัจจุบัน — ตัดแถบปุ่ม (.qz-noprint) และ CSS ตกแต่งจอ (.screen-only) ออก
        // เพื่อให้ได้ layout พิมพ์จริง ไม่ใช่หน้า preview (พื้นหลังเทา/เงา/จัดกลาง)
        printCurrentPage: function (printerName, opts) {
            var clone = document.documentElement.cloneNode(true);
            clone.querySelectorAll('.qz-noprint, .screen-only').forEach(function (el) { el.remove(); });
            return window.QZPrint.html(printerName, '<!doctype html>' + clone.outerHTML, opts);
        }
    };
})();
