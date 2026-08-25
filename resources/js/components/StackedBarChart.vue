<template>
  <div style="height: 260px">
    <Bar :data="chartData" :options="chartOptions" />
  </div>
</template>

<script>
import { Bar } from "vue-chartjs";
import {
  Chart as ChartJS,
  BarElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Legend,
} from "chart.js";

import ChartDataLabels from "chartjs-plugin-datalabels";

ChartJS.register(
  BarElement,
  CategoryScale,
  LinearScale,
  Tooltip,
  Legend,
  ChartDataLabels
);

export default {
  name: "StackedBarChart",

  components: {
    Bar,
  },

  props: {
    labels: {
      type: Array,
      required: true, // Job statuses
    },
    teachingValues: {
      type: Array,
      required: true,
    },
    nonTeachingValues: {
      type: Array,
      required: true,
    },
  },

  computed: {
    chartData() {
      return {
        labels: this.labels,
        datasets: [
          {
            label: "Teaching",
            data: this.teachingValues,
            backgroundColor: "#1976D2",
            borderRadius: 6,
            datalabels: {
              color: "#fff",
            },
          },
          {
            label: "Non-Teaching",
            data: this.nonTeachingValues,
            backgroundColor: "#26A69A",
            borderRadius: 6,
            datalabels: {
              color: "#fff",
            },
          },
        ],
      };
    },

    chartOptions() {
      return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "bottom",
          },

          datalabels: {
            formatter: (value) => (value > 0 ? value : ""),
            font: {
              weight: "bold",
              size: 12,
            },
          },

          tooltip: {
            mode: "index",
            intersect: false,
          },
        },

        scales: {
          x: {
            stacked: true,
            grid: {
              display: false,
            },
          },
          y: {
            stacked: true,
            beginAtZero: true,
            ticks: {
              precision: 0,
            },
          },
        },
      };
    },
  },
};
</script>
