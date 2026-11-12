{{-- ช่อง "การให้คำแนะนำ" + popup ตัวเลือกคำแนะนำ — แยกไฟล์ 2569-10-01
     เพราะ TMC วางไว้ก่อนค่าบริการ ส่วน STI/TB ยังอยู่ในส่วนการรักษาเหมือนเดิม
     include ได้ที่เดียวต่อหน้าเท่านั้น (id treatment / staticBackdrop ต้องไม่ซ้ำ ปุ่ม "เดิม" ชี้ด้วย id)
     popup ต้องย้ายตามช่องไปด้วย — ถ้าค้างอยู่ใน collapse ที่ปิดอยู่ กดชื่อช่องแล้ว popup ไม่โผล่ --}}
        <div class="form-group {{ $errors->has('treatment') ? 'has-error' : '' }}">
            <label for="treatment" class="control-label click" data-bs-toggle="modal"
                data-bs-target="#staticBackdrop">{{ 'การให้คำแนะนำ' }}</label>
            @include('visit.field_history_button', ['field' => 'treatment', 'target' => 'treatment'])

            <textarea class="form-control" rows="5" name="treatment" type="textarea" id="treatment">{{ isset($visit->treatment) ? $visit->treatment : '' }}</textarea>
            {!! $errors->first('treatment', '<p class="help-block">:message</p>') !!}
        </div>


        <div class="modal fade" id="staticBackdrop" data-bs-keyboard="false" tabindex="-1"
            aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">การให้คำแนะนำ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @foreach (App\Models\Visit::$treatmentOption as $item)
                            <!-- <p class='treatment_item' style="display: list-item; margin-left : 1em; ">&nbsp;{{ $item }}</p> -->
                            <p class='treatment_item' style="display: list-item; margin-left : 1em;" onmouseover="this.style.color='red'" onmouseout ="this.style.color='black'"> {{ $item }}</p>
                        @endforeach
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $('.treatment_item').click(function() {
                console.log($(this).text())
                $('#treatment').append($(this).text() + ', ');

            })
            document.querySelectorAll('.treatment_item').forEach(item => {
                item.style.cursor = 'pointer';
            });

        </script>
