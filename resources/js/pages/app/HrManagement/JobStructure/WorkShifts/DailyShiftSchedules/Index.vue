<template>
  <JobStructureTabs :activeTab="activeTab" />
  <FilterWrapper v-model="isPanelOpen">
    <v-form @submit.prevent="handleFilter()">
      <v-row>
        <v-col cols="12" sm="12" md="4">
          <v-text-field
            label="Search"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            prepend-inner-icon="mdi-magnify"
            v-model="filterForm.search"
          ></v-text-field>
        </v-col>
        <v-col cols="12" sm="12" md="4">
          <v-select
            label="Day of the Week"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.day_of_week"
            :items="filterOptions.daysOfTheWeek"
            hide-details
            prepend-inner-icon="mdi-calendar-today"
          ></v-select>
        </v-col>

        <v-col cols="12" sm="12" md="2">
          <v-text-field
            label="Check In"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            prepend-inner-icon="mdi-clock-in"
            type="time"
            v-model="filterForm.time_in"
          ></v-text-field>
        </v-col>
        <v-col cols="12" sm="12" md="2">
          <v-text-field
            label="Break Out"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            prepend-inner-icon="mdi-clock-out"
            type="time"
            v-model="filterForm.break_start"
          ></v-text-field>
        </v-col>
        <v-col cols="12" sm="12" md="2">
          <v-text-field
            label="Break In"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            prepend-inner-icon="mdi-clock-in"
            type="time"
            v-model="filterForm.break_end"
          ></v-text-field>
        </v-col>
        <v-col cols="12" sm="12" md="2">
          <v-text-field
            label="Check Out"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            prepend-inner-icon="mdi-clock-out"
            type="time"
            v-model="filterForm.time_out"
          ></v-text-field>
        </v-col>


        <v-col cols="12" sm="12" md="4">
          <v-select
            label="Direction"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.direction"
            :items="filterOptions.direction"
            hide-details
            prepend-inner-icon="mdi-arrow-up-down"
          ></v-select>
        </v-col>
        <v-col cols="12" sm="12" md="2">
          <v-select
            label="Size"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.size"
            :items="filterOptions.size"
            hide-details
            prepend-inner-icon="mdi-numeric-10"
          ></v-select>
        </v-col>
      </v-row>
      <v-divider
        class="my-4"
        style="border:1px solid black;"
      ></v-divider>
      <div class="d-flex align-center justify-end">
        <ButtonMuted name="Reset" @click="resetFilter()" />
        <ButtonSuccess name="Search" type="submit" class="ml-2" />
      </div>
    </v-form>
  </FilterWrapper>
  <TableWrapper>
    <div class="d-flex align-center justify-space-between">
      <div class="v-card-title text-h6 font-weight-medium">
        Daily Shift Schedules
      </div>
      <Link
        :href="route('hrmanagement.jobstructure.dailyShiftSchedules.create')"
      >
        <ButtonSuccess name="+ Add" />
      </Link>
    </div>
    <v-table>
      <thead>
        <tr>
          <th>Day of the Week</th>
          <th>Check In</th>
          <th>Break Out</th>
          <th>Break In</th>
          <th>Check Out</th>
          <th>Remarks</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(dailyShiftSchedule, index) in dailyShiftSchedules.data"
          :key="index"
        >
          <td>{{ dailyShiftSchedule.day_of_week }}</td>
          <td>{{ dailyShiftSchedule.time_in }}</td>
          <td>{{ dailyShiftSchedule.break_start }}</td>
          <td>{{ dailyShiftSchedule.break_end }}</td>
          <td>{{ dailyShiftSchedule.time_out }}</td>
          <td>{{ dailyShiftSchedule.remarks }}</td>
          <td class="text-center">
            <v-btn
              color="red"
              variant="tonal"
              size="x-small"
              icon="mdi-trash-can"
              @click="
                deleteConfirmationDialog = true;
                id = dailyShiftSchedule.id;
              "
            />
            <Link
              :href="dailyShiftSchedule.edit_link"
              class="text-decoration-none"
            >
              <v-btn
                color="orange-darken-4"
                variant="tonal"
                size="x-small"
                icon="mdi-pencil"
                class="ml-2"
              />
            </Link>
          </td>
        </tr>
      </tbody>
    </v-table>
    <!-- <pre>{{ dailyShiftSchedules }}</pre> -->
    <Pagination 
      class="mt-3" 
      :meta="dailyShiftSchedules.meta" 
      :filters="filterForm.data()"
    />
  </TableWrapper>
  <DeleteDialog
    v-model="deleteConfirmationDialog"
    message="Are you sure you want to delete this daily shift schedule?"
    @confirm="handleDelete()"
    @cancel="deleteConfirmationDialog = false"
  />
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import JobStructureTabs from "@/components/JobStructureTabs.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import DeleteDialog from "@/components/DeleteDialog.vue";
import Pagination from "@/components/Pagination.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";
import { useForm } from '@inertiajs/vue3';
import { defaultSizes, defaultDirections } from '@/utils/filters';
import { daysOfTheWeek } from "@/utils/days";

export default {
  layout: SidebarLayout,
  components: {
    JobStructureTabs,
    TableWrapper,
    ButtonSuccess,
    ButtonMuted,
    DeleteDialog,
    Pagination,
    FilterWrapper,
  },
  props: {
    errors: Object,
    dailyShiftSchedules: Object,
    filters: Object,
  },
  data() {
    return {
      activeTab: "workShift",
      deleteConfirmationDialog: false,
      isPanelOpen: [0],
      filterForm: useForm({
        search: null,
        day_of_week: null,
        time_in: null,
        break_start: null,
        break_end: null,
        time_out: null,
        direction: 'Ascending',
        size: 10,
      }),
      filterOptions: {
        size: defaultSizes,
        direction: defaultDirections,
        daysOfTheWeek: daysOfTheWeek,
      },
    };
  },
  methods: {
    handleDelete() {
      this.$inertia.delete(
        route("hrmanagement.jobstructure.dailyShiftSchedules.destroy", this.id),
        {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.showToast(
              "Daily shift schedule deleted successfully",
              "success"
            );
            this.deleteConfirmationDialog = false;
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
            this.deleteConfirmationDialog = false;
          },
        }
      );
    },

    handleFilter(){
      console.log(this.filterForm.data());
      this.filterForm.post(route('hrmanagement.jobstructure.dailyShiftSchedules.index'), {
        preserveScroll: true,
        preserveState: true,
        only: ['dailyShiftSchedules']
      })
    },

    resetFilter(){
      this.filterForm.search = null;
      this.filterForm.day_of_week = null;
      this.filterForm.time_in = null;
      this.filterForm.break_start = null;
      this.filterForm.break_end = null;
      this.filterForm.time_out = null;
      this.filterForm.direction = 'Ascending';
      this.filterForm.size = 10;
      this.handleFilter();
    }
  },
};
</script>
