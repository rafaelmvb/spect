import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Returns the member area base path for building URLs.
 * - "/area" when using clean routes (/area/comunidade)
 * - "/m/{slug}" when using path-based slug routes
 * - "" when using subdomain/custom-domain routes
 *
 * Falls back to "/m/{slug}" if the shared prop isn't available.
 */
export function useMemberBase(slugFallback) {
    const page = usePage();
    return computed(() => {
        const mb = page.props?.member_base;
        if (mb !== undefined && mb !== null) return mb;
        const slug = typeof slugFallback === 'object' ? slugFallback?.value : slugFallback;
        return slug ? `/m/${slug}` : '';
    });
}
