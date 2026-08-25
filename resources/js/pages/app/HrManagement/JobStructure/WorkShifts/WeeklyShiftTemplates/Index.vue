<template>
  <JobStructureTabs :activeTab="activeTab" />
  <FilterWrapper v-model="isPanelOpen">
    <v-form @submit.prevent="handleFilter()">
      <v-row>
        <v-col cols="12" sm="12" md="6">
          <v-text-field
            label="Search"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.search"
            hide-details
            prepend-inner-icon="mdi-magnify"
          ></v-text-field>
        </v-col>
        <v-col cols="12" sm="12" md="4">
          <v-select
            label="Direction"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.direction"
            :items="filterOptions.direction"
            hide-details
          ></v-select>
        </v-col>
        <v-col cols="12" sm="12" md="2">
          <v-select
            label="Size"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.size"
            :items="filterOptions.size"
            hide-details
          ></v-select>
        </v-col>
      </v-row>
      <v-divider
        class="my-4"
        style="border:1px solid black;"
      ></v-divider>
      <div class="d-flex align-center justify-end">
        <ButtonMuted name="Reset" @click="resetFilter()" />
        <ButtonSuccess name="Search" type="submit" class="ml-2" />
      </div>
    </v-form>
  </FilterWrapper>
  <TableWrapper>
    <div class="d-flex align-center justify-space-between">
      <div class="v-card-title text-h6 font-weight-medium">
        Weekly Shift Templates
      </div>
      <Link :href="route('hrmanagement.jobstructure.weeklyShiftTemplates.create')">
        <ButtonSuccess prepend-icon="mdi-plus" name="Add" />
      </Link>
    </div>
    <v-table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Description</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="weeklyShiftTemplate in weeklyShiftTemplates.data" :key="weeklyShiftTemplate.id">
          <td>{{ weeklyShiftTemplate.name }}</td>
          <td>{{ weeklyShiftTemplate.description }}</td>
          <td class="text-center">
            <v-btn
              color="red"
              variant="tonal"
              size="x-small"
              icon="mdi-trash-can"
              @click="deleteConfirmationDialog = true; id = weeklyShiftTemplate.id"
            />
            <Link :href="weeklyShiftTemplate.edit_link">
              <v-btn
                color="orange-darken-4"
                variant="tonal"
                size="x-small"
                icon="mdi-pencil"
                class="ml-2"
              />
            </Link>
            <Link :href="route('hrmanagement.jobstructure.weeklyShiftTemplates.manage', {id: weeklyShiftTemplate.id})">
              <v-btn
                color="blue-darken-4"
                variant="tonal"
                size="x-small"
                class="ml-2"
                icon="mdi-cog"
              ></v-btn>
            </Link>
          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination 
      class="mt-3" 
      :meta="weeklyShiftTemplates.meta" 
      :filters="filterForm.data()"
    />
  </TableWrapper>

  <DeleteDialog
    v-model="deleteConfirmationDialog"
    message="Are you sure you want to delete this weekly shift template?"
    @confirm="handleDelete()"
    @cancel="deleteConfirmationDialog = false"
  />

  <!-- <pre>{{ filters }}</pre> -->
</template>
<script>
  import ButtonMuted from '@/components/ButtonMuted.vue'
  import ButtonSuccess from '@/components/ButtonSuccess.vue'
  import Pagination from '@/components/Pagination.vue'
  import TableWrapper from '@/components/TableWrapper.vue'
  import SidebarLayout from '@/layouts/SidebarLayout.vue'
  import JobStructureTabs from '@/components/JobStructureTabs.vue'
  import DeleteDialog from '@/components/DeleteDialog.vue'
  import FilterWrapper from '@/components/FilterWrapper.vue'
  import { useForm } from '@inertiajs/vue3'
  import { defaultSizes, defaultDirections } from '@/utils/filters'
 
  export default {
    layout: SidebarLayout,
    components: {
      JobStructureTabs,
      ButtonMuted,
      ButtonSuccess,
      Pagination,
      TableWrapper,
      DeleteDialog,
      FilterWrapper,
    },
    props: {
      errors: Object,
      weeklyShiftTemplates: Object,
      filters: Object,
    },
    data(){
      return {
        activeTab: "workShift",
        deleteConfirmationDialog: false,
        isPanelOpen: [0],
        filterForm: useForm({
          search: this.$page.props.filters.search || '',
          direction: this.$page.props.filters.direction || 'Ascending',
          size: this.$page.props.filters.size || 10,
        }),
        filterOptions: {
          size: defaultSizes,
          direction: defaultDirections,
        },
      }
    },
    methods: {
      handleDelete(){
        this.$inertia.delete(route('hrmanagement.jobstructure.weeklyShiftTemplates.destroy', this.id), {
          onSuccess: () => {
            this.deleteConfirmationDialog = false;
            this.showToast('Weekly shift template deleted successfully', 'success');
          },
          onError: () => {
            this.showToast('Failed to delete weekly shift template', 'error');
          }
        });
      },

      handleFilter(){
        this.filterForm.post(route('hrmanagement.jobstructure.weeklyShiftTemplates.index'), {
          preserveState: true,
          preserveScroll: true,
          only: ['weeklyShiftTemplates'],
        });
      },

      resetFilter(){
        this.filterForm.search = null;
        this.filterForm.size = 10;
        this.filterForm.direction = 'Ascending';
        this.handleFilter();
      },

       
    }
  }
</script>