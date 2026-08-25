<template>
  <HrmMenu />
  <v-row>
    <v-col cols="12">
      <v-card>
        <v-card-text>
          <p class="text-h6 font-weight-bold">Add Leave Type</p>
          <v-form @submit.prevent="submitLeaveType()">
            <v-row>
              <v-divider class="my-3" style="border: 1px solid black;"></v-divider>
              <v-col cols="12">
                <v-text-field 
                  label="Leave Type"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  v-model="submitForm.name"
                  @input="cleanLeaveTypeName"
                  @blur="finalCleanLeaveTypeName"
                  :error-messages="v$.submitForm.name.$errors.map((e) => e.$message)"
                ></v-text-field>
              </v-col>
              <v-col cols="12">
                <v-text-field 
                  label="Shortcut"
                  variant="outlined"
                  density="compact"
                  rounded="lg"
                  v-model="submitForm.shortcut"
                  @input="cleanShortcut"
                  @blur="finalCleanShortcut"
                  :error-messages="v$.submitForm.shortcut.$errors.map((e) => e.$message)"
                ></v-text-field>
              </v-col>
              <!-- <v-col cols="12">
                <p>Is Entitlement Situational? <v-icon size="small" color="primary" @click="dialog = true">mdi-information</v-icon></p>
                <v-radio-group inline>
                  <v-radio class="mr-5" label="Yes" value="yes" />
                  <v-radio label="No" value="no" />
                </v-radio-group>
              </v-col> -->
              <v-divider class="my-3" style="border: 1px solid black;"></v-divider>
              <v-col cols="12">
                <div class="d-flex justify-end align-center">
                  <ButtonMuted :href="route('hrmanagement.leave.leaveType.index')" class="mr-3" name="Cancel" />
                  <ButtonSuccess type="submit" name="Save" />
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
        <ButtonSuccess  @click="dialog = false" name="Ok" />
      </v-card-actions> 
    </v-card>
  </v-dialog>

</template>

<script>
  import SidebarLayout from '@/layouts/SidebarLayout.vue';
  import HrmMenu from '@/components/Menu/HrmMenu.vue';
  import ButtonSuccess from '@/components/ButtonSuccess.vue';
  import ButtonMuted from '@/components/ButtonMuted.vue';
  import useVuelidate from '@vuelidate/core';
  import { required, minLength, maxLength, helpers } from '@vuelidate/validators';
  import { useForm } from '@inertiajs/vue3';
  
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
        v$: useVuelidate(),
        submitForm: useForm({
          name: '',
          shortcut: '',
          // is_entitlement_situational: false,
        }),
      }
    },
    validations(){
      // Custom validators
      const nameFormat = helpers.withMessage(
        'Name can only contain letters, spaces, and hyphens',
        (value) => !value || /^[a-zA-Z\s\-]+$/.test(value)
      );
      
      const shortcutFormat = helpers.withMessage(
        'Shortcut can only contain uppercase letters and numbers',
        (value) => !value || /^[A-Z0-9]+$/.test(value)
      );
      
      const noMultipleSpaces = helpers.withMessage(
        'Multiple consecutive spaces are not allowed',
        (value) => !value || !/\s{2,}/.test(value)
      );
      
      return{
        submitForm: {
          name: { 
            required: helpers.withMessage('Leave type name is required', required),
            minLength: helpers.withMessage('Name must be at least 2 characters', minLength(2)),
            maxLength: helpers.withMessage('Name cannot exceed 255 characters', maxLength(255)),
            nameFormat,
            noMultipleSpaces
          },
          shortcut: { 
            required: helpers.withMessage('Shortcut is required', required),
            minLength: helpers.withMessage('Shortcut must be at least 2 characters', minLength(2)),
            maxLength: helpers.withMessage('Shortcut cannot exceed 10 characters', maxLength(10)),
            shortcutFormat
          },
        }
      }
    },
    methods:{
      // Data cleansing methods
      cleanLeaveTypeName(event) {
        // Real-time cleaning: remove multiple spaces, trim leading/trailing spaces
        let value = event.target.value;
        value = value.replace(/\s+/g, ' '); // Replace multiple spaces with single space
        value = value.replace(/[^\w\s-]/g, ''); // Allow only alphanumeric, spaces, and hyphens
        this.submitForm.name = value;
      },
      
      finalCleanLeaveTypeName() {
        // Final cleanup on blur: proper case formatting
        let value = this.submitForm.name.trim();
        // Convert to title case (capitalize first letter of each word)
        value = value.replace(/\w\S*/g, (txt) => 
          txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()
        );
        this.submitForm.name = value;
      },
      
      cleanShortcut(event) {
        // Real-time cleaning for shortcut: uppercase, remove spaces and special chars
        let value = event.target.value;
        value = value.replace(/[^A-Za-z0-9]/g, ''); // Only alphanumeric
        value = value.toUpperCase(); // Convert to uppercase
        value = value.substring(0, 10); // Limit to 10 characters
        this.submitForm.shortcut = value;
      },
      
      finalCleanShortcut() {
        // Final cleanup on blur
        let value = this.submitForm.shortcut.trim();
        this.submitForm.shortcut = value;
      },
      
      submitLeaveType(){
        // Final validation and cleaning before submission
        this.finalCleanLeaveTypeName();
        this.finalCleanShortcut();
        
        this.v$.submitForm.$validate();
        if(!this.v$.submitForm.$invalid){
          this.submitForm.post(route('hrmanagement.leave.leaveType.store'),{
            onSuccess: () => {
              this.showToast('Leave type created successfully', 'success');
              this.submitForm.reset();
            }
          });
        }
      }
    }
  }
</script>