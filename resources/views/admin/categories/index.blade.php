@extends('layouts.panel')

@section('title', __('Categories'))
@section('eyebrow', __('Service management'))
@section('page-title', __('Service categories'))
@section('page-description', __('Groups of services, and the input fields customers fill in when ordering from each group.'))

@section('page-actions')
    <a class="btn btn-primary" href="{{ route('admin.categories.create') }}">@include('partials.icon', ['name' => 'plus']) {{ __('New category') }}</a>
@endsection

@section('content')
    @include('partials.page-guide', [
        'key' => 'admin-categories',
        'title' => 'How categories and input fields work',
        'intro' => 'A category groups related services and owns the list of input fields those services can ask for.',
        'steps' => [
            '<b>Create the category</b>, for example "EMI Services".',
            '<b>Add its input fields</b>: IMEI, Serial Number, Email, Lock Code, or free text / number. Each type is validated for you (an IMEI must be 15 digits).',
            '<b>Add services</b> to the category and tick which of those fields each service needs.',
        ],
        'note' => 'An inactive category hides all of its services from customers without deleting anything.',
    ])

    <div class="card">
        @if ($categories->isEmpty())
            <div class="empty"><strong>{{ __('No categories yet') }}</strong>{{ __('Create your first category to start adding services.') }}</div>
        @else
            <div class="table-wrap">
                <table class="cards">
                    <thead><tr><th>{{ __('Category') }}</th><th>{{ __('Input fields') }}</th><th class="right">{{ __('Services') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td class="primary">
                                    <a href="{{ route('admin.categories.edit', $category->id) }}">{{ $category->name }}</a>
                                    <span class="sub">{{ \Illuminate\Support\Str::limit($category->description, 80) }}</span>
                                </td>
                                <td data-label="{{ __('Input fields') }}">
                                    @forelse ($category->fields as $field)
                                        <span class="tag" title="{{ __($field->typeLabel()) }}">{{ $field->label }}</span>
                                    @empty
                                        <span class="muted">{{ __('None') }}</span>
                                    @endforelse
                                </td>
                                <td data-label="{{ __('Services') }}" class="right num"><a href="{{ route('admin.services.index', ['category' => $category->id]) }}">{{ $category->services_count }}</a></td>
                                <td data-label="{{ __('Status') }}"><span class="badge badge-{{ $category->status }}">{{ __(ucfirst($category->status)) }}</span></td>
                                <td>
                                    <div class="row-actions">
                                        <a class="btn btn-sm" href="{{ route('admin.services.create', ['category' => $category->id]) }}">{{ __('Add service') }}</a>
                                        <a class="btn btn-sm" href="{{ route('admin.categories.edit', $category->id) }}">{{ __('Edit') }}</a>
                                        <form method="post" action="{{ route('admin.categories.toggle', $category->id) }}">
                                            @csrf
                                            <button class="btn btn-sm" type="submit">{{ $category->status === 'active' ? __('Deactivate') : __('Activate') }}</button>
                                        </form>
                                        <form method="post" action="{{ route('admin.categories.destroy', $category->id) }}" data-confirm="{{ __('Delete the category ":name"? This cannot be undone.', ['name' => $category->name]) }}">
                                            @csrf @method('delete')
                                            <button class="btn btn-sm btn-danger" type="submit">{{ __('Delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
