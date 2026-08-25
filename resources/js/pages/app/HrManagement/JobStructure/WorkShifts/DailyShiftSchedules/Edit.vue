<template>
  <JobStructureTabs :activeTab="activeTab" />
  <v-card>
    <v-card-text>
      <v-card-title class="text-h6 font-weight-medium">
        Edit Daily Shift Schedule
      </v-card-title>
      <v-divider class="my-4" style="border: 1px solid black"></v-divider>
      <v-form @submit.prevent="handleSubmit()">
        <v-row>
          <v-col cols="12" md="3">
            <v-select
              label="Day of the Week"
              variant="outlined"
              density="compact"
              rounded="lg"
              :items="daysOfTheWeek"
              v-model="form.day_of_week"
              :error-messages="
                v$.form.day_of_week.$errors.map((e) => e.$message)
              "
            ></v-select>
          </v-col>
        </v-row>
        <v-row>
          <v-col cols="12" md="3">
            <v-text-field
              label="Check In"
              variant="outlined"
              density="compact"
              rounded="lg"
              type="time"
              v-model="form.time_in"
              :error-messages="v$.form.time_in.$errors.map((e) => e.$message)"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              label="Break Out"
              variant="outlined"
              density="compact"
              rounded="lg"
              type="time"
              v-model="form.break_start"
              :error-messages="
                v$.form.break_start.$errors.map((e) => e.$message)
              "
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              label="Break In"
              variant="outlined"
              density="compact"
              rounded="lg"
              type="time"
              v-model="form.break_end"
              :error-messages="v$.form.break_end.$errors.map((e) => e.$message)"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              label="Check Out"
              variant="outlined"
              density="compact"
              rounded="lg"
              type="time"
              v-model="form.time_out"
              :error-messages="v$.form.time_out.$errors.map((e) => e.$message)"
            ></v-text-field>
          </v-col>
        </v-row>
        <v-row>
          <v-col cols="12">
            <v-textarea
              label="Remarks"
              variant="outlined"
              density="compact"
              rounded="lg"
              v-model="form.remarks"
              :error-messages="v$.form.remarks.$errors.map((e) => e.$message)"
            ></v-textarea>
          </v-col>
        </v-row>
        <v-divider class="my-4" style="border: 1px solid black"></v-divider>
        <div class="d-flex justify-end">
          <ButtonMuted @click="goToIndex()" name="Cancel" />
          <ButtonSuccess class="ml-2" type="submit" name="Save" />
        </div>
      </v-form>
    </v-card-text>
  </v-card>
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import JobStructureTabs from "@/components/JobStructureTabs.vue";
import ButtonSuccess from "@/components/ButtonSuccess.vue";
import ButtonMuted from "@/components/ButtonMuted.vue";
import { daysOfTheWeek } from "@/utils/days";
import { useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import { required, minLength } from "@vuelidate/validators";
import AgainDialog from "@/components/AgainDialog.vue";

export default {
  layout: SidebarLayout,
  components: {
    JobStructureTabs,
    ButtonSuccess,
    ButtonMuted,
    AgainDialog,
  },
  props: {
    errors: Object,
    dailyShiftSchedule: Object,
  },
  data() {
    return {
      activeTab: "workShift",
      daysOfTheWeek,
      v$: useVuelidate(),
      form: useForm({
        day_of_week: this.dailyShiftSchedule.day_of_week,
        time_in: this.dailyShiftSchedule.time_in,
        time_out: this.dailyShiftSchedule.time_out,
        break_start: this.dailyShiftSchedule.break_start,
        break_end: this.dailyShiftSchedule.break_end,
        remarks: this.dailyShiftSchedule.remarks,
      }),
    };
  },
  validations() {
    return {
      form: {
        day_of_week: { required },
        time_in: { required },
        time_out: { required },
        break_start: { required },
        break_end: { required },
        remarks: { minLength: minLength(0) },
      },
    };
  },
  methods: {
    goToIndex() {
      this.$inertia.visit(
        route("hrmanagement.jobstructure.dailyShiftSchedules.index")
      );
    },

    handleSubmit() {
      this.v$.$validate();
      if (!this.v$.$invalid) {
        this.form.put(
          route("hrmanagement.jobstructure.dailyShiftSchedules.update", {
            id: this.dailyShiftSchedule.id,
          }),
          {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
              this.showToast(
                "Daily shift schedule updated successfully",
                "success"
              );
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
            },
          }
        );
      }
    },
  },
};
</script>
