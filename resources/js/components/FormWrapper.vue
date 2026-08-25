<template>
  <v-row>
    <!-- <v-col cols="12" sm="6" md="6" lg="6" xl="6">
      <v-card-title>{{ name }}</v-card-title>
      <v-card-subtitle>{{ description }}</v-card-subtitle>
    </v-col> -->
    <v-col cols="12">
      <v-card class="px-5 py-5" rounded="lg">
        <v-card-text>
          <slot />
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>
</template>
<script>
  export default {
    props: {
      name: {
        type: String,
        required: true,
      },
      description: {
        type: String,
        required: true,
      },
    },
  }
</script>