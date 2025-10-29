@extends('templates.main')

@section('content')

<!-- Contact Form Section -->
<div class="container my-5">
    <div class="row">
        <div class="col-md-6 mx-auto">
            @if(session('success'))
            <div class="alert alert-success" role="alert">
                Message has been sent!
            </div>
            @elseif(session('errors') && session('errors')->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach (session('errors')->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
            <h2>Contact Us</h2>
            <form action="/forms/contact" id="contact-form" method="post">
                @csrf
                <div class="mb-3">
                    <label for="firstName" class="form-label">First Name</label>
                    <input type="text" maxlength="100" name="first_name" class="form-control" id="firstName" placeholder="Enter your first name" value="{{ old('first_name') }}" required>
                </div>
                <div class="mb-3">
                    <label for="lastName" class="form-label">Last Name</label>
                    <input type="text" maxlength="100" name="last_name" class="form-control" id="lastName" placeholder="Enter your last name" value="{{ old('last_name') }}">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" maxlength="100" name="email" class="form-control" id="email" placeholder="Enter your email" value="{{ old('email') }}" required>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" maxlength="100" name="phone" class="form-control" id="phone" placeholder="Enter your phone number" value="{{ old('phone') }}" required>
                </div>
                <div class="mb-3">
                    <label for="subject" class="form-label">Subject</label>
                    <select name="subject" class="form-select" id="subject" required>
                        <option value="">Select a subject</option>
                        <option value="general" {{ old('subject') == 'general' ? 'selected' : '' }}>General Inquiry</option>
                        <option value="support" {{ old('subject') == 'support' ? 'selected' : '' }}>Technical Support</option>
                        <option value="billing" {{ old('subject') == 'billing' ? 'selected' : '' }}>Billing &amp; Payments</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-check-label">Preferred Contact Method</label>
                    <div>
                        <div class="form-check">
                            <input name="preferred_contact" class="form-check-input" type="radio" name="contactMethod" id="radioEmail" value="email" checked>
                            <label class="form-check-label" for="radioEmail">Email</label>
                        </div>
                        <div class="form-check">
                            <input name="preferred_contact" class="form-check-input" type="radio" name="contactMethod" id="radioPhone" value="phone">
                            <label class="form-check-label" for="radioPhone">Phone</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-check-label">Interests (Check all that apply)</label>
                    <div>
                        <div class="form-check">
                            <input name="interests[]" class="form-check-input" type="checkbox" id="checkbox1" value="newsletter">
                            <label class="form-check-label" for="checkbox1">Newsletter</label>
                        </div>
                        <div class="form-check">
                            <input name="interests[]" class="form-check-input" type="checkbox" id="checkbox2" value="promotions">
                            <label class="form-check-label" for="checkbox2">Promotions</label>
                        </div>
                        <div class="form-check">
                            <input name="interests[]" class="form-check-input" type="checkbox" id="checkbox3" value="productUpdates">
                            <label class="form-check-label" for="checkbox3">Product Updates</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="message" class="form-label">Message</label>
                    <textarea name="message" maxlength="2000" class="form-control" id="message" rows="4" placeholder="Enter your message" required>{{ old('message') }}</textarea>
                </div>
                @csrf
                <input type="hidden" id="recaptcha" name="recaptcha" value="">
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        </div>
    </div>
</div>

@endsection

@push('script')

@if(!is_dev())
<script src="https://www.google.com/recaptcha/api.js?render={!! _c('form.recaptcha.site_key') !!}"></script>

<script>

document.getElementById('contact-form').onsubmit = function(e) {
    grecaptcha.ready(function() {
        grecaptcha.execute('{!! _c('form.recaptcha.site_key') !!}', {action: 'submit'}).then(function(token) {
            document.getElementById("recaptcha").value = token;
            e.target.submit();
        });
    });
    return false;
};

@endif

</script>

@endpush
