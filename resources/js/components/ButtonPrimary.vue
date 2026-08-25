<template>
  <v-btn color="blue-darken-1" min-width="120" rounded="xl" elevation="3">
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
