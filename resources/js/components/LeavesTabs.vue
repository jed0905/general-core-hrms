<template>
  <div class="mb-10 d-flex gap-5">
    <v-menu :offset="[10, 0]">
      <template #activator="{ props }">
        <v-btn
          :color="
            activeTab === 'entitlements'
              ? 'light-green-lighten-4'
              : 'grey-lighten-3'
          "
          :text-color="
            activeTab === 'entitlements'
              ? 'light-green-darken-4'
              : 'grey-darken-2'
          "
          class="text-none rounded-lg"
          v-bind="props"
          @click="$emit('update:activeTab', 'entitlements')"
          min-width="120"
        >
          Entitlements
          <v-icon end>mdi-menu-down</v-icon>
        </v-btn>
      </template>

      <v-list>
        <Link
          :href="route('hrmanagement.leave.addLeaveEntitlements')"
          class="text-decoration-none"
        >
          <v-list-item>
            <v-list-item-title>Add Entitlements</v-list-item-title>
          </v-list-item>
        </Link>

        <Link
          :href="route('hrmanagement.leave.viewLeaveEntitlements')"
          class="text-decoration-none"
        >
          <v-list-item>
            <v-list-item-title>Employee Entitlements</v-list-item-title>
          </v-list-item>
        </Link>
      </v-list>
    </v-menu>

    <Link
      :href="route('hrmanagement.jobstructure.position.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'position' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'position')"
        :text-color="
          activeTab === 'position' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Positions
      </v-btn>
    </Link>

    <Link
      :href="route('hrmanagement.jobstructure.designation.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'designation'
            ? 'light-green-lighten-4'
            : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'designation')"
        :text-color="
          activeTab === 'designation' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Designations
      </v-btn>
    </Link>

    <Link
      :href="route('hrmanagement.jobstructure.salary.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'Salary' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'Salary')"
        :text-color="
          activeTab === 'Salary' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Salary
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
  name: "JobStructureTabs",
  props: {
    activeTab: {
      type: String,
      required: true,
    },
  },
};
</script>
