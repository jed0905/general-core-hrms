<template>
  <JobStructureTabs v-model:activeTab="activeTab" />
  <FilterWrapper v-model="isPanelOpen">
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
  </FilterWrapper>

  <TableWrapper>
    <v-card-title class="mb-2 d-flex justify-space-between align-center">
      <span>Designations</span>
      <Link :href="route('hrmanagement.jobstructure.designation.create')">
        <ButtonSuccess class="ml-2" name="+ Add" />
      </Link>
    </v-card-title>
    <v-divider class="mb-6"></v-divider>
    <v-table>
      <thead>
        <tr>
          <th>Designation</th>
          <th>Operating Unit</th>
          <th class="text-center">Assigned Employees</th>
          <th>Is VSL</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in designations.data" :key="item.id">
          <td>{{ item.name }}</td>
          <td>{{ item.operating_unit.name }}</td>
          <td class="text-center">
            <div class="mt-2 mb-2" v-if="item.employees && item.employees.length > 0">
              <div v-for="employee in item.employees" :key="employee.id" class="mb-1">
                <v-chip size="small" variant="outlined">
                  {{ employee.name }} ({{ employee.employee_number }})
                </v-chip>
                <div class="text-caption text-medium-emphasis">
                  Assumed: {{ formatDate(employee.assumption_date) }}
                </div>
              </div>
            </div>
            <div v-else class="text-medium-emphasis">
              No employees assigned
            </div>
          </td>
          <td>
            <v-switch
              v-model="item.is_vsl"
              color="starbucks-green"
              hide-details
              inset
              :true-value="1"
              :false-value="0"
              @change="toggleVsl(item)"
            ></v-switch>
          </td>
          <td class="text-center">
            <Link
              :href="item.edit_link"
              class="v-btn v-btn--text text-orange-darken-4"
              style="min-width: unset; padding: 0 8px; height: 36px"
            >
              <v-btn
                variant="tonal"
                size="x-small"
                icon="mdi-pencil"
              ></v-btn>
            </Link>
          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination 
    class="mt-3" 
    :meta="designations.meta" 
    :filters="filterForm.data()"
    />
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

  <!-- <pre>{{ designations.data }}</pre> -->
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
    designations: Object,
    operating_units: Object,
    errors: Object,
  },
  data() {
    return {
      isPanelOpen: [0],
      activeTab: "designation",
      deleteConfirmationDialog: false,
      selectedJobStatus: null,

      filterForm: useForm({
        search: null,
        operating_unit:
          this.$page.props.auth.roles[0] != "superadmin" &&
          this.$page.props.auth.roles[0] != "hr_director"
            ? this.$page.props.auth.user.employee.operating_unit_id
            : null,
        size: 10,
        direction: 'Ascending',
      }),
      filterOptions: {
        size: defaultSizes,
        direction: defaultDirections,
      },
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
      this.filterForm.direction = 'Ascending';
      this.handleFilter();
    },

    formatDate(date) {
      if (!date) return 'N/A';
      return new Date(date).toLocaleDateString();
    },

    handlePageClick(url) {
      // Extract page parameter from URL
      const urlObj = new URL(url);
      const page = urlObj.searchParams.get('page');
      
      // Create form data with current filters and new page
      const formData = {
        ...this.filterForm.data(),
        page: page
      };
      
      // Make POST request with preserved filters
      this.filterForm.transform(() => formData).post(route("hrmanagement.jobstructure.designation.index"), {
        preserveState: true,
        preserveScroll: true,
        only: ["designations"],
      });
    },

    toggleVsl(item) {
      const originalValue = item.is_vsl;

      this.$inertia.post(
        route('hrmanagement.jobstructure.designation.toggle-status', item.id),

        {
          is_vsl: item.is_vsl,
        },

        {
          onSuccess: () => {
            this.showToast("Designation status updated successfully.", "success");
          },
          onError: (errors) => {
            item.is_vsl = originalValue;

            const errorMessages = Object.values(errors).flat().join(" ");

            this.showToast(errorMessages, "error");
          },
        }
      );
    }
  },
};
</script>
