<div class="d-grid">
    <div class="row">
        <div>
            <a 
            class="btn btn-primary"
            data-tabtoggle="1"
            href="#visitReason"
            role="button" 
            aria-expanded="false"
            aria-controls="visitReason"
            style="width: 100%">
            <font size="4">เหตุผลหลักในการตรวจครั้งนี้</font>
            </a>
        </div>

<div class="collapse" id="visitReason">

    {{-- ส่วนเหตุผล/พฤติกรรมเสี่ยง STI — ซ่อนสำหรับคลินิก TMC (แต่คง LMP/วิธีคุมกำเนิดด้านล่างไว้) --}}
    @if((int) ($visit->clinic ?? 1) !== 3)
    <div class="card card-body">
        <h6>STI</h6>
        <div class="row">
            <div class="col-4 form-group {{ $errors->has('reason_sti') ? 'has-error' : '' }}">
                <label for="reason_sti" class="control-label">{{ 'เหตุผล Sti' }}</label>
                {{-- <input class="form-control" name="reason_sti" type="number" id="reason_sti"
            value="{{ isset($visit->reason_sti) ? $visit->reason_sti : '' }}"> --}}
                    {{-- {{dd($visit->reason_sti)}} --}}
                <select class="form-control" name="reason_sti[]" id="reason_sti" multiple size="{{count(App\Models\Visit::$reasonStiOption)}}">
                    @foreach (App\Models\Visit::$reasonStiOption as $key => $value)
                    <option value="{{ $key }}" @if (isset($visit->reason_sti) && in_array($key,$visit->reason_sti)) selected="selected" @endif>
                        {{ $value }}
                    </option>
                    @endforeach
                </select>
                {!! $errors->first('reason_sti', '<p class="help-block">:message</p>') !!}
            </div>

            <div class="col-4 form-group {{ $errors->has('reason_vct') ? 'has-error' : '' }}" >
                <label for="reason_vct" class="control-label">{{ 'เหตุผล Vct' }}</label>
                <select class="form-control" name="reason_vct[]" id="reason_vct" multiple size="{{count(App\Models\Visit::$reasonVctOption)}}">
                    @foreach (App\Models\Visit::$reasonVctOption as $key => $value)
                    <option value="{{ $key }}" @if (isset($visit->reason_vct) && in_array($key, $visit->reason_vct)) selected="selected" @endif>
                        {{ $value }}
                    </option>
                    @endforeach
                </select>
                {!! $errors->first('reason_vct', '<p class="help-block">:message</p>') !!}
            </div>
            <div class="col-4 form-group {{ $errors->has('risk_behavior') ? 'has-error' : '' }}">
                <label for="risk_behavior" class="control-label">{{ 'พฤติกรรมเสี่ยง' }}</label>
                {{-- <input class="form-control" name="risk_behavior" type="text" id="risk_behavior"
            value="{{ isset($visit->risk_behavior) ? $visit->risk_behavior : '' }}"> --}}
                <select class="form-control" name="risk_behavior[]" id="risk_behavior" multiple size="{{count(App\Models\Visit::$riskBehaviorOption)}}">
                    @foreach (App\Models\Visit::$riskBehaviorOption as $key => $value)
                    <option value="{{ $key }}" @if (isset($visit->risk_behavior) && in_array($key,$visit->risk_behavior)) selected="selected" @endif>
                        {{ $value }}
                    </option>
                    @endforeach
                </select>
                {!! $errors->first('risk_behavior', '<p class="help-block">:message</p>') !!}
            </div>


    </div>

    </div>
     


        <div class="row">
            <div class="col-4 form-group {{ $errors->has('reason_sti_other') ? 'has-error' : '' }}">
    <label for="reason_sti_other" 
           class="control-label click" 
           data-bs-toggle="modal" 
           data-bs-target="#reason_sti_other_model">
           {{ 'เหตุผล Sti อื่น ๆ' }}
    </label>
    <input class="form-control" 
           name="reason_sti_other" 
           type="text" 
           id="reason_sti_other" 
           value="{{ isset($visit->reason_sti_other) ? $visit->reason_sti_other : '' }}">
    {!! $errors->first('reason_sti_other', '<p class="help-block">:message</p>') !!}
