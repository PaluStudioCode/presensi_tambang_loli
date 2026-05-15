const THEME_LIGHT = 'light';
const THEME_DARK = 'dark';

const initializeTheme = () => {
    if (typeof document === 'undefined') {
        return THEME_LIGHT;
    }

    document.documentElement.classList.remove(THEME_DARK);
    document.documentElement.classList.add(THEME_LIGHT);
    document.documentElement.style.colorScheme = THEME_LIGHT;

    if (typeof window !== 'undefined') {
        window.localStorage.removeItem('theme');
    }

    return THEME_LIGHT;
};

export { initializeTheme };
