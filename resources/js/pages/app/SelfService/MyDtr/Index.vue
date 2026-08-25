<!--
  This page will allow user to view their timesheet and apply for correction of their timesheet.
  Process will be as follows:
  1. User will select the month and year of the timesheet they want to view.
  2. User will see the timesheet with the following columns:
    - Date
    - Day
    - AM In
    - AM Out
    - PM In
    - PM Out
    - UT
    - OT
  3. User will be able to edit the timesheet by clicking on the cell of the timesheet.
  4. User will be able to submit the timesheet by clicking on the submit button.
  5. User will be able to view the summary of the timesheet.
-->

<template>
  <v-row>
    <v-col cols="12" md="12">
      <v-form>
        <v-card rounded="lg" elevation="2">
          <v-card-text>
            <!-- Filters and Controls -->
            <div
              class="d-flex flex-wrap align-center justify-space-between mb-6 pa-4 bg-grey-lighten-5 rounded-lg"
            >
              <div class="d-flex align-center gap-4">
                <v-select
                  v-model="form.selectedMonth"
                  :items="months"
                  item-title="name"
                  item-value="value"
                  label="Month"
                  variant="outlined"
                  density="compact"
                  style="min-width: 200px"
                  hide-details
                ></v-select>

                <v-select
                  v-model="form.selectedYear"
                  :items="years"
                  label="Year"
                  variant="outlined"
                  density="compact"
                  style="min-width: 200px"
                  hide-details
                ></v-select>

                <v-btn
                  color="starbucks-green"
                  variant="tonal"
                  prepend-icon="mdi-refresh"
                  @click="loadTimesheetData()"
                  min-width="120"
                  rounded="xl"
                  :loading="loadingData"
                >
                  Load Data
                </v-btn>
              </div>

              <!-- <div class="d-flex align-center gap-6">
                <div class="text-center">
                  <div class="text-caption text-grey-600">
                    Total Working Days
                  </div>
                  <div class="text-h6 font-weight-bold text-primary">
                    {{ totalWorkingDays }}
                  </div>
                </div>
                <div class="text-center">
                  <div class="text-caption text-grey-600">Total Hours</div>
                  <div class="text-h6 font-weight-bold text-success">
                    {{ totalHours }}
                  </div>
                </div>
                <div class="text-center">
                  <div class="text-caption text-grey-600">Overtime</div>
                  <div class="text-h6 font-weight-bold text-warning">
                    {{ totalOT }}
                  </div>
                </div>
              </div> -->
            </div>

            <!-- Timesheet Table -->
            <div class="bg-white rounded-lg">
              <!-- Summary Section -->
              <v-card variant="outlined" class="mt-6 mb-6">
                <v-card-text class="pa-4">
                  <h4 class="text-h6 font-weight-bold mb-4">Monthly Summary</h4>
                  <div class="d-flex flex-wrap gap-6">
                    <div class="d-flex align-center gap-2">
                      <v-icon color="success" size="small"
                        >mdi-check-circle</v-icon
                      >
                      <span class="text-body-2"
                        >Present Days: <strong>{{ presentDays }}</strong></span
                      >
                    </div>
                    <!-- <div class="d-flex align-center gap-2">
                      <v-icon color="error" size="small"
                        >mdi-close-circle</v-icon
                      >
                      <span class="text-body-2"
                        >Absent Days: <strong>{{ absentDays }}</strong></span
                      >
                    </div> -->
                    <div class="d-flex align-center gap-2">
                      <v-icon color="warning" size="small"
                        >mdi-clock-outline</v-icon
                      >
                      <span class="text-body-2"
                        >Late Days: <strong>{{ lateDays }}</strong></span
                      >
                    </div>
                    <div class="d-flex align-center gap-2">
                      <v-icon color="info" size="small"
                        >mdi-calendar-weekend</v-icon
                      >
                      <span class="text-body-2"
                        >Weekend Work:
                        <strong>{{ weekendDaysWorked }}</strong></span
                      >
                    </div>
                  </div>
                </v-card-text>
              </v-card>

              <div class="overflow-x-auto">
                <v-table class="rounded-lg">
                  <!-- Table Header -->
                  <thead>
                    <tr class="bg-green-darken-3 text-white">
                      <th
                        colspan="2"
                        class="border border-black p-3 text-center font-semibold text-white"
                      >
                        WORKING
                      </th>
                      <th
                        colspan="2"
                        class="border border-black p-3 text-center font-semibold text-white"
                      >
                        AM
                      </th>
                      <th
                        colspan="2"
                        class="border border-black p-3 text-center font-semibold text-white"
                      >
                        PM
                      </th>
                      <th
                        colspan="2"
                        class="border border-black p-3 text-center font-semibold text-white"
                      >
                        OVERTIME
                      </th>
                      <th
                        colspan="2"
                        class="border border-black p-3 text-center font-semibold text-white"
                      >
                        HOURS
                      </th>
                      <th
                        class="border border-black p-3 text-center font-semibold text-white"
                      >
                        Event / Docs
                      </th>
                    </tr>
                    <tr class="bg-grey-lighten-4">
                      <th
                        class="border border-black p-2 text-center text-sm font-medium"
                      >
                        Date
                      </th>
                      <th
                        class="border border-black p-2 text-center text-sm font-medium"
                      >
                        Days
                      </th>
                      <th
                        class="border border-black p-2 text-center text-sm font-medium"
                      >
                        Check In
                      </th>
                      <th
                        class="border border-black p-2 text-center text-sm font-medium"
                      >
                        Break Out
                      </th>
                      <th
                        class="border border-black p-2 text-center text-sm font-medium"
                      >
                        Break In
                      </th>
                      <th
                        class="border border-black p-2 text-center text-sm font-medium"
                      >
                        Check Out
                      </th>
                      <th
                        class="border border-black p-2 text-center text-sm font-medium"
                      >
                        Overtime In
                      </th>
                      <th
                        class="border border-black p-2 text-center text-sm font-medium"
                      >
                        Overtime Out
                      </th>
                      <th
                        class="border border-black p-2 text-center text-sm font-medium"
                      >
                        Undertime
                      </th>
                      <th
                        class="border border-black p-2 text-center text-sm font-medium"
                      >
                        Overtime
                      </th>
                      <th
                        class="border border-black p-2 text-center text-sm font-medium"
                      >
                        Event / Docs
                      </th>
                    </tr>
                  </thead>

                  <!-- Table Body -->
                  <tbody>
                    <tr
                      v-for="(day, index) in localTimeSheetData"
                      :key="index"
                      class="hover:bg-grey-lighten-5 transition-colors"
                      :class="{ 'bg-grey-lighten-4': isWeekend(day.day) }"
                    >
                      <!-- Date -->
                      <td
                        class="border border-black p-2 text-center text-sm font-medium"
                      >
                        {{ day.date }}
                      </td>

                      <!-- Day -->
                      <td
                        class="border border-black p-2 text-center text-sm"
                        :class="getDayClass(day.day)"
                      >
                        {{ day.day }}
                      </td>

                      <!-- 1. Official Event priority (Handles AM, PM, Custom, and Whole Day) -->
                      <template v-if="day.event && day.event.coverage === 'am'">
                        <td
                          colspan="2"
                          class="border border-black p-2 text-center text-sm font-semibold bg-green-lighten-5 text-green-darken-4 text-uppercase cursor-pointer hover:bg-green-lighten-4"
                          @click="openEventDialog(day)"
                        >
                          <div class="d-flex align-center justify-center">
                            <v-icon
                              size="14"
                              color="starbucks-green"
                              class="mr-1"
                              >mdi-map-marker-distance</v-icon
                            >
                            {{
                              formatEventType(
                                day.event.attendance_log_event_type?.name ||
                                  day.event.type
                              )
                            }}
                            (AM)
                          </div>
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.break_in || "-" }}
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.check_out || "-" }}
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.overtime_in || "-" }}
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.overtime_out || "-" }}
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <span class="text-error font-weight-medium">{{
                            day.ut || "-"
                          }}</span>
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <span class="text-warning font-weight-medium">{{
                            day.ot || "-"
                          }}</span>
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <div class="d-flex align-center justify-center gap-1">
                            <v-btn
                              icon="mdi-trash-can"
                              size="x-small"
                              variant="tonal"
                              color="error"
                              @click.stop="confirmDeleteEvent(day.event)"
                            ></v-btn>
                          </div>
                        </td>
                      </template>

                      <template
                        v-else-if="day.event && day.event.coverage === 'pm'"
                      >
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.check_in || "-" }}
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.break_out || "-" }}
                        </td>
                        <td
                          colspan="2"
                          class="border border-black p-2 text-center text-sm font-semibold bg-green-lighten-5 text-green-darken-4 text-uppercase cursor-pointer hover:bg-green-lighten-4"
                          @click="openEventDialog(day)"
                        >
                          <div class="d-flex align-center justify-center">
                            <v-icon
                              size="14"
                              color="starbucks-green"
                              class="mr-1"
                              >mdi-map-marker-distance</v-icon
                            >
                            {{
                              formatEventType(
                                day.event.attendance_log_event_type?.name ||
                                  day.event.type
                              )
                            }}
                            (PM)
                          </div>
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.overtime_in || "-" }}
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.overtime_out || "-" }}
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <span class="text-error font-weight-medium">{{
                            day.ut || "-"
                          }}</span>
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <span class="text-warning font-weight-medium">{{
                            day.ot || "-"
                          }}</span>
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <div class="d-flex align-center justify-center gap-1">
                            <v-btn
                              icon="mdi-trash-can"
                              size="x-small"
                              variant="tonal"
                              color="error"
                              @click.stop="confirmDeleteEvent(day.event)"
                            ></v-btn>
                          </div>
                        </td>
                      </template>

                      <template v-else-if="day.event && day.event.coverage === 'custom'">
                      <template v-for="(col, index) in getCustomColumns(day)" :key="index">
                        <td
                          v-if="col.type === 'event'"
                          :colspan="col.colspan"
                          class="border border-black p-2 text-center text-sm font-semibold bg-green-lighten-5 text-green-darken-4 text-uppercase cursor-pointer hover:bg-green-lighten-4"
                          @click="openEventDialog(day)"
                        >
                          {{ day.event.type }} | {{ day.event.start_time }}-{{ day.event.end_time }}
                        </td>

                        <td
                          v-else
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day[col.key] || "-" }}
                        </td>
                      </template>

                      <td class="border border-black p-2 text-center text-sm text-black font-weight-medium">
                        {{ day.overtime_in || "-" }}
                      </td>

                      <td class="border border-black p-2 text-center text-sm text-black font-weight-medium">
                        {{ day.overtime_out || "-" }}
                      </td>

                      <td class="border border-black p-2 text-center text-sm">
                        <span class="text-error font-weight-medium">{{ day.ut || "-" }}</span>
                      </td>

                      <td class="border border-black p-2 text-center text-sm">
                        <span class="text-warning font-weight-medium">{{ day.ot || "-" }}</span>
                      </td>
                    </template>

                      <template v-else-if="day.event">
                        <td
                          colspan="4"
                          class="border border-black p-2 text-center text-sm font-semibold bg-green-lighten-5 text-green-darken-4 cursor-pointer hover:bg-green-lighten-4"
                          @click="openEventDialog(day)"
                        >
                          <div class="d-flex align-center justify-center gap-2">
                            <v-icon size="18" color="starbucks-green"
                              >mdi-map-marker-distance</v-icon
                            >
                            <span class="text-uppercase tracking-wider">
                              {{
                                formatEventType(
                                  day.event.attendance_log_event_type?.name ||
                                    day.event.type
                                )
                              }}
                            </span>
                          </div>
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.overtime_in || "-" }}
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.overtime_out || "-" }}
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <span class="text-error font-weight-medium">{{
                            day.ut || "-"
                          }}</span>
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <span class="text-warning font-weight-medium">{{
                            day.ot || "-"
                          }}</span>
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <div class="d-flex align-center justify-center gap-1">
                            <v-btn
                              icon="mdi-trash-can"
                              size="x-small"
                              variant="tonal"
                              color="error"
                              @click.stop="confirmDeleteEvent(day.event)"
                            ></v-btn>
                          </div>
                        </td>
                      </template>

                      <!-- 2. University Activity priority -->
                      <template
                        v-else-if="
                          day.university_activity &&
                          day.university_activity.meridian === 'AM' &&
                          (!hasDtrRecord(day) ||
                            (day.total_rendered_minutes || 0) <
                              FULL_DAY_MINUTES_THRESHOLD)
                        "
                      >
                        <td
                          colspan="2"
                          class="border border-black p-2 text-center text-sm font-semibold bg-green-lighten-5 text-green-darken-4 text-uppercase"
                        >
                          {{ day.university_activity.label }} (AM)
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.break_in || "-" }}
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.check_out || "-" }}
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.overtime_in || "-" }}
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.overtime_out || "-" }}
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <span class="text-error font-weight-medium">{{
                            day.ut || "-"
                          }}</span>
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <span class="text-warning font-weight-medium">{{
                            day.ot || "-"
                          }}</span>
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <v-btn
                            size="small"
                            color="starbucks-green"
                            rounded="xl"
                            @click="openEventDialog(day)"
                          >
                            Log Official Event
                          </v-btn>
                        </td>
                      </template>

                      <template
                        v-else-if="
                          day.university_activity &&
                          day.university_activity.meridian === 'PM' &&
                          (!hasDtrRecord(day) ||
                            (day.total_rendered_minutes || 0) <
                              FULL_DAY_MINUTES_THRESHOLD)
                        "
                      >
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.check_in || "-" }}
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.break_out || "-" }}
                        </td>
                        <td
                          colspan="2"
                          class="border border-black p-2 text-center text-sm font-semibold bg-green-lighten-5 text-green-darken-4 text-uppercase"
                        >
                          <div class="d-flex align-center justify-center">
                            {{ day.university_activity.label }} (PM)
                          </div>
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.overtime_in || "-" }}
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.overtime_out || "-" }}
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <span class="text-error font-weight-medium">{{
                            day.ut || "-"
                          }}</span>
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <span class="text-warning font-weight-medium">{{
                            day.ot || "-"
                          }}</span>
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <v-btn
                            size="small"
                            color="starbucks-green"
                            rounded="xl"
                            @click="openEventDialog(day)"
                          >
                            Log Official Event
                          </v-btn>
                        </td>
                      </template>

                      <template
                        v-else-if="
                          day.university_activity &&
                          (!hasDtrRecord(day) ||
                            (day.total_rendered_minutes || 0) <
                              FULL_DAY_MINUTES_THRESHOLD)
                        "
                      >
                        <td
                          colspan="4"
                          class="border border-black p-2 text-center text-sm font-semibold bg-green-lighten-5 text-green-darken-4 text-uppercase"
                        >
                          <div class="d-flex align-center justify-center">
                            {{ day.university_activity.label }}
                          </div>
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.overtime_in || "-" }}
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day.overtime_out || "-" }}
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <span class="text-error font-weight-medium">{{
                            day.ut || "-"
                          }}</span>
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <span class="text-warning font-weight-medium">{{
                            day.ot || "-"
                          }}</span>
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <v-btn
                            size="small"
                            color="starbucks-green"
                            rounded="xl"
                            @click="openEventDialog(day)"
                          >
                            Log Official Event
                          </v-btn>
                        </td>
                      </template>

                      <!-- 3. Leave priority -->
                      <template
                        v-else-if="
                          isOnLeave(day.date_full) && !hasDtrRecord(day)
                        "
                      >
                        <td
                          colspan="9"
                          class="border border-black border-r-2 p-2 text-center text-sm font-semibold text-uppercase cursor-pointer"
                          :class="
                            getLeaveStatusClass(
                              leaveByDate[day.date_full].status
                            )
                          "
                          @click="
                            openLeave(leaveByDate[day.date_full].view_link)
                          "
                        >
                          <div class="d-flex align-center justify-center gap-2">
                            <v-icon
                              size="18"
                              :color="
                                getLeaveStatusIconColor(
                                  leaveByDate[day.date_full].status
                                )
                              "
                              >mdi-calendar-check</v-icon
                            >
                            <span>{{ getLeaveLabel(day.date_full) }}</span>
                          </div>
                        </td>
                      </template>

                      <!-- 4. Holiday priority -->
                      <template
                        v-else-if="
                          isHoliday(day.date_full) && !hasDtrRecord(day)
                        "
                      >
                        <td
                          colspan="8"
                          class="border border-black border-r-2 p-2 text-center text-sm font-semibold bg-red-lighten-4 text-red-darken-4"
                        >
                          {{ isHoliday(day.date_full).name }}
                        </td>
                        <td class="border border-black p-2 text-center text-sm">
                          <v-btn
                            size="small"
                            color="starbucks-green"
                            rounded="xl"
                            @click="openEventDialog(day)"
                          >
                            Log Official Event
                          </v-btn>
                        </td>
                      </template>

                      <!-- 5. Normal Workday -->
                      <template v-else>
                        <td
                          class="border border-black p-2 text-center text-sm cursor-pointer hover:bg-blue-lighten-5"
                          @click="openEditDialog(day, index, 'check_in')"
                        >
                          <span :class="getTimeClass(day.check_in, true)">{{
                            day.check_in || "-"
                          }}</span>
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm cursor-pointer hover:bg-blue-lighten-5"
                          @click="openEditDialog(day, index, 'break_out')"
                        >
                          <span :class="getTimeClass(day.break_out)">{{
                            day.break_out || "-"
                          }}</span>
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm cursor-pointer hover:bg-blue-lighten-5"
                          @click="openEditDialog(day, index, 'break_in')"
                        >
                          <span :class="getTimeClass(day.break_in)">{{
                            day.break_in || "-"
                          }}</span>
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm cursor-pointer hover:bg-blue-lighten-5"
                          @click="openEditDialog(day, index, 'check_out')"
                        >
                          <span :class="getTimeClass(day.check_out)">{{
                            day.check_out || "-"
                          }}</span>
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm cursor-pointer hover:bg-blue-lighten-5"
                        >
                          <span :class="getTimeClass(day.overtime_in)">{{
                            day.overtime_in || "-"
                          }}</span>
                        </td>
                        <td
                          class="border border-black p-2 text-center text-sm cursor-pointer hover:bg-blue-lighten-5"
                        >
                          <span :class="getTimeClass(day.overtime_out)">{{
                            day.overtime_out || "-"
                          }}</span>
                        </td>
                        <!-- UT -->
                        <td class="border border-black p-2 text-center text-sm">
                          <span class="text-error font-weight-medium">{{
                            day.ut || "-"
                          }}</span>
                        </td>

                        <!-- OT -->
                        <td class="border border-black p-2 text-center text-sm">
                          <span class="text-warning font-weight-medium">{{
                            day.ot || "-"
                          }}</span>
                        </td>
                        <!-- Out-of-office event / supporting documents -->
                        <!-- Event / Docs Action Column -->
                        <td class="border border-black p-2 text-center text-sm">
                          <v-btn
                            v-if="!isOnLeave(day.date_full)"
                            size="small"
                            color="starbucks-green"
                            rounded="xl"
                            @click="openEventDialog(day)"
                          >
                            Log Official Event
                          </v-btn>
                          <span v-else class="text-caption text-grey-400">
                            —
                          </span>
                        </td>
                      </template>
                    </tr>
                  </tbody>
                </v-table>
              </div>
            </div>
          </v-card-text>
        </v-card>

        <!-- Out-of-office (official travel/business) event dialog -->
        <v-dialog v-model="eventDialog" max-width="650" persistent scrollable>
          <v-card rounded="xl" elevation="24">
            <!-- Premium Header -->
            <v-card-title class="pa-0 overflow-hidden">
              <div class="bg-starbucks-green text-white pa-6">
                <div class="d-flex align-center justify-space-between mb-2">
                  <div class="d-flex align-center gap-3">
                    <v-avatar color="white" size="48" class="elevation-4">
                      <v-icon color="starbucks-green" size="28"
                        >mdi-map-marker-distance</v-icon
                      >
                    </v-avatar>
                    <div>
                      <h3 class="text-h6 font-weight-black mb-0">
                        Official Travel / Business
                      </h3>
                      <div class="text-body-2 text-starbucks-green-lighten-4">
                        Document your out-of-office activities
                      </div>
                    </div>
                  </div>
                  <v-btn
                    icon="mdi-close"
                    variant="text"
                    color="white"
                    @click="closeEventDialog"
                  ></v-btn>
                </div>
              </div>
            </v-card-title>

            <v-card-text class="pa-6 pt-8 bg-grey-lighten-5">
              <v-form ref="eventFormRef" v-model="eventFormValid">
                <!-- Date Alert -->
                <v-alert
                  type="starbucks-green"
                  variant="tonal"
                  class="mb-6 rounded-lg border-opacity-25"
                  icon="mdi-calendar"
                >
                  <div class="d-flex flex-column">
                    <span class="text-overline line-height-1"
                      >Logging activity for:</span
                    >
                    <span class="text-h6 font-weight-bold">{{
                      formatDisplayDate(eventForm.date)
                    }}</span>
                  </div>
                </v-alert>

                <div class="d-flex flex-column gap-5">
                  <!-- Type and Category -->
                  <div
                    class="section-card pa-4 bg-white rounded-lg elevation-1"
                  >
                    <div
                      class="d-flex align-center mb-3 text-subtitle-2 font-weight-bold text-grey-700"
                    >
                      <v-icon start size="18" color="indigo">mdi-tag</v-icon>
                      Event Details
                    </div>
                    <v-select
                      v-model="eventForm.att_log_event_type_id"
                      :items="attendanceLogEventTypes"
                      item-title="name"
                      item-value="id"
                      label="Select Event Type"
                      variant="outlined"
                      density="comfortable"
                      :rules="[(v) => !!v || 'Please select an event type']"
                      prepend-inner-icon="mdi-format-list-bulleted-type"
                      class="mb-4"
                      hide-details="auto"
                    >
                      <template v-slot:item="{ props, item }">
                        <v-list-item
                          v-bind="props"
                          :subtitle="item.raw.description"
                        ></v-list-item>
                      </template>
                    </v-select>

                    <v-textarea
                      v-model="eventForm.remarks"
                      label="Detailed Remarks / Purpose"
                      aria-label="Remarks / Purpose"
                      variant="outlined"
                      rows="3"
                      auto-grow
                      prepend-inner-icon="mdi-text-box-edit"
                      placeholder="Explain the purpose of this travel or official business..."
                      :rules="[
                        (v) => !!v || 'Remarks are required for justification',
                      ]"
                      class="mb-0"
                      hide-details="auto"
                    ></v-textarea>

                    <div class="mt-4">
                      <div
                        class="text-caption font-weight-bold text-grey-600 mb-2"
                      >
                        Time Coverage
                      </div>
                      <v-radio-group
                        v-model="eventForm.coverage"
                        inline
                        hide-details
                        density="comfortable"
                        color="starbucks-green"
                      >
                        <v-radio label="Whole Day" value="whole_day"></v-radio>
                        <v-radio label="Morning (AM)" value="am"></v-radio>
                        <v-radio label="Afternoon (PM)" value="pm"></v-radio>
                        <v-radio label="Custom Time" value="custom"></v-radio>
                      </v-radio-group>
                    </div>

                    <v-expand-transition>
                      <v-row
                        v-if="eventForm.coverage === 'custom'"
                        class="mt-2"
                        dense
                      >
                        <v-col cols="12" sm="6">
                          <v-text-field
                            v-model="eventForm.start_time"
                            label="Start Time"
                            type="time"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-clock-start"
                            :rules="[(v) => !!v || 'Start time is required']"
                          ></v-text-field>
                        </v-col>
                        <v-col cols="12" sm="6">
                          <v-text-field
                            v-model="eventForm.end_time"
                            label="End Time"
                            type="time"
                            variant="outlined"
                            density="comfortable"
                            prepend-inner-icon="mdi-clock-end"
                            :rules="[(v) => !!v || 'End time is required']"
                          ></v-text-field>
                        </v-col>
                      </v-row>
                    </v-expand-transition>
                  </div>

                  <!-- Document Section -->
                  <div
                    class="section-card pa-4 bg-white rounded-lg elevation-1"
                  >
                    <div
                      class="d-flex align-center justify-space-between mb-4 text-subtitle-2 font-weight-bold text-grey-700"
                    >
                      <div class="d-flex align-center">
                        <v-icon start size="18" color="indigo"
                          >mdi-paperclip</v-icon
                        >
                        Supporting Documents
                      </div>
                      <v-chip size="x-small" color="grey" variant="flat">
                        {{
                          (existingDocuments?.length || 0) +
                          (eventForm.documents?.length || 0)
                        }}
                        Files
                      </v-chip>
                    </div>

                    <!-- Existing Files -->
                    <div v-if="existingDocuments?.length > 0" class="mb-4">
                      <div class="text-caption text-grey-600 mb-2">
                        Already Uploaded:
                      </div>
                      <v-list
                        density="compact"
                        class="bg-grey-lighten-4 rounded-lg"
                      >
                        <v-list-item
                          v-for="file in existingDocuments"
                          :key="file.id"
                          :title="file.file_name"
                          prepend-icon="mdi-file-document-outline"
                        >
                          <template v-slot:append>
                            <div class="d-flex gap-1">
                              <v-tooltip text="View Document" location="top">
                                <template v-slot:activator="{ props }">
                                  <v-btn
                                    v-bind="props"
                                    icon="mdi-eye"
                                    size="x-small"
                                    variant="text"
                                    color="primary"
                                    :href="file.url"
                                    target="_blank"
                                    title="View Document"
                                  ></v-btn>
                                </template>
                              </v-tooltip>
                              <v-tooltip text="Delete Document" location="top">
                                <template v-slot:activator="{ props }">
                                  <v-btn
                                    v-bind="props"
                                    icon="mdi-trash-can-outline"
                                    size="x-small"
                                    variant="text"
                                    color="error"
                                    @click="confirmDeleteDocument(file)"
                                    :loading="deletingDocumentId === file.id"
                                  ></v-btn>
                                </template>
                              </v-tooltip>
                            </div>
                          </template>
                        </v-list-item>
                      </v-list>
                    </div>

                    <!-- New Upload Area -->
                    <div
                      class="file-upload-zone border-dashed pa-4 rounded-lg text-center"
                      :class="{ 'bg-blue-lighten-5': isDragging }"
                      @dragover.prevent="isDragging = true"
                      @dragleave.prevent="isDragging = false"
                      @drop.prevent="handleFileDrop"
                    >
                      <v-file-input
                        v-model="eventForm.documents"
                        label="Attach new files"
                        variant="outlined"
                        multiple
                        show-size
                        density="comfortable"
                        prepend-icon=""
                        prepend-inner-icon="mdi-cloud-upload"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                        hint="Max 10MB per file: PDF, Images, Word"
                        persistent-hint
                        class="mb-0"
                      ></v-file-input>
                    </div>
                  </div>
                </div>
              </v-form>
            </v-card-text>

            <v-divider></v-divider>

            <v-card-actions class="pa-6 bg-white">
              <v-spacer></v-spacer>
              <v-btn
                variant="outlined"
                color="grey-darken-1"
                @click="closeEventDialog"
                class="px-6 rounded-lg"
                min-width="120"
              >
                Cancel
              </v-btn>
              <v-btn
                color="starbucks-green"
                variant="flat"
                :loading="eventForm.processing"
                @click="submitEvent"
                class="px-8 rounded-lg"
                min-width="120"
                prepend-icon="mdi-check"
              >
                Save Event
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>

        <!-- Delete Confirmation Dialog -->
        <v-dialog v-model="deleteDialog" max-width="400">
          <v-card rounded="xl">
            <v-card-title class="text-h6 pt-6 px-6">
              Delete Official Event?
            </v-card-title>
            <v-card-text class="px-6">
              Are you sure you want to remove this log official event? Any
              associated documents will also be deleted.
            </v-card-text>
            <v-card-actions class="pb-6 px-6">
              <v-spacer></v-spacer>
              <v-btn
                variant="text"
                color="grey-darken-1"
                @click="deleteDialog = false"
              >
                No, Keep it
              </v-btn>
              <v-btn
                color="error"
                variant="flat"
                class="px-6 rounded-lg"
                :loading="deleting"
                @click="deleteEvent"
              >
                Yes, Delete
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>

        <!-- Delete Document Confirmation Dialog -->
        <v-dialog v-model="deleteDocumentDialog" max-width="400">
          <v-card rounded="xl">
            <v-card-title class="text-h6 pt-6 px-6">
              Delete Supporting Document?
            </v-card-title>
            <v-card-text class="px-6">
              Are you sure you want to remove this document? This action cannot
              be undone.
            </v-card-text>
            <v-card-actions class="pb-6 px-6">
              <v-spacer></v-spacer>
              <v-btn
                variant="text"
                color="grey-darken-1"
                @click="deleteDocumentDialog = false"
              >
                Cancel
              </v-btn>
              <v-btn
                color="error"
                variant="flat"
                class="px-6 rounded-lg"
                :loading="deletingDocument"
                @click="deleteDocument"
              >
                Yes, Delete
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>
      </v-form>
    </v-col>
  </v-row>
