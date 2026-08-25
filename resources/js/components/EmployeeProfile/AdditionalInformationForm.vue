<template>
  <v-form @submit.prevent="handleSubmitAdditionalInformation()" >
    <v-row>
      <v-col cols="12">
        <v-card-title class="d-flex align-center justify-space-between">
          Additional Information
        </v-card-title>
      </v-col>
      <v-col cols="12" class="ml-4">
        <v-row class="mr-3">
          <v-col cols="12">
            <v-row>
              <v-col cols="12" md="6">
                <p class="mb-0 text-wrap">Are you related by consanguinity or affinity to the appointing or recommending authority, or to the			
                chief of bureau or office or to the person who has immediate supervision over you in the Office, 			
                Bureau or Department where you will be apppointed</p>
              </v-col>
              <v-col cols="12" md="6">

              </v-col>
              <v-col cols="12" md="6">
                a. within the third degree?
              </v-col>
              <v-col cols="12" md="6">
                <v-row>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="Yes" 
                      v-model="additionalInformationForm.consanguinityThirdDegree.yes" 
                      :disabled="isFormEditable"
                      @change="handleConsanguinityThirdDegree('yes')"
                      :error-messages="v$.additionalInformationForm.consanguinityThirdDegree.yes.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="No" 
                      v-model="additionalInformationForm.consanguinityThirdDegree.no" 
                      :disabled="isFormEditable"
                      @change="handleConsanguinityThirdDegree('no')"
                      :error-messages="v$.additionalInformationForm.consanguinityThirdDegree.no.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" md="6">
                b. within the fourth degree?
              </v-col>
              <v-col cols="12" md="6">
                <v-row>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="Yes" 
                      v-model="additionalInformationForm.consanguinityFourthDegree.yes" 
                      :disabled="isFormEditable"
                      @change="handleConsanguinityFourthDegree('yes')"
                      :error-messages="v$.additionalInformationForm.consanguinityFourthDegree.yes.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="No" 
                      v-model="additionalInformationForm.consanguinityFourthDegree.no" 
                      :disabled="isFormEditable"
                      @change="handleConsanguinityFourthDegree('no')"
                      :error-messages="v$.additionalInformationForm.consanguinityFourthDegree.no.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" v-if="additionalInformationForm.consanguinityThirdDegree.yes || additionalInformationForm.consanguinityFourthDegree.yes">
                <v-row>
                  <v-col cols="12" md="6">
                    
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
                      density="compact"
                      variant="outlined"
                      label="If yes, give the details"
                      :model-value="additionalInformationForm.consanguinityDetails"
                      @update:model-value="handleTextInput('consanguinityDetails', $event, 'sanitizeDetails')"
                      :disabled="isFormEditable"
                      :error-messages="v$.additionalInformationForm.consanguinityDetails.$errors?.map(e => e.$message) || []"
                    ></v-text-field>
                  </v-col>
                </v-row>
                
              </v-col>
            </v-row>

          </v-col>
          <v-col cols="12">
            <v-row>
              <v-col cols="12" md="6">
                <p>Have you ever been found guilty of any administrative offense?</p>
              </v-col>
              <v-col cols="12" md="6">
                <v-row>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="Yes" 
                      v-model="additionalInformationForm.administrativeOffense.yes" 
                      :disabled="isFormEditable"
                      @change="handleAdministrativeOffense('yes')"
                      :error-messages="v$.additionalInformationForm.administrativeOffense.yes.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="No" 
                      v-model="additionalInformationForm.administrativeOffense.no" 
                      :disabled="isFormEditable"
                      @change="handleAdministrativeOffense('no')"
                      :error-messages="v$.additionalInformationForm.administrativeOffense.no.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" v-if="additionalInformationForm.administrativeOffense.yes">
                <v-row>
                  <v-col cols="12" md="6">
                    
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
                      density="compact"
                      variant="outlined"
                      label="If yes, give the details"
                      :model-value="additionalInformationForm.administrativeOffenseDetails"
                      @update:model-value="handleTextInput('administrativeOffenseDetails', $event, 'sanitizeDetails')"
                      :disabled="isFormEditable"
                      :error-messages="v$.additionalInformationForm.administrativeOffenseDetails.$errors?.map(e => e.$message) || []"
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col>
            </v-row>
          </v-col>
          <v-col cols="12">
            <v-row>
              <v-col cols="12" md="6">
                <p>Have you been criminally charged before any court?</p>
              </v-col>
              <v-col cols="12" md="6">
                <v-row>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="Yes" 
                      v-model="additionalInformationForm.criminalCharge.yes" 
                      :disabled="isFormEditable"
                      @change="handleCriminalCharge('yes')"
                      :error-messages="v$.additionalInformationForm.criminalCharge.yes.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="No" 
                      v-model="additionalInformationForm.criminalCharge.no" 
                      :disabled="isFormEditable"
                      @change="handleCriminalCharge('no')"
                      :error-messages="v$.additionalInformationForm.criminalCharge.no.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" v-if="additionalInformationForm.criminalCharge.yes">
                <v-row>
                  <v-col cols="12" md="6">
                  </v-col>
                  <v-col cols="12" md="6">
                    <p>If YES, give details:</p>
                  </v-col>
                </v-row>
                <v-row>
                  <v-col cols="12" md="6">
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
                      density="compact"
                      variant="outlined"
                      label="Date Filed"
                      type="date"
                      v-model="additionalInformationForm.criminalChargeDateFiled"
                      :disabled="isFormEditable"
                      :error-messages="v$.additionalInformationForm.criminalChargeDateFiled.$errors?.map(e => e.$message) || []"
                    ></v-text-field>
                  </v-col>
                  <v-col cols="12" md="6">
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
                      density="compact"
                      variant="outlined"
                      label="Status of Case/s"
                      :model-value="additionalInformationForm.criminalChargeStatus"
                      @update:model-value="handleTextInput('criminalChargeStatus', $event, 'sanitizeDetails')"
                      :disabled="isFormEditable"
                      :error-messages="v$.additionalInformationForm.criminalChargeStatus.$errors?.map(e => e.$message) || []"
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col>
            </v-row>
          </v-col>
          <v-col cols="12">
            <v-row>
              <v-col cols="12" md="6">
                <p>Have you ever been convicted of any crime or violation of any law, decree, ordinance or regulation by any court or tribunal?</p>
              </v-col>
              <v-col cols="12" md="6">
                <v-row>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="Yes" 
                      v-model="additionalInformationForm.conviction.yes" 
                      :disabled="isFormEditable"
                      @change="handleConviction('yes')"
                      :error-messages="v$.additionalInformationForm.conviction.yes.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="No" 
                      v-model="additionalInformationForm.conviction.no" 
                      :disabled="isFormEditable"
                      @change="handleConviction('no')"
                      :error-messages="v$.additionalInformationForm.conviction.no.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" v-if="additionalInformationForm.conviction.yes">
                <v-row>
                  <v-col cols="12" md="6">
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
                      density="compact"
                      variant="outlined"
                      label="If yes, give details"
                      :model-value="additionalInformationForm.convictionDetails"
                      @update:model-value="handleTextInput('convictionDetails', $event, 'sanitizeDetails')"
                      :disabled="isFormEditable"
                      :error-messages="v$.additionalInformationForm.convictionDetails.$errors?.map(e => e.$message) || []"
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col>
            </v-row>
          </v-col>
          <v-col cols="12">
            <v-row>
              <v-col cols="12" md="6">
                <p>Have you ever been separated from the service in any of the following modes: resignation, retirement, dropped from the rolls, dismissal, termination, end of term, finished contract or phased out (abolition) in the public or private sector?</p>
              </v-col>
              <v-col cols="12" md="6">
                <v-row>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="Yes" 
                      v-model="additionalInformationForm.separationFromService.yes" 
                      :disabled="isFormEditable"
                      @change="handleSeparationFromService('yes')"
                      :error-messages="v$.additionalInformationForm.separationFromService.yes.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="No" 
                      v-model="additionalInformationForm.separationFromService.no" 
                      :disabled="isFormEditable"
                      @change="handleSeparationFromService('no')"
                      :error-messages="v$.additionalInformationForm.separationFromService.no.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" v-if="additionalInformationForm.separationFromService.yes">
                <v-row>
                  <v-col cols="12" md="6">
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
                      density="compact"
                      variant="outlined"
                      label="If yes, give details"
                      :model-value="additionalInformationForm.separationFromServiceDetails"
                      @update:model-value="handleTextInput('separationFromServiceDetails', $event, 'sanitizeDetails')"
                      :disabled="isFormEditable"
                      :error-messages="v$.additionalInformationForm.separationFromServiceDetails.$errors?.map(e => e.$message) || []"
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col>
            </v-row>
          </v-col>
          <v-col cols="12">
            <v-row>
              <v-col cols="12" md="6">
                <p>Have you ever been a candidate in a national or local election held within the last year (except Barangay election)?</p>
              </v-col>
              <v-col cols="12" md="6">
                <v-row>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="Yes" 
                      v-model="additionalInformationForm.electionCandidate.yes" 
                      :disabled="isFormEditable"
                      @change="handleElectionCandidate('yes')"
                      :error-messages="v$.additionalInformationForm.electionCandidate.yes.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="No" 
                      v-model="additionalInformationForm.electionCandidate.no" 
                      :disabled="isFormEditable"
                      @change="handleElectionCandidate('no')"
                      :error-messages="v$.additionalInformationForm.electionCandidate.no.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" v-if="additionalInformationForm.electionCandidate.yes">
                <v-row>
                  <v-col cols="12" md="6">
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
                      density="compact"
                      variant="outlined"
                      label="If yes, give details"
                      :model-value="additionalInformationForm.electionCandidateDetails"
                      @update:model-value="handleTextInput('electionCandidateDetails', $event, 'sanitizeDetails')"
                      :disabled="isFormEditable"
                      :error-messages="v$.additionalInformationForm.electionCandidateDetails.$errors?.map(e => e.$message) || []"
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col>
            </v-row>
          </v-col>
          <v-col cols="12">
            <v-row>
              <v-col cols="12" md="6">
                <p>Have you resigned from the government service during the three (3)-month period before the last election to promote/actively campaign for a national or local candidate?</p>
              </v-col>
              <v-col cols="12" md="6">
                <v-row>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="Yes" 
                      v-model="additionalInformationForm.resignedForElection.yes" 
                      :disabled="isFormEditable"
                      @change="handleResignedForElection('yes')"
                      :error-messages="v$.additionalInformationForm.resignedForElection.yes.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="No" 
                      v-model="additionalInformationForm.resignedForElection.no" 
                      :disabled="isFormEditable"
                      @change="handleResignedForElection('no')"
                      :error-messages="v$.additionalInformationForm.resignedForElection.no.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" v-if="additionalInformationForm.resignedForElection.yes">
                <v-row>
                  <v-col cols="12" md="6">
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
                      density="compact"
                      variant="outlined"
                      label="If yes, give details"
                      :model-value="additionalInformationForm.resignedForElectionDetails"
                      @update:model-value="handleTextInput('resignedForElectionDetails', $event, 'sanitizeDetails')"
                      :disabled="isFormEditable"
                      :error-messages="v$.additionalInformationForm.resignedForElectionDetails.$errors?.map(e => e.$message) || []"
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col>
            </v-row>
          </v-col>
          <v-col cols="12">
            <v-row>
              <v-col cols="12" md="6">
                <p>Have you acquired the status of an immigrant or permanent resident of another country?</p>
              </v-col>
              <v-col cols="12" md="6">
                <v-row>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="Yes" 
                      v-model="additionalInformationForm.immigrantStatus.yes" 
                      :disabled="isFormEditable"
                      @change="handleImmigrantStatus('yes')"
                      :error-messages="v$.additionalInformationForm.immigrantStatus.yes.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="No" 
                      v-model="additionalInformationForm.immigrantStatus.no" 
                      :disabled="isFormEditable"
                      @change="handleImmigrantStatus('no')"
                      :error-messages="v$.additionalInformationForm.immigrantStatus.no.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" v-if="additionalInformationForm.immigrantStatus.yes">
                <v-row>
                  <v-col cols="12" md="6">
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
                      density="compact"
                      variant="outlined"
                      label="If yes, give details (Country)"
                      :model-value="additionalInformationForm.immigrantStatusCountry"
                      @update:model-value="handleTextInput('immigrantStatusCountry', $event, 'sanitizeCountry')"
                      :disabled="isFormEditable"
                      :error-messages="v$.additionalInformationForm.immigrantStatusCountry.$errors?.map(e => e.$message) || []"
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col>
            </v-row>
          </v-col>

          <v-col cols="12">
            <v-row>
              <v-col cols="12" md="6">
                <p>Pursuant to: (a) Indigenous People's Act (RA 8371); (b) Magna Carta for Disabled Persons (RA 7277); and (c) Solo Parents Welfare Act of 2000 (RA 8972), please answer the following items:</p>
              </v-col>
              <v-col cols="12" md="6"></v-col>

              <v-col cols="12" md="6">
                <p>a. Are you a member of any indigenous group?</p>
              </v-col>
              <v-col cols="12" md="6">
                <v-row>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="Yes" 
                      v-model="additionalInformationForm.indigenousGroup.yes" 
                      :disabled="isFormEditable"
                      @change="handleIndigenousGroup('yes')"
                      :error-messages="v$.additionalInformationForm.indigenousGroup.yes.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="No" 
                      v-model="additionalInformationForm.indigenousGroup.no" 
                      :disabled="isFormEditable"
                      @change="handleIndigenousGroup('no')"
                      :error-messages="v$.additionalInformationForm.indigenousGroup.no.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" md="12" v-if="additionalInformationForm.indigenousGroup.yes">
                <v-row>
                  <v-col cols="12" md="6"></v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
                      density="compact"
                      variant="outlined"
                      label="If yes, please specify"
                      :model-value="additionalInformationForm.indigenousGroupSpecify"
                      @update:model-value="handleTextInput('indigenousGroupSpecify', $event, 'sanitizeDetails')"
                      :disabled="isFormEditable"
                      :error-messages="v$.additionalInformationForm.indigenousGroupSpecify.$errors?.map(e => e.$message) || []"
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" md="6">
                <p>b. Are you a person with disability?</p>
              </v-col>
              <v-col cols="12" md="6">
                <v-row>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="Yes" 
                      v-model="additionalInformationForm.personWithDisability.yes" 
                      :disabled="isFormEditable"
                      @change="handlePersonWithDisability('yes')"
                      :error-messages="v$.additionalInformationForm.personWithDisability.yes.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="No" 
                      v-model="additionalInformationForm.personWithDisability.no" 
                      :disabled="isFormEditable"
                      @change="handlePersonWithDisability('no')"
                      :error-messages="v$.additionalInformationForm.personWithDisability.no.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" md="12" v-if="additionalInformationForm.personWithDisability.yes">
                <v-row>
                  <v-col cols="12" md="6"></v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
                      density="compact"
                      variant="outlined"
                      label="If yes, please specify ID No"
                      :model-value="additionalInformationForm.personWithDisabilityIdNo"
                      @update:model-value="handleTextInput('personWithDisabilityIdNo', $event, 'sanitizeIdNumber')"
                      :disabled="isFormEditable"
                      :error-messages="v$.additionalInformationForm.personWithDisabilityIdNo.$errors?.map(e => e.$message) || []"
                    ></v-text-field>
                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" md="6">
                <p>c. Are you a solo parent?</p>
              </v-col>
              <v-col cols="12" md="6">
                <v-row>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="Yes" 
                      v-model="additionalInformationForm.soloParent.yes" 
                      :disabled="isFormEditable"
                      @change="handleSoloParent('yes')"
                      :error-messages="v$.additionalInformationForm.soloParent.yes.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-checkbox 
                      label="No" 
                      v-model="additionalInformationForm.soloParent.no" 
                      :disabled="isFormEditable"
                      @change="handleSoloParent('no')"
                      :error-messages="v$.additionalInformationForm.soloParent.no.$errors?.map(e => e.$message) || []"
                    ></v-checkbox>
                  </v-col>
                </v-row>
              </v-col>
              <v-col cols="12" md="12" v-if="additionalInformationForm.soloParent.yes">
                <v-row>
                  <v-col cols="12" md="6"></v-col>
                  <v-col cols="12" md="6">
                    <v-text-field
                      density="compact"
                      variant="outlined"
                      label="If yes, please specify"
                      :model-value="additionalInformationForm.soloParentSpecify"
                      @update:model-value="handleTextInput('soloParentSpecify', $event, 'sanitizeDetails')"
                      :disabled="isFormEditable"
                      :error-messages="v$.additionalInformationForm.soloParentSpecify.$errors?.map(e => e.$message) || []"
                    ></v-text-field>
                  </v-col>
                </v-row>  
              </v-col>
            </v-row>
          </v-col>
        </v-row>
      </v-col>
      <v-col cols="12">
        <v-card-title class="d-flex align-center justify-space-between">
          References
          <v-btn
            variant="tonal"
            color="error"
            rounded="xl"
            min-width="120"
            @click="isDeleteAllReferencesDialog = true"
          >
            Delete All
          </v-btn>
        </v-card-title>
      </v-col>

      <v-col cols="12" class="ml-4 pr-6">
        <v-row v-for="(reference, index) in additionalInformationForm.references" :key="index">
          <v-col cols="12" md="4">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Name"
              :model-value="reference.name"
              @update:model-value="handleReferenceInput(index, 'name', $event)"
              :disabled="isFormEditable"
              :error-messages="v$.additionalInformationForm.references.$each.$errors?.[index]?.name?.map(e => e.$message) || []"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Address"
              :model-value="reference.address"
              @update:model-value="handleReferenceInput(index, 'address', $event)"
              :disabled="isFormEditable"
              :error-messages="v$.additionalInformationForm.references.$each.$errors?.[index]?.address?.map(e => e.$message) || []"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Tel No"
              :model-value="reference.telNo"
              @update:model-value="handleReferenceInput(index, 'telNo', $event)"
              :disabled="isFormEditable"
              :error-messages="v$.additionalInformationForm.references.$each.$errors?.[index]?.telNo?.map(e => e.$message) || []"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="1">
            <v-btn
              icon="mdi-delete"
              color="error"
              variant="tonal"
              size="x-small"
              @click="handleDeleteReference(index)"
            ></v-btn>
          </v-col>
        </v-row>
      </v-col>
      <v-divider></v-divider>
      <v-col cols="12">
        <div class="v-card-title">
          Government Issued ID
        </div>
        <v-row>
          <v-col cols="12" md="6">
            <v-select
              density="compact"
              variant="outlined"
              label="ID Type"
              :items="governmentIdType"
              item-title="name"
              item-value="value"
              :disabled="isFormEditable"
              v-model="additionalInformationForm.govIdType"
              :error-messages="v$.additionalInformationForm.govIdType.$errors?.map(e => e.$message)"
            ></v-select>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="ID Number"
              :model-value="additionalInformationForm.govIdNumber"
              @update:model-value="handleTextInput('govIdNumber', $event, 'sanitizeIdNumber')"
              :disabled="isFormEditable"
              :error-messages="v$.additionalInformationForm.govIdNumber.$errors?.map(e => e.$message)"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Place Issued"
              :disabled="isFormEditable"
              v-model="additionalInformationForm.govIdPlaceIssued"
              :error-messages="v$.additionalInformationForm.govIdPlaceIssued.$errors?.map(e => e.$message)"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              density="compact"
              variant="outlined"
              label="Date Issued"
              type="date"
              :disabled="isFormEditable"
              v-model="additionalInformationForm.govIdDateIssued"
              :error-messages="v$.additionalInformationForm.govIdDateIssued.$errors?.map(e => e.$message)"
            ></v-text-field>
          </v-col>
        </v-row>
      </v-col>

      <v-col cols="12">
        <div class="d-flex justify-end">
          <v-btn 
            type="submit" 
            rounded="xl" 
            class="starbucks-green" 
            min-width="120" 
            :disabled="isFormEditable"
          >Save</v-btn>
        </div>
      </v-col>
    </v-row>
    <!-- <pre>{{ employeeReferences }}</pre> -->
  </v-form>
  
  <!-- Delete Dialog -->
  <DeleteDialog
    v-model="isDeleteAllReferencesDialog"
    message="Are you sure you want to delete all references?"
    @confirm="handleDeleteAllReferences"
    @cancel="isDeleteAllReferencesDialog = false"
  />

