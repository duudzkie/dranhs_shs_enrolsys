<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'components/main_head.php'; ?>
    <title>DRANHS — Daniel R. Aguinaldo National High School</title>
    <meta name="description" content="Official website of Daniel R. Aguinaldo National High School — Matina Crossing, Davao City.">
</head>
<body>

<?php include 'components/main_navbar.php'; ?>

<!-- ═══════════════════════════════════════════════
     CAROUSEL
     ═══════════════════════════════════════════════ -->
<div class="carousel" id="carousel">
    <div class="carousel-track" id="carousel-track">
        <div class="carousel-slide" style="background:url('https://images.unsplash.com/photo-1523050854058-8df90110c6f6?w=1400&q=80') center/cover;">
            <div class="carousel-slide-content">
                <span class="carousel-slide-tag">📢 Announcement</span>
                <h2>Enrollment is Now Open for S.Y. 2026–2027</h2>
                <p>Secure your slot today. Visit our online enrollment portal and start your journey with DRANHS.</p>
            </div>
        </div>
        <div class="carousel-slide" style="background:url('https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?w=1400&q=80') center/cover;">
            <div class="carousel-slide-content">
                <span class="carousel-slide-tag">🏫 Campus Life</span>
                <h2>A Community Built on Excellence</h2>
                <p>Join a vibrant community of learners, educators, and leaders shaping the future of Davao.</p>
            </div>
        </div>
        <div class="carousel-slide" style="background:url('https://images.unsplash.com/photo-1541339907198-e08756dedf3f?w=1400&q=80') center/cover;">
            <div class="carousel-slide-content">
                <span class="carousel-slide-tag">🏆 Achievements</span>
                <h2>50+ Years of Academic Distinction</h2>
                <p>Producing nationally competitive students in academics, sports, and the arts since 1973.</p>
            </div>
        </div>
    </div>
    <button class="carousel-btn carousel-btn--prev" id="carousel-prev" aria-label="Previous">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
    <button class="carousel-btn carousel-btn--next" id="carousel-next" aria-label="Next">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
    <div class="carousel-dots" id="carousel-dots"></div>
</div>


<!-- ═══════════════════════════════════════════════
     HERO
     ═══════════════════════════════════════════════ -->
<section class="hero" id="home" style="min-height: auto; padding: 60px 24px;">
    <div class="hero-shapes"><div class="hero-shape"></div><div class="hero-shape"></div><div class="hero-shape"></div></div>
    <div class="hero-content">
        <div class="hero-badge"><span class="hero-badge-dot"></span> Official School Website</div>
        <h1 class="hero-title">Daniel R. Aguinaldo<br><span class="hero-title-accent">National High School</span></h1>
        <p class="hero-subtitle">Empowering the next generation of leaders. Soar high with Davao's most dynamic and innovative senior high school community.</p>
        <div class="hero-actions">
            <a href="main_portal.php" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                Student Portal
            </a>
            <a href="main_about.php" class="btn btn-outline">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                Learn More
            </a>
        </div>
    </div>
</section>


<!-- ═══════════════════════════════════════════════
     STATS BAR
     ═══════════════════════════════════════════════ -->
<div class="stats-bar">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item reveal"><div class="stat-number" data-count="5000" data-suffix="+">0</div><div class="stat-label">Students Enrolled</div></div>
            <div class="stat-item reveal"><div class="stat-number" data-count="200" data-suffix="+">0</div><div class="stat-label">Faculty Members</div></div>
            <div class="stat-item reveal"><div class="stat-number" data-count="15">0</div><div class="stat-label">SHS Strands</div></div>
            <div class="stat-item reveal"><div class="stat-number" data-count="50" data-suffix="+">0</div><div class="stat-label">Years of Excellence</div></div>
        </div>
    </div>
</div>


<!-- ═══════════════════════════════════════════════
     QUICK FEATURES
     ═══════════════════════════════════════════════ -->
<section class="section">
    <div class="container">
        <div class="section-header reveal">
            <span class="section-tag">Why DRANHS</span>
            <h2 class="section-title">Shaping Tomorrow's Leaders</h2>
        </div>
        <div class="features-grid">
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422A12.083 12.083 0 0118.825 17.057 11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                </div>
                <h3>Academic Excellence</h3>
                <p>A rigorous curriculum preparing students for college and career success across all SHS strands.</p>
            </div>
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <h3>Diverse Community</h3>
                <p>A vibrant learning community embracing inclusivity, mutual respect, and collaborative growth.</p>
            </div>
            <div class="feature-card reveal">
                <div class="feature-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <h3>Holistic Development</h3>
                <p>Leadership, creativity, and values nurtured through co-curricular activities and student orgs.</p>
            </div>
        </div>
    </div>
</section>


<!-- ═══════════════════════════════════════════════
     CTA
     ═══════════════════════════════════════════════ -->
<section class="cta-section">
    <div class="container reveal">
        <h2>Ready to Begin Your Journey?</h2>
        <p>Explore our student portal for enrollment, grade viewing, learning resources, and more.</p>
        <a href="main_portal.php" class="btn btn-primary" style="font-size:1rem; padding:16px 36px;">Go to Student Portal</a>
    </div>
</section>

<?php include 'components/main_footer.php'; ?>
<script src="main.js"></script>
</body>
</html>
