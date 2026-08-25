<template>
  <v-row dense>
    <v-col
      cols="6"
      sm="6"
      md="3"
      v-for="(card, index) in kpiCards"
      :key="index"
    >
      <v-card class="kpi-card pa-5" :class="card.colorClass" elevation="8">
        <v-row align="center" justify="space-between" no-gutters>
          <v-col>
            <div class="kpi-value">{{ card.value }}</div>
            <div class="kpi-title">{{ card.title }}</div>
          </v-col>

          <v-col cols="auto">
            <v-icon class="kpi-icon">
              {{ card.icon }}
            </v-icon>
          </v-col>
        </v-row>
      </v-card>
    </v-col>
  </v-row>

  <v-row dense>
    <v-col cols="12" md="3">
      <v-card
        elevation="5"
        class="pa-4 rounded-lg"
        style="height: 360px; display: flex; flex-direction: column"
      >
        <!-- Card Header -->
        <div class="d-flex justify-space-between mb-2">
          <span class="text-subtitle-1 font-weight-medium">
            Employees Per Operating Unit
          </span>
          <span class="text-caption text-grey">
            Total: {{ totalEmployees }}
          </span>
        </div>

        <!-- Donut Chart -->
        <div
          style="
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
          "
        >
          <DonutChart
            :labels="operatingUnitLabels"
            :values="operatingUnitValues"
          />
        </div>
      </v-card>
    </v-col>

    <v-col cols="12" md="3">
      <v-card
        elevation="5"
        class="pa-4 rounded-lg"
        style="height: 360px; display: flex; flex-direction: column"
      >
        <!-- Card Header -->
        <div class="d-flex justify-space-between align-center mb-3 ga-3">
          <span class="text-subtitle-1 font-weight-medium">
            Employees Educational Attainment
          </span>

          <v-select
            v-model="selectedOUEdu"
            :items="operatingUnits"
            item-title="title"
            item-value="value"
            placeholder="Select Operating Unit"
            variant="solo"
            density="compact"
            hide-details
            class="w-auto"
            style="min-width: 90px; max-width: 150px; font-size: 0.85rem"
          />
        </div>

        <!-- Chart -->
        <div
          style="
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
          "
        >
          <DonutChart :labels="degreeLabels" :values="degreeValues" />
        </div>
      </v-card>
    </v-col>

    <v-col cols="12" md="6">
      <v-card
        elevation="5"
        class="pa-4 rounded-lg"
        style="height: 360px; display: flex; flex-direction: column"
      >
        <!-- Card Header -->
        <div class="d-flex justify-space-between align-center mb-3">
          <span class="text-subtitle-1 font-weight-medium">
            Employees Status Per Operating Unit
          </span>

          <v-select
            v-model="selectedOU"
            :items="operatingUnits"
            item-title="title"
            item-value="value"
            placeholder="Select Operating Unit"
            variant="solo"
            density="compact"
            hide-details
            class="w-auto"
            style="min-width: 140px; max-width: 180px; font-size: 0.85rem"
          />
        </div>

        <!-- Chart -->
        <div
          style="
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
          "
        >
          <StackedBarChart
            :labels="filteredStats.map((i) => i.job_status)"
            :teachingValues="filteredStats.map((i) => Number(i.teaching))"
            :nonTeachingValues="
              filteredStats.map((i) => Number(i.non_teaching))
            "
          />
        </div>
      </v-card>
    </v-col>
  </v-row>

  <v-row dense>
    <v-col cols="12">
      <v-card
        elevation="5"
        class="pa-4 rounded-lg"
        style="height: 400px; display: flex; flex-direction: column"
      >
        <!-- Card Header -->
        <div class="d-flex justify-space-between align-center mb-3">
          <span class="text-subtitle-1 font-weight-medium">
            Employee Hiring Trends (Past 5 Years)
          </span>
        </div>

        <!-- Chart -->
        <div
          style="
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
          "
        >
          <LineChart
            :labels="hiringTrends.labels"
            :datasets="hiringTrends.datasets"
          />
        </div>
      </v-card>
    </v-col>
  </v-row>

  <v-row dense> </v-row>

  <!-- <pre>{{ selectedOU }}</pre> -->
</template>

