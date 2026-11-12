<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Report</title>
    <style>
        /* ปรับแต่ง CSS สำหรับการพิมพ์ */
        body { font-family: 'Sarabun', sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
    </style>
</head>
<body>
    <h1>Lab Report</h1>
    @foreach ($labs as $lab)
    <table>
        <tr>
            <th>Method</th>
            <td>{{ $lab->method() }}</td>
        </tr>
        <tr>
            <th>Specimen From</th>
            <td>{{ $lab->specimenFrom() }}</td>
        </tr>
        <tr>
            <th>Result</th>
            <td>{{ $lab->result }}</td>
        </tr>
    </table>
    @endforeach
</body>
</html>
