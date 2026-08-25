<template>
  <LeaveManagementTabs
    :activeMenuTitle="activeMenuTitle"
    v-model:activeTab="activeTab"
  />
  <TableWrapper>
    <div class="d-flex align-center justify-space-between">
      <div class="v-card-title">Special Leave Types</div>
      <div class="d-flex align-center gap-2">
        <v-btn
          v-if="selectedSpecialLeaves.length > 0"
          color="error"
          variant="elevated"
          prepend-icon="mdi-delete"
          @click="openBulkDeleteDialog"
          rounded="xl"
        >
          Delete Selected ({{ selectedSpecialLeaves.length }})
        </v-btn>
        <Link :href="route('hrmanagement.leave.specialLeave.create')">
          <ButtonSuccess prepend-icon="mdi-plus" name="Add" />
        </Link>
      </div>
    </div>
    <v-divider class="my-4" style="border: 1px solid black"></v-divider>
    <v-table>
      <thead>
        <tr>
          <th>
            <v-checkbox
              v-model="selectAll"
              :indeterminate="isIndeterminate"
              @change="toggleSelectAll"
            ></v-checkbox>
          </th>
          <th>Name</th>
          <th>Shortcut</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="specialLeave in specialLeaves" :key="specialLeave.id">
          <td>
            <v-checkbox
              :model-value="selectedSpecialLeaves.includes(specialLeave.id)"
              @update:model-value="toggleSelect(specialLeave.id)"
            ></v-checkbox>
          </td>
          <td>{{ specialLeave.name }}</td>
          <td>{{ specialLeave.shortcut }}</td>
          <td class="text-center">
            <Link :href="specialLeave.edit_link">
              <v-btn
                icon="mdi-pencil"
                variant="tonal"
                size="x-small"
                color="orange-darken-4"
                class="ml-2"
              ></v-btn>
            </Link>

            <v-btn
              v-if="$page.props.auth.roles?.[0] == 'superadmin'"
              icon="mdi-trash-can"
              variant="tonal"
              size="x-small"
              color="error"
              @click="openSingleDeleteDialog(specialLeave.id)"
            ></v-btn>
          </td>
        </tr>
      </tbody>
    </v-table>
  </TableWrapper>
  <DeleteDialog
    v-model="isDeleteDialog"
    :message="deleteDialogMessage"
    @confirm="handleDelete()"
    @cancel="isDeleteDialog = false"
  />
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import LeaveManagementTabs from "@/components/LeaveManagementTabs.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import { useForm } from "@inertiajs/vue3";
import TableWrapper from "@/components/TableWrapper.vue";
import DeleteDialog from "@/components/DeleteDialog.vue";

export default {
  layout: SidebarLayout,
  components: {
    LeaveManagementTabs,
    ButtonSuccess,
    ButtonMuted,
    TableWrapper,
    DeleteDialog,
  },
  props: {
    errors: Object,
    specialLeaves: Object,
  },
  data() {
    return {
      activeTab: "configure",
      isDeleteDialog: false,
      selectAll: false,
      isIndeterminate: false,
      selectedSpecialLeaves: [],
      id: null,
      isBulkDelete: false,
      deleteDialogMessage: "",
    };
  },
  methods: {
    handleDelete() {
      if (this.isBulkDelete) {
        // Handle bulk delete
        console.log("Bulk Delete");
        this.$inertia.post(
          route("hrmanagement.leave.specialLeave.bulkDestroy"),
          {
            ids: this.selectedSpecialLeaves,
          },
          {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
              this.showToast(
                `${this.selectedSpecialLeaves.length} special leave type(s) deleted successfully`,
                "success"
              );
              this.selectedSpecialLeaves = [];
              this.updateSelectAllState();
              this.isDeleteDialog = false;
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
              this.isDeleteDialog = false;
            },
          }
        );
      } else {
        console.log("Single Delete");
        // Handle single delete
        this.$inertia.delete(
          route("hrmanagement.leave.specialLeave.destroy", { id: this.id }),
          {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
              this.showToast(
                "Special leave type deleted successfully",
                "success"
              );
              this.isDeleteDialog = false;
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
              this.isDeleteDialog = false;
            },
          }
        );
      }
    },
    toggleSelectAll() {
      if (this.selectAll) {
        // If select all is checked, select all items
        this.selectedSpecialLeaves = this.specialLeaves.map(
          (specialLeave) => specialLeave.id
        );
      } else {
        // If select all is unchecked, deselect all items
        this.selectedSpecialLeaves = [];
      }
      this.updateSelectAllState();
    },
    toggleSelect(id) {
      const index = this.selectedSpecialLeaves.indexOf(id);
      if (index > -1) {
        this.selectedSpecialLeaves.splice(index, 1);
      } else {
        this.selectedSpecialLeaves.push(id);
      }
      this.updateSelectAllState();
    },
    updateSelectAllState() {
      const totalItems = this.specialLeaves.length;
      const selectedCount = this.selectedSpecialLeaves.length;

      if (selectedCount === 0) {
        this.selectAll = false;
        this.isIndeterminate = false;
      } else if (selectedCount === totalItems) {
        this.selectAll = true;
        this.isIndeterminate = false;
      } else {
        this.selectAll = false;
        this.isIndeterminate = true;
      }
    },
    openSingleDeleteDialog(id) {
      this.id = id;
      this.isBulkDelete = false;
      this.deleteDialogMessage =
        "Are you sure you want to delete this special leave type?";
      this.isDeleteDialog = true;
    },
    openBulkDeleteDialog() {
      this.isBulkDelete = true;
      this.deleteDialogMessage = `Are you sure you want to delete ${this.selectedSpecialLeaves.length} selected special leave type(s)?`;
      this.isDeleteDialog = true;
    },
  },
};
</script>
