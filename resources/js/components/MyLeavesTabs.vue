<template>
  <div class="mb-10 d-flex gap-5">
    <Link
      :href="route('self-service.my-leaves.apply')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'apply'
            ? 'light-green-lighten-4'
            : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'apply')"
        :text-color="
          activeTab === 'apply'
            ? 'light-green-darken-4'
            : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Apply
      </v-btn>
    </Link>

    <Link
      :href="route('self-service.my-leaves.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'index' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'index')"
        :text-color="
          activeTab === 'index' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Leave Applications
      </v-btn>
    </Link>

    <Link
      :href="route('self-service.my-leaves.entitlements')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'entitlements'
            ? 'light-green-lighten-4'
            : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'entitlements')"
        :text-color="
          activeTab === 'entitlements'
            ? 'light-green-darken-4'
            : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Entitlements
      </v-btn>
    </Link>

    <!-- <v-btn
      :color="
        activeTab === 'reports' ? 'light-green-lighten-4' : 'grey-lighten-3'
      "
      @click="$emit('update:activeTab', 'reports')"
      :text-color="
        activeTab === 'reports' ? 'light-green-darken-4' : 'grey-darken-2'
      "
      class="text-none rounded-lg"
      min-width="120"
    >
      Reports
    </v-btn> -->
  </div>
</template>

<script>
export default {
  name: "MyLeavesTabs",
  props: {
    activeTab: {
      type: String,
      required: true,
    },
  },
};
</script>