</template>

<script setup>
import {
  ref,
  reactive,
  computed,
  watch,
  onMounted,
  onBeforeUnmount,
} from "vue";
import { useForm, router, usePage } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import DynamicTabs from "@/components/DynamicTabs.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";

// Define layout
defineOptions({ layout: SidebarLayout });

// Constants
const POLLING_INTERVAL_MS = 5000;
const LATE_THRESHOLD_HOUR = 8;
const FULL_DAY_MINUTES_THRESHOLD = 420;

// Props
const props = defineProps({
  selectedMonth: Number,
  selectedYear: [Number, String],
  timeSheetData: {
    type: Array,
    default: () => [],
  },
  holidays: {
    type: Array,
    default: () => [],
  },
  presentDays: {
    type: Number,
    default: 0,
  },
  lateDays: {
    type: Number,
    default: 0,
  },
  weekendDaysWorked: {
    type: Number,
    default: 0,
  },
  attendanceLogEventTypes: {
    type: Array,
    default: () => [],
  },
  leaveSummary: {
    type: Object,
    default: () => ({}),
  },
  myLeaveApplications: {
    type: [Object, Array],
    default: () => ({}),
  },
});

// Composables
const toast = useToast();
const page = usePage();

// State
const loadingData = ref(false);
const localTimeSheetData = ref(JSON.parse(JSON.stringify(props.timeSheetData)));
const pollingInterval = ref(null);

