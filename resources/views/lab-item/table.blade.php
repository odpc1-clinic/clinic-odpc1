<div class="table-responsive">
    <table class="table table-bordered ">
        <thead class="text-center">
            <tr>
                <th rowspan="3">Specimen From</th><th colspan="7">Tests and Result</th><th rowspan="3">Actions</th>
            </tr>
            <tr>
                <th colspan="2">Gram Stain</th><th colspan="2">Wet Preparation</th><th>KOH</th><th rowspan="2">Bacterial Culture</th><th rowspan="2">อื่่น</th>
            </tr>
            <tr>
                <th>GNDC I/E</th><th>PMN</th><th>TV</th><th>Clue cell</th><th>Yeast OF Hyphae</th>
            </tr>

        </thead>
        <tbody>
        @foreach($labitem as $item)
            <tr>
                {{-- ['lab_id', 'specimen_from', 'other_specimen', 'gram_stain', 'gncd', 'pmn', 'wet_preparation', 'tv', 'clue_cell', 
                'koh', 'yeast_hyphae', 'bacterial_ulture', 'bc_result', 'other_test', 'other_result']; --}}

                <td>{{ $item->specimenFrom() }}</td><td>{{ $item->gncd }}</td><td>{{ $item->pmn }}</td><td>{{ $item->tv }}</td><td>{{ $item->clue_cell }}</td>
                <td>{{ $item->yeast_hyphae }}</td><td>{{ $item->bc_result }}</td><td>{{ $item->other_result }}</td>
                <td>
                    {{-- <a href="{{ url('/backend/lab-item/' . $item->id) }}" title="View LabItem"><button class="btn btn-info btn-sm"><i class="fa fa-eye" aria-hidden="true"></i> View</button></a> --}}
                    <a href="{{ route('lab-item.edit',$item->id) }}" title="Edit LabItem"><button class="btn btn-primary btn-sm"><i class="fa fa-pencil" aria-hidden="true"></i></button></a>

                    <form method="POST" action="{{ route('lab-item.destroy',$item->id)}}" accept-charset="UTF-8" style="display:inline">
                        {{ method_field('DELETE') }}
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-danger btn-sm" title="Delete LabItem" onclick="return confirm(&quot;Confirm delete?&quot;)"><i class="fa fa-trash" aria-hidden="true"></i> </button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

</div>