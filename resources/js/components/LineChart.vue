<template>
  <div style="height: 300px; width: 100%">
    <Line :data="chartData" :options="chartOptions" />
  </div>
</template>

<script>
import { Line } from "vue-chartjs";
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
} from "chart.js";

import ChartDataLabels from "chartjs-plugin-datalabels";

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  LineElement,
  PointElement,
  CategoryScale,
  LinearScale,
  ChartDataLabels
);

export default {
  name: "LineChart",
  components: { Line },

  props: {
    labels: {
      type: Array,
      required: true, // e.g., [2022, 2023, 2024, 2025, 2026]
    },
    datasets: {
      type: Array,
      required: true,
      /* Example:
      [
        { label: 'CA', data: [12,14,10,15,18], borderColor: '#1976D2', fill: false },
        { label: 'MLUC', data: [8,10,12,9,11], borderColor: '#26A69A', fill: false },
      ]
      */
    },
  },

  computed: {
    chartData() {
      return {
        labels: this.labels,
        datasets: this.datasets.map((ds) => ({
          ...ds,
          tension: 0.3, // smooth curve
          pointRadius: 5,
          pointHoverRadius: 7,
        })),
      };
    },

    chartOptions() {
      return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: "right",
            align: "center",
            labels: {
              boxWidth: 20,
              padding: 10,
              font: { size: 12, weight: "bold" },
            },
          },
          datalabels: {
            display: true,
            color: "#000",
            font: { weight: "bold" },
            formatter: (value) => (value > 0 ? value : ""),
          },
          tooltip: {
            mode: "index",
            intersect: false,
          },
        },
        scales: {
          x: {
            title: { display: true, text: "Year", font: { weight: "bold" } },
          },
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: "Total Hired",
              font: { weight: "bold" },
            },
            ticks: { precision: 0 },
          },
        },
      };
    },
  },
};
</script>
