<template>
  <div
    class="min-h-screen bg-gray-100 text-gray-900 flex justify-center items-center px-4 py-8"
  >
    <div class="w-full max-w-4xl mx-auto">
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
          <v-col cols="12" md="8">
            <div class="text-center mb-4">
              <div
                class="v-card-title text-center text-xl sm:text-2xl lg:text-3xl font-bold text-gray-800 leading-tight text-wrap"
              >
                Terms of Service
              </div>
              <div class="v-card-subtitle text-center text-sm text-gray-600">
                Last updated: {{ lastUpdated }}
              </div>
            </div>

            <!-- Scrollable Content -->
            <div
              class="flex-1 min-h-[320px] max-h-[50vh] lg:max-h-[420px] overflow-y-auto"
            >
              <section class="mb-6">
                <p class="text-sm text-gray-700 leading-6 text-justify">
                  These Terms of Service ("Terms") govern the use of the Human
                  Resource Management System ("HRMS") provided by Don Mariano
                  Marcos Memorial State University ("we," "our," or "us"). By
                  accessing or using the HRMS, all users ("you," "your") agree
                  to comply with these Terms of Service and all applicable laws
                  and regulations. If you do not agree with any part of the
                  terms, you may not access the service.
                </p>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  1. Purpose of the HRMS
                </h2>
                <p class="text-sm text-gray-700 leading-6 mb-2">
                  The HRMS is provided to manage human resource functions,
                  including but not limited to:
                </p>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700">
                  <li>Recruitment and employee onboarding</li>
                  <li>Payroll and benefits administration</li>
                  <li>Attendance and leave management</li>
                  <li>Performance evaluation and training records</li>
                  <li>Employee records management</li>
                </ul>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  2. Authorized Users
                </h2>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700">
                  <li>
                    The HRMS is intended solely for employees, administrators,
                    and authorized personnel of Don Mariano Marcos Memorial
                    State University.
                  </li>
                  <li>
                    Users must use their assigned credentials to access the
                    system.
                  </li>
                  <li>
                    Sharing of accounts, passwords, or unauthorized access to
                    another user's account is strictly prohibited.
                  </li>
                </ul>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  3. User Responsibilities
                </h2>
                <p class="text-sm text-gray-700 leading-6 mb-2">
                  By using the HRMS, you agree to:
                </p>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700">
                  <li>
                    Provide accurate and updated personal and employment-related
                    information.
                  </li>
                  <li>
                    Use the system only for legitimate work-related purposes.
                  </li>
                  <li>
                    Maintain the confidentiality of your account credentials.
                  </li>
                  <li>
                    Immediately report unauthorized access, data breaches, or
                    security issues to the HR/ICT department.
                  </li>
                  <li>
                    Refrain from tampering with, misusing, or attempting to
                    disrupt system operations.
                  </li>
                </ul>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  4. Prohibited Conduct
                </h2>
                <p class="text-sm text-gray-700 leading-6 mb-2">
                  Users are not allowed to:
                </p>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700">
                  <li>
                    Access, collect, or use another employee's personal data
                    without proper authority.
                  </li>
                  <li>
                    Upload or transmit malicious software, viruses, or harmful
                    code.
                  </li>
                  <li>
                    Use the system for unlawful, discriminatory, or harassing
                    activities.
                  </li>
                  <li>Bypass or disable system security features.</li>
                </ul>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  5. Data Privacy and Confidentiality
                </h2>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700">
                  <li>
                    Personal and sensitive information processed through the
                    HRMS is subject to the Data Privacy Act of 2012 (RA 10173)
                    and our Privacy Policy.
                  </li>
                  <li>
                    Users acknowledge that all employment and HR-related data
                    remain the property of Don Mariano Marcos Memorial State
                    University and must be kept confidential.
                  </li>
                  <li>
                    Any misuse or unauthorized disclosure of data may result in
                    disciplinary and/or legal action.
                  </li>
                </ul>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  6. System Availability and Maintenance
                </h2>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700">
                  <li>
                    We strive to ensure the HRMS is available and secure;
                    however, we do not guarantee uninterrupted access.
                  </li>
                  <li>
                    Scheduled maintenance or system upgrades may temporarily
                    restrict access.
                  </li>
                  <li>
                    We reserve the right to suspend or terminate system access
                    for security, policy violations, or other legitimate
                    reasons.
                  </li>
                </ul>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  7. Intellectual Property
                </h2>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700">
                  <li>
                    All content, materials, and software associated with the
                    HRMS remain the property of Don Mariano Marcos Memorial
                    State University.
                  </li>
                  <li>
                    Users may not copy, distribute, or modify the system or its
                    components without prior written authorization.
                  </li>
                </ul>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  8. Disciplinary Action for Violations
                </h2>
                <ul class="list-disc pl-5 space-y-2 text-sm text-gray-700">
                  <li>
                    Any violation of these Terms may result in disciplinary
                    measures in accordance with university policies and the
                    Labor Code of the Philippines.
                  </li>
                  <li>
                    Possible actions include warnings, suspension of system
                    access, administrative sanctions, and legal action where
                    applicable.
                  </li>
                </ul>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  9. Amendments to Terms
                </h2>
                <p class="text-sm text-gray-700 leading-6">
                  We may revise these Terms from time to time to reflect legal,
                  regulatory, or operational requirements. Updated Terms will be
                  communicated to all users, and continued use of the HRMS
                  constitutes acceptance of the updated Terms.
                </p>
              </section>

              <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">
                  10. Contact Information
                </h2>
                <p class="text-sm text-gray-700 leading-6 mb-2">
                  For questions or concerns regarding these Terms, please
                  contact:
                </p>
                <div class="text-sm text-gray-700 leading-6 pl-4">
                  <p><strong>Data Protection Officer (DPO)</strong></p>
                  <p>Eusebio L. Mique Jr.</p>
                  <p>emique@dmmmsu.edu.ph</p>
                  <p>Don Mariano Marcos Memorial State University</p>
                  <p>Mid La Union Campus</p>
                  <p>Quezon Ave., Catbangen</p>
                  <p>City of San Fernando, La Union, 2500</p>
                </div>
              </section>
            </div>
          </v-col>
        </v-row>
        <v-col cols="12">
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
  name: "TermsAndServices",
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
