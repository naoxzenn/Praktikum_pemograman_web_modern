
<script setup lang="ts">
defineProps<{
  label: string
  modelValue: string
  type?: string
  placeholder?: string
  required?: boolean
  disabled?: boolean
  error?: string
  name?: string
  autocomplete?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <div class="app-field">
    <label v-if="label" :for="name">
      {{ label }}
      <span v-if="required" class="required-mark">*</span>
    </label>

    <input
      :id="name"
      :name="name"
      :type="type || 'text'"
      :value="modelValue"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :aria-invalid="!!error"
      :autocomplete="autocomplete"
      @input="emit(
        'update:modelValue',
        ($event.target as HTMLInputElement).value
      )"
    />

    <p v-if="error" class="field-error">
      {{ error }}
    </p>
  </div>
</template>

<style scoped>
.app-field {
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  margin-bottom: 18px;
}

label {
  font-size: 14px;
  font-weight: 500;
  color: #17231f;
  line-height: 1.5;
}

.required-mark {
  color: #c0392b;
}

input {
  box-sizing: border-box;
  width: 100%;
  min-height: 46px;
  padding: 11px 14px;
  border: 1px solid #cbd5d1;
  border-radius: 7px;
  background: #fff;
  color: #17231f;
  font: inherit;
}

input:focus {
  outline: 2px solid #78b49d;
  outline-offset: 2px;
}

input:disabled {
  background: #f2f4f3;
  cursor: not-allowed;
}

.field-error {
  margin: 0;
  color: #a12f35;
  font-size: 12px;
}


label {
  font-size: 14px;
  font-weight: 500;
  color: #17231f;
}

.required-mark {
  color: #c0392b;
}

input {
  box-sizing: border-box;
  width: 100%;
  min-height: 44px;
  padding: 10px 13px;
  border: 1px solid #cbd5d1;
  border-radius: 7px;
  background: #fff;
  color: #17231f;
  font: inherit;
}

input:focus {
  outline: 2px solid #78b49d;
  outline-offset: 2px;
}

input:disabled {
  background: #f2f4f3;
  cursor: not-allowed;
}

.field-error {
  margin: 0;
  color: #a12f35;
  font-size: 12px;
}
</style>
