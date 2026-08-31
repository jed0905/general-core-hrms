<script setup>
import { ref, watch } from "vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import SidebarLayout from "@/Layouts/SidebarLayout.vue";

const props = defineProps({
  roles: {
    type: Array,
    required: true,
  },
  permissions: {
    type: Object,
    required: true,
  },
});

// Active Role State
const selectedRole = ref(props.roles[0] || null);

// Permission Sync Form
const syncForm = useForm({
  permissions: [],
});

// Populate form when selected role changes or props update
const loadRolePermissions = (role) => {
  if (!role) return;
  selectedRole.value = role;
  syncForm.permissions = role.permissions
    ? role.permissions.map((p) => p.name)
    : [];
};

watch(
  () => props.roles,
  (newRoles) => {
    if (selectedRole.value) {
      const updated = newRoles.find((r) => r.id === selectedRole.value.id);
      if (updated) loadRolePermissions(updated);
    } else if (newRoles.length > 0) {
      loadRolePermissions(newRoles[0]);
    }
  },
  { deep: true, immediate: true }
);

const handleSelectRole = (role) => {
  loadRolePermissions(role);
};

// Category helpers
const formatCategoryName = (category) => {
  return category
    .replace(/_/g, " ")
    .replace(/-/g, " ")
    .replace(/\b\w/g, (l) => l.toUpperCase());
};

const isCategoryAllSelected = (categoryPermissions) => {
  return categoryPermissions.every((p) =>
    syncForm.permissions.includes(p.name)
  );
};

const toggleCategoryPermissions = (categoryPermissions) => {
  const allSelected = isCategoryAllSelected(categoryPermissions);
  const categoryNames = categoryPermissions.map((p) => p.name);

  if (allSelected) {
    syncForm.permissions = syncForm.permissions.filter(
      (name) => !categoryNames.includes(name)
    );
  } else {
    const updated = new Set([...syncForm.permissions, ...categoryNames]);
    syncForm.permissions = Array.from(updated);
  }
};

const savePermissions = () => {
  if (!selectedRole.value) return;
  syncForm.put(
    route("administration.role.sync-permissions", selectedRole.value.id),
    {
      preserveScroll: true,
    }
  );
};

// Create Role Dialog
const createDialog = ref(false);
const createForm = useForm({
  name: "",
});

const submitCreateRole = () => {
  createForm.post(route("administration.role.store"), {
    preserveScroll: true,
    onSuccess: () => {
      createDialog.value = false;
      createForm.reset();
    },
  });
};

// Delete Role Dialog
const deleteDialog = ref(false);
const roleToDelete = ref(null);

const confirmDeleteRole = (role) => {
  roleToDelete.value = role;
  deleteDialog.value = true;
};

const submitDeleteRole = () => {
  if (!roleToDelete.value) return;
  router.delete(route("administration.role.destroy", roleToDelete.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      deleteDialog.value = false;
      if (selectedRole.value?.id === roleToDelete.value.id) {
        selectedRole.value =
          props.roles.find((r) => r.id !== roleToDelete.value.id) || null;
      }
      roleToDelete.value = null;
    },
  });
};
</script>

