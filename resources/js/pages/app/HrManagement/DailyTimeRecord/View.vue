<template>
  <DailyTimeRecordTabs v-model:activeTab="activeTab" />
  <v-row>
    <v-col cols="12" md="12">
      <v-card rounded="xl" elevation="2">
        <div class="d-flex align-center justify-end ma-3 pa-3">
          <v-btn
            icon="mdi-printer"
            size="small"
            class="mr-2"
            @click="printSingleDtr(employee.data.id)"
            variant="tonal"
            color="starbucks-green"
          ></v-btn>
        </div>

        <div class="d-flex align-center ma-3 pa-3">
          <div class="profile-box mr-4 profile-hover">
            <v-img
              v-if="employee.data.photo"
              :src="`/storage/${employee.data.photo}`"
              alt="Profile Image"
              width="150"
              height="150"
              class="rounded-lg profile-image"
              cover
            ></v-img>
            <div
              v-else
              class="d-flex align-center justify-center bg-grey-lighten-2 rounded-lg profile-placeholder"
              style="width: 120px; height: 120px"
            >
              <v-icon size="40" color="grey-darken-2"> mdi-account </v-icon>
            </div>
          </div>
          <div class="v-card-title">
            <div class="d-flex align-center justify-space-between">
              <div class="d-flex align-center gap-2">
                <span class="text-h6 font-weight-bold">{{
                  employee.data.personal_information.full_name_asc
                }}</span>
              </div>
            </div>
          </div>
        </div>

        <v-card-text>
          <!-- Filters and Controls -->
          <div
            class="d-flex flex-wrap align-center justify-space-between mb-6 pa-4 bg-grey-lighten-5 rounded-xl"
          >
            <v-row>
              <v-col cols="12" sm="12" md="3">
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
                  rounded="lg"
                ></v-select>
              </v-col>
              <v-col cols="12" sm="12" md="3">
                <v-select
                  v-model="form.selectedYear"
                  :items="years"
                  label="Year"
                  variant="outlined"
                  density="compact"
                  style="min-width: 200px"
                  hide-details
                  rounded="lg"
                ></v-select>
              </v-col>
              <v-col cols="12" sm="12" md="3">
                <v-btn
                  color="starbucks-green"
                  variant="tonal"
                  prepend-icon="mdi-refresh"
                  @click="loadTimesheetData()"
                  class="w-100 w-md-auto"
                  min-width="120"
                  rounded="xl"
                >
                  Load Data
                </v-btn>
              </v-col>
            </v-row>

            <!-- <div class="d-flex align-center gap-4">
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




            </div> -->

            <!-- <div class="d-flex align-center gap-6">
              <div class="text-center">
                <div class="text-caption text-grey-600">Total Working Days</div>
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
            <v-card variant="outlined" class="mt-6 mb-6" rounded="lg">
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
                    <v-icon color="error" size="small">mdi-close-circle</v-icon>
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
                  </tr>
                </thead>

                <!-- Table Body -->
                <tbody>
                  <tr
                    v-for="(day, index) in timeSheetData"
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

                    <!-- Leave Application priority -->
                    <!-- Whole Day Leave -->
                    <template
                      v-if="
                        leaveByDate[day.date_full] &&
                        leaveByDate[day.date_full].duration === 'full_day' &&
                        !hasDtrRecord(day)
                      "
                    >
                      <td
                        colspan="8"
                        class="border border-black p-2 text-center text-sm font-semibold text-uppercase cursor-pointer"
                        :class="
                          getLeaveStatusClass(leaveByDate[day.date_full].status)
                        "
                        @click="openLeave(leaveByDate[day.date_full].view_link)"
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
                          <span>
                            {{ leaveByDate[day.date_full].leave_type }}
                            {{
                              leaveByDate[day.date_full].special_leave
                                ? `(${
                                    leaveByDate[day.date_full].special_leave
                                  })`
                                : ""
                            }}
                            <span class="text-caption ml-1 font-weight-bold"
                              >({{ leaveByDate[day.date_full].status }})</span
                            >
                          </span>
                        </div>
                      </td>
                    </template>

                    <!-- AM Leave -->
                    <template
                      v-else-if="
                        leaveByDate[day.date_full] &&
                        leaveByDate[day.date_full].duration === 'half_day_am' &&
                        (!hasDtrRecord(day) ||
                          (day.total_rendered_minutes || 0) <
                            FULL_DAY_MINUTES_THRESHOLD)
                      "
                    >
                      <td
                        colspan="2"
                        class="border border-black p-2 text-center text-sm font-semibold text-uppercase cursor-pointer"
                        :class="
                          getLeaveStatusClass(leaveByDate[day.date_full].status)
                        "
                        @click="openLeave(leaveByDate[day.date_full].view_link)"
                      >
                        <div class="d-flex flex-column align-center">
                          <span
                            >{{
                              leaveByDate[day.date_full].leave_type
                            }}
                            (AM)</span
                          >
                          <span class="text-caption font-weight-bold"
                            >({{ leaveByDate[day.date_full].status }})</span
                          >
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
                    </template>

                    <!-- PM Leave -->
                    <template
                      v-else-if="
                        leaveByDate[day.date_full] &&
                        leaveByDate[day.date_full].duration === 'half_day_pm' &&
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
                        class="border border-black p-2 text-center text-sm font-semibold text-uppercase cursor-pointer"
                        :class="
                          getLeaveStatusClass(leaveByDate[day.date_full].status)
                        "
                        @click="openLeave(leaveByDate[day.date_full].view_link)"
                      >
                        <div class="d-flex flex-column align-center">
                          <span
                            >{{
                              leaveByDate[day.date_full].leave_type
                            }}
                            (PM)</span
                          >
                          <span class="text-caption font-weight-bold"
                            >({{ leaveByDate[day.date_full].status }})</span
                          >
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
                    </template>

                    <!-- Attendance Log priority (Official Business, etc) -->
                    <template
                      v-else-if="
                        day.event && day.event.coverage === 'whole_day'
                      "
                    >
                      <td
                        colspan="8"
                        class="border border-black p-2 text-center text-sm font-semibold bg-green-lighten-5 text-green-darken-4 text-uppercase"
                      >
                        <div class="d-flex align-center justify-center gap-2">
                          <v-icon size="18" color="green"
                            >mdi-map-marker-distance</v-icon
                          >
                          <span>{{ day.event.type }}</span>

                          <!-- Document Viewer -->
                          <v-menu
                            v-if="day.event?.documents?.length"
                            location="bottom end"
                          >
                            <template v-slot:activator="{ props }">
                              <v-btn
                                v-bind="props"
                                icon="mdi-paperclip"
                                size="x-small"
                                variant="text"
                                color="primary"
                                class="ml-1"
                              ></v-btn>
                            </template>
                            <v-list density="compact">
                              <v-list-item
                                v-for="file in day.event.documents"
                                :key="file.id"
                                :href="file.url"
                                target="_blank"
                                prepend-icon="mdi-file-document-outline"
                              >
                                <v-list-item-title class="text-caption">{{
                                  file.file_name
                                }}</v-list-item-title>
                              </v-list-item>
                            </v-list>
                          </v-menu>
                        </div>
                      </td>
                    </template>

                    <template
                      v-else-if="day.event && day.event.coverage === 'am'"
                    >
                      <td
                        colspan="2"
                        class="border border-black p-2 text-center text-sm font-semibold bg-green-lighten-5 text-green-darken-4 text-uppercase"
                      >
                        <div class="d-flex align-center justify-center gap-2">
                          <v-icon size="18" color="green"
                            >mdi-map-marker-distance</v-icon
                          >
                          <span>{{ day.event.type }} (AM)</span>

                          <!-- Document Viewer -->
                          <v-menu
                            v-if="day.event?.documents?.length"
                            location="bottom end"
                          >
                            <template v-slot:activator="{ props }">
                              <v-btn
                                v-bind="props"
                                icon="mdi-paperclip"
                                size="x-small"
                                variant="text"
                                color="primary"
                                class="ml-1"
                              ></v-btn>
                            </template>
                            <v-list density="compact">
                              <v-list-item
                                v-for="file in day.event.documents"
                                :key="file.id"
                                :href="file.url"
                                target="_blank"
                                prepend-icon="mdi-file-document-outline"
                              >
                                <v-list-item-title class="text-caption">{{
                                  file.file_name
                                }}</v-list-item-title>
                              </v-list-item>
                            </v-list>
                          </v-menu>
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
                        class="border border-black p-2 text-center text-sm font-semibold bg-green-lighten-5 text-green-darken-4 text-uppercase"
                      >
                        <div class="d-flex align-center justify-center gap-2">
                          <v-icon size="18" color="green"
                            >mdi-map-marker-distance</v-icon
                          >
                          <span>{{ day.event.type }} (PM)</span>

                          <!-- Document Viewer -->
                          <v-menu
                            v-if="day.event?.documents?.length"
                            location="bottom end"
                          >
                            <template v-slot:activator="{ props }">
                              <v-btn
                                v-bind="props"
                                icon="mdi-paperclip"
                                size="x-small"
                                variant="text"
                                color="primary"
                                class="ml-1"
                              ></v-btn>
                            </template>
                            <v-list density="compact">
                              <v-list-item
                                v-for="file in day.event.documents"
                                :key="file.id"
                                :href="file.url"
                                target="_blank"
                                prepend-icon="mdi-file-document-outline"
                              >
                                <v-list-item-title class="text-caption">{{
                                  file.file_name
                                }}</v-list-item-title>
                              </v-list-item>
                            </v-list>
                          </v-menu>
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
                    </template>

                    <template
                      v-else-if="day.event && day.event.coverage === 'custom'"
                    >
                      <template
                        v-for="(col, index) in getCustomColumns(day)"
                        :key="index"
                      >
                        <td
                          v-if="col.type === 'event'"
                          :colspan="col.colspan"
                          class="border border-black p-2 text-center text-sm font-semibold bg-green-lighten-5 text-green-darken-4 text-uppercase cursor-pointer hover:bg-green-lighten-4"
                          @click="openEventDialog(day)"
                        >
                          <div
                            class="d-flex align-center justify-center text-caption font-weight-black text-black"
                          >
                            <v-icon
                              size="14"
                              color="starbucks-green"
                              class="mr-1"
                            >
                              mdi-map-marker-distance
                            </v-icon>

                            {{ day.event.type }} | {{ day.event.start_time }}-{{
                              day.event.end_time
                            }}

                            <v-menu
                              v-if="day.event?.documents?.length"
                              location="bottom end"
                            >
                              <template v-slot:activator="{ props }">
                                <v-btn
                                  v-bind="props"
                                  icon="mdi-paperclip"
                                  size="x-small"
                                  variant="text"
                                  color="primary"
                                  class="ml-1"
                                />
                              </template>

                              <v-list density="compact">
                                <v-list-item
                                  v-for="file in day.event.documents"
                                  :key="file.id"
                                  :href="file.url"
                                  target="_blank"
                                  prepend-icon="mdi-file-document-outline"
                                >
                                  <v-list-item-title class="text-caption">
                                    {{ file.file_name }}
                                  </v-list-item-title>
                                </v-list-item>
                              </v-list>
                            </v-menu>
                          </div>
                        </td>

                        <td
                          v-else
                          class="border border-black p-2 text-center text-sm text-black font-weight-medium"
                        >
                          {{ day[col.key] || "-" }}
                        </td>
                      </template>

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
                    </template>

                    <!-- University Activity priority -->
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
                      <!-- <td class="border border-black p-2 text-center text-sm">
                        <v-btn
                          size="small"
                          color="starbucks-green"
                          rounded="xl"
                          @click="openEventDialog(day)"
                        >
                          Log Official Event
                        </v-btn>
                      </td> -->
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
                      <!-- <td class="border border-black p-2 text-center text-sm">
                        <v-btn
                          size="small"
                          color="starbucks-green"
                          rounded="xl"
                          @click="openEventDialog(day)"
                        >
                          Log Official Event
                        </v-btn>
                      </td> -->
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
                      <!-- <td class="border border-black p-2 text-center text-sm">
                        <v-btn
                          size="small"
                          color="starbucks-green"
                          rounded="xl"
                          @click="openEventDialog(day)"
                        >
                          Log Official Event
                        </v-btn>
                      </td> -->
                    </template>

                    <!-- Holiday priority -->
                    <template
                      v-else-if="isHoliday(day.date_full) && !hasDtrRecord(day)"
                    >
                      <td
                        colspan="8"
                        class="border border-black border-r-2 p-2 text-center text-sm font-semibold bg-green-lighten-4 text-green-darken-4"
                      >
                        {{ isHoliday(day.date_full).name }}
                      </td>
                      <!-- <td class="border border-black p-2 text-center text-sm">
                        <v-btn
                          size="small"
                          color="starbucks-green"
                          rounded="xl"
                          @click="openEventDialog(day)"
                        >
                          Log Official Event
                        </v-btn>
                      </td> -->
                    </template>

                    <!-- Normal Workday -->
                    <template v-else>
                      <td
                        :class="[
                          'border border-black p-2 text-center text-sm',
                          canEditTime
                            ? 'cursor-pointer hover:bg-blue-lighten-5'
                            : 'cursor-not-allowed',
                        ]"
                        @click="
                          canEditTime
                            ? openEditDialog(day, index, 'check_in')
                            : null
                        "
                      >
                        <span :class="getTimeClass(day.check_in, true)">{{
                          day.check_in || "-"
                        }}</span>
                      </td>
                      <td
                        :class="[
                          'border border-black p-2 text-center text-sm',
                          canEditTime
                            ? 'cursor-pointer hover:bg-blue-lighten-5'
                            : 'cursor-not-allowed',
                        ]"
                        @click="
                          canEditTime
                            ? openEditDialog(day, index, 'break_out')
                            : null
                        "
                      >
                        <span :class="getTimeClass(day.break_out)">{{
                          day.break_out || "-"
                        }}</span>
                      </td>
                      <td
                        :class="[
                          'border border-black p-2 text-center text-sm',
                          canEditTime
                            ? 'cursor-pointer hover:bg-blue-lighten-5'
                            : 'cursor-not-allowed',
                        ]"
                        @click="
                          canEditTime
                            ? openEditDialog(day, index, 'break_in')
                            : null
                        "
                      >
                        <span :class="getTimeClass(day.break_in)">{{
                          day.break_in || "-"
                        }}</span>
                      </td>
                      <td
                        :class="[
                          'border border-black p-2 text-center text-sm',
                          canEditTime
                            ? 'cursor-pointer hover:bg-blue-lighten-5'
                            : 'cursor-not-allowed',
                        ]"
                        @click="
                          canEditTime
                            ? openEditDialog(day, index, 'check_out')
                            : null
                        "
                      >
                        <span :class="getTimeClass(day.check_out)">{{
                          day.check_out || "-"
                        }}</span>
                      </td>
                      <td
                        :class="[
                          'border border-black p-2 text-center text-sm',
                          canEditTime
                            ? 'cursor-pointer hover:bg-blue-lighten-5'
                            : 'cursor-not-allowed',
                        ]"
                      >
                        <span :class="getTimeClass(day.overtime_in)">{{
                          day.overtime_in || "-"
                        }}</span>
                      </td>
                      <td
                        :class="[
                          'border border-black p-2 text-center text-sm',
                          canEditTime
                            ? 'cursor-pointer hover:bg-blue-lighten-5'
                            : 'cursor-not-allowed',
                        ]"
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

                      <!--  <td class="border border-black p-2 text-center text-sm">
                        <v-btn
                          size="small"
                          color="starbucks-green"
                          rounded="xl"
                          @click="openEventDialog(day)"
                        >
                          Log Official Event
                        </v-btn>
                      </td> -->
                    </template>
                  </tr>
                </tbody>
              </v-table>
            </div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <!-- Submit Confirmation Dialog -->
  <v-dialog v-model="submitDialog" max-width="400px">
    <v-card>
      <v-card-title class="text-h6 bg-green-darken-3 text-white">
        <v-icon start>mdi-alert</v-icon>
        Confirm Submission
      </v-card-title>

      <v-card-text class="pt-4">
        <p class="text-body-1">
          Are you sure you want to submit your timesheet?
        </p>
        <p class="text-caption text-grey-600 mt-2">
          This action cannot be undone.
        </p>
      </v-card-text>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn
          color="grey"
          variant="text"
          min-width="120"
          rounded="xl"
          @click="submitDialog = false"
        >
          Cancel
        </v-btn>
        <v-btn
          color="green-darken-3"
          variant="elevated"
          min-width="120"
          rounded="xl"
          @click="handleSubmit"
        >
          Proceed
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Edit Time Dialog -->
  <v-dialog v-model="editDialog" max-width="500px">
    <v-card>
      <v-card-title class="text-h6 bg-green-darken-3 text-white">
        <v-icon start>mdi-clock-edit</v-icon>
        Edit Time Entry
      </v-card-title>

      <v-card-text class="pt-4">
        <div class="mb-4">
          <div class="text-subtitle-1 font-weight-medium mb-2">
            {{ selectedDay?.date }} ({{ selectedDay?.day }})
          </div>
          <div class="text-caption text-grey-600">
            {{ getTimeFieldLabel(selectedTimeField) }}
          </div>
        </div>

        <v-form @submit.prevent="saveTimeEdit()">
          <v-row>
            <v-col cols="12">
              <v-text-field
                v-model="editTimeValue"
                label="Time"
                type="time"
                variant="outlined"
                density="compact"
                :rules="timeRules"
                placeholder="HH:MM"
                prepend-inner-icon="mdi-clock"
                rounded="lg"
              ></v-text-field>
            </v-col>

            <v-col cols="12">
              <div class="d-flex align-center justify-end">
                <v-btn
                  color="grey"
                  variant="text"
                  rounded="xl"
                  @click="closeEditDialog"
                >
                  Cancel
                </v-btn>
                <v-btn
                  color="green-darken-3"
                  variant="elevated"
                  type="submit"
                  rounded="xl"
                  class="pa-2"
                  min-width="120"
                >
                  <v-icon class="pl-2" start>mdi-content-save</v-icon>
                  Save Changes
                </v-btn>
              </div>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <!-- <v-card-actions class="pa-4">
        <v-spacer></v-spacer>

      </v-card-actions> -->
    </v-card>
  </v-dialog>

  <!-- Success Snackbar -->
  <v-snackbar
    v-model="showSuccessMessage"
    color="success"
    timeout="3000"
    location="top"
  >
    <div class="d-flex align-center">
      <v-icon start>mdi-check-circle</v-icon>
      Time entry updated successfully!
    </div>
  </v-snackbar>

  <v-dialog v-model="printDtrDialog" max-width="600px">
    <v-card>
      <v-card-text class="pa-4">
        <div class="v-card-title">
          <h4>Print Daily Time Record</h4>
        </div>
        <v-form>
          <v-row>
            <v-col cols="12" md="6">
              <v-select
                variant="outlined"
                density="compact"
                :items="months"
                label="Month"
                return-object
                hide-details
                class="mb-4"
                rounded="lg"
              ></v-select>
            </v-col>
            <v-col cols="12" md="6">
              <v-select
                variant="outlined"
                density="compact"
                :items="period"
                label="Period"
                return-object
                hide-details
                class="mb-4"
                rounded="lg"
              ></v-select>
            </v-col>
            <v-col cols="12">
              <div class="d-flex align-center justify-end">
                <ButtonMuted
                  name="Cancel"
                  class="mr-2"
                  @click="printDtrDialog = false"
                />
                <ButtonSuccess name="Print" />
              </div>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>
    </v-card>
  </v-dialog>

  <v-dialog v-model="isPrintDialogVisible" max-width="600">
    <v-card class="pa-4 ma-4">
      <v-card-title class="d-flex justify-space-between align-center">
        <h4>Print Daily Time Record</h4>
      </v-card-title>
      <v-card-text>
        <v-form @submit.prevent="printDailyTimeRecord()">
          <v-row>
            <v-col cols="6">
              <v-select
                v-model="selectedMonthForDialog"
                variant="outlined"
                density="compact"
                :items="monthsForDialog"
                label="Month"
              ></v-select>
            </v-col>
            <v-col cols="6">
              <v-select
                v-model="selectedYearForDialog"
                variant="outlined"
                density="compact"
                :items="years"
                label="Year"
                hide-details
              ></v-select>
            </v-col>
          </v-row>
          <v-row>
            <v-col cols="12">
              <v-select
                label="Period"
                variant="outlined"
                density="compact"
                hide-details
                hide-no-data
                :items="period"
                item-title="text"
                item-value="value"
                v-model="cutOff"
              ></v-select>
            </v-col>
          </v-row>
          <v-col cols="12">
            <div class="d-flex align-center justify-end">
              <ButtonMuted
                class="mr-2"
                name="Cancel"
                @click="resetPrintDialogForm()"
              />
              <ButtonSuccess name="Print" type="submit" />
            </div>
          </v-col>
        </v-form>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>
