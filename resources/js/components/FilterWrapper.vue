<template>
  <v-expansion-panels class="mt-3">
    <v-expansion-panel title="Filters" rounded="lg" elevation="4" class="mb-3">
      <v-expansion-panel-text>
        <slot />
      </v-expansion-panel-text>
    </v-expansion-panel>
  </v-expansion-panels>
</template>

<script>
export default {};
</script>

<style>
</style>
