<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
            <th>บ้านเลขที่</th><th>หมู่</th><th>ตำบล</th><th>อำเภอ</th><th>จังหวัด</th><th>รหัสไปรษณีย์</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>

            <tr>
               
                <td>{{ $address->house_no }}</td><td>{{ $address->address }}</td><td>{{ $address->sub_district() }}</td><td>{{ $address->district() }}</td><td>{{ $address->province() }}</td><td>{{ $address->zipcode }}</td>

                <td>
                    <!-- <a href="{{ url('/backend/address/' . $address->id) }}" title="View Address"><button class="btn btn-info btn-sm"><i class="fa fa-eye" aria-hidden="true"></i> View</button></a> -->
                    <a href="{{ route('address.edit',['address' => $address->id]) }}" title="Edit Address" class="btn btn-primary btn-sm"><i class="fa fa-pencil" aria-hidden="true"></i></a>

                    <form method="POST" action="{{  route('address.destroy',['address' => $address->id])  }}" accept-charset="UTF-8" style="display:inline">
                        {{ method_field('DELETE') }}
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-danger btn-sm" title="Delete Address" onclick="return confirm(&quot;Confirm delete?&quot;)"><i class="fa fa-trash" aria-hidden="true"></i> </button>
                    </form>
                </td>
            </tr>

        </tbody>
    </table>

</div>