@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            {{-- @include('admin.sidebar') --}}

            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Create New LabItem</div>
                    <div class="card-body">
                        <a href="{{ route('visit.edit',$_GET['visit']) }}" title="Back"><button class="btn btn-warning btn-sm"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back</button></a>
                        <br />
                        <br />

                        @if ($errors->any())
                            <ul class="alert alert-danger">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif
                            <?php $visit = App\Models\Visit::find($_GET['visit'])?>
                        <form method="POST" action="{{  route('lab-item.store') }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data">
                            {{ csrf_field() }}
                            <input type="hidden" name="lab_id" value="{{$visit->lab()->id}}">
                            @include ('lab-item.form', ['formMode' => 'create'])

                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
