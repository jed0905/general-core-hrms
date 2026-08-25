<template>
  <canvas ref="chart"></canvas>
</template>

<script>
import { Chart, ArcElement, Tooltip, Legend } from "chart.js";

Chart.register(ArcElement, Tooltip, Legend);

export default {
  props: {
    labels: Array,
    values: Array,
  },
  mounted() {
    new Chart(this.$refs.chart, {
      type: "pie",
      data: {
        labels: this.labels,
        datasets: [
          {
            data: this.values,
            backgroundColor: ["#4CAF50", "#2196F3"],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
      },
    });
  },
};
</script>

<style scoped>
canvas {
  max-height: 240px;
}
</style>
