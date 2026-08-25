<template>
  <UserManagementTabs v-model:activeTab="activeTab" />
  <FilterWrapper :model-value="[0, 1]">
    <v-form @submit.prevent="handleFilter()">
      <v-row>
        <v-col cols="12" md="4">
          <!-- Will search employee name, employee id -->
          <v-text-field
            label="Search by Employee Name"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.employee"
            hide-details
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            label="User Role"
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            :items="roles"
            v-model="filterForm.role"
            item-title="name"
            item-value="id"
          ></v-select>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            label="Account Status"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="accountStatus"
            v-model="filterForm.account_status"
            hide-details
          ></v-select>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            label="Operating Unit"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="operatingUnits"
            item-title="name"
            item-value="id"
            v-model="filterForm.operating_unit"
            :disabled="!isPrivileged"
            hide-details
          ></v-select>
        </v-col>
        <v-col cols="12" md="3">
          <v-select
            label="Direction"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="filterOptions.direction"
            v-model="filterForm.direction"
            hide-details
          ></v-select>
        </v-col>
        <v-col cols="12" md="1">
          <v-select
            label="Size"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="filterOptions.size"
            v-model="filterForm.size"
            hide-details
          ></v-select>
        </v-col>

        <v-col cols="12">
          <div class="d-flex align-center justify-end">
            <ButtonMuted class="mr-2" name="Reset" @click="resetFilter()" />
            <ButtonSuccess name="Filter" type="submit" />
          </div>
        </v-col>
      </v-row>
    </v-form>
  </FilterWrapper>

  <TableWrapper>
    <v-skeleton-loader
      v-if="!users"
      type="table"
      class="mx-auto mt-8"
    >
    </v-skeleton-loader>
    <v-table
      v-else
    >
      <thead>
        <tr>
          <th>Username</th>
          <th>User Role</th>
          <th>Employee Name</th>
          <th>Status</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in users.data" :key="item.id">
          <td>{{ item.username }}</td>
          <td>
            {{ item.roles[0]?.display_name ?? ''}}
          </td>
          <td>
            {{ item.employee?.personal_information?.full_name_asc || "-" }}
          </td>
          <td>{{ item.status }}</td>
          <td class="text-center">
            <v-btn
              color="red"
              variant="tonal"
              size="x-small"
              @click="deleteConfirmationDialog = true; selectedUser = item"
              icon="mdi-delete"
              class="mr-2"
              title="Delete User Credentials"
            />

            <v-btn
              color="orange-darken-4"
              variant="tonal"
              size="x-small"
              icon="mdi-key-alert"
              class="mr-2"
              title="Reset Password"
              @click="resetPasswordDialog = true; selectedUser = item"
            />

            <Link :href="item.edit_link">
              <v-btn
                color="yellow-darken-4"
                variant="tonal"
                size="x-small"
                icon="mdi-pencil"
                class="mr-2"
                title="Edit User"
              />
            </Link>
          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination 
      class="mt-3" 
      :meta="users.meta"
      :customClickHandler="true"
      @page-click="handlePageClick"
    />
  </TableWrapper>

  <!-- Employee Account Confirmation Modal Dialog -->
  <v-dialog v-model="employeeAccountConfirmationDialog" max-width="500">
    <v-card class="pa-4 rounded-lg">
      <v-card-title
        class="d-flex align-center justify-center text-h5 font-weight-bold mb-4"
      >
        <v-icon color="warning" size="large" class="mr-2">
          mdi-alert-circle
        </v-icon>
        Confirm Status Change
      </v-card-title>
      <v-divider class="mb-4"></v-divider>
      <v-card-text class="text-body-1 text-center">
        <p class="mb-2">
          Are you sure you want to change this account's status?
        </p>
        <p class="text-caption text-medium-emphasis">
          This action will affect the user's access to the system.
        </p>
      </v-card-text>
      <v-card-actions class="d-flex justify-end gap-2 pa-4">
        <v-btn
          color="grey-darken-1"
          variant="outlined"
          @click="employeeAccountConfirmationDialog = false"
          min-width="120"
        >
          Cancel
        </v-btn>
        <v-btn color="warning" variant="elevated" min-width="120">
          Confirm
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Reset Password Modal Dialog -->
  <v-dialog v-model="resetPasswordDialog" max-width="500">
    <v-card class="pa-4 rounded-lg">
      <v-card-title
        class="d-flex align-center justify-center text-h5 font-weight-bold mb-4"
      >
        <v-icon color="warning" size="large" class="mr-2">
          mdi-key-alert
        </v-icon>
        Reset Password
      </v-card-title>
      <v-divider class="mb-4"></v-divider>
      <v-card-text class="text-body-1 text-center">
        <p class="mb-2">
          Are you sure you want to reset the password for this account?
        </p>
        <p class="text-caption text-medium-emphasis">
          This action cannot be undone.
        </p>
      </v-card-text>
      <v-card-actions class="d-flex justify-end gap-2 pa-4">
        <v-btn
          color="grey-darken-1"
          variant="outlined"
          @click="resetPasswordDialog = false; selectedUser = null"
          min-width="120"
        >
          Cancel
        </v-btn>
        <v-btn
          color="warning"
          variant="elevated"
          min-width="120"
          @click="resetUserPassword()"
        >
          Reset
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

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
          @click="deleteConfirmationDialog = false; selectedUser = null"
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
  <!-- <pre>{{ roles }}</pre> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";
