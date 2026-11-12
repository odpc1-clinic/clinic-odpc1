@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h1 class="fw-bold text-primary mb-4">รายงานภาพรวม (แดชบอร์ด)</h1>

    <form method="GET" action="{{ route('report.dashboard') }}" class="mb-4">
        <div class="row g-2 align-items-center">
            <div class="col-auto"><label for="month" class="form-label fw-semibold">เดือน</label></div>
            <div class="col-auto">
                <select name="month" id="month" class="form-select shadow-sm">
                    <option value="">ทั้งหมด</option>
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @if($m == $month) selected @endif>{{ $m }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto"><label for="year" class="form-label fw-semibold">ปี (พ.ศ.)</label></div>
            <div class="col-auto">
                <select name="year" id="year" class="form-select shadow-sm">
                    @php
                        $startYear = 2560;
                        $currentYear = date('Y') + 543;
                        $selectedYear = $year < 2500 ? $year + 543 : $year;
                    @endphp
                    @for ($y = $startYear; $y <= $currentYear; $y++)
                        <option value="{{ $y }}" @if($selectedYear == $y) selected @endif>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary shadow-sm">ดูรายงาน</button>
            </div>
        </div>
    </form>

    @if ($reportData)
        {{-- Summary Cards --}}
        <div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
            <div class="col">
                <div class="card text-white bg-primary text-center">
                    <div class="card-body small">
                        <h2 class="display-4 fw-bold" style="color:#fff;text-shadow:2px 2px 6px #000;">{{ $reportData->total_patients }}</h2>
                        <div>จำนวนผู้รับบริการ</div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-white bg-success text-center">
                    <div class="card-body small">
                        <h2 class="display-4 fw-bold" style="color:#fff;text-shadow:2px 2px 6px #000;">{{ $reportData->total_visits }}</h2>
                        <div>จำนวนครั้งที่รับบริการ</div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-white bg-info text-center">
                    <div class="card-body small">
                        <h2 class="display-4 fw-bold" style="color:#fff;text-shadow:2px 2px 6px #000;">{{ $reportData->visit_male }}</h2>
                        <div>ชาย</div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card text-white bg-warning text-center">
                    <div class="card-body small">
                        <h2 class="display-4 fw-bold" style="color:#fff;text-shadow:2px 2px 6px #000;">{{ $reportData->visit_female }}</h2>
                        <div>หญิง</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bar Charts --}}
        <div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
            <div class="col">
                <div class="card flex-fill h-100">
                    <div class="card-body">
                        <h6 class="fw-bold text-muted small">ผู้ป่วยแยกตามโรคและเพศ</h6>
                        <div style="height:240px"><canvas id="diseaseBySexBar"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card flex-fill h-100">
                    <div class="card-body">
                        <h6 class="fw-bold text-muted small">ผู้ป่วยแยกตามช่วงอายุและเพศ</h6>
                        <div style="height:240px"><canvas id="ageBar"></canvas></div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card flex-fill h-100">
                    <div class="card-body">
                        <h6 class="fw-bold text-muted small">จำนวนผู้ป่วยใหม่ / ซ้ำ (3 โรคหลัก)</h6>
                        <div style="height:240px"><canvas id="diseaseRepeatBar"></canvas></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pie Charts --}}
        <div class="row row-cols-1 row-cols-md-2 g-4 mb-4">
            <div class="col">
                <div class="card shadow-sm h-100 text-center p-3">
                    <h6 class="fw-bold text-muted small">สัดส่วนเพศ</h6>
                    <div style="height:180px"><canvas id="genderPie"></canvas></div>
                </div>
            </div>
            <div class="col">
                <div class="card shadow-sm h-100 text-center p-3">
                    <h6 class="fw-bold text-muted small">สัดส่วนสัญชาติ</h6>
                    <div style="height:180px"><canvas id="nationalityPie"></canvas></div>
                </div>
            </div>
        </div>

        {{-- Summary Table --}}
        <div class="table-responsive mb-4">
            <style>
    .bg-male {
        background-color: #B3E5FC !important;  /* ฟ้าอ่อน */
    }
    .bg-female {
        background-color: #F8BBD0 !important;  /* ชมพูอ่อน */
    }
    .bg-total {
        background-color: #FFF9C4 !important;  /* เหลืองอ่อน */
    }

    table tr td, table tr th {
        text-align: center;
        vertical-align: middle;
    }

    table tr th {
        background-color: #f1f1f1;
    }
