<template>
  <v-btn 
    color="yellow-darken-3"  
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
