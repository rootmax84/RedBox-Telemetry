let localization, lang, tt;

class Localization {
    constructor() {
        this.currentLang = localStorage.getItem('language') || 'en';
        this.translations = {};
        this.cacheKey = 'translations-cache';
        this.loadTranslations();
        lang = this.currentLang;
        tt = JSON.parse(localStorage.getItem(`translations-cache-${lang}`));
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
        if (lang !== 'en' && lang !== 'ru' && lang !== 'es' && lang !== 'de') {
            console.error('Unsupported language');
            return;
        }

        const keysToRemove = Object.keys(localStorage).filter(key => key.startsWith('translations-'));
        keysToRemove.forEach(key => localStorage.removeItem(key));

        this.currentLang = lang;
        localStorage.setItem('language', lang);
        await this.fetchAndCacheTranslations();

        tt = JSON.parse(localStorage.getItem(`translations-cache-${lang}`));
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
                    textNode.nodeValue = tempDiv.textContent;
                    textNode.parentNode.replaceChild(document.createTextNode(tempDiv.innerHTML), textNode);
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
