<template>
  <SidebarLayout>
    <Head title="Leave Applications" />

    <v-container fluid class="pa-4 pa-sm-6">
      <!-- Header -->
      <div
        class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-4 mb-6"
      >
        <div>
          <h1 class="text-h5 font-weight-bold">Leave Applications</h1>
          <p class="text-body-2 text-medium-emphasis">
            Apply for leave, check team availability, and manage your requests.
          </p>
        </div>

        <div class="d-flex align-center ga-2 ga-sm-3">
          <v-btn-toggle
            v-model="viewMode"
            mandatory
            color="primary"
            density="compact"
            variant="outlined"
            class="custom-toggle"
          >
            <v-btn value="calendar" prepend-icon="mdi-calendar-month">
              <span class="hidden-xs">Calendar</span>
            </v-btn>

            <v-btn value="list" prepend-icon="mdi-format-list-bulleted">
              <span class="hidden-xs">List</span>
            </v-btn>
          </v-btn-toggle>

          <v-btn
            v-if="canApply"
            color="primary"
            prepend-icon="mdi-plus"
            elevation="0"
            @click="openModal()"
          >
            Apply Leave
          </v-btn>
        </div>
      </div>

      <v-alert
        v-if="!hasEmployeeRecord"
        type="info"
        variant="tonal"
        class="mb-6"
      >
        Your account is not linked to an employee record, so you can't file
        leave here. Contact HR if this is unexpected.
      </v-alert>

      <!-- Balances Overview Cards -->
      <v-row class="mb-6">
        <v-col
          v-for="balance in balances"
          :key="balance.id"
          cols="12"
          sm="6"
          md="3"
        >
          <v-card variant="outlined" class="rounded-lg pa-4">
            <div class="d-flex justify-space-between align-center mb-1">
              <span class="text-subtitle-2 font-weight-bold">{{
                balance.leave_type?.name
              }}</span>
              <v-chip size="x-small" color="primary" variant="tonal">{{
                balance.leave_type?.code
              }}</v-chip>
            </div>
            <div class="d-flex align-baseline ga-1">
              <span class="text-h4 font-weight-bold">{{
                balance.balance ?? 0
              }}</span>
              <span class="text-caption text-medium-emphasis">days left</span>
            </div>
            <div
              class="d-flex justify-space-between mt-2 text-caption text-medium-emphasis"
            >
              <span>Pending: {{ balance.pending ?? 0 }}d</span>
              <span>Used: {{ balance.used ?? 0 }}d</span>
            </div>
          </v-card>
        </v-col>
      </v-row>

      <!-- FULL RESPONSIVE CALENDAR VIEW -->
      <v-card
        v-if="viewMode === 'calendar'"
        variant="outlined"
        class="rounded-lg overflow-hidden mb-6 pa-3 pa-sm-4"
      >
        <!-- Selection Action Banner -->
        <v-alert
          v-if="selectedDates.length > 0"
          color="primary"
          variant="tonal"
          density="compact"
          class="mb-4 rounded-lg d-flex align-center justify-space-between"
        >
          <div class="d-flex align-center ga-2">
            <v-icon icon="mdi-checkbox-marked-circle-outline" color="primary" />
            <span class="font-weight-bold text-body-2">
              {{ selectedDates.length }}
              {{ selectedDates.length === 1 ? "day" : "days" }} selected
            </span>
          </div>
          <div class="d-flex ga-2">
            <v-btn
              size="small"
              variant="text"
              color="primary"
              @click="clearSelection"
              >Clear</v-btn
            >
            <v-btn
              v-if="canApply"
              size="small"
              color="primary"
              elevation="0"
              prepend-icon="mdi-plus"
              @click="applyForSelectedDates"
            >
              Apply Leave for Selected
            </v-btn>
          </div>
        </v-alert>

        <!-- Calendar Header Controls -->
        <div
          class="d-flex flex-column flex-sm-row align-sm-center justify-space-between ga-3 mb-4"
        >
          <div class="d-flex align-center ga-2 ga-sm-4">
            <v-btn
              icon="mdi-chevron-left"
              variant="outlined"
              density="compact"
              @click="changeMonth(-1)"
            />
            <v-btn variant="outlined" density="compact" @click="goToday"
              >Today</v-btn
            >
            <v-btn
              icon="mdi-chevron-right"
              variant="outlined"
              density="compact"
              @click="changeMonth(1)"
            />
            <span class="text-h6 font-weight-bold ml-2">{{
              currentMonthLabel
            }}</span>
          </div>

          <!-- Legend -->
          <div class="d-flex align-center flex-wrap ga-3 text-caption">
            <div class="d-flex align-center ga-1">
              <v-badge dot color="primary" inline /> My Leave
            </div>
            <div class="d-flex align-center ga-1">
              <v-badge dot color="info" inline /> Team Approved
            </div>
            <div class="d-flex align-center ga-1">
              <v-badge dot color="amber-darken-2" inline /> Team Pending
            </div>
          </div>
        </div>

        <!-- CSS Grid Calendar -->
        <div class="calendar-wrapper border rounded-lg overflow-hidden">
          <!-- Day Headers -->
          <div class="calendar-header-grid">
            <div v-for="day in weekDays" :key="day" class="calendar-day-header">
              {{ day }}
            </div>
          </div>

          <!-- Day Cells -->
          <div class="calendar-body-grid">
            <div
              v-for="(cell, index) in calendarGrid"
              :key="index"
              class="calendar-day-cell"
              :class="{
                'off-month': !cell.isCurrentMonth,
                'is-today': cell.isToday,
                'selected-cell': isSelected(cell.date),
              }"
              @click="handleCellClick(cell)"
            >
              <!-- Date Number & Checkbox/Add -->
              <div class="d-flex align-center justify-space-between px-1 pt-1">
                <div class="d-flex align-center ga-1">
                  <v-icon
                    v-if="isSelected(cell.date)"
                    icon="mdi-check-circle"
                    size="x-small"
                    color="primary"
                  />
                  <span
                    class="day-number"
                    :class="{
                      'font-weight-bold text-primary':
                        cell.isToday || isSelected(cell.date),
                    }"
                  >
                    {{ cell.dayNumber }}
                  </span>
                </div>

                <v-btn
                  v-if="cell.isCurrentMonth && canApply"
                  icon="mdi-plus"
                  size="x-small"
                  variant="text"
                  color="primary"
                  class="add-btn"
                  @click.stop="openModalWithDate(cell.date)"
                />
              </div>

              <!-- Desktop View: Event Chips -->
              <div class="event-list hidden-xs mt-1">
                <div
                  v-for="evt in cell.events"
                  :key="evt.id"
                  class="event-chip"
                  :class="getEventChipClass(evt)"
                  :title="`${evt.employee_name} - ${evt.leave_type_name}`"
                >
                  <span class="font-weight-bold mr-1">{{
                    evt.is_self ? "Me" : evt.employee_name
                  }}</span>
                  <span class="text-caption">({{ evt.leave_type }})</span>
                </div>
              </div>

              <!-- Mobile View: Color Dots -->
              <div class="mobile-dots hidden-sm-and-up mt-1">
                <div
                  v-for="evt in cell.events.slice(0, 3)"
                  :key="evt.id"
                  class="dot-indicator"
                  :class="getEventDotClass(evt)"
                />
                <span
                  v-if="cell.events.length > 3"
                  class="text-caption font-weight-bold text-medium-emphasis"
                >
                  +{{ cell.events.length - 3 }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </v-card>

      <!-- LIST VIEW -->
      <v-card v-else variant="outlined" class="rounded-lg">
        <v-table hover>
          <thead>
            <tr>
              <th class="font-weight-bold">Leave Type</th>
              <th class="font-weight-bold">Reason</th>
              <th class="font-weight-bold">Duration</th>
              <th class="font-weight-bold">Submitted</th>
              <th class="font-weight-bold">Status</th>
              <th class="text-end font-weight-bold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!applications.data || !applications.data.length">
              <td colspan="6" class="text-center py-6 text-medium-emphasis">
                No active leave applications found.
              </td>
            </tr>
            <tr v-for="app in applications.data" :key="app.id">
              <td class="font-weight-medium">{{ app.leave_type?.name }}</td>
              <td class="text-truncate" style="max-width: 200px">
                {{ app.reason || "—" }}
              </td>
              <td>{{ app.total_days }} days ({{ app.total_hours }} hrs)</td>
              <td>{{ formatDate(app.submitted_at) }}</td>
              <td>
                <v-chip
                  :color="getStatusColor(app.status)"
                  size="small"
                  variant="tonal"
                >
                  {{ app.status }}
                </v-chip>
              </td>
              <td class="text-end">
                <v-btn
                  v-if="app.can?.view"
                  icon="mdi-eye-outline"
                  variant="text"
                  size="small"
                  color="info"
                  title="View details"
                  @click="viewApplication(app)"
                />
                <v-btn
                  v-if="app.can?.update"
                  icon="mdi-pencil-outline"
                  variant="text"
                  size="small"
                  :title="app.status === 'returned' ? 'Correct and resubmit' : 'Edit'"
                  @click="openModal(app)"
                />
                <v-btn
                  v-if="app.can?.cancel"
                  icon="mdi-cancel"
                  variant="text"
                  size="small"
                  color="error"
                  @click="confirmCancel(app)"
                />
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>

      <!-- Mobile Day Detail Bottom Sheet -->
      <v-bottom-sheet v-model="dayDrawer">
        <v-card class="pa-4 rounded-t-lg">
          <div class="d-flex justify-space-between align-center mb-3">
            <h3 class="text-subtitle-1 font-weight-bold">
              Leaves for {{ selectedDayDate }}
            </h3>
            <v-btn
              icon="mdi-close"
              variant="text"
              size="small"
              @click="dayDrawer = false"
            />
          </div>

          <v-list v-if="selectedDayEvents.length" density="compact">
            <v-list-item
              v-for="evt in selectedDayEvents"
              :key="evt.id"
              class="px-0 border-b"
            >
              <template #prepend>
                <v-avatar
                  size="32"
                  :color="evt.is_self ? 'primary' : 'info'"
                  variant="tonal"
                  class="mr-3"
                >
                  {{ evt.employee_name.charAt(0) }}
                </v-avatar>
              </template>
              <v-list-item-title class="font-weight-medium">
                {{ evt.employee_name }}
              </v-list-item-title>
              <v-list-item-subtitle>
                {{ evt.leave_type_name }} ({{ evt.duration_type }})
              </v-list-item-subtitle>
              <template #append>
                <v-chip
                  :color="getStatusColor(evt.status)"
                  size="x-small"
                  variant="tonal"
                >
                  {{ evt.status }}
                </v-chip>
              </template>
            </v-list-item>
          </v-list>

          <p v-else class="text-caption text-medium-emphasis text-center py-4">
            No team members are scheduled on leave for this day.
          </p>

          <v-btn
            v-if="canApply"
            block
            color="primary"
            class="mt-3"
            prepend-icon="mdi-plus"
            @click="
              openModalWithDate(selectedDayDate);
              dayDrawer = false;
            "
          >
            Apply Leave for this Date
          </v-btn>
        </v-card>
      </v-bottom-sheet>

      <!-- Cancel Confirmation -->
      <v-dialog v-model="cancelDialog" max-width="420">
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="font-weight-bold">Cancel leave application?</v-card-title>
          <v-card-text>
            <span v-if="applicationToCancel">
              Your {{ applicationToCancel.leave_type?.name }} request for
              {{ applicationToCancel.total_days }} day(s) will be cancelled.
              <template v-if="applicationToCancel.status === 'approved'">
                The used days will be returned to the balance.
              </template>
            </span>
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="cancelDialog = false">Keep</v-btn>
            <v-btn color="error" :loading="cancelling" @click="cancelApplication">
              Cancel Application
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Apply / Edit Leave Modal -->
      <v-dialog v-model="dialog" max-width="700" persistent>
        <v-card class="pa-2 rounded-lg">
          <v-card-title class="font-weight-bold">
            {{ isEditing ? "Edit Leave Application" : "Apply for Leave" }}
          </v-card-title>
          <v-card-text>
            <v-row density="compact">
              <v-col cols="12">
                <v-select
                  v-model="form.leave_type_id"
                  label="Leave Type *"
                  :items="leaveTypes"
                  item-title="name"
                  item-value="id"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.leave_type_id"
                />
              </v-col>
              <v-col cols="12">
                <v-textarea
                  v-model="form.reason"
                  label="Reason"
                  rows="2"
                  variant="outlined"
                  density="compact"
                  :error-messages="form.errors.reason"
                />
              </v-col>
              <v-col cols="12">
                <div class="d-flex align-center justify-space-between mb-2">
                  <span class="text-subtitle-2 font-weight-bold"
                    >Leave Dates *</span
                  >
                  <v-btn
                    size="x-small"
                    color="primary"
                    variant="tonal"
                    prepend-icon="mdi-plus"
                    @click="addDateRow()"
                    >Add Date</v-btn
                  >
                </div>
                <div
                  v-for="(dateRow, idx) in form.dates"
                  :key="idx"
                  class="pa-3 border rounded-lg mb-2 bg-grey-lighten-5"
                >
                  <v-row density="compact" align="center">
                    <v-col cols="12" sm="4">
                      <v-text-field
                        v-model="dateRow.leave_date"
                        type="date"
                        label="Date *"
                        variant="outlined"
                        density="compact"
                        hide-details
                      />
                    </v-col>
                    <v-col cols="12" sm="4">
                      <v-select
                        v-model="dateRow.duration_type"
                        label="Duration *"
                        :items="[
                          { title: 'Full Day', value: 'full_day' },
                          { title: 'Half Day', value: 'half_day' },
                          { title: 'Specific Hours', value: 'hours' },
                        ]"
                        variant="outlined"
                        density="compact"
                        hide-details
                      />
                    </v-col>
                    <v-col
                      v-if="dateRow.duration_type === 'hours'"
                      cols="10"
                      sm="3"
                    >
                      <v-text-field
                        v-model.number="dateRow.hours"
                        type="number"
                        step="0.5"
                        label="Hours"
                        variant="outlined"
                        density="compact"
                        hide-details
                      />
                    </v-col>
                    <v-col cols="2" sm="1" class="text-end">
                      <v-btn
                        icon="mdi-delete-outline"
                        variant="text"
                        size="small"
                        color="error"
                        :disabled="form.dates.length <= 1"
                        @click="removeDateRow(idx)"
                      />
                    </v-col>
                  </v-row>
                </div>
              </v-col>
              <v-col cols="12" class="mt-2">
                <v-file-input
                  v-model="form.attachments"
                  label="Attachments"
                  variant="outlined"
                  density="compact"
                  multiple
                  show-size
                  prepend-icon="mdi-paperclip"
                  :error-messages="form.errors.attachments"
                />
              </v-col>
            </v-row>
          </v-card-text>
          <v-card-actions class="justify-end ga-2">
            <v-btn variant="outlined" @click="dialog = false">Cancel</v-btn>
            <v-btn color="primary" :loading="form.processing" @click="submit"
              >Submit Request</v-btn
            >
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import { Head, router, useForm } from "@inertiajs/vue3";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import permissions from "@/mixins/permissions";

