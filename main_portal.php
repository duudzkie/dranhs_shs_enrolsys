<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'components/main_head.php'; ?>
    <title>Student Portal — DRANHS</title>
    <meta name="description" content="Access enrollment, grade viewing, learning resources, and more.">
</head>
<body>

<?php include 'components/main_navbar.php'; ?>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <span class="section-tag">Student Portal</span>
        <h1>School Systems & Services</h1>
        <p>Access all DRANHS digital services from one place. Select a system below to get started.</p>
    </div>
</div>

<!-- Portal Cards -->
<section class="section">
    <div class="container">
        <div class="portal-grid">

            <!-- SHS Enrollment — ACTIVE -->
            <div class="portal-card portal-card--active reveal">
                <div class="portal-card-icon" style="background: #dcfce7; color: #16a34a;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422A12.083 12.083 0 0118.825 17.057 11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                </div>
                <h3>SHS Enrollment</h3>
                <p>Official online enrollment for Senior High School — Grade 11 & Grade 12 students.</p>
                <a href="EMS2/index.php" class="btn btn-primary">Open System</a>
            </div>

            <!-- JHS Enrollment — COMING SOON -->
            <div class="portal-card portal-card--soon reveal">
                <div class="portal-card-icon" style="background: #dbeafe; color: #2563eb;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </div>
                <h3>JHS Enrollment</h3>
                <p>Online enrollment portal for Junior High School — Grade 7 to Grade 10.</p>
                <span class="coming-soon-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Coming Soon
                </span>
            </div>

            <!-- Grade Viewing — COMING SOON -->
            <div class="portal-card portal-card--soon reveal">
                <div class="portal-card-icon" style="background: #fef3c7; color: #d97706;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <h3>Grade Viewing</h3>
                <p>View your academic records, quarterly grades, and general weighted average online.</p>
                <span class="coming-soon-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Coming Soon
                </span>
            </div>

            <!-- Learning Resources — COMING SOON -->
            <div class="portal-card portal-card--soon reveal">
                <div class="portal-card-icon" style="background: #ede9fe; color: #7c3aed;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                </div>
                <h3>Learning Resources</h3>
                <p>Access study materials, modules, and digital resources shared by your teachers.</p>
                <span class="coming-soon-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Coming Soon
                </span>
            </div>

            <!-- Library System — COMING SOON -->
            <div class="portal-card portal-card--soon reveal">
                <div class="portal-card-icon" style="background: #fce7f3; color: #db2777;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                </div>
                <h3>Library System</h3>
                <p>Browse the school library catalog, reserve books, and track borrowed materials.</p>
                <span class="coming-soon-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Coming Soon
                </span>
            </div>

            <!-- Attendance Tracker — COMING SOON -->
            <div class="portal-card portal-card--soon reveal">
                <div class="portal-card-icon" style="background: #d1fae5; color: #059669;">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 16l2 2 4-4"/></svg>
                </div>
                <h3>Attendance Tracker</h3>
                <p>Monitor your daily attendance records and view attendance summaries per subject.</p>
                <span class="coming-soon-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Coming Soon
                </span>
            </div>

        </div>
    </div>
</section>

<?php include 'components/main_footer.php'; ?>
<script src="main.js"></script>
</body>
</html>
