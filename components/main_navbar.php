<?php
// Detect current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
function isActive($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>
<!-- ═══════════════════════════════════════════════
     NAVBAR — Shared Component
     ═══════════════════════════════════════════════ -->
<nav class="navbar" id="navbar">
    <div class="container navbar-inner">
        <!-- Brand -->
        <a href="index.php" class="nav-brand">
            <div class="nav-logo">
                <img src="https://ui-avatars.com/api/?name=DR&background=009b5a&color=fff&size=128&bold=true" alt="DRANHS Logo" id="nav-logo-img">
            </div>
            <div class="nav-brand-text">
                <span class="nav-brand-name">DRANHS</span>
                <span class="nav-brand-sub">Matina Crossing, Davao City</span>
            </div>
        </a>

        <!-- Desktop Links -->
        <ul class="nav-links">
            <li><a href="index.php" class="nav-link <?= isActive('index.php') ?>">Home</a></li>
            <li><a href="main_about.php" class="nav-link <?= isActive('main_about.php') ?>">About</a></li>
            <li><a href="main_events.php" class="nav-link <?= isActive('main_events.php') ?>">Events</a></li>
            <li><a href="main_portal.php" class="nav-link <?= isActive('main_portal.php') ?>">Portal</a></li>
            <li><a href="main_downloads.php" class="nav-link <?= isActive('main_downloads.php') ?>">Downloadables</a></li>
            <li><a href="main_contact.php" class="nav-link <?= isActive('main_contact.php') ?>">Contact</a></li>
            <li><a href="login.php" class="nav-link nav-link--login">Login</a></li>
        </ul>

        <!-- Hamburger -->
        <button class="nav-hamburger" id="nav-hamburger" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<!-- Mobile Menu Overlay -->
<div class="nav-mobile" id="nav-mobile">
    <a href="index.php" class="nav-link <?= isActive('index.php') ?>">Home</a>
    <a href="main_about.php" class="nav-link <?= isActive('main_about.php') ?>">About</a>
    <a href="main_events.php" class="nav-link <?= isActive('main_events.php') ?>">Events</a>
    <a href="main_portal.php" class="nav-link <?= isActive('main_portal.php') ?>">Portal</a>
    <a href="main_downloads.php" class="nav-link <?= isActive('main_downloads.php') ?>">Downloadables</a>
    <a href="main_contact.php" class="nav-link <?= isActive('main_contact.php') ?>">Contact</a>
    <a href="login.php" class="nav-link nav-link--login">Login</a>
</div>
