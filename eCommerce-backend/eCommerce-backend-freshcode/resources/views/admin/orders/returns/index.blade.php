@extends('layouts.admin.app', ['page' => 'returns'])

@section('admin-content')
    <div class="page-wrapper">
        <div class="page-header d-print-none">
            <div class="container-xl">
                <div class="row align-items-center">
                    <div class="col">
                        <h2 class="page-title">{{ __('labels.return_requests') ?? 'Returns' }}</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="page-body">
            <div class="container-xl">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ __('labels.return_requests') ?? 'Returns' }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <x-datatable id="admin-returns-table" :columns="$columns"
                                         route="{{ route('admin.orders.returns.datatable') }}"
                                         :export-options="['module' => 'returns', 'rowId' => 'id']"
                                         :options="['order' => [[0, 'desc']], 'pageLength' => 10]"/>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
