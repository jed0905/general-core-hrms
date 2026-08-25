<template>
  <div class="mb-10 d-flex gap-5">
    <template v-for="tab in tabs" :key="tab.key">
      <!-- If tab has a route, use Link component -->
      <Link
        v-if="tab.route"
        :href="route(tab.route)"
        class="text-decoration-none"
      >
        <v-btn
          :color="
            activeTab === tab.key ? 'light-green-lighten-4' : 'grey-lighten-3'
          "
          @click="$emit('update:activeTab', tab.key)"
          :text-color="
            activeTab === tab.key ? 'light-green-darken-4' : 'grey-darken-2'
          "
          class="text-none rounded-lg"
          min-width="120"
        >
          {{ tab.label }}
        </v-btn>
      </Link>

      <!-- If tab doesn't have a route, use regular button -->
      <v-btn
        v-else
        :color="
          activeTab === tab.key ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', tab.key)"
        :text-color="
          activeTab === tab.key ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        {{ tab.label }}
      </v-btn>
    </template>
  </div>
</template>

<script>
export default {
  name: "DynamicTabs",
  props: {
    activeTab: {
      type: String,
      required: true,
    },
    tabs: {
      type: Array,
      required: true,
      // Each tab should have: { key: string, label: string, route?: string }
      // route is optional - if not provided, it will be a regular button
    },
  },
  emits: ['update:activeTab'],
};
</script> 