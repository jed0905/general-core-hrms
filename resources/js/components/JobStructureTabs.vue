<template>
  <div class="mb-10 d-flex gap-5">
    <Link
      :href="route('hrmanagement.jobstructure.jobstatus.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'jobStatus' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'jobStatus')"
        :text-color="
          activeTab === 'jobStatus' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Job Status
      </v-btn>
    </Link>

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
          activeTab === 'salary' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'salary')"
        :text-color="
          activeTab === 'salary' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Salary
      </v-btn>
    </Link>

    <v-menu :offset="[10, 0]">
      <template #activator="{ props }">
        <v-btn
          :color="
            activeTab === 'workShift'
              ? 'light-green-lighten-4'
              : 'grey-lighten-3'
          "
          class="text-none rounded-lg"
          v-bind="props"
          min-width="120"
        >
          Work Shift
          <v-icon end>mdi-menu-down</v-icon>
        </v-btn>
      </template>

      <v-list>
        <!-- Mobile-friendly nested dropdown -->
        <v-list-item>
          <Link
            :href="route('hrmanagement.jobstructure.dailyShiftSchedules.index')"
            class="w-full"
          >
            <v-list-item-title>Daily Shift Schedules</v-list-item-title>
          </Link>
        </v-list-item>

        <v-list-item>
          <Link
            :href="
              route('hrmanagement.jobstructure.weeklyShiftTemplates.index')
            "
            class="w-full"
          >
            <v-list-item-title>Weekly Shift Templates</v-list-item-title>
          </Link>
        </v-list-item>
      </v-list>
    </v-menu>

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
