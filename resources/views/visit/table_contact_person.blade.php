<div class="table-responsive">
    <table class="table table-bordered table-striped text-center align-middle">
        <thead class="table-light">
            <tr>
                <th rowspan="2" class="align-middle">วันที่</th>
                <th rowspan="2" class="align-middle">ชื่อ-นามสกุล</th>
                <th rowspan="2" class="align-middle">เพศ</th>
                <th rowspan="2" class="align-middle">ประเภท</th>
                {{-- ปรับ colspan เป็น 6 --}}
                <th colspan="6">ช่องทางการมีเพศสัมพันธ์</th>
                <th colspan="4">การใช้ถุงยาง</th>
                <th rowspan="2" class="align-middle">การติดตามคู่สัมผัส</th>
                <th rowspan="2" class="align-middle">Actions</th>
            </tr>
            <tr>
                <th>Vagina</th>
                <th class="text-muted" style="font-size: 0.9em; background-color: #f0f0f0;">Mouth (Old)</th> {{-- แสดงช่องเก่า --}}
                <th>Oral (รุก)</th> {{-- เพิ่มใหม่ --}}
                <th>Oral (รับ)</th> {{-- เพิ่มใหม่ --}}
                <th>Penis</th>
                <th>Anus</th>
                
                <th>ใช้</th>
                <th>ไม่ใช้</th>
                <th>แตก/หลุด</th>
                <th>ไม่แตก/หลุด</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($contactperson as $k1 => $item)
                <?php 
                    $sex_condoms = $item->sex_condom();
                    $count = count($sex_condoms);
                    if ($count == 0) $count = 1; // ป้องกัน error หารด้วย 0 กรณีไม่มีข้อมูล sex_condom แต่มี record
                ?>
                
                {{-- กรณีไม่มีข้อมูล condom เลย ให้วนลูป 1 ครั้งเพื่อแสดง row หลัก --}}
                @if(count($sex_condoms) > 0)
                    @foreach ($sex_condoms as $key => $sex_condom)
                        <tr key="{{ $k1 }}">
                            @if ($key == 0)
                                <td rowspan="{{ $count }}" class="align-middle">{{ $item->date }}</td>
                                <td rowspan="{{ $count }}" class="align-middle">{{ $item->name_surname }}</td>
                                <td rowspan="{{ $count }}" class="align-middle">{{ $item->cpsex() }}</td>
                                <td rowspan="{{ $count }}" class="align-middle">{{ $item->type() }}</td>
                            @endif

                            <td key="{{ $key }}">{{ $sex_condom['vagina_mt'] ?? '' }}</td>
                            
                            {{-- Mouth เก่า --}}
                            <td style="background-color: #f9f9f9;" class="text-muted">{{ $sex_condom['mouth_mt'] ?? '' }}</td>
                            
                            {{-- Oral ใหม่ --}}
                            <td>{{ $sex_condom['oral_insertive_mt'] ?? '' }}</td>
                            <td>{{ $sex_condom['oral_receptive_mt'] ?? '' }}</td>
                            
                            <td>{{ $sex_condom['penis_mt'] ?? '' }}</td>
                            <td>{{ $sex_condom['anus_mt'] ?? '' }}</td>

                            <td>{{ $sex_condom['use_cd'] ?? '' }}</td>
                            <td>{{ $sex_condom['unuse_cd'] ?? '' }}</td>
                            <td>{{ $sex_condom['brea_slip_cd'] ?? '' }}</td>
                            <td>{{ $sex_condom['unbrea_slip_cd'] ?? '' }}</td>

                            @if ($key == 0)
                                <td rowspan="{{ $count }}" class="align-middle">
                                    {{ $item->trush_tracks ? (App\Models\ContactPerson::$touchTracingOption[$item->trush_tracks] ?? '-') : '' }}
                                </td>

                                <td rowspan="{{ $count }}" class="align-middle">
                                    <a href="{{ route('contact-person.edit', ['contact_person' => $item->id, 'page' => $_GET['page'] ?? 1]) }}"
                                        title="Edit ContactPerson" class="btn btn-primary btn-sm mb-1">
                                        <i class="fa fa-pencil" aria-hidden="true"></i>
                                    </a>

                                    {{-- ห้ามใช้ <form> ตรงนี้ ตารางนี้อยู่ในฟอร์มหลักของหน้าตรวจ ฟอร์มซ้อนฟอร์มผิดกติกา HTML
                                         เบราว์เซอร์จะทิ้งฟอร์มลบแล้วปุ่มไปกดบันทึกฟอร์มหลักแทน (เจอตอนพยาบาลกดลบแล้วกลายเป็นบันทึกคนไข้) --}}
                                    <button type="button" class="btn btn-danger btn-sm js-cp-delete"
                                        data-url="{{ route('contact-person.destroy', ['contact_person'=>$item->id, 'page' => $_GET['page'] ?? 1]) }}"
                                        title="Delete ContactPerson">
                                        <i class="fa fa-trash" aria-hidden="true"></i>
                                    </button>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                @else
                    {{-- กรณีมีข้อมูล ContactPerson แต่ไม่มีข้อมูล Sex Condom --}}
                    <tr>
                         <td>{{ $item->date }}</td>
                         <td>{{ $item->name_surname }}</td>
                         <td>{{ $item->cpsex() }}</td>
                         <td>{{ $item->type() }}</td>
                         <td colspan="10" class="text-center text-muted">- ไม่มีข้อมูลเพศสัมพันธ์ -</td>
                         <td>{{ $item->trush_tracks ? (App\Models\ContactPerson::$touchTracingOption[$item->trush_tracks] ?? '-') : '' }}</td>
                         <td>
                            <a href="{{ route('contact-person.edit', ['contact_person' => $item->id, 'page' => $_GET['page'] ?? 1]) }}"
                                title="Edit ContactPerson" class="btn btn-primary btn-sm mb-1"><i class="fa fa-pencil"></i></a>

                            {{-- ปุ่มลบ: เดิมถูกเขียนค้างไว้เป็นคอมเมนต์ ทำให้แถวที่ไม่มีข้อมูลเพศสัมพันธ์ลบไม่ได้เลย
                                 (พบตอนพยาบาลดึงคู่สัมผัสเดิมมาแล้วลบทิ้งไม่ได้ 2569-09-08 — กระทบของเดิม 1,039 รายการ) --}}
                            <button type="button" class="btn btn-danger btn-sm js-cp-delete"
                                data-url="{{ route('contact-person.destroy', ['contact_person'=>$item->id, 'page' => $_GET['page'] ?? 1]) }}"
                                title="Delete ContactPerson">
                                <i class="fa fa-trash" aria-hidden="true"></i>
                            </button>
                         </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>

{{-- ลบคู่สัมผัส: สร้างฟอร์มขึ้นมาใหม่นอกฟอร์มหลัก (ต่อกับ body) แล้วส่ง
     ทำแบบนี้เพราะตารางนี้ถูก include อยู่ในฟอร์มหลักของหน้าตรวจ วาง <form> ตรงนั้นตรงๆ ไม่ได้ --}}
<script>
(function () {
    if (window.__cpDeleteInit) return;
    window.__cpDeleteInit = true;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-cp-delete');
        if (!btn) return;
        e.preventDefault();
        if (!confirm('ยืนยันลบคู่สัมผัสรายนี้?')) return;

        var f = document.createElement('form');
        f.method = 'POST';
        f.action = btn.dataset.url;
        f.style.display = 'none';
        f.innerHTML = '<input type="hidden" name="_method" value="DELETE">'
                    + '<input type="hidden" name="_token" value="{{ csrf_token() }}">';
        document.body.appendChild(f);   // นอกฟอร์มหลัก จึงส่งได้ถูกที่
        f.submit();
    });
})();
</script>