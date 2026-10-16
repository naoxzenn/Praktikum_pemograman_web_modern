
<script setup lang="ts">
withDefaults(defineProps<{
  type?: 'success' | 'error' | 'info'
  message: string
}>(), {
  type: 'info',
})
</script>

<template>
  <div
    class="app-alert"
    :class="`alert-${type}`"
    role="alert"
  >
    {{ message }}
  </div>
</template>

<style scoped>
.app-alert {
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 14px;
  line-height: 1.5;
  border: 1px solid transparent;
}

.alert-success {
  color: #206b48;
  background: #dcf5e7;
  border-color: #b7e8ca;
}

.alert-error {
  color: #a12f35;
  background: #fde5e5;
  border-color: #f5c2c2;
}

.alert-info {
  color: #2058a0;
  background: #e2edff;
  border-color: #c4d9ff;
}
</style>
