@extends('layouts.app')

@section('content')
@php
    $thMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $th = fn ($c) => $c->day . ' ' . $thMonths[$c->month] . ' ' . ($c->year + 543);
    $range = fn ($a, $b) => $a->month === $b->month ? $a->day . '–' . $th($b) : $th($a) . ' – ' . $th($b);
    $items = \App\Support\SeasonFx::ITEMS;
@endphp
<style>
    #sfx-page { font-size: 1.02rem; max-width: 960px; }
    #sfx-page .sfx-ico { font-size: 1.35rem; width: 2rem; display: inline-block; text-align: center; }
    #sfx-page .form-switch .form-check-input { width: 2.6em; height: 1.35em; }
    #sfx-page table td, #sfx-page table th { vertical-align: middle; }
    #sfx-page .muted { color: #6c757d; font-size: .9rem; }
</style>

<div class="container py-3" id="sfx-page">
    <h4 class="mb-1"><i class="fa-solid fa-wand-magic-sparkles me-2"></i>ตั้งค่าเอฟเฟกต์เทศกาล / ฤดู</h4>
    <p class="muted mb-3">ไอคอนข้างชื่อระบบบนแถบเมนู + ของลอยจาง ๆ ช่วงใกล้วันเทศกาล — มีผลกับทุกเครื่อง (แต่ละเครื่องกดที่ไอคอนเพื่อปิดของลอยของตัวเองได้)</p>

    @if($errors->any())
        <div class="alert alert-danger py-2">
            <b>ยังไม่ได้บันทึก:</b>
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="alert alert-light border py-2 d-flex align-items-center gap-2">
        <span class="sfx-ico">{{ $today['icon'] ?? '—' }}</span>
        <div>
            <b>วันนี้แสดง:</b>
            @if($today)
                {{ $today['label'] }} {{ $today['fx'] ? '· มีของลอย' : '· ไอคอนอย่างเดียว' }}
            @else
                ไม่แสดงอะไร (ปิดอยู่)
            @endif
        </div>
    </div>

    <form method="POST" action="{{ route('season-fx.save') }}">
        @csrf
        <div class="card mb-3">
            <div class="card-body">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="sfx-enabled" name="enabled" value="1" {{ old('enabled', $s['enabled']) ? 'checked' : '' }}>
                    <label class="form-check-label fw-bold ms-1" for="sfx-enabled">เปิดใช้เอฟเฟกต์ทั้งระบบ</label>
                    <div class="muted">ปิด = ไม่มีไอคอนและของลอยเลยทุกเครื่อง</div>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="sfx-particles" name="particles" value="1" {{ old('particles', $s['particles']) ? 'checked' : '' }}>
                    <label class="form-check-label fw-bold ms-1" for="sfx-particles">มีของลอย</label>
                    <div class="muted">ปิด = มีแต่ไอคอน ไม่มีอะไรขยับบนหน้าจอเลย (ทั้งเทศกาลและฤดู)</div>
                </div>
                <div class="form-check form-switch mt-2 ms-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="sfx-season-p" name="season_particles" value="1" {{ old('season_particles', $s['season_particles']) ? 'checked' : '' }}>
                    <label class="form-check-label fw-bold ms-1" for="sfx-season-p">ของลอยตามฤดูด้วย (หิมะ · สายฝน · ดอกคูน)</label>
                    <div class="muted">ฤดูขึ้นได้ทั้งปี ถ้ารู้สึกว่าถี่ไป ปิดอันนี้ — เทศกาลยังมีของลอยตามปกติ</div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><b>เลือกเทศกาล / ฤดูที่จะแสดง</b></div>
            <table class="table table-sm mb-0">
                <thead><tr><th style="width:3rem">แสดง</th><th>เทศกาล / ฤดู</th><th>ของลอย</th><th class="text-end">ดูตัวอย่าง</th></tr></thead>
                <tbody>
                @foreach($items as $key => [$label, $icon, $fx, $fxText])
                    <tr>
                        <td class="text-center">
                            <input class="form-check-input" type="checkbox" name="items[{{ $key }}]" value="1" id="sfx-it-{{ $key }}"
                                   {{ ! in_array($key, $s['disabled'], true) ? 'checked' : '' }}>
                        </td>
                        <td><label for="sfx-it-{{ $key }}"><span class="sfx-ico">{{ $icon }}</span>{{ $label }}</label></td>
                        <td class="muted">{{ $fxText ?? 'ไม่มี (ไอคอนอย่างเดียว)' }}</td>
                        <td class="text-end">
                            @if($fx)
                                <a href="{{ url('/patient') }}?sfx={{ $key }}" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="fa fa-eye me-1"></i>ลองดู</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="card-footer bg-white muted">
                เทศกาลที่ปิดไว้ ช่วงนั้นจะแสดงของฤดูแทน · ปิดฤดู = ช่วงนั้นไม่มีไอคอน · ปุ่ม "ลองดู" เปิดหน้าใหม่ให้เห็นของลอยทันที แม้ยังไม่ถึงวัน
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-white"><b>🪔 วันลอยกระทง</b> <span class="muted">(ขึ้น 15 ค่ำ เดือน 12)</span></div>
                    <div class="card-body">
                        <textarea name="loy" rows="6" class="form-control" placeholder="24/11/2569">{{ old('loy', $loyText) }}</textarea>
                        <div class="muted mt-1">บรรทัดละ 1 ปี แบบ วว/ดด/ปปปป (พ.ศ.) · ไอคอนขึ้นก่อน 7 วัน · โคมลอยขึ้นก่อน 3 วันถึงวันถัดไป</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-white"><b>🧧 วันตรุษจีน</b> <span class="muted">(วันชิวอิด)</span></div>
                    <div class="card-body">
                        <textarea name="cny" rows="6" class="form-control" placeholder="06/02/2570">{{ old('cny', $cnyText) }}</textarea>
                        <div class="muted mt-1">บรรทัดละ 1 ปี แบบ วว/ดด/ปปปป (พ.ศ.) · ปีที่ไม่ได้ใส่ เทศกาลนั้นจะไม่ขึ้น</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><b>ของลอย</b></div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="sfx-op">ความเข้ม: <span id="sfx-op-v">{{ old('opacity', $s['opacity']) }}</span>%</label>
                    <input type="range" class="form-range" id="sfx-op" name="opacity" min="{{ \App\Support\SeasonFx::NUMS['opacity'][1] }}" max="{{ \App\Support\SeasonFx::NUMS['opacity'][2] }}" step="5"
                           value="{{ old('opacity', $s['opacity']) }}" oninput="document.getElementById('sfx-op-v').textContent=this.value">
                    <div class="muted">น้อย = จางมาก</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="sfx-sec">ลอยนาน (วินาที)</label>
                    <input type="number" class="form-control" id="sfx-sec" name="seconds" min="{{ \App\Support\SeasonFx::NUMS['seconds'][1] }}" max="{{ \App\Support\SeasonFx::NUMS['seconds'][2] }}" value="{{ old('seconds', $s['seconds']) }}">
                    <div class="muted">3–30 วินาที แล้วหายเอง</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold" for="sfx-gap">ขึ้นซ้ำได้ทุก (นาที)</label>
                    <input type="number" class="form-control" id="sfx-gap" name="gap" min="{{ \App\Support\SeasonFx::NUMS['gap'][1] }}" max="{{ \App\Support\SeasonFx::NUMS['gap'][2] }}" value="{{ old('gap', $s['gap']) }}">
                    <div class="muted">ต่อเครื่อง · 0 = ขึ้นทุกครั้งที่เปลี่ยนหน้า</div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mb-4">
            <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> บันทึก</button>
            <a href="{{ route('season-fx.index') }}" class="btn btn-outline-secondary">ยกเลิก</a>
        </div>
    </form>

    <div class="card">
        <div class="card-header bg-white"><b>ช่วงที่จะแสดงเร็ว ๆ นี้</b> <span class="muted">(ตามค่าที่บันทึกไว้)</span></div>
        <table class="table table-sm mb-0">
            <thead><tr><th>เทศกาล</th><th>วันจริง</th><th>ไอคอนขึ้น</th><th>ของลอย</th><th></th></tr></thead>
            <tbody>
            @forelse($upcoming as [$key, $deco, $float, $day])
                <tr>
                    <td><span class="sfx-ico">{{ $items[$key][1] }}</span>{{ $items[$key][0] }}</td>
                    <td>{{ $th($day) }}</td>
                    <td>{{ $range($deco[0], $deco[1]) }}</td>
                    <td>{{ $s['particles'] ? $range($float[0], $float[1]) : 'ปิดอยู่' }}</td>
                    <td>@if(in_array($key, $s['disabled'], true))<span class="badge bg-secondary">ปิดไว้</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center muted py-2">ไม่มี</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
