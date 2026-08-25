<template>
  <JobStructureTabs v-model:activeTab="activeTab" />

  <TableWrapper>
    <v-card-title class="mb-2 d-flex justify-space-between align-center">
      <span>Job Status</span>
      <Link :href="route('hrmanagement.jobstructure.jobstatus.create')">
        <ButtonSuccess class="ml-2" name="+ Add" />
      </Link>
    </v-card-title>
    <v-divider class="mb-6"></v-divider>
    <v-table>
      <thead>
        <tr>
          <th>Job Status</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in jobStatus" :key="item.id">
          <td>{{ item.name }}</td>
          <td class="text-center">
            <!-- Edit button with tooltip -->
            <v-tooltip text="Edit Job Status" location="top">
              <template v-slot:activator="{ props }">
                <Link
                  v-bind="props.props"
                  :href="item.edit_link"
                  class="v-btn v-btn--text text-orange-darken-4"
                  style="min-width: unset; padding: 0 8px; height: 36px"
                >
                  <v-btn
                    variant='tonal'
                    size="x-small"
                    icon="mdi-pencil"
                  ></v-btn>
                  <!-- <v-icon>mdi-pencil</v-icon> -->
                </Link>
              </template>
            </v-tooltip>

            <!-- Delete button -->
            <!-- <v-tooltip text="Delete User" location="top" v-if="item.can_delete">
              <template v-slot:activator="{ props }">
                <v-btn
                  v-bind="props.props"
                  color="red"
                  variant="text"
                  @click="
                    (deleteConfirmationDialog = true), (selectedUser = item)
                  "
                >
                  <v-icon>mdi-delete</v-icon>
                </v-btn>
              </template>
            </v-tooltip> -->
          </td>
        </tr>
      </tbody>
    </v-table>
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

  <!-- <pre>{{ jobStatus }}</pre> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import ButtonPrimary from "@/components/ButtonPrimary.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";
import JobStructureTabs from "@/components/JobStructureTabs.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";

export default {
  layout: SidebarLayout,
  components: {
    Breadcrumbs,
    ButtonPrimary,
    TableWrapper,
    FilterWrapper,
    JobStructureTabs,
    ButtonSuccess,
  },
  props: {
    jobStatus: Object,
  },
  data() {
    return {
      activeTab: "jobStatus",

      deleteConfirmationDialog: false,

      selectedJobStatus: null,
    };
  },
  methods: {

  },
};
</script>
