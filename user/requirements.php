<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Basic page setup -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover" />
  <title>Service Requirements — PhilHealth</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Google font -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">

  <!-- Page styles -->
  <style>
    /* Main font */
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
    }

    /* Group header */
    .grp-hdr {
      padding: 1.25rem 1.5rem 1rem;
      border-bottom: 1px solid #e8f5e8;
    }

    .grp-title-row {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .grp-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      flex-shrink: 0;
    }

    .dot-g {
      background: #2d7a3a;
    }

    .grp-title {
      font-size: 1.05rem;
      font-weight: 800;
      color: #14381e;
    }

    .grp-sub {
      margin-top: 4px;
      font-size: 12px;
      color: #6b9a72;
      padding-left: 16px;
    }

    /* Card group box */
    .card.grp.req-group {
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 1rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      margin-bottom: 1.75rem;
    }

    /* Bottom sheet hidden by default */
    .sheet-ov {
      display: none;
    }

    .sheet-ov.open {
      display: block;
    }

    /* Background fade */
    .sheet-bd {
      opacity: 0;
      transition: opacity 0.22s;
    }

    .sheet-bd.vis {
      opacity: 1;
    }

    /* Bottom sheet animation */
    .sheet-card {
      transform: translateY(100%);
      opacity: 0;
      transition: transform 0.28s cubic-bezier(0.32, 0.72, 0, 1), opacity 0.2s;
    }

    @media (min-width: 640px) {
      .sheet-card {
        transform: translateY(16px) scale(0.97);
      }
    }

    .sheet-card.vis {
      transform: translateY(0) scale(1);
      opacity: 1;
    }

    /* Hide list dots */
    .li-dot {
      display: none;
    }

    /* Active slider dot */
    .sldr-dot.active {
      background-color: #2d7a3a;
    }
  </style>
</head>

