<template>
  <LeaveManagementTabs :activeMenuTitle="activeMenuTitle" v-model:activeTab="activeTab" />
  <FilterWrapper v-model="isPanelOpen">
    <v-form>
      <v-row>
        <v-col cols="12" lg="4" md="4" sm="12" xs="12">
          <v-text-field
            type="date"
            label="To"
            variant="outlined"
            color="primary"
            rounded="lg"
            density="compact"
            size="small"
            v-model="filterForm.to"
          ></v-text-field>

        </v-col>
        <v-col cols="12" lg="4" md="4" sm="12" xs="12">
          <v-text-field
            type="date"
            label="From"
            variant="outlined"
            color="primary"
            rounded="lg"
            density="compact"
            size="small"
            v-model="filterForm.from"
          ></v-text-field>
        </v-col>
        <v-col cols="12" lg="4" md="4" sm="12" xs="12">
          <v-select
            label="Operating Unit"
            variant="outlined"
            color="primary"
            rounded="lg"
            density="compact"
            size="small"
            :items="operatingUnits"
            item-title="name"
            item-value="id"
            v-model="filterForm.operatingUnit"
          ></v-select>
        </v-col>

        <v-col cols="12">
          <div class="d-flex align-center justify-end">
            <ButtonMuted class="mr-2" name="Reset"></ButtonMuted>
            <ButtonSuccess name="Search"></ButtonSuccess>
          </div>
        </v-col>
      </v-row>
    </v-form>
  </FilterWrapper>
  <TableWrapper>
    <div class="d-flex align-center justify-space-between">
      <Link :href="route('hrmanagement.holiday.create')">
        <v-btn
          min-width="120"
          color="starbucks-green"
          rounded="xl"
          prepend-icon="mdi-plus"
        >
          Add
        </v-btn>
      </Link>
    </div>
    <v-divider class="my-4" style="border: 1px solid black;"></v-divider>
    <div class="d-flex align-center ">
        <div>
          <span v-if="!hasSelectedItems && holidayCount > 0">({{ holidayCount }}) Record<span v-if="holidayCount > 1">s</span> Found</span>
          <span v-if="hasSelectedItems">({{ selectedHolidays.length }}) Record<span v-if="selectedHolidays.length > 1">s</span> Selected</span>
        </div>
      <v-btn
        class="ml-3"
        v-if="hasSelectedItems"
        rounded="xl"
        color="error"
        variant="tonal"
        prepend-icon="mdi-delete"
        @click="openBulkDeleteDialog()"
      >Delete Selected</v-btn>
    </div>
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
            <th>Date</th>
            <th>Full Day/Half Day</th>
            <th>Repeats Annually</th>
            <th>Operating Unit</th>
            <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="holiday in holidays" :key="holiday.id">
          <th>
            <v-checkbox
              :value="holiday.id"
              v-model="selectedHolidays"
              @change="updateSelectAllState"
            ></v-checkbox>
          </th>
          <td>{{ holiday.name }}</td>
          <td>{{ new Date(holiday.date).toLocaleDateString('en-US', {month: 'long', day: 'numeric', year: 'numeric'}) }}</td>
          <td>{{ holiday.length == 1 ? 'Full Day' : 'Half Day' }}</td>
          <td>{{ holiday.repeats_annually === 1 ? 'Yes' : 'No' }}</td>
          <td>
            <v-chip
              v-for="operatingUnit in holiday.operating_units"
              :key="operatingUnit.id"
              class="mr-1 mb-1"
              size="small"
              color="primary"
              variant="tonal"
            >
              {{ operatingUnit.shortcut }}
            </v-chip>
          </td>

          <td class="text-center">
            <v-btn
              icon="mdi-delete"
              size="x-small"
              color="red-darken-1"
              variant="tonal"
              class="mr-2"
              @click="openDeleteDialog(holiday)"
            ></v-btn>
            <Link :href="holiday.edit_link">
              <v-btn
                icon="mdi-pencil"
                size="x-small"
                color="green-darken-1"
                variant="tonal"
              ></v-btn>
            </Link>

          </td>
        </tr>
      </tbody>
    </v-table>
  </TableWrapper>
  <DeleteDialog
    v-model="isDeleteDialog"
    message="Are you sure you want to delete this holiday?"
    @confirm="handleDelete"
    @cancel="isDeleteDialog = false"
  />

  <!-- Bulk Delete Dialog -->
  <DeleteDialog
    v-model="isBulkDeleteDialog"
    message="Are you sure you want to delete these holidays?"
    @confirm="handleBulkDelete"
    @cancel="isBulkDeleteDialog = false"
  />

  <!-- <pre>{{ holidays }}</pre> -->

