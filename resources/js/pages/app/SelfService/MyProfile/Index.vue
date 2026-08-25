<!--
 This page allows user to update their PDS
-->
<template>
  <v-row>
    <v-col cols="12">
      <v-card class="pa-1 ma-2" rounded="lg">
        <v-card-text class="mt-2">
          <!-- Side Bar Menu -->
          <v-row>
            <v-col cols="12" md="3" lg="3" sm="6" class="fill-height">
              <v-row>
                <v-col cols="12" class="d-flex justify-center">
                  <div class="profile-picture-container">
                    <v-img
                      :src="profileImage"
                      alt="Profile Image"
                      class="profile-picture"
                      cover
                    />
                    <div
                      class="profile-edit-overlay"
                      @click="uploadDialog = true"
                    >
                      <v-icon size="24" color="white">mdi-pencil</v-icon>
                    </div>
                    <input
                      ref="fileInput"
                      type="file"
                      accept="image/*"
                      style="display: none"
                      @change="handleImageUpload"
                    />
                  </div>
                </v-col>
                <v-col cols="12" class="text-center mb-4">
                  <p class="text-h6 mb-0">{{ employeeNumber }}</p>
                  <div
                    class="d-flex align-center justify-center flex-wrap text-h6"
                  >
                    <span>{{ employeeName }}</span>

                    <v-tooltip text="PSA Verified" v-if="employeePsaVerified">
                      <template #activator="{ props }">
                        <v-icon
                          v-bind="props"
                          color="green"
                          class="ml-2"
                          size="20"
                        >
                          mdi-check-decagram
                        </v-icon>
                      </template>
                    </v-tooltip>
                  </div>

                  <v-card-subtitle class="text-wrap py-0">{{
                    employeePosition
                  }}</v-card-subtitle>
                </v-col>
              </v-row>
              <v-divider></v-divider>
              <v-row class="mt-2">
                <v-col
                  v-for="menu in myProfileMenu"
                  :key="menu.title"
                  cols="12"
                  class="d-flex align-center justify-left"
                >
                  <v-btn
                    variant="text"
                    class="pa-1 menu-btn"
                    :class="{ 'active-menu': activeMenu === menu.title }"
                    @click="setActiveMenu(menu.title)"
                  >
                    {{ menu.title }}
                  </v-btn>
                </v-col>
              </v-row>
            </v-col>

            <v-divider vertical></v-divider>

            <!-- Main Content -->
            <v-col
              cols="12"
              md="9"
              lg="9"
              sm="6"
              class="align-center justify-center"
            >
              <!-- Edit Toggle Button -->
              <v-row
                class="mb-4"
                v-if="
                  route().current().startsWith('self-service.') &&
                  activeMenu !== 'Job'
                "
              >
                <v-col cols="12" class="d-flex justify-end">
                  <div class="d-flex align-center">
                    <span class="mr-2 text-body-2">Edit Mode</span>
                    <v-switch
                      v-model="editMode"
                      color="starbucks-green"
                      hide-details
                      inset
                    ></v-switch>
                  </div>
                </v-col>
              </v-row>

              <!-- <pre>{{ employee}}</pre> -->

              <!-- Job Form -->
              <JobForm
                v-if="activeMenu === 'Job'"
                :editMode="editMode"
                :employee_id="employee_id"
                :employeeJobDetails="employeeJobDetails"
                :operatingUnits="operatingUnits"
                :detailed_at="detailed_at"
                :departments="departments"
                :jobStatuses="jobStatuses"
                :positions="positions"
                :salarySteps="salarySteps"
                :designations="designations"
                :employeeDesignations="employeeDesignations"
              />
              <!-- Personal Information Form -->
              <PersonalInformation
                v-if="activeMenu === 'Personal Information'"
                :isFormEditable="isFormEditable"
                :employee_id="employee_id"
                :employeePersonalInformation="employeePersonalInformation"
              />
              <!-- Family Background Form -->
              <FamilyBackgroundForm
                v-if="activeMenu === 'Family Background'"
                :isFormEditable="isFormEditable"
                :employee_id="employee_id"
                :employeeFamilyBackground="employeeFamilyBackground"
                :employeeSpouseInformation="employeeSpouseInformation"
                :employeeChildren="employeeChildren"
                @childrenRefresh="refreshEmployeeChildren"
              />
              <!-- Educational Background Form -->
              <EducationalBackgroundForm
                v-if="activeMenu === 'Educational Background'"
                :isFormEditable="isFormEditable"
                :employee_id="employee_id"
                :employeeEducationalBackground="employeeEducationalBackground"
                @educationalBackgroundRefresh="
                  refreshEmployeeEducationalBackground
                "
              />
              <!-- Civil Service Eligibility Form -->
              <CivilServiceEligibilityForm
                v-if="activeMenu === 'Civil Service Eligibility'"
                :isFormEditable="isFormEditable"
                :employee_id="employee_id"
                :employeeCivilServiceEligibilities="
                  employeeCivilServiceEligibilities
                "
                @eligibilityRefresh="refreshEligibilities"
              />
              <!-- Work Experience Form -->
              <WorkExperienceForm
                v-if="activeMenu === 'Work Experience'"
                :isFormEditable="isFormEditable"
                :employee_id="employee_id"
                :employeeWorkExperiences="employeeWorkExperiences"
                @workExperienceRefresh="refreshWorkExperiences"
              />
              <!-- Voluntary Work Form -->
              <VoluntaryWorkForm
                v-if="activeMenu === 'Voluntary Work'"
                :isFormEditable="isFormEditable"
                :employee_id="employee_id"
                :employeeVoluntaryWorks="employeeVoluntaryWorks"
                @voluntaryWorkRefresh="voluntaryWorkRefresh"
              />
              <!-- Learning and Development Form -->
              <LearningAndDevelopmentForm
                v-if="activeMenu === 'Learning and Development'"
                :isFormEditable="isFormEditable"
                :employee_id="employee_id"
                :employeeLearningAndDevelopment="employeeLearningAndDevelopment"
                @learningAndDevelopmentRefresh="refreshLearningAndDevelopment"
              />
              <!-- Other Information Form -->
              <OtherInformationForm
                v-if="activeMenu === 'Other Information'"
                :isFormEditable="isFormEditable"
                :employee_id="employee_id"
                :employeeJobDetails="employeeJobDetails"
                :employeeSpecialSkills="employeeSpecialSkills"
                :employeeNonAcademicDistinctions="
                  employeeNonAcademicDistinctions
                "
                :employeeOtherInfoOrganizations="employeeOtherInfoOrganizations"
              />
              <!-- Additional Information Form -->
              <AdditionalInformationForm
                v-if="activeMenu === 'Additional Information'"
                :isFormEditable="isFormEditable"
                :employee_id="employee_id"
                :employeeJobDetails="employeeJobDetails"
                :employeeAdditionalInformation="employeeAdditionalInformation"
                :employeeReferences="employeeReferences"
              />
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <!-- Upload Image Dialog -->
  <v-dialog v-model="uploadDialog" max-width="500">
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center pa-4">
        <span>Upload Image</span>
        <v-btn icon="mdi-close" size="small" @click="closeUploadDialog"></v-btn>
      </v-card-title>

      <v-card-text>
        <div class="text-body-2 mb-4">File size limit: 25MB</div>

        <v-progress-linear
          v-if="uploading"
          :model-value="uploadProgress"
          color="primary"
          height="25"
        >
          <template v-slot:default="{ value }">
            <strong
              >{{ Math.ceil(value) }}% | {{ formatFileSize(uploadedSize) }} of
              {{ formatFileSize(totalSize) }}</strong
            >
          </template>
        </v-progress-linear>

        <input
          type="file"
          ref="fileInput"
          @change="handleFileUpload"
          accept="image/*"
          class="d-none"
        />

        <!-- Image Preview Section -->
        <div v-if="previewImage" class="preview-section mb-4">
          <div class="text-body-1 mb-3 font-weight-medium">Preview:</div>
          <div class="preview-container">
            <v-img
              :src="previewImage"
              alt="Preview"
              class="preview-image rounded"
              cover
            />
            <v-btn
              icon="mdi-close"
              size="small"
              color="error"
              class="preview-remove-btn"
              @click="removePreview"
            />
          </div>
          <div class="text-body-2 text-grey mt-2">
            {{ selectedFile ? selectedFile.name : "" }}
            ({{ selectedFile ? formatFileSize(selectedFile.size) : "" }})
          </div>
        </div>

        <!-- Upload Area (shown when no preview) -->
        <div v-if="!previewImage" class="d-flex justify-center mt-4 mb-4">
          <div
            class="upload-area pa-8 rounded border-dashed border-2 text-center"
            @dragover.prevent
            @dragenter.prevent="handleDragEnter"
            @dragleave.prevent="handleDragLeave"
            @drop.prevent="handleDrop"
            :class="{
              uploading: uploading,
              'drag-over': dragOver,
              'border-primary': dragOver,
              'bg-blue-lighten-5': dragOver,
            }"
          >
            <v-icon
              size="48"
              :color="dragOver ? 'primary' : 'grey-lighten-1'"
              class="mb-4"
            >
              mdi-cloud-upload-outline
            </v-icon>
            <div class="text-body-1 mb-2" :class="{ 'text-primary': dragOver }">
              Drag and drop your image here
            </div>
            <div class="text-body-2 text-grey">or</div>
            <v-btn
              color="primary"
              variant="text"
              class="mt-2"
              @click="$refs.fileInput.click()"
              :disabled="uploading"
            >
              Browse files
            </v-btn>
          </div>
        </div>
      </v-card-text>

      <!-- Action Buttons -->
      <v-card-actions class="pa-4">
        <v-spacer></v-spacer>
        <v-btn variant="text" @click="closeUploadDialog" :disabled="uploading">
          Cancel
        </v-btn>
        <v-btn
          color="primary"
          @click="uploadImage"
          :disabled="!selectedFile || uploading"
          :loading="uploading"
        >
          Upload
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
<script>
import SidebarLayout from "@/layouts/SidebarLayout.vue";
import Breadcrumbs from "@/components/Breadcrumbs.vue";
import useVuelidate from "@vuelidate/core";
import {
  required,
  minLength,
  maxLength,
  email,
  sameAs,
  numeric,
  alpha,
  helpers,
} from "@vuelidate/validators";
import { useForm } from "@inertiajs/vue3";
import JobForm from "@/components/EmployeeProfile/JobForm.vue";
import PersonalInformation from "@/components/EmployeeProfile/PersonalInformation.vue";
import FamilyBackgroundForm from "@/components/EmployeeProfile/FamilyBackgroundForm.vue";
import EducationalBackgroundForm from "@/components/EmployeeProfile/EducationalBackgroundForm.vue";
import CivilServiceEligibilityForm from "@/components/EmployeeProfile/CivilServiceEligibilityForm.vue";
import WorkExperienceForm from "@/components/EmployeeProfile/WorkExperienceForm.vue";
import VoluntaryWorkForm from "@/components/EmployeeProfile/VoluntaryWorkForm.vue";
import LearningAndDevelopmentForm from "@/components/EmployeeProfile/LearningAndDevelopmentForm.vue";
import OtherInformationForm from "@/components/EmployeeProfile/OtherInformationForm.vue";
import AdditionalInformationForm from "@/components/EmployeeProfile/AdditionalInformationForm.vue";

