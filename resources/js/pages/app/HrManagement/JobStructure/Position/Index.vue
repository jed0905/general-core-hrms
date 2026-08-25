<template>
  <JobStructureTabs v-model:activeTab="activeTab" />
  <FilterWrapper v-model="isPanelOpen">
    <v-form @submit.prevent="handleFilter()">
      <v-row>
        <v-col cols="12" :md="mdSize">
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
        <v-col cols="12" :md="mdSize">
          <v-select
            variant="outlined"
            density="compact"
            rounded="lg"
            hide-details
            label="Salary Grade"
            :items="salary_grades"
            item-title="salary_grade"
            tiem-value="id"
            v-model="filterForm.salary_grade"
            prepend-inner-icon="mdi-briefcase-check"
          ></v-select>
        </v-col>
        <v-col
          v-if="
            $page.props.auth.roles[0] === 'superadmin' ||
            $page.props.auth.roles[0] === 'hr_director'
          "
          cols="12"
          :md="mdSize"
        >
          <v-select
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
          ></v-select>
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
            <ButtonSuccess name="Search" type="submit" />
          </div>
        </v-col>
      </v-row>
    </v-form>
  </FilterWrapper>
  <TableWrapper>
    <v-card-title class="mb-2 d-flex justify-space-between align-center">
      <span>Positions</span>
      <Link :href="route('hrmanagement.jobstructure.position.create')">
        <ButtonSuccess class="ml-2" name="+ Add" />
      </Link>
    </v-card-title>
    <v-divider class="mb-6"></v-divider>
    <v-table>
      <thead>
        <tr>
          <th>Position</th>
          <th>Shortcut</th>
          <th>Plantilla Item Number</th>
          <th>Salary Grade</th>
          <th>Operating Unit</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in positions.data" :key="item.id">
          <td>{{ item.name }}</td>
          <td>{{ item.shortcut }}</td>
          <td>{{ item.plantilla_item_number ?? "N/A" }}</td>
          <td>{{ item.salary_grade ?? "N/A" }}</td>
          <td>
            {{ item.operating_unit.name }}
          </td>
          <td>
            <v-btn
              size="x-small"
              color="red-darken-4"
              variant="tonal"
              icon="mdi-delete"
              @click="deleteConfirmationDialog = true; id=item.id"
            ></v-btn>

            <Link :href="item.edit_link">
              <v-btn
                size="x-small"
                color="yellow-darken-4"
                variant="tonal"
                icon="mdi-pencil"
                class="ml-4"
              ></v-btn>
            </Link>
          </td>
          
        </tr>
      </tbody>
    </v-table>
    <Pagination 
      class="mt-3" 
      :meta="positions.meta" 
      :filters="filterForm.data()"
    />
  </TableWrapper>
  <DeleteDialog
    v-model="deleteConfirmationDialog"
    message="Are you sure you want to delete this position?"
    :loading="loading"
    @confirm="handleDelete()"
    @cancel="deleteConfirmationDialog = false"
  />
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import TableWrapper from "@/components/TableWrapper.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";
import JobStructureTabs from "@/components/JobStructureTabs.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import { useForm } from "@inertiajs/vue3";
import Pagination from "@/components/Pagination.vue";
import DeleteDialog from "@/components/DeleteDialog.vue";
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
    DeleteDialog,
  },

  props: {
    positions: Object,
    operating_units: Object,
    salary_grades: Object,
  },
  data() {
    return {
      isPanelOpen: [0],
      activeTab: "position",

      filterForm: useForm({
        search: null,
        salary_grade: null,
        operating_unit: null,
        size: 10,
        direction: 'Ascending',
      }),
      filterOptions: {
        size: defaultSizes,
        direction: defaultDirections,
      },
      deleteConfirmationDialog: false,
      selectedJobStatus: null,
      mdSize:
        this.$page.props.auth.roles[0] === "superadmin" ||
        this.$page.props.auth.roles[0] === "hr_director"
          ? 4
          : 6,
    };
  },
  methods: {
    handleFilter() {
      this.filterForm.post(route("hrmanagement.jobstructure.position.index"), {
        preserveState: true,
        preserveScroll: true,
        only: ["positions"],
      });
    },

    resetFilter() {
      this.filterForm.search = null;
      this.filterForm.salary_grade = null;
      this.filterForm.operating_unit = null;
      this.filterForm.size = 10;
      this.filterForm.direction = 'Ascending';
      this.handleFilter();
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
      this.filterForm.transform(() => formData).post(route("hrmanagement.jobstructure.position.index"), {
        preserveState: true,
        preserveScroll: true,
        only: ["positions"],
      });
    },

    handleDelete(){
      this.$inertia.delete(route("hrmanagement.jobstructure.position.destroy", {
        id: this.id
      }), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          this.showToast("Position deleted successfully!", "success");
          this.deleteConfirmationDialog = false;
        },
        onError: (errors) => {
          const errorMessages = Object.values(errors).flat().join(" ");
          this.showToast(`${errorMessages}`, "error");
          this.deleteConfirmationDialog = false;
        },
      });
    },
  },
};
</script>
