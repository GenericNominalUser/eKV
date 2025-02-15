@extends('dashboard.layout.admin')
@section('content')
    <div class="w-100 h-100 mt-3">
        <div class="row d-flex justify-content-center align-items-center">
            <div class="col-12 col-lg-10">
                <form action="" method="post" class="mt-3 mb-5">
                    @csrf
                    <h2 class="text-left">Kemas Kini Kursus</h2>
                    @if(session()->has('courseUpdateSuccess'))
                        <div class="alert alert-success">{{ session('courseUpdateSuccess') }}</div>
                    @endif
                    @error('notExisted')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                    <div class="mb-3">
                        <p>Kod Kursus: <span>{{ strtoupper($course->code) }}</span></p>
                        <input type="hidden" name="course_code" value="{{ $course->code }}">
                    </div>
                    <div class="form-floating mb-3">
                        <input type="text" name="course_name" id="course_name" class="form-control" placeholder="course_name" value="@php if(old('course_name') !== null){echo old('course_name');}elseif(isset($course->name)){echo ucwords($course->name);}else{echo NULL;} @endphp">
                        <label for="course_name" class="form-label">Nama Kursus</label>
                        @error('course_name')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100 hvr-shrink" name="info">Kemas Kini</button>
                </form>
            </div>
        </div>
    </div>
@endsection