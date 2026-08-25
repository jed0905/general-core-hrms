<template>
  <PayrollMaintenanceTabs :activeTab="activeTab" @update:activeTab="activeTab = $event" />
  <TableWrapper>
    <div class="d-flex align-center justify-end">
      <v-btn
        v-if="selectedDeductions.length"
        class="mr-4"
        color="error"
        prepend-icon="mdi-delete"
        rounded="xl"
        min-width="120"
        @click="bulkDeleteDialog = true"
      >
        Delete ({{ selectedCount }})
      </v-btn>
      <Link :href="route('payroll.maintenance.deduction.create')">
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
          <th>
            <v-checkbox
              :model-value="allSelected"
              :indeterminate="isIndeterminate"
              @update:modelValue="toggleSelectAll"
            ></v-checkbox>
          </th>
          <th>Deduction Name</th>
          <th>Operating Unit</th>
          <th class="text-center">Action</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="payrollDeduction in payrollDeductions.data" :key="payrollDeduction.id">
          <td><v-checkbox v-model="selectedDeductions" :value="payrollDeduction.id"></v-checkbox></td>
          <td>{{ payrollDeduction.name }}</td>
          <td>{{ payrollDeduction.operating_unit?.shortcut ?? '' }}</td>
          <td class="text-center">
            <v-btn
              variant="tonal"
              color="error"
              icon="mdi-delete"
              size="x-small"
              @click="deleteDialog = true; id = payrollDeduction.id"
            >
            </v-btn>
            <Link :href="payrollDeduction.signed_url" class="text-decoration-none">
              <v-btn
                variant="tonal"
                color="warning"
                icon="mdi-pencil"
                size="x-small"
                class="ml-4"
              ></v-btn>
            </Link>
          </td>
        </tr>
      </tbody>
    </v-table>
    <Pagination :meta="payrollDeductions.meta" :partials="['payrollDeductions']" />
  </TableWrapper>
  <!-- <pre>{{ payrollDeductions }}</pre> -->
  <DeleteDialog 
    :modelValue="deleteDialog"
    @update:modelValue="deleteDialog = $event"
    @confirm="deleteDeduction()"
    @cancel="deleteDialog = false"
  />
  <DeleteDialog 
    :modelValue="bulkDeleteDialog"
    @update:modelValue="bulkDeleteDialog = $event"
    :message="`Are you sure you want to delete ${selectedCount} selected item(s)?`"
    @confirm="confirmBulkDelete()"
    @cancel="bulkDeleteDialog = false"
  />
</template>
<script>
  import SidebarLayout from '@/layouts/SidebarLayout.vue'
  import TableWrapper from '@/components/TableWrapper.vue'
  import FilterWrapper from '@/components/FilterWrapper.vue'
  import PayrollMaintenanceTabs from '@/components/Payroll/PayrollMaintenanceTabs.vue'
  import DeleteDialog from '@/components/DeleteDialog.vue'
  import Pagination from '@/components/Pagination.vue'
  export default {
    layout: SidebarLayout,
    components: {
      TableWrapper,
      FilterWrapper,
      PayrollMaintenanceTabs,
      DeleteDialog,
      Pagination,
   },
   props:{
    errors: Object,
    payrollDeductions: Object,
   },
   data(){
    return {
      activeTab: 'deductions',
      deleteDialog: false,
      bulkDeleteDialog: false,
      selectedDeductions: [],
      id: null,
    }
   },
   computed: {
     allIds(){
       return (this.payrollDeductions?.data || []).map(item => item.id)
     },
     allSelected(){
       const total = this.allIds.length
       return total > 0 && this.selectedDeductions.length === total
     },
     isIndeterminate(){
       return this.selectedDeductions.length > 0 && !this.allSelected
     },
     selectedCount(){
       return this.selectedDeductions.length
     }
   },
   methods:{
    confirmBulkDelete(){
      // Placeholder: implement bulk deletion on backend when ready
      this.bulkDeleteDialog = false
    },
    toggleSelectAll(checked){
      this.selectedDeductions = checked ? [...this.allIds] : []
    },
    
    deleteDeduction(){
      this.$inertia.delete(route('payroll.maintenance.deduction.delete', this.id),{
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          this.showToast('Deduction deleted successfully', 'success');
          this.deleteDialog = false;
        },
        onError: (errors) => {
          const errorMessages = Object.values(errors).flat().join(" ");
          this.showToast(`${errorMessages}`, 'error');
          this.deleteDialog = false;
        }
      });
    }
   }
  }
</script>