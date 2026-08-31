<template>
  <SidebarLayout>
    <v-container fluid class="pa-6">
      <!-- Header Section -->
      <div
        class="d-flex flex-column flex-sm-row justify-space-between align-sm-center mb-6 ga-4"
      >
        <div>
          <h1 class="text-h5 font-weight-bold mb-1">User Management</h1>
          <p class="text-body-2 text-medium-emphasis">
            Manage system user accounts, assign roles, and handle authentication
            security.
          </p>
        </div>
        <div>
          <v-btn
            color="primary"
            prepend-icon="mdi-account-plus-outline"
            rounded="lg"
            elevation="2"
            class="text-none font-weight-medium"
            @click="openCreateDialog"
          >
            Add New User
          </v-btn>
        </div>
      </div>

      <!-- Filters & Search Bar -->
      <v-card variant="outlined" class="pa-4 rounded-xl mb-6 bg-surface">
        <v-row density="compact" align="center">
          <v-col cols="12" sm="6" md="4">
            <v-text-field
              v-model="search"
              label="Search by username or employee name"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              @update:model-value="debouncedSearch"
            />
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-select
              v-model="statusFilter"
              :items="statusOptions"
              label="Status"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              clearable
              @update:model-value="applyFilters"
            />
          </v-col>
        </v-row>
      </v-card>

      <!-- Users Table Card -->
      <v-card variant="outlined" class="rounded-xl overflow-hidden border">
        <v-table class="text-no-wrap">
          <thead>
            <tr>
              <th class="font-weight-bold">User</th>
              <th class="font-weight-bold">Linked Employee</th>
              <th class="font-weight-bold">Assigned Roles</th>
              <th class="font-weight-bold">Status</th>
              <th class="font-weight-bold text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="users.data && users.data.length > 0">
              <tr
                v-for="user in users.data"
                :key="user.id"
                class="table-row-hover"
              >
                <!-- Username -->
                <td class="py-3">
                  <div class="d-flex align-center ga-3">
                    <v-avatar
                      color="primary"
                      variant="tonal"
                      size="36"
                      class="font-weight-bold"
                    >
                      {{ user.username.charAt(0).toUpperCase() }}
                    </v-avatar>
                    <div>
                      <div class="font-weight-bold text-body-2">
                        {{ user.username }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        ID: #{{ user.id }}
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Employee -->
                <td class="py-3">
                  <div v-if="user.employee" class="d-flex flex-column">
                    <span class="text-body-2 font-weight-medium">
                      {{ user.employee.first_name }}
                      {{ user.employee.last_name }}
                    </span>
                    <span class="text-caption text-medium-emphasis">{{
                      user.employee.email || "No email"
                    }}</span>
                  </div>
                  <v-chip v-else size="x-small" variant="tonal" color="grey"
                    >Unlinked</v-chip
                  >
                </td>

                <!-- Roles -->
                <td class="py-3">
                  <div class="d-flex flex-wrap ga-1">
                    <v-chip
                      v-for="role in user.roles"
                      :key="role.id"
                      size="small"
                      color="primary"
                      variant="tonal"
                      class="font-weight-medium"
                    >
                      {{ role.name }}
                    </v-chip>
                    <span
                      v-if="!user.roles || user.roles.length === 0"
                      class="text-caption text-medium-emphasis"
                    >
                      No roles assigned
                    </span>
                  </div>
                </td>

                <!-- Status -->
                <td class="py-3">
                  <v-chip
                    :color="user.status === 'active' ? 'success' : 'error'"
                    size="small"
                    variant="flat"
                    class="text-capitalize font-weight-bold"
                  >
                    {{ user.status }}
                  </v-chip>
                </td>

                <!-- Actions -->
                <td class="py-3 text-center">
                  <v-menu location="bottom end">
                    <template #activator="{ props }">
                      <v-btn
                        icon="mdi-dots-vertical"
                        variant="text"
                        size="small"
                        v-bind="props"
                      />
                    </template>

                    <v-list
                      density="compact"
                      elevation="4"
                      class="rounded-lg py-1"
                    >
                      <v-list-item
                        prepend-icon="mdi-pencil-outline"
                        title="Edit Details"
                        @click="openEditDialog(user)"
                      />
                      <v-list-item
                        prepend-icon="mdi-shield-account-outline"
                        title="Manage Roles"
                        @click="openRolesDialog(user)"
                      />
                      <v-list-item
                        prepend-icon="mdi-key-variant"
                        title="Reset Password"
                        @click="openResetPasswordDialog(user)"
                      />
                      <v-divider class="my-1" />
                      <v-list-item
                        v-if="user.status === 'active'"
                        prepend-icon="mdi-account-off-outline"
                        title="Deactivate Account"
                        class="text-error"
                        @click="toggleUserStatus(user)"
                      />
                      <v-list-item
                        v-else
                        prepend-icon="mdi-account-check-outline"
                        title="Activate Account"
                        class="text-success"
                        @click="toggleUserStatus(user)"
                      />
                    </v-list>
                  </v-menu>
                </td>
              </tr>
            </template>
            <tr v-else>
              <td colspan="5" class="text-center py-8 text-medium-emphasis">
                <v-icon
                  icon="mdi-account-search-outline"
                  size="48"
                  class="mb-2 text-disabled"
                />
                <p class="text-subtitle-2">
                  No user accounts found matching your criteria.
                </p>
              </td>
            </tr>
          </tbody>
        </v-table>

        <!-- Pagination -->
        <v-divider />
        <div v-if="users.last_page > 1" class="d-flex justify-end pa-4">
          <v-pagination
            v-model="users.current_page"
            :length="users.last_page"
            density="comfortable"
            rounded="circle"
            active-color="primary"
            @update:model-value="onPageChange"
          />
        </div>
      </v-card>

      <!-- CREATE USER DIALOG -->
      <v-dialog v-model="createDialog" max-width="540" persistent>
        <v-card rounded="xl" class="pa-2">
          <v-card-title
            class="d-flex align-center justify-space-between pt-4 px-4"
          >
            <span class="text-h6 font-weight-bold">Create User Account</span>
            <v-btn
              icon="mdi-close"
              variant="text"
              density="compact"
              @click="createDialog = false"
            />
          </v-card-title>

          <v-card-text class="pt-2 px-4">
            <v-form ref="createForm" @submit.prevent="submitCreateUser">
              <v-text-field
                v-model="createForm.username"
                label="Username"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                class="mb-3"
                :rules="[(v) => !!v || 'Username is required']"
              />

              <v-text-field
                v-model="createForm.password"
                label="Password"
                type="password"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                class="mb-3"
                :rules="[(v) => !!v || 'Password is required']"
              />

              <v-select
                v-model="createForm.employee_id"
                :items="employees"
                item-title="full_name"
                item-value="id"
                label="Link to Employee (Optional)"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                clearable
                class="mb-3"
              />

              <v-select
                v-model="createForm.roles"
                :items="roles"
                item-title="name"
                item-value="name"
                label="Assign Roles"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                multiple
                chips
                closable-chips
              />
            </v-form>
          </v-card-text>

          <v-card-actions class="pa-4 justify-end">
            <v-btn variant="text" rounded="lg" @click="createDialog = false"
              >Cancel</v-btn
            >
            <v-btn
              color="primary"
              rounded="lg"
              class="px-6 font-weight-medium"
              :loading="saving"
              @click="submitCreateUser"
            >
              Create Account
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- EDIT DETAILS DIALOG -->
      <v-dialog v-model="editDialog" max-width="500" persistent>
        <v-card rounded="xl" class="pa-2">
          <v-card-title
            class="d-flex align-center justify-space-between pt-4 px-4"
          >
            <span class="text-h6 font-weight-bold">Edit User Details</span>
            <v-btn
              icon="mdi-close"
              variant="text"
              density="compact"
              @click="editDialog = false"
            />
          </v-card-title>

          <v-card-text class="pt-2 px-4">
            <v-form @submit.prevent="submitEditUser">
              <v-text-field
                v-model="editForm.username"
                label="Username"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                class="mb-3"
              />

              <v-select
                v-model="editForm.employee_id"
                :items="employees"
                item-title="full_name"
                item-value="id"
                label="Linked Employee"
                variant="outlined"
                density="comfortable"
                rounded="lg"
                clearable
              />
            </v-form>
          </v-card-text>

          <v-card-actions class="pa-4 justify-end">
            <v-btn variant="text" rounded="lg" @click="editDialog = false"
              >Cancel</v-btn
            >
            <v-btn
              color="primary"
              rounded="lg"
              class="px-6 font-weight-medium"
              :loading="saving"
              @click="submitEditUser"
            >
              Save Changes
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- MANAGE ROLES DIALOG -->
      <v-dialog v-model="rolesDialog" max-width="480" persistent>
        <v-card rounded="xl" class="pa-2">
          <v-card-title
            class="d-flex align-center justify-space-between pt-4 px-4"
          >
            <span class="text-h6 font-weight-bold">Assign Roles</span>
            <v-btn
              icon="mdi-close"
              variant="text"
              density="compact"
              @click="rolesDialog = false"
            />
          </v-card-title>

          <v-card-text class="pt-2 px-4">
            <p class="text-caption text-medium-emphasis mb-3">
              Modifying roles for
              <strong>{{ selectedUser?.username }}</strong> will change their
              access permissions.
            </p>

            <v-select
              v-model="rolesForm.roles"
              :items="roles"
              item-title="name"
              item-value="name"
              label="Roles"
              variant="outlined"
              density="comfortable"
              rounded="lg"
              multiple
              chips
              closable-chips
            />
          </v-card-text>

          <v-card-actions class="pa-4 justify-end">
            <v-btn variant="text" rounded="lg" @click="rolesDialog = false"
              >Cancel</v-btn
            >
            <v-btn
              color="primary"
              rounded="lg"
              class="px-6 font-weight-medium"
              :loading="saving"
              @click="submitRoles"
            >
              Update Roles
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- RESET PASSWORD DIALOG -->
      <v-dialog v-model="resetPasswordDialog" max-width="450" persistent>
        <v-card rounded="xl" class="pa-2">
          <v-card-title
            class="d-flex align-center justify-space-between pt-4 px-4"
          >
            <span class="text-h6 font-weight-bold">Reset Password</span>
            <v-btn
              icon="mdi-close"
              variant="text"
              density="compact"
              @click="resetPasswordDialog = false"
            />
          </v-card-title>

          <v-card-text class="pt-2 px-4">
            <p class="text-caption text-medium-emphasis mb-3">
              Enter a new password for account
              <strong>{{ selectedUser?.username }}</strong
              >.
            </p>

            <v-text-field
              v-model="passwordForm.password"
              label="New Password"
              type="password"
              variant="outlined"
              density="comfortable"
              rounded="lg"
              class="mb-3"
            />

            <v-text-field
              v-model="passwordForm.password_confirmation"
              label="Confirm New Password"
              type="password"
              variant="outlined"
              density="comfortable"
              rounded="lg"
            />
          </v-card-text>

          <v-card-actions class="pa-4 justify-end">
            <v-btn
              variant="text"
              rounded="lg"
              @click="resetPasswordDialog = false"
              >Cancel</v-btn
            >
            <v-btn
              color="primary"
              rounded="lg"
              class="px-6 font-weight-medium"
              :loading="saving"
              @click="submitResetPassword"
            >
              Reset Password
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </v-container>
  </SidebarLayout>
</template>

<script>
import SidebarLayout from "@/Layouts/SideBarLayout.vue";

export default {
  components: {
    SidebarLayout,
  },

  props: {
    users: {
      type: Object,
      required: true,
    },
    filters: {
      type: Object,
      default: () => ({ search: "", status: null }),
    },
    employees: {
      type: Array,
      default: () => [],
    },
    roles: {
      type: Array,
      default: () => [],
    },
  },

  data() {
    return {
      search: this.filters.search || "",
      statusFilter: this.filters.status || null,
      statusOptions: [
        { title: "All Statuses", value: null },
        { title: "Active", value: "active" },
        { title: "Inactive", value: "inactive" },
      ],
      saving: false,
      searchTimer: null,

      // Dialog States
      createDialog: false,
      editDialog: false,
      rolesDialog: false,
      resetPasswordDialog: false,
      selectedUser: null,

      // Forms
      createForm: {
        username: "",
        password: "",
        employee_id: null,
        roles: [],
      },
      editForm: {
        username: "",
        employee_id: null,
      },
      rolesForm: {
        roles: [],
      },
      passwordForm: {
        password: "",
        password_confirmation: "",
      },
    };
  },

  methods: {
    debouncedSearch() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => {
        this.applyFilters();
      }, 350);
    },

    applyFilters() {
      this.$inertia.get(
        route("administration.user.index"),
        {
          search: this.search || undefined,
          status: this.statusFilter || undefined,
        },
        { preserveState: true, replace: true }
      );
    },

    onPageChange(page) {
      this.$inertia.get(
        route("administration.user.index"),
        {
          page,
          search: this.search || undefined,
          status: this.statusFilter || undefined,
        },
        { preserveState: true }
      );
    },

    openCreateDialog() {
      this.createForm = {
        username: "",
        password: "",
        employee_id: null,
        roles: [],
      };
      this.createDialog = true;
    },

    submitCreateUser() {
      this.saving = true;
      this.$inertia.post(route("administration.user.store"), this.createForm, {
        preserveScroll: true,
        onSuccess: () => {
          this.createDialog = false;
          this.showToast("User account created successfully.", "success");
        },
        onError: () =>
          this.showToast("Failed to create user account.", "error"),
        onFinish: () => (this.saving = false),
      });
    },

    openEditDialog(user) {
      this.selectedUser = user;
      this.editForm = {
        username: user.username,
        employee_id: user.employee_id,
      };
      this.editDialog = true;
    },

    submitEditUser() {
      this.saving = true;
      this.$inertia.put(
        route("administration.user.update", this.selectedUser.id),
        this.editForm,
        {
          preserveScroll: true,
          onSuccess: () => {
            this.editDialog = false;
            this.showToast("User updated successfully.", "success");
          },
          onError: () => this.showToast("Failed to update user.", "error"),
          onFinish: () => (this.saving = false),
        }
      );
    },

    openRolesDialog(user) {
      this.selectedUser = user;
      this.rolesForm.roles = user.roles ? user.roles.map((r) => r.name) : [];
      this.rolesDialog = true;
    },

    submitRoles() {
      this.saving = true;
      this.$inertia.put(
        route("administration.user.assign-role", this.selectedUser.id),
        this.rolesForm,
        {
          preserveScroll: true,
          onSuccess: () => {
            this.rolesDialog = false;
            this.showToast("Roles updated successfully.", "success");
          },
          onError: () => this.showToast("Failed to assign roles.", "error"),
          onFinish: () => (this.saving = false),
        }
      );
    },

    openResetPasswordDialog(user) {
      this.selectedUser = user;
      this.passwordForm = { password: "", password_confirmation: "" };
      this.resetPasswordDialog = true;
    },

    submitResetPassword() {
      this.saving = true;
      this.$inertia.put(
        route("administration.user.reset-password", this.selectedUser.id),
        this.passwordForm,
        {
          preserveScroll: true,
          onSuccess: () => {
            this.resetPasswordDialog = false;
            this.showToast("Password reset successfully.", "success");
          },
          onError: () => this.showToast("Failed to reset password.", "error"),
          onFinish: () => (this.saving = false),
        }
      );
    },

    toggleUserStatus(user) {
      const isActivating = user.status !== "active";
      const routeName = isActivating
        ? "administration.user.activate"
        : "administration.user.deactivate";

      this.$inertia.patch(
        route(routeName, user.id),
        {},
        {
          preserveScroll: true,
          onSuccess: () => {
            this.showToast(
              `User account ${
                isActivating ? "activated" : "deactivated"
              } successfully.`,
              "success"
            );
          },
          onError: () => this.showToast("Failed to update status.", "error"),
        }
      );
    },
  },
};
</script>

<style scoped>
/* Modern, theme-aware muted header */
.v-table :deep(thead th) {
  background-color: rgba(var(--v-theme-on-surface), 0.04) !important;
  color: rgba(var(--v-theme-on-surface), 0.75) !important;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.12) !important;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-size: 0.75rem !important;
}

/* Subtle row hover */
.table-row-hover:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.03);
  transition: background-color 0.15s ease;
}
</style>