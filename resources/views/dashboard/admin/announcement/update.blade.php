@extends('dashboard.layout.admin')
@section('content')
    <div class="w-100 h-100 mt-3">
        <div class="row d-flex justify-content-center align-items-center">
            <div class="col-12 col-lg-10">
                <form action="" method="post" class="mt-3 mb-5">
                    @csrf
                    <h2>Kemas Kini Pengumuman</h2>
                    @if(session()->has('announcementUpdateSuccess'))
                        <div class="alert alert-success">{{ session('announcementUpdateSuccess') }}</div>
                    @endif
                    @error('existed')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                    <div class="form-floating mb-3">
                        <input type="text" name="title" id="title" class="form-control" placeholder="title" value="@php if(old('title') !== null){echo old('title');}elseif(!empty($announcementPost->title)){echo strtoupper($announcementPost->title);}else{echo NULL;} @endphp">
                        <label for="title" class="form-label">Tajuk</label>
                        @error('title')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror
                    </div>
                    <div id="titleHelp" class="form-text mb-3">Tajuk mestilah ringkas dan padat.</div>
                    <p class="fw-bold mt-1">Diterbitkan oleh: <span class="fst-italic fw-normal">{{ strtoupper($announcementPost->username) }}</span></p>
                    <p class="fw-bold mt-1">Diterbitkan pada: <span><x-buk-carbon :date="$announcementPost->created_at" format="d/m/Y, h:i A" class="fst-italic fw-normal"/></span></p>
                    <label for="content" class="form-label fw-bold mt-1">Kandungan</label>
                    <x-buk-easy-mde name="content" :options="['initialValue' => $announcementPost->content, 'spellChecker' => false]"/>
                    @error('content')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror
                    <div id="contentHelp" class="form-text mb-3">Penyunting teks ini memanfaatkan penggunaan <a href="https://www.markdownguide.org/cheat-sheet/" class="hvr-underline-reveal">Markdown</a>.</div>
                    <input type="hidden" name="user" value="{{ $announcementPost->id }}">
                    <button type="submit" class="btn btn-primary w-100 hvr-shrink" name="info">Kemas Kini</button>
                </form>
            </div>
        </div>
    </div>    
@endsection