<template>
  <SidebarLayout>
    <Head title="Roles & Permissions" />

    <v-container fluid class="pa-6">
      <!-- Header -->
      <div class="d-flex align-center justify-space-between mb-6">
        <div>
          <h1 class="text-h5 font-weight-bold text-high-emphasis">
            Roles & Permissions
          </h1>
          <p class="text-body-2 text-medium-emphasis">
            Manage system roles and configure access permissions.
          </p>
        </div>
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          elevation="0"
          @click="createDialog = true"
        >
          Create Role
        </v-btn>
      </div>

      <v-row>
        <!-- Left Panel: Role List -->
        <v-col cols="12" md="4" lg="3">
          <v-card variant="outlined" class="rounded-lg">
            <v-card-title
              class="text-subtitle-1 font-weight-bold py-3 px-4 border-b"
            >
              System Roles
            </v-card-title>

            <v-list class="pa-2" nav density="compact">
              <v-list-item
                v-for="role in roles"
                :key="role.id"
                :value="role.id"
                :active="selectedRole?.id === role.id"
                color="primary"
                rounded="lg"
                class="mb-1"
                @click="handleSelectRole(role)"
              >
                <template #prepend>
                  <v-icon icon="mdi-shield-account" size="small" class="mr-2" />
                </template>

                <v-list-item-title class="font-weight-medium">
                  {{ role.name }}
                </v-list-item-title>

                <v-list-item-subtitle class="text-caption">
                  {{ role.users_count || 0 }}
                  {{ role.users_count === 1 ? "user" : "users" }}
                </v-list-item-subtitle>

                <template #append>
                  <v-btn
                    v-if="!['Super Admin', 'Admin'].includes(role.name)"
                    icon="mdi-delete-outline"
                    variant="text"
                    size="x-small"
                    color="error"
                    @click.stop="confirmDeleteRole(role)"
                  />
                </template>
              </v-list-item>
            </v-list>
          </v-card>
        </v-col>

        <!-- Right Panel: Ultra-Compact Permission Toggles -->
        <v-col cols="12" md="8" lg="9">
          <v-card v-if="selectedRole" variant="outlined" class="rounded-lg">
            <!-- Role Header & Action -->
            <v-card-item class="py-3 px-5 border-b">
              <div
                class="d-flex align-center justify-space-between flex-wrap ga-3"
              >
                <div>
                  <div class="d-flex align-center ga-2">
                    <h2 class="text-h6 font-weight-bold text-high-emphasis">
                      {{ selectedRole.name }}
                    </h2>
                    <v-chip size="x-small" color="primary" variant="tonal">
                      {{ syncForm.permissions.length }} Active Permissions
                    </v-chip>
                  </div>
                  <p class="text-caption text-medium-emphasis mb-0">
                    Toggle permissions to grant or revoke access for this role.
                  </p>
                </div>

                <v-btn
                  color="primary"
                  prepend-icon="mdi-content-save-outline"
                  elevation="0"
                  size="small"
                  :loading="syncForm.processing"
                  :disabled="!syncForm.isDirty"
                  @click="savePermissions"
                >
                  Save Changes
                </v-btn>
              </div>
            </v-card-item>

            <!-- Grouped Permission Switch Matrix -->
            <v-card-text class="pa-5">
              <div
                v-for="(groupPermissions, category) in permissions"
                :key="category"
                class="mb-4 pb-4 border-b last-no-border"
              >
                <!-- Category Header -->
                <div class="d-flex align-center justify-space-between mb-2">
                  <div class="d-flex align-center ga-2">
                    <v-icon
                      icon="mdi-folder-key-outline"
                      size="x-small"
                      color="primary"
                    />
                    <span
                      class="text-subtitle-2 font-weight-bold text-high-emphasis"
                    >
                      {{ formatCategoryName(category) }}
                    </span>
                  </div>

                  <v-btn
                    variant="text"
                    size="x-small"
                    color="primary"
                    class="px-1"
                    @click="toggleCategoryPermissions(groupPermissions)"
                  >
                    {{
                      isCategoryAllSelected(groupPermissions)
                        ? "Deselect All"
                        : "Select All"
                    }}
                  </v-btn>
                </div>

                <!-- Ultra-Compact Micro Permission Cards Grid -->
                <v-row density="compact">
                  <v-col
                    v-for="perm in groupPermissions"
                    :key="perm.id"
                    cols="12"
                    sm="6"
                    md="4"
                    lg="3"
                  >
                    <div
                      class="perm-card rounded-md pa-1 px-2 d-flex align-center justify-space-between"
                      :class="{
                        'perm-card-active': syncForm.permissions.includes(
                          perm.name
                        ),
                      }"
                      @click="
                        syncForm.permissions.includes(perm.name)
                          ? (syncForm.permissions = syncForm.permissions.filter(
                              (p) => p !== perm.name
                            ))
                          : syncForm.permissions.push(perm.name)
                      "
                    >
                      <div class="pr-1 text-truncate">
                        <div
                          class="perm-title text-truncate"
                          :class="{
                            'perm-title-active': syncForm.permissions.includes(
                              perm.name
                            ),
                          }"
                        >
                          {{
                            formatCategoryName(
                              perm.name.split(".").slice(1).join(" ") ||
                                perm.name
                            )
                          }}
                        </div>
                        <div class="perm-subtext text-truncate">
                          {{ perm.name }}
                        </div>
                      </div>

                      <v-switch
                        v-model="syncForm.permissions"
                        :value="perm.name"
                        color="primary"
                        density="compact"
                        hide-details
                        inset
                        class="micro-switch"
                        @click.stop
                      />
                    </div>
                  </v-col>
                </v-row>
              </div>
            </v-card-text>
          </v-card>

          <!-- Empty State -->
          <v-card
            v-else
            variant="outlined"
            class="rounded-lg text-center pa-12"
          >
            <v-icon
              icon="mdi-shield-outline"
              size="64"
              color="medium-emphasis"
              class="mb-3"
            />
            <h3 class="text-h6 font-weight-medium text-high-emphasis">
              No Role Selected
            </h3>
            <p class="text-body-2 text-medium-emphasis">
              Select a role from the left list or create a new one to manage
              permissions.
            </p>
          </v-card>
        </v-col>
      </v-row>
    </v-container>

    <!-- Create Role Dialog -->
    <v-dialog v-model="createDialog" max-width="450">
      <v-card rounded="lg">
        <v-card-title class="text-h6 font-weight-bold pa-5 border-b">
          Create New Role
        </v-card-title>

        <v-card-text class="pa-5">
          <v-text-field
            v-model="createForm.name"
            label="Role Name"
            placeholder="e.g. Finance Manager"
            variant="outlined"
            density="comfortable"
            :error-messages="createForm.errors.name"
            hide-details="auto"
            autofocus
          />
        </v-card-text>

        <v-divider />

        <v-card-actions class="pa-4 justify-end">
          <v-btn variant="text" @click="createDialog = false">Cancel</v-btn>
          <v-btn
            color="primary"
            elevation="0"
            :loading="createForm.processing"
            @click="submitCreateRole"
          >
            Create
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Role Confirmation Dialog -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card rounded="lg">
        <v-card-title class="text-h6 font-weight-bold pa-5 border-b">
          Delete Role
        </v-card-title>

        <v-card-text class="pa-5">
          Are you sure you want to delete the
          <strong>{{ roleToDelete?.name }}</strong> role? This action cannot be
          undone.
        </v-card-text>

        <v-divider />

        <v-card-actions class="pa-4 justify-end">
          <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
          <v-btn color="error" elevation="0" @click="submitDeleteRole"
            >Delete</v-btn
          >
        </v-card-actions>
      </v-card>
    </v-dialog>
  </SidebarLayout>
