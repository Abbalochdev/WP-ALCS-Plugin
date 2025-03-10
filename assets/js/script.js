document.addEventListener('DOMContentLoaded', function () {
    const LocaleManager = {
        isLoading: false,

        init() {
            this.cacheDOM();
            this.bindEvents();
            // console.log('LocaleManager initialized', alcsData);
        },

        cacheDOM() {
            this.isMobile = window.innerWidth <= 768;
            this.langButton = document.getElementById(this.isMobile ? 'mobile-lang-switch' : 'desktop-lang-switch');
            this.countrySelectors = document.querySelectorAll('.custom-dropdown');
        },

        bindEvents() {
            if (this.langButton) {
                this.langButton.addEventListener('click', this.handleLanguageSwitch.bind(this));
            }

            this.countrySelectors.forEach(selector => {
                selector.addEventListener('click', this.handleCountrySelector.bind(this));
            });

            document.addEventListener('click', this.closeDropdowns.bind(this));
        },

        handleLanguageSwitch(e) {
            e.preventDefault();
            if (this.isLoading) return;

            const newLang = alcsData.currentLang === 'ar' ? 'en' : 'ar';
            this.updateLocale(alcsData.currentCountry, newLang);
        },

        handleCountrySelector(e) {
            e.stopPropagation();
            const selector = e.currentTarget;
            const dropdownBtn = selector.querySelector('.dropdown-btn');
            const clickedItem = e.target.closest('.dropdown-item');

            if (dropdownBtn) {
                selector.classList.toggle('open');
            }

            if (clickedItem) {
                e.preventDefault();
                const newCountry = clickedItem.dataset.country;
                selector.classList.remove('open');
                this.updateLocale(newCountry, alcsData.currentLang);
            }
        },

        closeDropdowns(e) {
            if (!e.target.closest('.custom-dropdown')) {
                document.querySelectorAll('.custom-dropdown.open').forEach(d => d.classList.remove('open'));
            }
        },

        async updateLocale(country, lang) {
            if (this.isLoading) return;
            this.isLoading = true;

            try {
                const response = await fetch(alcsData.ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'alcs_switch_locale',
                        nonce: alcsData.nonce,
                        country,
                        lang: lang.replace('_US', ''),
                        current_path: window.location.pathname
                    })
                });

                const data = await response.json();
                console.log('Response:', data);

                if (data.success && data.data?.redirect) {
                    window.location.href = data.data.redirect;
                } else {
                    throw new Error(data.data?.message || 'Update failed');
                }
            } catch (error) {
                console.error('Error updating locale:', error);
                alert(`Failed to update locale: ${error.message}`);
            } finally {
                this.isLoading = false;
            }
        },
    };

    LocaleManager.init();
});
