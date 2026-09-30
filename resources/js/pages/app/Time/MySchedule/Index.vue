<template>
  <SidebarLayout>
    <Head title="My Schedule" />

    <v-container fluid class="pa-4 pa-sm-6">
      <div class="mb-6">
        <h1 class="text-h5 font-weight-bold">My Schedule</h1>
        <p class="text-body-2 text-medium-emphasis">
          Work schedules assigned to you, current and past.
        </p>
      </div>

      <v-alert v-if="!hasEmployeeRecord" type="info" variant="tonal">
        Your account is not linked to an employee record. Contact HR if this is
        unexpected.
      </v-alert>

      <v-alert v-else-if="!schedules.length" type="info" variant="tonal">
        No work schedule has been assigned to you yet.
      </v-alert>

      <template v-else>
      <v-card
        v-for="assignment in schedules"
        :key="assignment.id"
        variant="outlined"
        class="rounded-lg mb-4"
      >
        <v-card-title class="d-flex flex-wrap align-center ga-2">
          <span class="font-weight-bold">
            {{ assignment.work_schedule?.name }}
          </span>
          <v-chip size="small" variant="outlined">
            {{ assignment.work_schedule?.code }}
          </v-chip>
          <v-chip v-if="assignment.is_current" size="small" color="success" variant="tonal">
            Current
          </v-chip>
          <v-chip v-if="assignment.is_primary" size="small" color="primary" variant="tonal">
            Primary
          </v-chip>
        </v-card-title>
        <v-card-subtitle>
          {{ formatDate(assignment.effective_from) }} –
          {{ assignment.effective_to ? formatDate(assignment.effective_to) : "ongoing" }}
        </v-card-subtitle>
        <v-card-text>
          <v-table density="compact">
            <thead>
              <tr>
                <th>Day</th>
                <th>Shift</th>
                <th>Hours</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="day in assignment.work_schedule?.days || []" :key="day.id">
                <td class="font-weight-medium">{{ dayName(day.day_of_week) }}</td>
                <td>
                  <span v-if="day.is_working_day && day.shift">
                    {{ day.shift.name }}
                    <span class="text-medium-emphasis">
                      {{ time(day.shift.start_time) }}–{{ time(day.shift.end_time) }}
                    </span>
                  </span>
                  <span v-else class="text-medium-emphasis">Rest day</span>
                </td>
                <td>
                  {{ day.is_working_day && day.shift ? day.shift.required_hours : "—" }}
                </td>
              </tr>
            </tbody>
          </v-table>
          <p v-if="assignment.remarks" class="text-body-2 mt-3">
            {{ assignment.remarks }}
          </p>
        </v-card-text>
      </v-card>
      </template>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";

const DAY_NAMES = {
  0: "Sunday",
  1: "Monday",
  2: "Tuesday",
  3: "Wednesday",
  4: "Thursday",
  5: "Friday",
  6: "Saturday",
  7: "Sunday",
};

export default {
  name: "MyScheduleIndex",
  components: { SidebarLayout, Head },
  props: {
    hasEmployeeRecord: { type: Boolean, default: true },
    schedules: { type: Array, default: () => [] },
  },
  methods: {
    dayName(day) {
      return DAY_NAMES[day] ?? day;
    },
    time(value) {
      return value ? String(value).substring(0, 5) : "";
    },
    formatDate(value) {
      return value ? new Date(value).toLocaleDateString() : "";
    },
  },
};
</script>
