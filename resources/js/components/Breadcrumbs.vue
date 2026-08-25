<template>
  <ul class="v-breadcrumbs v-breadcrumbs--density-default">
    <template v-for="(item, i) in items" :key="i">
      <Link :href="item.href" 
        class="v-breadcrumbs-item v-breadcrumbs-item--link"
        :class="item.disabled ? 'v-breadcrumbs-item--disabled' : ''" v-text="item.title">
      </Link>
      <li class="v-breadcrumbs-divider" v-if="i != (items.length-1)">
        <i class="mdi-chevron-right mdi v-icon notranslate v-theme--light v-icon--size-default"></i>
      </li>
    </template>
  </ul>
</template>

<script>
export default {
  props: {
    items: Object
  }
}
</script>

<style>

</style>