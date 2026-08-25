<template>
  <div>
    <v-card class="elevation-2">
      <v-card-title class="text-h6">
        Monthly Employment Trends
      </v-card-title>
      <v-card-text>
        <div v-if="!monthlyTrends || monthlyTrends.length === 0" class="text-center pa-4">
          <v-icon size="48" color="grey">mdi-chart-line</v-icon>
          <p class="text-grey mt-2">No trend data available</p>
        </div>
        <canvas v-else ref="lineChart" width="400" height="200"></canvas>
      </v-card-text>
    </v-card>
  </div>
</template>

<script>
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);

export default {
  name: 'EmploymentTrendChart',
  props: {
    monthlyTrends: {
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
    monthlyTrends: {
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
      if (!this.monthlyTrends || this.monthlyTrends.length === 0) {
        return;
      }

      try {
        const ctx = this.$refs.lineChart?.getContext('2d');
        if (!ctx) return;
        
        this.chart = new Chart(ctx, {
          type: 'line',
          data: {
            labels: this.monthlyTrends.map(item => item.month),
            datasets: [{
              label: 'New Hires',
              data: this.monthlyTrends.map(item => item.count),
              borderColor: '#2196F3',
              backgroundColor: 'rgba(33, 150, 243, 0.1)',
              borderWidth: 3,
              fill: true,
              tension: 0.4,
              pointBackgroundColor: '#2196F3',
              pointBorderColor: '#fff',
              pointBorderWidth: 2,
              pointRadius: 6,
              pointHoverRadius: 8
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: {
                display: true,
                position: 'top'
              },
              tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                  label: (context) => {
                    return `New Hires: ${context.parsed.y}`;
                  }
                }
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  stepSize: 1
                },
                title: {
                  display: true,
                  text: 'Number of Employees'
                }
              },
              x: {
                title: {
                  display: true,
                  text: 'Month'
                }
              }
            },
            interaction: {
              mode: 'nearest',
              axis: 'x',
              intersect: false
            }
          }
        });
      } catch (error) {
        console.error('Error creating trend chart:', error);
      }
    },
    updateChart() {
      if (this.chart && this.monthlyTrends && this.monthlyTrends.length > 0) {
        try {
          this.chart.data.labels = this.monthlyTrends.map(item => item.month);
          this.chart.data.datasets[0].data = this.monthlyTrends.map(item => item.count);
          this.chart.update();
        } catch (error) {
          console.error('Error updating trend chart:', error);
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