<body
  class="min-h-screen bg-gradient-to-br from-[#c9eda0] via-[#9ed654] to-[#4fa828] text-[#14381e] overflow-x-hidden relative">

  <!-- Simple background pattern -->
  <div class="fixed inset-0 pointer-events-none opacity-20"
    style="background-image: linear-gradient(rgba(255,255,255,0.14) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.14) 1px, transparent 1px); background-size: 52px 52px;">
  </div>

  <!-- Top header -->
  <header class="sticky top-0 z-50 bg-white/30 backdrop-blur-md border-b border-white/40">
    <div class="max-w-5xl mx-auto px-6 min-h-[64px] flex items-center justify-between gap-4">
      <a href="dashboard.php"
        class="inline-flex items-center gap-1.5 text-sm font-bold text-[#1a4d2e] no-underline px-4 py-2 rounded-xl border border-white/60 bg-white/50 hover:bg-white/70 transition shadow-sm">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd"
            d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z"
            clip-rule="evenodd" />
        </svg>
        Back
      </a>

      <span class="hidden md:block text-xs font-semibold text-[#1a4d2e] opacity-70">
        Prepare documents before your appointment
      </span>
    </div>
  </header>

  <main class="max-w-5xl mx-auto px-6 py-8">

    <!-- Main intro card -->
    <div class="bg-white/95 backdrop-blur-sm border border-white/80 rounded-2xl shadow-lg p-8 mb-8">
      <h1 class="text-3xl md:text-4xl font-extrabold text-[#14381e] tracking-tight">Service Requirements</h1>
      <p class="mt-2 text-sm text-[#4a7a52]">
        Tap a service to view complete requirements. Bring originals and photocopies when applicable.
      </p>

      <!-- Search and PMRF button -->
      <div class="flex flex-wrap items-start gap-4 mt-6">
        <div class="flex-1 min-w-[240px]">
          <input id="req-search" type="text"
            class="w-full bg-white border-2 border-[#cce8cc] rounded-xl px-5 py-3 text-sm text-[#14381e] placeholder-gray-400 outline-none focus:border-[#4caf50] focus:ring-2 focus:ring-[#4caf50]/20 transition"
            placeholder="Search (e.g., PMRF, OFW, civil status, renewal, MDR, claims)">
          <p class="mt-1.5 text-xs text-[#6b9a72] ml-1">Filters services instantly.</p>
        </div>

        <div>
          <a href="/philhealth_queue/download_pmrf.php"
            class="inline-flex items-center justify-center bg-[#1a4d2e] text-white font-bold text-sm px-6 py-3 rounded-xl hover:bg-[#2d7a3a] transition shadow-md whitespace-nowrap">
            Download PMRF Form
          </a>
          <p class="mt-1.5 text-xs text-[#6b9a72] ml-1">One click downloads the PDF.</p>
        </div>
      </div>

      <!-- Important note -->
      <div class="mt-6 p-4 bg-amber-50/90 border border-amber-200 rounded-xl text-sm text-amber-800 shadow-sm">
        If transacting through a representative: bring an authorization letter + representative valid ID (photocopy)
        and present original IDs.
      </div>
    </div>

    <!-- Membership section -->
    <div class="card grp req-group">
      <div class="grp-hdr">
        <div class="grp-title-row">
          <span class="grp-dot dot-g"></span>
          <h2 class="grp-title">Membership</h2>
        </div>
        <p class="grp-sub">Registration, renewal, amendment</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 p-6">

        <!-- Registration direct -->
        <button
          class="req-card group bg-white border border-gray-200 rounded-xl p-5 text-left hover:border-[#4caf50] hover:shadow-lg transition-all outline-none w-full"
          data-title="Membership Registration" data-badge="Direct" data-badgecolor="indigo"
          data-desc="Direct Contributor (Employed private/government, professional, self-earning, kasambahay, migrant worker, lifetime member, dual citizen, family driver)."
          data-req='["GENERAL REQUIREMENTS (ALL DIRECT MEMBERS):","• Birth Certificate / Baptismal Certificate / Valid ID (1 photocopy)","• Birth Certificate with registry number from LCR/PSA; OR Baptismal Certificate with registry number (1 original copy)","• Duly accomplished PMRF duly signed by the member (1 original copy)","","IF NO VALID DOCUMENT/ID:","• Notarized Affidavit of two (2) disinterested persons attesting to date of birth (1 original copy)","","EMPLOYED MEMBERS:","• Valid ID of the authorized signatory (photo & signature bearing) (1 photocopy)","","PROFESSIONAL PRACTITIONER / SELF-EARNING / MIGRANT WORKER / DUAL CITIZEN:","• Income Tax Return / Employment Contract / Financial Statement / Proof of Income (1 photocopy)","• If unable to present proof of income: Duly accomplished PMRF with monthly income indicated (1 original copy)","","LIFETIME MEMBER:","• Retirement Certification / General Order / Special Order / Retirement Voucher (1 photocopy)","• If unable to present Certificate of Active Membership: City/Municipal Link Certification (1 original copy)"]'>
          <h3 class="font-extrabold text-base text-[#14381e]">Registration (Direct)</h3>
          <p class="mt-1 text-sm text-gray-500 leading-5">Register as Direct Contributor</p>
          <span
            class="inline-block mt-2 px-2.5 py-1 rounded-full text-xs font-bold border bg-indigo-50 text-indigo-700 border-indigo-200 shadow-sm">Direct</span>
          <p class="mt-3 text-xs text-gray-400">Tap to view</p>
          <span class="inline-block mt-1 text-sm font-semibold text-[#2d7a3a] group-hover:underline">View →</span>
        </button>

        <!-- Registration indirect -->
        <button
          class="req-card group bg-white border border-gray-200 rounded-xl p-5 text-left hover:border-[#4caf50] hover:shadow-lg transition-all outline-none w-full"
          data-title="Membership Registration" data-badge="Indirect" data-badgecolor="emerald"
          data-desc="Indirect Contributor (Listahanan, 4Ps/MCCT, senior citizen, LGU/private-sponsored, PWD)."
          data-req='["GENERAL REQUIREMENTS (ALL INDIRECT MEMBERS):","• Birth Certificate / Baptismal Certificate / Valid ID (1 photocopy)","• Birth Certificate with registry number from LCR/PSA; OR Baptismal Certificate with registry number (1 original copy)","• Duly accomplished PMRF duly signed by the member (1 original copy)","","IF NO VALID DOCUMENT/ID:","• Notarized Affidavit of two (2) disinterested persons attesting to date of birth (1 original copy)","","LISTAHANAN & 4Ps/MCCT:","• Certificate of Active Membership with 4Ps ID (1 photocopy)","","SENIOR CITIZEN:","• OSCA ID (1 photocopy)","• Valid government-issued ID with date of birth","• If unable to present Senior Citizen ID: Birth Certificate (1 original copy)","","PERSON WITH DISABILITY (PWD):","• PWD Card (registered under DOH Philippine Registry of PWD/DOH-PRPWD) (1 photocopy)"]'>
          <h3 class="font-extrabold text-base text-[#14381e]">Registration (Indirect)</h3>
          <p class="mt-1 text-sm text-gray-500 leading-5">Register as Indirect Contributor</p>
          <span
            class="inline-block mt-2 px-2.5 py-1 rounded-full text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-200 shadow-sm">Indirect</span>
          <p class="mt-3 text-xs text-gray-400">Tap to view</p>
          <span class="inline-block mt-1 text-sm font-semibold text-[#2d7a3a] group-hover:underline">View →</span>
        </button>

        <!-- Amendment card with slider -->
        <button
          class="req-card group bg-white border border-gray-200 rounded-xl p-5 text-left hover:border-[#4caf50] hover:shadow-lg transition-all outline-none w-full"
          data-title="Amendment of Member Data Record" data-badge="3 Types" data-badgecolor="indigo"
          data-desc="Swipe left/right: Any Category, Civil Status Correction, Update to OFW."
          data-reqmode="slider"
          data-variants='[{"label":"Any Category","desc":"General amendment (dependents/data updates)","req":["Original copy of duly accomplished PMRF","Photocopy of at least 1 valid photo-bearing ID of the member (present original ID)","Spouse: Photocopy of Marriage Contract/Certificate","Children: Photocopy of Birth Certificate or proof of adoption/guardianship","Parents: Photocopy of member Birth Certificate AND photocopy of any of the following: parent Birth Certificate, OSCA Senior Citizen ID, or any valid ID indicating parent date of birth","If through representative: original authorization letter + photocopy of representative valid photo-bearing ID (present original ID)"]},{"label":"Civil Status Correction","desc":"Married / Widowed / Single to Married","req":["Original copy of duly accomplished PMRF","Photocopy of at least 1 valid photo-bearing ID of the member (present original ID)","Photocopy of Marriage Contract/Certificate","Photocopy of Death Certificate of spouse","Photocopy of Certificate of No Marriage Record (CENOMAR)","Photocopy of legal documents as proof of Annulment / Legal Separation / Declaration of Absolute Nullity of Marriage (as applicable)","If through representative: original authorization letter + photocopy of representative valid photo-bearing ID (present original ID)"]},{"label":"Update to OFW","desc":"Overseas Filipino Worker","req":["Original copy of duly accomplished PMRF","Photocopy of at least 1 valid photo-bearing ID of the member (present original ID)","Land-based OFW: Photocopy of any of the following as proof of active OFW status:","• Valid OEC or E-receipt","• Valid Working Visa / Re-entry Permit","• Valid Job Employment Contract","• Valid worker ID card issued by host country (e.g., HK ID, Iqama, etc.)","• Any equivalent document proving active OFW status (subject to approval of authorized PhilHealth officer)","Sea-based OFW: Original copy of PhilHealth ER2 duly accomplished by current employer/manning agency","If through representative: original authorization letter + photocopy of representative valid photo-bearing ID (present original ID)"]}]'>
          <h3 class="font-extrabold text-base text-[#14381e]">Amendment</h3>
          <p class="mt-1 text-sm text-gray-500 leading-5">Swipe: Civil Status / OFW</p>
          <span
            class="inline-block mt-2 px-2.5 py-1 rounded-full text-xs font-bold border bg-indigo-50 text-indigo-700 border-indigo-200 shadow-sm">3
            Types</span>
          <p class="mt-3 text-xs text-gray-400">Tap to view</p>
          <span class="inline-block mt-1 text-sm font-semibold text-[#2d7a3a] group-hover:underline">View →</span>
        </button>

        <!-- Membership renewal -->
        <button
          class="req-card group bg-white border border-gray-200 rounded-xl p-5 text-left hover:border-[#4caf50] hover:shadow-lg transition-all outline-none w-full"
          data-title="Membership Renewal (Reactivation)" data-badge="Renewal" data-badgecolor="indigo"
          data-desc="Reactivation for inactive membership status."
          data-req='["Duly accomplished PMRF duly signed by the member (1 original copy)","Valid ID (1 photocopy)","Proof of Payment Contribution","IF EMPLOYED:","• Payslip showing PhilHealth deduction","• Certificate of Contribution from employer","• Employer&#39;s remittance report","IF SELF-EMPLOYED:","• Official receipt from PhilHealth office OR payment receipt from accredited collecting agents/payment centers"]'>
          <h3 class="font-extrabold text-base text-[#14381e]">Membership Renewal</h3>
          <p class="mt-1 text-sm text-gray-500 leading-5">Reactivate membership</p>
          <span
            class="inline-block mt-2 px-2.5 py-1 rounded-full text-xs font-bold border bg-indigo-50 text-indigo-700 border-indigo-200 shadow-sm">Renewal</span>
          <p class="mt-3 text-xs text-gray-400">Tap to view</p>
          <span class="inline-block mt-1 text-sm font-semibold text-[#2d7a3a] group-hover:underline">View →</span>
        </button>
      </div>
    </div>

    <!-- Benefit section -->
    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 mb-10">
      <div class="px-6 pt-5 pb-4 border-b border-gray-100">
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-[#10b981]"></span>
          <h2 class="text-lg font-extrabold text-[#14381e]"></h2>Benefit Availment
        </div>
        <p class="mt-1 text-xs text-gray-400 pl-4">Hospitalization verification, coverage assessment, Other Benefit claims </p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 p-6">

        <!-- Hospitalization verification -->
        <button
          class="req-card group bg-white border border-gray-200 rounded-xl p-5 text-left hover:border-[#4caf50] hover:shadow-lg transition-all outline-none w-full"
          data-title="Admission Verification" data-badge="Admission" data-badgecolor="emerald"
          data-desc="Requirements differ for Principal vs Dependent."
          data-req='["PRINCIPAL:","• Valid ID (1 photocopy)","• Member Data Record (MDR)","• Hospital Admission Slip","• If through representative: authorization letter + representative ID (photocopy; present original)","","DEPENDENT:","• Valid ID of Member (1 photocopy)","• Birth Certificate (child) / Marriage Certificate (spouse)","• Admission Slip","• If through representative: authorization letter + representative ID (photocopy; present original)"]'>
          <h3 class="font-extrabold text-base text-[#14381e]">Hospitalization Verification</h3>
          <p class="mt-1 text-sm text-gray-500 leading-5">Principal / Dependent</p>
          <span
            class="inline-block mt-2 px-2.5 py-1 rounded-full text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-200 shadow-sm">Hospitalization</span>
          <p class="mt-3 text-xs text-gray-400">Tap to view</p>
          <span class="inline-block mt-1 text-sm font-semibold text-[#2d7a3a] group-hover:underline">View →</span>
        </button>

        <!-- Coverage assessment -->
        <button
          class="req-card group bg-white border border-gray-200 rounded-xl p-5 text-left hover:border-[#4caf50] hover:shadow-lg transition-all outline-none w-full"
          data-title="Benefit Coverage Assessment" data-badge="Coverage" data-badgecolor="emerald"
          data-desc="Requirements differ for Principal vs Dependent."
          data-req='["PRINCIPAL:","• Member Data Record (MDR)","• Hospital Billing Statement","• Valid ID (1 photocopy)","","DEPENDENT:","• MDR of Principal Member","• Hospital SOA / billing statement","• Proof of relationship","• Valid ID (1 photocopy)"]'>
          <h3 class="font-extrabold text-base text-[#14381e]">Benefit Coverage Assessment</h3>
          <p class="mt-1 text-sm text-gray-500 leading-5">Principal / Dependent</p>
          <span
            class="inline-block mt-2 px-2.5 py-1 rounded-full text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-200 shadow-sm">Coverage</span>
          <p class="mt-3 text-xs text-gray-400">Tap to view</p>
          <span class="inline-block mt-1 text-sm font-semibold text-[#2d7a3a] group-hover:underline">View →</span>
        </button>

        <!-- Other Benefit Claims with slider -->
        <button
          class="req-card group bg-white border border-gray-200 rounded-xl p-5 text-left hover:border-[#4caf50] hover:shadow-lg transition-all outline-none w-full"
          data-title="Hospital Claims Processing" data-badge="Claims" data-badgecolor="emerald"
          data-desc="Requirements differ for Principal vs Dependent. Swipe to switch."
          data-reqmode="slider"
          data-variants='[{"label":"Principal","desc":"Principal member","req":["Claim Forms (CF1–CF4 / CSF)","Acknowledgment Receipt Form","Hospital & Doctor&#39;s Waiver","Official Receipts","Statement of Account (SOA)","If through representative: original authorization letter + photocopy of representative valid photo-bearing ID (present original ID)"]},{"label":"Dependent","desc":"Dependent of member","req":["Claim Forms","Statement of Account (SOA)","Official Receipts","Birth/Marriage Certificate","If through representative: original authorization letter + photocopy of representative valid photo-bearing ID (present original ID)"]}]'>
          <h3 class="font-extrabold text-base text-[#14381e]">Other Benefit Claims </h3>
          <p class="mt-1 text-sm text-gray-500 leading-5">Principal / Dependent</p>
          <span
            class="inline-block mt-2 px-2.5 py-1 rounded-full text-xs font-bold border bg-emerald-50 text-emerald-700 border-emerald-200 shadow-sm">Claims</span>
          <p class="mt-3 text-xs text-gray-400">Tap to view</p>
          <span class="inline-block mt-1 text-sm font-semibold text-[#2d7a3a] group-hover:underline">View →</span>
        </button>
      </div>
    </div>

    <!-- Footer -->
    <footer class="text-center text-xs text-white/70 pt-6 pb-2">
      For questions, please contact the help desk before your scheduled appointment.
    </footer>
  </main>

  <!-- Bottom sheet popup -->
  <div id="sheetOverlay" class="sheet-ov fixed inset-0 z-50" aria-hidden="true">
    <div id="sheetBackdrop" class="sheet-bd absolute inset-0 bg-black/40 backdrop-blur-sm"></div>

    <div class="absolute inset-x-0 bottom-0 sm:inset-0 flex items-end sm:items-center justify-center">
      <div id="sheetCard"
        class="sheet-card w-full max-w-[560px] bg-white rounded-t-3xl sm:rounded-3xl overflow-hidden flex flex-col max-h-[92dvh] sm:max-h-[88dvh] shadow-2xl">

        <!-- Mobile drag line -->
        <div class="pt-3.5 pb-1.5 flex justify-center sm:hidden">
          <div class="w-9 h-1 bg-gray-200 rounded-full"></div>
        </div>

        <!-- Top color line -->
        <div class="h-1 bg-gradient-to-r from-[#1a4d2e] via-[#4caf50] to-[#e6a817]"></div>

        <!-- Popup header -->
        <div class="px-6 pt-5 pb-4 border-b border-gray-100 relative">
          <button id="sheetClose"
            class="absolute right-4 top-4 bg-gray-100 border border-gray-200 rounded-xl p-1.5 text-gray-500 hover:bg-gray-200 transition">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd"
                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                clip-rule="evenodd" />
            </svg>
          </button>

          <div class="flex items-center gap-2 flex-wrap pr-10">
            <div id="sheetTitle" class="font-extrabold text-[#14381e] text-lg">Title</div>
            <span id="sheetBadge"
              class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold border bg-indigo-50 text-indigo-700 border-indigo-200 shadow-sm">Badge</span>
          </div>

          <p id="sheetDesc" class="mt-1 text-sm text-gray-500 pr-10">Description</p>
        </div>

        <!-- Popup body -->
        <div class="flex-1 overflow-y-auto px-6 py-5">
          <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Requirements</p>

          <!-- Normal list -->
          <ul id="sheetList" class="flex flex-col gap-2 text-gray-700"></ul>

          <!-- Slider content -->
          <div id="sheetSlider" class="hidden">
            <div class="flex items-center justify-between gap-3 mb-3">
              <div>
                <div id="variantLabel" class="font-extrabold text-sm text-gray-800">Type</div>
                <div id="variantDesc" class="text-xs text-gray-400 mt-0.5"></div>
              </div>

              <div class="flex gap-1.5 flex-shrink-0">
                <button type="button" id="prevVariant"
                  class="px-3.5 py-1.5 rounded-xl border-2 border-gray-200 bg-gray-50 text-sm font-bold text-gray-600 hover:bg-gray-100">
                  ← Prev
                </button>
                <button type="button" id="nextVariant"
                  class="px-3.5 py-1.5 rounded-xl border-2 border-gray-200 bg-gray-50 text-sm font-bold text-gray-600 hover:bg-gray-100">
                  Next →
                </button>
              </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200">
              <div id="variantTrack" class="flex transition-transform duration-300 ease-[cubic-bezier(0.4,0,0.2,1)]">
              </div>
            </div>

            <div id="variantDots" class="flex justify-center gap-1.5 mt-3"></div>
            <p class="text-center text-xs text-gray-400 mt-2">Swipe left/right to switch types</p>
          </div>

          <!-- Reminder note -->
          <div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-800">
            Bring originals and photocopies. Requirements may vary — verify with the PhilHealth office if needed.
          </div>
        </div>

        <!-- Popup footer buttons -->
        <div class="px-6 py-4 border-t border-gray-100 flex gap-2.5 bg-white">
          <a href="/philhealth_queue/download_pmrf.php"
            class="flex-1 inline-flex items-center justify-center bg-[#1a4d2e] text-white font-bold text-sm px-5 py-3 rounded-xl hover:bg-[#2d7a3a] transition shadow-md">
            Download PMRF Form
          </a>

          <button type="button" id="sheetDone"
            class="flex-1 inline-flex items-center justify-center px-5 py-3 rounded-xl border-2 border-gray-200 bg-gray-50 text-sm font-bold text-gray-600 hover:bg-gray-100">
            Done
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- JavaScript -->
  <script>
    // Get elements
    const search = document.getElementById('req-search');
    const cards = Array.from(document.querySelectorAll('.req-card'));

    const overlay = document.getElementById('sheetOverlay');
    const backdrop = document.getElementById('sheetBackdrop');
    const sheetCard = document.getElementById('sheetCard');

    const sheetTitle = document.getElementById('sheetTitle');
    const sheetDesc = document.getElementById('sheetDesc');
    const sheetBadge = document.getElementById('sheetBadge');
    const sheetList = document.getElementById('sheetList');

    const sheetClose = document.getElementById('sheetClose');
    const sheetDone = document.getElementById('sheetDone');
    const sheetSlider = document.getElementById('sheetSlider');

    const variantTrack = document.getElementById('variantTrack');
    const variantDots = document.getElementById('variantDots');
    const variantLabel = document.getElementById('variantLabel');
    const variantDesc = document.getElementById('variantDesc');

    const prevVariant = document.getElementById('prevVariant');
    const nextVariant = document.getElementById('nextVariant');

    // Slider values
    let sliderState = {
      active: false,
      index: 0,
      variants: []
    };

    let txSX = null;
    let txSY = null;

    // Search cards
    search.addEventListener('input', e => {
      const q = e.target.value.toLowerCase().trim();

      cards.forEach(card => {
        const text =
          card.innerText.toLowerCase() + ' ' +
          (card.dataset.title || '').toLowerCase() + ' ' +
          (card.dataset.desc || '').toLowerCase() + ' ' +
          (card.dataset.variants || '').toLowerCase();

        card.style.display = (!q || text.includes(q)) ? '' : 'none';
      });
    });

    // Change badge color
    function bdgClass(color) {
      if (color === 'emerald') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
      if (color === 'amber') return 'bg-amber-50 text-amber-700 border-amber-200';
      return 'bg-indigo-50 text-indigo-700 border-indigo-200';
    }

    // Safe text output
    function esc(text) {
      return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    }

    // Show normal requirement list
    function renderList(element, items) {
      element.innerHTML = '';

      (items || []).forEach(item => {
        const li = document.createElement('li');
        li.innerHTML = `<span class="li-dot hidden"></span><span>${esc(item)}</span>`;
        element.appendChild(li);
      });
    }

    // Change active slider page
    function setSldrIdx(i) {
      const total = sliderState.variants.length;
      if (!total) return;

      sliderState.index = (i + total) % total;
      variantTrack.style.transform = `translateX(-${sliderState.index * 100}%)`;

      const current = sliderState.variants[sliderState.index];
      variantLabel.textContent = current.label || 'Type';
      variantDesc.textContent = current.desc || '';

      Array.from(variantDots.children).forEach((dot, idx) => {
        dot.classList.toggle('active', idx === sliderState.index);
      });
    }

    // Start slider
    function initSldr(variants) {
      sliderState = {
        active: true,
        index: 0,
        variants: Array.isArray(variants) ? variants : []
      };

      sheetList.style.display = 'none';
      sheetSlider.classList.remove('hidden');
      variantTrack.innerHTML = '';

      sliderState.variants.forEach(variant => {
        const slide = document.createElement('div');
        slide.className = 'w-full flex-shrink-0 p-4';

        const ul = document.createElement('ul');
        ul.className = 'flex flex-col gap-2 text-gray-700';

        renderList(ul, variant.req || []);
        slide.appendChild(ul);
        variantTrack.appendChild(slide);
      });

      variantDots.innerHTML = '';

      sliderState.variants.forEach((_, idx) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'w-1.5 h-1.5 rounded-full bg-gray-300 border-none cursor-pointer transition-colors' + (idx === 0 ? ' active bg-[#2d7a3a]' : '');
        dot.addEventListener('click', () => setSldrIdx(idx));
        variantDots.appendChild(dot);
      });

      setSldrIdx(0);
    }

    // Remove slider
    function destroySldr() {
      sliderState = {
        active: false,
        index: 0,
        variants: []
      };

      sheetSlider.classList.add('hidden');
      sheetList.style.display = '';
      variantTrack.innerHTML = '';
      variantDots.innerHTML = '';
    }

    // Swipe start
    variantTrack.addEventListener('touchstart', e => {
      if (!sliderState.active) return;
      const t = e.touches[0];
      txSX = t.clientX;
      txSY = t.clientY;
    }, {
      passive: true
    });

    // Swipe end
    variantTrack.addEventListener('touchend', e => {
      if (!sliderState.active || txSX == null) return;

      const t = e.changedTouches[0];
      const dx = t.clientX - txSX;
      const dy = t.clientY - txSY;

      txSX = null;
      txSY = null;

      if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy)) return;

      setSldrIdx(sliderState.index + (dx < 0 ? 1 : -1));
    }, {
      passive: true
    });

    // Slider buttons
    prevVariant.addEventListener('click', () => setSldrIdx(sliderState.index - 1));
    nextVariant.addEventListener('click', () => setSldrIdx(sliderState.index + 1));

    // Open popup
    function openSheet(card) {
      sheetTitle.textContent = card.dataset.title || 'Requirements';
      sheetDesc.textContent = card.dataset.desc || '';

      sheetBadge.className =
        'inline-flex px-2.5 py-1 rounded-full text-xs font-bold border ' +
        bdgClass(card.dataset.badgecolor || 'indigo');

      sheetBadge.textContent = card.dataset.badge || '';

      destroySldr();
      sheetList.innerHTML = '';

      if (card.dataset.reqmode === 'slider') {
        let variants = [];
        try {
          variants = JSON.parse(card.dataset.variants || '[]');
        } catch (_) {}
        initSldr(variants);
      } else {
        let reqs = [];
        try {
          reqs = JSON.parse(card.dataset.req || '[]');
        } catch (_) {}
        renderList(sheetList, reqs);
      }

      overlay.classList.add('open');
      overlay.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';

      requestAnimationFrame(() => {
        backdrop.classList.add('vis');
        sheetCard.classList.add('vis');

        const body = sheetCard.querySelector('.overflow-y-auto');
        if (body) body.scrollTop = 0;
      });
    }

    // Close popup
    function closeSheet() {
      backdrop.classList.remove('vis');
      sheetCard.classList.remove('vis');

      const done = () => {
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        destroySldr();
        backdrop.removeEventListener('transitionend', done);
      };

      backdrop.addEventListener('transitionend', done);
    }

    // Open popup when card is clicked
    cards.forEach(card => {
      card.addEventListener('click', () => openSheet(card));

      card.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          openSheet(card);
        }
      });
    });

    // Close popup buttons
    sheetClose.addEventListener('click', closeSheet);
    sheetDone.addEventListener('click', closeSheet);
    backdrop.addEventListener('click', closeSheet);

    // Close popup using ESC key
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && overlay.classList.contains('open')) {
        closeSheet();
      }
    });
  </script>
</body>

</html>