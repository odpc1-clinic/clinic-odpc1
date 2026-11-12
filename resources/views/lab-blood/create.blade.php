@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <!-- @include('admin.sidebar') -->

            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Create New LabBlood</div>
                    <div class="card-body">
                        <a href="{{ route('lab.edit',$_GET['lab']) }}" title="Back"><button class="btn btn-warning btn-sm"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back</button></a>
                        <br />
                        <br />

                        @if ($errors->any())
                            <ul class="alert alert-danger">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <form method="POST" action="{{ route('lab-blood.store') }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data">
                            {{ csrf_field() }}
                            <?php
                            $lab = App\Models\Lab::find($_GET['lab']);
                            ?>
                            <input type="hidden" name="visit_id" value="{{$lab->visit_id}}">
                            <input type="hidden" name="patient_id" value="{{$lab->patient_id}}">

                            @include ('lab-blood.form', ['formMode' => 'create'])

                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