const form = useForm({
  selectedMonth: props.selectedMonth || new Date().getMonth() + 1,
  selectedYear: props.selectedYear || new Date().getFullYear(),
});

// Event Dialog State
const eventDialog = ref(false);
const eventFormValid = ref(false);
const eventFormRef = ref(null);
const isDragging = ref(false);
const existingDocuments = ref([]);
const deleteDialog = ref(false);
const eventToDelete = ref(null);
const deleting = ref(false);
const deleteDocumentDialog = ref(false);
const documentToDelete = ref(null);
const deletingDocument = ref(false);
const deletingDocumentId = ref(null);

const eventForm = useForm({
  date: null,
  att_log_event_type_id: null,
  coverage: "whole_day",
  start_time: null,
  end_time: null,
  remarks: "",
  documents: [],
});

// Edit Dialog State (Currently disabled for phase 1)
const editDialog = ref(false);
const editFormValid = ref(false);
const editTimeValue = ref("");
const editReason = ref("");
const selectedDay = ref(null);
const selectedTimeField = ref("");
const selectedDayIndex = ref(-1);
const saving = ref(false);
const showSuccessMessage = ref(false);

const timeRules = [
  (v) => !!v || "Time is required",
  (v) =>
    /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/.test(v) ||
    "Please enter a valid time (HH:MM)",
];

