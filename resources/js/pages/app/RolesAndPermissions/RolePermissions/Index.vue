<template>
  <div class="mb-3 d-flex justify-space-between align-center">
    <Breadcrumbs
      :items="[
        {
          title: 'Role Permissions',
          disabled: false,
          href: '#',
        },
      ]"
    />
  </div>
  <v-row>
    <v-col cols="3">
      <v-card class="mx-auto my-5" max-width="350" color="teal lighten-3">
        <v-card-title class="text-h6 font-weight-bold text-indigo-900">
          Roles
        </v-card-title>

        <v-divider></v-divider>

        <v-list dense>
          <div class="list-item">
            <v-list-item
              v-for="role in roles"
              :active="selectedRoleId == role.id"
              :key="role.id"
              clickable
              ripple
              class="role-button"
              @click="selectRole(role)"
            >
              <v-list-item-content>
                <v-list-item-title class="font-weight-medium text-indigo-900">
                  {{ role.name }}
                </v-list-item-title>
              </v-list-item-content>
            </v-list-item>
          </div>
        </v-list>
      </v-card>
    </v-col>
    <v-col cols="9">
      <v-table class="mx-auto my-5">
        <thead style="background-color: #90a4ae">
          <tr>
            <th class="text-white">Action</th>
            <th class="text-center text-white">Permission</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(permission, index) in permissions" :key="index">
            <td>{{ permission.name }}</td>
            <td class="text-center">
              <v-switch
                color="starbucks-green"
                :model-value="hasPermission(permission.id)"
                @update:model-value="
                  (val) => togglePermission(val, permission.id)
                "
                hide-details
              ></v-switch>
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-col>
  </v-row>

  <!-- Delete Dialog -->
  <DeleteDialog
    v-model="isDeleteDialog"
    message="Are you sure you want to delete this permission?"
    :loading="loading"
    @confirm="handleDelete"
    @cancel="isDeleteDialog = false"
  />

  <!-- <pre>{{ selectedRolePermissions }}</pre> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import PrimaryButton from "@/components/PrimaryButton.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import DeleteDialog from "@/components/DeleteDialog.vue";

export default {
  layout: SidebarLayout,
  components: {
    Breadcrumbs,
    PrimaryButton,
    TableWrapper,
    DeleteDialog,
  },
  props: {
    roles: Array,
    permissions: Array,
    selectedRoleId: Number,
    selectedRolePermissions: Array,
  },
  data() {
    return {
      //   currentPermissions: this.selectedRolePermissions,
      //   currentRoleId: this.selectedRoleId,
      activeRole: this.selectedRoleId,
    };
  },
  methods: {
    selectRole(role) {
      if (this.currentRoleId === role.id) return;
      this.$inertia.get(
        route("role-permission.management.index"),
        {
          role_id: role.id,
        },
        {
          preserveScroll: true,
          preserveState: true,
          replace: true,
          only: ["selectedRolePermissions", "selectedRoleId"],
          onSuccess: ({ props }) => {
            this.currentRoleId = props.selectedRoleId;
            this.currentPermissions = props.permissions;
          },
        }
      );
    },

    hasPermission(permissionId) {
      return this.selectedRolePermissions.includes(permissionId);
    },

    togglePermission(enabled, permissionId) {
      console.log(enabled);
      const roleId = this.selectedRoleId;

      if (!roleId) return;

      const url = enabled
        ? route("role-permission.management.attach", {
            role: roleId,
            permission: permissionId,
          })
        : route("role-permission.management.detach", {
            role: roleId,
            permission: permissionId,
          });

      this.$inertia.post(
        url,
        {},
        {
          preserveState: true,
          preserveScroll: true,

          onSuccess: () => {
            this.showToast("Permission updated.", "success");
          },

          onError: (errors) => {
            if (errors) {
              this.showToast(errors.message, "error");
            }
          },
        }
      );
    },
  },
};
</script>
<style scoped>
.list-item {
  padding: 8px 16px;
}

/* Role buttons styling */
.role-button {
  border: 1px solid #5c6bc0;
  border-radius: 4px;
  margin: 8px 0;
  background-color: white;
  transition: 0.3s ease;
}

.role-button:hover {
  background-color: #e8eaf6;
}
</style>
