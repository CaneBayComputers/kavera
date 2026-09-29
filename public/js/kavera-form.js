/*
 * Kavera form helper: makes Send reliable when Google reCAPTCHA is slow,
 * blocked or missing.
 *
 * Include it through the Blade partial, which also loads the reCAPTCHA
 * script when a site key is configured:
 *
 *     @push('script')
 *         @include('partials.form-script')
 *     @endpush
 *
 * It binds every <form data-kavera-form>. On submit it disables the submit
 * button, shows "Sending…", asks reCAPTCHA for a token with a time limit,
 * puts the token in the hidden "recaptcha" field and submits. If the
 * reCAPTCHA script never loaded, throws, or does not answer in time, the
 * form is submitted anyway and the server decides what to do; the visitor
 * never ends up with a dead button.
 *
 * Optional attributes on the form:
 *   data-sending-label="Sending…"   text shown on the button while sending
 *   data-recaptcha-timeout="8000"   milliseconds to wait for a token
 */
(function () {
    'use strict';

    var current = document.currentScript;
    var siteKey = (current && current.getAttribute('data-recaptcha-key')) || '';
    var DEFAULT_TIMEOUT = 8000;
    var POLL = 100;

    function recaptchaReady() {
        return typeof window.grecaptcha !== 'undefined'
            && window.grecaptcha
            && typeof window.grecaptcha.ready === 'function';
    }

    /* Resolves with a token, or with '' when one cannot be had in time. Never rejects. */
    function getToken(timeout) {
        return new Promise(function (resolve) {
            if (!siteKey) {
                resolve('');
                return;
            }

            var done = false;
            var deadline = Date.now() + timeout;

            function finish(token) {
                if (done) {
                    return;
                }
                done = true;
                resolve(typeof token === 'string' ? token : '');
            }

            var timer = setTimeout(function () { finish(''); }, timeout);

            function execute() {
                try {
                    window.grecaptcha.ready(function () {
                        try {
                            var pending = window.grecaptcha.execute(siteKey, { action: 'submit' });
                            if (pending && typeof pending.then === 'function') {
                                pending.then(function (token) { clearTimeout(timer); finish(token); },
                                             function () { clearTimeout(timer); finish(''); });
                            } else {
                                clearTimeout(timer);
                                finish('');
                            }
                        } catch (err) {
                            clearTimeout(timer);
                            finish('');
                        }
                    });
                } catch (err) {
                    clearTimeout(timer);
                    finish('');
                }
            }

            /* The reCAPTCHA script may still be downloading (or blocked): wait for it, bounded by the deadline. */
            (function waitForScript() {
                if (done) {
                    return;
                }
                if (recaptchaReady()) {
                    execute();
                } else if (Date.now() < deadline) {
                    setTimeout(waitForScript, POLL);
                } else {
                    clearTimeout(timer);
                    finish('');
                }
            })();
        });
    }

    function bind(form) {
        if (form.kaveraFormBound) {
            return;
        }
        form.kaveraFormBound = true;

        var button = form.querySelector('button[type="submit"], input[type="submit"]');
        var idleLabel = button ? (button.tagName === 'INPUT' ? button.value : button.innerHTML) : '';
        var sendingLabel = form.getAttribute('data-sending-label') || 'Sending…';
        var timeout = parseInt(form.getAttribute('data-recaptcha-timeout'), 10) || DEFAULT_TIMEOUT;
        var busy = false;

        function setBusy(on) {
            busy = on;
            form.setAttribute('aria-busy', on ? 'true' : 'false');
            if (!button) {
                return;
            }
            button.disabled = on;
            if (button.tagName === 'INPUT') {
                button.value = on ? sendingLabel : idleLabel;
            } else {
                button.innerHTML = on ? sendingLabel : idleLabel;
            }
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (busy) {
                return;
            }
            setBusy(true);

            getToken(timeout).then(function (token) {
                var hidden = form.querySelector('input[name="recaptcha"]');
                if (hidden) {
                    hidden.value = token;
                }
                /* form.submit() does not re-fire this handler, and the browser already ran its own validation. */
                HTMLFormElement.prototype.submit.call(form);
            });
        });

        /* Back/forward cache restores the page with the button still disabled: reset it. */
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                setBusy(false);
            }
        });
    }

    function init() {
        var forms = document.querySelectorAll('form[data-kavera-form]');
        for (var i = 0; i < forms.length; i++) {
            bind(forms[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
