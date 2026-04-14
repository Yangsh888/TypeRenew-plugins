document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tr-panel-tab');
    const tabContents = document.querySelectorAll('.tr-panel-pane');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('is-active'));
            this.classList.add('is-active');

            const target = this.getAttribute('data-target');

            tabContents.forEach(content => {
                if (content.getAttribute('data-tab') === target) {
                    content.classList.add('is-active');
                } else {
                    content.classList.remove('is-active');
                }
            });
        });
    });

    const toggles = [
        { id: 'baiduEnable', groupClass: 'group-baidu-push' },
        { id: 'indexNowEnable', groupClass: 'group-indexnow-push' },
        { id: 'bingEnable', groupClass: 'group-bing-push' }
    ];

    toggles.forEach(toggle => {
        const checkbox = document.getElementById(toggle.id);
        if (!checkbox) return;

        const groupElements = document.querySelectorAll('.' + toggle.groupClass);

        const updateVisibility = (checked, initial = false) => {
            groupElements.forEach(el => {
                if (checked) {
                    el.style.display = '';
                    setTimeout(() => {
                        el.classList.remove('hidden-smooth');
                        if (!initial) {
                            el.classList.add('visible-smooth');
                        }
                    }, 10);
                } else {
                    el.classList.add('hidden-smooth');
                    el.classList.remove('visible-smooth');
                    if (!initial) {
                        setTimeout(() => {
                            if (el.classList.contains('hidden-smooth')) {
                                el.style.display = 'none';
                            }
                        }, 200);
                    } else {
                        el.style.display = 'none';
                    }
                }
            });
        };

        updateVisibility(checkbox.checked, true);

        checkbox.addEventListener('change', function() {
            updateVisibility(this.checked);
        });
    });
});
