<template>
  <div class="mb-10 d-flex gap-5">
    <Link
      :href="route('hrmanagement.dailytimerecord.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'list'
            ? 'light-green-lighten-4'
            : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'list')"
        :text-color="
          activeTab === 'list'
            ? 'light-green-darken-4'
            : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Employee List
      </v-btn>
    </Link>
    <Link
      :href="route('hrmanagement.dailytimerecord.employeeWorkShifts.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'shifts'
            ? 'light-green-lighten-4'
            : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'shifts')"
        :text-color="
          activeTab === 'shifts'
            ? 'light-green-darken-4'
            : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Work Shift
      </v-btn>
    </Link>

    <Link
      :href="route('hrmanagement.dailytimerecord.attendanceLogEventTypes.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'eventTypes'
            ? 'light-green-lighten-4'
            : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'eventTypes')"
        :text-color="
          activeTab === 'eventTypes'
            ? 'light-green-darken-4'
            : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="160"
      >
        Event Types
      </v-btn>
    </Link>
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
