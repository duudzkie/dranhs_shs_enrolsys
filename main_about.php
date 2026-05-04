<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'components/main_head.php'; ?>
    <title>About Us — DRANHS</title>
    <meta name="description" content="Learn about Daniel R. Aguinaldo National High School's mission, vision, and history.">
</head>
<body>

<?php include 'components/main_navbar.php'; ?>

<div class="page-header">
    <div class="container">
        <span class="section-tag">About Us</span>
        <h1>Our Story & Mission</h1>
        <p>Over 50 years of nurturing excellence in education at the heart of Davao City.</p>
    </div>
</div>

<!-- Mission / Vision -->
<section class="section">
    <div class="container">
        <div class="features-grid" style="grid-template-columns: repeat(2, 1fr);">
            <div class="feature-card reveal">
                <div class="feature-icon" style="background: #dbeafe; color: #2563eb;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                </div>
                <h3>Our Mission</h3>
                <p>To provide quality, equitable, culture-based, and complete basic education, ensuring every learner develops holistically and becomes a productive and responsible citizen.</p>
            </div>
            <div class="feature-card reveal">
                <div class="feature-icon" style="background: #fef3c7; color: #d97706;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </div>
                <h3>Our Vision</h3>
                <p>We dream of Filipinos who passionately love their country and whose values and competencies enable them to realize their full potential and contribute meaningfully to building the nation.</p>
            </div>
        </div>
    </div>
</section>

<!-- Core Values -->
<section class="section" style="background: var(--slate-100);">
    <div class="container">
        <div class="section-header reveal">
            <span class="section-tag">Core Values</span>
            <h2 class="section-title">What We Stand For</h2>
        </div>
        <div class="features-grid">
            <div class="feature-card reveal">
                <div class="feature-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
                <h3>Excellence</h3>
                <p>Striving for the highest standards in academics, leadership, and character formation.</p>
            </div>
            <div class="feature-card reveal">
                <div class="feature-icon" style="background: #ede9fe; color: #7c3aed;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></div>
                <h3>Integrity</h3>
                <p>Building a community rooted in honesty, accountability, and ethical behavior.</p>
            </div>
            <div class="feature-card reveal">
                <div class="feature-icon" style="background: #fce7f3; color: #db2777;"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <h3>Service</h3>
                <p>Fostering a spirit of volunteerism and commitment to community development.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="container reveal">
        <h2>Be Part of Our Legacy</h2>
        <p>Join the DRANHS family and write the next chapter of our story.</p>
        <a href="main_portal.php" class="btn btn-primary" style="font-size:1rem; padding:16px 36px;">Visit Portal</a>
    </div>
</section>

<?php include 'components/main_footer.php'; ?>
<script src="main.js"></script>
</body>
</html>
