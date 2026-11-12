{{-- หัวข้อ + กล่อง "การติดตามผู้ป่วย"
     แยกเป็น partial เพราะตำแหน่งต่างกันตามคลินิก:
     STI/TB = ก่อนวันนัด (ที่เดิม) · TMC = ล่างสุดของหน้า (ผู้ใช้สั่ง 2026-07-27)
     ไม่มี <form> ข้างใน — ย้ายตำแหน่งได้โดยไม่กระทบฟอร์มหลัก --}}
<br>
<div class="d-grid">
    <div class="row">
        <div>
            <a class="btn btn-light" data-tabtoggle="1" href="#visitFollowup" role="button"
                style="width: 100%" aria-expanded="false" aria-controls="visitFollowup">
                <font size="4">**การติดตามผู้ป่วย**</font>
            </a>
        </div>
    </div>
    <div class="collapse pb-2" id="visitFollowup">
        <div class="card card-body">
            @include('visit.form.followup', ['visit' => $visit, 'page' => request()->get('page')])
        </div>
    </div>
</div>