const months = [
  { name: "January", value: 1 },
  { name: "February", value: 2 },
  { name: "March", value: 3 },
  { name: "April", value: 4 },
  { name: "May", value: 5 },
  { name: "June", value: 6 },
  { name: "July", value: 7 },
  { name: "August", value: 8 },
  { name: "September", value: 9 },
  { name: "October", value: 10 },
  { name: "November", value: 11 },
  { name: "December", value: 12 },
];

const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);

// Computed
const currentMonth = computed(() => new Date().getMonth() + 1);
const currentYear = computed(() => new Date().getFullYear());

const leaveApplications = computed(
  () => props.myLeaveApplications?.data ?? props.myLeaveApplications ?? []
);

const leaveByDate = computed(() => {
  const map = {};
  leaveApplications.value.forEach((leave) => {
    if (!leave.from || !leave.to) return;
    const start = new Date(leave.from);
    const end = new Date(leave.to);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return;

    const cur = new Date(start);
    while (cur <= end) {
      const year = cur.getFullYear();
      const month = String(cur.getMonth() + 1).padStart(2, "0");
      const day = String(cur.getDate()).padStart(2, "0");
      const key = `${year}-${month}-${day}`;
      if (!map[key]) map[key] = leave;
      cur.setDate(cur.getDate() + 1);
    }
  });
  return map;
});

