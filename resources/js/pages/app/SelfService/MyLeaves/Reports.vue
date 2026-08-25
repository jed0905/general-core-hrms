<template>
  <DynamicTabs 
  :tabs="tabs" 
  :activeTab="activeTab"
  />

  <FilterWrapper v-model="isPanelOpen">
    <v-form>
      <v-row>
        <p class="ml-3">My Leave Entitlements and Usage Report</p>
        <v-divider class="mt-2 mb-2"></v-divider>
        <v-col cols="12" md="6">
          <v-select
            label="Leave Period"
            density="compact"
            variant="outlined"
            hide-details
          ></v-select>
        </v-col>
        <v-divider class="mt-2 mb-2"></v-divider>
        <v-col cols="12">
          <div class="d-flex align-center justify-end">
            <ButtonMuted name="Reset" class="mr-2" @click="resetForm()" />
            <ButtonSuccess name="Search"  />
          </div>
        </v-col>
      </v-row>
    </v-form>
  </FilterWrapper>

  <TableWrapper :class="{ 'fullscreen-table': isFullscreen }">
    <v-row>
      <v-col cols="12" class="d-flex align-center justify-space-between">
        <p class="text-h6">
          <v-icon 
            @click="toggleFullscreen" 
            class="cursor-pointer"
            :class="{ 'text-primary': isFullscreen }"
          >
            {{ isFullscreen ? 'mdi-arrow-collapse-all' : 'mdi-arrow-expand-all' }}
          </v-icon>
        </p>
        <p class="text-h6">(2) Records Found</p>
      </v-col>
    </v-row>
    <v-table>
      <thead>
        <tr>
          <th>Leave Type</th>
          <th>Leave Entitlements (Days)</th>
          <th>Leave Pending Approval (Days)</th>
          <th>Leave Scheduled (Days)</th>
          <th>Leave Taken (Days)</th>
          <th>Leave Balance (Days)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Sick Leave</td>
          <td>0.00</td>
          <td>0.00</td>
          <td>0.00</td>
          <td>0.00</td>
          <td>0.00</td>
        </tr>
        <tr>
          <td>Vacation Leave</td>
          <td>15.00</td>
          <td>0.00</td>
          <td>0.00</td>
          <td>0.00</td>
          <td>0.00</td>
        </tr>
      </tbody>
    </v-table>
  </TableWrapper>

</template>
<script>
  import SidebarLayout from '@/layouts/SidebarLayout.vue'
  import TableWrapper from '@/components/TableWrapper.vue'
  import DynamicTabs from '@/components/DynamicTabs.vue'
  import FilterWrapper from '@/components/FilterWrapper.vue'
  import ButtonMuted from '@/components/ButtonMuted.vue'
  import ButtonSuccess from '@/components/ButtonSuccess.vue'
export default {
  layout: SidebarLayout,
  components: {
    TableWrapper,
    DynamicTabs,
    FilterWrapper,
    ButtonMuted,
    ButtonSuccess
  },
  data(){
    return {
      activeTab: 'reports',
      tabs: [
        {
          key: 'apply',
          label: 'Apply',
          route: 'self-service.my-leaves.apply' 
        },
        {
          key: 'list',
          label: 'My Leave',
          route: 'self-service.my-leaves.index'
        },
        {
          key: 'entitlements',
          label: 'Entitlements',
          route: 'self-service.my-entitlement.index'
        },
        {
          key: 'reports',
          label: 'Reports',
          route: 'self-service.my-reports.index'
        }
      ],
      isPanelOpen: 0,
      isFullscreen: false,
    }
    
  },
  methods: {
    resetForm(){
      this.form.reset()
    },
    toggleFullscreen() {
      this.isFullscreen = !this.isFullscreen;
      if (this.isFullscreen) {
        document.body.style.overflow = 'hidden';
      } else {
        document.body.style.overflow = '';
      }
    }
  }
}
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}

.fullscreen-table {
  position: fixed !important;
  top: 0 !important;
  left: 0 !important;
  width: 100vw !important;
  height: 100vh !important;
  z-index: 9999 !important;
  background: white !important;
  padding: 20px !important;
  overflow: auto !important;
}

.fullscreen-table .v-row {
  margin: 0 !important;
}

.fullscreen-table .v-col {
  padding: 8px !important;
}
</style>