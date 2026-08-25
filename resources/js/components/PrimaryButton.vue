<template>
  <v-btn min-width="120" color="primary" class="text-white">{{ name }}</v-btn>
</template>
<script>
  export default {
    props: {
      name: {
        type: String,
        required: true,
      },
    },
  }
</script>
