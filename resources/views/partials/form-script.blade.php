{{--
    Form submit helper. Include once per page, inside @push('script'):

        @push('script')
            @include('partials.form-script')
        @endpush

    Then mark each form with data-kavera-form and give it a hidden "recaptcha"
    field. The helper (public/js/kavera-form.js) disables the button while
    sending, fetches a reCAPTCHA token with a time limit when a site key is
    configured, and submits either way so the visitor is never stuck.
    reCAPTCHA is skipped in local development and when no site key is set.
--}}
@php
    $kaveraRecaptchaKey = (!is_dev() && _c('form.recaptcha.site_key')) ? (string) _c('form.recaptcha.site_key') : '';
@endphp
@if($kaveraRecaptchaKey !== '')
<script src="https://www.google.com/recaptcha/api.js?render={{ $kaveraRecaptchaKey }}" async defer></script>
@endif
<script src="{{ asset('js/kavera-form.js') }}" data-recaptcha-key="{{ $kaveraRecaptchaKey }}"></script>