</style>

    <table class="table table-bordered text-center align-middle">
    <thead class="table-light">
        <tr>
            <th>รายการ</th>
            <th class="bg-male text-black">ชาย</th>
            <th class="bg-female text-black">หญิง</th>
            <th class="bg-total">รวม</th>
        </tr>
    </thead>
    <tbody>
        @php
            $mainDiseases = [
                'syphilis' => 'ซิฟิลิส',
                'gonorrhea' => 'หนองใน',
                'chlamydia' => 'หนองในเทียม'
            ];
        @endphp

        @foreach($mainDiseases as $key => $label)
            <tr class="table-secondary fw-bold">
    <td colspan="4" class="text-start">{{ $label }}</td>
</tr>


            {{-- ผู้ป่วยใหม่ --}}
            <tr>
                <td class="text-start">ผู้ป่วยใหม่</td>
                <td class="bg-male text-black">{{ $reportData->{$key . '_new_male'} ?? 0 }}</td>
                <td class="bg-female text-black">{{ $reportData->{$key . '_new_female'} ?? 0 }}</td>
                <td class="bg-total fw-bold">
                    {{ ($reportData->{$key . '_new_male'} ?? 0) + ($reportData->{$key . '_new_female'} ?? 0) }}
                </td>
            </tr>

            {{-- ผู้ป่วยเกิดโรคซ้ำ --}}
            <tr>
                <td class="text-start">เกิดโรคซ้ำ</td>
                <td class="bg-male text-black">{{ $reportData->{$key . '_repeat_male'} ?? 0 }}</td>
                <td class="bg-female text-black">{{ $reportData->{$key . '_repeat_female'} ?? 0 }}</td>
                <td class="bg-total fw-bold">
                    {{ ($reportData->{$key . '_repeat_male'} ?? 0) + ($reportData->{$key . '_repeat_female'} ?? 0) }}
                </td>
            </tr>

            {{-- รวม (ไม่สนว่าใหม่หรือซ้ำ) --}}
            <tr>
                <td class="text-start">รวมทั้งหมด</td>
                <td class="bg-male text-black">{{ $reportData->{$key . '_male'} ?? 0 }}</td>
                <td class="bg-female text-black">{{ $reportData->{$key . '_female'} ?? 0 }}</td>
                <td class="bg-total fw-bold">
                    {{ ($reportData->{$key . '_male'} ?? 0) + ($reportData->{$key . '_female'} ?? 0) }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="table table-bordered text-center align-middle">
    <thead class="table-light">
        <tr>
            <th>รายการ</th>
            <th class="bg-male text-black">ชาย</th>
            <th class="bg-female text-black">หญิง</th>
            <th class="bg-total">รวม</th>
        </tr>
    </thead>
    <tbody>
        @php
            $diseaseList = [
                'syphilis' => 'ซิฟิลิส',
                'gonorrhea' => 'หนองใน',
                'chlamydia' => 'หนองในเทียม',
                'lgv' => 'LGV',
                'chancroid' => 'Chancroid',
                'bacterial_vaginosis' => 'Bacterial vaginosis',
                'vaginal_candidiasis' => 'Vaginal candidiasis',
                'trichomoniasis' => 'Trichomoniasis',
                'molluscum_contagiosum' => 'Molluscum contagiosum',
                'anogenital_warts' => 'Anogenital warts',
                'herpes_simplex' => 'Herpes simplex',
                'other' => 'Other'
            ];
        @endphp

        @foreach($diseaseList as $key => $label)
            <tr>
                <td class="text-start">{{ $label }}</td>
                <td class="bg-male text-black">{{ $reportData->{$key . '_male'} ?? 0 }}</td>
                <td class="bg-female text-black">{{ $reportData->{$key . '_female'} ?? 0 }}</td>
                <td class="bg-total fw-bold">
                    {{ ($reportData->{$key . '_male'} ?? 0) + ($reportData->{$key . '_female'} ?? 0) }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

        </div>
    @else
        <div class="alert alert-warning">ไม่พบข้อมูลในช่วงเวลาที่เลือก</div>
    @endif

    @if (!empty($activityRows))
    <h5 class="fw-bold mt-4">รายงานกิจกรรมแยกตามกลุ่มเสี่ยง</h5>
    <div class="table-responsive">
        <table class="table table-bordered small text-center">
            <thead class="table-secondary align-middle">
                <tr>
                    <th rowspan="2">กิจกรรม</th>
                    <th colspan="3">รวม</th>
                    <th colspan="7">กลุ่มเสี่ยง</th>
                </tr>
                <tr>
                    <th>ชาย</th><th>หญิง</th><th>รวม</th>
                    <th>FSW</th><th>MSW</th><th>MSM</th><th>แรงงาน<br>ข้ามชาติ</th><th>เรือนจำ</th><th>เยาวชน</th><th>อื่น ๆ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($activityRows as $row)
                    <tr>
                        <td class="text-start">{{ $row['activity'] }}</td>
                        <td>{{ $row['male'] }}</td>
                        <td>{{ $row['female'] }}</td>
                        <td>{{ $row['total'] }}</td>
                        <td>{{ $row['fsw'] }}</td>
                        <td>{{ $row['msw'] }}</td>
                        <td>{{ $row['msm'] }}</td>
                        <td>{{ $row['labor'] }}</td>
                        <td>{{ $row['prisoner'] }}</td>
                        <td>{{ $row['young'] }}</td>
                        <td>{{ $row['other'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
</div>


@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
<script>
    function createBarChart(canvasId, labels, datasets) {
    const ctx = document.getElementById(canvasId)?.getContext('2d');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: datasets.map(ds => ({
                ...ds,
                barThickness: 14,
                borderRadius: 3
            }))
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        font: { size: 11 }
                    }
                },
                tooltip: {
                    bodyFont: { size: 11 },
                    titleFont: { size: 12 }
                },
                datalabels: {
                    color: '#333',
                    anchor: 'end',
                    align: 'top',
                    font: {
                        weight: 'bold',
                        size: 10
                    },
                    formatter: Math.round
                }
            },
            scales: {
                x: {
                    ticks: { font: { size: 10 } }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: { size: 10 },
                        stepSize: 1
                    }
                }
            }
        },
        plugins: [ChartDataLabels]
    });
}

