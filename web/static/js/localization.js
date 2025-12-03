let localization, lang;

class Localization {
    constructor() {
        this.supportedLanguages = ['en', 'ru', 'es', 'de'];
        const browserLang = navigator.language.substring(0, 2).toLowerCase();
        const defaultLang = this.supportedLanguages.includes(browserLang) ? browserLang : 'en';
        this.currentLang = localStorage.getItem('language') || defaultLang;
        this.translations = {};
        this.cacheKey = 'translations-cache';
        this.showSkeletonLoaders();
        this.loadTranslations();
        lang = this.currentLang;
        document.documentElement.lang = lang;
    }

    get key() {
        return this.translations;
    }

    updateAllContent() {
        this.updateContent();
    }

    async loadTranslations() {
        try {
            const translations = this.getCachedTranslations();
            if (translations) {
                this.translations = translations;
                this.updateContent();
            } else {
                await this.fetchAndCacheTranslations();
            }
        } catch (error) {
            console.error('Error loading translations:', error);
            this.hideSkeletonLoaders();
        }
    }

    getCachedTranslations() {
        const cachedData = localStorage.getItem(`${this.cacheKey}-${this.currentLang}`);
        if (cachedData) {
            try {
                return JSON.parse(cachedData);
            } catch (error) {
                console.error('Error parsing cached translations:', error);
                return null;
            }
        }
        return null;
    }

    async fetchAndCacheTranslations() {
        try {
            const response = await fetch(`translations.php?l10n`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const translations = await response.json();

            // Сохраняем в localStorage
            localStorage.setItem(
                `${this.cacheKey}-${this.currentLang}`,
                JSON.stringify(translations[this.currentLang])
            );

            this.translations = translations[this.currentLang];
            this.updateContent();
        } catch (error) {
            console.error('Error fetching and caching translations:', error);
            this.hideSkeletonLoaders();
        }
    }

    async setLang(lang) {
        if (!this.supportedLanguages.includes(lang)) {
            console.error('Unsupported language:', lang);
            return;
        }

        document.documentElement.lang = lang;
        this.showSkeletonLoaders();

        const keysToRemove = Object.keys(localStorage).filter(key => key.startsWith('translations-'));
        keysToRemove.forEach(key => localStorage.removeItem(key));

        this.currentLang = lang;
        localStorage.setItem('language', lang);
        await this.fetchAndCacheTranslations();
    }

    getCurrentLanguage() {
        return this.currentLang;
    }

    translate(key) {
        return this.translations[key];
    }

    showSkeletonLoaders() {
        const elements = document.querySelectorAll('[l10n]');
        elements.forEach(element => {
            const key = element.getAttribute('l10n');

            const originalContent = element.innerHTML;
            if (!element.hasAttribute('data-original-content')) {
                element.setAttribute('data-original-content', originalContent);
            }

            const skeleton = document.createElement('span');
            skeleton.className = 'l10n-skeleton';
            skeleton.style.cssText = `
                min-width: ${Math.min(100, key.length * 10)}px;
                height: 1em;
            `;

            skeleton.textContent = ' '.repeat(Math.max(3, key.length));

            element.innerHTML = '';
            element.appendChild(skeleton);
        });

        const placeholderElements = document.querySelectorAll('[l10n-placeholder]');
        placeholderElements.forEach(element => {
            const key = element.getAttribute('l10n-placeholder');

            if (!element.hasAttribute('data-original-placeholder')) {
                const originalPlaceholder = element.placeholder || '';
                element.setAttribute('data-original-placeholder', originalPlaceholder);
            }

            element.placeholder = ' '.repeat(Math.max(3, key.length));

            element.classList.add('l10n-placeholder-skeleton');
        });

        this.addSkeletonStyles();
    }

    addSkeletonStyles() {
        if (!document.getElementById('l10n-skeleton-styles')) {
            const style = document.createElement('style');
            style.id = 'l10n-skeleton-styles';
            style.textContent = `
                :root {
                    --skeleton-back: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%) !important;
                }

                .l10n-skeleton {
                    display: flex !important;
                    background: var(--skeleton-back);
                    border-radius: 3px !important;
                }

                .l10n-placeholder-skeleton::placeholder {
                    background: var(--skeleton-back);
                    border-radius: 3px !important;
                }
            `;
            document.head.appendChild(style);
        }
    }

    hideSkeletonLoaders() {
        const skeletonElements = document.querySelectorAll('.l10n-skeleton');
        skeletonElements.forEach(skeleton => {
            skeleton.remove();
        });

        const elements = document.querySelectorAll('[l10n][data-original-content]');
        elements.forEach(element => {
            const originalContent = element.getAttribute('data-original-content');
            element.innerHTML = originalContent;
            element.removeAttribute('data-original-content');
        });

        const placeholderElements = document.querySelectorAll('[l10n-placeholder].l10n-placeholder-skeleton');
        placeholderElements.forEach(element => {
            const originalPlaceholder = element.getAttribute('data-original-placeholder');
            element.placeholder = originalPlaceholder || '';
            element.classList.remove('l10n-placeholder-skeleton');
            element.removeAttribute('data-original-placeholder');
        });
    }

    updateContent() {
        this.hideSkeletonLoaders();
        const elements = document.querySelectorAll('[l10n]');
        elements.forEach(element => {
            const key = element.getAttribute('l10n');
            const translation = this.translate(key);
            if (translation !== undefined) {
                const textNode = Array.from(element.childNodes).find(node => node.nodeType === Node.TEXT_NODE);
                if (textNode) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = translation;
                    const fragment = document.createDocumentFragment();
                    while (tempDiv.firstChild) {
                        fragment.appendChild(tempDiv.firstChild);
                    }
                    textNode.parentNode.replaceChild(fragment, textNode);
                } else {
                    element.prepend(document.createTextNode(translation.replace(/<br>/g, '\n')));
                }
            }
        });

        // Добавляем перевод для placeholder
        const placeholderElements = document.querySelectorAll('[l10n-placeholder]');
        placeholderElements.forEach(element => {
            const key = element.getAttribute('l10n-placeholder');
            const translation = this.translate(key);
            if (translation !== undefined) {
                element.placeholder = translation;
            }
        });
    }

    clearCache() {
        localStorage.removeItem(`${this.cacheKey}-${this.currentLang}`);
    }
}
