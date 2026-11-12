@php
    $page = request('page', 1);

    // visits.date ส่วนใหญ่เก็บเป็น พ.ศ. แต่ข้อมูลยุคแรก (ปี 2022) บางแถวเก็บเป็น ค.ศ.
    // ถ้าโชว์ดิบ ๆ จะเห็นปนกันเป็น "20/10/2568" กับ "06/06/2022" — แปลงให้เป็น พ.ศ. เหมือนกันหมด
    $showDate = function ($d) {
        $d = trim((string) $d);
        if ($d === '') { return '-'; }
        $p = explode('-', substr($d, 0, 10));
        if (count($p) !== 3) { return $d; }
        $y = (int) $p[0];
        if ($y < 2500) { $y += 543; }
        return sprintf('%02d/%02d/%d', (int) $p[2], (int) $p[1], $y);
    };

    // สรุปการใช้ถุงยางจาก sex_condom (เป็น JSON ไม่ใช่คอลัมน์ use_cd ตรง ๆ)
    $condomText = function ($c) {
        $rows = $c->sex_condom();
        if (!$rows) { return null; }
        $use = $unuse = false;
        foreach ($rows as $r) {
            if (!empty($r['use_cd']))   { $use = true; }
            if (!empty($r['unuse_cd'])) { $unuse = true; }
        }
        if ($use && $unuse) { return 'ใช้บ้าง'; }
        if ($use)   { return 'ใช้'; }
        if ($unuse) { return 'ไม่ใช้'; }
        return null;
    };
@endphp

<div class="ch-wrap">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <div class="fw-bold fs-5"><i class="fas fa-user-group text-muted me-1"></i> คู่สัมผัสที่เคยบันทึกของคนไข้รายนี้</div>
            <div class="text-muted small">กด "ดึงทั้งชุด" เพื่อคัดลอกคู่สัมผัสทุกคนของครั้งนั้นมาใส่ครั้งนี้</div>
        </div>
        <span class="badge bg-secondary align-self-center">ย้อนหลัง {{ $past->count() }} ครั้ง</span>
    </div>

    @if($past->isEmpty())
        <div class="alert alert-light border text-center text-muted mb-0">
            <i class="fas fa-folder-open me-1"></i> คนไข้รายนี้ยังไม่เคยบันทึกคู่สัมผัสไว้ในระบบ
        </div>
    @else
        @foreach($past as $p)
            @php
                $newCount = $p->contactPersons->filter(function ($c) use ($already) {
                    $k = mb_strtolower(trim((string) $c->name_surname));
                    return $k === '' || !in_array($k, $already, true);
                })->count();
            @endphp
            <div class="card mb-2">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
                    <div class="fw-bold">
                        <i class="fas fa-calendar-day text-muted me-1"></i>
                        {{ $showDate($p->date) }}
                        <span class="badge bg-light text-muted border ms-1">{{ $p->contactPersons->count() }} ราย</span>
                    </div>
                    {{-- ปุ่มเป็น button ธรรมดา ไม่ใช่ <form> เพราะ popup นี้อยู่ในฟอร์มหลักของหน้าตรวจ
                         ฟอร์มซ้อนฟอร์มจะถูกเบราว์เซอร์ทิ้ง แล้วปุ่มไปกดบันทึกฟอร์มหลักแทน (เจอ 2569-09-08) --}}
                    <button type="button" class="btn btn-primary btn-sm js-cp-copy"
                            data-url="{{ route('contact-person.copy', $visit->id) }}"
                            data-field="from_visit" data-value="{{ $p->id }}" data-page="{{ $page }}"
                            {{ $newCount === 0 ? 'disabled' : '' }}>
                        <i class="fas fa-copy"></i>
                        {{ $newCount ? 'ดึงทั้งชุด (' . $newCount . ')' : 'มีครบแล้ว' }}
                    </button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ชื่อ-นามสกุล</th>
                                <th style="width:70px;">เพศ</th>
                                <th style="width:130px;">ประเภท</th>
                                <th style="width:150px;">ข้อมูลเพศสัมพันธ์</th>
                                <th style="width:100px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($p->contactPersons as $c)
                            @php
                                $k = mb_strtolower(trim((string) $c->name_surname));
                                $dup = $k !== '' && in_array($k, $already, true);
                                $cd  = $condomText($c);
                                $scN = count($c->sex_condom());
                            @endphp
                            <tr class="{{ $dup ? 'text-muted' : '' }}">
                                <td>{{ $c->name_surname ?: '(ไม่ระบุชื่อ)' }}</td>
                                <td>{{ $c->cpsex() ?: '-' }}</td>
                                <td>{{ $c->type() ?: '-' }}</td>
                                <td>
                                    {{-- ใช้สีแบบระบุเองเพราะธีมนี้เป็น Bootstrap รุ่นก่อน 5.3 ที่ยังไม่มีคลาส *-subtle
                                         (ถ้าใช้ bg-info-subtle จะได้กรอบเปล่า ตัวหนังสือมองไม่เห็น) --}}
                                    @if($scN)
                                        <span class="badge border" style="background:#e9ecef; color:#212529;">{{ $scN }} รายการ</span>
                                        @if($cd)<span class="badge border ms-1" style="background:#cfe2ff; color:#084298;">ถุงยาง: {{ $cd }}</span>@endif
                                    @else
                                        <span class="text-muted small">ไม่มีข้อมูล</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($dup)
                                        <span class="badge bg-light text-muted border">มีแล้ว</span>
                                    @else
                                        <button type="button" class="btn btn-outline-primary btn-sm js-cp-copy"
                                                data-url="{{ route('contact-person.copy', $visit->id) }}"
                                                data-field="item" data-value="{{ $c->id }}" data-page="{{ $page }}">
                                            <i class="fas fa-plus"></i> ดึง
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <div class="alert alert-warning small mt-2 mb-0">
            <i class="fas fa-triangle-exclamation me-1"></i>
            <strong>ช่อง "สัมผัสครั้งล่าสุดเมื่อไหร่" จะว่างไว้ให้กรอกใหม่</strong>
            เพราะเป็นข้อมูลของการมาครั้งนี้ ลอกของเก่ามาจะกลายเป็นข้อมูลผิด ·
            ส่วนชื่อ เพศ ประเภท พฤติกรรมเสี่ยง และการใช้ถุงยาง ถูกคัดลอกมาให้ —
            อย่าลืมตรวจว่าตรงกับที่คนไข้เล่าครั้งนี้ ·
            คนที่ชื่อซ้ำกับที่บันทึกไว้แล้วจะถูกข้ามอัตโนมัติ
        </div>
    @endif
</div>

{{-- ตัวจัดการปุ่ม "ดึง" อยู่ใน visit/contact.blade.php (หน้าหลัก) ตั้งใจไม่วาง <script> ไว้ที่นี่
     เพราะไฟล์นี้ถูกโหลดเข้า popup ด้วย AJAX แล้วยัดด้วย innerHTML — เบราว์เซอร์จะไม่รันสคริปต์ที่มากับ innerHTML
     (เคยวางไว้ที่นี่แล้วปุ่มกดไม่ติดเลย 2569-09-08) --}}
