<template>
  <div>
    <v-card class="elevation-2">
      <v-card-title class="text-h6">
        Employment Rate by Status
      </v-card-title>
      <v-card-text>
        <div v-if="!employmentData || employmentData.length === 0" class="text-center pa-4">
          <v-icon size="48" color="grey">mdi-chart-donut</v-icon>
          <p class="text-grey mt-2">No employment data available</p>
        </div>
        <canvas v-else ref="pieChart" width="400" height="200"></canvas>
      </v-card-text>
    </v-card>
  </div>
</template>

<script>
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);

export default {
  name: 'EmploymentRateChart',
  props: {
    employmentData: {
      type: Array,
      required: true,
      default: () => []
    }
  },
  data() {
    return {
      chart: null
    };
  },
  mounted() {
    this.$nextTick(() => {
      this.createChart();
    });
  },
  watch: {
    employmentData: {
      handler(newData) {
        if (newData && newData.length > 0) {
          this.$nextTick(() => {
            this.updateChart();
          });
        }
      },
      deep: true,
      immediate: true
    }
  },
  methods: {
    createChart() {
      if (!this.employmentData || this.employmentData.length === 0) {
        return;
      }

      try {
        const ctx = this.$refs.pieChart?.getContext('2d');
        if (!ctx) return;
        
        const colors = [
          '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
          '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
        ];

        this.chart = new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: this.employmentData.map(item => item.status),
            datasets: [{
              data: this.employmentData.map(item => item.count),
              backgroundColor: colors.slice(0, this.employmentData.length),
              borderWidth: 2,
              borderColor: '#fff'
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                position: 'bottom',
                labels: {
                  padding: 20,
                  usePointStyle: true
                }
              },
              tooltip: {
                callbacks: {
                  label: (context) => {
                    const data = this.employmentData[context.dataIndex];
                    return `${data.status}: ${data.count} (${data.percentage}%)`;
                  }
                }
              }
            }
          }
        });
      } catch (error) {
        console.error('Error creating chart:', error);
      }
    },
    updateChart() {
      if (this.chart && this.employmentData && this.employmentData.length > 0) {
        try {
          this.chart.data.labels = this.employmentData.map(item => item.status);
          this.chart.data.datasets[0].data = this.employmentData.map(item => item.count);
          this.chart.update();
        } catch (error) {
          console.error('Error updating chart:', error);
        }
      }
    }
  },
  beforeUnmount() {
    if (this.chart) {
      this.chart.destroy();
    }
  }
};
</script>

<style scoped>
canvas {
  max-height: 300px;
}
</style> 