@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            {{-- @include('admin.sidebar') --}}

            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Medicine for Visit {{ $visit->id }}</div>
                    <div class="card-body">
                        <a href="{{ route('visit.room',4) }}" title="Back"><button class="btn btn-warning btn-sm"><i class="fa fa-arrow-left" aria-hidden="true"></i> Back</button></a>
 
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>NH</th><th>ชื่อ</th><th>นามสกุล</th><th>Acrion</th>
                                </tr>
                            </thead>
                            <tbody>
                      
                                <tr>
                                    <td>{{ $visit->patient()->code }}</td><td>{{ $visit->patient()->name }}</td><td>{{ $visit->patient()->surname }}</td>
                                    <td >
                                        <form action="{{route('visit.change-status',4)}}" method="post" style="display: flex">
                                            {{ csrf_field() }}
                                            <input type="hidden" name="id" value="{{$visit->id}}">
                                            <div class="form-group px-1">
                                                <label class="visually-hidden" for="status">Status</label>
                                                <select class="form-select" id="status" name="status">
                                                    <option value="6">กลับบ้าน</option>
                                                    <option value="2">ห้องตรวจ</option>
                                                    <option value="3">ห้องเจาะเลือด/ฉีดยา</option>
                                                    <option value="7">ห้องตรวจ 7 (TMC)</option>
                                                    <option value="4">ห้อง Lab</option>
                                                    <option value="5">ห้องยา</option>



                                                </select>
                                            </div>

                                            <div class=" form-group">
                                                <input class="btn btn-primary" type="submit" value="Save">
                                            </div>
                                        </form>
                                    </td>

                                </tr>
                          
                            </tbody>
                        </table>


                        <?php $lab =$visit->lab() ?>

                        @if(!$lab)
                        <a href="{{ route('lab.create',['visit' => $visit->id]) }}" class="btn btn-success btn-sm" title="Add New ContactPerson">
                            <i class="fa fa-plus" aria-hidden="true"></i> Add New
                        </a>  
                        @else
                           
                        
                            @include ('lab.table', ['lab' => $visit->lab(),'page'=>4])
                            @if(false)
                                <a href="{{ route('lab-item.create',['visit' => $visit->id]) }}" class="btn btn-success btn-sm" title="Add New ContactPerson">
                                    <i class="fa fa-plus" aria-hidden="true"></i> Add New
                                </a> 
                                <br><br>
                                @include ('lab-item.table', ['labitem' => $visit->labItem()])
                                
                                <?php $labBlood =$lab->labBlood() ?>
                                @if($labBlood)
                                <h3>Blood Test</h3>
                                    @include ('lab-blood.table', ['labBlood' => $labBlood,'edit' => 0])
                                @endif
            
                            @endif
            
                            <a href="{{ route('lab-sub.add',['visit' => $visit->id,'page'=>4]) }}" class="btn btn-success btn-sm" title="Add New ContactPerson">
                                <i class="fa fa-plus" aria-hidden="true"></i> Add New
                            </a> 
            
                            <a href="{{ route('lab-sub.config',['visit' => $visit->id,'page'=>4]) }}" class="btn btn-success btn-sm" title="Add New ContactPerson">
                                <i class="fa fa-pencil" aria-hidden="true"></i> 
                            </a> 
            
                            @include ('lab-sub.table', ['labsub' => $visit->labSub(),'page'=>4])
                            @include ('partials.lab_sub_delete_js')
                        @endif
                        
                </div>
            </div>
        </div>
    </div>
@endsection
