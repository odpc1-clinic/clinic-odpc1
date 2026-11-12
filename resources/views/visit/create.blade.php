@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            {{-- @include('admin.sidebar') --}}

            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Create New Visit</div>
                    <div class="card-body">
                        <a href="{{ route('patient.edit',$_GET['patient']) }}" title="Back"><button class="btn btn-warning btn-sm"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back</button></a>
                        <br />
                        <br />

                        @if ($errors->any())
                            <ul class="alert alert-danger">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <form method="POST" action="{{ url('/visit') }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data"  autocomplete="off">
                            {{ csrf_field() }}

                            <input type="hidden" name="patient_id" value="{{$_GET['patient']}}">
                            @include ('visit.form.index', ['formMode' => 'create','main'=>$_GET["main"]])

                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
