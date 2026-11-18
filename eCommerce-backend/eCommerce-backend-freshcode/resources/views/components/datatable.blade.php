<table id="{{ $id }}" data-datatable data-route="{{ $route }}" data-columns='@json($columns)'
       data-options='@json($options ?? [])'
       data-export-options='@json($exportOptions ?? [])'
       class="{{ $class ?? 'table table-striped table-bordered table-vcenter text-nowrap w-100' }} data-table">
    <thead class="table-light">
    <tr>
@foreach ($columns as $column)
            <th>{{ $column['label'] ?? '' }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    </tbody>
</table>
@if(!empty($exportOptions['module']))
    @once
        @include('components.datatable-export-modal')
    @endonce
@endif
