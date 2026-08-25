<template>
  <div class="mb-10 d-flex gap-5 ">
    <Link
      :href="route('payroll.maintenance.accounttype.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'accountTypes' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'accountTypes')"
        :text-color="
          activeTab === 'accountTypes' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Account Types
      </v-btn>
    </Link>

    <Link
      :href="route('payroll.maintenance.deduction.index')"
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
        Deductions
      </v-btn>
    </Link>

    <Link
      :href="route('payroll.maintenance.projectfund.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'funds' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'funds')"
        :text-color="
          activeTab === 'funds' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Projects
      </v-btn>
    </Link>
  </div>
</template>

<script>
export default {
  name: "PayrollMaintenanceTabs",
  props: {
    activeTab: {
      type: String,
      required: true,
    },
  },
};
</script>

