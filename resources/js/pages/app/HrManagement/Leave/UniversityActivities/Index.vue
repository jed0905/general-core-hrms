<template>
  <LeaveManagementTabs v-model:activeTab="activeTab" />
  <FilterWrapper v-model="isPanelOpen">
    <v-form @submit.prevent="handleFilter()">
      <v-row>
        <v-col cols="12" md="4">
          <v-text-field
            label="Search"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.search"
            prepend-inner-icon="mdi-magnify"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            label="Type"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.type"
            :items="type"
            item-title="title"
            item-value="value"
          ></v-select>
        </v-col>
        <v-col cols="12" md="4">
          <v-text-field
            type="date"
            label="Start At"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.start_at"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="4">
          <v-text-field
            type="date"
            label="End At"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.end_at"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            label="Status"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.status"
            :items="status"
            item-title="title"
            item-value="value"
          ></v-select>
        </v-col>
        <v-col cols="12" md="4">
          <v-autocomplete
            label="Operating Unit"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.operating_unit_ids"
            :items="operatingUnits"
            item-title="name"
            item-value="id"
            multiple
            chips
            closable-chips
          ></v-autocomplete>
        </v-col>

        <v-col cols="12" md="3">
          <v-select
            label="Direction"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="filterOptions.directions"
            v-model="filterForm.direction"
          ></v-select>
        </v-col>
        <v-col cols="12" md="1">
          <v-select
            label="Size"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="filterOptions.sizes"
            v-model="filterForm.size"
          ></v-select>
        </v-col>
      </v-row>
      <v-divider class="my-4" style="border: 1px solid black"></v-divider>
      <div class="d-flex align-center justify-end">
        <ButtonMuted
          class="mr-2"
          name="Reset"
          @click="resetFilter()"
        ></ButtonMuted>
        <ButtonSuccess name="Search" type="submit"></ButtonSuccess>
      </div>
    </v-form>
  </FilterWrapper>
  <TableWrapper>
    <div class="d-flex align-center justify-space-between mb-4">
      <div class="v-card-title text-h6">University Activities</div>
      <Link :href="route('hrmanagement.universityActivities.create')">
        <v-btn
          color="starbucks-green"
          rounded="xl"
          prepend-icon="mdi-plus"
          min-width="120"
        >
          Add
        </v-btn>
      </Link>
    </div>
    <v-divider></v-divider>
    <v-table>
      <thead>
        <tr>
          <th>Title</th>
          <th>Type</th>
          <th>Description</th>
          <th>Start At</th>
          <th>End At</th>
          <th>Operating Units</th>
          <th>Status</th>
          <th>Document Control Number</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="universityActivity in universityActivities.data"
          :key="universityActivity.id"
        >
          <td>{{ universityActivity.title }}</td>
          <td>{{ universityActivity.type }}</td>
          <td>{{ universityActivity.description }}</td>
          <td>{{ universityActivity.start_at }}</td>
          <td>{{ universityActivity.end_at }}</td>
          <td>
            <v-chip
              v-for="operatingUnit in universityActivity.operating_units"
              :key="operatingUnit.id"
              class="mr-1 mb-1"
              size="small"
              color="primary"
              variant="tonal"
            >
              {{ operatingUnit }}
            </v-chip>
          </td>
          <td>{{ universityActivity.status }}</td>
          <td>{{ universityActivity.document_control_number }}</td>
          <td class="text-center">
            <v-btn
              icon="mdi-delete"
              size="x-small"
              color="red-darken-1"
              variant="tonal"
              class="mr-2"
              @click="
                deleteConfirmationDialog = true;
                id = universityActivity.id;
              "
            ></v-btn>
            <Link :href="universityActivity.edit_link">
              <v-btn
                icon="mdi-pencil"
                size="x-small"
                color="orange-darken-4"
                variant="tonal"
                class="ml-2"
              ></v-btn>
            </Link>
          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination class="mt-2" :meta="universityActivities.meta" />
  </TableWrapper>
  <DeleteDialog
    v-model="deleteConfirmationDialog"
    @confirm="handleDelete()"
    @cancel="deleteConfirmationDialog = false"
    :message="deleteMessage"
  />
  <!-- <pre>{{ universityActivities }}</pre> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import LeaveManagementTabs from "@/components/LeaveManagementTabs.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import Pagination from "@/components/Pagination.vue";
import { useForm } from "@inertiajs/vue3";
import { defaultSizes, defaultDirections } from "@/utils/filters";
import DeleteDialog from "@/components/DeleteDialog.vue";

export default {
  layout: SidebarLayout,
  components: {
    LeaveManagementTabs,
    TableWrapper,
    FilterWrapper,
    ButtonSuccess,
    ButtonMuted,
    Pagination,
    DeleteDialog,
  },
  props: {
    errors: Object,
    universityActivities: Object,
    operatingUnits: Object,
  },
  data() {
    return {
      activeTab: "configure",
      isPanelOpen: [0],
      deleteConfirmationDialog: false,
      deleteMessage:
        "Are you sure you want to delete this university activity?",
      filterForm: useForm({
        search: null,
        type: null,
        start_at: null,
        end_at: null,
        status: null,
        operating_unit_ids: [],
        size: null,
        direction: "Ascending",
      }),
      filterOptions: {
        sizes: defaultSizes,
        directions: defaultDirections,
      },
      type: [
        {
          title: "Event",
          value: "event",
        },
        {
          title: "Suspension",
          value: "suspension",
        },
        {
          title: "Work from Home",
          value: "work_from_home",
        },
        {
          title: "Advisory",
          value: "advisory",
        },
      ],
      status: [
        {
          title: "Planned",
          value: "planned",
        },
        {
          title: "Ongoing",
          value: "ongoing",
        },
        {
          title: "Completed",
          value: "completed",
        },
        {
          title: "Cancelled",
          value: "cancelled",
        },
      ],
    };
  },
  methods: {
    handleFilter() {
      this.filterForm.post(route("hrmanagement.universityActivities.index"), {
        preserveState: true,
        preserveScroll: true,
        only: ["universityActivities", "operatingUnits"],
      });
    },

    resetFilter() {
      this.filterForm.search = null;
      this.filterForm.type = null;
      this.filterForm.start_at = null;
      this.filterForm.end_at = null;
      this.filterForm.status = null;
      this.filterForm.operating_unit_ids = [];
      this.filterForm.size = null;
      this.filterForm.direction = "Ascending";
      this.handleFilter();
    },

    handleDelete() {
      this.$inertia.delete(
        route("hrmanagement.universityActivities.destroy", this.id),
        {
          preserveScroll: true,
          onSuccess: () => {
            this.deleteConfirmationDialog = false;
            this.id = null;
            this.showToast(
              "University activity deleted successfully",
              "success"
            );
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(errorMessages, "error");
          },
        }
      );
    },
  },
};
</script>
