<template>
  <LeaveManagementTabs :activeMenuTitle="activeMenuTitle" />
  <v-row>
    <v-col cols="12">
      <v-card rounded="lg">
        <v-card-text>
          <div class="v-card-title">
            Add Holiday
          </div>
          <v-divider
            class="my-4"
            style="border: 1px solid black;"
          ></v-divider>
          <v-form>
            <v-row>
              <v-col cols="12" lg="4" md="4" sm="12" xs="12">
                <v-text-field
                  label="Name"
                  variant="outlined"
                  rounded="lg"
                  density="compact"
                  v-model="form.name"
                  @blur="cleanHolidayName"
                  :error-messages="v$.form.name.$errors.map(e => e.$message)"
                ></v-text-field>
              </v-col>
              <v-col cols="12" lg="4" md="4" sm="12" xs="12">
                <v-text-field
                  label="Date"
                  variant="outlined"
                  rounded="lg"
                  density="compact"
                  type="date"
                  v-model="form.date"
                  @change="validateAndCleanDate"
                  :error-messages="v$.form.date.$errors.map(e => e.$message)"
                ></v-text-field>
              </v-col>
              <v-col cols="12" lg="4" md="4" sm="12" xs="12">
                <v-select
                  label="Type of Day"
                  variant="outlined"
                  rounded="lg"
                  density="compact"
                  :items="typeOfDay"
                  item-title="value"
                  item-value="title"
                  v-model="form.length"
                  :error-messages="v$.form.length.$errors.map(e => e.$message)"
                >
                </v-select>
              </v-col>
              <v-col cols="12" lg="4" md="4" sm="12" xs="12">
                <v-autocomplete
                  label="Operating Unit"
                  variant="outlined"
                  rounded="lg"
                  density="compact"
                  :items="operatingUnits"
                  item-title="name"
                  item-value="id"
                  v-model="form.operating_unit_ids"
                  :error-messages="v$.form.operating_unit_ids.$errors.map(e => e.$message)"
                  multiple
                  chips
                  closable-chips
                >
                </v-autocomplete>
              </v-col>
              <v-col cols="12" lg="4" md="4" sm="12" xs="12">
                <v-radio-group
                  label="Repeats Annually"
                  variant="outlined"
                  rounded="lg"
                  density="compact"
                  color="starbucks-green"
                  inline
                  v-model="form.recurring"
                  :error-messages="v$.form.recurring.$errors.map(e => e.$message)"
                >
                  <v-radio
                    label="Yes"
                    value="Yes"
                    class="mr-5"
                    v-model="form.recurring"
                  ></v-radio>
                  <v-radio
                    label="No"
                    value="No"
                    v-model="form.recurring"
                  ></v-radio>
                </v-radio-group>
              </v-col>
            </v-row>
             <v-divider
            class="my-4"
            style="border: 1px solid black;"
          ></v-divider>
          <v-row>
            <v-col cols="12">
              <div
                class="d-flex align-center justify-end"
              >
                <ButtonMuted class="mr-4" name="Cancel"></ButtonMuted>
                <ButtonSuccess name="Save" @click="saveHoliday"></ButtonSuccess>
              </div>
            </v-col>
          </v-row>
          </v-form>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>