<script setup>
import { ref, computed } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import { useToast } from "vue-toastification";
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import DailyTimeRecordTabs from "@/components/DailyTimeRecordTabs.vue";
import { getCurrentInstance } from "vue";

// Define layout
defineOptions({ layout: SidebarLayout });

// Constants
const LATE_THRESHOLD_HOUR = 8;
const FULL_DAY_MINUTES_THRESHOLD = 420;

const app = getCurrentInstance();

// Props
const props = defineProps({
  employee: Object,
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
  employeeLeaves: {
    type: Object,
    default: () => ({ data: [] }),
  },
  employeeAttendanceLogs: {
    type: Array,
    default: () => [],
  },
});

// Composables
const toast = useToast();
const page = usePage();

// State
const activeTab = ref("list");
const submitDialog = ref(false);
const isPrintDialogVisible = ref(false);
const employeeIds = ref(null);
const cutOff = ref(null);

const period = [
  { text: "1st Half", value: 1 },
  { text: "Full Month", value: 2 },
];

const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);

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

const monthsForDialog = [
  "January",
  "February",
  "March",
  "April",
  "May",
  "June",
  "July",
  "August",
  "September",
  "October",
  "November",
  "December",
];

const form = useForm({
  employee_id: props.employee.data.id,
  selectedMonth: props.selectedMonth || new Date().getMonth() + 1,
  selectedYear: props.selectedYear || new Date().getFullYear(),
});

