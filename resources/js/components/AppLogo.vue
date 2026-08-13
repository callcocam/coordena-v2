<script setup lang="ts">
// Marca Coordena para o shell autenticado. O glifo é auto-contido (quadrado
// teal + nós + centro âmbar), então basta dimensioná-lo — sem fundo colorido em
// volta. A estrutura preserva o colapso do sidebar (glifo à esquerda, wordmark
// clipado no modo ícone).
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <AppLogoIcon class="size-8 rounded-md shadow-sm" />
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span
            class="mb-0.5 truncate leading-tight font-extrabold tracking-tight"
        >
            Coordena
        </span>
    </div>
</template>
