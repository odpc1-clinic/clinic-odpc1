@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">


            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Visit</div>
                    <div class="card-body">

                        {{-- @include ('status') --}}

                        <!-- <a href="{{ route('visit.create') }}" class="btn btn-success btn-sm" title="Add New Visit">
                                <i class="fa fa-plus" aria-hidden="true"></i> Add New
                            </a> -->

                        <form method="GET" action="{{ route('visit.appointment') }}" accept-charset="UTF-8"
                            class="form-inline my-2 my-lg-0 float-end" role="search" autocomplete="off">
                            <div class="input-group">
                                <input type="text" class="form-control date" name="search" placeholder="Search..."
                                    value="{{ request('search') }}">
                                <span class="input-group-append">
                                    <button class="btn btn-secondary" type="submit">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </span>
                            </div>
                        </form>

                        <br />
                        <br />
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>NH</th>
                                        <th>ชื่อ</th>
                                        <th>นามสกุล</th>
                                        <th>เพศ</th>
                                        <th>วันนัด</th>
                                        <th class="col-4">เหตุผล</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($visit as $item)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $item->patient()->code }}</td>
                                            <td>{{ $item->patient()->name }}</td>
                                            <td>{{ $item->patient()->surname }}</td>
                                            <td>{{ App\Models\Patient::$sexOption[$item->patient()->sex] }}</td>
                                            
                                            <td>{{ $item->appointment }}</td>
                                            <td>{{ $item->appointment_reason}}</td>
                                            <td>
                                                {{-- <a href="{{ url('/visit/' . $item->id) }}" title="View Visit"><button class="btn btn-info btn-sm"><i class="fa fa-eye" aria-hidden="true"></i> </button></a> --}}
                                                <a href="{{ $item->roomLink() }}"><button class="btn btn-primary btn-sm"><i
                                                            class="fa fa-pencil" aria-hidden="true"></i> </button></a>

                                                {{-- <form method="POST" action="{{ url('/visit' . '/' . $item->id) }}" accept-charset="UTF-8" style="display:inline">
                                                {{ method_field('DELETE') }}
                                                {{ csrf_field() }}
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete Visit" onclick="return confirm(&quot;Confirm delete?&quot;)"><i class="fa fa-trash" aria-hidden="true"></i> </button>
                                            </form> --}}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="pagination-wrapper"> {!! $visit->appends(['search' => Request::get('search')])->render() !!} </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="/js/bootstrap-datepicker.js"></script>
    <script src="/js/bootstrap-datepicker-thai.js"></script>
    <script src="/js/bootstrap-datepicker.th.js"></script>

    <script type="text/javascript">
        $(function() {

            $('.date').datepicker({
                language: 'th-th',
            });

        });
    </script>
@endsection