// Edit dialog state
const editDialog = ref(false);
const editTimeValue = ref("");
const selectedDay = ref(null);
const selectedTimeField = ref("");
const selectedDayIndex = ref(-1);
const showSuccessMessage = ref(false);

const dtrTimeRecordForm = useForm({
  rowId: null,
  employee_id: props.employee.data.id,
  selected_date: null,
  check_in: null,
  check_out: null,
  break_in: null,
  break_out: null,
});

const timeRules = [
  (v) => !!v || "Time is required",
  (v) =>
    /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/.test(v) ||
    "Please enter a valid time (HH:MM)",
];

const selectedMonthForDialog = ref(
  new Date().toLocaleString("default", { month: "long" })
);
const selectedYearForDialog = ref(new Date().getFullYear());

// Computed
const userRoles = computed(() => page.props.auth.roles || []);
const canEditTime = computed(() => userRoles.value.includes("superadmin"));

const leaveApplications = computed(() => props.employeeLeaves?.data ?? []);

const leaveByDate = computed(() => {
  const map = {};
  leaveApplications.value.forEach((leave) => {
    (leave.inclusive_dates || []).forEach((dateObj) => {
      const key = dateObj.date_raw;
      if (!map[key]) {
        map[key] = {
          ...leave,
          duration: dateObj.duration_raw,
          duration_label: dateObj.duration,
        };
      }
    });
  });
  return map;
});

