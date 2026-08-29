@extends('adminlte::page')

@section('title', 'Create Inventory Transfer')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0">Create Inventory Transfer</h1>
        </div>

        <a
            href="{{ route('erp.inventory.transfer.index') }}"
            class="btn btn-default"
        >
            <i class="fas fa-arrow-left mr-1"></i>
            Back
        </a>
    </div>
@stop

@section('content')

    @if ($errors->any())
        <div class="alert alert-danger">
            <h5>
                <i class="icon fas fa-ban"></i>
                Validation Error
            </h5>

            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('erp.inventory.transfer.store') }}"
        id="inventory-transfer-form"
    >
        @csrf

        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-exchange-alt mr-1"></i>
                    Transfer Header
                </h3>
            </div>

            <div class="card-body">
                <div class="row">

                    <div class="col-md-4">
                        <div class="form-group">
                            <label>
                                Transfer Date
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="date"
                                name="transfer_date"
                                class="form-control @error('transfer_date') is-invalid @enderror"
                                value="{{ old('transfer_date', now()->toDateString()) }}"
                                required
                            >

                            @error('transfer_date')
                                <span class="invalid-feedback">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label>
                                Source Warehouse
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                name="source_warehouse_id"
                                id="source_warehouse_id"
                                class="form-control @error('source_warehouse_id') is-invalid @enderror"
                                required
                            >
                                <option value="">
                                    -- Select Source Warehouse --
                                </option>

                                @foreach($warehouses as $warehouse)
                                    <option
                                        value="{{ $warehouse->id }}"
                                        @selected(
                                            (string) old('source_warehouse_id')
                                            ===
                                            (string) $warehouse->id
                                        )
                                    >
                                        {{ $warehouse->code }}
                                        -
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>

                            @error('source_warehouse_id')
                                <span class="invalid-feedback">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label>
                                Destination Warehouse
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                name="destination_warehouse_id"
                                id="destination_warehouse_id"
                                class="form-control @error('destination_warehouse_id') is-invalid @enderror"
                                required
                            >
                                <option value="">
                                    -- Select Destination Warehouse --
                                </option>

                                @foreach($warehouses as $warehouse)
                                    <option
                                        value="{{ $warehouse->id }}"
                                        @selected(
                                            (string) old('destination_warehouse_id')
                                            ===
                                            (string) $warehouse->id
                                        )
                                    >
                                        {{ $warehouse->code }}
                                        -
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>

                            @error('destination_warehouse_id')
                                <span class="invalid-feedback">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>
                    </div>

                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group mb-0">
                            <label>Remarks</label>

                            <textarea
                                name="remarks"
                                rows="3"
                                class="form-control @error('remarks') is-invalid @enderror"
                                placeholder="Optional transfer remarks"
                            >{{ old('remarks') }}</textarea>

                            @error('remarks')
                                <span class="invalid-feedback">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-boxes mr-1"></i>
                    Transfer Items
                </h3>

                <div class="card-tools">
                    <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        id="add-detail-row"
                    >
                        <i class="fas fa-plus mr-1"></i>
                        Add Item
                    </button>
                </div>
            </div>

            <div class="card-body table-responsive p-0">

                <table class="table table-bordered mb-0" id="transfer-detail-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th style="min-width: 280px;">
                                Item
                            </th>
                            <th style="width: 160px;">
                                Qty
                            </th>
                            <th>
                                Remarks
                            </th>
                            <th
                                class="text-center"
                                style="width: 80px;"
                            >
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody id="transfer-detail-body"></tbody>
                </table>

            </div>

            <div class="card-footer">
                <small class="text-muted">
                    Cost transfer tidak diinput manual. Saat POST, sistem akan
                    mengambil authoritative moving-average cost dari source warehouse.
                </small>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            <a
                href="{{ route('erp.inventory.transfer.index') }}"
                class="btn btn-default"
            >
                <i class="fas fa-times mr-1"></i>
                Cancel
            </a>

            <button
                type="submit"
                class="btn btn-primary"
                id="save-draft-button"
            >
                <i class="fas fa-save mr-1"></i>
                Save Draft
            </button>
        </div>

    </form>

@stop
@php
    $transferItems = $items->map(function ($item) {
        return [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
        ];
    })->values()->all();

    $transferOldDetails = old('details', []);
@endphp