function createBar(ctx, labels, a, b) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'ชาย',
                    data: a,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)', // ฟ้าใส
                    borderRadius: 4,
                    barThickness: 14
                },
                {
                    label: 'หญิง',
                    data: b,
                    backgroundColor: 'rgba(255, 99, 132, 0.7)', // ชมพูใส
                    borderRadius: 4,
                    barThickness: 14
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { size: 11 } }
                },
                datalabels: {
                    anchor: 'end',
                    align: 'top',
                    font: { size: 10, weight: 'bold' },
                    color: '#333',
                    formatter: Math.round
                }
            },
            scales: {
                x: { ticks: { font: { size: 11 } } },
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: { size: 11 },
                        stepSize: 1,
                        precision: 0
                    }
                }
            }
        },
        plugins: [ChartDataLabels]
    });
}


document.addEventListener('DOMContentLoaded', () => {
    createBar(
        document.getElementById('diseaseBySexBar'),
        ['Syphilis','Gonorrhea','Non-Gonococcal urethritis','LGV','Chancroid','Bacterial vaginosis','Vaginal candidiasis','Trichomoniasis','Molluseum contagiosum','Anogenital warts','Herpes simplex'],
        [
            {{ $reportData->syphilis_male ?? 0 }},
            {{ $reportData->gonorrhea_male ?? 0 }},
            {{ $reportData->chlamydia_male ?? 0 }},
            {{ $reportData->lgv_male ?? 0 }},
            {{ $reportData->chancroid_male ?? 0 }},
            {{ $reportData->bacterial_vaginosis_male ?? 0 }},
            {{ $reportData->vaginal_candidiasis_male ?? 0 }},
            {{ $reportData->trichomoniasis_male ?? 0 }},
            {{ $reportData->molluscum_contagiosum_male ?? 0 }},
            {{ $reportData->anogenital_warts_male ?? 0 }},
            {{ $reportData->herpes_simplex_male ?? 0 }}
        ],
        [
            {{ $reportData->syphilis_female ?? 0 }},
            {{ $reportData->gonorrhea_female ?? 0 }},
            {{ $reportData->chlamydia_female ?? 0 }},
            {{ $reportData->lgv_female ?? 0 }},
            {{ $reportData->chancroid_female ?? 0 }},
            {{ $reportData->bacterial_vaginosis_female ?? 0 }},
            {{ $reportData->vaginal_candidiasis_female ?? 0 }},
            {{ $reportData->trichomoniasis_female ?? 0 }},
            {{ $reportData->molluscum_contagiosum_female ?? 0 }},
            {{ $reportData->anogenital_warts_female ?? 0 }},
            {{ $reportData->herpes_simplex_female ?? 0 }}
        ]
    );
    createBarChart(
    'ageBar',
    ['0-14', '15-24', '25-34', '35-44', '45-54', '55-64', '65-74', '75+'],
    [
        {
            label: 'ชาย',
            data: [
                {{ $reportData->age_0_14_male ?? 0 }},
                {{ $reportData->age_15_24_male ?? 0 }},
                {{ $reportData->age_25_34_male ?? 0 }},
                {{ $reportData->age_35_44_male ?? 0 }},
                {{ $reportData->age_45_54_male ?? 0 }},
                {{ $reportData->age_55_64_male ?? 0 }},
                {{ $reportData->age_65_74_male ?? 0 }},
                {{ $reportData->age_75_up_male ?? 0 }}
            ],
            backgroundColor: 'rgba(54, 162, 235, 0.7)'
        },
        {
            label: 'หญิง',
            data: [
                {{ $reportData->age_0_14_female ?? 0 }},
                {{ $reportData->age_15_24_female ?? 0 }},
                {{ $reportData->age_25_34_female ?? 0 }},
                {{ $reportData->age_35_44_female ?? 0 }},
                {{ $reportData->age_45_54_female ?? 0 }},
                {{ $reportData->age_55_64_female ?? 0 }},
                {{ $reportData->age_65_74_female ?? 0 }},
                {{ $reportData->age_75_up_female ?? 0 }}
            ],
            backgroundColor: 'rgba(255, 99, 132, 0.7)'
        }
    ]
);

    createBarChart(
    'diseaseRepeatBar',
    ['Syphilis', 'Gonorrhea', 'Chlamydia'],
    [
        {
            label: 'ใหม่ - ชาย',
            data: [{{ $reportData->syphilis_new_male ?? 0 }}, {{ $reportData->gonorrhea_new_male ?? 0 }}, {{ $reportData->chlamydia_new_male ?? 0 }}],
            backgroundColor: 'rgba(54, 162, 235, 0.7)'
        },
        {
            label: 'ใหม่ - หญิง',
            data: [{{ $reportData->syphilis_new_female ?? 0 }}, {{ $reportData->gonorrhea_new_female ?? 0 }}, {{ $reportData->chlamydia_new_female ?? 0 }}],
            backgroundColor: 'rgba(255, 99, 132, 0.7)'
        },
        {
            label: 'ซ้ำ - ชาย',
            data: [{{ $reportData->syphilis_repeat_male ?? 0 }}, {{ $reportData->gonorrhea_repeat_male ?? 0 }}, {{ $reportData->chlamydia_repeat_male ?? 0 }}],
            backgroundColor: 'rgba(255, 206, 86, 0.7)'
        },
        {
            label: 'ซ้ำ - หญิง',
            data: [{{ $reportData->syphilis_repeat_female ?? 0 }}, {{ $reportData->gonorrhea_repeat_female ?? 0 }}, {{ $reportData->chlamydia_repeat_female ?? 0 }}],
            backgroundColor: 'rgba(153, 102, 255, 0.7)'
        }
    ]
);

   function pie(el, labels, data, colors) {
    new Chart(el, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: colors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        font: { size: 11 }
                    }
                },
                datalabels: {
                    color: '#fff',
                    font: {
                        size: 10,
                        weight: 'bold'
                    },
                    formatter: (value, context) => {
                        const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                        const percentage = total ? (value / total * 100).toFixed(1) : 0;
                        return `${value} (${percentage}%)`;
                    }
                }
            }
        },
        plugins: [ChartDataLabels]
    });
}

    pie(
  document.getElementById('genderPie'),
  ['ชาย', 'หญิง'],
  [{{ $reportData->visit_male }}, {{ $reportData->visit_female }}],
  ['rgba(54, 162, 235, 0.7)', 'rgba(255, 99, 132, 0.7)']
);

pie(
  document.getElementById('nationalityPie'),
  ['ไทย', 'พม่า', 'อื่นๆ'],
  [{{ $reportData->thai }}, {{ $reportData->burmese }}, {{ $reportData->other_nationality }}],
  ['rgba(0, 200, 81, 0.7)', 'rgba(255, 187, 51, 0.7)', 'rgba(136, 136, 136, 0.7)']
);

});
</script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
@endpush