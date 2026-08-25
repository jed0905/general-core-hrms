<template>
  <v-form class="ma-4 pa-4" @submit.prevent="submitPersonalInformationForm()">
    <div
      class="d-flex align-center justify-space-between mb-4 flex-column flex-md-row"
    >
      <div class="v-card-title text-center text-md-left">Basic Information</div>
      <div>
        <v-btn
          v-if="
            $page.url.startsWith('/self-service')
          "
          class="mt-2 mt-md-0"
          rounded="xl"
          color="starbucks-green"
          variant="tonal"
          size="small"
          @click="openPSAVerificationDialog('basic')"
        >
          <v-icon>mdi-shield-check</v-icon>
          PSA Verification
        </v-btn>
      </div>
    </div>

    <v-row>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="First Name"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.firstname"
          :error-messages="
            v$.personalInformationForm.firstname.$errors.map((e) => e.$message)
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Middle Name"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.middlename"
          :error-messages="
            v$.personalInformationForm.middlename.$errors.map((e) => e.$message)
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Last Name"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.lastname"
          :error-messages="
            v$.personalInformationForm.lastname.$errors.map((e) => e.$message)
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Name Extension"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.suffix"
          :error-messages="
            v$.personalInformationForm.suffix.$errors.map((e) => e.$message)
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field
          type="date"
          density="compact"
          variant="outlined"
          label="Date of Birth"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.date_of_birth"
          :error-messages="
            v$.personalInformationForm.date_of_birth.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="8">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Place of Birth"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.place_of_birth"
          :error-messages="
            v$.personalInformationForm.place_of_birth.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-select
          density="compact"
          variant="outlined"
          label="Sex"
          rounded="lg"
          :items="sex"
          :disabled="isFormEditable"
          v-model="personalInformationForm.sex"
          :error-messages="
            v$.personalInformationForm.sex.$errors.map((e) => e.$message)
          "
        ></v-select>
      </v-col>
      <v-col cols="12" md="3">
        <v-select
          density="compact"
          variant="outlined"
          label="Civil Status"
          rounded="lg"
          :items="civilStatus"
          :disabled="isFormEditable"
          v-model="personalInformationForm.civil_status"
          :error-messages="
            v$.personalInformationForm.civil_status.$errors.map(
              (e) => e.$message
            )
          "
        ></v-select>
      </v-col>
      <v-col
        cols="12"
        md="3"
        v-if="personalInformationForm.civil_status == 'Other/s'"
      >
        <v-text-field
          density="compact"
          variant="outlined"
          label="Other Civil Status"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.otherCivilStatus"
          :error-messages="
            v$.personalInformationForm.otherCivilStatus.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>

      <v-col cols="12" md="2">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Height (m)"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.height"
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="2">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Weight (kg)"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.weight"
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="2">
        <v-select
          density="compact"
          variant="outlined"
          label="Blood Type"
          rounded="lg"
          :items="bloodType"
          :disabled="isFormEditable"
          v-model="personalInformationForm.blood_type"
        ></v-select>
      </v-col>
      <v-divider style="border: 1px solid black"></v-divider>

      <v-col cols="12" md="6">
        <div class="v-card-title">Citizenship</div>

        <v-radio-group
          v-model="personalInformationForm.citizenship"
          :disabled="isFormEditable"
          inline
          :error-messages="
            v$.personalInformationForm.citizenship.$errors.map(
              (e) => e.$message
            )
          "
        >
          <v-col cols="6">
            <v-radio label="Filipino" value="filipino"></v-radio>
          </v-col>
          <v-col cols="6">
            <v-radio label="Dual Citizenship" value="dual"></v-radio>
          </v-col>
        </v-radio-group>
      </v-col>

      <!-- Dual Citizenship options -->
      <v-col cols="12" v-if="personalInformationForm.citizenship === 'dual'">
        <v-row>
          <v-col cols="12" md="6">
            <v-radio-group
              v-model="personalInformationForm.dual_citizenship_type"
              :disabled="isFormEditable"
              label="If Dual, acquired by"
              inline
            >
              <v-radio label="By Birth" value="birth"></v-radio>
              <v-radio
                label="By Naturalization"
                value="naturalization"
              ></v-radio>
            </v-radio-group>
          </v-col>

          <v-col cols="12" md="6">
            <v-autocomplete
              density="compact"
              variant="outlined"
              label="Country"
              rounded="lg"
              :items="countries"
              item-title="name"
              item-value="name"
              :disabled="isFormEditable"
              v-model="personalInformationForm.dual_citizenship_country"
            ></v-autocomplete>
          </v-col>
        </v-row>
      </v-col>

      <v-divider></v-divider>
      <v-col cols="12">
        <v-card-title>Residential Address</v-card-title>
        <v-row>
          <v-col cols="12" md="6">
            <v-autocomplete
              density="compact"
              variant="outlined"
              label="Province"
              :items="residential_provinces"
              item-title="name"
              item-value="code"
              rounded="lg"
              :disabled="isFormEditable"
              v-model="personalInformationForm.residential_province"
              :error-messages="
                v$.personalInformationForm.residential_province.$errors.map(
                  (e) => e.$message
                )
              "
              return-object
            ></v-autocomplete>
          </v-col>
          <v-col cols="12" md="6">
            <v-autocomplete
              density="compact"
              variant="outlined"
              label="City/Municipality"
              rounded="lg"
              :items="residential_filtered_municipalities"
              item-value="name"
              item-title="name"
              :disabled="isFormEditable"
              v-model="personalInformationForm.residential_city_municipality"
              :error-messages="
                v$.personalInformationForm.residential_city_municipality.$errors.map(
                  (e) => e.$message
                )
              "
              return-object
            ></v-autocomplete>
          </v-col>
          <v-col cols="12" md="6">
            <v-autocomplete
              density="compact"
              variant="outlined"
              label="Barangay"
              rounded="lg"
              :items="residential_filtered_barangay"
              item-value="name"
              item-title="name"
              :disabled="isFormEditable"
              v-model="personalInformationForm.residential_barangay"
              :error-messages="
                v$.personalInformationForm.residential_barangay.$errors.map(
                  (e) => e.$message
                )
              "
              return-object
            ></v-autocomplete>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Subdivision/Village"
              rounded="lg"
              :disabled="isFormEditable"
              v-model="personalInformationForm.residential_subdivision"
              :error-messages="
                v$.personalInformationForm.residential_subdivision.$errors.map(
                  (e) => e.$message
                )
              "
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Street"
              rounded="lg"
              :disabled="isFormEditable"
              v-model="personalInformationForm.residential_street"
              :error-messages="
                v$.personalInformationForm.residential_street.$errors.map(
                  (e) => e.$message
                )
              "
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              density="compact"
              variant="outlined"
              label="House/Block/Lot No."
              rounded="lg"
              :disabled="isFormEditable"
              v-model="personalInformationForm.residential_house_no"
              :error-messages="
                v$.personalInformationForm.residential_house_no.$errors.map(
                  (e) => e.$message
                )
              "
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Zip Code"
              rounded="lg"
              readonly
              :disabled="isFormEditable"
              :model-value="
                personalInformationForm.residential_city_municipality
                  ? personalInformationForm.residential_city_municipality
                      .zip_code
                  : null
              "
            ></v-text-field>
          </v-col>
        </v-row>
      </v-col>
      <v-divider></v-divider>
      <v-col cols="12">
        <div class="v-card-title">Permanent Address</div>

        <div class="d-flex align-center">
          <v-checkbox
            label="Same as Residential Address"
            v-model="personalInformationForm.same_as_residential_address"
            @update:model-value="handleSameAsResidentialAddress"
          ></v-checkbox>
        </div>

        <v-row>
          <v-col cols="12" md="6">
            <v-autocomplete
              density="compact"
              variant="outlined"
              label="Province"
              rounded="lg"
              :items="permanent_provinces"
              item-title="name"
              item-value="code"
              :disabled="isFormEditable"
              v-model="personalInformationForm.permanent_province"
              :error-messages="
                v$.personalInformationForm.permanent_province.$errors.map(
                  (e) => e.$message
                )
              "
              return-object
            ></v-autocomplete>
          </v-col>
          <v-col cols="12" md="6">
            <v-autocomplete
              density="compact"
              variant="outlined"
              label="City/Municipality"
              rounded="lg"
              :items="permanent_filtered_municipalities"
              item-title="name"
              item-value="name"
              :disabled="isFormEditable"
              v-model="personalInformationForm.permanent_city_municipality"
              :error-messages="
                v$.personalInformationForm.permanent_city_municipality.$errors.map(
                  (e) => e.$message
                )
              "
              return-object
            ></v-autocomplete>
          </v-col>
          <v-col cols="12" md="6">
            <v-autocomplete
              density="compact"
              variant="outlined"
              label="Barangay"
              rounded="lg"
              :items="permanent_filtered_barangay"
              item-title="name"
              item-value="name"
              :disabled="isFormEditable"
              v-model="personalInformationForm.permanent_barangay"
              :error-messages="
                v$.personalInformationForm.permanent_barangay.$errors.map(
                  (e) => e.$message
                )
              "
              return-object
            ></v-autocomplete>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Subdivision/Village"
              rounded="lg"
              :disabled="isFormEditable"
              v-model="personalInformationForm.permanent_subdivision"
              :error-messages="
                v$.personalInformationForm.permanent_subdivision.$errors.map(
                  (e) => e.$message
                )
              "
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Street"
              rounded="lg"
              :disabled="isFormEditable"
              v-model="personalInformationForm.permanent_street"
              :error-messages="
                v$.personalInformationForm.permanent_street.$errors.map(
                  (e) => e.$message
                )
              "
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              density="compact"
              variant="outlined"
              label="House/Block/Lot No."
              rounded="lg"
              :disabled="isFormEditable"
              v-model="personalInformationForm.permanent_house_no"
              :error-messages="
                v$.personalInformationForm.permanent_house_no.$errors.map(
                  (e) => e.$message
                )
              "
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Zip Code"
              rounded="lg"
              readonly
              :disabled="isFormEditable"
              v-model="personalInformationForm.permanent_zip_code"
              :model-value="
                personalInformationForm.permanent_city_municipality
                  ? personalInformationForm.permanent_city_municipality.zip_code
                  : null
              "
            ></v-text-field>
          </v-col>
        </v-row>
      </v-col>
      <v-divider></v-divider>
      <v-col cols="12">
        <v-card-title>Contact Information</v-card-title>
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Telephone Number"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.telephone_no"
          :error-messages="
            v$.personalInformationForm.telephone_no.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Mobile Number"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.mobile_no"
          :error-messages="
            v$.personalInformationForm.mobile_no.$errors.map((e) => e.$message)
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Email Address"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.email"
          :error-messages="
            v$.personalInformationForm.email.$errors.map((e) => e.$message)
          "
        ></v-text-field>
      </v-col>
      <v-divider></v-divider>
      <v-col cols="12">
        <v-card-title>Government Issued IDs</v-card-title>
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field
          density="compact"
          variant="outlined"
          label="GSIS ID No"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.gsis_id_no"
          :error-messages="
            v$.personalInformationForm.gsis_id_no.$errors.map((e) => e.$message)
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field
          density="compact"
          variant="outlined"
          label="PAG-IBIG ID No"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.pag_ibig_id_no"
          :error-messages="
            v$.personalInformationForm.pag_ibig_id_no.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field
          density="compact"
          variant="outlined"
          label="PhilHealth ID No"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.philhealth_id_no"
          :error-messages="
            v$.personalInformationForm.philhealth_id_no.$errors.map(
              (e) => e.$message
            )
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field
          density="compact"
          variant="outlined"
          label="SSS No"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.sss_id_no"
          :error-messages="
            v$.personalInformationForm.sss_id_no.$errors.map((e) => e.$message)
          "
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="4">
        <v-text-field
          density="compact"
          variant="outlined"
          label="TIN No"
          rounded="lg"
          :disabled="isFormEditable"
          v-model="personalInformationForm.tin_id_no"
          :error-messages="
            v$.personalInformationForm.tin_id_no.$errors.map((e) => e.$message)
          "
        ></v-text-field>
      </v-col>

      <v-col cols="12" v-if="$page.url.startsWith('/self-service')">
        <v-divider></v-divider>
        <v-card-title>Electronic Signature</v-card-title>

        <div class="mt-2">
          <!-- If there is a signature uploaded -->
          <template v-if="employeePersonalInformation?.data?.e_signature_path">
            <!-- Show / Change buttons -->
            <v-btn
              color="primary"
              @click="showSignatureSection = !showSignatureSection"
            >
              {{ showSignatureSection ? "Hide Signature" : "Show Signature" }}
            </v-btn>
            <v-btn
              color="secondary"
              class="ml-2"
              @click="showSignatureDialog = true"
            >
              Change Signature
            </v-btn>
          </template>

          <!-- If no signature uploaded yet -->
          <template v-else>
            <v-btn color="primary" @click="showSignatureDialog = true">
              Upload Signature
            </v-btn>
          </template>
        </div>

        <!-- Preview / Upload Section -->
        <v-expand-transition>
          <div v-if="showSignatureSection" class="mt-4">
            <v-card
              outlined
              class="pa-4"
              style="position: relative; max-width: 280px; min-height: 160px"
            >
              <p class="mb-2 font-medium">Current Signature:</p>

              <!-- Watermark logo behind -->
              <img
                src="/images/HRMS.svg"
                alt="HRMS Logo"
                style="
                  position: absolute;
                  top: 50%;
                  left: 50%;
                  transform: translate(-50%, -50%);
                  opacity: 0.3;
                  z-index: 0;
                  max-width: 80%;
                  height: auto;
                  pointer-events: none;
                "
              />

              <!-- Signature image in front -->
              <img
                v-if="employeePersonalInformation?.data?.e_signature_path"
                :src="`/storage/${employeePersonalInformation.data.e_signature_path}`"
                alt="Signature"
                style="
                  position: relative;
                  z-index: 1;
                  max-width: 260px;
                  display: block;
                  margin: 0 auto;
                "
              />
            </v-card>
          </div>
        </v-expand-transition>
      </v-col>

      <!-- <v-col cols="12" md="4">
        <v-text-field
          density="compact"
          variant="outlined"
          label="Agency Employee No"
          rounded="lg"
          :disabled="isFormEditable"
          readonly
          v-model="personalInformationForm.agencyIdNo"
        ></v-text-field>
      </v-col> -->
      <v-col cols="12">
        <div class="d-flex align-center justify-end">
          <v-btn
            type="submit"
            rounded="xl"
            class="starbucks-green"
            min-width="120"
            :disabled="isFormEditable"
            >Save</v-btn
          >
        </div>
      </v-col>
    </v-row>
  </v-form>

  <!-- Signature Upload Dialog -->
  <v-dialog v-model="showSignatureDialog" max-width="500px">
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center">
        <span>Upload Electronic Signature</span>
        <!-- Close button -->
        <v-btn icon @click="showSignatureDialog = false">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-card-title>

      <v-card-text>
        <v-file-input
          ref="signatureFileInput"
          accept=".png,image/png"
          label="Choose PNG file"
          variant="outlined"
          density="compact"
          prepend-icon="mdi-file-image"
          @update:modelValue="handleSignatureUpload"
        ></v-file-input>

        <div class="text-caption text-medium-emphasis">
          Only PNG files are allowed. Maximum file size: 5MB.
        </div>

        <v-img
          v-if="signatureDataUrl"
          :src="signatureDataUrl"
          max-width="260"
          class="mt-3"
        ></v-img>
      </v-card-text>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn text color="error" @click="removeSignature">Remove</v-btn>
        <v-btn color="primary" @click="uploadSignature">Upload</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <!-- PSA Verification Dialog -->
  <v-dialog v-model="isPSAVerificationDialog" max-width="600" persistent>
    <v-card>
      <v-card-title class="d-flex align-center">
        <v-icon color="starbucks-green" class="mr-2">mdi-shield-check</v-icon>
        PSA Verification
      </v-card-title>
      <v-divider></v-divider>
      <v-card-text class="pt-4">
        <div class="mb-4">
          <v-alert
            border="start"
            type="info"
            color="starbucks-green"
            icon="mdi-information"
            density="compact"
          >
            To increase the security and integrity of your profile, please
            verify your identity with the Philippine Statistics Authority (PSA).
            Your personal information will be checked against PSA records to
            ensure accuracy and for compliance with government regulations.
          </v-alert>
        </div>

        <div class="mb-4">
          <div class="text-subtitle-1 font-weight-bold mb-3">
            Select Verification Method
          </div>
          <v-radio-group
            v-model="psaVerificationMethod"
            inline
            density="compact"
          >
            <v-radio
              label="Basic Information"
              value="basic"
              color="starbucks-green"
            >
              <template v-slot:label>
                <div>
                  <div class="font-weight-medium">Basic Information</div>
                  <div class="text-caption text-medium-emphasis">
                    Use your name and date of birth
                  </div>
                </div>
              </template>
            </v-radio>
            <v-radio
              label="Nat'l ID Card No/Digital Nat'l ID No"
              value="pcn"
              color="starbucks-green"
            >
              <template v-slot:label>
                <div>
                  <div class="font-weight-medium">
                    Nat'l ID Card No/Digital Nat'l ID No
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Use your Nat'l ID Card No or Digital Nat'l ID No
                  </div>
                </div>
              </template>
            </v-radio>
          </v-radio-group>
        </div>

        <!-- Basic Information Preview -->
        <v-card
          v-if="psaVerificationMethod === 'basic'"
          variant="outlined"
          class="mb-4"
          density="compact"
        >
          <v-card-text>
            <div class="text-caption text-medium-emphasis mb-2">
              Information to be verified:
            </div>
            <v-row dense>
              <v-col cols="12" md="6">
                <div class="mb-2">
                  <span class="text-caption text-medium-emphasis"
                    >First Name:</span
                  >
                  <div class="text-body-2 font-weight-medium">
                    {{ personalInformationForm.firstname || "N/A" }}
                  </div>
                </div>
              </v-col>
              <v-col cols="12" md="6">
                <div class="mb-2">
                  <span class="text-caption text-medium-emphasis"
                    >Middle Name:</span
                  >
                  <div class="text-body-2 font-weight-medium">
                    {{ personalInformationForm.middlename || "N/A" }}
                  </div>
                </div>
              </v-col>
              <v-col cols="12" md="6">
                <div class="mb-2">
                  <span class="text-caption text-medium-emphasis"
                    >Last Name:</span
                  >
                  <div class="text-body-2 font-weight-medium">
                    {{ personalInformationForm.lastname || "N/A" }}
                  </div>
                </div>
              </v-col>
              <v-col cols="12" md="6">
                <div class="mb-2">
                  <span class="text-caption text-medium-emphasis"
                    >Date of Birth:</span
                  >
                  <div class="text-body-2 font-weight-medium">
                    <span v-if="personalInformationForm.date_of_birth">
                      {{
                        new Date(
                          personalInformationForm.date_of_birth
                        ).toLocaleDateString()
                      }}
                    </span>
                    <span v-else class="text-red font-italic"> N/A </span>
                  </div>
                </div>
              </v-col>
            </v-row>
            <v-alert
              type="warning"
              variant="tonal"
              density="compact"
              class="mt-3"
            >
              <div class="text-caption">
                Please ensure all basic information fields are filled correctly
                before proceeding.
              </div>
            </v-alert>
          </v-card-text>
        </v-card>

        <!-- PCN/DNIDN Input -->
        <div v-if="psaVerificationMethod === 'pcn'" class="mb-4">
          <v-text-field
            label="Nat'l ID Card No/Digital Nat'l ID No *"
            density="compact"
            variant="outlined"
            v-model="v$.pcnFormData.id_number.$model"
            prepend-inner-icon="mdi-account-badge-outline"
            placeholder="Enter Nat'l ID Card No/Digital Nat'l ID No"
            :error-messages="
              v$.pcnFormData.id_number.$errors.map((e) => e.$message)
            "
            @input="
              v$.pcnFormData.id_number.$model = v$.pcnFormData.id_number.$model
                .toUpperCase()
                .replace(/[^0-9A-Z]/g, '')
            "
          ></v-text-field>
          <div class="text-caption text-medium-emphasis">
            <v-icon size="14" class="mr-1">mdi-information-outline</v-icon>
            Enter your Nat'l ID Card No or Digital Nat'l ID No for verification.
          </div>
        </div>
      </v-card-text>
      <v-divider></v-divider>
      <v-card-actions class="d-flex align-center justify-end pa-4">
        <v-btn
          color="grey-darken-1"
          variant="outlined"
          @click="closePSAVerificationDialog"
          min-width="120"
          rounded="xl"
        >
          Cancel
        </v-btn>
        <v-btn
          color="starbucks-green"
          variant="elevated"
          @click="proceedWithPSAVerification"
          min-width="140"
          rounded="xl"
          :disabled="!canProceedWithVerification"
          prepend-icon="mdi-shield-check"
        >
          Verify Now
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog
    v-model="isSaveAuthorizationDialogVisible"
    max-width="700"
    persistent
    scrollable
  >
    <v-card>
      <v-card-title class="d-flex align-center pa-4 starbucks-green text-white">
        <v-icon class="mr-2" color="white">mdi-shield-check</v-icon>
        <span class="text-h6 font-weight-bold"
          >Authorize Saving of PSA Data</span
        >
      </v-card-title>

      <v-card-text class="pa-4">
        <!-- Information Alert -->
        <v-alert
          type="info"
          variant="tonal"
          class="mb-4"
          prominent
          density="compact"
          color="starbucks-green"
        >
          <v-alert-title class="text-subtitle-1 font-weight-bold mb-2">
            PSA Verification Successful
          </v-alert-title>
          <div class="text-body-2">
            PSA verification has successfully collected key personal details.
            Please review the information below and authorize saving this data
            to your employee profile.
          </div>
        </v-alert>

        <!-- Data Preview Section -->
        <div class="mb-4">
          <div
            class="text-subtitle-1 font-weight-bold mb-3 d-flex align-center"
          >
            <v-icon class="mr-2" color="starbucks-green"
              >mdi-account-details</v-icon
            >
            Retrieved Information
          </div>

          <v-card variant="outlined" class="pa-3" style="border-color: #006241">
            <v-row dense>
              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    First Name
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.firstname || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Middle Name
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.middlename || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Last Name
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.lastname || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6" v-if="psaLoadedData.suffix">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Suffix
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.suffix }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Birthdate
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{
                      psaLoadedData.date_of_birth
                        ? new Date(
                            psaLoadedData.date_of_birth
                          ).toLocaleDateString()
                        : "N/A"
                    }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Place of Birth
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.place_of_birth || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Mobile Number
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.mobile_no || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Email
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.email || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12">
                <v-divider class="my-2"></v-divider>
                <div
                  class="text-subtitle-2 font-weight-bold mb-2 d-flex align-center"
                >
                  <v-icon class="mr-2" color="starbucks-green" size="small"
                    >mdi-map-marker</v-icon
                  >
                  Address
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Province
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.residential_province || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Municipality/City
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.residential_city_municipality || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Barangay
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.residential_barangay || "N/A" }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <div class="mb-3">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Zip Code
                  </div>
                  <div class="text-body-1 font-weight-medium">
                    {{ psaLoadedData.residential_zip_code || "N/A" }}
                  </div>
                </div>
              </v-col>
            </v-row>
          </v-card>
        </div>

        <!-- Authorization Message -->
        <v-alert type="warning" variant="tonal" density="compact" class="mb-2">
          <div class="text-body-2">
            <strong>Authorization Required:</strong> Do you authorize this
            system to save the information retrieved from the PSA verification
            (such as name, birthdate, and other identifiers) into your employee
            profile? Your consent ensures data accuracy and secure record
            keeping.
          </div>
        </v-alert>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="pa-4 justify-end">
        <v-btn
          min-width="120"
          class="text-black"
          @click="isSaveAuthorizationDialogVisible = false"
          color="grey-darken-1"
          variant="outlined"
          rounded="xl"
          prepend-icon="mdi-close-circle"
          >No</v-btn
        >
        <v-btn
          min-width="120"
          class="text-white"
          @click="continueSaving()"
          color="starbucks-green"
          variant="elevated"
          rounded="xl"
          prepend-icon="mdi-check-circle"
          >Yes</v-btn
        >
      </v-card-actions>
    </v-card>
  </v-dialog>
  <!-- <pre>{{ employeePersonalInformation.data }}</pre> -->
