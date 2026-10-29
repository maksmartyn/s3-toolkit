<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { useId } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();

// Уникальный id маски: иконка встречается на странице несколько раз
const maskId = `app-logo-${useId().replace(/\W/g, '')}`;
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        :class="className"
        v-bind="$attrs"
    >
        <mask
            :id="maskId"
            maskUnits="userSpaceOnUse"
            x="0"
            y="0"
            width="32"
            height="32"
        >
            <path
                d="M2 6L5.2 26.5C5.5 29 26.5 29 26.8 26.5L30 6Z"
                fill="white"
            />
            <ellipse cx="16" cy="6" rx="14" ry="4.5" fill="white" />
            <ellipse cx="16" cy="6" rx="11.6" ry="2.8" fill="black" />

            <g
                transform="translate(16 18) rotate(45) scale(0.9) translate(-16 -16)"
                stroke-linejoin="round"
            >
                <g fill="black" stroke="black" stroke-width="2.8">
                    <rect x="14.4" y="10" width="3.2" height="18" rx="1.6" />
                    <path
                        d="M14.6 4.04V8.2H17.4V4.04A4.2 4.2 0 1 1 14.6 4.04Z"
                    />
                </g>
                <g fill="white">
                    <rect x="14.4" y="10" width="3.2" height="18" rx="1.6" />
                    <path
                        d="M14.6 4.04V8.2H17.4V4.04A4.2 4.2 0 1 1 14.6 4.04Z"
                    />
                </g>
            </g>
            <g
                transform="translate(16 18) rotate(-45) scale(0.9) translate(-16 -16)"
                stroke-linejoin="round"
            >
                <g fill="black" stroke="black" stroke-width="2.8">
                    <rect x="13.8" y="3" width="4.4" height="10" rx="1.8" />
                    <rect x="15.2" y="12" width="1.6" height="13" />
                    <path d="M15.2 24H16.8L16.6 28H15.4Z" />
                </g>
                <g fill="white">
                    <rect x="13.8" y="3" width="4.4" height="10" rx="1.8" />
                    <rect x="15.2" y="12" width="1.6" height="13" />
                    <path d="M15.2 24H16.8L16.6 28H15.4Z" />
                </g>
            </g>
        </mask>
        <rect
            width="32"
            height="32"
            fill="currentColor"
            :mask="`url(#${maskId})`"
        />
    </svg>
</template>