export default {
  components: { SidebarLayout, Head },
  mixins: [permissions],
  props: {
    applications: Object,
    history: Object,
    balances: Array,
    teamEvents: Array,
    leaveTypes: Array,
    filters: Object,
    hasEmployeeRecord: { type: Boolean, default: true },
  },
  data() {
    return {
      viewMode: "calendar",
      currentDate: new Date(),
      weekDays: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"],
      selectedDates: [], // Holds multi-selected dates
      dialog: false,
      isEditing: false,
      selectedId: null,
      dayDrawer: false,
      selectedDayDate: "",
      selectedDayEvents: [],
      cancelDialog: false,
      cancelling: false,
      applicationToCancel: null,
      form: useForm({
        leave_type_id: null,
        reason: "",
        dates: [
          {
            leave_date: "",
            duration_type: "full_day",
            hours: 8,
            start_time: null,
            end_time: null,
            is_paid: true,
          },
        ],
        attachments: [],
      }),
    };
  },
  mounted() {
    // "Apply Leave" (leave.applications.create) lands here with ?apply=1.
    if (new URLSearchParams(window.location.search).has("apply") && this.canApply) {
      this.openModal();
    }
  },
  computed: {
    canApply() {
      return this.hasEmployeeRecord && this.can("leave.create");
    },
    currentMonthLabel() {
      return this.currentDate.toLocaleString("default", {
        month: "long",
        year: "numeric",
      });
    },
    calendarGrid() {
      const year = this.currentDate.getFullYear();
      const month = this.currentDate.getMonth();

      const firstDayIndex = new Date(year, month, 1).getDay();
      const totalDays = new Date(year, month + 1, 0).getDate();
      const prevMonthDays = new Date(year, month, 0).getDate();

      const todayStr = new Date().toISOString().split("T")[0];
      const grid = [];

      for (let i = firstDayIndex - 1; i >= 0; i--) {
        const d = prevMonthDays - i;
        const prevMonth = month === 0 ? 11 : month - 1;
        const prevYear = month === 0 ? year - 1 : year;
        const dateStr = `${prevYear}-${String(prevMonth + 1).padStart(
          2,
          "0"
        )}-${String(d).padStart(2, "0")}`;
        grid.push({
          dayNumber: d,
          date: dateStr,
          isCurrentMonth: false,
          isToday: false,
          events: [],
        });
      }

      for (let day = 1; day <= totalDays; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, "0")}-${String(
          day
        ).padStart(2, "0")}`;
        const dayEvents = (this.teamEvents || []).filter(
          (e) => e.date === dateStr
        );
        grid.push({
          dayNumber: day,
          date: dateStr,
          isCurrentMonth: true,
          isToday: dateStr === todayStr,
          events: dayEvents,
        });
      }

      const remainingSlots = (7 - (grid.length % 7)) % 7;
      for (let j = 1; j <= remainingSlots; j++) {
        const nextMonth = month === 11 ? 0 : month + 1;
        const nextYear = month === 11 ? year + 1 : year;
        const dateStr = `${nextYear}-${String(nextMonth + 1).padStart(
          2,
          "0"
        )}-${String(j).padStart(2, "0")}`;
        grid.push({
          dayNumber: j,
          date: dateStr,
          isCurrentMonth: false,
          isToday: false,
          events: [],
        });
      }

      return grid;
    },
  },
  methods: {
    changeMonth(delta) {
      this.currentDate = new Date(
        this.currentDate.getFullYear(),
        this.currentDate.getMonth() + delta,
        1
      );
    },
    goToday() {
      this.currentDate = new Date();
    },
    isSelected(dateStr) {
      return this.selectedDates.includes(dateStr);
    },
    handleCellClick(cell) {
      if (!cell.isCurrentMonth) return;

      // Toggle multi-selection on click
      const idx = this.selectedDates.indexOf(cell.date);
      if (idx > -1) {
        this.selectedDates.splice(idx, 1);
      } else {
        this.selectedDates.push(cell.date);
      }

      // Mobile sheet triggers only if events exist and no dates selected
      if (
        this.$vuetify.display.xs &&
        cell.events.length &&
        !this.selectedDates.length
      ) {
        this.selectedDayDate = cell.date;
        this.selectedDayEvents = cell.events;
        this.dayDrawer = true;
      }
    },
    clearSelection() {
      this.selectedDates = [];
    },
    applyForSelectedDates() {
      if (!this.selectedDates.length) return;
      const sortedDates = [...this.selectedDates].sort();
      this.openModal();
      this.form.dates = sortedDates.map((d) => ({
        leave_date: d,
        duration_type: "full_day",
        hours: 8,
        start_time: null,
        end_time: null,
        is_paid: true,
      }));
    },
    getEventChipClass(evt) {
      if (evt.is_self) return "bg-primary text-white";
      if (evt.status === "pending")
        return "bg-amber-lighten-4 text-amber-darken-4";
      return "bg-info-lighten-4 text-info-darken-4";
    },
    getEventDotClass(evt) {
      if (evt.is_self) return "bg-primary";
      if (evt.status === "pending") return "bg-amber-darken-2";
      return "bg-info";
    },
    openModalWithDate(dateStr) {
      this.openModal();
      if (dateStr) {
        this.form.dates = [
          {
            leave_date: dateStr,
            duration_type: "full_day",
            hours: 8,
            start_time: null,
            end_time: null,
            is_paid: true,
          },
        ];
      }
    },
    openModal(app = null) {
      this.form.reset();
      this.form.clearErrors();
      if (app) {
        this.isEditing = true;
        this.selectedId = app.id;
        this.form.leave_type_id = app.leave_type_id;
        this.form.reason = app.reason ?? "";
        this.form.dates =
          app.dates?.map((d) => ({
            leave_date: String(d.leave_date ?? "").substring(0, 10),
            duration_type: d.duration_type,
            hours: d.hours,
            start_time: d.start_time,
            end_time: d.end_time,
            is_paid: Boolean(d.is_paid),
          })) || [];
      } else {
        this.isEditing = false;
        this.selectedId = null;
      }
      this.dialog = true;
    },
    addDateRow() {
      this.form.dates.push({
        leave_date: "",
        duration_type: "full_day",
        hours: 8,
        start_time: null,
        end_time: null,
        is_paid: true,
      });
    },
    removeDateRow(idx) {
      if (this.form.dates.length > 1) this.form.dates.splice(idx, 1);
    },
    formatDate(dt) {
      return dt ? new Date(dt).toLocaleDateString() : "—";
    },
    getStatusColor(s) {
      return (
        {
          pending: "warning",
          approved: "success",
          rejected: "error",
          returned: "info",
          cancelled: "grey",
        }[s] || "default"
      );
    },
    submit() {
      const options = {
        forceFormData: true,
        onSuccess: () => {
          this.dialog = false;
          this.clearSelection();
        },
      };

      if (this.isEditing) {
        // PUT route; file uploads need a multipart POST with method spoofing.
        this.form
          .transform((data) => ({ ...data, _method: "put" }))
          .post(route("leave.applications.update", this.selectedId), options);
      } else {
        this.form
          .transform((data) => data)
          .post(route("leave.applications.store"), options);
      }
    },
    viewApplication(app) {
      router.visit(
        route("leave.applications.show", { leaveApplication: app.id })
      );
    },
    confirmCancel(app) {
      this.applicationToCancel = app;
      this.cancelDialog = true;
    },
    cancelApplication() {
      if (!this.applicationToCancel) return;
      this.cancelling = true;
      router.post(
        route("leave.applications.cancel", {
          leaveApplication: this.applicationToCancel.id,
        }),
        {},
        {
          preserveScroll: true,
          onFinish: () => {
            this.cancelling = false;
            this.cancelDialog = false;
            this.applicationToCancel = null;
          },
        }
      );
    },
  },
};
</script>

<style scoped>
.custom-toggle :deep(.v-btn--active) {
  background-color: rgb(var(--v-theme-primary)) !important;
  color: rgb(var(--v-theme-on-primary)) !important;
}

.custom-toggle :deep(.v-btn--active .v-icon) {
  color: rgb(var(--v-theme-on-primary)) !important;
}

.calendar-header-grid,
.calendar-body-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 1px;
  background-color: rgba(var(--v-border-color), var(--v-border-opacity));
}

.calendar-day-header {
  background-color: rgb(var(--v-theme-surface));
  text-align: center;
  padding: 8px 4px;
  font-weight: 600;
  font-size: 0.75rem;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.calendar-day-cell {
  background-color: rgb(var(--v-theme-surface));
  min-height: 110px;
  display: flex;
  flex-direction: column;
  transition: all 0.15s ease;
  position: relative;
  cursor: pointer;
}

.calendar-day-cell:hover {
  background-color: rgba(var(--v-theme-primary), 0.04);
}

.calendar-day-cell.selected-cell {
  background-color: rgba(var(--v-theme-primary), 0.12) !important;
  box-shadow: inset 0 0 0 2px rgb(var(--v-theme-primary));
}

.calendar-day-cell.off-month {
  background-color: rgba(var(--v-theme-on-surface), 0.03);
  opacity: 0.5;
}

.calendar-day-cell.is-today {
  background-color: rgba(var(--v-theme-primary), 0.06);
}

.day-number {
  font-size: 0.8rem;
  font-weight: 500;
}

.add-btn {
  opacity: 0;
  transition: opacity 0.15s ease;
}

.calendar-day-cell:hover .add-btn {
  opacity: 1;
}

.event-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 0 4px;
  overflow-y: auto;
  max-height: 75px;
}

.event-chip {
  font-size: 0.7rem;
  padding: 2px 6px;
  border-radius: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  line-height: 1.2;
}

.mobile-dots {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 3px;
  flex-wrap: wrap;
}

.dot-indicator {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

@media (max-width: 600px) {
  .calendar-day-cell {
    min-height: 55px;
  }
  .add-btn {
    display: none;
  }
}
</style>