<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>LN</th>
                <th>Report By</th>
                <th>Approve By</th>
                <th>Collected Date</th>
                <th>Report Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>


            <tr>
                <td>{{ $lab->LN }}</td>
                <td>{{ App\Models\User::getName($lab->report_by) }}</td>
                <td>{{ App\Models\User::getName($lab->approve_by) }}</td>
                <td>{{ $lab->collected_date }}</td>

                <td>{{ $lab->report_date }}</td>
                <td>{{ $lab->status() }}</td>

                <td>
                    
                    {{-- <a href="{{ url('/backend/lab/' . $lab->id) }}" title="View Lab" class="btn btn-info btn-sm"><i class="fa fa-eye" aria-hidden="true"></i> View</a> --}}
                    <a href="{{ route('lab.edit', ['lab' => $lab->id, 'page' => $page]) }}" title="Edit Lab"
                        class="btn btn-primary btn-sm"><i class="fa fa-pencil" aria-hidden="true"></i> </a>

                    {{-- <form method="POST" action="{{ route('lab.destroy', ['lab' => $lab->id, 'page' => $page]) }}"
                        accept-charset="UTF-8" style="display:inline">
                        {{ method_field('DELETE') }}
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-danger btn-sm" title="Delete LabSub"
                            onclick="return confirm(&quot;Confirm delete?&quot;)"><i class="fa fa-trash"
                                aria-hidden="true"></i></button>
                    </form> --}}

                </td>
            </tr>

        </tbody>
    </table>
</div>
