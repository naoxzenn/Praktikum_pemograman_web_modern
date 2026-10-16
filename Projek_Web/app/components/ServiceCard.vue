
<script setup lang="ts">
defineProps<{
  nama: string
  deskripsi?: string
}>()
</script>

<template>
  <article class="service-card">
    <div class="service-icon">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="24"
        height="24"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
        <path d="M14 2v6h6" />
        <path d="M8 13h8M8 17h8" />
      </svg>
    </div>

    <h3>{{ nama }}</h3>

    <p v-if="deskripsi">
      {{ deskripsi }}
    </p>

    <slot />
  </article>
</template>

<style scoped>
.service-card {
  padding: 22px;
  border: 1px solid #dce5df;
  border-radius: 12px;
  background: #fff;
  transition:
    border-color 0.2s ease,
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.service-card:hover {
  border-color: #8db8a5;
  transform: translateY(-2px);
  box-shadow: 0 8px 22px rgb(16 70 56 / 7%);
}

.service-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 46px;
  height: 46px;
  margin-bottom: 16px;
  border-radius: 10px;
  color: #104638;
  background: #e8f3ed;
}

h3 {
  margin: 0 0 8px;
  color: #17231f;
  font-size: 17px;
  font-weight: 650;
}

p {
  margin: 0;
  color: #65736b;
  font-size: 14px;
  line-height: 1.7;
}
</style>