export default {
  layout: SidebarLayout,
  components: {
    Breadcrumbs,
    JobForm,
    PersonalInformation,
    FamilyBackgroundForm,
    EducationalBackgroundForm,
    CivilServiceEligibilityForm,
    WorkExperienceForm,
    VoluntaryWorkForm,
    LearningAndDevelopmentForm,
    OtherInformationForm,
    AdditionalInformationForm,
  },
  props: {
    operatingUnits: Array,
    detailed_at: Array,
    departments: Array,
    employeeJobDetails: Object, // For Job Form
    jobStatuses: Array,
    positions: Array,
    salarySteps: Array,
    designations: Array,
    employeeDesignations: Array, // Added employeeDesignations prop
    employeePersonalInformation: Object, // For Personal Information Form
    employeeFamilyBackground: Object,
    employeeSpouseInformation: Object,
    employeeChildren: Object,
    employeeEducationalBackground: Object, // For Educational Background Form
    employeeCivilServiceEligibilities: Array, // For Civil Service Eligibility Form
    employeeWorkExperiences: Array, // For Work Experience Form
    employeeLearningAndDevelopment: Object, // For Learning and Development Form
    employeeAdditionalInformation: Object, // For Additional Information Form
    employeeReferences: [Object, Array], // For Additional Information Form (can be Object with data property or Array)
    employee: Object, // For Additional Information Form
    employeeSpecialSkills: Array,
    employeeNonAcademicDistinctions: Array,
    employeeOtherInfoOrganizations: Array,
    employeeVoluntaryWorks: Object,
  },

  data() {
    return {
      v$: useVuelidate(),
      employee_id: this.employeeJobDetails.data.id,
      employeeNumber: this.employeeJobDetails.data.employee_number,
      profileImage: "https://via.placeholder.com/150",
      myProfileMenu: [
        {
          title: "Job",
        },
        {
          title: "Personal Information",
        },
        {
          title: "Family Background",
        },
        {
          title: "Educational Background",
        },
        {
          title: "Civil Service Eligibility",
        },
        {
          title: "Work Experience",
        },
        {
          title: "Voluntary Work",
        },
        {
          title: "Learning and Development",
        },
        {
          title: "Other Information",
        },
        {
          title: "Additional Information",
        },
      ],
      activeMenu: "Job", // Initialize active menu

      govtService: [
        { title: "Yes", value: "Y" },
        { title: "No", value: "N" },
      ],

      editMode: false,

      children: [
        { fullName: "", dateOfBirth: "", occupation: "", contactNo: "" },
      ],

      uploadDialog: false,
      uploading: false,
      uploadProgress: 0,
      uploadedSize: 0,
      totalSize: 0,
      dragOver: false,
      previewImage: null,
      selectedFile: null,

      employeePsaVerified: this.employeePersonalInformation.data?.psa_verified,
    };
  },

  computed: {
    profileImage() {
      if (this.employeeJobDetails?.data?.photo) {
        // Add timestamp to bust cache
        return `/storage/${this.employeeJobDetails.data.photo}?t=${Date.now()}`;
      }
      return "/images/default-avatar.png";
    },

    isFormEditable() {
      return (
        !this.editMode &&
        route().current().startsWith("self-service.") &&
        !route().current().startsWith("hrmanagement.")
      );
    },

    employeeName() {
      const info = this.employeePersonalInformation.data;
      return [info.firstname, info.middlename, info.lastname, info.suffix]
        .filter(Boolean) // removes null, undefined, or empty strings
        .join(" ");
    },

    employeePosition() {
      const info = this.employeeJobDetails.data;
      return [
        info.position_name,
        info.parenthetical_title ? `(${info.parenthetical_title})` : null,
      ]
        .filter(Boolean)
        .join(" ");
    },
  },

  methods: {
    handleSubmitEducationalBackground() {
      this.v$.$touch();
      if (this.v$.$invalid) {
        return;
      }
      alert("Educational Background form submitted successfully!");
    },

    handleJobFormSubmitted(formData) {
      console.log("Job form submitted:", formData);
      // Here you can handle the job form data
      // For example, send it to the server using Inertia.js
      // this.$inertia.post(route('employee.job-details.store'), formData)
      alert("Job Details form submitted successfully!");
    },

    openImageUpload() {
      this.$refs.fileInput.click();
    },

    openFileUpload(index) {
      this.$refs[`fileInput${index}`][0].click();
    },

    handleFileUpload(event, index) {
      const file = event.target.files[0];
      if (file) {
        this.learningAndDevelopment[index].file = file;
        this.learningAndDevelopment[index].fileName = file.name;
        console.log("Selected file for learning and development:", file.name);
      }
    },

    // Drag and drop methods for image upload
    handleDrop(event) {
      this.dragOver = false;
      const files = event.dataTransfer.files;
      if (files.length > 0) {
        const file = files[0];
        if (file.type.startsWith("image/")) {
          this.processImageFile(file);
        } else {
          this.showToast("Please select an image file.", "error");
        }
      }
    },

    handleDragEnter(event) {
      event.preventDefault();
      this.dragOver = true;
    },

    handleDragLeave(event) {
      event.preventDefault();
      this.dragOver = false;
    },

    handleFileUpload(event) {
      const file = event.target.files[0];
      if (file) {
        this.processImageFile(file);
      }
    },

    async processImageFile(file) {
      // Validate file size (25MB limit)
      const maxSize = 25 * 1024 * 1024; // 25MB in bytes
      if (file.size > maxSize) {
        this.showToast("File size exceeds 25MB limit.", "error");
        return;
      }

      // Validate file type
      if (!file.type.startsWith("image/")) {
        this.showToast("Please select an image file.", "error");
        return;
      }

      // Store selected file and create preview
      this.selectedFile = file;
      this.createImagePreview(file);
    },

    createImagePreview(file) {
      // Clean up previous preview URL to prevent memory leaks
      if (this.previewImage) {
        URL.revokeObjectURL(this.previewImage);
      }

      // Create new preview URL
      this.previewImage = URL.createObjectURL(file);
    },

    removePreview() {
      if (this.previewImage) {
        URL.revokeObjectURL(this.previewImage);
        this.previewImage = null;
      }
      this.selectedFile = null;
      // Reset file input
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = "";
      }
    },

    uploadImage() {
      if (!this.selectedFile) return;

      this.uploading = true;
      this.uploadProgress = 0;
      this.uploadedSize = 0;
      this.totalSize = this.selectedFile.size;
      try {
        const formData = new FormData();
        formData.append("profile_picture", this.selectedFile);
        formData.append("employee_id", this.employeeJobDetails.data.id);

        // Simulate upload progress (you can replace this with actual progress tracking)
        const progressInterval = setInterval(() => {
          if (this.uploadProgress < 90) {
            this.uploadProgress += Math.random() * 30;
            this.uploadedSize = (this.uploadProgress / 100) * this.totalSize;
          }
        }, 200);

        this.$inertia.post(
          route("self-service.my-profile.uploadPicture"),
          formData,
          {
            preserveState: true,
            preserveScroll: true,

            onSuccess: () => {
              this.showToast(
                "Profile picture uploaded successfully.",
                "success"
              );
              this.uploadDialog = false;
              //   this.removePreview();
              this.uploading = false;
              this.uploadProgress = 100;
              this.uploadedSize = this.totalSize;
              this.removePreview();
              this.$inertia.reload({ only: ["employeeJobDetails"] });
            },
            onError: (errors) => {
              this.uploading = false;
              const errorMessages = Object.values(errors).flat().join(" ");
              this.showToast(`${errorMessages}`, "error");
              //   console.error("Upload error:", errors);
            },
          }
        );
      } catch (errors) {
        this.uploading = false;
        const errorMessages = Object.values(errors).flat().join(" ");

        this.showToast(`${errorMessages}`, "error");
      }
    },

    formatFileSize(bytes) {
      if (bytes === 0) return "0 Bytes";
      const k = 1024;
      const sizes = ["Bytes", "KB", "MB", "GB"];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
    },

    closeUploadDialog() {
      this.uploadDialog = false;
      this.removePreview();
    },

    setActiveMenu(title) {
      this.activeMenu = title;
    },

    addVoluntaryWork() {
      this.voluntaryWorks.push({
        nameAddress: "",
        position: "",
        from: "",
        to: "",
        hours: "",
      });
    },
    removeVoluntaryWork(index) {
      this.voluntaryWorks.splice(index, 1);
    },
    addCareerService() {
      this.careerServices.push({
        careerService: "",
        rating: "",
        dateOfExamination: "",
        placeOfExamination: "",
        license: "",
      });
    },
    removeCareerService(index) {
      this.careerServices.splice(index, 1);
    },

    refreshEmployeeJobDetails() {
      this.$inertia.reload({ only: ["employeeJobDetails"] });
    },

    refreshEmployeeChildren() {
      this.$inertia.reload({ only: ["employeeFamilyBackground"] });
    },

    refreshEmployeeEducationalBackground() {
      this.$inertia.reload({ only: ["employeeEducationalBackground"] });
    },

    refreshEligibilities() {
      this.$inertia.reload({ only: ["employeeCivilServiceEligibilities"] });
    },

    refreshWorkExperiences() {
      this.$inertia.reload({ only: ["employeeWorkExperiences"] });
    },

    refreshVoluntaryWorks() {
      this.$inertia.reload({ only: ["employeeVoluntaryWorks"] });
    },

    refreshLearningAndDevelopment() {
      this.$inertia.reload({ only: ["employeeLearningAndDevelopment"] });
    },
  },
};
</script>

<style scoped>
.profile-picture-container {
  width: 150px;
  height: 150px;
  border-radius: 50%;
  overflow: hidden;
  border: 3px solid #068746;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  margin: 0 auto 16px auto;
}

.profile-picture {
  width: 100%;
  height: 100%;
  border-radius: 50%;
}

.profile-picture-container {
  position: relative;
  cursor: pointer;
}

.profile-edit-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.6);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.profile-picture-container:hover .profile-edit-overlay {
  opacity: 0.5;
}

/* Menu button styles for consistent width */
.menu-btn {
  width: 100% !important;
  justify-content: flex-start !important;
  text-align: left !important;
  min-height: 40px !important;
}

/* New styles for active menu */
.active-menu {
  background-color: #e0f2f7; /* Light blue background for active */
  border-radius: 8px;
  font-weight: bold;
  color: #068746; /* Dark green text for active */
}

/* Hover styles for menu buttons */
.menu-btn:hover {
  background-color: #f0f8ff; /* Light blue background on hover */
  border-radius: 8px;
  transition: all 0.3s ease;
}
</style>
