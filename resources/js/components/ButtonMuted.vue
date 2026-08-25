<template>
  <v-btn 
    color="grey-lighten-4" 
    min-width="120" 
    rounded="xl" 
    elevation="3">
    {{ name }}
  </v-btn>
</template>

<script>
export default {

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
