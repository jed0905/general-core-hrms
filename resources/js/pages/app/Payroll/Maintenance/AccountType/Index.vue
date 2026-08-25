<template>
  <PayrollMaintenanceTabs :activeTab="activeTab" @update:activeTab="updateActiveTab" />
  <TableWrapper >
    <div class="d-flex align-center justify-end">
      <Link :href="route('payroll.maintenance.accounttype.create')">
      <v-btn 
        color="starbucks-green" 
        prepend-icon="mdi-plus"
        min-width="120"
        rounded="xl"
      >
        Add
      </v-btn>
      </Link>
    </div>
    <v-table>
      <thead>
        <tr>
          <th>Account Type Name</th>
          <th>Operating Unit</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="payrollAccountType in payrollAccountTypes.data" :key="payrollAccountType.id">
          <td>{{ payrollAccountType.name }}</td>
          <td>{{ payrollAccountType.operating_unit?.shortcut ?? '' }}</td>
          <td class="text-center">
            <v-btn 
              variant="tonal"
              color="red" 
              icon="mdi-delete"
              size="x-small"
              @click="deleteDialog = true; id = payrollAccountType.id"
            ></v-btn>
            <Link :href="payrollAccountType.signed_url">
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
    <Pagination :pagination="payrollAccountTypes.meta" />
  </TableWrapper>
  <DeleteDialog
    :modelValue="deleteDialog"
    @update:modelValue="deleteDialog = $event"
    @confirm="handleDelete()"
    @cancel="deleteDialog = false"
  />
</template>
<script>
  import Layout from '@/layouts/SidebarLayout.vue';
  import TableWrapper from '@/components/TableWrapper.vue';
  import PayrollMaintenanceTabs from '@/components/Payroll/PayrollMaintenanceTabs.vue';
  import DeleteDialog from '@/components/DeleteDialog.vue';
  import Pagination from '@/components/Pagination.vue';
  export default {
      layout: Layout,
      components: {
        TableWrapper,
        PayrollMaintenanceTabs,
        DeleteDialog,
        Pagination,
      },
      props:{
        errors: Object,
        payrollAccountTypes: Array,
      },
      data() {
        return {
          activeTab: 'accountTypes',
          deleteDialog: false,
        }
      },
      methods: {
        handleDelete() {
          this.$inertia.delete(route('payroll.maintenance.accounttype.delete', this.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
              this.showToast('Account type deleted successfully', 'success');
              this.deleteDialog = false;
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
              this.deleteDialog = false;
            },
          });
        },
        
      }
    }
</script>