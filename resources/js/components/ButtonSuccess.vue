<template>
  <v-btn 
    class="text-white starbucks-green" 
    min-width="120" 
    rounded="xl"
    elevation="3"
    @click="$emit('click')"
  >
    {{ name }}
  </v-btn>
</template>

<script>
export default {
  emits: ['click'],
  props: {
    name: {
      type: String,
      required: true,
    },
  }
}
</script>

<style>

</style>
