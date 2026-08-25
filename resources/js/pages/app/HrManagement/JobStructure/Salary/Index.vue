<template>
  <JobStructureTabs v-model:activeTab="activeTab" />
  <!-- <FilterWrapper v-model="isPanelOpen">
    <v-form @submit.prevent="handleFilter()">
      <v-row>
        <v-col cols="12" md="4">
          <v-text-field
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            label="Search"
            v-model="filterForm.search"
            prepend-inner-icon="mdi-magnify"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            :readonly="
              $page.props.auth.roles[0] != 'superadmin' &&
              $page.props.auth.roles[0] != 'hr_director'
            "
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            label="Operating Unit"
            :items="operating_units"
            item-title="name"
            item-value="id"
            v-model="filterForm.operating_unit"
            prepend-inner-icon="mdi-office-building"
          />
        </v-col>
        <v-col cols="12" md="2">
          <v-select
            label="Direction"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="filterOptions.direction"
            v-model="filterForm.direction"
            hide-details
            prepend-inner-icon="mdi-arrow-up-down"
          ></v-select>
        </v-col>
        <v-col cols="12" md="2">
          <v-select
            label="Size"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="filterOptions.size"
            v-model="filterForm.size"
            hide-details
            prepend-inner-icon="mdi-numeric-10"
          ></v-select>
        </v-col>
        <v-col cols="12">
          <div class="d-flex align-center justify-end">
            <ButtonMuted name="Reset" class="mr-2" @click="resetFilter()" />
            <ButtonSuccess name="Filter" type="submit" />
          </div>
        </v-col>
      </v-row>
    </v-form>
  </FilterWrapper> -->

  <TableWrapper>
    <v-card-title class="mb-2 d-flex justify-space-between align-center">
      <span>Salary Schedules</span>
      <Link :href="route('hrmanagement.jobstructure.salary.create')">
        <ButtonSuccess class="ml-2" name="+ Add" />
      </Link>
    </v-card-title>
    <v-divider class="mb-6"></v-divider>
    <v-table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Law Reference</th>
          <th>Effective From</th>
          <th>Effective To</th>
          <th>Is Active?</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="schedule in salary_schedules.data" :key="schedule.id">
          <td>{{ schedule.name }}</td>
          <td>{{ schedule.law_reference ?? 'N/A' }}</td>
          <td>{{ formatDate(schedule.effective_from) }}</td>
          <td>{{ formatDate(schedule.effective_to) }}</td>
          <td>
            <v-chip
              :color="schedule.is_active ? 'green' : 'red'"
              class="white--text cursor-pointer"
              size="small"
              @click="openStatusDialog(schedule)"
            >
              {{ schedule.is_active ? "Active" : "Inactive" }}
            </v-chip>
          </td>
          <td class="text-center">
            <Link
              :href="schedule.edit_link"
              class="v-btn v-btn--text text-orange-darken-4"
              style="min-width: unset; padding: 0 8px; height: 36px"
            >
              <v-btn variant="tonal" size="x-small" icon="mdi-pencil"></v-btn>
            </Link>
            <!-- View button -->
            <Link
              :href="schedule.view_link"
              class="v-btn v-btn--text text-blue-darken-1"
              style="min-width: unset; padding: 0 8px; height: 36px"
            >
              <v-btn
                icon="mdi-eye-outline"
                size="x-small"
                variant="tonal"
                color="blue-darken-1"
              />
            </Link>
          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination class="mt-3" :meta="salary_schedules.meta" />
  </TableWrapper>
  <!-- Delete Confirmation Modal Dialog -->
  <v-dialog v-model="deleteConfirmationDialog" max-width="500">
    <v-card class="pa-4 rounded-lg">
      <v-card-title
        class="d-flex align-center justify-center text-h5 font-weight-bold mb-4"
      >
        <v-icon color="error" size="large" class="mr-2"> mdi-delete </v-icon>
        Confirm Deletion
      </v-card-title>
      <v-divider class="mb-4"></v-divider>
      <v-card-text class="text-body-1 text-center">
        <p class="mb-2">Are you sure you want to delete this user?</p>
        <p class="text-caption text-medium-emphasis">
          This action cannot be undone.
        </p>
      </v-card-text>
      <v-card-actions class="d-flex justify-end gap-2 pa-4">
        <v-btn
          color="grey-darken-1"
          variant="outlined"
          @click="(deleteConfirmationDialog = false), (selectedUser = null)"
          min-width="120"
        >
          Cancel
        </v-btn>
        <v-btn
          color="error"
          variant="elevated"
          min-width="120"
          @click="deleteUser()"
        >
          Delete
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="statusDialog" max-width="400">
    <v-card>
      <v-card-title class="text-h6"> Update Status </v-card-title>

      <v-card-text>
        Change status to:
        <strong>
          {{ selectedSchedule?.is_active ? "Inactive" : "Active" }} </strong
        >?
      </v-card-text>

      <v-card-actions>
        <v-spacer />

        <v-btn variant="text" @click="statusDialog = false"> Cancel </v-btn>

        <v-btn color="primary" :loading="updatingStatus" @click="updateStatus">
          Confirm
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- <pre>{{ salary_schedules.data }}</pre> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";
import JobStructureTabs from "@/components/JobStructureTabs.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import { useForm } from "@inertiajs/vue3";
import Pagination from "@/components/Pagination.vue";
import { defaultSizes, defaultDirections } from "@/utils/filters";

