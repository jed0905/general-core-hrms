<template>
  <div class="mb-10 d-flex gap-5 ">
    <Link
      :href="route('payroll.employee.maintenance.accounts.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'accounts' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'accounts')"
        :text-color="
          activeTab === 'accounts' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Employee Accounts
      </v-btn>
    </Link>

    <Link
      :href="route('payroll.employee.maintenance.deductions.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'deductions' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'deductions')"
        :text-color="
          activeTab === 'deductions' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Employee Deductions
      </v-btn>
    </Link>

   
  </div>
</template>

<script>
export default {
  name: "PayrollEmployeeMaintenanceTabs",
  props: {
    activeTab: {
      type: String,
      required: true,
    },
  },
};
</script>

