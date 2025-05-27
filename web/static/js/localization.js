let localization, lang;

class Localization {
    constructor() {
        this.supportedLanguages = ['en', 'ru', 'es', 'de'];
        const browserLang = navigator.language.substring(0, 2).toLowerCase();
        const defaultLang = this.supportedLanguages.includes(browserLang) ? browserLang : 'en';
        this.currentLang = localStorage.getItem('language') || defaultLang;
        this.translations = {};
        this.cacheKey = 'translations-cache';
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
        }
    }

    async setLang(lang) {
        if (!this.supportedLanguages.includes(lang)) {
            console.error('Unsupported language:', lang);
            return;
        }

        document.documentElement.lang = lang;
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

    updateContent() {
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