// Watchers
watch(
  () => props.timeSheetData,
  (newVal) => {
    console.log("[MyDtr] Timesheet data updated from props");
    localTimeSheetData.value = JSON.parse(JSON.stringify(newVal));

    if (eventDialog.value && eventForm.date) {
      const updatedDay = localTimeSheetData.value.find(
        (d) => d.date_full === eventForm.date
      );
      existingDocuments.value = updatedDay?.event?.documents || [];
    }
  },
  { deep: true }
);

// Methods
const fetchLatestDtr = async () => {
  try {
    const response = await fetch("my-dtr/poll");
    const newRecords = await response.json();
    if (newRecords.length > 0) {
      console.log("[MyDtr] New DTR records fetched:", newRecords);
      updateTimeSheetData(newRecords);
    }
  } catch (error) {
    console.error("[MyDtr] Error fetching latest DTR:", error);
  }
};

const updateTimeSheetData = (newRecords) => {
  const flatRecords = Array.isArray(newRecords[0]) ? newRecords[0] : newRecords;

  flatRecords.forEach((record) => {
    const existingDay = localTimeSheetData.value.find(
      (day) => day.date_full === record.auth_date
    );
    let directionKey = null;

    switch (record.direction.toLowerCase()) {
      case "check in":
        directionKey = "check_in";
        break;
      case "break out":
        directionKey = "break_out";
        break;
      case "break in":
        directionKey = "break_in";
        break;
      case "check out":
        directionKey = "check_out";
        break;
      case "overtime in":
        directionKey = "overtime_in";
        break;
      case "overtime out":
        directionKey = "overtime_out";
        break;
      default:
        return;
    }

    const formattedTime = record.auth_time
      ? record.auth_time.slice(0, 5)
      : null;

    if (existingDay) {
      existingDay[directionKey] = formattedTime;
    } else {
      localTimeSheetData.value.push({
        date_full: record.auth_date,
        day: "",
        check_in: directionKey === "check_in" ? formattedTime : null,
        break_out: directionKey === "break_out" ? formattedTime : null,
        break_in: directionKey === "break_in" ? formattedTime : null,
        check_out: directionKey === "check_out" ? formattedTime : null,
        ut: null,
        ot: null,
      });
    }
  });

  localTimeSheetData.value = [...localTimeSheetData.value];
};

