<template>
  <LeaveManagementTabs
    :activeMenuTitle="activeMenuTitle"
    v-model:activeTab="activeTab"
  />

  <TableWrapper>
    <v-row>
      <v-col cols="12">
        <div class="d-flex justify-space-between align-center">
          <p class="text-h6 font-weight-bold">Leave Types</p>
          <Link :href="route('hrmanagement.leave.leaveType.create')">
            <ButtonSuccess prepend-icon="mdi-plus" name="Add" v-if="isPrivileged" />
          </Link>
        </div>
      </v-col>
      <v-divider class="mt-3"></v-divider>
      <v-col cols="12">
        <div class="d-flex justify-space-between align-center">
          <p
            class="text-h6"
            v-if="!hasSelectedItems && totalCountOfLeaveTypes > 0"
          >
            ({{ totalCountOfLeaveTypes }})
            <span v-if="totalCountOfLeaveTypes > 1">Records</span>
            <span v-else>Record</span> Found
          </p>
          <p class="text-h6" v-if="hasSelectedItems">
            ({{ selectedLeaveTypesCount }})
            <span v-if="selectedLeaveTypesCount > 1">Records</span>
            <span v-else>Record</span> Selected
          </p>
          <v-btn
            v-if="hasSelectedItems"
            variant="tonal"
            prepend-icon="mdi-delete"
            text="Delete Selected"
            color="error"
            rounded="xl"
            @click="openBulkDeleteDialog()"
          ></v-btn>
        </div>
      </v-col>
    </v-row>
    <v-table>
      <thead>
        <tr>
          <td>
            <v-checkbox
              v-model="selectAll"
              :indeterminate="isIndeterminate"
              @change="toggleSelectAll"
            />
          </td>
          <td>Leave Type</td>
          <td>Shortcut</td>
          <td class="text-center">Actions</td>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(leaveType, index) in leaveTypes" :key="index">
          <td>
            <v-checkbox
              v-model="leaveType.selected"
              @change="updateSelectAllState"
            />
          </td>
          <td>{{ leaveType.name }}</td>
          <td>{{ leaveType.shortcut }}</td>
          <td class="text-center">
            <Link :href="leaveType.edit_link">
              <v-btn
                icon="mdi-pencil"
                variant="tonal"
                size="x-small"
                color="orange-darken-4"
                class="mr-2"
              ></v-btn>
            </Link>

            <v-btn
              v-if="$page.props.auth.roles?.[0] == 'superadmin'"
              icon="mdi-delete"
              variant="tonal"
              size="x-small"
              color="error"
              @click="openDeleteDialog(leaveType)"
            />
          </td>
        </tr>
      </tbody>
    </v-table>
  </TableWrapper>

  <!-- Delete Confirmation Dialog -->
  <v-dialog v-model="dialog" max-width="500" persistent>
    <v-card>
      <v-card-title class="text-h6 d-flex align-center">
        <v-icon color="error" class="mr-3">mdi-alert-circle</v-icon>
        Delete Leave Type
      </v-card-title>
      <v-card-text class="pt-4">
        <p class="text-body-1">
          Are you sure you want to delete
          <strong>"{{ selectedLeaveType?.name }}"</strong>?
        </p>
        <p class="text-caption text-grey-darken-1 mt-2">
          This action cannot be undone. All associated data will be permanently
          removed.
        </p>
      </v-card-text>
      <v-card-actions class="pa-4 pt-0">
        <v-spacer></v-spacer>
        <v-btn
          color="grey-darken-1"
          variant="outlined"
          @click="closeDeleteDialog"
          class="text-none"
          rounded="xl"
          min-width="120"
        >
          Cancel
        </v-btn>
        <v-btn
          color="error"
          variant="elevated"
          @click="deleteLeaveType()"
          class="text-none ml-2"
          rounded="xl"
          min-width="120"
        >
          Delete
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- Bulk Delete Confirmation Dialog -->
  <v-dialog v-model="bulkDeleteDialog" max-width="500" persistent>
    <v-card>
      <v-card-title class="text-h6 d-flex align-center">
        <v-icon color="error" class="mr-3">mdi-alert-circle</v-icon>
        Delete Selected Leave Types
      </v-card-title>
      <v-card-text class="pt-4">
        <p class="text-body-1">
          Are you sure you want to delete
          <strong>{{ selectedLeaveTypesCount }}</strong> selected leave type(s)?
        </p>
        <p class="text-caption text-grey-darken-1 mt-2">
          This action cannot be undone. All associated leave credits of
          employees will be permanently removed.
        </p>
      </v-card-text>
      <v-card-actions class="pa-4 pt-0">
        <v-spacer></v-spacer>
        <v-btn
          color="grey-darken-1"
          variant="outlined"
          @click="closeBulkDeleteDialog()"
          class="text-none"
          rounded="xl"
          min-width="120"
        >
          Cancel
        </v-btn>
        <v-btn
          color="error"
          variant="elevated"
          @click="deleteSelectedLeaveTypes()"
          class="text-none ml-2"
          rounded="xl"
          min-width="120"
        >
          Delete All
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
  <!-- <pre>{{ leaveTypes }}</pre> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import LeaveManagementTabs from "@/components/LeaveManagementTabs.vue";