import UserManagementTabs from "@/components/UserManagementTabs.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import Pagination from "@/components/Pagination.vue";
import { useForm } from "@inertiajs/vue3";
import { defaultSizes, defaultDirections } from "@/utils/filters";

export default {
  layout: SidebarLayout,
  components: {
    Breadcrumbs,
    PrimaryButton,
    TableWrapper,
    FilterWrapper,
    UserManagementTabs,
    ButtonSuccess,
    ButtonMuted,
    Pagination,
  },

  props: {
    users: Object,
    roles: Array,
    operatingUnits: Object,
  },

  data() {
    const roles = (this.$page?.props?.auth?.roles) || [];
    const isSuperAdmin = roles.includes('superadmin');
    const isHrDirector = roles.includes('hr_director');
    const isCampusHr = roles.includes('campus_hr');
    const isCampusHrStaff = roles.includes('campus_hr_staff');
    const isPrivileged = isSuperAdmin || isHrDirector;
    const defaultOperatingUnitId = !isPrivileged
      ? (this.$page?.props?.auth?.user?.employee?.operating_unit_id ?? null)
      : null;


    return {
      disableOperatingUnitInput: isCampusHr || isCampusHrStaff,
      isPrivileged,
      initialOperatingUnitId: defaultOperatingUnitId,
      activeTab: "userIndex",
      resetPasswordDialog: false,
      deleteConfirmationDialog: false,
      employeeAccountConfirmationDialog: false,
      accountStatus: ["Active", "Inactive", "Suspended"],
      filterForm: useForm({
        employee: null,
        role: null,
        account_status: null,
        operating_unit: defaultOperatingUnitId ?? null,
        direction: 'Ascending',
        size: 10,
      }),
      filterOptions: {
        size: defaultSizes,
        direction: defaultDirections,
      },
      selectedUser: null,
    };
  },
  methods: {

    handleFilter() {
      // Reset to page 1 when filtering to avoid empty results when filtering
      // from a higher page number to a smaller result set
      this.filterForm.transform((data) => ({
        ...data,
        page: 1
      })).post(route("administration.user.index"), {
        preserveState: true,
        preserveScroll: true,
        only: ['users'],
      });
    },

    resetFilter() {
      this.filterForm.employee = null;
      this.filterForm.role = null;
      this.filterForm.account_status = null;
      this.filterForm.operating_unit = this.initialOperatingUnitId;
      this.filterForm.direction = 'Ascending';
      this.filterForm.size = 10;
      this.handleFilter();
    },


    resetUserPassword() {
      this.$inertia.put(
        this.route("administration.user.admin-reset-password", this.selectedUser.id),
        {},
        {
          preserveScroll: true,
          onSuccess: () => {
            this.resetPasswordDialog = false;
            this.selectedUser = null;
            this.showToast("Password reset successfully", "success");
          },
          onError: (errors) => {
            this.resetPasswordDialog = false;
            this.selectedUser = null;
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(errorMessages, "error");
          },
        }
      );
    },

    deleteUser() {
      this.$inertia.delete(
        this.route("administration.user.destroy", this.selectedUser.id),
        {
          preserveScroll: true,
          onSuccess: () => {
            this.showToast("User deleted successfully", "success");
            this.deleteConfirmationDialog = false;
            this.selectedUser = null;
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(errorMessages, "error");
            this.selectedUser = null;
          },
        }
      );
    },

    handlePageClick(url) {
      const urlObj = new URL(url);
      const page = urlObj.searchParams.get('page');
      
      this.filterForm.transform((data) => ({
        ...data,
        page: page
      })).post(route('administration.user.index'), {
        preserveState: true,
        preserveScroll: true,
        only: ['users'],
      });
    }
  },
};
</script>