const startPolling = () => {
  fetchLatestDtr();
  pollingInterval.value = setInterval(fetchLatestDtr, POLLING_INTERVAL_MS);
};

const stopPolling = () => {
  if (pollingInterval.value) clearInterval(pollingInterval.value);
};

const isHoliday = (dateFull) =>
  props.holidays.find((h) => h.date === dateFull) || null;

const hasDtrRecord = (day) =>
  day.check_in || day.break_out || day.break_in || day.check_out;

const isOnLeave = (dateFull) => !!leaveByDate.value[dateFull];

const getLeaveLabel = (dateFull) => {
  const leave = leaveByDate.value[dateFull];
  if (!leave) return "";
  const name = leave.leave?.name || "On Leave";
  return `${name} (${leave.status ?? "pending"})`;
};

const formatDisplayDate = (date) => {
  if (!date) return "";
  const d = new Date(date);
  if (Number.isNaN(d.getTime())) return date;
  return d.toLocaleDateString("en-US", {
    month: "long",
    day: "numeric",
    year: "numeric",
  });
};

const formatEventType = (type) => {
  if (!type) return "";
  return type
    .toString()
    .replace(/_/g, " ")
    .replace(/\b\w/g, (c) => c.toUpperCase());
};

const getLeaveStatusClass = (status) => {
  if (!status) return "bg-blue-lighten-5 text-blue-darken-4";

  const statusLower = status.toLowerCase();

  if (statusLower === "approved")
    return "bg-green-lighten-5 text-green-darken-4";
  if (statusLower === "for approval")
    return "bg-blue-lighten-5 text-blue-darken-4";
  if (statusLower === "for disapproval")
    return "bg-orange-lighten-5 text-orange-darken-4";
  if (statusLower === "disapproved")
    return "bg-red-lighten-5 text-red-darken-4";
  if (statusLower === "cancelled")
    return "bg-grey-lighten-3 text-grey-darken-3";

  // Default fallback
  return "bg-blue-lighten-5 text-blue-darken-4";
};

