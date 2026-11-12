<link rel="stylesheet" type="text/css" href="/css/select2.min.css">

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>

                <th>ชื่อยา</th>
                <th>Stock คงเหลือ</th>
                <th>dose</th>
                <th>route</th>
                <th>จำนวน</th>
            </tr>
        </thead>
        <tbody>
        <?php $items = App\Models\Medicine::orderBy('name')->get();
        // dd($items);
        ?>

            @for ($i = 1; $i < 11; $i++) <tr>
                <td>{{ $i }}
                </td>
                <td>

                    <select name="medicine_id[{{$i}}]" class="form-control name" rowId="{{ $i }}">
                    <option value=""></option>
                        @foreach($items as $item)
                        <option value="{{$item->id}}" dose="{{$item->dose}}" route="{{$item->route}}" amount="{{$item->amount}}" stock="{{$item->stock}}" unit="{{$item->unit}}" lowstock="{{ $item->isLowStock() ? 1 : 0 }}">{{$item->name}} (คงเหลือ {{ $item->stock }} {{ $item->unit }})</option>
                        @endforeach
                    </select>

                </td>
                <td>
                    <span class="stock-display" rowId="{{ $i }}"></span>
                </td>
                <td>
                    <input type="text" name="dose[{{$i}}]" class="form-control dose">
                </td>
                <td>
                    <input type="text" name="route[{{$i}}]" class="form-control route">
                </td>
                <td>
                    <input type="text" name="amount[{{$i}}]" class="form-control amount">
                </td>

                </tr>
                @endfor
        </tbody>
    </table>
</div>


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>
<!-- <script src="/js/select2.min.js" type="text/javascript"></script> -->
<script type="text/javascript">
    $(document).ready(function() {
        $('.name').change(function(){
            
            // console.log($(this).attr('rowId'))
            // console.log($(this).val())
            $('input[name="dose['+$(this).attr('rowId')+']"]').val($('option:selected', this).attr('dose'))
            $('input[name="route['+$(this).attr('rowId')+']"]').val($('option:selected', this).attr('route'))
            $('input[name="amount['+$(this).attr('rowId')+']"]').val($('option:selected', this).attr('amount'))

            var selected = $('option:selected', this);
            var stockSpan = $('.stock-display[rowId="'+$(this).attr('rowId')+'"]');
            if (selected.val()) {
                var stock = selected.attr('stock');
                var unit = selected.attr('unit') || '';
                var isLow = selected.attr('lowstock') === '1';
                stockSpan.text(stock + ' ' + unit).css('color', isLow ? '#dc3545' : '#28a745').css('font-weight', isLow ? 'bold' : 'normal');
            } else {
                stockSpan.text('');
            }
        });
    });
</script>