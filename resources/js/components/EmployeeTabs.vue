<template>
  <div class="mb-10 d-flex gap-5 ">
    <Link
      :href="route('hrmanagement.employee.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'employeeIndex' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'employeeIndex')"
        :text-color="
          activeTab === 'employeeIndex' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Employee List
      </v-btn>
    </Link>
    <Link
      :href="route('hrmanagement.separated-employees.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'separatedEmployee' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'separatedEmployee')"
        :text-color="
          activeTab === 'separatedEmployee' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Separated Employees List
      </v-btn>
    </Link>

    <Link
      :href="route('hrmanagement.employee.create')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'employeeAdd' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'employeeAdd')"
        :text-color="
          activeTab === 'employeeAdd' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Add Employee
      </v-btn>
    </Link>

    <Link
      :href="route('hrmanagement.employee.report.index')"
      class="text-decoration-none"
    >
      <v-btn
        :color="
          activeTab === 'reports' ? 'light-green-lighten-4' : 'grey-lighten-3'
        "
        @click="$emit('update:activeTab', 'reports')"
        :text-color="
          activeTab === 'reports' ? 'light-green-darken-4' : 'grey-darken-2'
        "
        class="text-none rounded-lg"
        min-width="120"
      >
        Reports
      </v-btn>
    </Link>
  </div>
</template>

<script>
export default {
  name: "EmployeeTabs",
  props: {
    activeTab: {
      type: String,
      required: true,
    },
  },
};
</script>

