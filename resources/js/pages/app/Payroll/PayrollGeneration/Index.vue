<template>
  <FilterWrapper v-model="isPanelOpen">
    <v-form>
      <v-row>
        <v-col cols="12" md="4">
          <v-select
            label="Employee Status"
            rounded="lg"
            variant="outlined"
            density="compact"
            :items="job_status"
            item-title="name"
            item-value="id"
          ></v-select>
        </v-col>
      </v-row>
    </v-form>
  </FilterWrapper>
  <TableWrapper>
    <div class="d-flex align-center justify-end">
      <Link :href="route('payroll.generation.calendar.index')" >
        <v-btn
          min-width="120"
          rounded="xl"
          prepend-icon="mdi-plus"
          color="starbucks-green"
        >Add</v-btn>
      </Link>
    </div>
  </TableWrapper>
  <pre>{{ payrollCalendars }}</pre>
</template>
<script>
  import SidebarLayout from '@/layouts/SidebarLayout.vue'
  import TableWrapper from '@/components/TableWrapper.vue'
  import FilterWrapper from '@/components/FilterWrapper.vue'
  import ButtonSuccess from '@/components/ButtonSuccess.vue'
  import ButtonMuted from '@/components/ButtonMuted.vue'
  
  export default {
    layout:SidebarLayout,
    components:{
      TableWrapper,
      FilterWrapper,
      ButtonSuccess,
      ButtonMuted,
    },
    props:{
      errors: Object,
      payrollCalendars: Object,
      job_status: Object,
    },
    data(){
      return {
        isPanelOpen: [0],
      }
    }
  }
</script>