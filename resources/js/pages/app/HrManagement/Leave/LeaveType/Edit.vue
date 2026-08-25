<template>
  <HrmMenu />
  <v-row>
    <v-col cols="12">
      <v-card>
        <v-card-text>
          <p class="text-h6 font-weight-bold">Edit Leave Type</p>
          <v-form>
            <v-row>
              <v-divider class="mt-3 mb-3"></v-divider>
              <v-col cols="12">
                <v-text-field 
                  label="Leave Type"
                  variant="outlined"
                  density="compact"
                ></v-text-field>
              </v-col>
              <v-col cols="12">
                <p>Is Entitlement Situational? <v-icon size="small" color="primary" @click="dialog = true">mdi-information</v-icon></p>
                <v-radio-group inline>
                  <v-radio class="mr-5" label="Yes" value="yes" />
                  <v-radio label="No" value="no" />
                </v-radio-group>
              </v-col>
              <v-divider class="my-3"></v-divider>
              <v-col cols="12">
                <div class="d-flex justify-end align-center">
                  <ButtonMuted class="mr-3" name="Cancel" />
                  <ButtonSuccess name="Save" />
                </div>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>
  
  <v-dialog v-model="dialog" max-width="500" persistent>
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <p class="text-h6 font-weight-bold">Situational Leave</p>
        <v-btn size="x-small" class="pa-2" icon @click="dialog = false">
          <v-icon >mdi-close</v-icon>
        </v-btn>
      </v-card-title>
      <v-card-text>
        <p>
          These leave will be excluded from reports unless there's some activity. E.g. maternity leave, jury duty leave.
        </p>
      </v-card-text>
      <v-card-actions class="d-flex align-center justify-center">
        <ButtonSuccess variant="outlined" @click="dialog = false" name="Ok" />
      </v-card-actions> 
    </v-card>
  </v-dialog>

</template>

<script>
  import SidebarLayout from '@/layouts/SidebarLayout.vue';
  import HrmMenu from '@/components/Menu/HrmMenu.vue';
  import ButtonSuccess from '@/components/ButtonSuccess.vue';
  import ButtonMuted from '@/components/ButtonMuted.vue';

  export default {
    layout: SidebarLayout,
    components:{
      HrmMenu,
      ButtonSuccess,
      ButtonMuted,
    },
    data(){
      return{
        dialog: false,
      }
    }
    
  }
</script>