</template>

<style scoped>
.last-no-border:last-child {
  border-bottom: none !important;
  margin-bottom: 0 !important;
  padding-bottom: 0 !important;
}

/* Micro Permission Card Styles */
.perm-card {
  height: 40px;
  cursor: pointer;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  background-color: transparent;
  transition: background-color 0.15s ease, border-color 0.15s ease;
  user-select: none;
}

.perm-card:hover {
  background-color: rgba(var(--v-theme-on-surface), 0.04);
}

/* Active State with Theme-Aware Primary Tint and High Contrast */
.perm-card-active {
  border-color: rgb(var(--v-theme-primary)) !important;
  background-color: rgba(var(--v-theme-primary), 0.08) !important;
}

.perm-title {
  font-size: 0.72rem;
  font-weight: 500;
  line-height: 1.1;
  color: rgb(var(--v-theme-on-surface));
}

.perm-title-active {
  color: rgb(var(--v-theme-primary)) !important;
  font-weight: 700;
}

.perm-subtext {
  font-size: 0.62rem;
  line-height: 1;
  color: rgba(var(--v-theme-on-surface), 0.55);
}

/* Scaled Micro Switch */
.micro-switch {
  flex: 0 0 auto;
  transform: scale(0.62);
  transform-origin: right center;
  margin-right: -4px;
}

.micro-switch :deep(.v-selection-control) {
  min-height: auto !important;
}
</style>