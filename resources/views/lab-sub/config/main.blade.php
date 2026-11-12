@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <!-- @include('admin.sidebar') -->

        <div class="col-md-12">
            <div class="card">
                <div class="card-header">New Add LabSub</div>
                <div class="card-body">
                    <a href="{{ $backUrl }}" title="Back"><button class="btn btn-warning btn-sm"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back</button></a>
 
                    <br />
                    <br />


                    <?php $visit = App\Models\Visit::find($_GET['visit']) ?>
                    <?php $labSubConfig = $visit->lab()->labSubConfig() ?>

     
                 <form method="POST" action="{{ route('lab-sub.save-config',['page'=>request('page', 1)]) }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <input type="hidden" name="lab_id" value="{{$visit->lab()->id}}">


                        <h3>Gram stain</h3>
                        @include ('lab-sub.config.specimenFrom', ['method' => '1','labSubConfig' => $labSubConfig])
                        <hr>

                        <h3>Wet smear</h3>
                        @include ('lab-sub.config.specimenFrom', ['method' => '2','labSubConfig' => $labSubConfig])
                        <hr>
                        <?php 
                        // <h3>PAP Smear</h3>
                        // @include ('lab-sub.config.specimenFrom', ['method' => '3','labSubConfig' => $labSubConfig])
                        // <hr>
                        ?>
                        <h3>Culture</h3>
                        @include ('lab-sub.config.specimenFrom', ['method' => '4','labSubConfig' => $labSubConfig])
                        <hr>
 
                        <h3>PCR for CT/NG</h3>
                        @include ('lab-sub.config.specimenFromPcr', ['method' => '5','labSubConfig' => $labSubConfig])
                        <hr>

                        <h3>Blood test</h3>
                        <div class="small text-muted mb-2">
                            HBs Antibody ย้ายไปสั่งที่ <b>Lab นอก</b> แล้ว ชื่อ
                            "Hepatitis B surface Antibody (HBsAb, Anti-HBs)"
                        </div>
                        @include ('lab-sub.config.blood', ['labSubConfig' => $labSubConfig])
                        <hr>
                        {{-- Pap Smear ย้ายไปสั่งที่ "Lab นอก" แล้ว (พยาบาลขอ 2569-08-26)
                             แสดงเฉพาะใบที่ "เคยสั่งไว้แล้ว" เท่านั้น ห้ามซ่อนทิ้งดื้อ ๆ
                             เพราะ saveConfig() ลบ lab_subs ของใบนี้ทั้งหมดแล้วสร้างใหม่จากที่ติ๊กมา
                             ถ้าไม่มีช่องให้ติ๊ก ของเก่าจะหายทันทีที่พยาบาลกดบันทึกครั้งถัดไป --}}
                        @if (isset($labSubConfig[13]))
                            <h3>Pap Smear <span class="badge bg-warning text-dark">ย้ายไป Lab นอกแล้ว</span></h3>
                            <div class="small text-muted mb-2">
                                ใบนี้เคยสั่งไว้ก่อนย้าย ยังแก้ได้ตามปกติ · ครั้งต่อไปสั่งที่ Lab นอก ชื่อ
                                "Pap smear, ThinPrep method (BGH)"
                            </div>
                            @include ('lab-sub.config.specimenFrom', ['method' => '13','labSubConfig' => $labSubConfig])
                            <hr>
                        @endif
                        <div  style="display: flex;">
                            <label class="control-label pe-2" for="other">
                                Other
                            </label>
                            <input class="form-control" type="text"  id="other" name="other" value="{{ isset($labSubConfig[10]) ? $labSubConfig[10]['key'] : '' }}">
                            <input  type="hidden"  id="othervalue" name="othervalue" value="{{ isset($labSubConfig[10]) ? $labSubConfig[10]['value'] : '' }}">
                        </div>
                        <hr>
                        <div class="form-group">
    <label for="collected_date" class="control-label">Collected Date</label>
    <input class="form-control date" name="collected_date" type="text" id="collected_date" 
       value="{{ old('collected_date', isset($lab->collected_date) ? $lab->collected_date : \Carbon\Carbon::now()->addYears(543)->format('Y-m-d')) }}">

   <input class="form-control" name="collected_at" type="time" id="collected_at"
  value="{{ old('collected_at',
            isset($lab->collected_at)
              ? \Carbon\Carbon::parse($lab->collected_at)->format('H:i')
              : now()->format('H:i')
          ) }}">
</div>
<hr>
<div class="form-group {{ $errors->has('status') ? 'has-error' : ''}}">
    <label for="status" class="control-label">{{ 'Status' }}</label>
    <select class="form-control" name="status" id="status" required>
        {{-- ค่าตั้งต้น = "ส่ง Lab" (flow ใหม่ 2569-09-30) เดิมต้องเลือกเองทุกครั้ง ลืมแล้วบันทึกไม่ได้ --}}
        @php $__st = ! empty($lab->status) ? (int) $lab->status : 1; @endphp
        <option value="" disabled>เลือก *ส่ง Lab* นะจ๊ะ</option>
        @foreach (App\Models\Lab::$statusOption as $key => $value)
            <option value="{{ $key }}"
                @if ($key == $__st) selected="selected" @endif>
                {{ $value }}
            </option>
        @endforeach
    </select>
    {!! $errors->first('status', '<p class="help-block">:message</p>') !!}
</div>

                        {{-- Lab ส่งตรวจภายนอก แยกเป็นโมดูลของตัวเองแล้ว (การ์ด "Lab ส่งตรวจภายนอก" ในแท็บ Lab
                             ของหน้าตรวจ) หน้านี้จึงเหลือเฉพาะรายการตรวจในบ้าน --}}
                        <div class="form-group">
                            <input class="btn btn-primary" type="submit" value="save">
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection