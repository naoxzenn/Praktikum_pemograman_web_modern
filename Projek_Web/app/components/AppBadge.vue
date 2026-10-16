
<script setup lang="ts">
type StatusPengajuan =
  | 'Menunggu'
  | 'Diproses'
  | 'Selesai'
  | 'Ditolak'

defineProps<{
  status: StatusPengajuan
}>()

const classStatus: Record<StatusPengajuan, string> = {
  Menunggu: 'badge-menunggu',
  Diproses: 'badge-diproses',
  Selesai: 'badge-selesai',
  Ditolak: 'badge-ditolak',
}
</script>

<template>
  <span
    class="app-badge"
    :class="classStatus[status]"
  >
    {{ status }}
  </span>
</template>

<style scoped>
.app-badge {
  display: inline-flex;
  align-items: center;
  padding: 5px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  white-space: nowrap;
}

.badge-menunggu {
  color: #855b12;
  background: #fff1c7;
}

.badge-diproses {
  color: #2058a0;
  background: #e2edff;
}

.badge-selesai {
  color: #206b48;
  background: #dcf5e7;
}

.badge-ditolak {
  color: #a12f35;
  background: #fde5e5;
}
</style>
