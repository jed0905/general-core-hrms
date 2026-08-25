<template>
  <v-btn color="red-darken-1" class="text-white" min-width="120" rounded="xl">
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
