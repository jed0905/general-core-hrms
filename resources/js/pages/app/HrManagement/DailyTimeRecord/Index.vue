<template>
  <DailyTimeRecordTabs v-model:activeTab="activeTab" />
  <FilterWrapper v-model="isFilterOpen" class="mb-4">
    <v-form @submit.prevent="handleFilter()">
      <v-row>
        <v-col cols="12" sm="12" md="6">
          <v-text-field
            label="Search"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            v-model="filterForm.search"
            prepend-inner-icon="mdi-magnify"
          ></v-text-field>
        </v-col>

        <v-col cols="12" sm="12" md="6">
          <v-autocomplete
            label="Operating Unit"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            :items="operatingUnits"
            item-title="name"
            item-value="id"
            v-model="filterForm.operating_unit"
          ></v-autocomplete>
        </v-col>

        <v-col cols="12" sm="12" md="6">
          <v-autocomplete
            label="Department"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            :items="departments"
            item-title="name"
            item-value="id"
            v-model="filterForm.department"
          ></v-autocomplete>
        </v-col>
        <v-col cols="12" sm="6" md="6">
          <v-select
            label="Employment Status"
            variant="outlined"
            density="compact"
            :items="job_statuses"
            item-title="name"
            item-value="id"
            hide-details
            rounded="lg"
            v-model="filterForm.employment_status"
          ></v-select>
        </v-col>
        <v-col cols="12" sm="6" md="6">
          <v-select
            label="Employee Type"
            variant="outlined"
            density="compact"
            :items="employeeType"
            hide-details
            rounded="lg"
            v-model="filterForm.employee_type"
          ></v-select>
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            label="Size"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            :items="filterOptions.size"
            v-model="filterForm.size"
          ></v-select>
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            label="Direction"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            :items="filterOptions.direction"
            v-model="filterForm.direction"
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
  <v-row>
    <v-col cols="12">
      
      <v-card rounded="lg">
        <v-card-text>
          <div class="d-flex justify-space-between align-center">
            <span class="text-h5 font-weight-medium">Daily Time Record</span>
            <v-btn
              color="grey-darken-4"
              variant="tonal"
              prepend-icon="mdi-printer"
              :disabled="selectedEmployees.length === 0"
              @click.prevent="printMultipleDtr(selectedEmployees)"
            >
              Print Selected
            </v-btn>
          </div>
          <v-divider class="mt-4 mb-4" style="border: 1px solid black;"></v-divider>
          <!-- Employees lists -->

          <v-skeleton-loader
            v-if="!employees"
            type="table"
            class="mx-auto mt-8"
          >
          </v-skeleton-loader>

          <v-table
            v-else
          >
            <thead>
              <tr>
                <th>
                  <v-checkbox
                    v-model="selectAll"
                    @change="toggleSelectAll"
                    hide-details
                    density="compact"
                  ></v-checkbox>
                </th>
                <th>Employee No</th>
                <th>Biometrics ID</th>
                <th>Employee</th>
                <th>Department</th>
                <th>Employment Status</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="employee in employees.data" :key="employee.id">
                <td>
                  <v-checkbox
                    v-model="selectedEmployees"
                    :value="employee.id"
                    hide-details
                    density="compact"
                  ></v-checkbox>
                </td>
                <td>{{ employee.employee_number }}</td>
                <td>{{ employee.biometric_id.join(', ') }}</td>
                <td>
                  {{ employee.fullname_desc }}
                </td>
                <td>{{ employee.department?.name ?? "" }}</td>
                <td>{{ employee.job_status?.name ?? "" }}</td>
                <td class="text-center">
                  <Link
                    :href="employee.redirect_link"
                    class="text-decoration-none"
                  >
                    <v-btn
                      size="x-small"
                      color="yellow-darken-4"
                      icon="mdi-eye"
                      variant="tonal"
                      class="mr-2"
                    />
                  </Link>
                  <v-btn
                    size="x-small"
                    color="grey-darken-4"
                    icon="mdi-printer"
                    variant="tonal"
                    @click="printSingleDtr(employee.id)"
                  />
                </td>
              </tr>
            </tbody>
          </v-table>

          <Pagination
            class="mt-2"
            :meta="employees.meta"
            :filters="filterForm.data()"
          />
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <v-dialog v-model="isPrintDialogVisible" max-width="600">
    <v-card class="pa-4 ma-4">
      <v-card-title class="d-flex justify-space-between align-center">
        <h4>Print Daily Time Record</h4>
      </v-card-title>
      <v-card-text>
        <v-form @submit.prevent="printDailyTimeRecord()">
          <v-row>
            <v-col cols="6">
              <v-select
                v-model="selectedMonth"
                variant="outlined"
                density="compact"
                :items="months"
                label="Month"
              ></v-select>
            </v-col>
            <v-col cols="6">
              <v-select
                v-model="selectedYear"
                variant="outlined"
                density="compact"
                :items="years"
                label="Year"
                hide-details
              ></v-select>
            </v-col>
          </v-row>
          <v-row>
            <v-col cols="12">
              <v-select
                label="Period"
                variant="outlined"
                density="compact"
                hide-details
                hide-no-data
                :items="period"
                item-title="text"
                item-value="value"
                v-model="cutOff"
              ></v-select>
            </v-col>
          </v-row>
          <v-col cols="12">
            <div class="d-flex align-center justify-end">
              <ButtonMuted
                class="mr-2"
                name="Cancel"
                @click="resetPrintDialogForm()"
              />
              <ButtonSuccess name="Print" type="submit" />
            </div>
          </v-col>
        </v-form>
      </v-card-text>
    </v-card>
  </v-dialog>
  <!-- <pre>{{ employees }}</pre> -->
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import DailyTimeRecordTabs from "@/components/DailyTimeRecordTabs.vue";
import Pagination from "@/components/Pagination.vue";
import FilterWrapper from "@/components/FilterWrapper.vue";
import { useForm } from "@inertiajs/vue3";
import { employeeType } from "@/utils/EmployeeType";
import { defaultSizes, defaultDirections } from "@/utils/filters";

