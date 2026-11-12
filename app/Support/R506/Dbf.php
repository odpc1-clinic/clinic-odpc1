<?php

namespace App\Support\R506;

/**
 * อ่าน/เขียนไฟล์ DBF (dBASE III / FoxPro) สำหรับงานส่งออกรายงาน 506
 * - ข้อความไทยเก็บเป็น TIS-620/CP874 (โปรแกรม FoxPro 506 ใช้ codepage นี้)
 * - อ่าน: คืน array ของ record (assoc) พร้อม decode CP874 -> UTF-8
 * - เขียน: รับนิยามฟิลด์ + แถว (ค่าเป็น UTF-8) แล้ว encode กลับเป็น CP874
 */
class Dbf
{
    /** อ่านทั้งไฟล์ -> ['fields'=>[...], 'rows'=>[assoc,...]] (ข้าม record ที่ถูกลบ) */
    public static function read(string $path): array
    {
        $fh = fopen($path, 'rb');
        if (!$fh) {
            throw new \RuntimeException("เปิดไฟล์ DBF ไม่ได้: $path");
        }
        $header = fread($fh, 32);
        $h = unpack('Cver/Cy/Cm/Cd/VnumRec/vheaderLen/vrecLen', $header);
        $numRec    = $h['numRec'];
        $headerLen = $h['headerLen'];
        $recLen    = $h['recLen'];

        $fields = [];
        while (true) {
            $fd = fread($fh, 32);
            if (strlen($fd) < 32 || ord($fd[0]) === 0x0D) {
                break;
            }
            $fields[] = [
                'name' => rtrim(substr($fd, 0, 11), "\0"),
                'type' => $fd[11],
                'len'  => ord($fd[16]),
                'dec'  => ord($fd[17]),
            ];
        }

        fseek($fh, $headerLen);
        $rows = [];
        for ($r = 0; $r < $numRec; $r++) {
            $rec = fread($fh, $recLen);
            if (strlen($rec) < $recLen) {
                break;
            }
            if ($rec[0] === '*') {
                continue; // ถูกลบ
            }
            $off = 1;
            $row = [];
            foreach ($fields as $f) {
                $raw = substr($rec, $off, $f['len']);
                $off += $f['len'];
                $val = trim($raw);
                $conv = @iconv('CP874', 'UTF-8//IGNORE', $val);
                $row[$f['name']] = $conv !== false ? $conv : $val;
            }
            $rows[] = $row;
        }
        fclose($fh);

        return ['fields' => $fields, 'rows' => $rows];
    }

    /**
     * เขียนไฟล์ DBF
     * @param string $path       ปลายทาง
     * @param array  $fieldDefs  [['name'=>,'type'=>'C|N|D|L','len'=>,'dec'=>0], ...] (เรียงตามลำดับคอลัมน์)
     * @param array  $rows       [ ['FIELD'=>value, ...], ... ] ค่าเป็น UTF-8
     */
    public static function write(string $path, array $fieldDefs, array $rows): void
    {
        $numRec  = count($rows);
        $nFields = count($fieldDefs);
        $recLen  = 1;
        foreach ($fieldDefs as $f) {
            $recLen += $f['len'];
        }
        $headerLen = 32 + 32 * $nFields + 1;

        $fh = fopen($path, 'wb');
        if (!$fh) {
            throw new \RuntimeException("สร้างไฟล์ DBF ไม่ได้: $path");
        }

        // ---- header 32 bytes ----
        $hdr  = chr(0x03);                                   // dBASE III (ไม่มี memo)
        $hdr .= chr((int) date('y')) . chr((int) date('n')) . chr((int) date('j'));
        $hdr .= pack('V', $numRec);
        $hdr .= pack('v', $headerLen);
        $hdr .= pack('v', $recLen);
        $hdr .= str_repeat("\0", 20);
        fwrite($fh, $hdr);

        // ---- field descriptors ----
        foreach ($fieldDefs as $f) {
            $name = substr($f['name'], 0, 11);
            $name = $name . str_repeat("\0", 11 - strlen($name));
            fwrite($fh, $name);                               // 0-10 ชื่อ
            fwrite($fh, $f['type']);                          // 11 ชนิด
            fwrite($fh, str_repeat("\0", 4));                 // 12-15 address
            fwrite($fh, chr($f['len']));                      // 16 ความยาว
            fwrite($fh, chr($f['dec'] ?? 0));                 // 17 ทศนิยม
            fwrite($fh, str_repeat("\0", 14));                // 18-31 reserved
        }
        fwrite($fh, chr(0x0D));                               // ปิดส่วน header

        // ---- records ----
        foreach ($rows as $row) {
            fwrite($fh, ' ');                                 // ธง not-deleted
            foreach ($fieldDefs as $f) {
                fwrite($fh, self::encodeField($f, $row[$f['name']] ?? ''));
            }
        }
        fwrite($fh, chr(0x1A));                               // EOF
        fclose($fh);
    }

    /** แปลงค่า 1 ฟิลด์เป็น byte string ตามชนิด + ความยาว (encode ไทยเป็น CP874) */
    protected static function encodeField(array $f, $value): string
    {
        $len  = $f['len'];
        $type = $f['type'];

        if ($type === 'D') {
            // วันที่ YYYYMMDD (8) ; ว่าง = ช่องว่าง
            $v = preg_replace('/\D/', '', (string) $value);
            $v = ($v === '' || strlen($v) !== 8) ? str_repeat(' ', 8) : $v;
            return substr($v, 0, 8);
        }

        if ($type === 'L') {
            $v = strtoupper(substr(trim((string) $value), 0, 1));
            return in_array($v, ['T', 'F', 'Y', 'N']) ? $v : '?';
        }

        if ($type === 'N') {
            $v = trim((string) $value);
            if ($v === '') {
                return str_repeat(' ', $len);                 // เว้นว่าง = null
            }
            $v = substr($v, 0, $len);
            return str_pad($v, $len, ' ', STR_PAD_LEFT);      // ตัวเลขชิดขวา
        }

        // Character
        $v = (string) $value;
        $conv = @iconv('UTF-8', 'CP874//TRANSLIT//IGNORE', $v);
        if ($conv !== false) {
            $v = $conv;
        }
        if (strlen($v) > $len) {
            $v = substr($v, 0, $len);
        }
        return str_pad($v, $len, ' ', STR_PAD_RIGHT);         // ข้อความชิดซ้าย
    }
}