</template>
<script>
import { useForm } from "@inertiajs/vue3";
import useVuelidate from "@vuelidate/core";
import {
  required,
  minLength,
  maxLength,
  numeric,
  email,
} from "@vuelidate/validators";
import { region, province, municipality, country } from "@/composables/psgc.js";
import { barangays } from "psgc";
import { Inertia } from "@inertiajs/inertia";

export default {
  props: {
    isFormEditable: {
      type: Boolean,
      default: null,
    },

    employeePersonalInformation: {
      type: Object,
      required: true,
    },
  },

  data() {
    return {
      showSignatureSection: false, // hidden by default
      showSignatureDialog: false,
      // PSA Verification Dialog
      isPSAVerificationDialog: false,
      psaVerificationMethod: "basic", // 'basic' or 'pcn'
      psaPcnDnNumber: null,
      // Save Authorization Dialog
      isSaveAuthorizationDialogVisible: false,

      isUserRoleEmployee: [
        "superadmin",
        "hr_director",
        "campus_hr",
        "campus_hr_staff",
      ].includes(this.$page.props.auth.roles[0]),

      v$: useVuelidate(),
      personalInformationForm: useForm({
        // Basic Information
        firstname: this.employeePersonalInformation.data?.firstname || null,
        middlename: this.employeePersonalInformation.data?.middlename || null,
        lastname: this.employeePersonalInformation.data?.lastname || null,
        suffix: this.employeePersonalInformation.data?.suffix || null,
        date_of_birth:
          this.employeePersonalInformation.data?.date_of_birth || null,
        place_of_birth:
          this.employeePersonalInformation.data?.place_of_birth || null,
        sex: this.employeePersonalInformation.data?.sex || null,
        civil_status:
          this.employeePersonalInformation.data?.civil_status || null,
        otherCivilStatus: "",
        height: this.employeePersonalInformation.data?.height || null,
        weight: this.employeePersonalInformation.data?.weight || null,
        blood_type: this.employeePersonalInformation.data?.blood_type || null,

        // Citizenship
        citizenship: this.employeePersonalInformation.data?.citizenship || null,
        dual_citizenship_type:
          this.employeePersonalInformation.data?.dual_citizenship_type || null,
        dual_citizenship_country:
          this.employeePersonalInformation.data?.dual_citizenship_country ||
          null,

        // Residential Address
        residential_province:
          this.employeePersonalInformation.data?.residential_province || null,
        residential_city_municipality:
          this.employeePersonalInformation.data
            ?.residential_city_municipality || null,
        residential_barangay:
          this.employeePersonalInformation.data?.residential_barangay || null,
        residential_subdivision:
          this.employeePersonalInformation.data?.residential_subdivision ||
          null,
        residential_street:
          this.employeePersonalInformation.data?.residential_street || null,
        residential_house_no:
          this.employeePersonalInformation.data?.residential_house_no || null,
        residential_zip_code:
          this.employeePersonalInformation.data?.residential_zip_code || null,

        // Permanent Address
        permanent_province:
          this.employeePersonalInformation.data?.permanent_province || null,
        permanent_city_municipality:
          this.employeePersonalInformation.data?.permanent_city_municipality ||
          null,
        permanent_barangay:
          this.employeePersonalInformation.data?.permanent_barangay || null,
        permanent_subdivision:
          this.employeePersonalInformation.data?.permanent_subdivision || null,
        permanent_street:
          this.employeePersonalInformation.data?.permanent_street || null,
        permanent_house_no:
          this.employeePersonalInformation.data?.permanent_house_no || null,
        permanent_zip_code:
          this.employeePersonalInformation.data?.permanent_zip_code || null,
        same_as_residential_address: false,

        telephone_no:
          this.employeePersonalInformation.data?.telephone_no || null,
        mobile_no: this.employeePersonalInformation.data?.mobile_no || null,
        email: this.employeePersonalInformation.data?.email || null,
        gsis_id_no: this.employeePersonalInformation.data?.gsis_id_no || null,
        pag_ibig_id_no:
          this.employeePersonalInformation.data?.pag_ibig_id_no || null,
        philhealth_id_no:
          this.employeePersonalInformation.data?.philhealth_id_no || null,
        sss_id_no: this.employeePersonalInformation.data?.sss_id_no || null,
        tin_id_no: this.employeePersonalInformation.data?.tin_id_no || null,
        agencyIdNo: null,
        e_signature_path:
          this.employeePersonalInformation.data?.e_signature_path || null,
      }),

      signature: null,
      signatureDataUrl: null,
      showSignatureDialog: false,

      psaFormData: useForm({
        verificationType: "psa",
        employee_id: this.employeePersonalInformation.data?.id || null,
        fname: this.employeePersonalInformation.data?.firstname || null,
        mname: this.employeePersonalInformation.data?.middlename || null,
        lname: this.employeePersonalInformation.data?.lastname || null,
        suffix: this.employeePersonalInformation.data?.suffix || null,
        birthdate: this.employeePersonalInformation.data?.date_of_birth || null,
        is_update: true,
      }),

      pcnFormData: useForm({
        verificationType: "pcn",
        employee_id: this.employeePersonalInformation.data?.id || null,
        id_number: null,
        is_update: true,
        face_liveness_session_id: null,
      }),

      sex: ["Male", "Female"],
      civilStatus: ["Single", "Married", "Widowed", "Separated", "Other/s"],
      citizenship: ["Filipino", "Foreigner"],

      bloodType: ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"],

      countries: country.all(),

      residential_provinces: province.all(),
      residential_municipalities: municipality.all(),
      residential_barangays: barangays.all(),

      permanent_provinces: province.all(),
      permanent_city_municipalities: municipality.all(),
      permanent_barangays: barangays.all(),

      residentialProvince: "",
      residentialMunicipality: "",
      residentialBarangay: "",
      permanentProvince: "",
      permanentMunicipality: "",
      permanentBarangay: "",

      // PSA Verification
      response: null,
      psaLivenessSessionId: null,

      psaLoadedData: {
        firstname: null,
        middlename: null,
        lastname: null,
        suffix: null,
        date_of_birth: null,
        sex: null,
        civil_status: null,
        blood_type: null,
        place_of_birth: null,
        mobile_no: null,
        residential_province: null,
        residential_city_municipality: null,
        residential_barangay: null,
        residential_zip_code: null,
        email: null,
      },
    };
  },

  mounted() {
    // Set dualCitizenship fields empty if citizenship is Filipino
    if (this.personalInformationForm.citizenship === "filipino") {
      this.personalInformationForm.dual_citizenship_type = null;
      this.personalInformationForm.dual_citizenship_country = null;
    }

    this.prefillResidentialAddress();
    this.prefillPermanentAddress();

    const scriptUrl =
      "https://liveness.everify.gov.ph/js/everify-liveness-sdk.min.js";

    if (!document.querySelector(`script[src="${scriptUrl}"]`)) {
      const script = document.createElement("script");
      script.src = scriptUrl;
      script.onload = () => {
        console.log("eKYC script loaded");
      };
      script.onerror = () => {
        console.error("Failed to load eKYC script");
      };
      document.body.appendChild(script);
    }
  },

  watch: {
    "personalInformationForm.citizenship"(newVal) {
      if (newVal === "filipino") {
        this.personalInformationForm.dual_citizenship_type = null;
        this.personalInformationForm.dual_citizenship_country = null;
      }
    },

    // Watch for changes in residential address and copy to permanent if checkbox is checked
    "personalInformationForm.residential_province"() {
      if (this.personalInformationForm.same_as_residential_address) {
        this.personalInformationForm.permanent_province =
          this.personalInformationForm.residential_province;
      }
    },
    "personalInformationForm.residential_city_municipality"() {
      if (this.personalInformationForm.same_as_residential_address) {
        this.personalInformationForm.permanent_city_municipality =
          this.personalInformationForm.residential_city_municipality;
        this.personalInformationForm.permanent_zip_code = this
          .personalInformationForm.residential_city_municipality
          ? this.personalInformationForm.residential_city_municipality.zip_code
          : null;
      }
    },
    "personalInformationForm.residential_barangay"() {
      if (this.personalInformationForm.same_as_residential_address) {
        this.personalInformationForm.permanent_barangay =
          this.personalInformationForm.residential_barangay;
      }
    },
    "personalInformationForm.residential_subdivision"() {
      if (this.personalInformationForm.same_as_residential_address) {
        this.personalInformationForm.permanent_subdivision =
          this.personalInformationForm.residential_subdivision;
      }
    },
    "personalInformationForm.residential_street"() {
      if (this.personalInformationForm.same_as_residential_address) {
        this.personalInformationForm.permanent_street =
          this.personalInformationForm.residential_street;
      }
    },
    "personalInformationForm.residential_house_no"() {
      if (this.personalInformationForm.same_as_residential_address) {
        this.personalInformationForm.permanent_house_no =
          this.personalInformationForm.residential_house_no;
      }
    },
  },

  validations: {
    personalInformationForm: {
      firstname: { required },
      middlename: { minLength: minLength(0) },
      lastname: { required },
      suffix: { minLength: minLength(0) },
      date_of_birth: { minLength: minLength(0) },
      place_of_birth: { minLength: minLength(0) },
      sex: { minLength: minLength(0) },
      civil_status: { minLength: minLength(0) },
      otherCivilStatus: { minLength: minLength(0) },
      blood_type: { minLength: minLength(0) },

      citizenship: { minLength: minLength(0) },
      dual_citizenship_type: { minLength: minLength(0) },
      dual_citizenship_country: { minLength: minLength(0) },

      residential_province: { minLength: minLength(0) },
      residential_city_municipality: { minLength: minLength(0) },
      residential_barangay: { minLength: minLength(0) },
      residential_subdivision: { minLength: minLength(0) },
      residential_street: { minLength: minLength(0) },
      residential_house_no: { minLength: minLength(0) },
      residential_zip_code: { minLength: minLength(0) },

      permanent_province: { minLength: minLength(0) },
      permanent_city_municipality: { minLength: minLength(0) },
      permanent_barangay: { minLength: minLength(0) },
      permanent_subdivision: { minLength: minLength(0) },
      permanent_street: { minLength: minLength(0) },
      permanent_house_no: { minLength: minLength(0) },
      permanent_zip_code: { minLength: minLength(0) },
      telephone_no: { minLength: minLength(0), numeric: numeric },
      mobile_no: { numeric: numeric },
      email: { email },
      gsis_id_no: { minLength: minLength(0), numeric: numeric },
      pag_ibig_id_no: { minLength: minLength(0), numeric: numeric },
      philhealth_id_no: { minLength: minLength(0), numeric: numeric },
      sss_id_no: { minLength: minLength(0), numeric: numeric },
      tin_id_no: { minLength: minLength(0), numeric: numeric },
      agencyIdNo: { minLength: minLength(0), numeric: numeric },
    },

    psaFormData: {
      employee_id: { required },
      fname: { required },
      mname: { minLength: minLength(0) },
      lname: { required },
      suffix: { minLength: minLength(0) },
      birthdate: { required },
    },

    pcnFormData: {
      employee_id: { required },
      id_number: { required, minLength: minLength(5) },
      face_liveness_session_id: { required },
    },
  },

  computed: {
    isMyProfileEditable() {
      return !editMode &&
        route().current().startsWith("self-service.") &&
        !route().current().startsWith("hrmanagement.")
        ? true
        : false;
    },

    isEditMode() {
      if (this.editMode !== null && this.editMode !== undefined) {
        return this.editMode;
      }
      return this.$page?.props?.auth?.roles?.[0] !== "employee";
    },

    residential_filtered_municipalities() {
      /* if current model of province is not null */
      // this.personalInformationForm.residentialMunicipality = null;
      if (this.personalInformationForm.residential_province) {
        return province.getMunicipalities(
          this.personalInformationForm.residential_province.code
        );
      }
      return [];
    },

    residential_filtered_barangay() {
      if (this.personalInformationForm.residential_city_municipality) {
        return municipality.getBarangays(
          this.personalInformationForm.residential_city_municipality.code
        );
      }
      return [];
    },

    permanent_filtered_municipalities() {
      /* if current model of province is not null */
      // this.personalInformationForm.residentialMunicipality = null;
      if (this.personalInformationForm.permanent_province) {
        return province.getMunicipalities(
          this.personalInformationForm.permanent_province.code
        );
      }
      return [];
    },

    permanent_filtered_barangay() {
      if (this.personalInformationForm.permanent_city_municipality) {
        return municipality.getBarangays(
          this.personalInformationForm.permanent_city_municipality.code
        );
      }
      return [];
    },

    canProceedWithVerification() {
      if (!this.psaVerificationMethod) {
        return false;
      }
      if (this.psaVerificationMethod === "pcn") {
        return (
          this.pcnFormData.id_number && this.pcnFormData.id_number.length >= 5
        );
      }
      // For basic method, check if required fields are filled
      return (
        this.personalInformationForm.firstname &&
        this.personalInformationForm.lastname &&
        this.personalInformationForm.date_of_birth
      );
    },
  },

  methods: {
    // Prefill residential address fields
    prefillResidentialAddress() {
      // Residential Address Prefill
      // First, find and set the province object
      if (this.employeePersonalInformation.data?.residential_province) {
        const foundProvince = this.residential_provinces.find(
          (province) =>
            province.name ===
            this.employeePersonalInformation.data?.residential_province
        );

        if (foundProvince) {
          this.personalInformationForm.residential_province = foundProvince;
        }
      }

      // Then, find the city/municipality from the filtered list based on province
      if (
        this.employeePersonalInformation.data?.residential_city_municipality
      ) {
        // Get municipalities filtered by the selected province
        const filteredMunicipalities = this.personalInformationForm
          .residential_province
          ? province.getMunicipalities(
              this.personalInformationForm.residential_province.code
            )
          : this.residential_municipalities;

        const foundMunicipality = filteredMunicipalities.find(
          (town) =>
            town.name ===
            this.employeePersonalInformation.data?.residential_city_municipality
        );

        if (foundMunicipality) {
          this.personalInformationForm.residential_city_municipality =
            foundMunicipality;
        }
      }

      // Finally, find the barangay from the filtered list based on city/municipality
      if (this.employeePersonalInformation.data?.residential_barangay) {
        // Get barangays filtered by the selected city/municipality
        const filteredBarangays = this.personalInformationForm
          .residential_city_municipality
          ? municipality.getBarangays(
              this.personalInformationForm.residential_city_municipality.code
            )
          : this.residential_barangays;

        const foundBarangay = filteredBarangays.find(
          (barangay) =>
            barangay.name ===
            this.employeePersonalInformation.data?.residential_barangay
        );

        if (foundBarangay) {
          this.personalInformationForm.residential_barangay = foundBarangay;
        }
      }
    },

    // Prefill permanent address fields
    prefillPermanentAddress() {
      // Permanent Address Prefill
      // First, find and set the province object
      if (this.employeePersonalInformation.data?.permanent_province) {
        const foundProvince = this.permanent_provinces.find(
          (province) =>
            province.name ===
            this.employeePersonalInformation.data?.permanent_province
        );

        if (foundProvince) {
          this.personalInformationForm.permanent_province = foundProvince;
        }
      }

      // Then, find the city/municipality from the filtered list based on province
      if (this.employeePersonalInformation.data?.permanent_city_municipality) {
        // Get municipalities filtered by the selected province
        const filteredMunicipalities = this.personalInformationForm
          .permanent_province
          ? province.getMunicipalities(
              this.personalInformationForm.permanent_province.code
            )
          : this.permanent_city_municipalities;

        const foundMunicipality = filteredMunicipalities.find(
          (town) =>
            town.name ===
            this.employeePersonalInformation.data?.permanent_city_municipality
        );

        if (foundMunicipality) {
          this.personalInformationForm.permanent_city_municipality =
            foundMunicipality;
        }
      }

      // Finally, find the barangay from the filtered list based on city/municipality
      if (this.employeePersonalInformation.data?.permanent_barangay) {
        // Get barangays filtered by the selected city/municipality
        const filteredBarangays = this.personalInformationForm
          .permanent_city_municipality
          ? municipality.getBarangays(
              this.personalInformationForm.permanent_city_municipality.code
            )
          : this.permanent_barangays;

        const foundBarangay = filteredBarangays.find(
          (barangay) =>
            barangay.name ===
            this.employeePersonalInformation.data?.permanent_barangay
        );

        if (foundBarangay) {
          this.personalInformationForm.permanent_barangay = foundBarangay;
        }
      }
    },

    submitPersonalInformationForm() {
      this.v$.personalInformationForm.$touch();

      //   console.log(this.personalInformationForm);

      if (this.v$.personalInformationForm.$invalid) {
        return;
      }

      if (this.personalInformationForm.residential_province !== null) {
        this.personalInformationForm.residential_zip_code =
          this.personalInformationForm.residential_city_municipality.zip_code;
        // Convert the selected object to its name value before submitting
        this.personalInformationForm.residential_province =
          this.personalInformationForm.residential_province.name;
        this.personalInformationForm.residential_city_municipality =
          this.personalInformationForm.residential_city_municipality.name;
        this.personalInformationForm.residential_barangay =
          this.personalInformationForm.residential_barangay.name;
      }

      if (this.personalInformationForm.permanent_province !== null) {
        this.personalInformationForm.permanent_zip_code =
          this.personalInformationForm.permanent_city_municipality.zip_code;
        // Convert the selected object to its name value before submitting
        this.personalInformationForm.permanent_province =
          this.personalInformationForm.permanent_province.name;
        this.personalInformationForm.permanent_city_municipality =
          this.personalInformationForm.permanent_city_municipality.name;
        this.personalInformationForm.permanent_barangay =
          this.personalInformationForm.permanent_barangay.name;
      }

      if (route().current().startsWith("self-service.")) {
        this.personalInformationForm.put(
          route("self-service.my-profile.updatePersonalInformation", {
            id: this.employeePersonalInformation.data.id,
          }),
          {
            preserveScroll: true,
            onSuccess: () => {
              this.showToast(
                "Personal Information updated successfully",
                "success"
              );
              this.prefillResidentialAddress();
              this.prefillPermanentAddress();
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");

              this.showToast(`${errorMessages}`, "error");
              this.prefillResidentialAddress();
              this.prefillPermanentAddress();
            },
          }
        );
      } else {
        this.personalInformationForm.put(
          route("hrmanagement.employee.updatePersonalInformation", {
            id: this.employeePersonalInformation.data.id,
          }),
          {
            preserveScroll: true,
            onSuccess: () => {
              this.showToast(
                "Personal Information updated successfully",
                "success"
              );
              this.prefillResidentialAddress();
              this.prefillPermanentAddress();
            },
            onError: (errors) => {
              const errorMessages = Object.values(errors).flat().join(" ");

              this.showToast(`${errorMessages}`, "error");
              this.prefillResidentialAddress();
              this.prefillPermanentAddress();
            },
          }
        );
      }
    },

    async uploadSignature() {
      if (!this.signature) {
        this.showToast("Please select a PNG file first.", "error");
        return;
      }

      this.uploading = true;
      this.uploadProgress = 0;
      this.uploadedSize = 0;
      this.totalSize = this.signature.size;

      const formData = new FormData();
      formData.append("signature", this.signature);
      formData.append("employee_id", this.employeePersonalInformation.data.id);

      // Optional: simulated upload progress
      const progressInterval = setInterval(() => {
        if (this.uploadProgress < 90) {
          this.uploadProgress += Math.random() * 30;
          this.uploadedSize = (this.uploadProgress / 100) * this.totalSize;
        }
      }, 200);

      try {
        // Choose route based on current context
        const uploadRoute = route().current().startsWith("self-service.")
          ? route("self-service.my-profile.uploadSignature")
          : route("hrmanagement.employee.uploadSignature");

        this.$inertia.post(uploadRoute, formData, {
          preserveState: true,
          preserveScroll: true,
          onSuccess: (res) => {
            clearInterval(progressInterval);

            this.showToast("Signature uploaded successfully!", "success");

            // Save backend path in form
            this.signature = res.props.path;

            // Reset dialog and preview
            this.showSignatureDialog = false;
            this.signatureDataUrl = null;
            this.selectedSignatureFile = null;
            this.uploading = false;
            this.uploadProgress = 100;
            this.uploadedSize = this.totalSize;
          },
          onError: (errors) => {
            clearInterval(progressInterval);
            this.uploading = false;
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        });
      } catch (error) {
        clearInterval(progressInterval);
        this.uploading = false;
        this.showToast("Failed to upload signature.", "error");
        console.error(error);
      }
    },

    openPSAVerificationDialog(method = "basic") {
      this.psaVerificationMethod = method;
      this.psaPcnDnNumber = null;
      this.isPSAVerificationDialog = true;
    },

    handleCitizenship(selection) {
      if (selection === "Filipino") {
        this.personalInformationForm.citizenship = "Filipino";
      } else {
        this.personalInformationForm.citizenship = false;
        this.personalInformationForm.dualCitizenship = true;
      }
    },

    handleDualCitizenship(selection) {
      if (selection === "By Birth") {
        this.personalInformationForm.dualCitizenshipByBirth = true;
        this.personalInformationForm.dualCitizenshipByNaturalization = false;
      } else {
        this.personalInformationForm.dualCitizenshipByBirth = false;
        this.personalInformationForm.dualCitizenshipByNaturalization = true;
      }
    },

    handleSameAsResidentialAddress(checked) {
      if (checked) {
        // Copy residential address to permanent address
        this.personalInformationForm.permanent_province =
          this.personalInformationForm.residential_province;
        this.personalInformationForm.permanent_city_municipality =
          this.personalInformationForm.residential_city_municipality;
        this.personalInformationForm.permanent_barangay =
          this.personalInformationForm.residential_barangay;
        this.personalInformationForm.permanent_subdivision =
          this.personalInformationForm.residential_subdivision;
        this.personalInformationForm.permanent_street =
          this.personalInformationForm.residential_street;
        this.personalInformationForm.permanent_house_no =
          this.personalInformationForm.residential_house_no;
        this.personalInformationForm.permanent_zip_code = this
          .personalInformationForm.residential_city_municipality
          ? this.personalInformationForm.residential_city_municipality.zip_code
          : null;
      }
    },

    // Sync psaFormData with current personalInformationForm values
    syncPsaFormData() {
      this.psaFormData.employee_id =
        this.employeePersonalInformation.data?.id || null;
      this.psaFormData.fname = this.personalInformationForm.firstname || null;
      this.psaFormData.mname = this.personalInformationForm.middlename || null;
      this.psaFormData.lname = this.personalInformationForm.lastname || null;
      this.psaFormData.suffix = this.personalInformationForm.suffix || null;
      this.psaFormData.birthdate =
        this.personalInformationForm.date_of_birth || null;
    },

    // Validate psaFormData before starting liveness
    validatePsaFormData() {
      // Sync form data first
      this.syncPsaFormData();

      // Validate using vuelidate
      this.v$.psaFormData.$touch();

      // Check if validation passed
      if (this.v$.psaFormData.$invalid) {
        const errors = [];
        if (this.v$.psaFormData.employee_id.$invalid) {
          errors.push("Employee ID is required");
        }
        if (this.v$.psaFormData.fname.$invalid) {
          errors.push("First name is required");
        }
        if (this.v$.psaFormData.lname.$invalid) {
          errors.push("Last name is required");
        }
        if (this.v$.psaFormData.birthdate.$invalid) {
          errors.push("Date of birth is required");
        }

        const errorMessage =
          errors.length > 0
            ? `Please fill in the following required fields: ${errors.join(
                ", "
              )}`
            : "Please fill in all required fields for PSA verification";

        if (typeof this.showToast === "function") {
          this.showToast(errorMessage, "error");
        } else {
          console.error("PSA Form Validation Error:", errorMessage);
          alert(errorMessage);
        }
        return false;
      }

      // Check if PSA public API key is available
      if (!this.$page.props.psa_public_api_key) {
        const errorMessage =
          "PSA public API key is not configured. Please contact administrator.";
        if (typeof this.showToast === "function") {
          this.showToast(errorMessage, "error");
        } else {
          console.error("PSA Configuration Error:", errorMessage);
          alert(errorMessage);
        }
        return false;
      }

      return true;
    },

    closePSAVerificationDialog() {
      this.isPSAVerificationDialog = false;
      // Reset form state
      this.psaVerificationMethod = "basic";
      this.psaPcnDnNumber = null;
    },

    proceedWithPSAVerification() {
      if (this.psaVerificationMethod === "pcn") {
        // Handle PCN/DNIDN verification
        if (!this.pcnFormData.id_number || this.pcnFormData.id_number < 5) {
          if (typeof this.showToast === "function") {
            this.showToast(
              "Please enter a valid Nat'l ID No or Digital Nat'l ID No",
              "error"
            );
          } else {
            this.showToast(
              "Please enter a valid Nat'l ID No or Digital Nat'l ID No",
              "error"
            );
          }
          return;
        }
        // Set PCN form data and start liveness
        // this.pcnFormData.id_number = this.psaPcnDnNumber;
        this.startLiveness("pcn");
      } else {
        // Handle basic information verification
        if (!this.validatePsaFormData()) {
          return;
        }
        this.startLiveness("psa");
      }
    },

    startLiveness(type) {
      // Check if eKYC script is loaded
      if (typeof window.eKYC !== "function") {
        const errorMessage =
          "eKYC is not available yet, script not loaded. Please refresh the page and try again.";
        if (typeof this.showToast === "function") {
          this.showToast(errorMessage, "error");
        } else {
          console.error(errorMessage);
          alert(errorMessage);
        }
        return;
      }

      // Close the dialog
      this.isPSAVerificationDialog = false;

      // Start liveness verification
      window
        .eKYC()
        .start({
          pubKey: this.$page.props.psa_public_api_key,
        })
        .then((data) => {
          this.response = data;
          // console.log("Liveness data", data.result.session_id);
          this.psaLivenessSessionId = data.result.session_id;

          if (type === "pcn") {
            this.pcnFormData.face_liveness_session_id = data.result.session_id;
            this.submitPcnForm();
          } else {
            this.submitPsaForm();
          }
        })
        .catch((err) => {
          console.error("Liveness verification error", err);
          const errorMessage =
            "Failed to complete liveness verification. Please try again.";
          if (typeof this.showToast === "function") {
            this.showToast(errorMessage, "error");
          } else {
            alert(errorMessage);
          }
          // Reopen dialog on error
          this.isPSAVerificationDialog = true;
          if (type === "pcn") {
            this.psaVerificationMethod = "pcn";
          }
        });
    },

    //PCN FORM
    submitPcnForm() {
      this.v$.pcnFormData.$touch();
      console.log(this.pcnFormData);
      this.$inertia.post(
        route("hrmanagement.employee.psa.verify"),
        {
          ...this.pcnFormData,
        },
        {
          onSuccess: () => {
            // Load data from backend if submit is success
            const data = this.$page.props.flash.psaLoadedData;

            if (data) {
              // Map backend keys to frontend keys
              this.psaLoadedData.firstname = data.first_name;
              this.psaLoadedData.middlename = data.middle_name;
              this.psaLoadedData.lastname = data.last_name;
              this.psaLoadedData.suffix = data.suffix;
              this.psaLoadedData.date_of_birth = data.birth_date;
              this.psaLoadedData.sex = data.sex;
              this.psaLoadedData.civil_status = data.marital_status;
              this.psaLoadedData.blood_type = data.blood_type;
              this.psaLoadedData.place_of_birth = data.place_of_birth;
              this.psaLoadedData.mobile_no = data.mobile_number;
              this.psaLoadedData.residential_province = data.province;
              this.psaLoadedData.residential_city_municipality =
                data.municipality;
              this.psaLoadedData.residential_barangay = data.barangay;
              this.psaLoadedData.residential_zip_code = data.postal_code;
              this.psaLoadedData.email = data.email;
            }
            this.isSaveAuthorizationDialogVisible = true;
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },
    // PSA Form Submission
    submitPsaForm() {
      // Map form data to backend expected format
      const formData = {
        employee_id: this.psaFormData.employee_id,
        firstName: this.psaFormData.fname,
        middleName: this.psaFormData.mname || "",
        lastName: this.psaFormData.lname,
        suffix: this.psaFormData.suffix || "",
        birthdate: this.psaFormData.birthdate,
        is_update: this.psaFormData.is_update,
        face_liveness_session_id: this.psaLivenessSessionId,
        verificationType: "psa",
      };

      this.$inertia.post(route("hrmanagement.employee.psa.verify"), formData, {
        onSuccess: () => {
          // Load data from backend if submit is success
          const data = this.$page.props.flash.psaLoadedData;

          if (data) {
            // Map backend keys to frontend keys
            this.psaLoadedData.firstname = data.first_name;
            this.psaLoadedData.middlename = data.middle_name;
            this.psaLoadedData.lastname = data.last_name;
            this.psaLoadedData.suffix = data.suffix;
            this.psaLoadedData.date_of_birth = data.birth_date;
            this.psaLoadedData.sex = data.sex;
            this.psaLoadedData.civil_status = data.marital_status;
            this.psaLoadedData.blood_type = data.blood_type;
            this.psaLoadedData.place_of_birth = data.place_of_birth;
            this.psaLoadedData.mobile_no = data.mobile_number;
            this.psaLoadedData.residential_province = data.province;
            this.psaLoadedData.residential_city_municipality =
              data.municipality;
            this.psaLoadedData.residential_barangay = data.barangay;
            this.psaLoadedData.residential_zip_code = data.postal_code;
            this.psaLoadedData.email = data.email;
          }
          this.isSaveAuthorizationDialogVisible = true;
        },
        onError: (errors) => {
          const errorMessages = Object.values(errors).flat().join(" ");
          this.showToast(`${errorMessages}`, "error");
          // Reopen dialog on error so user can try again
          // this.isPSAVerificationDialog = true;
        },
      });
    },

    continueSaving() {
      /* Add Route Here for storing fetch data */
      this.$inertia.put(
        route("self-service.my-profile.updatePersonalInformationFromPsa", {
          id: this.employeePersonalInformation.data.id,
        }),
        {
          ...this.psaLoadedData,
        },
        {
          onSuccess: () => {
            this.isSaveAuthorizationDialogVisible = false;
            this.$inertia.reload({
              only: ["employeePersonalInformation"],
              preserveState: true,
              preserveScroll: true,
              onSuccess: (page) => {
                // Re-bind local form to updated props
                this.personalInformationForm = {
                  ...page.props.employeePersonalInformation.data,
                };
              },
            });
            this.showToast(
              "Employee successfully updated with PSA data.",
              "success"
            );
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        }
      );
    },

    handleSignatureUpload(file) {
      const selectedFile = Array.isArray(file) ? file[0] : file;

      if (!selectedFile) return;

      if (selectedFile.type !== "image/png") {
        this.showToast("Only PNG files are allowed.", "error");
        return;
      }

      if (selectedFile.size > 5 * 1024 * 1024) {
        alert("Maximum file size is 5MB.");
        return;
      }

      // Save actual file for backend upload
      this.signature = selectedFile;

      // Preview
      const reader = new FileReader();
      reader.onload = (e) => {
        this.signatureDataUrl = e.target.result;
      };
      reader.readAsDataURL(selectedFile);
    },

    removeSignature() {
      this.signature = null;
      this.signatureDataUrl = null;
      this.$refs.signatureFileInput.reset();
    },
  },
};
</script>
