@extends('templates.main')

@php
    $pageTitle = 'Contact ' . config('app.name');
    $pageDescription = 'Send a message and we will get back to you.';
@endphp

@section('content')
<section class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-7 mx-auto">
                <h1 class="mb-3">Contact</h1>

                @if(session('success'))
                    <div class="alert alert-success" role="alert">Thanks, your message has been sent.</div>
                @elseif(session('errors') && session('errors')->any())
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0">
                            @foreach(session('errors')->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Field names must match the rules for the "contact" form in config/form.php. --}}
                <form action="/forms/contact" method="post" id="contact-form" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label for="first_name" class="form-label">First name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" maxlength="100" value="{{ old('first_name') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label for="last_name" class="form-label">Last name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" maxlength="100" value="{{ old('last_name') }}">
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" maxlength="100" value="{{ old('email') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="tel" class="form-control" id="phone" name="phone" maxlength="100" value="{{ old('phone') }}">
                    </div>
                    <div class="col-12">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="5" maxlength="2000" required>{{ old('message') }}</textarea>
                    </div>
                    <input type="hidden" id="recaptcha" name="recaptcha" value="">
                    <div class="col-12">
                        <button type="submit" class="btn btn-accent">Send message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

@push('script')
@if(!is_dev() && _c('form.recaptcha.site_key'))
<script src="https://www.google.com/recaptcha/api.js?render={!! _c('form.recaptcha.site_key') !!}"></script>
<script>
    document.getElementById('contact-form').onsubmit = function (e) {
        grecaptcha.ready(function () {
            grecaptcha.execute('{!! _c('form.recaptcha.site_key') !!}', { action: 'submit' }).then(function (token) {
                document.getElementById('recaptcha').value = token;
                e.target.submit();
            });
        });
        return false;
    };
</script>
@endif
@endpush