</div>

<!-- Modal -->
<div class="modal fade" id="reason_sti_other_model" data-bs-keyboard="false" tabindex="-1"
     aria-labelledby="reasonStiOtherLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reasonStiOtherLabel">เหตุผล STI อื่น ๆ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @foreach (App\Models\Visit::$ReasonStiOtherOption as $item)
<p class="reason_sti_other_item"
   style="display: list-item; margin-left: 1em;"
   onmouseover="this.style.color='red'"
   onmouseout ="this.style.color='black'">{{ $item }}</p>
                @endforeach
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
  // เคอร์เซอร์เป็นรูปมือ
  document.querySelectorAll('.reason_sti_other_item').forEach(el => el.style.cursor = 'pointer');

  $('.reason_sti_other_item').on('click', function () {
    const picked = $(this).text().trim();              // ข้อความที่กด
    const $input = $('#reason_sti_other');

    // แตกค่าปัจจุบัน => array พร้อม trim, ตัดว่าง, กันซ้ำ
    let items = $input.val()
      .split(',')
      .map(s => s.trim())
      .filter(s => s.length > 0);

    // ถ้ายังไม่มีค่านี้ ค่อยเพิ่ม
    if (!items.includes(picked)) {
      items.push(picked);
    }

    // เขียนกลับแบบฟอร์แมตเดียว “ค่า1, ค่า2, ค่า3”
    $input.val(items.join(', '));
  });

  // กันเคสพิมพ์เองแล้วมีเว้นวรรค/คอมมาซ้อน: จัดฟอร์แมตตอน blur
  $('#reason_sti_other').on('blur', function () {
    let items = $(this).val()
      .split(',')
      .map(s => s.trim())
      .filter((s, i, arr) => s.length > 0 && arr.indexOf(s) === i); // ตัดว่างและกันซ้ำ
    $(this).val(items.join(', '));
  });
