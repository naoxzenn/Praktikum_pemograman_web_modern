
<script setup lang="ts">
withDefaults(defineProps<{
  type?: 'button' | 'submit' | 'reset'
  variant?: 'primary' | 'secondary'
  loading?: boolean
  disabled?: boolean
}>(), {
  type: 'button',
  variant: 'primary',
  loading: false,
  disabled: false,
})
</script>

<template>
  <button
    v-bind="$attrs"
    :type="type"
    class="app-button"
    :class="`button-${variant}`"
    :disabled="disabled || loading"
  >
    <span v-if="loading">Memproses...</span>
    <slot v-else />
  </button>
</template>

<style scoped>
.app-button {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  padding: 11px 20px;
  border: 1px solid transparent;
  border-radius: 7px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.button-primary {
  color: #fff;
  background: #104638;
}

.button-primary:hover:not(:disabled) {
  background: #0b362b;
}

.button-secondary {
  color: #104638;
  background: #fff;
  border-color: #cbd5d1;
}

.button-secondary:hover:not(:disabled) {
  background: #f2f6f4;
}

.app-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
