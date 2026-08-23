import { useDark, useToggle } from '@vueuse/core';

export function useDarkMode() {
    const isDark = useDark({
        selector: 'html',
        attribute: 'class',
        valueDark: 'dark',
        valueLight: '',
    });
    const toggle = useToggle(isDark);
    // `@click="toggleDark"` in templates passes the native MouseEvent as an
    // argument; useToggle() treats any argument as an explicit value to set
    // rather than a request to flip, which would always force dark mode on.
    const toggleDark = () => toggle();

    return { isDark, toggleDark };
}
