@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <!-- @include('admin.sidebar') -->

            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Edit LabBlood #{{ $labblood->id }}</div>
                    <div class="card-body">
                        <a href="{{ route('lab.edit',$labblood->visit()->lab()->id) }}" title="Back"><button class="btn btn-warning btn-sm"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back</button></a>
                        <br />
                        <br />

                        @if ($errors->any())
                            <ul class="alert alert-danger">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <form method="POST" action="{{ route('lab-blood.update' , $labblood->id) }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data">
                            {{ method_field('PATCH') }}
                            {{ csrf_field() }}

                            @include ('lab-blood.form', ['formMode' => 'edit',])

                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