export default {
  layout: SidebarLayout,

  components: {
    ButtonMuted,
    ButtonSuccess,
    DailyTimeRecordTabs,
    Pagination,
    FilterWrapper,
  },

  props: {
    employees: Array,
    job_statuses: Object,
    departments: Object,
    operatingUnits: Object,
    filter: Object,
  },

  data() {

    const roles = (this.$page?.props?.auth?.roles) || [];
    const isSuperAdmin = roles.includes('superadmin');
    const isHrDirector = roles.includes('hr_director');
    const isCampusHr = roles.includes('campus_hr');
    const isCampusHrStaff = roles.includes('campus_hr_staff');
    const isPrivileged = isSuperAdmin || isHrDirector;
    const defaultOperatingUnitId = !isPrivileged
      ? (this.$page?.props?.auth?.user?.employee?.operating_unit_id ?? null)
      : null;

    return {
      disableOperatingUnitInput: isCampusHr || isCampusHrStaff,
      isPrivileged,
      initialOperatingUnitId: defaultOperatingUnitId,
      initialSize: this.filter?.size ?? 10,
      isResetting: false,

      activeTab: "list",
      isFilterOpen: [0],
      selectAll: false,
      selectedEmployees: [],
      employeeIds: [],
      employeeType,
      isPrintDialogVisible: false,

      cutOff: null,
      period: [
        { text: "1st Half", value: 1 },
        { text: "Full Month", value: 2 },
      ],

      months: [
        "January",
        "February",
        "March",
        "April",
        "May",
        "June",
        "July",
        "August",
        "September",
        "October",
        "November",
        "December",
      ],

      filterForm: useForm({
        search: null,
        operating_unit: defaultOperatingUnitId,
        department: null,
        employment_status: null,
        employee_type: null,
        size: this.filter.size ?? 10,
        direction: 'Ascending',
      }),

      filterOptions: {
        direction: defaultDirections,
        size:  defaultSizes,
      },

      years: Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i),
      selectedMonth: "", // will be set in mounted()
      selectedYear: "",
    };
  },

  created() {
    this.selectedMonth = this.currentMonth;
    this.selectedYear = this.currentYear;
  },


  computed: {
    currentMonth() {
      return new Date().toLocaleString("default", { month: "long" });
    },

    currentYear() {
      return new Date().getFullYear();
    },

  },

  watch:{
    'filterForm.operating_unit': function(newVal, oldVal){
      if(!this.isResetting && newVal !== oldVal){
        // Reset department when operating unit changes
        this.filterForm.department = null;
        this.fetchOperatingUnitDepartments(newVal);
      }
    },

    // Keep header checkbox in sync if user manually unchecks/checks
    selectedEmployees(val) {
      this.selectAll = val.length === this.employees.data.length;
    },
  },

  methods: {

    handleFilter() {
      this.filterForm.post(route("hrmanagement.dailytimerecord.index"), {
          preserveState: true,
          preserveScroll: true,
          only: ["employees"],
      });
    },

    resetFilter() {
      this.isResetting = true;
      this.filterForm.search = null;
      this.filterForm.department = null;
      this.filterForm.employment_status = null;
      this.filterForm.employee_type = null;
      this.filterForm.size = this.initialSize;
      this.filterForm.direction = 'Ascending';
      this.filterForm.operating_unit = this.isPrivileged ? null : this.initialOperatingUnitId;
      this.handleFilter();
      // this.$nextTick(() => {
      //   this.filterForm
      //     .transform((data) => ({ ...data, page: 1 }))
      //     .post(route("hrmanagement.dailytimerecord.dailytimerecord.index"), {
      //       preserveState: false,
      //       preserveScroll: true,
      //       only: ["employees"],
      //       onFinish: () => { this.isResetting = false; }
      //     });
      // });
    },

    fetchOperatingUnitDepartments(newVal){
      if(newVal){
        this.$inertia.post(route("hrmanagement.dailytimerecord.index"),
        {
          operating_unit: newVal,
          ...this.filterForm.data()
        },{
          preserveScroll:true,
          preserveState: true,
          only: ["departments"]
        })
      }
    },

    toggleSelectAll() {
      if (this.selectAll) {
        this.selectedEmployees = this.employees.data.map(
          (e) => e.id
        );
      } else {
        this.selectedEmployees = [];
      }
    },

    printSingleDtr(employeeId) {
      this.isPrintDialogVisible = true;
      this.employeeIds = employeeId;
    },

    printMultipleDtr(employeeIds) {
      this.isPrintDialogVisible = true;
      this.employeeIds = employeeIds;
    },

    resetPrintDialogForm() {
      this.isPrintDialogVisible = false;
      this.selectedMonth = this.currentMonth;
    },

    printDailyTimeRecord() {
      window.open(
        route("hrmanagement.dailytimerecord.printDailyTimeRecord", {
          emp_id: [this.employeeIds],
          month: this.selectedMonth,
          year: this.selectedYear,
          cut_off: this.cutOff,
        }),
        "_blank"
      );

      this.isPrintDialogVisible = false;
      this.resetPrintDialogForm();
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
      this.filterForm.transform(() => formData).post(route("hrmanagement.dailytimerecord.index"), {
        preserveState: true,
        preserveScroll: true,
        only: ["employees"],
      });
    },
  },


  mounted(){
    if(!this.isPrivileged && this.filterForm.operating_unit){
      this.fetchOperatingUnitDepartments(this.filterForm.operating_unit);
    }
  },

};
</script>
