<script setup>
import { computed } from 'vue';
import MemberAreaAppLayout from '@/Layouts/MemberAreaAppLayout.vue';
import { ShoppingBag, CheckCircle } from 'lucide-vue-next';

defineOptions({ layout: MemberAreaAppLayout });

const props = defineProps({
    product:          { type: Object, required: true },
    config:           { type: Object, default: () => ({}) },
    catalog_products: { type: Array,  default: () => [] },
    items:            { type: Array,  default: () => [] },
    categories:       { type: Array,  default: () => [] },
    slug:             { type: String, required: true },
    base_url:         { type: String, default: '' },
});

const primary = computed(() => props.config?.theme?.primary || '#6FCF00');
</script>

<template>
    <div class="ex-root">

        <!-- Cabeçalho -->
        <div class="ex-header">
            <p class="ex-eyebrow">explorar</p>
            <h1 class="ex-title">Descubra mais trilhas</h1>
            <p class="ex-sub">Outros produtos disponíveis para você adquirir.</p>
        </div>

        <!-- Sem produtos -->
        <div v-if="!catalog_products.length" class="ex-empty">
            <ShoppingBag class="ex-empty__icon" />
            <p class="ex-empty__title">Nenhum produto disponível no momento.</p>
            <p class="ex-empty__text">Novos conteúdos serão listados aqui quando estiverem disponíveis.</p>
        </div>

        <!-- Grid de produtos -->
        <div v-else class="ex-grid">
            <component
                :is="item.is_owned ? 'a' : 'a'"
                v-for="item in catalog_products"
                :key="item.id"
                :href="item.is_owned ? item.area_url : item.checkout_url"
                :target="item.is_owned ? '_self' : '_blank'"
                :rel="item.is_owned ? undefined : 'noopener'"
                class="ex-card"
                :class="{ 'ex-card--owned': item.is_owned }"
            >
                <!-- Badge matriculado -->
                <div v-if="item.is_owned" class="ex-card__badge">
                    <CheckCircle style="width:13px; height:13px;" />
                    {{ item.is_current ? 'Trilha atual' : 'Matriculado' }}
                </div>

                <!-- Capa -->
                <div class="ex-card__cover">
                    <img
                        v-if="item.image"
                        :src="item.image"
                        :alt="item.name"
                        class="ex-card__img"
                    />
                    <div v-else class="ex-card__placeholder">
                        <ShoppingBag style="width:32px; height:32px; opacity:0.25;" />
                    </div>
                </div>

                <!-- Conteúdo -->
                <div class="ex-card__body">
                    <h2 class="ex-card__name">{{ item.name }}</h2>

                    <p v-if="item.description" class="ex-card__desc">
                        {{ item.description }}
                    </p>

                    <div class="ex-card__footer">
                        <span v-if="item.is_owned" class="ex-card__price ex-card__price--free">
                            Acesso liberado
                        </span>
                        <span v-else-if="item.price" class="ex-card__price">
                            R$ {{ item.price }}
                        </span>
                        <span v-else class="ex-card__price ex-card__price--free">Gratuito</span>

                        <span
                            class="ex-card__cta"
                            :style="item.is_owned
                                ? { background: 'rgba(111,207,0,0.15)', color: primary }
                                : { background: primary, color: '#060D14' }"
                        >
                            {{ item.is_owned ? 'Acessar' : 'Comprar' }}
                        </span>
                    </div>
                </div>
            </component>
        </div>
    </div>
</template>

<style scoped>
.ex-root {
    padding: 20px 20px 48px;
    max-width: 960px;
    margin: 0 auto;
    font-family: 'Cormorant Garamond', Georgia, serif;
}

/* Header */
.ex-header { padding: 8px 0 32px; }

.ex-eyebrow {
    font-family: 'DM Mono', monospace;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: var(--ma-text-2);
    margin: 0 0 8px;
}
.ex-title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: clamp(28px, 4vw, 40px);
    font-weight: 600;
    color: var(--ma-text);
    margin: 0 0 10px;
    line-height: 1.1;
}
.ex-sub {
    font-size: 17px;
    color: var(--ma-text-2);
    margin: 0;
    line-height: 1.5;
}

/* Empty */
.ex-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 80px 24px;
    text-align: center;
    color: var(--ma-text-2);
}
.ex-empty__icon {
    width: 48px;
    height: 48px;
    opacity: 0.2;
    margin-bottom: 18px;
}
.ex-empty__title {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 20px;
    font-weight: 600;
    color: var(--ma-text);
    margin: 0 0 8px;
}
.ex-empty__text {
    font-size: 15px;
    color: var(--ma-text-2);
    margin: 0;
    max-width: 340px;
}

/* Grid */
.ex-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

/* Card */
.ex-card {
    display: flex;
    flex-direction: column;
    background: var(--ma-surface);
    border: 1px solid var(--ma-border);
    border-radius: 16px;
    overflow: hidden;
    text-decoration: none;
    transition: border-color 0.2s, transform 0.15s, box-shadow 0.2s;
    position: relative;
}
.ex-card:hover {
    border-color: rgba(111, 207, 0, 0.35);
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
}
.ex-card--owned {
    border-color: rgba(111, 207, 0, 0.2);
}

.ex-card__badge {
    position: absolute;
    top: 10px;
    left: 10px;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(6, 13, 20, 0.82);
    color: #6FCF00;
    font-family: 'DM Mono', monospace;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    padding: 4px 9px;
    border-radius: 20px;
    backdrop-filter: blur(6px);
}

/* Capa */
.ex-card__cover {
    width: 100%;
    aspect-ratio: 16/9;
    overflow: hidden;
    background: var(--ma-surface-2, #1A3040);
    flex-shrink: 0;
}
.ex-card__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.3s;
}
.ex-card:hover .ex-card__img { transform: scale(1.03); }

.ex-card__placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--ma-text-2);
}

/* Body */
.ex-card__body {
    display: flex;
    flex-direction: column;
    flex: 1;
    padding: 20px;
    gap: 10px;
}

.ex-card__name {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 20px;
    font-weight: 600;
    color: var(--ma-text);
    margin: 0;
    line-height: 1.2;
}

.ex-card__desc {
    font-size: 15px;
    color: var(--ma-text-2);
    margin: 0;
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Footer: preço + CTA */
.ex-card__footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: auto;
    padding-top: 14px;
    border-top: 1px solid var(--ma-border);
}

.ex-card__price {
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 22px;
    font-weight: 700;
    color: var(--ma-text);
}
.ex-card__price--free {
    font-size: 16px;
    font-weight: 500;
    color: var(--ma-text-2);
    font-style: italic;
}

.ex-card__cta {
    display: inline-flex;
    align-items: center;
    padding: 8px 18px;
    border-radius: 8px;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 0.03em;
    white-space: nowrap;
    transition: opacity 0.15s;
}
.ex-card:hover .ex-card__cta { opacity: 0.85; }

@media (max-width: 600px) {
    .ex-root { padding: 16px 16px 48px; }
    .ex-grid { grid-template-columns: 1fr; }
}
</style>
