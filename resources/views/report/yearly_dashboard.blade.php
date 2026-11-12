@extends('layouts.app')

@section('content')
<div class="container py-4">
  {{-- เปลี่ยนหัวข้อให้แสดงวันที่ --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="fw-bold text-primary">
        📊 แดชบอร์ดสถิติ
        @if(isset($start_input) && isset($end_input))
            <span style="font-size: 1.2rem; color: #555;">(ตั้งแต่วันที่ {{ \Carbon\Carbon::parse($start_input)->format('d/m/Y') }} ถึง {{ \Carbon\Carbon::parse($end_input)->format('d/m/Y') }})</span>
        @endif
    </h1>
  </div>

  {{-- ฟอร์มค้นหาแบบใหม่: Date Range Selector & Export Button --}}
  <form method="GET" action="{{ route('report.yearly') }}" class="mb-4">
    {{-- เปิดด้วยลิงก์ลับ (ไม่ได้ login) ต้องพา ?key ไปกับฟอร์มด้วย ไม่งั้นกดค้นหาแล้วเด้งไปหน้า login --}}
    @guest
      @if(request('key'))
        <input type="hidden" name="key" value="{{ request('key') }}">
      @endif
    @endguest
    <div class="row g-2 align-items-end">
      <div class="col-auto">
        <label for="start_date" class="form-label fw-semibold">ตั้งแต่วันที่</label>
        <input type="date" name="start_date" id="start_date" class="form-control shadow-sm" value="{{ $start_input ?? date('Y').'-01-01' }}" required>
      </div>
      <div class="col-auto">
        <label for="end_date" class="form-label fw-semibold">ถึงวันที่</label>
        <input type="date" name="end_date" id="end_date" class="form-control shadow-sm" value="{{ $end_input ?? date('Y-m-d') }}" required>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-success shadow-sm px-4">
            <i class="fas fa-search"></i> ดูรายงาน
        </button>
        {{-- ปุ่ม Export ทุกตารางบนหน้าจอเป็น Excel ด้วย Javascript --}}
        <button type="button" class="btn btn-warning shadow-sm px-4 ms-2" onclick="exportDashboardToExcel()">
            <i class="fas fa-file-excel"></i> Export หน้าตารางนี้เป็น Excel
        </button>
        @auth
        {{-- เปิด/ปิดลิงก์สำหรับส่งให้หัวหน้าหรือฉายในห้องประชุม (คนที่เปิดด้วยลิงก์ลับจะไม่เห็นปุ่มนี้) --}}
        <a href="{{ route('report.share') }}" class="btn btn-outline-secondary shadow-sm px-3 ms-2" title="เปิด/ปิดลิงก์สำหรับส่งให้หัวหน้า">
            <i class="fas fa-share-nodes"></i> ลิงก์ส่งให้หัวหน้า
        </a>
        @hasanyrole('Admin|Nurse|Doctor')
        {{-- ทางลัดไปหน้าดึงรายชื่อ — หน้านี้ตอบได้แค่ "กี่คน" ถ้าอยากรู้ "ใครบ้าง" ต้องไปหน้านั้น --}}
        <a href="{{ route('report.explore') }}" class="btn btn-outline-primary shadow-sm px-3 ms-2"
           title="อยากได้รายชื่อคนไข้ตามเงื่อนไข เลือกเองได้ที่นี่">
            <i class="fas fa-magnifying-glass-chart"></i> ดึงรายชื่อคนไข้เอง
        </a>
        @endhasanyrole
        @endauth
      </div>
    </div>
  </form>

  <div class="alert alert-warning py-2 px-3 mb-3" style="font-size: 0.9rem;">
    <i class="fas fa-info-circle"></i>
    <strong>หมายเหตุ:</strong> ตัวเลข "จำนวนผู้รับบริการ" นับเฉพาะคนไข้ที่ไม่ซ้ำกัน (unique) <u>ภายในช่วงวันที่ที่เลือกเท่านั้น</u>
    ถ้าคนไข้คนเดียวกันมารับบริการมากกว่า 1 เดือนในช่วงนั้น การเปิดดูทีละเดือนแล้วนำมาบวกกันเอง
    จะได้ยอดสูงกว่าการเลือกช่วงวันที่รวมทีเดียว เพราะคนคนเดียวถูกนับซ้ำในแต่ละเดือนที่มา
    (ส่วน "จำนวนครั้งที่รับบริการ" นับทุกครั้งที่มา ไม่ตัดซ้ำ จึงบวกทีละเดือนแล้วตรงกับยอดรวมเสมอ)
  </div>

  {{-- ส่วนที่เหลือของไฟล์เหมือนเดิม --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card text-white text-center shadow" style="background: #1976d2;">
        <div class="card-body py-4">
          <div class="display-3 fw-bold" style="text-shadow: 1px 2px 7px #3332;">{{ $totalPatients }}</div>
          <div class="fw-light fs-5 mt-1">
            จำนวนผู้รับบริการ
            <i class="fas fa-info-circle" title="นับคนไข้ไม่ซ้ำเฉพาะในช่วงวันที่ที่เลือก ดูหมายเหตุด้านบน" style="cursor: help;"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-white text-center shadow" style="background: #26c6da;">
        <div class="card-body py-4">
          <div class="display-3 fw-bold" style="text-shadow: 1px 2px 7px #3332;">{{ $malePatients }}</div>
          <div class="fw-light fs-5 mt-1">ชาย</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-white text-center shadow" style="background: #ffc107;">
        <div class="card-body py-4">
          <div class="display-3 fw-bold" style="text-shadow: 1px 2px 7px #3332;">{{ $femalePatients }}</div>
          <div class="fw-light fs-5 mt-1">หญิง</div>
        </div>
      </div>
    </div>
  
    <div class="col-6 col-md-3">
      <div class="card text-white text-center shadow" style="background: #388e3c;">
        <div class="card-body py-3">
          <div class="display-3 fw-bold" style="text-shadow: 1px 2px 7px #3332;">{{ $totalVisits }}</div>
          <div class="fw-light fs-5 mt-1">จำนวนครั้งที่รับบริการ</div>
          
          {{-- ส่วนที่เพิ่ม: แยกชายหญิง --}}
          <div class="mt-2 pt-2 border-top border-white border-opacity-25 d-flex justify-content-center gap-3">
            <span style="font-size: 0.9rem;">
                ชาย: <strong>{{ $maleVisits }}</strong>
            </span>
            <span style="font-size: 0.9rem;">
                หญิง: <strong>{{ $femaleVisits }}</strong>
            </span>
          </div>

        </div>
      </div>
    </div>

  @if(isset($diseaseNewRepeatRows))
  <div class="row g-3 mb-4">
    @php
      $diseaseColors = [
        'Syphilis' => [
          'bg' => '#e3f0ff', 'head' => '#1976d2',
          'new_male' => '#1976d2', 'new_female' => '#f06292',
          'repeat_male' => '#00bcd4', 'repeat_female' => '#ffd600'
        ],
        'Gonorrhea' => [
          'bg' => '#e5fbe5', 'head' => '#388e3c',
          'new_male' => '#388e3c', 'new_female' => '#f06292',
          'repeat_male' => '#00bcd4', 'repeat_female' => '#ffd600'
        ],
        'Non-Gonococcal urethritis' => [
          'bg' => '#fffbe4', 'head' => '#ffd600',
          'new_male' => '#ffd600', 'new_female' => '#ffb300',
          'repeat_male' => '#00bcd4', 'repeat_female' => '#1976d2'
        ]
      ];
    @endphp
    @foreach ($diseaseNewRepeatRows as $disease => $row)
      @php $color = $diseaseColors[$disease] ?? $diseaseColors['Syphilis']; @endphp
      <div class="col-12 col-md-4">
        <div class="card shadow border-0" style="background:{{ $color['bg'] }}; border-radius:1.4rem;">
          <div class="card-header fs-5 fw-bold text-center border-0"
               style="background:{{ $color['head'] }}; color:white; border-top-left-radius:1.4rem; border-top-right-radius:1.4rem; letter-spacing:1px;">
            {{ $disease }}
          </div>
          <div class="card-body py-3">
            <div class="row">
              <div class="col-6">
                <div class="mb-1 text-secondary" style="font-weight:600;">ผู้ป่วยใหม่</div>
                <div class="d-flex justify-content-center align-items-center gap-2 mb-1">
                  <span class="fw-bold display-5" style="color:{{ $color['new_male'] }};">{{ $row['new_male'] }}</span>
                  <span class="fw-bold display-5" style="color:#999; font-size:2.3rem;">/</span>
                  <span class="fw-bold display-5" style="color:{{ $color['new_female'] }};">{{ $row['new_female'] }}</span>
                </div>
                <div class="text-muted" style="font-size:13px;">ชาย / หญิง</div>
              </div>
              <div class="col-6">
                <div class="mb-1 text-secondary" style="font-weight:600;">โรคซ้ำ</div>
                <div class="d-flex justify-content-center align-items-center gap-2 mb-1">
                  <span class="fw-bold display-5" style="color:{{ $color['repeat_male'] }};">{{ $row['repeat_male'] }}</span>
                  <span class="fw-bold display-5" style="color:#999; font-size:2.3rem;">/</span>
                  <span class="fw-bold display-5" style="color:{{ $color['repeat_female'] }};">{{ $row['repeat_female'] }}</span>
                </div>
                <div class="text-muted" style="font-size:13px;">ชาย / หญิง</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>
  @endif

  {{-- กราฟ --}}
  <div class="row mb-4">
    <div class="col-6">
      <div class="card h-100 shadow-sm">
        <div class="card-header bg-success text-white fw-bold">ผู้มารับบริการแยกตามกลุ่มเป้าหมาย (ราย)</div>
        <div class="card-body">
          <canvas id="riskGroupBar" style="min-height:240px"></canvas>
        </div>
      </div>
    </div>
    <div class="col-6">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white fw-bold">ผู้ป่วยทั้งหมดจำแนกตามเพศและโรค (ราย)</div>
        <div class="card-body">
          <canvas id="diseaseBar" style="min-height:300px"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-header bg-info text-white fw-bold">สัดส่วนเพศ (ราย)</div>
        <div class="card-body">
          <canvas id="genderPie" style="min-height:240px"></canvas>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-header bg-warning fw-bold">สัดส่วนสัญชาติ (ราย)</div>
        <div class="card-body">
          <canvas id="nationPie" style="min-height:240px"></canvas>
        </div>
      </div>
    </div>
      {{-- สัดส่วนภูมิลำเนา --}}
  @isset($locationShare)
  
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-header bg-secondary text-white fw-bold">สัดส่วนภูมิลำเนา (ราย)</div>
        <div class="card-body">
          <canvas id="locationPie" style="min-height:240px"></canvas>
          <div class="mt-3 small text-muted">
            <div>เมืองเชียงใหม่: {{ $locationShare['เมืองเชียงใหม่'] ?? 0 }} คน ({{ $locPercent['เมืองเชียงใหม่'] ?? 0 }}%)</div>
            <div>ต่างอำเภอ (เชียงใหม่): {{ $locationShare['ต่างอำเภอ (จ.เชียงใหม่)'] ?? 0 }} คน ({{ $locPercent['ต่างอำเภอ (จ.เชียงใหม่)'] ?? 0 }}%)</div>
            <div>ต่างจังหวัด: {{ $locationShare['ต่างจังหวัด'] ?? 0 }} คน ({{ $locPercent['ต่างจังหวัด'] ?? 0 }}%)</div>
            <div>ไม่ระบุที่อยู่: {{ $locationShare['ไม่ระบุที่อยู่'] ?? 0 }} คน ({{ $locPercent['ไม่ระบุที่อยู่'] ?? 0 }}%)</div>
          </div>
        </div>
      </div>
    </div>
  
  @endisset
  </div>



  <div class="row mb-4">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white fw-bold">ผู้มารับบริการแยกตามช่วงอายุและเพศ (ราย)</div>
        <div class="card-body">
          <canvas id="ageBar" style="min-height:300px"></canvas>
        </div>
      </div>
    </div>
  </div>

  {{-- ผู้ป่วยตามช่วงอายุ --}}
  @isset($ageBySex)
  <h5 class="mt-4 fw-bold">ผู้มารับบริการแยกตามช่วงอายุและเพศ (ราย)</h5>
  <table class="table table-bordered text-center align-middle mb-4">
    <thead class="bg-main">
      <tr>
        <th class="text-start">ช่วงอายุ</th>
        <th class="bg-total">รวม</th>
        <th class="bg-male">ชาย</th>
        <th class="bg-female">หญิง</th>
      </tr>
    </thead>
    <tbody>
      @foreach($ageBySex as $label => $row)
        <tr>
          <td class="text-start">{{ $label }}</td>
          <td class="bg-total">{{ $row['total'] }}</td>
          <td class="bg-male">{{ $row['male'] }}</td>
          <td class="bg-female">{{ $row['female'] }}</td>
        </tr>
      @endforeach
      {{-- ส่วนที่เพิ่ม: แถวรวม --}}
      <tr class="fw-bold bg-light">
         <td class="text-start">รวมทั้งหมด</td>
         <td class="bg-total">{{ array_sum(array_column($ageBySex, 'total')) }}</td>
         <td class="bg-male">{{ array_sum(array_column($ageBySex, 'male')) }}</td>
         <td class="bg-female">{{ array_sum(array_column($ageBySex, 'female')) }}</td>
      </tr>
    </tbody>
  </table>
  @endisset

  {{-- (3.1) Patient by risk group (รายคน - มีอยู่แล้ว) --}}
  <h5 class="mt-4 fw-bold">ผู้มารับบริการแยกตามกลุ่มเป้าหมาย (ราย)</h5>
  <table class="table table-bordered text-center align-middle mb-4">
    <thead class="bg-main">
      <tr>
        <th class="text-start">กลุ่มเสี่ยง</th>
        <th class="bg-total">รวม</th>
        <th class="bg-male">ชาย</th>
        <th class="bg-female">หญิง</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($riskSummary as $name => $row)
        <tr>
          <td class="text-start">{{ $name }}</td>
          <td class="bg-total">{{ $row['total'] }}</td>
          <td class="bg-male">{{ $row['male'] }}</td>
          <td class="bg-female">{{ $row['female'] }}</td>
        </tr>
      @endforeach
      <tr class="fw-bold bg-light">
        <td class="text-start">รวมทั้งหมด</td>
        <td class="bg-total">{{ array_sum(array_column($riskSummary, 'total')) }}</td>
        <td class="bg-male">{{ array_sum(array_column($riskSummary, 'male')) }}</td>
        <td class="bg-female">{{ array_sum(array_column($riskSummary, 'female')) }}</td>
      </tr>
    </tbody>
  </table>

  {{-- (3.2) ส่วนที่เพิ่ม: Visit by risk group (จำนวนครั้ง) --}}
  @isset($riskVisitSummary)
  <h5 class="mt-4 fw-bold text-primary">จำนวนครั้งที่รับบริการแยกตามกลุ่มเป้าหมาย (ครั้ง)</h5>
  <table class="table table-bordered text-center align-middle mb-4">
    <thead class="bg-main">
      <tr>
        <th class="text-start">กลุ่มเสี่ยง</th>
        <th class="bg-total">รวม (ครั้ง)</th>
        <th class="bg-male">ชาย (ครั้ง)</th>
        <th class="bg-female">หญิง (ครั้ง)</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($riskVisitSummary as $name => $row)
        <tr>
          <td class="text-start">{{ $name }}</td>
          <td class="bg-total">{{ $row['total'] }}</td>
          <td class="bg-male">{{ $row['male'] }}</td>
          <td class="bg-female">{{ $row['female'] }}</td>
        </tr>
      @endforeach
      <tr class="fw-bold bg-light">
        <td class="text-start">รวมทั้งหมด</td>
        <td class="bg-total">{{ array_sum(array_column($riskVisitSummary, 'total')) }}</td>
        <td class="bg-male">{{ array_sum(array_column($riskVisitSummary, 'male')) }}</td>
        <td class="bg-female">{{ array_sum(array_column($riskVisitSummary, 'female')) }}</td>
      </tr>
    </tbody>
  </table>
  @endisset

  {{-- ตารางโรคหลัก (ปรับปรุงตามรูปภาพ) --}}
  @isset($diseaseRows)
  <h5 class="mt-4 fw-bold">ผู้ป่วยโรคติดต่อทางเพศสัมพันธ์ทั้งหมด (ราย)</h5>
  {{-- มีชื่อ-HN — ซ่อนตอนเปิดผ่านลิงก์แชร์ (ไม่ได้ login) · ช่วงวัน/คลินิกเดียวกับหน้านี้ --}}
  @auth
    <a href="{{ route('export.visit.diagnosis', ['start_date' => $start_input ?? date('Y').'-01-01', 'end_date' => $end_input ?? date('Y-m-d')]) }}" class="btn btn-success shadow-sm px-4 mb-3">
      <i class="fas fa-file-excel"></i> โหลด Excel รายชื่อ + วินิจฉัย + สัญชาติ (มีตารางสรุป)
    </a>
  @endauth
  <div class="table-responsive">
      <table class="table table-bordered text-center align-middle mb-4">
        <thead class="bg-main align-middle">
          <tr>
            <th rowspan="2" style="width: 25%;">กิจกรรม / โรค</th>
            <th rowspan="2" style="width: 5%;">หน่วยนับ</th>
            <th colspan="3">จำนวน</th>
            <th colspan="7">จำนวนแยกตามกลุ่มเป้าหมาย</th>
          </tr>
          <tr>
            <th class="bg-total">รวม</th>
            <th class="bg-male">ช</th>
            <th class="bg-female">ญ</th>
            
            <th style="font-size: 0.85rem;">พนักงานบริการหญิง<br>(FSW)</th>
            <th style="font-size: 0.85rem;">พนักงานบริการชาย<br>(MSW)</th>
            <th style="font-size: 0.85rem;">ชายที่มีเพศสัมพันธ์กับชาย<br>(MSM)</th>
            <th style="font-size: 0.85rem;">ผู้ใช้ยาเสพติดชนิดฉีด<br>(IDU)</th>
            <th style="font-size: 0.85rem;">แรงงานข้ามชาติ</th>
            <th style="font-size: 0.85rem;">เยาวชน (10-24 ปี)</th>
            <th style="font-size: 0.85rem;">กลุ่มอื่นๆ</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($diseaseRows as $disease => $row)
            <tr>
              <td class="text-start ps-3">{{ $disease }}</td>
              <td>ราย</td>
              <td class="bg-total fw-bold">{{ $row['total'] }}</td>
              <td class="bg-male">{{ $row['male'] }}</td>
              <td class="bg-female">{{ $row['female'] }}</td>
              
              {{-- กลุ่มเป้าหมาย --}}
              <td>{{ $row['fsw'] }}</td>
              <td>{{ $row['msw'] }}</td>
              <td>{{ $row['msm'] }}</td>
              <td>{{ $row['idu'] }}</td>
              <td>{{ $row['migrant'] }}</td>
              <td>{{ $row['youth'] }}</td>
              <td>{{ $row['other'] }}</td>
            </tr>
          @endforeach
          
          {{-- แถวรวม --}}
          <tr class="fw-bold bg-light">
             <td class="text-start ps-3">รวมทั้งหมด</td>
             <td>ราย</td>
             <td class="bg-total">{{ array_sum(array_column($diseaseRows, 'total')) }}</td>
             <td class="bg-male">{{ array_sum(array_column($diseaseRows, 'male')) }}</td>
             <td class="bg-female">{{ array_sum(array_column($diseaseRows, 'female')) }}</td>
             
             <td>{{ array_sum(array_column($diseaseRows, 'fsw')) }}</td>
             <td>{{ array_sum(array_column($diseaseRows, 'msw')) }}</td>
             <td>{{ array_sum(array_column($diseaseRows, 'msm')) }}</td>
             <td>{{ array_sum(array_column($diseaseRows, 'idu')) }}</td>
             <td>{{ array_sum(array_column($diseaseRows, 'migrant')) }}</td>
             <td>{{ array_sum(array_column($diseaseRows, 'youth')) }}</td>
             <td>{{ array_sum(array_column($diseaseRows, 'other')) }}</td>
          </tr>
        </tbody>
      </table>
  </div>
  @endisset

  {{-- ตารางกิจกรรม/โรคย่อย --}}
  @if(isset($activityRows) && count($activityRows))
    <h5 class="mt-4 fw-bold">ตารางกิจกรรม แยกตามเพศและกลุ่มเป้าหมาย (ครั้ง)</h5>
    <table class="table table-bordered text-center align-middle mb-4">
      <thead class="bg-main align-middle">
        <tr>
          <th rowspan="2">กิจกรรม</th>
          <th colspan="3">รวม</th>
          <th colspan="7">กลุ่มเสี่ยง</th>
        </tr>
        <tr>
          <th class="bg-male">ชาย</th><th class="bg-female">หญิง</th><th class="bg-total">รวม</th>
          <th>FSW</th><th>MSW</th><th>MSM</th><th>แรงงานข้ามชาติ</th><th>เรือนจำ</th><th>เยาวชน</th><th>อื่น ๆ</th>
        </tr>
      </thead>
      <tbody>
        @foreach($activityRows as $row)
        <tr>
          <td class="text-start">{{ $row['activity'] }}</td>
          <td class="bg-male">{{ $row['male'] }}</td>
          <td class="bg-female">{{ $row['female'] }}</td>
          <td class="bg-total fw-strong">{{ $row['total'] }}</td>
          <td>{{ $row['fsw'] }}</td>
          <td>{{ $row['msw'] }}</td>
          <td>{{ $row['msm'] }}</td>
          <td>{{ $row['labor'] }}</td>
          <td>{{ $row['prisoner'] }}</td>
          <td>{{ $row['young'] }}</td>
          <td>{{ $row['other'] }}</td>
        </tr>
        @endforeach
        
        {{-- ส่วนที่เพิ่ม: แถวรวมทั้งหมด --}}
        <tr class="fw-bold bg-light">
           <td class="text-start">รวมทั้งหมด</td>
           <td class="bg-male">{{ array_sum(array_column($activityRows, 'male')) }}</td>
           <td class="bg-female">{{ array_sum(array_column($activityRows, 'female')) }}</td>
           <td class="bg-total fw-strong">{{ array_sum(array_column($activityRows, 'total')) }}</td>
           <td>{{ array_sum(array_column($activityRows, 'fsw')) }}</td>
           <td>{{ array_sum(array_column($activityRows, 'msw')) }}</td>
           <td>{{ array_sum(array_column($activityRows, 'msm')) }}</td>
           <td>{{ array_sum(array_column($activityRows, 'labor')) }}</td>
           <td>{{ array_sum(array_column($activityRows, 'prisoner')) }}</td>
           <td>{{ array_sum(array_column($activityRows, 'young')) }}</td>
           <td>{{ array_sum(array_column($activityRows, 'other')) }}</td>
        </tr>
      </tbody>
    </table>
  @endif
</div>
{{-- ตารางยอดการส่งตรวจทางห้องปฏิบัติการ (Lab Tests) --}}
  @isset($labTestRows)
  <h5 class="mt-5 fw-bold text-primary">📊 สถิติการส่งตรวจทางห้องปฏิบัติการ (ครั้ง)</h5>
  <div class="table-responsive">
    <a href="{{ route('export.lab.list', ['start_date' => $start_input ?? date('Y').'-01-01', 'end_date' => $end_input ?? date('Y-m-d')]) }}" class="btn btn-info shadow-sm px-4 ms-2 mb-3">
    <i class="fas fa-file-excel"></i> โหลดรายชื่อผู้ตรวจ Lab ไปเทียบสมุด
</a>
      <table class="table table-bordered text-center align-middle mb-4">
        <thead class="bg-main align-middle">
          <tr>
            <th rowspan="2" style="width: 20%;">รายการตรวจ (Lab Test)</th>
            <th colspan="3">จำนวน (ครั้ง)</th>
            <th colspan="7">แยกตามกลุ่มเป้าหมาย (ครั้ง)</th>
          </tr>
          <tr>
            <th class="bg-total">รวม</th>
            <th class="bg-male">ช</th>
            <th class="bg-female">ญ</th>
            
            <th style="font-size: 0.85rem;">FSW</th>
            <th style="font-size: 0.85rem;">MSW</th>
            <th style="font-size: 0.85rem;">MSM</th>
            <th style="font-size: 0.85rem;">IDU</th>
            <th style="font-size: 0.85rem;">แรงงานข้ามชาติ</th>
            <th style="font-size: 0.85rem;">เยาวชน</th>
            <th style="font-size: 0.85rem;">กลุ่มอื่นๆ</th>
          </tr>
        </thead>
        <tbody>
          @foreach($labTestRows as $testName => $row)
            <tr>
              <td class="text-start ps-3 fw-bold text-secondary">{{ $testName }}</td>
              <td class="bg-total fw-bold text-dark">{{ $row['total'] }}</td>
              <td class="bg-male">{{ $row['male'] }}</td>
              <td class="bg-female">{{ $row['female'] }}</td>
              
              <td>{{ $row['fsw'] }}</td>
              <td>{{ $row['msw'] }}</td>
              <td>{{ $row['msm'] }}</td>
              <td>{{ $row['idu'] }}</td>
              <td>{{ $row['migrant'] }}</td>
              <td>{{ $row['youth'] }}</td>
              <td>{{ $row['other'] }}</td>
            </tr>
          @endforeach

          {{-- แถวรวม --}}
          <tr class="fw-bold bg-light">
             <td class="text-start ps-3">รวมทั้งหมด</td>
             <td class="bg-total text-dark">{{ array_sum(array_column($labTestRows, 'total')) }}</td>
             <td class="bg-male">{{ array_sum(array_column($labTestRows, 'male')) }}</td>
             <td class="bg-female">{{ array_sum(array_column($labTestRows, 'female')) }}</td>
             
             <td>{{ array_sum(array_column($labTestRows, 'fsw')) }}</td>
             <td>{{ array_sum(array_column($labTestRows, 'msw')) }}</td>
             <td>{{ array_sum(array_column($labTestRows, 'msm')) }}</td>
             <td>{{ array_sum(array_column($labTestRows, 'idu')) }}</td>
             <td>{{ array_sum(array_column($labTestRows, 'migrant')) }}</td>
             <td>{{ array_sum(array_column($labTestRows, 'youth')) }}</td>
             <td>{{ array_sum(array_column($labTestRows, 'other')) }}</td>
          </tr>
        </tbody>
      </table>
  </div>
  @endisset
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
<script>
// ฟังก์ชันโหลดตารางหน้า Dashboard เป็นไฟล์ Excel (.xls)
function exportDashboardToExcel() {
    let template = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><style>table, th, td { border: 1px solid black; border-collapse: collapse; padding: 5px; text-align: center; } th { background-color: #f1f8e9; }</style></head><body>';
    
    let tablesHtml = '';
    let tables = document.querySelectorAll("table");
    
    tables.forEach(table => {
        let title = table.previousElementSibling;
        if(title && (title.tagName === 'H5' || title.tagName === 'H3' || title.tagName === 'DIV')) {
            tablesHtml += '<h3 style="color:#1976d2; margin-top: 20px;">' + title.innerText + '</h3>';
        }
        tablesHtml += table.outerHTML + '<br>';
    });
    
    let end = '</body></html>';
    // เติม BOM (\uFEFF) เพื่อบังคับให้ Excel อ่านภาษาไทย (UTF-8) ได้สมบูรณ์
    let html = '\uFEFF' + template + tablesHtml + end; 
    
    let blob = new Blob([html], { type: "application/vnd.ms-excel;charset=utf-8" });
    let url = URL.createObjectURL(blob);
    let downloadLink = document.createElement("a");
    downloadLink.href = url;
    downloadLink.download = "Dashboard_Export_" + (new Date().toISOString().slice(0,10)) + ".xls";
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Palette
  const pastelBlue = "rgba(100,181,246,0.7)";
  const pastelPink = "rgba(255,138,170,0.6)";
  const pastelYellow = "rgba(255,236,139,0.7)";
  const pastelGreen = "rgba(129,199,132,0.6)";
  const pastelGray = "rgba(158,158,158,0.25)";
  const pastelPurple = "rgba(186,104,200,0.5)";

  // 1. เพศ
  new Chart(document.getElementById('genderPie'), {
    type: 'pie',
    data: {
      labels: ['ชาย', 'หญิง'],
      datasets: [{
        data: [{{ $malePatients }}, {{ $femalePatients }}],
        backgroundColor: [pastelBlue, pastelPink],
        borderWidth: 0
      }]
    },
    options: {
      plugins: {
        legend: { position: 'bottom' },
        datalabels: {
          color: '#555',
          font: { weight: '500', size: 17 },
          backgroundColor: "rgba(255,255,255,0.72)",
          padding: 6,
          formatter: (v, ctx) => {
            const t = ctx.chart.data.datasets[0].data.reduce((a,b)=>a+b,0);
            const pct = t ? (v*100/t).toFixed(1) : 0;
            return v + ' (' + pct + '%)';
          }
        }
      }
    },
    plugins: [ChartDataLabels]
  });

  // 2. สัญชาติ
  new Chart(document.getElementById('nationPie'), {
    type: 'pie',
    data: {
      labels: ['ไทย', 'พม่า', 'อื่นๆ'],
      datasets: [{
        data: [{{ $thai }}, {{ $burmese }}, {{ $other_nationality }}],
        backgroundColor: [pastelGreen, pastelYellow, pastelGray],
        borderWidth: 0
      }]
    },
    options: {
      plugins: {
        legend: { position: 'right' },
        datalabels: {
          color: '#555',
          font: { weight: '500', size: 16 },
          backgroundColor: "rgba(255,255,255,0.82)",
          padding: 7,
          formatter: (v, ctx) => {
            const t = ctx.chart.data.datasets[0].data.reduce((a,b)=>a+b,0);
            const pct = t ? (v*100/t).toFixed(1) : 0;
            return v + ' (' + pct + '%)';
          }
        }
      }
    },
    plugins: [ChartDataLabels]
  });

  // 3. กลุ่มเสี่ยง
  new Chart(document.getElementById('riskGroupBar'), {
    type: 'bar',
    data: {
      labels: {!! json_encode(array_keys($riskSummary)) !!},
      datasets: [
        { label:'ชาย',   data:{!! json_encode(array_column($riskSummary,'male')) !!},   backgroundColor: pastelBlue,  borderRadius:8 },
        { label:'หญิง',  data:{!! json_encode(array_column($riskSummary,'female')) !!}, backgroundColor: pastelPink,  borderRadius:8 },
        { label:'รวม',   data:{!! json_encode(array_column($riskSummary,'total')) !!},  backgroundColor: pastelYellow,borderRadius:8 }
      ]
    },
    options: {
      plugins: {
        legend:{ position:'top' },
        datalabels:{ anchor:'end', align:'top', color:'#666', backgroundColor:"rgba(255,255,255,0.7)", borderRadius:6, padding:4, formatter:v=>v>0?v:'' }
      },
      scales: { y:{ beginAtZero:true, ticks:{ stepSize:1 } } }
    },
    plugins:[ChartDataLabels]
  });

  // 4. โรคหลัก
  @isset($diseaseRows)
  new Chart(document.getElementById('diseaseBar'), {
    type: 'bar',
    data: {
      labels: {!! json_encode(array_keys($diseaseRows)) !!},
      datasets: [
        { label:'ชาย',  data:{!! json_encode(array_column($diseaseRows,'male')) !!},   backgroundColor: pastelBlue, borderRadius:8 },
        { label:'หญิง', data:{!! json_encode(array_column($diseaseRows,'female')) !!}, backgroundColor: pastelPink, borderRadius:8 }
      ]
    },
    options: {
      plugins: {
        legend:{ position:'top' },
        datalabels:{ anchor:'end', align:'top', color:'#666', backgroundColor:"rgba(255,255,255,0.7)", borderRadius:6, padding:4, formatter:v=>v>0?v:'' }
      },
      scales:{ y:{ beginAtZero:true, ticks:{ stepSize:1 } } }
    },
    plugins:[ChartDataLabels]
  });
  @endisset

  // 5. อายุ × เพศ
  @isset($ageBySex)
  new Chart(document.getElementById('ageBar'), {
    type: 'bar',
    data: {
      labels: {!! json_encode(array_keys($ageBySex)) !!},
      datasets: [
        { label:'ชาย',  data:{!! json_encode(array_column($ageBySex,'male')) !!},   backgroundColor: pastelBlue,  borderRadius:8 },
        { label:'หญิง', data:{!! json_encode(array_column($ageBySex,'female')) !!}, backgroundColor: pastelPink,  borderRadius:8 },
        { label:'รวม',  data:{!! json_encode(array_column($ageBySex,'total')) !!},  backgroundColor: pastelYellow,borderRadius:8 },
      ]
    },
    options: {
      plugins: {
        legend:{ position:'top' },
        datalabels:{ anchor:'end', align:'top', color:'#666', formatter:v=>v>0?v:'' }
      },
      scales:{ y:{ beginAtZero:true } }
    },
    plugins:[ChartDataLabels]
  });
  @endisset

  // 6. ภูมิลำเนา
  @isset($locationShare)
  new Chart(document.getElementById('locationPie'), {
    type: 'pie',
    data: {
      labels: ['เมืองเชียงใหม่','ต่างอำเภอ (จ.เชียงใหม่)','ต่างจังหวัด','ไม่ระบุที่อยู่'],
      datasets: [{
        data: [
          {{ $locationShare['เมืองเชียงใหม่'] ?? 0 }},
          {{ $locationShare['ต่างอำเภอ (จ.เชียงใหม่)'] ?? 0 }},
          {{ $locationShare['ต่างจังหวัด'] ?? 0 }},
          {{ $locationShare['ไม่ระบุที่อยู่'] ?? 0 }}
        ],
        backgroundColor: [pastelBlue, pastelGreen, pastelGray, '#e9ecef'],
        borderWidth: 0
      }]
    },
    options: {
      plugins: {
        legend: { position:'bottom' },
        datalabels: {
          color:'#555', backgroundColor:"rgba(255,255,255,0.8)", borderRadius:8, padding:6,
          formatter:(v,ctx)=>{ const t=ctx.chart.data.datasets[0].data.reduce((a,b)=>a+b,0); const pct=t?(v*100/t).toFixed(1):0; return v+' ('+pct+'%)'; }
        }
      }
    },
    plugins:[ChartDataLabels]
  });
  @endisset
});
</script>

<link href="https://fonts.googleapis.com/css?family=Prompt:400,600&display=swap" rel="stylesheet">
<style>
  body, .chartjs-render-monitor { font-family: 'Prompt','Nunito',sans-serif; }
  .bg-male   { background-color:#B3E5FC !important; }  /* ฟ้า */
  .bg-female { background-color:#F8BBD0 !important; }  /* ชมพู */
  .bg-total  { background-color:#FFF9C4 !important; }  /* เหลือง */
  .bg-main   { background-color:#f1f8e9 !important; }
  .fw-strong { font-weight:600; }
</style>
@endpush
@endsection