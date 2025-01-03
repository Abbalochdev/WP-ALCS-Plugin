document.addEventListener('DOMContentLoaded', function () {
    // Language Switcher
    function detectUserLanguage() {
        const userLang = navigator.language || navigator.userLanguage;
        return userLang.startsWith('ar') ? 'ar' : 'en_US';
    }

    function setLanguage(lang) {
        localStorage.setItem('preferredLang', lang);
        document.cookie = `preferredLang=${lang}; path=/; max-age=31536000`;
        location.reload();
    }

    function updateLanguageUI(lang, button) {
        if (button) {
            if (lang === 'ar') {
                document.documentElement.setAttribute('lang', 'ar');
                document.documentElement.setAttribute('dir', 'rtl');
                button.textContent = 'English';
                button.setAttribute('data-lang', 'en_US');
            } else {
                document.documentElement.setAttribute('lang', 'en');
                document.documentElement.setAttribute('dir', 'ltr');
                button.textContent = 'العربية';
                button.setAttribute('data-lang', 'ar');
            }
        }
    }

    // Country Selector
    function initializeCountrySelectors() {
        const countrySelectors = document.querySelectorAll('.custom-dropdown');
        const countryLabels = document.querySelectorAll('#selected-country-label');

        const countryNames = {
            'AE': 'UAE',
            'SA': 'Saudi Arabia',
            'QA': 'Qatar',
            'KW': 'Kuwait',
            'OM': 'Oman',
        };

        countrySelectors.forEach((countrySelector) => {
            const dropdownBtn = countrySelector.querySelector('.dropdown-btn');
            const dropdownItems = countrySelector.querySelectorAll('.dropdown-item');

            // Toggle dropdown visibility
            dropdownBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                countrySelector.classList.toggle('open');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function (event) {
                if (!countrySelector.contains(event.target)) {
                    countrySelector.classList.remove('open');
                }
            });

            // Handle country selection
            dropdownItems.forEach((item) => {
                item.addEventListener('click', function () {
                    const country = this.getAttribute('data-country');
                    const countryFlag = this.querySelector('img').src;
                    const countryName = countryNames[country];

                    // Update all labels
                    countryLabels.forEach(label => {
                        label.innerHTML = `<img src="${countryFlag}" alt="${countryName} Flag" class="flag-icon"> ${countryName}`;
                    });

                    // Set country cookie
                    document.cookie = `selected_country=${country}; path=/; max-age=${365 * 24 * 60 * 60}`;

                    // Close dropdown
                    countrySelector.classList.remove('open');

                    // Force page reload to apply changes
                    window.location.reload();
                });
            });
        });
    }

    // Initialize Language Switcher
    const isMobile = window.innerWidth <= 768;
    const langButton = isMobile ? 
        document.getElementById('mobile-lang-switch') : 
        document.getElementById('desktop-lang-switch');

    const savedLang = localStorage.getItem('preferredLang') || detectUserLanguage();
    updateLanguageUI(savedLang, langButton);

    if (langButton) {
        langButton.addEventListener('click', function() {
            const selectedLang = this.getAttribute('data-lang');
            setLanguage(selectedLang);
        });
    }

    // Initialize Country Selector
    initializeCountrySelectors();

    // Handle window resize
    let wasMobile = isMobile;
    window.addEventListener('resize', function() {
        const newIsMobile = window.innerWidth <= 768;
        if (wasMobile !== newIsMobile) {
            wasMobile = newIsMobile;
            location.reload();
        }
    });
});