@section('js')
<script>
(function () {

    const items = @json($transferItems);

    const oldDetails = @json($transferOldDetails);

    const detailBody = document.getElementById('transfer-detail-body');

    const addButton = document.getElementById('add-detail-row');

    const form = document.getElementById('inventory-transfer-form');

    const sourceWarehouse = document.getElementById('source_warehouse_id');

    const destinationWarehouse = document.getElementById('destination_warehouse_id');

    let rowIndex = 0;
    
    function itemOptions(selectedItemId = null) {
        let html = '<option value="">-- Select Item --</option>';

        items.forEach(function (item) {
            const selected = String(selectedItemId) === String(item.id)
                    ? 'selected'
                    : '';

            const label = `${item.code ?? ''} - ${item.name ?? ''}`;

            html += `
                <option
                    value="${item.id}"
                    ${selected}
                >
                    ${escapeHtml(label)}
                </option>
            `;
        });

        return html;
    }

    function escapeHtml(value) {

        const div =
            document.createElement('div');

        div.textContent =
            value ?? '';

        return div.innerHTML;
    }

    function addRow(detail = {}) {

        const currentIndex =
            rowIndex++;

        const row =
            document.createElement('tr');

        row.classList.add('transfer-detail-row');

        row.innerHTML = `
            <td class="row-number align-middle text-center">
            </td>

            <td>
                <select
                    name="details[${currentIndex}][item_id]"
                    class="form-control detail-item"
                    required
                >
                    ${itemOptions(detail.item_id ?? null)}
                </select>
            </td>

            <td>
                <input
                    type="number"
                    name="details[${currentIndex}][qty]"
                    class="form-control detail-qty"
                    min="0.0001"
                    step="0.0001"
                    value="${escapeHtml(detail.qty ?? '')}"
                    required
                >
            </td>

            <td>
                <input
                    type="text"
                    name="details[${currentIndex}][remarks]"
                    class="form-control"
                    maxlength="500"
                    value="${escapeHtml(detail.remarks ?? '')}"
                    placeholder="Optional"
                >
            </td>

            <td class="text-center align-middle">
                <button
                    type="button"
                    class="btn btn-sm btn-danger remove-detail-row"
                    title="Remove Item"
                >
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

        detailBody.appendChild(row);

        updateRowNumbers();
    }

    function updateRowNumbers() {

        const rows =
            detailBody.querySelectorAll(
                '.transfer-detail-row'
            );

        rows.forEach(function (row, index) {

            const numberCell =
                row.querySelector(
                    '.row-number'
                );

            numberCell.textContent =
                index + 1;
        });
    }

    function validateWarehouses() {

        if (
            sourceWarehouse.value !== '' &&
            destinationWarehouse.value !== '' &&
            sourceWarehouse.value === destinationWarehouse.value
        ) {
            return false;
        }

        return true;
    }

    function validateDuplicateItems() {

        const selectedItems =
            [];

        const itemSelects =
            detailBody.querySelectorAll(
                '.detail-item'
            );

        for (const select of itemSelects) {

            if (select.value === '') {
                continue;
            }

            if (
                selectedItems.includes(
                    select.value
                )
            ) {
                return false;
            }

            selectedItems.push(
                select.value
            );
        }

        return true;
    }

    function validateRows() {

        const rows =
            detailBody.querySelectorAll(
                '.transfer-detail-row'
            );

        if (rows.length === 0) {
            return false;
        }

        for (const row of rows) {

            const item =
                row.querySelector(
                    '.detail-item'
                );

            const qty =
                row.querySelector(
                    '.detail-qty'
                );

            if (!item.value) {
                return false;
            }

            if (
                !qty.value ||
                Number(qty.value) <= 0
            ) {
                return false;
            }
        }

        return true;
    }

    addButton.addEventListener(
        'click',
        function () {
            addRow();
        }
    );

    detailBody.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest(
                    '.remove-detail-row'
                );

            if (!button) {
                return;
            }

            const row =
                button.closest(
                    '.transfer-detail-row'
                );

            row.remove();

            updateRowNumbers();
        }
    );

    form.addEventListener(
        'submit',
        function (event) {

            if (!validateWarehouses()) {

                event.preventDefault();

                alert(
                    'Source Warehouse dan Destination Warehouse tidak boleh sama.'
                );

                return;
            }

            if (!validateRows()) {

                event.preventDefault();

                alert(
                    'Minimal harus ada satu item dengan quantity lebih besar dari 0.'
                );

                return;
            }

            if (!validateDuplicateItems()) {

                event.preventDefault();

                alert(
                    'Item yang sama tidak boleh dimasukkan lebih dari satu kali.'
                );

                return;
            }

            const saveButton =
                document.getElementById(
                    'save-draft-button'
                );

            saveButton.disabled =
                true;

            saveButton.innerHTML =
                '<i class="fas fa-spinner fa-spin mr-1"></i> Saving...';
        }
    );

    if (
        Array.isArray(oldDetails) &&
        oldDetails.length > 0
    ) {

        oldDetails.forEach(
            function (detail) {
                addRow(detail);
            }
        );

    } else {

        addRow();

    }

})();
</script>
@stop