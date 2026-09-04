const styleKey = 'chessboard.style';
const textSizeKey = 'chessboard.textSize';
const textWeightKey = 'chessboard.textWeight';
const textSizes = ['small', 'compact', 'normal', 'large', 'xlarge'];
const legacyStyles = new Map([
    ['classic', 'checkmate'],
    ['clean', 'bishop'],
    ['blue', 'knight'],
    ['dark', 'midnight-mate'],
]);

const readStoredValue = (key) => {
    try {
        return localStorage.getItem(key);
    } catch {
        return null;
    }
};

const storeValue = (key, value) => {
    try {
        localStorage.setItem(key, value);
    } catch {
        // The choice still applies for the current page.
    }
};

export const initializePreferences = () => {
    const styleSelect = document.querySelector('#style-switcher');
    const themeStylesheet = document.querySelector('#theme-stylesheet');
    const smallerButton = document.querySelector('#text-smaller');
    const largerButton = document.querySelector('#text-larger');
    const weightButton = document.querySelector('#text-weight');
    const readingStatus = document.querySelector('#reading-status');

    const allowedStyles = styleSelect instanceof HTMLSelectElement
        ? new Set(Array.from(styleSelect.options, (option) => option.value))
        : new Set(['checkmate']);

    const announceReadingChoice = (message) => {
        if (readingStatus instanceof HTMLElement) {
            readingStatus.textContent = message;
        }
    };

    const applyStyle = (style) => {
        const nextStyle = allowedStyles.has(style) ? style : 'checkmate';
        document.documentElement.dataset.style = nextStyle;

        if (styleSelect instanceof HTMLSelectElement) {
            styleSelect.value = nextStyle;
        }

        if (themeStylesheet instanceof HTMLLinkElement) {
            const themeBase = themeStylesheet.dataset.themeBase ?? '/assets/css/themes';
            themeStylesheet.href = `${themeBase}/${encodeURIComponent(nextStyle)}.css`;
        }

        storeValue(styleKey, nextStyle);
    };

    let selectedStyle = readStoredValue(styleKey) ?? 'checkmate';
    selectedStyle = legacyStyles.get(selectedStyle) ?? selectedStyle;
    applyStyle(selectedStyle);

    if (styleSelect instanceof HTMLSelectElement) {
        styleSelect.addEventListener('change', () => applyStyle(styleSelect.value));
    }

    const applyTextSize = (size, announce = false) => {
        const nextSize = textSizes.includes(size) ? size : 'normal';
        const index = textSizes.indexOf(nextSize);
        document.documentElement.dataset.textSize = nextSize;
        storeValue(textSizeKey, nextSize);

        if (smallerButton instanceof HTMLButtonElement) {
            smallerButton.disabled = index === 0;
        }
        if (largerButton instanceof HTMLButtonElement) {
            largerButton.disabled = index === textSizes.length - 1;
        }
        if (announce) {
            announceReadingChoice(`Text size: ${nextSize}`);
        }
    };

    let selectedTextSize = readStoredValue(textSizeKey) ?? 'normal';
    if (!textSizes.includes(selectedTextSize)) {
        selectedTextSize = 'normal';
    }
    applyTextSize(selectedTextSize);

    if (smallerButton instanceof HTMLButtonElement) {
        smallerButton.addEventListener('click', () => {
            const current = textSizes.indexOf(document.documentElement.dataset.textSize ?? 'normal');
            applyTextSize(textSizes[Math.max(0, current - 1)], true);
        });
    }

    if (largerButton instanceof HTMLButtonElement) {
        largerButton.addEventListener('click', () => {
            const current = textSizes.indexOf(document.documentElement.dataset.textSize ?? 'normal');
            applyTextSize(textSizes[Math.min(textSizes.length - 1, current + 1)], true);
        });
    }

    let selectedTextWeight = readStoredValue(textWeightKey) === 'strong' ? 'strong' : 'normal';
    const applyTextWeight = (weight, announce = false) => {
        selectedTextWeight = weight === 'strong' ? 'strong' : 'normal';
        document.documentElement.dataset.textWeight = selectedTextWeight;
        storeValue(textWeightKey, selectedTextWeight);

        if (weightButton instanceof HTMLButtonElement) {
            weightButton.setAttribute('aria-pressed', selectedTextWeight === 'strong' ? 'true' : 'false');
        }
        if (announce) {
            announceReadingChoice(selectedTextWeight === 'strong' ? 'Thicker text on' : 'Thicker text off');
        }
    };

    applyTextWeight(selectedTextWeight);
    if (weightButton instanceof HTMLButtonElement) {
        weightButton.addEventListener('click', () => {
            applyTextWeight(selectedTextWeight === 'strong' ? 'normal' : 'strong', true);
        });
    }
};
