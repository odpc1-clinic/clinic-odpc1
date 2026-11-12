<div class="row">

<div class="col-md-12">
    <div class="card">
        <div class="card-header">Lab for Visit #{{ $visit->id }}</div>
        <div class="card-body">
            <?php $lab =$visit->lab() ?>
            {{-- คลินิกวัณโรค: หน้าสั่ง Lab เต็มเป็นรายการของงาน STI (Gram/Wet/Culture/PCR ตามตำแหน่ง) ยาวเกินและไม่เกี่ยว (คลินิกขอ 2569-10-01)
                 แต่คลินิก TB ตรวจ HIV ในคลินิกด้วย (คลินิกแจ้ง 2569-10-02) -> แสดงแผงสั้นเฉพาะการตรวจเลือด HIV ก่อน
                 แผงเดียวกับในบัตร TB 01 (tb/_inhouse_lab_panel) บันทึกด้วย JS ไม่ผ่านปุ่ม Save ของหน้าตรวจ (ไม่มี name= ในแผง)
                 ยังมีลิงก์ไปหน้าสั่ง Lab เต็มในแผง เผื่อต้องสั่ง Gram/Culture --}}
            @php
                $__isTb = (int) ($visit->clinic ?? 1) === 2;
                $__tbNoInhouse = $__isTb && count($visit->labSub()) === 0;
                // TB + แคตตาล็อกแล็บรวมเปิดใช้ = สั่งแล็บนอกที่ปุ่ม "สั่งแล็บ" ด้านบน การ์ดแล็บนอกเหลือแค่ลงผล/ราคา (2569-10-02)
                // คลินิกอื่นยังเหมือนเดิมทุกอย่าง
                $__hideExtPicker = $__isTb && \App\Models\LabCatalogItem::availableFor(2);
            @endphp

            @if($__isTb)
                @include('tb._inhouse_lab_panel', ['visit' => $visit, 'fullLink' => true])
                @include('tb._inhouse_lab_js')
            @endif

            @if($__tbNoInhouse)
                @include ('visit.form.external_lab', ['hideExtPicker' => $__hideExtPicker])
            @elseif(!$lab)
            <a href="{{ route('lab.create',['visit' => $visit->id]) }}" class="btn btn-success btn-sm" title="Add New Lab">
                <i class="fa fa-plus" aria-hidden="true"></i> Add New
            </a>  
            @else
               
            
                @include ('lab.table', ['lab' => $visit->lab(),'page'=>request('page', 1)])
                @if(false)
                    <a href="{{ route('lab-item.create',['visit' => $visit->id]) }}" class="btn btn-success btn-sm" title="Add New Lab">
                        <i class="fa fa-plus" aria-hidden="true"></i> Add New
                    </a> 
                    <br><br>
                    @include ('lab-item.table', ['labitem' => $visit->labItem(),'page'=>request('page', 1)])
                    
                    <?php $labBlood =$lab->labBlood() ?>
                    @if($labBlood)
                    <h3>Blood Test</h3>
                        @include ('lab-blood.table', ['labBlood' => $labBlood,'edit' => 0])
                    @endif

                @endif

                {{-- <a href="{{ route('lab-sub.add',['visit' => $visit->id,'page'=>request('page', 1)]) }}" class="btn btn-success btn-sm" title="Add New Lab">
                    <i class="fa fa-plus" aria-hidden="true"></i> Add New
                </a>  --}}

                <a href="{{ route('lab-sub.config',['visit' => $visit->id,'page'=>request('page', 1)]) }}" class="btn btn-success btn-sm" title="Add New Lab">
                    <i class="fa fa-pencil" aria-hidden="true"></i> Add Lab
                </a> 

                @include ('lab-sub.table', ['labsub' => $visit->labSub()])
                @include ('partials.lab_sub_delete_js')

                {{-- Lab ส่งตรวจภายนอก: คนละชุดกับ Lab ในบ้าน ไม่ปนกัน --}}
                @include ('visit.form.external_lab', ['hideExtPicker' => $__hideExtPicker])
            @endif


        </div>
    </div>
</div>
</div>