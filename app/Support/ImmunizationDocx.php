<?php

namespace App\Support;

/**
 * สร้างไฟล์ Word (.docx) ของ IMMUNIZATION CERTIFICATE — หน้าตาตามแบบฟอร์ม Word เดิมของ TMC
 * (A4 · ขอบกระดาษเท่าต้นฉบับ · TH Sarabun PSK 16) ให้พยาบาลโหลดไปแก้ต่อได้ เช่น วัคซีนเยอะจนล้นหน้า
 *
 * เขียน XML เองแทนการลงไลบรารี PhpWord — ใช้แค่ข้อความ ตาราง และรูปโลโก้ 1 รูป
 * ข้อมูลมาจาก VisitVaccineController::certificateData() ชุดเดียวกับหน้าพิมพ์
 */
class ImmunizationDocx
{
    private const FONT = 'TH Sarabun PSK';
    private const SIZE = 28;       // ครึ่งพอยต์ = 14pt (ลดจาก 16 — TMC ขอ 2569-11-10)
    private const TEXT_W = 9214;   // ความกว้างพื้นที่พิมพ์ (twip) = A4 11906 − ซ้าย 1418 − ขวา 1274

    /** คืน path ไฟล์ชั่วคราว (ผู้เรียกลบทิ้งหลังส่ง) */
    public static function build(array $d, ?array $doctor, string $logoPath): string
    {
        $patient = $d['patient'];
        $b = [];

        if (is_file($logoPath)) { $b[] = self::logo($logoPath); }
        $b[] = self::p([self::r('IMMUNIZATION CERTIFICATE', true, 36)], 'center');
        $b[] = self::p([self::r('Preventive Medicine Service Center')], 'center', 0, 0);
        $b[] = self::p([self::r('Office of Disease Prevention and Control region 1, Chiangmai, Thailand')], 'center');
        // เว้นวันที่ไว้ให้ปั๊มเองตอนคนไข้มารับใบ (TMC ขอ 2569-11-10)
        $b[] = self::p([self::r('Issue date ..........................')], 'right');
        $b[] = self::p([self::r('To whom it may concern,')]);
        // ชื่อ/พาสปอร์ต/วันเกิด: ตัวปกติ ขีดเส้นใต้ · ชิดซ้าย (TMC ขอ 2569-11-10)
        $b[] = self::p([self::r('This is the verified immunization record certifying that  '),
                        self::r($d['nameEn'] ?: '..............................', false, self::SIZE, true)]);
        $b[] = self::p([self::r('Passport no  '), self::r($patient->passport ?: ($patient->id_card_number ?: '-'), false, self::SIZE, true),
                        self::r('        date of birth  '), self::r($d['dobEn'] ?: '-', false, self::SIZE, true)]);
        $b[] = self::p([self::r('has received vaccines and immunization status has been updated as follows:')]);
        $b[] = self::table($d['rows'], (int) $d['doseCols']);

        if ($d['hasHome']) { $b[] = self::p([self::r('† Oral vaccine supplied by this center, self-administered.', false, 24)], 'left', 0, 0); }

        // ลายเซ็นชิดขวา (เยื้องซ้ายแล้วจัดกึ่งกลาง) เหมือนต้นฉบับ
        // ยังไม่ได้ตั้งชื่ออังกฤษของแพทย์ = เว้นจุดไว้ให้พิมพ์ใน Word (ไม่ใส่ชื่อไทยลงใบภาษาอังกฤษ) — เหมือนหน้าพิมพ์
        $name = ($doctor && $doctor['name_en'] !== '')
            ? (preg_match('/M\.?D\.?/i', $doctor['name_en']) ? $doctor['name_en'] : $doctor['name_en'] . ', M.D.')
            : '........................................';
        $b[] = self::p([], 'left');
        $b[] = self::p([self::r('...................................')], 'center', 4600, 0, 600);   // เส้นเซ็นสั้นลง (TMC ขอ 2569-11-10)
        $b[] = self::p([self::r('(' . $name . ')')], 'center', 4600, 0);
        // แพทย์ท่านอื่น (ไม่ได้เลือก) = เลขใบอนุญาต/ตำแหน่งเป็นจุดให้เขียนเอง
        $b[] = self::p([self::r('License No.' . (($doctor['license'] ?? '') ?: '....................'))], 'center', 4600, 0);
        // Travel Medicine Physician เฉพาะ พญ.นิศาชล · แพทย์ท่านอื่นเป็นจุดให้เขียนตำแหน่งเอง
        $b[] = self::p([self::r(($doctor['title'] ?? '') ?: '........................................')], 'center', 4600, 0);

        $body = implode('', $b)
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="851" w:right="1274" w:bottom="709" w:left="1418" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>';

        $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"'
            . ' xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<w:body>' . $body . '</w:body></w:document>';

        $path = tempnam(sys_get_temp_dir(), 'imm') . '.docx';
        $z = new \ZipArchive();
        $z->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '</Types>');
        $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>');
        $z->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rIdLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo.png"/>'
            . '</Relationships>');
        if (is_file($logoPath)) { $z->addFile($logoPath, 'word/media/logo.png'); }
        $z->addFromString('word/document.xml', $doc);
        $z->close();
        return $path;
    }

    private static function x(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** run ข้อความ · ขนาดเป็นครึ่งพอยต์ (32 = 16pt) */
    private static function r(string $text, bool $bold = false, int $size = self::SIZE, bool $underline = false): string
    {
        $f = self::FONT;
        return '<w:r><w:rPr><w:rFonts w:ascii="' . $f . '" w:hAnsi="' . $f . '" w:cs="' . $f . '" w:eastAsia="' . $f . '"/>'
            . ($bold ? '<w:b/><w:bCs/>' : '') . ($underline ? '<w:u w:val="single"/>' : '') . '<w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/></w:rPr>'
            . '<w:t xml:space="preserve">' . self::x($text) . '</w:t></w:r>';
    }

    private static function p(array $runs, string $align = 'left', int $indent = 0, int $after = 60, int $before = 0): string
    {
        return '<w:p><w:pPr><w:spacing w:before="' . $before . '" w:after="' . $after . '" w:line="240" w:lineRule="auto"/>'
            . ($indent ? ($align === 'center' ? '<w:ind w:left="' . $indent . '"/>' : '<w:ind w:firstLine="' . $indent . '"/>') : '')
            . '<w:jc w:val="' . $align . '"/></w:pPr>' . implode('', $runs) . '</w:p>';
    }

    private static function cell(string $text, int $w, array $opt = []): string
    {
        $pr = '<w:tcW w:w="' . $w . '" w:type="dxa"/>'
            . (isset($opt['span']) ? '<w:gridSpan w:val="' . $opt['span'] . '"/>' : '')
            . (isset($opt['vmerge']) ? '<w:vMerge' . ($opt['vmerge'] === 'restart' ? ' w:val="restart"' : '') . '/>' : '')
            . (! empty($opt['gray']) ? '<w:shd w:val="clear" w:color="auto" w:fill="808080"/>' : '')
            . '<w:vAlign w:val="center"/>';
        return '<w:tc><w:tcPr>' . $pr . '</w:tcPr>'
            . '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/><w:jc w:val="' . ($opt['align'] ?? 'center') . '"/></w:pPr>'
            . ($text !== '' ? self::r($text, $opt['bold'] ?? false) : '') . '</w:p></w:tc>';
    }

    private static function table(array $rows, int $cols): string
    {
        $vw = 3500;
        $dw = intdiv(self::TEXT_W - $vw, $cols);
        $ord = fn ($n) => $n . (in_array($n % 100, [11, 12, 13]) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th'));
        $bd = '<w:top w:val="single" w:sz="4" w:color="000000"/><w:left w:val="single" w:sz="4" w:color="000000"/>'
            . '<w:bottom w:val="single" w:sz="4" w:color="000000"/><w:right w:val="single" w:sz="4" w:color="000000"/>'
            . '<w:insideH w:val="single" w:sz="4" w:color="000000"/><w:insideV w:val="single" w:sz="4" w:color="000000"/>';

        $t = '<w:tbl><w:tblPr><w:tblW w:w="' . ($vw + $dw * $cols) . '" w:type="dxa"/><w:tblBorders>' . $bd . '</w:tblBorders>'
            . '<w:tblLayout w:type="fixed"/><w:tblCellMar><w:left w:w="80" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar></w:tblPr>'
            . '<w:tblGrid><w:gridCol w:w="' . $vw . '"/>' . str_repeat('<w:gridCol w:w="' . $dw . '"/>', $cols) . '</w:tblGrid>';

        // หัวตาราง 2 แถว: Vaccine (ผสานแนวตั้ง) | Date Given (ผสานแนวนอน) → 1st 2nd ...
        $t .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>' . self::cell('Vaccine', $vw, ['vmerge' => 'restart', 'bold' => true])
            . self::cell('Date Given', $dw * $cols, ['span' => $cols, 'bold' => true]) . '</w:tr>';
        $t .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>' . self::cell('', $vw, ['vmerge' => 'continue']);
        for ($i = 1; $i <= $cols; $i++) { $t .= self::cell($ord($i), $dw, ['bold' => true]); }
        $t .= '</w:tr>';

        if (! $rows) {
            $t .= '<w:tr>' . self::cell('— No vaccination recorded —', $vw + $dw * $cols, ['span' => $cols + 1]) . '</w:tr>';
        }
        foreach ($rows as $r) {
            $t .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>' . self::cell($r['name'], $vw, ['align' => 'left']);
            for ($i = 1; $i <= $cols; $i++) {
                $c = $r['doses'][$i] ?? null;
                // เข็มที่ไม่มี = ช่องเทาทึบ แทนขีด -
                $t .= $c ? self::cell($c['date'] . $c['mark'], $dw) : self::cell('', $dw, ['gray' => true]);
            }
            $t .= '</w:tr>';
        }
        return $t . '</w:tbl>';
    }

    /** โลโก้กรมกึ่งกลางหัวกระดาษ กว้าง ~3 ซม. */
    private static function logo(string $path): string
    {
        [$w, $h] = @getimagesize($path) ?: [1, 1];
        $cx = 1080000;   // EMU (3 ซม.)
        $cy = (int) round($cx * $h / max($w, 1));
        return '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/><w:jc w:val="center"/></w:pPr><w:r><w:drawing>'
            . '<wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<wp:docPr id="1" name="Logo"/>'
            . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic><pic:nvPicPr><pic:cNvPr id="1" name="logo.png"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="rIdLogo"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    }
}