</template>
<script>

  import SidebarLayout from '@/layouts/SidebarLayout.vue'
  import LeaveManagementTabs from '@/components/LeaveManagementTabs.vue'
  import ButtonSuccess from '@/components/ButtonSuccess.vue'
  import ButtonMuted from '@/components/ButtonMuted.vue'
  import useVuelidate from '@vuelidate/core'
  import { required, minLength, helpers } from '@vuelidate/validators'
  import { useForm } from '@inertiajs/vue3'
  

  export default {
    layout: SidebarLayout,
    components: {
      LeaveManagementTabs,
      ButtonSuccess,
      ButtonMuted,
    },
    props: {
      activeMenuTitle: {
        type: String,
        default: 'Leave Management',
      },
      operatingUnits: Object,
      
    },
    data(){
      return { 
        typeOfDay: [{
          'title': 1,
          'value': 'Full Day',
        }, {
          'title': 2,
          'value': 'Half Day',
        }],
        selectedRepeatOption: 'Yes',
        isRadioDisabled: false,
        v$: useVuelidate(),
        form: useForm({
          name: '',
          date: '',
          recurring: '',
          length: '',
          operating_unit_ids: []
        }),
      }
    },
    validations: {

     

      form: {
        name: { 
          required: helpers.withMessage('Holiday name is required', required),
          validName: helpers.withMessage('Holiday name contains invalid characters', (value) => {
            if (!value) return true;
            return /^[a-zA-Z0-9\s\-'.]+$/.test(value);
          })
        },
        date: { 
          required: helpers.withMessage('Holiday date is required', required),
          validDate: helpers.withMessage('Please select a valid date', (value) => {
            if (!value) return true;
            const date = new Date(value);
            return date instanceof Date && !isNaN(date);
          })
        },
        recurring: { 
          required: helpers.withMessage('Please specify if the holiday repeats annually', required)
        },
        length: { 
          required: helpers.withMessage('Please specify the type of day', required)
        },
        operating_unit_ids: { 
          // Optional field, no validation needed
        },
      }
    },
    methods: {
      // Data cleaning method for holiday name
      cleanHolidayName() {
        if (this.form.name) {
          // Trim whitespace
          this.form.name = this.form.name.trim();
          
          // Convert to proper case (first letter of each word capitalized)
          this.form.name = this.form.name
            .toLowerCase()
            .split(' ')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
          
          // Remove extra spaces between words
          this.form.name = this.form.name.replace(/\s+/g, ' ');
          
          // Remove special characters except hyphens, apostrophes, and periods
          this.form.name = this.form.name.replace(/[^a-zA-Z0-9\s\-'.]/g, '');
        }
      },

      // Date validation and cleaning
      validateAndCleanDate() {
        if (this.form.date) {
          const selectedDate = new Date(this.form.date);
          const today = new Date();
          today.setHours(0, 0, 0, 0);
          
          // Validate date is not in the past (optional - remove if past dates are allowed)
          if (selectedDate < today) {
            // You can add a warning here if needed
            console.warn('Selected date is in the past');
          }
          
          // Ensure proper date format (YYYY-MM-DD)
          if (selectedDate instanceof Date && !isNaN(selectedDate)) {
            this.form.date = selectedDate.toISOString().split('T')[0];
          }
        }
      },

      // Convert recurring field to proper boolean format for backend
      cleanRecurringField() {
        if (this.form.recurring) {
          // Convert string values to boolean/integer for backend
          if (this.form.recurring === 'Yes' || this.form.recurring === true) {
            this.form.recurring = 1;
          } else if (this.form.recurring === 'No' || this.form.recurring === false) {
            this.form.recurring = 0;
          }
        }
      },

      // Validate and clean length field
      cleanLengthField() {
        if (this.form.length) {
          // Ensure length is a valid integer
          this.form.length = parseInt(this.form.length);
          
          // Validate length value (1 for full day, 2 for half day based on your options)
          if (![1, 2].includes(this.form.length)) {
            this.form.length = 1; // Default to full day
          }
        }
      },

      // Comprehensive form data cleaning before submission
      cleanFormData() {
        this.cleanHolidayName();
        this.validateAndCleanDate();
        this.cleanRecurringField();
        this.cleanLengthField();
        
        // Remove any null or undefined values
        Object.keys(this.form).forEach(key => {
          if (this.form[key] === null || this.form[key] === undefined) {
            this.form[key] = '';
          }
        });
      },

      // Validate form before submission
      async validateForm() {
        const result = await this.v$.$validate();
        if (!result) {
          // Get first error field and focus on it
          const firstErrorField = Object.keys(this.v$.form.$errors)[0];
          if (firstErrorField) {
            const element = document.querySelector(`[v-model*="${firstErrorField}"]`);
            if (element) {
              element.focus();
            }
          }
          return false;
        }
        return true;
      },

      async saveHoliday() {
        // Clean form data before validation and submission
        this.cleanFormData();
        
        // Validate form
        const isValid = await this.validateForm();
        if (!isValid) {
          return;
        }

        // Submit form with cleaned data
        this.form.post(route('hrmanagement.holiday.store'), {
          onSuccess: (response) => {
            // Handle success - maybe redirect or show success message
            // this.$inertia.visit(route('holidays.index'));
            this.showToast('Holiday created successfully', 'success');
          },
          onError: (errors) => {
            // Handle validation errors from backend
            for (const key in errors) {
              this.showToast(`${errors[key]}`, "error");
            }
          }
        });
      },
    }
  }
</script>