export default {
  layout: SidebarLayout,
  components: {
    TableWrapper,
    ButtonSuccess,
    LeaveManagementTabs,
  },
  props: {
    leaveTypes: Array,
    totalCountOfLeaveTypes: Number,
  },
  data() {
    return {
      activeTab: "configure",

      isActiveMenuTitle: "configure",
      dialog: false,
      bulkDeleteDialog: false,
      deleting: false,
      selectedLeaveType: null,
      activeMenuTitle: "Leave Types",
      selectAll: false,
      isIndeterminate: false,
      selectedLeaveType: null,
    };
  },
  computed: {
    hasSelectedItems() {
      return this.leaveTypes.some((leaveType) => leaveType.selected);
    },
    selectedLeaveTypesCount() {
      return this.leaveTypes.filter((leaveType) => leaveType.selected).length;
    },
    selectedLeaveTypeIds() {
      return this.leaveTypes
        .filter((leaveType) => leaveType.selected)
        .map((leaveType) => leaveType.id);
    },
  },
  methods: {
    toggleSelectAll() {
      this.leaveTypes.forEach((leaveType) => {
        leaveType.selected = this.selectAll;
      });
      this.isIndeterminate = false;
    },
    updateSelectAllState() {
      const selectedCount = this.leaveTypes.filter(
        (leaveType) => leaveType.selected
      ).length;
      const totalCount = this.leaveTypes.length;

      if (selectedCount === 0) {
        this.selectAll = false;
        this.isIndeterminate = false;
      } else if (selectedCount === totalCount) {
        this.selectAll = true;
        this.isIndeterminate = false;
      } else {
        this.selectAll = false;
        this.isIndeterminate = true;
      }
    },
    openDeleteDialog(leaveType) {
      this.selectedLeaveType = leaveType;
      this.dialog = true;
    },
    closeDeleteDialog() {
      this.dialog = false;
      this.selectedLeaveType = null;
      this.deleting = false;
    },

    deleteLeaveType() {
      this.$inertia.delete(
        route("hrmanagement.leave.leaveType.delete", this.selectedLeaveType.id),
        {
          onSuccess: () => {
            this.showToast("Leave type deleted successfully", "success");
            this.closeDeleteDialog();
          },
          onError: () => {
            this.showToast("Failed to delete leave type", "error");
            this.closeDeleteDialog();
          },
        }
      );
    },

    openBulkDeleteDialog() {
      this.bulkDeleteDialog = true;
    },
    closeBulkDeleteDialog() {
      this.bulkDeleteDialog = false;
    },
    deleteSelectedLeaveTypes() {
      const selectedIds = this.selectedLeaveTypeIds;
      console.log("Selected IDs:", selectedIds);
      console.log(
        "Route URL:",
        route("hrmanagement.leave.leaveType.bulkDelete")
      );
      this.$inertia.post(
        route("hrmanagement.leave.leaveType.bulkDelete"),
        {
          ids: selectedIds,
        },
        {
          onSuccess: () => {
            this.showToast("Leave types deleted successfully", "success");
            this.closeBulkDeleteDialog();
          },
          onError: () => {
            this.showToast("Failed to delete leave types", "error");
            this.closeBulkDeleteDialog();
          },
        }
      );
      // this.$inertia.delete(route('hrmanagement.leave.leaveType.bulkDelete'), {
      //   data: {
      //     ids: selectedIds
      //   },
      //   onSuccess: () => {
      //     this.showToast('Leave types deleted successfully', 'success');
      //     this.closeBulkDeleteDialog();
      //     // Clear all selections
      //     this.leaveTypes.forEach(leaveType => {
      //       leaveType.selected = false;
      //     });
      //     this.selectAll = false;
      //     this.isIndeterminate = false;
      //   },
      //   onError: () => {
      //     this.showToast('Failed to delete leave types', 'error');
      //     this.closeBulkDeleteDialog();
      //   }
      // });
    },
  },
};
</script>
