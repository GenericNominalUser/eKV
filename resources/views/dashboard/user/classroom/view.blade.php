@extends('dashboard.layout.main')
@section('content')
    <div class="container-fluid mt-1 w-100 h-100 d-flex flex-column align-items-center">
        <div class="row rounded-3 shadow-lg mt-5 w-100">
            <div class="col-6 my-3 text-start">
                <a href="{{ route('dashboard') }}" class="btn btn-primary"><i class="bi bi-arrow-return-left"></i>Dashboard</a>
            </div>
            <div class="col-6 my-3 text-end">
                {{-- Only coordinator and admin can view these buttons --}}
                @if(Gate::allows('authAdmin') || Gate::allows('authCoordinator', $classroomInfo->id))
                    <a href="{{ route('classroom.update', [$classroomInfo->id]) }}" class="btn btn-primary"><i class="bi bi-pencil-square"></i>Kelas</a>
                    <a href="{{ route('classroom.student', [$classroomInfo->id]) }}" class="btn btn-primary"><i class="bi bi-pencil-square"></i>Pelajar</a>
                @endif
            </div>
        </div>
        <div class="row rounded-3 shadow-lg mt-2 mb-2 w-100">
            <div class="row">
                <h1 class="fs-2 text-center my-3">MAKLUMAT KELAS</h1>
            </div>
            <div class="row">
                <div class="col-6">
                    <p class="fw-bold">ID KELAS: <span class="fw-normal">{{ $classroomInfo->id }}</span></p>
                    <p class="fw-bold">NAMA KELAS: <span class="fw-normal">{{ strtoupper($classroomInfo->name) }}</span></p>
                    <p class="fw-bold">KOD PROGRAM: <span class="fw-normal">{{ strtoupper($classroomInfo->programs_code) }}</span></p>
                    <p class="fw-bold">ID KOORDINATOR: 
                        <span class="fw-normal"><a href="{{ route('profile.user', [$classroomCoordinator->users_username]) }}" target="_blank" class="text-dark hvr-underline-reveal">{{ strtoupper($classroomCoordinator->users_username) }}</a></span>
                    </p>
                </div>
                <div class="col-6">
                    <p class="fw-bold">TAHUN KEMASUKAN: <span class="fw-normal">{{ $classroomInfo->admission_year }}</span></p>
                    <p class="fw-bold">TAHUN BELAJAR: <span class="fw-normal">{{ $classroomInfo->study_year }}</span></p>
                    <p class="fw-bold">KOD TAHUN PENGAJIAN: <span class="fw-normal">{{ strtoupper($classroomInfo->study_levels_code) }}</span></p>
                </div>
            </div>
            <div class="row  d-flex flex-column align-items-center justify-content-center">
                <h1 class="fs-2 text-center my-3">SENARAI PELAJAR</h1>
            </div>
            <div class="row d-flex flex-column align-items-center justify-content-center">
                <div class="row table-responsive d-flex flex-column align-items-center justify-content-center">
                    @if($students != NULL)
                        <table class="table table-hover table-bordered border-secondary text-center w-75 my-3">
                            <thead class="table-dark">
                                <tr>
                                    <tr>
                                    <th class="col-2">ID PELAJAR</th>
                                    <th class="col-5">NAMA PELAJAR</th>
                                    <th class="col-5">E-MEL PELAJAR</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($students as $s)
                                <tr>
                                    <td>{{ strtoupper($s->username) }}</td>
                                    <td>{{ strtoupper($s->fullname) }}</td>
                                    <td>{{ strtoupper($s->email) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="my-4">
                            <p class="text-center mt-1 fs-5">Tiada rekod pelajar.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection