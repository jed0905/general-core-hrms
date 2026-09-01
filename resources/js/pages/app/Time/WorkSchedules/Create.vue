<template>
  <SidebarLayout>
    <Head title="Create Work Schedule" />

    <v-container fluid class="pa-6">
      <!-- Header -->
      <div
        class="d-flex align-center justify-space-between flex-wrap ga-3 mb-6"
      >
        <div class="d-flex align-center ga-3">
          <Link :href="route('time.work-schedules.index')">
            <v-btn
              icon="mdi-arrow-left"
              variant="text"
              density="comfortable"
              color="medium-emphasis"
            />
          </Link>
          <div>
            <h1 class="text-h5 font-weight-bold text-high-emphasis">
              Create Work Schedule
            </h1>
            <p class="text-body-2 text-medium-emphasis">
              Define a weekly schedule pattern by mapping active shifts across
              Sunday through Saturday.
            </p>
          </div>
        </div>

        <div class="d-flex align-center ga-2">
          <v-btn
            variant="outlined"
            color="medium-emphasis"
            size="small"
            :to="route('time.work-schedules.index')"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            size="small"
            elevation="0"
            :loading="form.processing"
            @click="submit"
          >
            Save Schedule
          </v-btn>
        </div>
      </div>

      <form @submit.prevent="submit">
        <v-row>
          <!-- Schedule Information -->
          <v-col cols="12" md="4">
            <v-card variant="outlined" class="rounded-lg bg-surface mb-6">
              <v-card-title
                class="text-subtitle-1 font-weight-bold py-3 px-5 border-b"
              >
                Schedule Details
              </v-card-title>
              <v-card-text class="pa-5">
                <v-row density="compact">
                  <v-col cols="12">
                    <v-text-field
                      v-model="form.name"
                      label="Schedule Name *"
                      placeholder="e.g. Standard Mon-Fri Schedule"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.name"
                      required
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-text-field
                      v-model="form.code"
                      label="Schedule Code"
                      placeholder="e.g. SCHED-STD-5D"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.code"
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-textarea
                      v-model="form.description"
                      label="Description"
                      placeholder="Optional notes regarding this schedule structure..."
                      variant="outlined"
                      density="compact"
                      rows="3"
                      :error-messages="form.errors.description"
                    />
                  </v-col>

                  <v-col cols="12">
                    <v-select
                      v-model="form.status"
                      label="Status *"
                      :items="statusOptions"
                      item-title="title"
                      item-value="value"
                      variant="outlined"
                      density="compact"
                      :error-messages="form.errors.status"
                    />
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>
          </v-col>

          <!-- Weekly Shift Assignment -->
          <v-col cols="12" md="8">
            <v-card variant="outlined" class="rounded-lg bg-surface">
              <v-card-title
                class="text-subtitle-1 font-weight-bold py-3 px-5 border-b d-flex justify-space-between align-center flex-wrap ga-2"
              >
                <span>Weekly Shift Pattern</span>

                <!-- Bulk Apply Tool -->
                <div class="d-flex align-center ga-2" style="min-width: 280px">
                  <v-select
                    v-model="bulkShiftId"
                    :items="formattedShifts"
                    item-title="title"
                    item-subtitle="subtitle"
                    item-value="id"
                    placeholder="Select shift..."
                    density="compact"
                    variant="outlined"
                    hide-details
                    clearable
                    class="flex-grow-1"
                  />
                  <v-btn
                    size="small"
                    variant="tonal"
                    color="primary"
                    :disabled="!bulkShiftId"
                    @click="applyBulkShift"
                  >
                    Apply to Working Days
                  </v-btn>
                </div>
              </v-card-title>

              <v-card-text class="pa-0">
                <v-table hover class="bg-transparent">
                  <thead>
                    <tr>
                      <th
                        class="text-left font-weight-bold"
                        style="width: 140px"
                      >
                        Day
                      </th>
                      <th
                        class="text-left font-weight-bold"
                        style="width: 130px"
                      >
                        Working Day
                      </th>
                      <th class="text-left font-weight-bold">Assigned Shift</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(day, index) in form.days" :key="index">
                      <td class="font-weight-medium">
                        {{ dayNames[day.day_of_week] }}
                      </td>

                      <td>
                        <v-checkbox-btn
                          v-model="day.is_working_day"
                          color="primary"
                          density="compact"
                          @update:model-value="toggleWorkingDay(day)"
                        />
                      </td>

                      <td>
                        <v-select
                          v-model="day.shift_id"
                          :items="formattedShifts"
                          item-title="title"
                          item-subtitle="subtitle"
                          item-value="id"
                          placeholder="Select shift"
                          variant="outlined"
                          density="compact"
                          hide-details="auto"
                          :disabled="!day.is_working_day"
                          :error-messages="
                            form.errors[`days.${index}.shift_id`]
                          "
                          clearable
                        />
                      </td>
                    </tr>
                  </tbody>
                </v-table>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </form>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, Link, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/Layouts/SidebarLayout.vue";

export default {
  name: "WorkScheduleCreate",

  components: {
    SidebarLayout,
    Head,
    Link,
  },

  props: {
    shifts: {
      type: Array,
      default: () => [],
    },
  },

  data() {
    return {
      bulkShiftId: null,

      dayNames: [
        "Sunday",
        "Monday",
        "Tuesday",
        "Wednesday",
        "Thursday",
        "Friday",
        "Saturday",
      ],

      statusOptions: [
        { title: "Active", value: "active" },
        { title: "Inactive", value: "inactive" },
      ],

      form: useForm({
        name: "",
        code: "",
        description: "",
        status: "active",

        days: [0, 1, 2, 3, 4, 5, 6].map((dayIndex) => ({
          day_of_week: dayIndex,
          is_working_day: dayIndex >= 1 && dayIndex <= 5,
          shift_id: null,
        })),
      }),
    };
  },

  computed: {
    formattedShifts() {
      return (this.shifts || []).map((shift) => ({
        ...shift,
        title: shift.name,
        subtitle: `Code: ${shift.code || "N/A"} | Req: ${
          shift.required_hours ?? 0
        } hrs`,
      }));
    },
  },

  methods: {
    toggleWorkingDay(day) {
      if (!day.is_working_day) {
        day.shift_id = null;
      }
    },

    applyBulkShift() {
      if (!this.bulkShiftId) return;

      this.form.days.forEach((day) => {
        if (day.is_working_day) {
          day.shift_id = this.bulkShiftId;
        }
      });

      const selectedShift = this.shifts.find((s) => s.id === this.bulkShiftId);
      this.showToast(
        `Applied "${
          selectedShift?.name || "Shift"
        }" to all active working days`,
        "info"
      );
    },

    submit() {
      this.form.post(route("time.work-schedules.store"), {
        onSuccess: () => {
          this.showToast("Work schedule created successfully!", "success");
        },
        onError: (errors) => {
          const errorCount = Object.keys(errors).length;
          this.showToast(
            `Failed to create schedule. Please check ${errorCount} input field(s).`,
            "error"
          );
        },
      });
    },
  },
};
</script>