const attendanceLogs = computed(() => {
  const map = {};
  (props.employeeAttendanceLogs || []).forEach((log) => {
    map[log.date] = log;
  });
  return map;
});

// Methods
const isHoliday = (dateFull) =>
  props.holidays.find((h) => h.date === dateFull) || null;

const hasDtrRecord = (day) =>
  day.check_in || day.break_out || day.break_in || day.check_out;

const loadTimesheetData = () => {
  console.log(
    "[DtrView] Loading timesheet data for:",
    form.selectedMonth,
    form.selectedYear
  );
  form.get(route("hrmanagement.dailytimerecord.view"), {
    onSuccess: () => {
      toast.success("Record successfully loaded");
    },
    onError: (errors) => {
      const errorMessages = Object.values(errors).flat().join(" ");
      toast.error(errorMessages || "Failed to load data");
    },
  });
};

const isWeekend = (day) => day === "Sat" || day === "Sun";

const getDayClass = (day) =>
  isWeekend(day) ? "text-grey-500 font-weight-medium" : "text-grey-700";

const printSingleDtr = (employeeId) => {
  employeeIds.value = employeeId;
  isPrintDialogVisible.value = true;
};

const printDailyTimeRecord = () => {
  console.log("[DtrView] Printing DTR for employee:", employeeIds.value);

  if (!cutOff.value) {
    app.proxy.showToast("Cut-off is required.", "error");
    return;
  }

  window.open(
    route("hrmanagement.dailytimerecord.printDailyTimeRecord", {
      emp_id: [employeeIds.value],
      month: selectedMonthForDialog.value,
      year: selectedYearForDialog.value,
      cut_off: cutOff.value,
    }),
    "_blank"
  );
  isPrintDialogVisible.value = false;
  resetPrintDialogForm();
};

