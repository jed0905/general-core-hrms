<template>
  <PayrollMaintenanceTabs :activeTab="activeTab" @update:activeTab="activeTab = $event" />
  <FilterWrapper v-model="isPanelOpen">
    <v-form>
      <v-row>
        <v-col cols="12" md="6">
          <v-text-field
            label="Search"
            variant="outlined"
            density="compact"
            rounded="lg"
            v-model="filterForm.search"
            hide-details
            prepend-inner-icon="mdi-magnify"
            clearable
            placeholder="Search by project name"
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            label="Operating Unit"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="operatingUnits"
            item-title="name"
            item-value="id"
            v-model="filterForm.operating_unit"
          ></v-select>
        </v-col>
        <v-col cols="12" md="2">
          <v-select
            label="Size"
            variant="outlined"
            density="compact"
            rounded="lg"
            :items="defaultSizes"
            v-model="filterForm.size"
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
    <div class="d-flex align-center justify-end">
      <Link :href="route('payroll.maintenance.projectfund.create')">
        <v-btn
          color="starbucks-green"
          prepend-icon="mdi-plus"
          rounded="xl"
          min-width="120"
        >
          Add
        </v-btn>
      </Link>
    </div>
    <v-table>
      <thead>
        <tr>
          <th>Project Name</th>
          <th>Allocation</th>
          <th>Employee Status</th>
          <th>Employee Type</th>
          <th>Operating Unit</th>
          <th class="text-center">Action</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="payrollProjectFund in payrollProjectFunds.data" :key="payrollProjectFund.id">
          <td>{{ payrollProjectFund.name }}</td>
          <td>{{ payrollProjectFund.allocation }}</td>
          <td>{{ payrollProjectFund.job_status.name }}</td>
          <td>{{ payrollProjectFund.employee_type }}</td>
          <td>{{ payrollProjectFund.operating_unit?.shortcut }}</td>
          <td class="text-center">
            <v-btn
              variant="tonal"
              color="error"
              icon="mdi-delete"
              size="x-small"
              @click="deleteDialog = true; id = payrollProjectFund.id"
            >
            </v-btn>
            <Link :href="payrollProjectFund.signed_url" class="text-decoration-none">
              <v-btn
                variant="tonal"
                color="warning"
                icon="mdi-pencil"
                size="x-small"
                class="ml-4"
              ></v-btn>
            </Link>

            <Link :href="payrollProjectFund.assign_link" class="text-decoration-none">
              <v-btn
                variant="tonal"
                color="primary"
                icon="mdi-account-group"
                size="x-small"
                class="ml-4"
              ></v-btn>
            </Link>
          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination :meta="payrollProjectFunds.meta" />
  </TableWrapper>

  <DeleteDialog 
    :modelValue="deleteDialog"
    @update:modelValue="deleteDialog = $event"
    @confirm="deleteProjectFund()"
    @cancel="deleteDialog = false"
  />
</template>
<script>
  import SidebarLayout from '@/layouts/SidebarLayout.vue'
  import TableWrapper from '@/components/TableWrapper.vue'
  import FilterWrapper from '@/components/FilterWrapper.vue'
  import PayrollMaintenanceTabs from '@/components/Payroll/PayrollMaintenanceTabs.vue'
  import DeleteDialog from '@/components/DeleteDialog.vue'
  import Pagination from '@/components/Pagination.vue'
  import { useForm } from '@inertiajs/vue3'
  import ButtonSuccess from '@/components/ButtonSuccess.vue'
  import ButtonMuted from '@/components/ButtonMuted.vue'
  import { defaultSizes } from '@/utils/filters'
  
  export default {
    layout: SidebarLayout,
    components: {
      TableWrapper,
      FilterWrapper,
      PayrollMaintenanceTabs,
      DeleteDialog,
      Pagination,
      ButtonSuccess,
      ButtonMuted,
    },
    props: {
      errors: Object,
      payrollProjectFunds: Object,
      operatingUnits: Object,
    },
    data(){
      return {
        isPanelOpen: [0],
        activeTab: 'funds',
        deleteDialog: false,
        defaultSizes,
        filterForm: useForm({
          search: null,
          operating_unit: null,
          size: null,
        }),
      }
    },
    methods: {
      deleteProjectFund(){
        this.$inertia.delete(route('payroll.maintenance.projectfund.delete', this.id),{
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            this.showToast('Project fund deleted successfully', 'success');
            this.deleteDialog = false;
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, 'error');
            this.deleteDialog = false;
          }
        });
      },

      filterForm(){
        this.filterForm.post(route('payroll.maintenance.projectfund.index'), {
          preserveScroll: true,
          preserveState: true,
          only: ['payrollProjectFunds'],
        })
      }
      
    }
  }
</script>