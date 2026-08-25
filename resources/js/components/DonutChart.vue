<template>
  <div style="height: 260px; position: relative">
    <template v-if="hasData">
      <Doughnut :data="chartData" :options="chartOptions" />
    </template>

    <template v-else>
      <div class="no-data">
        No Data
      </div>
    </template>
  </div>
</template>

<script>
import { Doughnut } from "vue-chartjs";
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from "chart.js";
import ChartDataLabels from "chartjs-plugin-datalabels";

ChartJS.register(ArcElement, Tooltip, Legend, ChartDataLabels);

export default {
  name: "DonutChart",

  components: {
    Doughnut,
  },

  props: {
    labels: {
      type: Array,
      required: true,
    },
    values: {
      type: Array,
      required: true,
    },
  },

  computed: {
    hasData() {
      return this.values.some(v => Number(v) > 0);
    },

    chartData() {
      return {
        labels: this.labels,
        datasets: [
          {
            data: this.values,
            backgroundColor: [
              "#1976D2",
              "#26A69A",
              "#FFB300",
              "#E53935",
              "#8E24AA",
              "#FF7043",
            ],
            borderWidth: 0,
          },
        ],
      };
    },

    chartOptions() {
      return {
        responsive: true,
        maintainAspectRatio: false,
        cutout: "65%",
        plugins: {
          legend: {
            position: "right",
          },
          datalabels: {
            color: "white",
            font: {
              weight: "bold",
              size: 14,
            },
            formatter: (value) => value,
          },
        },
      };
    },
  },
};
</script>

<style scoped>
.no-data {
  height: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  font-weight: 600;
  color: #9e9e9e;
  font-size: 1rem;
}
</style>