</template>
<script>
  import { useForm, router } from '@inertiajs/vue3';
  import { useVuelidate } from '@vuelidate/core';
  import { helpers, required, minLength, maxLength } from '@vuelidate/validators';
  import ButtonSuccess from '@/components/ButtonSuccess.vue';
  import DeleteDialog from '@/components/DeleteDialog.vue';

  export default {
    components: {
      ButtonSuccess,
      DeleteDialog
    },
    props: {
      isFormEditable: {
        type: Boolean,
        default: false
      },
      employee: Object,
      employee_id: Object,
      employeeJobDetails: {
        type: Object,
        default: () => ({})
      },
      employeeAdditionalInformation: {
        type: Object,
        default: () => ({})
      },
      employeeReferences: {
        type: Array,
        default: () => []
      },
    },
    data() {
      return {
        emp_id: this.employee_id == null ? this.employeeJobDetails.data.id : this.employee_id,
        isDeleteAllReferencesDialog: false,
        v$: useVuelidate(),
        governmentIdType: [
          { name: 'Philippine Passport', value: 'Philippine Passport' },
          { name: 'PhilSys National ID', value: 'PhilSys National ID' },
          { name: 'Driver\'s License', value: 'Driver\'s License' },
          { name: 'Professional Regulation Commission ID', value: 'Professional Regulation Commission ID' },
          { name: 'Unified Multi-Purpose ID', value: 'Unified Multi-Purpose ID' },
          { name: 'Social Security System ID', value: 'Social Security System ID' },
          { name: 'Government Service Insurance System eCard', value: 'Government Service Insurance System eCard' },
          { name: 'Voter\'s ID', value: 'Voter\'s ID' },
          { name: 'Postal ID', value: 'Postal ID' },
          { name: 'Alien Certificate of Registration', value: 'Alien Certificate of Registration' },
          { name: 'Firearms License ID', value: 'Firearms License ID' },
          { name: 'Senior Citizen ID', value: 'Senior Citizen ID' },
          { name: 'Persons with Disability ID', value: 'Persons with Disability ID' },
          { name: 'PhilHealth ID Card', value: 'PhilHealth ID Card' },
          { name: 'Pag-IBIG Loyalty Card Plus', value: 'Pag-IBIG Loyalty Card Plus' },
          { name: 'Barangay ID / Barangay Certification', value: 'Barangay ID / Barangay Certification' },
          { name: 'Student ID', value: 'Student ID' },
          { name: 'Company ID', value: 'Company ID' },
          { name: 'Indigenous Peoples (IP) Tribal ID (NCIP-issued)', value: 'Indigenous Peoples (IP) Tribal ID (NCIP-issued)' },
        ],
        // editMode: this.$page.props.auth.roles[0] !== 'employee' ? true : false, 
        
        // Additional Information checkbox data
        additionalInformationForm: useForm({
          // Consanguinity/Affinity questions
          consanguinityThirdDegree: { 
            yes: this.employeeAdditionalInformation?.consanguinity_third_degree_yes || false, 
            no: this.employeeAdditionalInformation?.consanguinity_third_degree_no || false 
          },
          consanguinityFourthDegree: { 
            yes: this.employeeAdditionalInformation?.consanguinity_fourth_degree_yes || false, 
            no: this.employeeAdditionalInformation?.consanguinity_fourth_degree_no || false 
          },
          consanguinityDetails: this.employeeAdditionalInformation?.consanguinity_details || '',
          
          // Administrative offense
          administrativeOffense: { 
            yes: this.employeeAdditionalInformation?.administrative_offense_yes || false, 
            no: this.employeeAdditionalInformation?.administrative_offense_no || false 
          },
          administrativeOffenseDetails: this.employeeAdditionalInformation?.administrative_offense_details || '',
          
          // Criminal charge
          criminalCharge: { 
            yes: this.employeeAdditionalInformation?.criminal_charge_yes || false, 
            no: this.employeeAdditionalInformation?.criminal_charge_no || false 
          },
          criminalChargeDateFiled: this.employeeAdditionalInformation?.criminal_charge_date_filed || '',
          criminalChargeStatus: this.employeeAdditionalInformation?.criminal_charge_status || '',
          
          // Conviction
          conviction: { 
            yes: this.employeeAdditionalInformation?.conviction_yes || false, 
            no: this.employeeAdditionalInformation?.conviction_no || false 
          },
          convictionDetails: this.employeeAdditionalInformation?.conviction_details || '',
          
          // Separation from service
          separationFromService: { 
            yes: this.employeeAdditionalInformation?.separation_from_service_yes || false, 
            no: this.employeeAdditionalInformation?.separation_from_service_no || false 
          },
          separationFromServiceDetails: this.employeeAdditionalInformation?.separation_from_service_details || '',
          
          // Election candidate
          electionCandidate: { 
            yes: this.employeeAdditionalInformation?.election_candidate_yes || false, 
            no: this.employeeAdditionalInformation?.election_candidate_no || false 
          },
          electionCandidateDetails: this.employeeAdditionalInformation?.election_candidate_details || '',
          
          // Resigned for election
          resignedForElection: { 
            yes: this.employeeAdditionalInformation?.resigned_for_election_yes || false, 
            no: this.employeeAdditionalInformation?.resigned_for_election_no || false 
          },
          resignedForElectionDetails: this.employeeAdditionalInformation?.resigned_for_election_details || '',
          
          // Immigrant status
          immigrantStatus: { 
            yes: this.employeeAdditionalInformation?.immigrant_status_yes || false, 
            no: this.employeeAdditionalInformation?.immigrant_status_no || false 
          },
          immigrantStatusCountry: this.employeeAdditionalInformation?.immigrant_status_country || '',
          
          // Indigenous group
          indigenousGroup: { 
            yes: this.employeeAdditionalInformation?.indigenous_group_yes || false, 
            no: this.employeeAdditionalInformation?.indigenous_group_no || false 
          },
          indigenousGroupSpecify: this.employeeAdditionalInformation?.indigenous_group_specify || '',
          
          // Person with disability
          personWithDisability: { 
            yes: this.employeeAdditionalInformation?.person_with_disability_yes || false, 
            no: this.employeeAdditionalInformation?.person_with_disability_no || false 
          },
          personWithDisabilityIdNo: this.employeeAdditionalInformation?.person_with_disability_id_no || '',
          
          // Solo parent
          soloParent: { 
            yes: this.employeeAdditionalInformation?.solo_parent_yes || false, 
            no: this.employeeAdditionalInformation?.solo_parent_no || false 
          },
          soloParentSpecify: this.employeeAdditionalInformation?.solo_parent_specify || '',
          
          // References
          references: this.initializeReferences(),

          // Government Issued ID
          govIdType: this.employeeJobDetails?.data.gov_issued_id || null,
          govIdNumber: this.employeeJobDetails?.data.gov_id_number || null,
          govIdDateIssued: this.employeeJobDetails?.data.date_issue || null,
          govIdPlaceIssued: this.employeeJobDetails?.data.place_issue || null,
        }),
      }
    },
    
    validations: {
      // Additional Information validation
      additionalInformationForm: {
        // Consanguinity/Affinity validation
        consanguinityThirdDegree: {
          yes: { required: helpers.withMessage('Please select Yes or No for third degree consanguinity', required) },
          no: { required: helpers.withMessage('Please select Yes or No for third degree consanguinity', required) }
        },
        consanguinityFourthDegree: {
          yes: { required: helpers.withMessage('Please select Yes or No for fourth degree consanguinity', required) },
          no: { required: helpers.withMessage('Please select Yes or No for fourth degree consanguinity', required) }
        },
        consanguinityDetails: { 
          minLength: minLength(0),
          maxLength: maxLength(500)
        },
        
        // Administrative offense validation
        administrativeOffense: {
          yes: { required: helpers.withMessage('Please select Yes or No for administrative offense', required) },
          no: { required: helpers.withMessage('Please select Yes or No for administrative offense', required) }
        },
        administrativeOffenseDetails: { 
          minLength: minLength(0),
          maxLength: maxLength(500)
        },
        
        // Criminal charge validation
        criminalCharge: {
          yes: { required: helpers.withMessage('Please select Yes or No for criminal charge', required) },
          no: { required: helpers.withMessage('Please select Yes or No for criminal charge', required) }
        },
        criminalChargeDateFiled: { 
          minLength: minLength(0)
        },
        criminalChargeStatus: { 
          minLength: minLength(0),
          maxLength: maxLength(200)
        },
        
        // Conviction validation
        conviction: {
          yes: { required: helpers.withMessage('Please select Yes or No for conviction', required) },
          no: { required: helpers.withMessage('Please select Yes or No for conviction', required) }
        },
        convictionDetails: { 
          minLength: minLength(0),
          maxLength: maxLength(500)
        },
        
        // Separation from service validation
        separationFromService: {
          yes: { required: helpers.withMessage('Please select Yes or No for separation from service', required) },
          no: { required: helpers.withMessage('Please select Yes or No for separation from service', required) }
        },
        separationFromServiceDetails: { 
          minLength: minLength(0),
          maxLength: maxLength(500)
        },
        
        // Election candidate validation
        electionCandidate: {
          yes: { required: helpers.withMessage('Please select Yes or No for election candidate', required) },
          no: { required: helpers.withMessage('Please select Yes or No for election candidate', required) }
        },
        electionCandidateDetails: { 
          minLength: minLength(0),
          maxLength: maxLength(500)
        },
        
        // Resigned for election validation
        resignedForElection: {
          yes: { required: helpers.withMessage('Please select Yes or No for resigned for election', required) },
          no: { required: helpers.withMessage('Please select Yes or No for resigned for election', required) }
        },
        resignedForElectionDetails: { 
          minLength: minLength(0),
          maxLength: maxLength(500)
        },
        
        // Immigrant status validation
        immigrantStatus: {
          yes: { required: helpers.withMessage('Please select Yes or No for immigrant status', required) },
          no: { required: helpers.withMessage('Please select Yes or No for immigrant status', required) }
        },
        immigrantStatusCountry: { 
          minLength: minLength(0),
          maxLength: maxLength(100)
        },
        
        // Indigenous group validation
        indigenousGroup: {
          yes: { required: helpers.withMessage('Please select Yes or No for indigenous group', required) },
          no: { required: helpers.withMessage('Please select Yes or No for indigenous group', required) }
        },
        indigenousGroupSpecify: { 
          minLength: minLength(0),
          maxLength: maxLength(200)
        },
        
        // Person with disability validation
        personWithDisability: {
          yes: { required: helpers.withMessage('Please select Yes or No for person with disability', required) },
          no: { required: helpers.withMessage('Please select Yes or No for person with disability', required) }
        },
        personWithDisabilityIdNo: { 
          minLength: minLength(0),
          maxLength: maxLength(50)
        },
        
        // Solo parent validation
        soloParent: {
          yes: { required: helpers.withMessage('Please select Yes or No for solo parent', required) },
          no: { required: helpers.withMessage('Please select Yes or No for solo parent', required) }
        },
        soloParentSpecify: { 
          minLength: minLength(0),
          maxLength: maxLength(200)
        },
        
        // References validation
        references: {
          $each: helpers.forEach({
            name: { 
              required: helpers.withMessage('Please enter name', required),
  
            },
            address: { 
              required: helpers.withMessage('Please enter address', required),
            },
            telNo: { 
              required: helpers.withMessage('Please enter tel no', required),
            }
          })
        },
        
        // Government Issued ID validation
        govIdType: { minLength: minLength(0), },
        govIdNumber: { minLength: minLength(0), },
        govIdDateIssued: { minLength: minLength(0), },
        govIdPlaceIssued: { minLength: minLength(0), },
      },
    },
    methods: {
      // Initialize references to maintain exactly 3 references
      initializeReferences() {
        const referencesData = Array.isArray(this.employeeReferences) 
          ? this.employeeReferences 
          : this.employeeReferences?.data || [];
        
        const mappedReferences = referencesData.map(reference => ({
          name: reference.name ?? '', 
          address: reference.address ?? '', 
          telNo: reference.tel_no ?? ''
        }));
        
        // Ensure exactly 3 reference rows
        while (mappedReferences.length < 3) {
          mappedReferences.push({ name: '', address: '', telNo: '' });
        }
        // If there are more than 3, keep only the first 3
        if (mappedReferences.length > 3) {
          mappedReferences.length = 3;
        }
        
        return mappedReferences;
      },

      // Get reference by index with fallback to empty object
      getReference(index) {
        return this.safeEmployeeReferences[index] || { name: '', address: '', tel_no: '' };
      },

      // Input sanitization methods
      sanitizeTextInput(input, options = {}) {
        if (!input || typeof input !== 'string') {
          return input;
        }

        const defaults = {
          trim: true,
          removeExtraSpaces: true,
          removeHtml: true,
          maxLength: null,
          convertCase: null, // 'upper', 'lower', 'title'
          allowedChars: null, // regex pattern for allowed characters
        };

        const config = { ...defaults, ...options };
        let cleaned = input;

        // Remove HTML tags
        if (config.removeHtml) {
          cleaned = cleaned.replace(/<[^>]*>/g, '');
          cleaned = cleaned.replace(/&[^;]+;/g, ' ');
        }

        // Trim whitespace
        if (config.trim) {
          cleaned = cleaned.trim();
        }

        // Remove extra spaces
        if (config.removeExtraSpaces) {
          cleaned = cleaned.replace(/\s+/g, ' ');
        }

        // Filter allowed characters
        if (config.allowedChars) {
          const regex = new RegExp(config.allowedChars, 'g');
          const matches = cleaned.match(regex);
          cleaned = matches ? matches.join('') : '';
        }

        // Apply case conversion
        if (config.convertCase) {
          switch (config.convertCase) {
            case 'upper':
              cleaned = cleaned.toUpperCase();
              break;
            case 'lower':
              cleaned = cleaned.toLowerCase();
              break;
            case 'title':
              cleaned = cleaned.replace(/\w\S*/g, (txt) => 
                txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()
              );
              break;
          }
        }

        // Limit length
        if (config.maxLength && cleaned.length > config.maxLength) {
          cleaned = cleaned.substring(0, config.maxLength);
        }

        return cleaned;
      },

      sanitizeName(input) {
        return this.sanitizeTextInput(input, {
          trim: true,
          removeExtraSpaces: true,
          removeHtml: true,
          maxLength: 100,
          convertCase: 'title',
          allowedChars: '[a-zA-Z\\s\\-\\.\']+',
        });
      },

      sanitizeAddress(input) {
        return this.sanitizeTextInput(input, {
          trim: true,
          removeExtraSpaces: true,
          removeHtml: true,
          maxLength: 255,
          convertCase: 'title',
          allowedChars: '[a-zA-Z0-9\\s\\-\\.,#\']+',
        });
      },

      sanitizePhoneNumber(input) {
        return this.sanitizeTextInput(input, {
          trim: true,
          removeExtraSpaces: true,
          removeHtml: true,
          maxLength: 20,
          allowedChars: '[\\+0-9\\(\\)\\-\\.\\s]+',
        });
      },

      sanitizeDetails(input) {
        return this.sanitizeTextInput(input, {
          trim: true,
          removeExtraSpaces: true,
          removeHtml: true,
          maxLength: 500,
        });
      },

      sanitizeCountry(input) {
        return this.sanitizeTextInput(input, {
          trim: true,
          removeExtraSpaces: true,
          removeHtml: true,
          maxLength: 100,
          convertCase: 'title',
          allowedChars: '[a-zA-Z\\s\\-\\.\']+',
        });
      },

      sanitizeIdNumber(input) {
        return this.sanitizeTextInput(input, {
          trim: true,
          removeExtraSpaces: true,
          removeHtml: true,
          maxLength: 50,
          convertCase: 'upper',
          allowedChars: '[a-zA-Z0-9\\-]+',
        });
      },

      // Enhanced input handlers with sanitization
      handleTextInput(field, value, sanitizer = 'sanitizeDetails') {
        const sanitizedValue = this[sanitizer](value);
        this.additionalInformationForm[field] = sanitizedValue;
      },

      handleReferenceInput(index, field, value) {
        let sanitizedValue = value;
        
        switch (field) {
          case 'name':
            sanitizedValue = this.sanitizeName(value);
            break;
          case 'address':
            sanitizedValue = this.sanitizeAddress(value);
            break;
          case 'telNo':
            sanitizedValue = this.sanitizePhoneNumber(value);
            break;
        }
        
        this.additionalInformationForm.references[index][field] = sanitizedValue;
      },

      handleSubmitAdditionalInformation(){
        // Sanitize all form data before validation and submission
        this.sanitizeAllFormData();
        
        // this.v$.additionalInformationForm.$touch()
        // if (!this.v$.additionalInformationForm.$invalid) {
          // return
          this.additionalInformationForm.put(
            route("self-service.my-profile.updateAdditionalInformation", {
              id: this.emp_id,
            }),
            {
              preserveScroll:true,
              preserveState:true,
              onSuccess: () => {
                this.showToast("Additional Information updated successfully", "success");
              },
              onError: (errors) => {
                const errorMessages = Object.values(errors).flat().join(" ");
                this.showToast(`${errorMessages}`, "error");
              },
            }
          )
        // }
      },

      // Sanitize all form data before submission
      sanitizeAllFormData() {
        // Sanitize detail fields
        const detailFields = [
          'consanguinityDetails', 'administrativeOffenseDetails', 'criminalChargeStatus',
          'convictionDetails', 'separationFromServiceDetails', 'electionCandidateDetails',
          'resignedForElectionDetails', 'indigenousGroupSpecify', 'soloParentSpecify'
        ];
        
        detailFields.forEach(field => {
          if (this.additionalInformationForm[field]) {
            this.additionalInformationForm[field] = this.sanitizeDetails(this.additionalInformationForm[field]);
          }
        });

        // Sanitize country field
        if (this.additionalInformationForm.immigrantStatusCountry) {
          this.additionalInformationForm.immigrantStatusCountry = this.sanitizeCountry(this.additionalInformationForm.immigrantStatusCountry);
        }

        // Sanitize ID number
        if (this.additionalInformationForm.personWithDisabilityIdNo) {
          this.additionalInformationForm.personWithDisabilityIdNo = this.sanitizeIdNumber(this.additionalInformationForm.personWithDisabilityIdNo);
        }

        // Sanitize references
        this.additionalInformationForm.references.forEach((reference, index) => {
          if (reference.name) {
            this.additionalInformationForm.references[index].name = this.sanitizeName(reference.name);
          }
          if (reference.address) {
            this.additionalInformationForm.references[index].address = this.sanitizeAddress(reference.address);
          }
          if (reference.telNo) {
            this.additionalInformationForm.references[index].telNo = this.sanitizePhoneNumber(reference.telNo);
          }
        });
      },
      // Checkbox mutual exclusion methods
      handleConsanguinityThirdDegree(selection) {
        if (selection === 'yes') {
          this.additionalInformationForm.consanguinityThirdDegree.yes = true;
          this.additionalInformationForm.consanguinityThirdDegree.no = false;
        } else {
          this.additionalInformationForm.consanguinityThirdDegree.yes = false;
          this.additionalInformationForm.consanguinityThirdDegree.no = true;
        }
      },

      handleConsanguinityFourthDegree(selection) {
        if (selection === 'yes') {
          this.additionalInformationForm.consanguinityFourthDegree.yes = true;
          this.additionalInformationForm.consanguinityFourthDegree.no = false;
        } else {
          this.additionalInformationForm.consanguinityFourthDegree.yes = false;
          this.additionalInformationForm.consanguinityFourthDegree.no = true;
        }
      },

      handleAdministrativeOffense(selection) {
        if (selection === 'yes') {
          this.additionalInformationForm.administrativeOffense.yes = true;
          this.additionalInformationForm.administrativeOffense.no = false;
        } else {
          this.additionalInformationForm.administrativeOffense.yes = false;
          this.additionalInformationForm.administrativeOffense.no = true;
        }
      },

      handleCriminalCharge(selection) {
        if (selection === 'yes') {
          this.additionalInformationForm.criminalCharge.yes = true;
          this.additionalInformationForm.criminalCharge.no = false;
        } else {
          this.additionalInformationForm.criminalCharge.yes = false;
          this.additionalInformationForm.criminalCharge.no = true;
        }
      },

      handleConviction(selection) {
        if (selection === 'yes') {
          this.additionalInformationForm.conviction.yes = true;
          this.additionalInformationForm.conviction.no = false;
        } else {
          this.additionalInformationForm.conviction.yes = false;
          this.additionalInformationForm.conviction.no = true;
        }
      },

      handleSeparationFromService(selection) {
        if (selection === 'yes') {
          this.additionalInformationForm.separationFromService.yes = true;
          this.additionalInformationForm.separationFromService.no = false;
        } else {
          this.additionalInformationForm.separationFromService.yes = false;
          this.additionalInformationForm.separationFromService.no = true;
        }
      },

      handleElectionCandidate(selection) {
        if (selection === 'yes') {
          this.additionalInformationForm.electionCandidate.yes = true;
          this.additionalInformationForm.electionCandidate.no = false;
        } else {
          this.additionalInformationForm.electionCandidate.yes = false;
          this.additionalInformationForm.electionCandidate.no = true;
        }
      },

      handleResignedForElection(selection) {
        if (selection === 'yes') {
          this.additionalInformationForm.resignedForElection.yes = true;
          this.additionalInformationForm.resignedForElection.no = false;
        } else {
          this.additionalInformationForm.resignedForElection.yes = false;
          this.additionalInformationForm.resignedForElection.no = true;
        }
      },

      handleImmigrantStatus(selection) {
        if (selection === 'yes') {
          this.additionalInformationForm.immigrantStatus.yes = true;
          this.additionalInformationForm.immigrantStatus.no = false;
        } else {
          this.additionalInformationForm.immigrantStatus.yes = false;
          this.additionalInformationForm.immigrantStatus.no = true;
        }
      },

      handleIndigenousGroup(selection) {
        if (selection === 'yes') {
          this.additionalInformationForm.indigenousGroup.yes = true;
          this.additionalInformationForm.indigenousGroup.no = false;
        } else {
          this.additionalInformationForm.indigenousGroup.yes = false;
          this.additionalInformationForm.indigenousGroup.no = true;
        }
      },

      handlePersonWithDisability(selection) {
        if (selection === 'yes') {
          this.additionalInformationForm.personWithDisability.yes = true;
          this.additionalInformationForm.personWithDisability.no = false;
        } else {
          this.additionalInformationForm.personWithDisability.yes = false;
          this.additionalInformationForm.personWithDisability.no = true;
        }
      },

      handleSoloParent(selection) {
        if (selection === 'yes') {
          this.additionalInformationForm.soloParent.yes = true;
          this.additionalInformationForm.soloParent.no = false;
        } else {
          this.additionalInformationForm.soloParent.yes = false;
          this.additionalInformationForm.soloParent.no = true;
        }
      },

      // Handle deletion of a single reference
      handleDeleteReference(index) {
        const reference = this.employeeReferences[index];
        if (!reference?.id) {
          // If it's a new reference (no ID), just clear it
          this.additionalInformationForm.references[index] = { name: '', address: '', telNo: '' };
          return;
        }

        // Call the backend to delete the reference
        router.delete(route('self-service.my-profile.deleteReference', { id: reference.id }), {
          preserveState: true,
          preserveScroll: true,
          onSuccess: () => {
            // Clear the reference locally
            this.additionalInformationForm.references[index] = { name: '', address: '', telNo: '' };
            this.showToast('Reference deleted successfully', 'success');
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
          },
        });
      },

      // Handle deletion of all references
      handleDeleteAllReferences() {
        if (!this.employee_id) return;

        // Call the backend to delete all references
        router.delete(route('self-service.my-profile.deleteAllReferences', { employee_id: this.employee_id }), {
          preserveState: true,
          preserveScroll: true,
          onSuccess: () => {
            // Clear all references locally but maintain 3 empty rows
            this.additionalInformationForm.references = Array(3).fill().map(() => ({ name: '', address: '', telNo: '' }));
            this.isDeleteAllReferencesDialog = false;
            this.showToast('All references deleted successfully', 'success');
          },
          onError: (errors) => {
            const errorMessages = Object.values(errors).flat().join(" ");
            this.showToast(`${errorMessages}`, "error");
            this.isDeleteAllReferencesDialog = false;
          },
        });
      },
    }
  }
</script>