</template>
<script>
  import SidebarLayout from '@/layouts/SidebarLayout.vue';
  import LeaveManagementTabs from '@/components/LeaveManagementTabs.vue';
  import TableWrapper from '@/components/TableWrapper.vue';
  import FilterWrapper from '@/components/FilterWrapper.vue';
  import ButtonSuccess from '@/components/ButtonSuccess.vue';
  import ButtonMuted from '@/components/ButtonMuted.vue';
  import DeleteDialog from '@/components/DeleteDialog.vue';
  import { useForm } from '@inertiajs/vue3';

  export default {
    layout: SidebarLayout,
    components:{
      LeaveManagementTabs,
      TableWrapper,
      FilterWrapper,
      ButtonSuccess,
      ButtonMuted,
      DeleteDialog,
    },
    props:{
      holidays: Object,
      holidayCount: Number,
      operatingUnits: Object,
    },
    data(){
      return{
        activeTab: 'configure',
        isPanelOpen: [0],
        activeMenuTitle: 'Holidays',
        selectAll: false,
        isIndeterminate: false,
        selectedHolidays: [],
        isDeleteDialog: false,
        selectedHoliday: null,
        isBulkDeleteDialog: false,
        filterForm: useForm({
          from: "",
          to: "",
          operatingUnit: "",
        }),
      }
    },
    methods: {
      toggleSelectAll() {
        if (this.selectedHolidays.length === this.holidays.length) {
          this.selectedHolidays = [];
          this.selectAll = false;
        } else {
          this.selectedHolidays = this.holidays.map(holiday => holiday.id);
          this.selectAll = true;
        }
      },
      updateSelectAllState() {
        if (this.selectedHolidays.length === this.holidays.length) {
          this.selectAll = true;
          this.isIndeterminate = false;
        } else if (this.selectedHolidays.length > 0) {
          this.selectAll = false;
          this.isIndeterminate = true;
        } else {
          this.selectAll = false;
          this.isIndeterminate = false;
        }
      },
      openBulkDeleteDialog() {
        // Add your bulk delete logic here
        console.log('Selected holidays for deletion:', this.selectedHolidays);
        this.isBulkDeleteDialog = true;
      },
      openDeleteDialog(holiday) {
        this.selectedHoliday = holiday;
        this.isDeleteDialog = true;
      },
      handleDelete() {
        this.$inertia.delete(route('hrmanagement.holiday.destroy', { id: this.selectedHoliday.id }), {
          onSuccess: () => {
            this.showToast('Holiday deleted successfully', 'success');
            this.isDeleteDialog = false;
          },
        });
      },
      handleBulkDelete() {
        this.$inertia.post(route('hrmanagement.holiday.bulkDestroy'), {
          ids: this.selectedHolidays,
          },{
            onSuccess: () => {
              this.showToast('Holidays deleted successfully', 'success');
              this.isBulkDeleteDialog = false;
            },
            onError: () => {
              this.showToast('Failed to delete holidays', 'error');
              this.isBulkDeleteDialog = false;
            }
        })
        this.isBulkDeleteDialog = false;
        this.selectedHolidays = [];
        this.selectAll = false;
        this.isIndeterminate = false;
      }
    },
    computed: {
      isIndeterminate() {
        return this.selectedHolidays.length > 0 && this.selectedHolidays.length < this.holidays.length;
      },
      hasSelectedItems() {
        return this.selectedHolidays.length > 0;
      }
    }
  }
</script>
