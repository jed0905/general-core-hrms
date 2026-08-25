<template>
  <MyLeavesTabs v-model:activeTab="activeTab" />
  <v-row>
    <v-col cols="12">
      <v-card>
        <v-card-text>
          
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>
</template>
<script>

  import SidebarLayout from '@/layouts/SidebarLayout.vue'
  import MyLeavesTabs from '@/components/MyLeavesTabs.vue'
  export default {
    layout: SidebarLayout,
     
    components:{
      MyLeavesTabs,
      
    },
    data(){
      return {
        activeTab: 'index',

      }
    }
  }
</script>