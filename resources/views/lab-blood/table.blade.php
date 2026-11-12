<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
            <!-- 'report_by', 'approve_by', 'report_date', 'hiv', 'syphilis', 'rpr', 'pcr_specimen', 'pcr_result' -->
                <!-- <th>#</th> -->
                <th>report_by</th>     
                 <th>approve_by</th>      
                 <th>report_date</th>


                <th>hiv</th>
                <th>syphilis</th>
                <th>rpr</th>
                <th>pcr_specimen</th>
               
                <th>pcr_result</th>
                 @if($edit)
                 <th>Action</th>
                @endif
            </tr>
        </thead>
        <tbody>

            <tr>
                <td>{{ $labBlood->report_by() }}</td>
                <td>{{ $labBlood->approve_by() }}</td>
                <td>{{ $labBlood->report_date }}</td>

                <td>{{ $labBlood->hiv() }}</td>
                <td>{{ $labBlood->syphilis() }}</td>
                <td>{{ $labBlood->rpr }}</td>
                <td>{{ $labBlood->pcr_specimen }}</td>
                <td>{{ $labBlood->pcr_result }}</td>

                @if($edit)
                <td>
                    <!-- <a href="{{ url('/backend/lab-blood/' . $labBlood->id) }}" title="View LabBlood"><button class="btn btn-info btn-sm"><i class="fa fa-eye" aria-hidden="true"></i> </button></a> -->
                    <a href="{{ route('lab-blood.edit' , $labBlood->id ) }}" title="Edit LabBlood"><button class="btn btn-primary btn-sm"><i class="fa fa-pencil" aria-hidden="true"></i> </button></a>

                    <form method="POST" action="{{ route('lab-blood.destroy' , $labBlood->id) }}" accept-charset="UTF-8" style="display:inline">
                        {{ method_field('DELETE') }}
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-danger btn-sm" title="Delete LabBlood" onclick="return confirm(&quot;Confirm delete?&quot;)"><i class="fa fa-trash" aria-hidden="true"></i> </button>
                    </form>
                </td>
                @endif
            </tr>

        </tbody>
    </table>
</div>