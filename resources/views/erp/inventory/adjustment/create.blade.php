@extends('adminlte::page')

@section('title', 'Create Stock Adjustment')

@section('content_header')
    <h1>Create Stock Adjustment</h1>
@stop

@section('content')

    @if($errors->any())

        <div class="alert alert-danger">

            <ul class="mb-0">

                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach

            </ul>

        </div>

    @endif

    <form
        method="POST"
        action="{{ route('erp.inventory.adjustment.store') }}"
    >
        @csrf

        <div class="card card-primary">

            <div class="card-header">
                <h3 class="card-title">
                    Adjustment Header
                </h3>
            </div>

            <div class="card-body">

                <div class="row">

                    <div class="col-md-4">

                        <div class="form-group">

                            <label>Warehouse</label>

                            <select
                                name="warehouse_id"
                                class="form-control"
                                required
                            >

                                <option value="">
                                    -- Pilih Warehouse --
                                </option>

                                @foreach($warehouses as $warehouse)

                                    <option
                                        value="{{ $warehouse->id }}"
                                        @selected(
                                            old('warehouse_id')
                                            == $warehouse->id
                                        )
                                    >
                                        {{ $warehouse->code }}
                                        -
                                        {{ $warehouse->name }}
                                    </option>

                                @endforeach

                            </select>

                        </div>

                    </div>

                    <div class="col-md-4">

                        <div class="form-group">

                            <label>Adjustment Date</label>

                            <input
                                type="date"
                                name="adjustment_date"
                                class="form-control"
                                value="{{ old(
                                    'adjustment_date',
                                    now()->format('Y-m-d')
                                ) }}"
                                required
                            >

                        </div>

                    </div>

                    <div class="col-md-4">

                        <div class="form-group">

                            <label>Reason</label>

                            <select
                                name="reason"
                                class="form-control"
                                required
                            >

                                <option value="">
                                    -- Pilih Reason --
                                </option>

                                <option
                                    value="STOCK_OPNAME"
                                    @selected(
                                        old('reason')
                                        === 'STOCK_OPNAME'
                                    )
                                >
                                    Stock Opname
                                </option>

                                <option
                                    value="DAMAGE"
                                    @selected(
                                        old('reason')
                                        === 'DAMAGE'
                                    )
                                >
                                    Damage
                                </option>

                                <option
                                    value="LOSS"
                                    @selected(
                                        old('reason')
                                        === 'LOSS'
                                    )
                                >
                                    Loss
                                </option>

                                <option
                                    value="OTHER"
                                    @selected(
                                        old('reason')
                                        === 'OTHER'
                                    )
                                >
                                    Other
                                </option>

                            </select>

                        </div>

                    </div>

                </div>

                <div class="form-group">

                    <label>Remarks</label>

                    <textarea
                        name="remarks"
                        class="form-control"
                        rows="2"
                    >{{ old('remarks') }}</textarea>

                </div>

            </div>

        </div>

        <div class="card">

            <div class="card-header">

                <h3 class="card-title">
                    Adjustment Details
                </h3>

                <div class="card-tools">

                    <button
                        type="button"
                        id="add-row"
                        class="btn btn-sm btn-success"
                    >
                        <i class="fas fa-plus"></i>
                        Add Item
                    </button>

                </div>

            </div>

            <div class="card-body table-responsive">

                <table
                    class="table table-bordered"
                    id="adjustment-detail-table"
                >

                    <thead>
                        <tr>
                            <th style="width:35%">
                                Item
                            </th>
                            <th style="width:20%">
                                Physical Qty
                            </th>
                            <th>
                                Remarks
                            </th>
                            <th style="width:80px">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody id="detail-body">

                        <tr>

                            <td>

                                <select
                                    name="details[0][item_id]"
                                    class="form-control"
                                    required
                                >

                                    <option value="">
                                        -- Pilih Item --
                                    </option>

                                    @foreach($items as $item)

                                        <option
                                            value="{{ $item->id }}"
                                        >
                                            {{ $item->code }}
                                            -
                                            {{ $item->name }}
                                        </option>

                                    @endforeach

                                </select>

                            </td>

                            <td>

                                <input
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    name="details[0][physical_qty]"
                                    class="form-control"
                                    required
                                >

                            </td>

                            <td>

                                <input
                                    type="text"
                                    name="details[0][remarks]"
                                    class="form-control"
                                >

                            </td>

                            <td>

                                <button
                                    type="button"
                                    class="btn btn-danger btn-sm remove-row"
                                >
                                    <i class="fas fa-trash"></i>
                                </button>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

            <div class="card-footer">

                <a
                    href="{{ route(
                        'erp.inventory.adjustment.index'
                    ) }}"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <i class="fas fa-save mr-1"></i>
                    Save Draft
                </button>

            </div>

        </div>

    </form>

@stop

@section('js')
    <script>
        $(function () {

            let rowIndex = 1;

            const itemOptions = @json(
                $items->map(fn ($item) => [
                    'id' => $item->id,
                    'text' => $item->code . ' - ' . $item->name,
                ])->values()
            );

            $('#add-row').on('click', function () {

                let options =
                    '<option value="">-- Pilih Item --</option>';

                itemOptions.forEach(function (item) {

                    options +=
                        '<option value="' + item.id + '">'
                        + item.text
                        + '</option>';
                });

                const row = `
                    <tr>
                        <td>
                            <select
                                name="details[${rowIndex}][item_id]"
                                class="form-control"
                                required
                            >
                                ${options}
                            </select>
                        </td>

                        <td>
                            <input
                                type="number"
                                step="0.0001"
                                min="0"
                                name="details[${rowIndex}][physical_qty]"
                                class="form-control"
                                required
                            >
                        </td>

                        <td>
                            <input
                                type="text"
                                name="details[${rowIndex}][remarks]"
                                class="form-control"
                            >
                        </td>

                        <td>
                            <button
                                type="button"
                                class="btn btn-danger btn-sm remove-row"
                            >
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;

                $('#detail-body').append(row);

                rowIndex++;
            });

            $(document).on(
                'click',
                '.remove-row',
                function () {

                    if ($('#detail-body tr').length <= 1) {
                        return;
                    }

                    $(this).closest('tr').remove();
                }
            );

        });
    </script>
@stop