export default {
  layout: SidebarLayout,
  components: {
    Breadcrumbs,
    ButtonSuccess,
    TableWrapper,
    FilterWrapper,
    JobStructureTabs,
    ButtonMuted,
    Pagination,
  },

  props: {
    salary_schedules: Object,
  },

  data() {
    return {
      isPanelOpen: [0],
      activeTab: "salary",
      deleteConfirmationDialog: false,

      statusDialog: false,
      selectedSchedule: null,
      updatingStatus: false,

      //   filterForm: useForm({
      //     search: null,
      //     operating_unit:
      //       this.$page.props.auth.roles[0] != "superadmin" &&
      //       this.$page.props.auth.roles[0] != "hr_director"
      //         ? this.$page.props.auth.user.employee.operating_unit_id
      //         : null,
      //     size: 10,
      //     direction: 'Ascending',
      //   }),
      //   filterOptions: {
      //     size: defaultSizes,
      //     direction: defaultDirections,
      //   },
    };
  },

  methods: {
    handleFilter() {
      this.filterForm.post(
        route("hrmanagement.jobstructure.designation.index"),
        {
          preserveState: true,
          preserveScroll: true,
          only: ["designations"],
        }
      );
    },

    resetFilter() {
      this.filterForm.search = null;
      this.filterForm.operating_unit = null;
      this.filterForm.size = 10;
      this.filterForm.direction = "Ascending";
      this.handleFilter();
    },

    formatDate(date) {
      if (!date) return "N/A";
      return new Date(date).toLocaleDateString();
    },

    openStatusDialog(schedule) {
      this.selectedSchedule = schedule;
      this.statusDialog = true;
    },

    updateStatus() {
      if (!this.selectedSchedule) return;

      this.updatingStatus = true;

      this.$inertia.put(
        route("hrmanagement.jobstructure.salary.toggle-status", {
          id: this.selectedSchedule.id,
        }),
        {
          is_active: !this.selectedSchedule.is_active, // 👈 send in body instead
        },
        {
          onSuccess: () => {
            this.showToast("Status updated successfully!", "success");
            this.statusDialog = false;
          },
          onError: () => {
            this.showToast("Failed to update status.", "error");
          },
          onFinish: () => {
            this.updatingStatus = false;
          },
        }
      );
    },

    handlePageClick(url) {
      // Extract page parameter from URL
      const urlObj = new URL(url);
      const page = urlObj.searchParams.get("page");

      // Create form data with current filters and new page
      const formData = {
        ...this.filterForm.data(),
        page: page,
      };

      // Make POST request with preserved filters
      this.filterForm
        .transform(() => formData)
        .post(route("hrmanagement.jobstructure.designation.index"), {
          preserveState: true,
          preserveScroll: true,
          only: ["designations"],
        });
    },
  },
};
</script>
