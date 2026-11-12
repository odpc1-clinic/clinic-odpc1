@if (isset($visit))
    <div class="card card-body mt-4">
        <h5>การติดตามผู้ป่วย</h5>

        {{-- ตารางรายการติดตาม --}}
        <table class="table table-bordered">
            <thead>
    <tr>
        <th>ช่องทาง</th>
        <th>จำนวนครั้ง</th>
        <th>สถานะ</th>
        <th>หมายเหตุ</th>
        <th>วันที่ติดตาม</th>
        <th>แก้ไข</th>
        <th>ลบ</th>
    </tr>
</thead>
<tbody>
    @foreach ($visit->followups as $followup)
        <tr>
            <td>{{ $followup->method }}</td>
            <td>{{ $followup->followup_count }}</td>
            <td>{{ $followup->status }}</td>
            <td>{{ $followup->remark }}</td>
            <td>{{ $followup->created_at->format('d/m/Y H:i') }}</td>
            <td>
                <a href="{{ route('followups.edit', ['followup' => $followup->id, 'page' => request()->get('page', 1)]) }}"
                   class="btn btn-warning btn-sm">แก้ไข</a>
            </td>
            <td>
                <a href="#" class="btn btn-danger btn-sm btn-delete-followup"
                   data-id="{{ $followup->id }}" data-page="{{ $page }}">ลบ</a>
            </td>
        </tr>
    @endforeach
</tbody>

        </table>

        {{-- ลิงก์เพื่อเพิ่มการติดตาม --}}
        <div class="mt-4">
            <a href="{{ route('followups.create', ['visit' => $visit->id, 'page' => request()->get('page', 1)]) }}" class="btn btn-success btn-sm" title="Add New Follow-up" style="float: right;">
                <i class="fa fa-plus" aria-hidden="true"></i> เพิ่มการติดตาม
            </a>
        </div>
    </div>
@endif


{{-- เพิ่ม JavaScript สำหรับลบ --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.btn-delete-followup').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();

                const followupId = this.dataset.id;
                const page = this.dataset.page;

                if (confirm('ยืนยันการลบ?')) {
                    fetch("{{ url('followups') }}/" + followupId + "?page=" + page, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload(); // รีโหลดหน้าใหม่หลังจากลบ
                        } else {
                            alert('เกิดข้อผิดพลาดในการลบ');
                        }
                    })
                    .catch(error => {
                        alert('เกิดข้อผิดพลาด');
                    });
                }
            });
        });
    });
</script>