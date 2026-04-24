(function () {
    'use strict';

    if (window.TypechoTabs && typeof window.TypechoTabs.init === 'function') {
        window.TypechoTabs.init();
    }

    const toggles = [
        { id: 'baiduEnable', groupClass: 'group-baidu-push' },
        { id: 'indexNowEnable', groupClass: 'group-indexnow-push' },
        { id: 'bingEnable', groupClass: 'group-bing-push' }
    ];

    toggles.forEach((toggle) => {
        const checkbox = document.getElementById(toggle.id);
        if (!checkbox) {
            return;
        }

        const groupElements = document.querySelectorAll('.' + toggle.groupClass);
        const updateVisibility = (checked) => {
            groupElements.forEach((el) => {
                el.style.display = checked ? '' : 'none';
            });
        };

        updateVisibility(checkbox.checked);
        checkbox.addEventListener('change', function () {
            updateVisibility(this.checked);
        });
    });
})();
