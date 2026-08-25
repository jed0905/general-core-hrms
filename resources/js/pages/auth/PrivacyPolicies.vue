<template>
  <div
    class="min-h-screen bg-gray-100 text-gray-900 flex justify-center items-center px-4 py-8"
  >
    <div class="w-full max-w-6xl mx-auto">
      <div class="bg-white shadow-lg rounded-lg lg:rounded-xl overflow-hidden">
        <v-row class="ma-5">
          <v-col cols="12" md="4">
            <v-row>
              <v-col cols="12" class="d-flex align-center justify-center pr-9">
                <div class="d-flex align-center justify-center">
                  <Link :href="route('auth.registration')">
                    <v-img
                      :src="dmmmsulogo"
                      alt="dmmmsu-logo"
                      width="200px"
                      height="200px"
                      contain
                      class="pa-5"
                    ></v-img>
                  </Link>
                </div>
              </v-col>
            </v-row>

            <v-row>
              <v-col
                cols="12"
                class="d-flex align-center justify-center -mt-10"
              >
                <div class="d-flex align-center">
                  <div class="flex flex-col items-center text-center">
                    <Link :href="route('auth.registration')">
                      <v-img
                        :src="hrms"
                        alt="logo"
                        width="250px"
                        height="250px"
                        contain
                        class="mb-0"
                      ></v-img>
                    </Link>
                    <p class="font-bold -mt-10">
                      Human Resource Management System
                    </p>
                    <p class="text-sm text-gray-600 italic mt-0">
                      <!-- TAG LINE HERE -->
                    </p>
                  </div>
                </div>
              </v-col>
            </v-row>
          </v-col>

          <v-col cols="12" md="8" class="overflow-y-auto max-h-[70vh]">
            <!-- Header -->
            <div class="text-center mb-4">
              <div
                class="v-card-title text-center text-xl sm:text-2xl lg:text-3xl font-bold text-gray-800 leading-tight text-wrap px-2"
              >
                Privacy Policy
              </div>
              <div
                class="v-card-subtitle text-center text-sm text-gray-600 px-2"
              >
                Last updated: {{ lastUpdated }}
              </div>
            </div>

            <!-- Scrollable Content -->
            <div
              class="flex-1 min-h-[320px] max-h-[50vh] lg:max-h-[420px] overflow-y-auto pr-2"
            >
              <section class="mb-6">
                <p class="text-sm text-gray-700 leading-6 text-justify">
                  <b>Don Mariano Marcos Memorial State University</b> ("we,"
                  "our," or "us") is committed to protecting the privacy and
                  ensuring the security of all personal information processed
                  through our Human Resource Management System ("HRMS"). This
                  Privacy Policy is issued in compliance with the Data Privacy
                  Act of 2012 (Republic Act No. 10173), its Implementing Rules
                  and Regulations (IRR), and related issuances of the National
                  Privacy Commission (NPC).
                </p>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  1. Scope
                </h2>
                <p class="text-sm text-gray-700 leading-6">
                  This policy covers the collection, use, storage, sharing, and
                  disposal of personal data of all employees, job applicants,
                  consultants, contractors, and any other individual whose
                  information is processed through the HRMS.
                </p>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  2. Personal Data We Collect
                </h2>
                <p class="text-sm text-gray-700 leading-6 mb-2">
                  We collect and process the following categories of personal
                  data:
                </p>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700">
                  <li>
                    <strong>Personal Information:</strong> Name, address,
                    contact details, date of birth, civil status,
                    government-issued identification numbers.
                  </li>
                  <li>
                    <strong>Sensitive Personal Information:</strong> Health
                    records, medical certificates, financial information (e.g.,
                    salary, bank details), employment history, performance
                    evaluations, disciplinary records.
                  </li>
                  <li>
                    <strong>Employment-Related Information:</strong> Job titles,
                    contracts, attendance, leave records, training, and career
                    development data.
                  </li>
                  <li>
                    <strong>Recruitment Data:</strong> Personal Data Sheet,
                    application forms, interview results, examination results.
                  </li>
                  <li>
                    <strong>System-Generated Data:</strong> User accounts, login
                    details, access logs, and other activity records within the
                    HRMS.
                  </li>
                </ul>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  3. Purpose of Processing
                </h2>
                <p class="text-sm text-gray-700 leading-6 mb-2">
                  We process personal data only for legitimate HR and
                  organizational purposes, including:
                </p>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700">
                  <li>Recruitment, hiring, and onboarding.</li>
                  <li>
                    Payroll, benefits administration, and government-mandated
                    contributions.
                  </li>
                  <li>
                    Attendance, leave, performance monitoring, and employee
                    discipline.
                  </li>
                  <li>Compliance with labor, tax, and social security laws.</li>
                  <li>
                    Internal communication, organizational planning, and
                    reporting.
                  </li>
                  <li>Security and audit of HRMS usage.</li>
                </ul>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  4. Legal Basis of Processing
                </h2>
                <p class="text-sm text-gray-700 leading-6 mb-2">
                  Processing of personal and sensitive personal information is
                  carried out based on:
                </p>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700">
                  <li>Fulfillment of employment or service contracts.</li>
                  <li>Compliance with legal obligations.</li>
                  <li>Protection of the vital interests of employees.</li>
                  <li>
                    Legitimate interests of the organization, balanced with
                    employee rights.
                  </li>
                  <li>Consent of the data subject, where required.</li>
                </ul>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  5. Data Sharing and Disclosure
                </h2>
                <p class="text-sm text-gray-700 leading-6 mb-2">
                  We may disclose personal data to:
                </p>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700">
                  <li>
                    <strong>Authorized Personnel</strong> – HR officers,
                    supervisors, and administrators with legitimate business
                    access.
                  </li>
                  <li>
                    <strong>Third-Party Service Providers</strong> – Payroll
                    processors, insurance providers, IT support, background
                    check agencies, bound by data protection agreements.
                  </li>
                  <li>
                    <strong>Government Agencies</strong> – Bureau of Internal
                    Revenue (BIR), Government Service Insurance System (GSIS),
                    Social Security System (SSS), Pag-IBIG Fund, PhilHealth, and
                    other lawful authorities.
                  </li>
                  <li>
                    <strong>Law Enforcement and Courts</strong> – When required
                    under applicable laws.
                  </li>
                </ul>
                <p class="text-sm text-gray-700 leading-6 mt-2">
                  We do not sell, trade, or share personal information for
                  commercial purposes.
                </p>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  6. Data Protection Measures
                </h2>
                <p class="text-sm text-gray-700 leading-6 mb-2">
                  We adopt appropriate organizational, physical, and technical
                  security measures to ensure the confidentiality, integrity,
                  and availability of personal data, including but not limited
                  to:
                </p>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700">
                  <li>Encrypted storage and data transmission.</li>
                  <li>
                    Role-based access control and authentication measures.
                  </li>
                  <li>
                    Secure servers, firewalls, and intrusion detection systems.
                  </li>
                  <li>Regular system backups and audits.</li>
                  <li>
                    Strict confidentiality agreements with authorized personnel
                    and service providers.
                  </li>
                </ul>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  7. Data Retention and Disposal
                </h2>
                <p class="text-sm text-gray-700 leading-6">
                  Personal data shall be retained only for as long as necessary
                  to fulfill the declared and legitimate purposes, or as
                  required by law. After the retention period, data shall be
                  securely disposed of through deletion, anonymization, or
                  physical destruction of records.
                </p>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  8. Rights of Data Subjects
                </h2>
                <p class="text-sm text-gray-700 leading-6 mb-2">
                  In accordance with the Data Privacy Act, employees and
                  applicants have the following rights:
                </p>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700">
                  <li>
                    <strong>Right to be Informed</strong> – Know how personal
                    data is collected, used, and processed.
                  </li>
                  <li>
                    <strong>Right to Object</strong> – Withhold consent or
                    object to processing, subject to legal or contractual
                    obligations.
                  </li>
                  <li>
                    <strong>Right to Access</strong> – Request a copy of
                    personal data held by the organization.
                  </li>
                  <li>
                    <strong>Right to Rectification</strong> – Request correction
                    of inaccurate or outdated information.
                  </li>
                  <li>
                    <strong>Right to Erasure or Blocking</strong> – Request
                    deletion or blocking of personal data if unlawfully
                    processed.
                  </li>
                  <li>
                    <strong>Right to Data Portability</strong> – Obtain a copy
                    of data in electronic format for personal use, where
                    applicable.
                  </li>
                  <li>
                    <strong>Right to Damages</strong> – Claim compensation if
                    proven that personal data was misused, maliciously
                    disclosed, or negligently handled.
                  </li>
                  <li>
                    <strong>Right to File a Complaint</strong> – With the
                    National Privacy Commission (NPC).
                  </li>
                </ul>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  9. Cross-Border Data Processing
                </h2>
                <p class="text-sm text-gray-700 leading-6">
                  If personal data is transferred outside the Philippines, we
                  ensure that adequate safeguards are in place to protect data
                  consistent with the Data Privacy Act and NPC requirements.
                </p>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  10. Policy Updates
                </h2>
                <p class="text-sm text-gray-700 leading-6">
                  This Privacy Policy may be revised to comply with changes in
                  laws, regulations, or organizational practices. Updates will
                  be communicated through official channels and reflected in the
                  HRMS.
                </p>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  11. Contact Information
                </h2>
                <p class="text-sm text-gray-700 leading-6 mb-2">
                  For questions, concerns, or to exercise your rights under this
                  Privacy Policy, you may contact our Data Protection Officer
                  (DPO):
                </p>
                <div class="bg-gray-50 p-4 rounded-lg">
                  <p class="text-sm text-gray-700 leading-6 mb-2">
                    <strong>Data Protection Officer (DPO)</strong>
                  </p>
                  <p class="text-sm text-gray-700 leading-6">
                    Eusebio L. Mique Jr.
                  </p>
                  <p class="text-sm text-gray-700 leading-6">
                    emique@dmmmsu.edu.ph
                  </p>
                  <p class="text-sm text-gray-700 leading-6">
                    Don Mariano Marcos Memorial State University
                  </p>
                  <p class="text-sm text-gray-700 leading-6">
                    Mid La Union Campus
                  </p>
                  <p class="text-sm text-gray-700 leading-6">
                    Quezon Ave., Catbangen
                  </p>
                  <p class="text-sm text-gray-700 leading-6">
                    City of San Fernando, La Union, 2500
                  </p>
                </div>
                <p class="text-sm text-gray-700 leading-6 mt-3">
                  You may also lodge complaints with the National Privacy
                  Commission (NPC) at:
                </p>
                <ul class="list-disc pl-5 space-y-1 text-sm text-gray-700 mt-2">
                  <li><strong>Website:</strong> https://privacy.gov.ph</li>
                  <li>
                    <strong>Address:</strong> 25th-27th Floors, The Upper Class
                    Tower, Quezon Ave. Corner Scout Reyes Street, Quezon City,
                    Philippines
                  </li>
                </ul>
              </section>
            </div>

            <!-- Actions -->
          </v-col>
        </v-row>
        <v-col cols="12" class="d-flex align-center justify-center">
          <div class="mt-6 mb-6">
            <div class="d-flex align-center justify-center">
              <v-btn
                min-width="120"
                color="grey-lighten-3"
                elevation="2"
                rounded="xl"
                @click="goBack"
              >
                Back
              </v-btn>
              <!-- <v-btn :disabled="!accepted" color="primary" class="w-full sm:w-auto" @click="proceed">
                  Accept & Continue
                </v-btn> -->
            </div>
          </div>
        </v-col>
      </div>
    </div>
  </div>
</template>

<script>
import logo from "../../../images/abstract-doodle.png";
import dmmmsulogo from "../../../images/dmmmsu-logo.png";
import logo3 from "../../../images/logo3.png";
import hrms from "../../../images/HRMS.svg";

export default {
  name: "PrivacyPolicies",
  data() {
    return {
      logo,
      logo3,
      hrms,
      dmmmsulogo,
      accepted: false,
      lastUpdated: "September 2025",
    };
  },
  methods: {
    goBack() {
      this.$inertia.visit(route("auth.registration"));
    },
  },
};
</script>

<style scoped>
body {
  font-family: "Inter", sans-serif;
}

.bg {
  background: #006241;
  background: linear-gradient(
    207deg,
    rgba(0, 98, 65, 1) 18%,
    rgba(70, 194, 165, 1) 62%,
    rgba(242, 199, 27, 1) 98%
  );
}
</style>
