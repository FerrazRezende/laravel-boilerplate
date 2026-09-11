import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'

export interface LocaleOption {
  code: string
  label: string
  flag: string
}

export function useLang() {
  const page = usePage()

  const locale = computed(() => (page.props.locale as string) || 'pt')

  const translations = computed(() => {
    return (page.props.translations || {}) as Record<string, string>
  })

  const availableLocales: LocaleOption[] = [
    { code: 'pt', label: 'Português', flag: '🇧🇷' },
    { code: 'en', label: 'English', flag: '🇺🇸' },
    { code: 'es', label: 'Español', flag: '🇪🇸' },
  ]

  const setLocale = (newLocale: string) => {
    // Check if user is authenticated
    const isAuthenticated = !!page.props.auth?.user

    if (isAuthenticated) {
      // Authenticated users: save to database via profile route
      router.patch(route('profile.locale.update'), { locale: newLocale })
    } else {
      // Guests: save to session via public route
      // Use fetch directly for guests since Inertia router doesn't return Promise
      fetch('/locale', {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({ locale: newLocale }),
      }).then(() => {
        // Force page reload to apply new locale
        window.location.reload()
      })
    }
  }

  const currentLocale = computed(() =>
    availableLocales.find((l) => l.code === locale.value)
  )

  return {
    locale,
    translations,
    availableLocales,
    currentLocale,
    setLocale,
  }
}

// `__` and `transChoice` live in utils/lang: that is the copy app.js registers
// as the global template helper, so re-exporting keeps one implementation
// while both import paths in the codebase keep working.
export { __, transChoice, type TranslationParams } from '@/utils/lang'
