<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>โรค</th>
                <th>สถานะของโรค</th>
                <th title="ส่งเข้ารายงานเฝ้าระวังโรค 506">ส่ง 506</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($diagnosis as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->disease() }}</td>
                <td>{{ $item->diseaseState() }}</td>
                <td>
                    @if($item->is506Reportable())
                        @if($item->report_506_sent_at)
                            <span class="badge bg-secondary" title="ส่งเข้า 506 แล้ว">
                                <i class="fa fa-check"></i> ส่งแล้ว {{ \Illuminate\Support\Carbon::parse($item->report_506_sent_at)->format('d/m/y') }}
                            </span>
                        @else
                            <div class="form-check form-switch">
                                <input class="form-check-input js-r506-toggle" type="checkbox" role="switch"
                                       {{ $item->report_506 ? 'checked' : '' }}
                                       data-url="{{ route('diagnosis.toggle506', ['diagnosi' => $item->id]) }}"
                                       title="รหัส 506: {{ $item->code506() }}">
                            </div>
                        @endif
                    @else
                        <span class="text-muted" title="ไม่ใช่โรคที่ต้องรายงาน 506">—</span>
                    @endif
                </td>
                <td>
                    <!-- <a href="{{ url('/backend/diagnosis/' . $item->id) }}" title="View Diagnosi"><button class="btn btn-info btn-sm"><i class="fa fa-eye" aria-hidden="true"></i> View</button></a> -->
                    <a href="{{ route('diagnosis.edit',['diagnosi' => $item->id, 'page'=>request('page')]) }}" 
   title="Edit Diagnosi" 
   class="btn btn-primary btn-sm">
   <i class="fa fa-pencil" aria-hidden="true"></i>
</a>

{{-- ห้ามใช้ <form> ที่นี่: ตารางนี้อยู่ข้างในฟอร์มหลักของหน้าตรวจ (#visit_edit_form)
     form ซ้อน form ทำให้ </form> ตัวในไปปิดฟอร์มหลัก แล้วช่องสถานะ/วันนัดที่อยู่ถัดไปหลุดออกนอกฟอร์ม
     (เคยทำให้กด Save แล้วสถานะ/วันนัดไม่ถูกบันทึก) — ส่งด้วย JS แทน --}}
<button type="button" class="btn btn-danger btn-sm js-row-delete" title="Delete Diagnosi"
        data-action="{{ route('diagnosis.destroy', ['diagnosi' => $item->id, 'page' => request('page')]) }}">
    <i class="fa fa-trash" aria-hidden="true"></i>
</button>

                    
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

</div>

<script>
(function () {
    if (window.__r506ToggleInit) return;
    window.__r506ToggleInit = true;

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    document.addEventListener('change', function (e) {
        var cb = e.target.closest('.js-r506-toggle');
        if (!cb) return;

        var want = cb.checked;
        cb.disabled = true;

        var fd = new FormData();
        fd.append('on', want ? 1 : 0);
        fd.append('_token', csrf());

        fetch(cb.dataset.url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: fd
        })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
            if (!res.ok || !res.j.ok) {
                cb.checked = !want; // ย้อนกลับถ้าล้มเหลว
                if (res.j && res.j.msg) {
                    if (window.Swal) { Swal.fire({ icon: 'warning', title: res.j.msg, timer: 2000, showConfirmButton: false }); }
                    else { alert(res.j.msg); }
                }
            }
        })
        .catch(function () {
            cb.checked = !want;
            alert('บันทึกการติ๊ก 506 ไม่สำเร็จ');
        })
        .finally(function () { cb.disabled = false; });
    });
})();
</script>