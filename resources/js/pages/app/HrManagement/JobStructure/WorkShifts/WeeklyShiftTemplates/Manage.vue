<template>
  <JobStructureTabs :activeTab="activeTab" />
  <v-card rounded="lg" class="mb-3">
    <v-card-text>
      <div class="v-card-title text-h6">Weekly Shift Template</div>
      <v-row>
        <v-col cols="12">
          <p>Name: {{ weeklyShiftTemplate.name }}</p>
        </v-col>
        <v-col cols="12">
          <p>Description: {{ weeklyShiftTemplate.description }}</p>
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>
  <v-card rounded="lg" class="mb-3">
    <v-card-text>
      <v-row>
        <v-col class="d-flex align-center justify-center" cols="12">
          <v-btn
            v-for="day in daysOfTheWeek"
            :key="day"
            :color="selectedDay === day ? 'blue-darken-2' : 'blue-darken-4'"
            :variant="selectedDay === day ? 'elevated' : 'tonal'"
            size="small"
            class="mr-5 text-black"
            min-width="120"
            rounded="xl"
            @click="selectedDay = day"
            >{{ day }}</v-btn
          >
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>
  <v-row>
    <v-col cols="12" md="5">
      <v-card rounded="lg">
        <v-card-text>
          <div class="mb-3">
            <h4>Available Schedules for: {{ selectedDay ?? "" }}</h4>
          </div>
          <v-table fixed-header height="400">
            <thead>
              <tr>
                <th>Select</th>
                <th>Check In</th>
                <th>Break Out</th>
                <th>Break In</th>
                <th>Check Out</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="DailyShiftSchedules.length == 0">
                <td colspan="6" class="text-center">No data found</td>
              </tr>

              <tr
                v-else
                v-for="dailyShiftSchedule in DailyShiftSchedules"
                :key="dailyShiftSchedule.id"
              >
                <td>
                  <v-radio
                    :value="dailyShiftSchedule.id"
                    v-model="selectedScheduleId"
                    density="compact"
                    @click="toggleRadio(dailyShiftSchedule.id)"
                  ></v-radio>
                </td>
                <td>{{ dailyShiftSchedule.time_in }}</td>
                <td>{{ dailyShiftSchedule.break_start }}</td>
                <td>{{ dailyShiftSchedule.break_end }}</td>
                <td>{{ dailyShiftSchedule.time_out }}</td>
                <td>{{ dailyShiftSchedule.remarks }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" md="2" class="d-flex align-center justify-center">
      <div class="d-flex flex-column align-center gap-2">
        <v-btn
          icon="mdi-greater-than"
          color="starbucks-green"
          variant="outlined"
          @click="addSelected()"
          :disabled="!selectedScheduleId"
        ></v-btn>

        <v-btn
          icon="mdi-less-than"
          color="starbucks-green"
          variant="outlined"
          @click="removeSelected()"
          :disabled="!canRemove"
        ></v-btn>
      </div>
    </v-col>
    <v-col cols="12" md="5">
      <v-card rounded="lg">
        <v-card-text>
          <div class="mb-3 d-flex justify-space-between align-center">
            <h4>Selected Weekly Schedule</h4>
          </div>
          <v-table fixed-header height="400">
            <thead>
              <tr>
                <th>
                  <v-checkbox
                    v-model="selectAll"
                    @change="toggleSelectAll"
                    hide-details
                    density="compact"
                  ></v-checkbox>
                </th>
                <th>Day</th>
                <th>Check In</th>
                <th>Break Out</th>
                <th>Break In</th>
                <th>Check Out</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="weeklyShiftDays.length == 0">
                <td colspan="7" class="text-center">No schedules selected</td>
              </tr>
              <tr v-else v-for="(schedule, i) in weeklyShiftDays" :key="i">
                <td>
                  <v-checkbox
                    v-model="selectedWeeklyShiftDayIds"
                    :value="schedule.id"
                    hide-details
                    density="compact"
                    @change="syncSelectAll"
                  ></v-checkbox>
                </td>
                <td>{{ schedule.daily_shift_schedule.day_of_week }}</td>
                <td>{{ schedule.daily_shift_schedule.time_in }}</td>
                <td>{{ schedule.daily_shift_schedule.break_start }}</td>
                <td>{{ schedule.daily_shift_schedule.break_end }}</td>
                <td>{{ schedule.daily_shift_schedule.time_out }}</td>
                <td>{{ schedule.daily_shift_schedule.remarks }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>
  <!-- <pre>{{ weeklyShiftDays }}</pre> -->

  <!-- Debug info -->
  <!-- <v-card v-if="true" class="mt-3">
     <v-card-text>
       <pre>Debug Info:
        Selected Day: {{ selectedDay }}
        Selected Schedule ID: {{ selectedScheduleId }}
        Can Add: {{ canAdd }}
        DailyShiftSchedules Length: {{ DailyShiftSchedules.length }}
        Selected Schedules: {{ Object.keys(selectedSchedules) }}</pre>
     </v-card-text>
   </v-card> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import JobStructureTabs from "@/components/JobStructureTabs.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import { useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import { required, minLength, maxLength } from "@vuelidate/validators";
import { daysOfTheWeek } from "@/utils/days";
import DeleteDialog from "@/components/DeleteDialog.vue";

export default {
  layout: SidebarLayout,
  components: {
    JobStructureTabs,
    ButtonSuccess,
    ButtonMuted,
    DeleteDialog,
  },
  props: {
    errors: Object,
    weeklyShiftTemplate: Object,
    DailyShiftSchedules: Array,
    weeklyShiftDays: Array,
  },
  data() {
    return {
      v$: useVuelidate(),
      daysOfTheWeek,
      selectedDay: null,
      selectedScheduleId: null,
      selectedSchedules: {}, // { dayName: scheduleObject }
      selectedWeeklyShiftDayIds: [], // array of selected weekly shift day IDs on right table
      selectAll: false,
      loading: false,
      // removed dialogs and single delete usage
      id: null,
    };
  },
  computed: {
    canAdd() {
      return (
        this.selectedDay &&
        this.selectedScheduleId &&
        this.DailyShiftSchedules.length > 0 &&
        !this.selectedSchedules[this.selectedDay]
      );
    },
    canRemove() {
      return (
        this.selectedWeeklyShiftDayIds &&
        this.selectedWeeklyShiftDayIds.length > 0
      );
    },
  },
  watch: {
    selectedDay: {
      handler() {
        this.fetchDailyShiftSchedules();
        // Reset selection when day changes
        this.selectedScheduleId = null;
      },
      immediate: true,
    },
  },
  mounted() {
    // Initialize selectedSchedules with existing weeklyShiftDays
    this.initializeSelectedSchedules();
  },
  methods: {
    toggleSelectAll() {
      if (this.selectAll) {
        // select all ids on the right table
        this.selectedWeeklyShiftDayIds = this.weeklyShiftDays.map(
          (schedule) => schedule.id
        );
      } else {
        // deselect all
        this.selectedWeeklyShiftDayIds = [];
      }
    },

    // 🔹 Keep header checkbox in sync with row checkboxes
    syncSelectAll() {
      this.selectAll =
        this.selectedWeeklyShiftDayIds.length === this.weeklyShiftDays.length;
    },

    toggleRadio(id) {
      // if same radio is clicked again, deselect it
      this.selectedScheduleId = this.selectedScheduleId === id ? null : id;
    },

    initializeSelectedSchedules() {
      if (this.weeklyShiftDays && this.weeklyShiftDays.length > 0) {
        this.weeklyShiftDays.forEach((day) => {
          if (day.dailyShiftSchedule) {
            this.selectedSchedules[day.dailyShiftSchedule.day_of_week] =
              day.dailyShiftSchedule;
          }
        });
      }
    },
    fetchDailyShiftSchedules() {
      if (this.selectedDay) {
        this.$inertia.post(
          route("hrmanagement.jobstructure.weeklyShiftTemplates.manage", {
            id: this.weeklyShiftTemplate.id,
          }),
          {
            selectedDay: this.selectedDay,
          },
          {
            preserveScroll: true,
            preserveState: true,
          }
        );
      }
    },
    selectSchedule(schedule) {
      if (this.selectedDay && schedule) {
        // Only allow one schedule per day - this is for UI display only
        this.selectedSchedules[this.selectedDay] = schedule;
      }
    },
    removeSchedule(dayName) {
      if (dayName && this.selectedSchedules[dayName]) {
        const scheduleToRemove = this.selectedSchedules[dayName];
        delete this.selectedSchedules[dayName];

        // Reset radio selection if removing current day's schedule
        if (this.selectedDay === dayName) {
          this.selectedScheduleId = null;
        }

        // Automatically delete from database
        this.deleteSingleSchedule(dayName, scheduleToRemove.id);
      }
    },
    addSelected() {
      if (this.selectedScheduleId) {
        const selectedSchedule = this.DailyShiftSchedules.find(
          (s) => s.id === this.selectedScheduleId
        );
        if (selectedSchedule) {
          // Add to UI first
          this.selectSchedule(selectedSchedule);
          // Then save to database
          this.saveSingleSchedule(selectedSchedule);
        }
      }
    },
    removeSelected() {
      if (
        !this.selectedWeeklyShiftDayIds ||
        this.selectedWeeklyShiftDayIds.length === 0
      )
        return;

      const idsToDelete = [...this.selectedWeeklyShiftDayIds];

      this.$inertia.delete(
        route("hrmanagement.jobstructure.weeklyShiftTemplates.deleteBatch", {
          id: this.weeklyShiftTemplate.id,
        }),
        {
          data: { selectedIds: idsToDelete },
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.showToast(
              `${idsToDelete.length} schedule(s) deleted successfully`,
              "success"
            );
            this.selectedWeeklyShiftDayIds = [];
            this.selectAll = false;
          },
          onError: () => {
            this.showToast("Failed to delete selected schedules", "error");
          },
        }
      );
    },
    saveSingleSchedule(schedule) {
      if (!schedule || !this.selectedDay) return;

      this.$inertia.post(
        route(
          "hrmanagement.jobstructure.weeklyShiftTemplates.saveSingleSchedule",
          {
            id: this.weeklyShiftTemplate.id,
          }
        ),
        {
          day_of_week: this.selectedDay,
          daily_shift_schedule_id: schedule.id,
        },
        {
          onSuccess: () => {
            // Schedule saved successfully
          },
          onError: () => {
            // Revert the selection if save failed
            delete this.selectedSchedules[this.selectedDay];
          },
        }
      );
    },

    // removed single and batch delete dialog handlers

    showToast(message, type = "success") {
      // You can implement your toast notification here
      // This is a placeholder - replace with your actual toast implementation
      console.log(`${type.toUpperCase()}: ${message}`);
    },
  },
};
</script>