</script>


            <div class="col-4" id="reason_vct_other_div" class="col-8 form-group {{ $errors->has('reason_vct_other') ? 'has-error' : '' }}">
                <label for="reason_vct_other" class="control-label">{{ 'เหตุผล Vct อื่น ๆ' }}</label>
                <input class="form-control" name="reason_vct_other" type="text" id="reason_vct_other" value="{{ isset($visit->reason_vct_other) ? $visit->reason_vct_other : '' }}">
                {!! $errors->first('reason_vct_other', '<p class="help-block">:message</p>') !!}
            </div>

            <div class="col-4 form-group {{ $errors->has('other_risk_behavior') ? 'has-error' : '' }}">
                <label for="other_risk_behavior" class="control-label">{{ ' พฤติกรรมเสี่ยง อื่น ๆ' }}</label>
                <input class="form-control" name="other_risk_behavior" type="text" id="other_risk_behavior" value="{{ isset($visit->other_risk_behavior) ? $visit->other_risk_behavior : '' }}">
                {!! $errors->first('other_risk_behavior', '<p class="help-block">:message</p>') !!}
            </div>
        </div>
        @endif {{-- ปิดส่วน STI (ซ่อนสำหรับ TMC) --}}


        <div class="row">
            @if((int) ($visit->clinic ?? 1) !== 3)
            <div class="col-6 form-group {{ $errors->has('sti_hostory') ? 'has-error' : '' }}">
                <label for="sti_hostory" class="control-label">{{ 'ประวัติโรค STI (โรค, ระยะเวลา)' }}</label>
                <input class="form-control" name="sti_hostory" type="text" id="sti_hostory" value="{{ isset($visit->sti_hostory) ? $visit->sti_hostory : '' }}">
                {!! $errors->first('sti_hostory', '<p class="help-block">:message</p>') !!}
            </div>
            @endif
            <div class="col-6  form-group {{ $errors->has('contraceptive_method') ? 'has-error' : '' }}">
                <label for="contraceptive_method" class="control-label click" data-bs-toggle="modal" data-bs-target="#contraceptive_method_model">{{ 'วิธีการคุมกำเนิด' }}</label>
                <input class="form-control" name="contraceptive_method" type="text" id="contraceptive_method" value="{{ isset($visit->contraceptive_method) ? $visit->contraceptive_method : '' }}">
                {!! $errors->first('contraceptive_method', '<p class="help-block">:message</p>') !!}
            </div>

            <div class="modal fade" id="contraceptive_method_model" data-bs-keyboard="false" tabindex="-1"
            aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">Contraceptive Option</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @foreach (App\Models\Visit::$ContraceptionOption as $item)
                            <p class='contraceptive_method_item' style="display: list-item; margin-left : 1em;" onmouseover="this.style.color='red'" onmouseout ="this.style.color='black'"> {{ $item }}</p>
                        @endforeach             
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $('.contraceptive_method_item').click(function() {
                console.log($(this).text())
                $('#contraceptive_method').val($('#contraceptive_method').val() + $(this).text() + ', ');

            })
            document.querySelectorAll('.contraceptive_method_item').forEach(item => {
                item.style.cursor = 'pointer';
            });            
        </script>

            <?php
            if(isset($visit)){
                $patient = App\Models\Visit::getPatient($visit);
            }else{
                $patient = App\Models\Visit::getPatient();
            }
            
            ?>
            @if($patient && $patient->sex == 2 )
            <div class="col-3 form-group {{ $errors->has('LMP') ? 'has-error' : '' }}">
                <label for="LMP" class="control-label" title="วันแรกของการมีประจำเดือนครั้งสุดท้าย">ประจำเดือนครั้งสุดท้าย (LMP)</label>
                <input class="form-control date" name="LMP" type="text" id="LMP" autocomplete="off"
                       placeholder="วว/ดด/ปปปป" value="{{ isset($visit->LMP) ? $visit->LMP : '' }}">
                {!! $errors->first('LMP', '<p class="help-block">:message</p>') !!}
            </div>
            <div class="col-4 form-group {{ $errors->has('lmp_days') ? 'has-error' : '' }}">
                <label class="control-label d-block" title="นับจากวันแรกของ LMP ถึงวันนี้">ประจำเดือนมาแล้วกี่วัน</label>
                @php $__lmpDays = old('lmp_days', $visit->lmp_days ?? ''); @endphp
                <div class="d-flex flex-wrap" style="gap: .75rem;">
                    @foreach (['1','2','3','4','5','6','7+'] as $__d)
                        <div class="form-check form-check-inline m-0">
                            <input class="form-check-input" type="radio" name="lmp_days" id="lmp_days_{{ $__d }}"
                                   value="{{ $__d }}" {{ $__lmpDays == $__d ? 'checked' : '' }}>
                            <label class="form-check-label" for="lmp_days_{{ $__d }}">{{ $__d }}</label>
                        </div>
                    @endforeach
                </div>
                {!! $errors->first('lmp_days', '<p class="help-block">:message</p>') !!}
            </div>
            @endif

        </div>
    </div>
</div>
</div>
<script type="text/javascript">
    // $("#reason_sti").change(function (){
    //     console.log("reason_sti")
    //     if($("#reason_sti").val()==10){
    //         $("#reason_sti_other_div").show()
    //     }else{
    //         $("#reason_sti_other_div").hide()
    //         $("#reason_sti_other").val("")
    //     }
    // })
    // $("#reason_sti").change();

    // $("#reason_vct").change(function (){
    //     if($("#reason_vct").val()==10){
    //         $("#reason_vct_other_div").show()
    //     }else{
    //         $("#reason_vct_other_div").hide()
    //         $("#reason_vct_other").val("")
            
    //     }
    // })
    // $("#reason_vct").change();

</script>