<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import DonutChart from "@/components/DonutChart.vue";
import StackedBarChart from "@/components/StackedBarChart.vue";
import LineChart from "@/components/LineChart.vue";

export default {
  layout: SidebarLayout,

  components: {
    Breadcrumbs,
    DonutChart,
    StackedBarChart,
    LineChart,
  },

  props: {
    kpiData: {
      type: Object,
      required: true,
    },

    employeesPerOperatingUnit: {
      type: Array,
      required: true,
    },

    employeeStatsPerOu: {
      type: Array,
      required: true,
    },

    operatingUnits: {
      type: Array,
      required: true,
    },

    userOperatingUnit: {
      type: String,
      required: true,
    },

    hiringTrends: {
      type: Object,
      required: true,
    },

    degreesCount: {
      type: Array,
      required: true,
    },
  },

  computed: {
    operatingUnitLabels() {
      // convert collection to array
      return this.employeesPerOperatingUnit.map((item) => item.operating_unit);
    },
    operatingUnitValues() {
      return this.employeesPerOperatingUnit.map((item) => item.employee_count);
    },
    totalEmployees() {
      return this.operatingUnitValues.reduce((a, b) => a + b, 0);
    },

    // Employee Status Per Operating Unit Chart Data
    filteredData() {
      return this.employeeStatsPerOu.filter(
        (item) => item.operating_unit === "Mid La Union Campus"
      );
    },

    jobStatusLabels() {
      return this.filteredData.map((i) => i.job_status);
    },

    teachingValues() {
      return this.filteredData.map((i) => Number(i.teaching));
    },

    nonTeachingValues() {
      return this.filteredData.map((i) => Number(i.non_teaching));
    },

    filteredStats() {
      return this.employeeStatsPerOu.filter(
        (i) => i.operating_unit === this.selectedOU
      );
    },

    // Educational Attainment Chart Data
    filteredDegrees() {
      return this.degreesCount.filter(
        (d) => d.operating_unit === this.selectedOUEdu
      );
    },

    degreeLabels() {
      return this.filteredDegrees.map((d) => d.degree_type);
    },

    degreeValues() {
      return this.filteredDegrees.map((d) => Number(d.total));
    },
  },

  data() {
    return {
      kpiCards: [
        {
          title: "Operating Units",
          value: this.kpiData.totalOperatingUnits,
          icon: "mdi-domain",
          colorClass: "kpi-green",
        },
        {
          title: "Departments",
          value: this.kpiData.totalDepartments,
          icon: "mdi-folder-multiple",
          colorClass: "kpi-blue",
        },
        {
          title: "Active Employees",
          value: this.kpiData.totalActiveEmployees,
          icon: "mdi-account-group",
          colorClass: "kpi-orange",
        },
        {
          title: "Active Users",
          value: this.kpiData.totalActiveUsers,
          icon: "mdi-account-check",
          colorClass: "kpi-purple",
        },
      ],

      selectedOU: this.userOperatingUnit,
      selectedOUEdu: this.userOperatingUnit,
    };
  },
};
</script>

<style scoped>
.hover-elevation-12 {
  transition: box-shadow 0.3s ease;
}
.hover-elevation-12:hover {
  box-shadow: 0px 12px 20px rgba(0, 0, 0, 0.12);
}

.kpi-card {
  border-radius: 16px;
  color: #fff;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
  overflow: hidden;
}

.kpi-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 18px 30px rgba(0, 0, 0, 0.25);
}

/* Text */
.kpi-value {
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.2;
}

.kpi-title {
  font-size: 0.95rem;
  opacity: 0.9;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* MOBILE tuning */
@media (max-width: 500px) {
  .kpi-title {
    font-size: 0.8rem;
  }
}

/* Icon */
.kpi-icon {
  font-size: 56px; /* BIG icon */
  opacity: 0.25;
}

/* Color themes (gradient = not flat) */
.kpi-green {
  background: linear-gradient(135deg, #43a047, #66bb6a);
}

.kpi-blue {
  background: linear-gradient(135deg, #1e88e5, #42a5f5);
}

.kpi-orange {
  background: linear-gradient(135deg, #fb8c00, #ffb74d);
}

.kpi-purple {
  background: linear-gradient(135deg, #8e24aa, #ba68c8);
}
</style>
