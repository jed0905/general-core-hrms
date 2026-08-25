<template>
  <v-card class="elevation-2 fill-height">
    <v-card-title class="text-h6">
      Employee Job Satisfaction Survey
    </v-card-title>
    <v-card-text>
      <canvas ref="jobSatisfactionChart" width="400" height="200"></canvas>
    </v-card-text>
  </v-card>
</template>

<script>
import Chart from 'chart.js/auto';

export default {
  name: 'JobSatisfactionChart',
  data() {
    return {
      chart: null,
      satisfactionData: {
        labels: ['Very Satisfied', 'Satisfied', 'Neutral', 'Dissatisfied', 'Very Dissatisfied'],
        datasets: [{
          label: 'Number of Employees',
          data: [45, 78, 32, 15, 8],
          backgroundColor: [
            '#4CAF50', // Green for very satisfied
            '#8BC34A', // Light green for satisfied
            '#FFC107', // Yellow for neutral
            '#FF9800', // Orange for dissatisfied
            '#F44336'  // Red for very dissatisfied
          ],
          borderColor: [
            '#388E3C',
            '#689F38',
            '#FFA000',
            '#F57C00',
            '#D32F2F'
          ],
          borderWidth: 2,
          borderRadius: 8,
          borderSkipped: false,
        }]
      }
    };
  },
  mounted() {
    this.createChart();
  },
  beforeUnmount() {
    if (this.chart) {
      this.chart.destroy();
    }
  },
  methods: {
    createChart() {
      const ctx = this.$refs.jobSatisfactionChart.getContext('2d');
      
      this.chart = new Chart(ctx, {
        type: 'bar',
        data: this.satisfactionData,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const percentage = ((context.parsed.y / total) * 100).toFixed(1);
                  return `${context.parsed.y} employees (${percentage}%)`;
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              title: {
                display: true,
                text: 'Number of Employees'
              },
              ticks: {
                stepSize: 20
              }
            },
            x: {
              title: {
                display: true,
                text: 'Satisfaction Level'
              }
            }
          }
        }
      });
    }
  }
};
</script>

<style scoped>
canvas {
  max-height: 300px;
}
</style>
