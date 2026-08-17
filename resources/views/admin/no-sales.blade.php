@extends('layouts.app')

@section('title', 'Overview')

@section('content')
    <div class="card">
        <div class="empty">
            <h1>No sales yet</h1>
            <p>
                Once a promotional sale exists and orders start coming in on creators’ codes,
                this screen shows every creator’s numbers side by side.
            </p>
            <p style="margin-top: 20px;">
                <a class="btn btn--primary" href="{{ route('admin.creators.index') }}">Manage creators</a>
            </p>
        </div>
    </div>
@endsection