const getLeaveStatusIconColor = (status) => {
  if (!status) return "blue";
  const statusLower = status.toLowerCase();
  if (statusLower === "approved") return "green";
  if (statusLower === "for approval") return "blue";
  if (statusLower === "for disapproval") return "orange";
  if (statusLower === "disapproved") return "red";
  if (statusLower === "cancelled") return "grey";
  return "blue";
};

const openLeave = (link) => {
  if (link) {
    window.open(link, "_blank");
  }
};

const loadTimesheetData = () => {
  console.log(
    "[MyDtr] Loading timesheet data for:",
    form.selectedMonth,
    form.selectedYear
  );
  loadingData.value = true;
  router.get(
    route("self-service.my-dtr.index"),
    {
      selectedMonth: form.selectedMonth,
      selectedYear: form.selectedYear,
    },
    {
      preserveState: true,
      preserveScroll: true,
      only: [
        "timeSheetData",
        "presentDays",
        "lateDays",
        "weekendDaysWorked",
        "holidays",
      ],
      onSuccess: () => {
        toast.success("Record successfully loaded");
      },
      onFinish: () => {
        loadingData.value = false;
      },
    }
  );
};

const isWeekend = (day) => day === "Sat" || day === "Sun";

const getDayClass = (day) =>
  isWeekend(day) ? "text-grey-500 font-weight-medium" : "text-grey-700";

const getTimeClass = (time, isCheckIn = false) => {
  if (!time || time === "-") return "text-grey-400";
  if (isCheckIn) {
    const [hour, minute] = time.split(":").map(Number);
    if (
      hour > LATE_THRESHOLD_HOUR ||
      (hour === LATE_THRESHOLD_HOUR && minute > 0)
    ) {
      return "text-error font-weight-bold";
    }
  }
  return "text-black font-weight-medium";
};

const openEventDialog = (day) => {
  eventForm.date = day.date_full;
  eventForm.att_log_event_type_id = day.event?.att_log_event_type_id || null;
  eventForm.coverage = day.event?.coverage || "whole_day";
  eventForm.start_time = day.event?.start_time || null;
  eventForm.end_time = day.event?.end_time || null;
  eventForm.remarks = day.event?.remarks || "";
  eventForm.documents = [];
  existingDocuments.value = day.event?.documents || [];
  eventDialog.value = true;
};

