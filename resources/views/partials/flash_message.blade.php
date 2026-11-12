{{-- ข้อความหลังบันทึกที่ส่งมาแบบ ->with('flash_message', ...) (2569-10-20)
     controller ราว 100 จุดส่งแบบนี้ (ส่วนใหญ่มาจากโค้ดตั้งต้นของระบบ) แต่ไม่เคยมีหน้าไหนแสดง — กดบันทึกแล้วเงียบ
     แสดงที่นี่ที่เดียว · ข้อความอังกฤษอัตโนมัติ "Lab updated!" แปลเป็นไทย · ถ้าหน้านั้นมี flash() อยู่แล้ว ไม่แสดงซ้ำ --}}
@php
    $__fm = session('flash_message');
    if ($__fm && ! session()->has('flash_notification')) {
        if (preg_match('/^(\w+) (added|updated|deleted)!$/', trim($__fm), $__m)) {
            $__what = [
                'Lab' => 'ใบ Lab', 'LabSub' => 'รายการตรวจ', 'LabItem' => 'รายการ Lab', 'LabBlood' => 'ผลเลือด',
                'ContactPerson' => 'คู่สัมผัส', 'Address' => 'ที่อยู่', 'Visit' => 'ข้อมูลการตรวจ', 'Medicine' => 'ยา',
                'Diagnosi' => 'การวินิจฉัย', 'Diagnosis' => 'การวินิจฉัย', 'Patient' => 'ข้อมูลคนไข้', 'Config' => 'ค่าตั้งระบบ',
            ][$__m[1]] ?? 'ข้อมูล';
            $__fm = ['added' => 'เพิ่ม', 'updated' => 'บันทึก', 'deleted' => 'ลบ'][$__m[2]] . $__what
                . (preg_match('/[A-Za-z]$/', $__what) ? ' แล้ว' : 'แล้ว');   // "ใบ Lab แล้ว" ไม่ติดกัน
        }
    } else {
        $__fm = null;
    }
@endphp
@if($__fm)
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa fa-check-circle me-1"></i> {{ $__fm }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ปิด"></button>
    </div>
@endif
