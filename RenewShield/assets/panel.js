(function () {
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
            return;
        }
        fn();
    }

    ready(function () {
        var tabs = document.querySelectorAll('.tr-panel-tab');
        var panes = document.querySelectorAll('.tr-panel-pane');
        var sticky = document.getElementById('renewshield-sticky');
        var tabFields = document.querySelectorAll('input[name="tab"]');
        var applyProfileField = document.getElementById('renewshield-apply-profile');
        var applyButtons = document.querySelectorAll('[data-shield-apply-profile]');
        var form = document.getElementById('renewshield-main-form');
        if (!tabs.length || !panes.length) {
            return;
        }

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

        function activate(tab, focusTab) {
            var target = tab.getAttribute('data-target');
            for (var i = 0; i < tabs.length; i++) {
                var isActive = tabs[i] === tab;
                tabs[i].classList.toggle('is-active', isActive);
                tabs[i].setAttribute('aria-selected', isActive ? 'true' : 'false');
                tabs[i].setAttribute('tabindex', isActive ? '0' : '-1');
            }

            for (var j = 0; j < panes.length; j++) {
                var paneActive = panes[j].getAttribute('data-tab') === target;
                panes[j].classList.toggle('is-active', paneActive);
                panes[j].hidden = !paneActive;
            }

            if (sticky) {
                var showSticky = target !== 'ops';
                sticky.classList.toggle('is-hidden', !showSticky);
                sticky.setAttribute('aria-hidden', showSticky ? 'false' : 'true');
            }

            for (var n = 0; n < tabFields.length; n++) {
                tabFields[n].value = target;
            }

            if (focusTab) {
                tab.focus();
            }
        }

        function move(step) {
            var current = 0;
            for (var i = 0; i < tabs.length; i++) {
                if (tabs[i].classList.contains('is-active')) {
                    current = i;
                    break;
                }
            }

            var next = (current + step + tabs.length) % tabs.length;
            activate(tabs[next], true);
        }

        for (var k = 0; k < tabs.length; k++) {
            tabs[k].addEventListener('click', function () {
                activate(this, false);
            });
            tabs[k].addEventListener('keydown', function (event) {
                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                    event.preventDefault();
                    move(1);
                    return;
                }

                if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    move(-1);
                    return;
                }

                if (event.key === 'Home') {
                    event.preventDefault();
                    activate(tabs[0], true);
                    return;
                }

                if (event.key === 'End') {
                    event.preventDefault();
                    activate(tabs[tabs.length - 1], true);
                }
            });
        }

        for (var m = 0; m < tabs.length; m++) {
            if (tabs[m].classList.contains('is-active')) {
                activate(tabs[m], false);
                break;
            }
        }
    });
})();