const closeEventDialog = () => {
  eventDialog.value = false;
  existingDocuments.value = [];
  isDragging.value = false;
  eventForm.reset(
    "att_log_event_type_id",
    "remarks",
    "documents",
    "coverage",
    "start_time",
    "end_time"
  );
  if (eventFormRef.value) eventFormRef.value.resetValidation();
};

const handleFileDrop = (e) => {
  isDragging.value = false;
  const files = Array.from(e.dataTransfer.files);
  if (files.length > 0) eventForm.documents = files;
};

const submitEvent = () => {
  if (!eventFormValid.value) {
    if (eventFormRef.value) eventFormRef.value.validate();
    return;
  }
  if (!eventForm.date) {
    toast.error("Date is missing.");
    return;
  }

  console.log("[MyDtr] Submitting official event for:", eventForm.date);
  eventForm.post(route("self-service.my-dtr.events.store"), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      closeEventDialog();
      toast.success("Out-of-office event saved successfully.");
    },
    onError: (errors) => {
      const errorMessages = Object.values(errors).flat().join(" ");
      toast.error(errorMessages || "Failed to save event");
    },
  });
};

const confirmDeleteEvent = (event) => {
  eventToDelete.value = event;
  deleteDialog.value = true;
};

const deleteEvent = () => {
  if (!eventToDelete.value) return;
  deleting.value = true;
  console.log("[MyDtr] Deleting event ID:", eventToDelete.value.id);
  router.delete(
    route("self-service.my-dtr.events.destroy", { id: eventToDelete.value.id }),
    {
      onSuccess: () => {
        deleteDialog.value = false;
        eventToDelete.value = null;
        toast.success("Out-of-office event deleted.");
      },
      onError: (errors) => {
        const errorMessages = Object.values(errors).flat().join(" ");
        toast.error(errorMessages || "Failed to delete event");
      },
      onFinish: () => {
        deleting.value = false;
      },
    }
  );
};

const confirmDeleteDocument = (file) => {
  documentToDelete.value = file;
  deleteDocumentDialog.value = true;
};

const deleteDocument = () => {
  if (!documentToDelete.value) return;
  deletingDocument.value = true;
  deletingDocumentId.value = documentToDelete.value.id;
  console.log("[MyDtr] Deleting document ID:", documentToDelete.value.id);
  router.delete(
    route("self-service.my-dtr.documents.destroy", {
      id: documentToDelete.value.id,
    }),
    {
      onSuccess: () => {
        deleteDocumentDialog.value = false;
        documentToDelete.value = null;
        toast.success("Document deleted.");
      },
      onError: (errors) => {
        const errorMessages = Object.values(errors).flat().join(" ");
        toast.error(errorMessages || "Failed to delete document");
      },
      onFinish: () => {
        deletingDocument.value = false;
        deletingDocumentId.value = null;
      },
    }
  );
};

const getTimeFieldLabel = (field) => {
  const labels = {
    check_in: "Morning In",
    break_out: "Morning Out",
    break_in: "Afternoon In",
    check_out: "Afternoon Out",
  };
  return labels[field] || field;
};

const getCustomColumns = (day) => {
  const columns = [
    { key: "check_in", time: "08:00:00" },
    { key: "break_out", time: "12:00:00" },
    { key: "break_in", time: "13:00:00" },
    { key: "check_out", time: "17:00:00" },
  ];

  const start = day.event?.start_time;
  const end = day.event?.end_time;

  const coveredIndexes = columns
    .map((col, index) => ({ ...col, index }))
    .filter((col) => start <= col.time && end >= col.time)
    .map((col) => col.index);

  if (!coveredIndexes.length) {
    return columns;
  }

  const startIndex = coveredIndexes[0];
  const endIndex = coveredIndexes[coveredIndexes.length - 1];

  const result = [];

  for (let i = 0; i < columns.length; i++) {
    if (i === startIndex) {
      result.push({
        type: "event",
        colspan: endIndex - startIndex + 1,
      });

      i = endIndex;
    } else {
      result.push(columns[i]);
    }
  }

  return result;
};

// Polling setup
onMounted(() => {
  console.log("[MyDtr] Component mounted, starting polling");
  startPolling();
});

onBeforeUnmount(() => {
  console.log("[MyDtr] Component beforeUnmount, stopping polling");
  stopPolling();
});
</script>

<style scoped>
/* Additional custom styles for better table appearance */
table {
  font-family: "Courier New", monospace;
}

th,
td {
  min-width: 80px;
}

.cursor-pointer {
  cursor: pointer;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .overflow-x-auto {
    font-size: 0.75rem;
  }

  th,
  td {
    padding: 0.25rem;
    min-width: 60px;
  }
}

.section-card {
  transition: all 0.3s ease;
  border: 1px solid rgba(0, 0, 0, 0.05);
}

.section-card:hover {
  border-color: rgba(63, 81, 181, 0.3);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05) !important;
}

.file-upload-zone {
  border: 2px dashed #e0e0e0;
  transition: all 0.3s ease;
}

.file-upload-zone.bg-blue-lighten-5 {
  border-color: #3f51b5;
  background-color: #e8eaf6 !important;
}

.line-height-1 {
  line-height: 1;
}

.gap-3 {
  gap: 0.75rem;
}

.gap-5 {
  gap: 1.25rem;
}

.nav-tabs {
  height: auto !important;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  margin-bottom: 10px !important;
  border-radius: 8px;
}

.gap-4 {
  gap: 1rem;
}

.gap-6 {
  gap: 1.5rem;
}

.transition-colors {
  transition: background-color 0.2s ease;
}
</style>