const resetPrintDialogForm = () => {
  isPrintDialogVisible.value = false;
  selectedMonthForDialog.value = new Date().toLocaleString("default", {
    month: "long",
  });
};

const getTimeClass = (time, isCheckIn = false) => {
  if (!time) return "text-grey-400";
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

const openEditDialog = (day, index, timeField) => {
  selectedDay.value = day;
  selectedDayIndex.value = index;
  selectedTimeField.value = timeField;
  editTimeValue.value = day[timeField] || "";

  dtrTimeRecordForm.rowId = day.id || null;
  dtrTimeRecordForm.selected_date = day.date_full;
  dtrTimeRecordForm[timeField] = editTimeValue.value;
  editDialog.value = true;
};

const closeEditDialog = () => {
  editDialog.value = false;
  selectedDay.value = null;
  selectedDayIndex.value = -1;
  selectedTimeField.value = "";
  editTimeValue.value = "";
  dtrTimeRecordForm.reset();
};

const saveTimeEdit = async () => {
  console.log(
    "[DtrView] Saving time edit for:",
    selectedDay.value.date_full,
    selectedTimeField.value
  );
  dtrTimeRecordForm.rowId = selectedDay.value.id || null;
  dtrTimeRecordForm.selected_date = selectedDay.value.date_full;
  dtrTimeRecordForm[selectedTimeField.value] = editTimeValue.value;

  dtrTimeRecordForm.post(route("hrmanagement.dailytimerecord.update"), {
    onSuccess: () => {
      showSuccessMessage.value = true;
      toast.success("Time entry updated successfully");
      closeEditDialog();
    },
    onError: (errors) => {
      const errorMessages = Object.values(errors).flat().join(" ");
      toast.error(errorMessages || "Failed to update time entry");
    },
  });
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

const formatEventType = (type) => {
  if (!type) return "EVENT";
  return type
    .split("_")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
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

const handleSubmit = () => {
  submitDialog.value = false;
  console.log("[DtrView] Timesheet submitted");
};

const getCustomColumns = (day) => {
  const columns = [
    { key: "check_in", time: "08:00:00" },
    { key: "break_out", time: "10:00:00" },
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
</script>


<style scoped>
.profile-hover {
  transition: all 0.3s ease;
  cursor: pointer;
}

.profile-hover:hover {
  transform: scale(1.05);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.profile-image {
  transition: all 0.3s ease;
}

.profile-hover:hover .profile-image {
  filter: brightness(1.1);
}

.profile-placeholder {
  transition: all 0.3s ease;
}

.profile-hover:hover .profile-placeholder {
  background-color: #e0e0e0 !important;
  transform: scale(1.02);
}

.profile-hover:hover .profile-placeholder .v-icon {
  color: #424242 !important;
  transform: scale(1.1);
}
</style>
