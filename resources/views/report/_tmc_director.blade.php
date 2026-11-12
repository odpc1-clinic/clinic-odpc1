{{-- แบบรายงานผลการให้บริการคลินิกเวชศาสตร์การเดินทางฯ ส่ง ผอ. — แถวตามไฟล์ "รายงานTMCส่งผอ.69" (คลินิกขอ 2569-11-04)
     $rows จาก TmcReportController::director() · $periodText = "ประจำเดือน ..." / "ประจำปีงบประมาณ ..." --}}
<div class="tmc-dir">
    <div class="text-center mb-2">
        <div class="fw-bold" style="font-size:1.1rem">แบบรายงานผลการให้บริการคลินิกเวชศาสตร์การเดินทางและท่องเที่ยว</div>
        <div>สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่ · {{ $periodText }}</div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-1 tmc-dir-table">
            <thead>
                <tr>
                    <th class="text-center" style="width:4.5rem">ลำดับ</th>
                    <th>กิจกรรม</th>
                    <th class="text-center" style="width:5rem">หน่วยนับ</th>
                    <th class="text-end" style="width:6.5rem">ชาวไทย</th>
                    <th class="text-end" style="width:6.5rem">ชาวต่างชาติ</th>
                    <th class="text-end" style="width:6.5rem">รวม</th>
                </tr>
            </thead>
            <tbody>
            @foreach($rows as $r)
                <tr class="{{ $r['sub'] ? '' : 'tmc-dir-head' }}">
                    <td class="text-center">{{ $r['no'] }}</td>
                    <td class="{{ $r['sub'] ? 'ps-4' : 'fw-bold' }}">{{ $r['label'] }}</td>
                    <td class="text-center">{{ $r['unit'] }}</td>
                    @if(!empty($r['na']))
                        <td colspan="3" class="text-center text-muted small" title="ระบบยังไม่มีข้อมูลส่วนนี้ — กรอกเองตอนส่งรายงาน">— กรอกเอง —</td>
                    @else
                        <td class="num">{{ number_format($r['th']) }}</td>
                        <td class="num">{{ number_format($r['fx']) }}</td>
                        <td class="num fw-bold">{{ number_format($r['total']) }}</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
