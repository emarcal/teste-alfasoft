@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">


            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Trash</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>#</th><th>Name</th><th>Contact</th><th>Email</th><th>Deleted at</th><th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($contact as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $item->name }}</td><td>{{ $item->contact }}</td>
                                        <td>{{ $item->email }}</td>
                                        <td>{{ $item->deleted_at }}</td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ url('/admin/contact/recover' . '/' . $item->id) }}" accept-charset="UTF-8" style="display:inline">
                                                {{ method_field('POST') }}
                                                {{ csrf_field() }}
                                                <button type="submit" class="btn btn-secondary btn-sm" title="Recover Contact" onclick="return confirm(&quot;Confirm recover?&quot;)"><i class="fa fa-history" aria-hidden="true"></i> Recover</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="pagination-wrapper"> {!! $contact->appends(['search' => Request::get('search')])->render() !!} </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
