<table>
    <thead>
        <tr>
            <th>#</th>
            <th>HN</th>
            <th>LN</th>
            <th>ชื่อ</th>
            <th>นามสกุล</th>
            <th>Collected Date</th>
            <th>สถานะ</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($labs as $index => $lab)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $lab->patient->code }}</td>
                <td>{{ $lab->LN }}</td>
                <td>{{ $lab->patient->name }}</td>
                <td>{{ $lab->patient->surname }}</td>
                <td>{{ $lab->collected_date }}</td>
                <td>{{ \App\Models\Lab::$statusOption[$lab->status] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
