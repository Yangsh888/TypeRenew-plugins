(function () {
    if (!(window.TypechoTabs && typeof window.TypechoTabs.init === 'function')) {
        return;
    }

    var sticky = document.getElementById('renewshield-sticky');
    var tabFields = document.querySelectorAll('input[name="tab"]');
    var applyProfileField = document.getElementById('renewshield-apply-profile');
    var applyButtons = document.querySelectorAll('[data-shield-apply-profile]');
    var form = document.getElementById('renewshield-main-form');

    if (applyProfileField && applyButtons.length) {
        for (var a = 0; a < applyButtons.length; a++) {
            applyButtons[a].addEventListener('click', function (event) {
                event.preventDefault();
                applyProfileField.value = this.getAttribute('data-shield-apply-profile') === '1' ? '1' : '0';
                if (form) {
                    form.submit();
                }
            });
        }
    }

    window.TypechoTabs.init({
        onChange: function (target) {
            var showSticky = target !== 'ops';
            if (sticky) {
                sticky.classList.toggle('is-hidden', !showSticky);
                sticky.setAttribute('aria-hidden', showSticky ? 'false' : 'true');
            }

            for (var i = 0; i < tabFields.length; i++) {
                tabFields[i].value = target;
            }
        }
    });
})();
