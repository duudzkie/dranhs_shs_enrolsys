<!-- Main Hero Section (components/hero.php) -->
<main class="flex-1 flex justify-center items-center px-4 pt-[130px] pb-[100px] lg:pt-[80px] lg:pb-[80px] relative z-10 w-full min-h-[calc(100vh-200px)] lg:min-h-0">
    <div class="text-center max-w-4xl w-full mx-auto">
        <h1 class="text-5xl md:text-6xl lg:text-[5.5rem] font-heading font-black leading-none mb-1 text-dranhs-dark uppercase tracking-tight">
            SOAR HIGH
        </h1>
        <h1 class="text-5xl md:text-6xl lg:text-[5.5rem] font-heading font-black italic leading-none mb-6 text-dranhs-green uppercase tracking-tight">
            FUTURE AGILA.
        </h1>
        <p class="text-sm md:text-base lg:text-lg text-slate-600 mb-8 lg:mb-10 font-medium max-w-2xl mx-auto leading-relaxed px-2">
            Empowering the next generation of leaders. Join Davao's most dynamic and innovative senior high school community today.
        </p>
        <?php if (isset($enrollment_locked) && $enrollment_locked): ?>
            <button disabled class="bg-slate-300 text-slate-500 py-3.5 px-8 lg:py-4 lg:px-10 rounded-full font-bold text-base lg:text-lg cursor-not-allowed inline-flex items-center gap-3 shadow-sm w-full max-w-sm mx-auto justify-center uppercase tracking-wide">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                ENROLLMENT CLOSED
            </button>
        <?php else: ?>
            <button id="btn-start-enroll" class="bg-dranhs-green hover:bg-emerald-700 text-white border-none py-3.5 px-8 lg:py-4 lg:px-10 rounded-full font-bold text-base lg:text-lg cursor-pointer inline-flex items-center gap-3 transition-all duration-300 shadow-lg hover:shadow-xl hover:-translate-y-1 w-full max-w-sm mx-auto justify-center uppercase tracking-wide">
                OFFICIAL ENROLLMENT
            </button>
        <?php endif; ?>
        <noscript>
            <p class="text-sm font-medium text-red-600 mt-4">
                JavaScript is required for the modal. <a href="enrollment_form_g11.php" class="underline">Click here to enroll</a>.
            </p>
        </noscript>
    </div>
</main>
