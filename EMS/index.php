<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DRANHS SmartEnrol - Enrollment Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <nav class="top-nav">
        <div class="nav-inner">
            <div class="brand">
                <img src="assets/DRANHS LOGO.png" alt="DRANHS Logo" class="brand-logo">
                <span class="brand-title">DRANHS SmartEnrol</span>
            </div>

            <div class="desktop-login">
                <input type="text" class="login-input" placeholder="Username" aria-label="Username">
                <input type="password" class="login-input" placeholder="Password" aria-label="Password">
                <button class="btn-secondary" type="button">Login</button>
            </div>
        </div>
    </nav>

    <main class="hero-wrap">
        <section class="hero-card">
            <p class="hero-kicker">Enrollment Management System</p>
            <h1 class="hero-title">Start Your Enrollment in Minutes</h1>
            <p class="hero-subtitle">A faster and more reliable way to submit requirements, review details, and complete enrollment at DRANHS.</p>
            <div class="hero-highlights">
                <span class="highlight-pill">&#128196; Guided Steps</span>
                <span class="highlight-pill">&#9201; Fast Processing</span>
                <span class="highlight-pill">&#128274; Secure Data</span>
            </div>
            <button class="btn-primary" type="button" id="openPreEnrollModal">&#10024; Start Pre-Enrollment</button>
        </section>
    </main>

    <div class="modal-overlay" id="preEnrollModal" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="preEnrollTitle">
            <div class="modal-head">
                <div class="modal-title-wrap">
                    <h2 id="preEnrollTitle">Choose Your Enrollment Level</h2>
                </div>
                <button class="modal-close" type="button" id="closePreEnrollModal" aria-label="Close modal">&times;</button>
            </div>

            <div class="modal-options">
                <button class="level-option" type="button" id="grade11Option">
                    <span class="level-icon" aria-hidden="true">&#127891;</span>
                    <span class="level-tag">Professional Pathway</span>
                    <span class="level-title">Incoming Grade 11</span>
                    <span class="level-desc">Build your foundation through career-aligned learning and practical technical skills.</span>
                    <span class="level-points">Industry-ready training, guided orientation, and track selection support.</span>
                </button>

                <button class="level-option" type="button" id="grade12Option">
                    <span class="level-icon" aria-hidden="true">&#128218;</span>
                    <span class="level-tag">STRAND</span>
                    <span class="level-title">Incoming Grade 12</span>
                    <span class="level-desc">Continue your strand with advanced subjects aligned to your college or career goals.</span>
                    <span class="level-points">Focused specialization, completion planning, and readiness checkpoints.</span>
                </button>
            </div>
        </div>
    </div>

    <footer class="fixed-footer">
        <div class="footer-inner">
            <div class="footer-actions">
                <button class="footer-btn" type="button">&#128269; Check Status</button>
                <button class="footer-btn" type="button">&#128205; Room Locator</button>
            </div>
            <p class="footer-copy">&copy; 2026 DRANHS SmartEnrol. All rights reserved.</p>
        </div>
    </footer>
    <script>
        const preEnrollModal = document.getElementById("preEnrollModal");
        const openPreEnrollModal = document.getElementById("openPreEnrollModal");
        const closePreEnrollModal = document.getElementById("closePreEnrollModal");
        const grade11Option = document.getElementById("grade11Option");
        const grade12Option = document.getElementById("grade12Option");

        openPreEnrollModal.addEventListener("click", function () {
            preEnrollModal.classList.add("show");
            preEnrollModal.setAttribute("aria-hidden", "false");
        });

        closePreEnrollModal.addEventListener("click", function () {
            preEnrollModal.classList.remove("show");
            preEnrollModal.setAttribute("aria-hidden", "true");
        });

        preEnrollModal.addEventListener("click", function (event) {
            if (event.target === preEnrollModal) {
                preEnrollModal.classList.remove("show");
                preEnrollModal.setAttribute("aria-hidden", "true");
            }
        });

        grade11Option.addEventListener("click", function () {
            alert("Grade 11 form is next to be added.");
        });

        grade12Option.addEventListener("click", function () {
            window.location.href = "grade12_enrollment.php";
        });
    </script>
</body>
</html>
