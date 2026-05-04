<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$settingsFile = __DIR__ . '/page_settings.json';
$defaultSettings = [
    'site_title' => 'DRANHS Interactive Site',
    'pages' => [
        'index' => [
            'name' => 'Home',
            'title' => 'Welcome to DRANHS',
            'subtitle' => 'The official school web portal',
            'content' => 'Use the admin panel to update homepage sections, carousel text, and featured content.'
        ],
        'main_about' => [
            'name' => 'About',
            'title' => 'About DRANHS',
            'subtitle' => 'Learn about our values and mission',
            'content' => 'Update this content from the admin system settings page.'
        ],
        'main_events' => [
            'name' => 'Events',
            'title' => 'School Events',
            'subtitle' => 'See what\'s happening around the campus',
            'content' => 'Describe your latest events and announcements here.'
        ],
        'main_downloads' => [
            'name' => 'Downloadables',
            'title' => 'Downloadable Forms and Resources',
            'subtitle' => 'Quick access to important files',
            'content' => 'Add, remove, or update downloadable resources from the admin area.'
        ],
        'main_contact' => [
            'name' => 'Contact',
            'title' => 'Contact Us',
            'subtitle' => 'Get in touch with the school',
            'content' => 'Update the contact page text and contact details here.'
        ]
    ]
];

if (!file_exists($settingsFile)) {
    file_put_contents($settingsFile, json_encode($defaultSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$settings = json_decode(file_get_contents($settingsFile), true);
if (!is_array($settings)) {
    $settings = $defaultSettings;
}

$pageOptions = [
    'index' => 'Home',
    'main_about' => 'About',
    'main_events' => 'Events',
    'main_downloads' => 'Downloadables',
    'main_contact' => 'Contact'
];

$selectedPage = $_GET['page'] ?? 'index';
if (!array_key_exists($selectedPage, $pageOptions)) {
    $selectedPage = 'index';
}

$successMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedPage = $_POST['page'] ?? 'index';
    if (!array_key_exists($selectedPage, $pageOptions)) {
        $selectedPage = 'index';
    }
    $settings['pages'][$selectedPage]['title'] = trim($_POST['page_title'] ?? '');
    $settings['pages'][$selectedPage]['subtitle'] = trim($_POST['page_subtitle'] ?? '');
    $settings['pages'][$selectedPage]['content'] = trim($_POST['page_content'] ?? '');
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $successMessage = 'Saved settings for ' . $pageOptions[$selectedPage] . '.';
}

$currentPageData = $settings['pages'][$selectedPage] ?? $settings['pages']['index'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings | DRANHS</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-shell {
            max-width: 980px;
            margin: 0 auto;
            padding: 32px 18px 80px;
        }
        .admin-hero {
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.15);
            border-radius: 28px;
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.08);
            padding: 28px;
            margin-bottom: 26px;
        }
        .admin-hero h1 {
            margin-bottom: 6px;
        }
        .admin-panel {
            background: #ffffff;
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 24px;
            padding: 26px;
        }
        .admin-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 20px;
        }
        .admin-row-full {
            grid-column: 1 / -1;
        }
        .field-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .field-group label {
            font-weight: 700;
            color: #0f172a;
        }
        .field-group input,
        .field-group textarea,
        .field-group select {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            padding: 14px 16px;
            font-size: 0.95rem;
            resize: vertical;
            outline: none;
        }
        .field-group textarea {
            min-height: 160px;
        }
        .field-group select {
            appearance: none;
            background: #fff url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="%23333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"%3E%3Cpolyline points="6 9 12 15 18 9"/%3E%3C/svg%3E') no-repeat right 16px center / 12px 12px;
            padding-right: 44px;
        }
        .admin-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }
        .admin-meta .badge {
            display: inline-flex;
            align-items: center;
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid rgba(16, 163, 127, 0.2);
            background: rgba(16, 163, 127, 0.08);
            color: #115e42;
            font-size: 0.9rem;
            font-weight: 700;
        }
        .admin-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: flex-start;
            margin-top: 24px;
        }
        @media (max-width: 720px) {
            .admin-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900">
    <?php include 'components/main_navbar.php'; ?>
    <main class="admin-shell">
        <section class="admin-hero">
            <p class="section-tag">System Settings</p>
            <h1>Page Content Editor</h1>
            <p class="text-slate-600">Edit homepage and section page text from Home through Contact. Draft changes are stored in <code>page_settings.json</code>.</p>
        </section>

        <?php if ($successMessage): ?>
            <div class="alert alert-success" style="margin-bottom:20px; padding:18px 22px; border-radius:18px; background:#ecfdf5; border:1px solid #d1fae5; color:#064e3b;">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <section class="admin-panel">
            <div class="admin-meta">
                <div class="badge">Editing: <?php echo htmlspecialchars($pageOptions[$selectedPage]); ?></div>
                <form method="GET" style="display:inline-flex; align-items:center; gap:12px;">
                    <label for="page-select" class="font-bold">Select page</label>
                    <select id="page-select" name="page" onchange="this.form.submit()">
                        <?php foreach ($pageOptions as $pageKey => $pageLabel): ?>
                            <option value="<?php echo htmlspecialchars($pageKey); ?>" <?php echo $selectedPage === $pageKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($pageLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <form method="POST">
                <input type="hidden" name="page" value="<?php echo htmlspecialchars($selectedPage); ?>">
                <div class="admin-row">
                    <div class="field-group">
                        <label for="page_title">Page Title</label>
                        <input id="page_title" name="page_title" type="text" value="<?php echo htmlspecialchars($currentPageData['title'] ?? ''); ?>" required>
                    </div>
                    <div class="field-group">
                        <label for="page_subtitle">Page Subtitle</label>
                        <input id="page_subtitle" name="page_subtitle" type="text" value="<?php echo htmlspecialchars($currentPageData['subtitle'] ?? ''); ?>">
                    </div>
                </div>
                <div class="admin-row admin-row-full">
                    <div class="field-group">
                        <label for="page_content">Page Content</label>
                        <textarea id="page_content" name="page_content"><?php echo htmlspecialchars($currentPageData['content'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="admin-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="index.php" class="btn btn-outline">View Site</a>
                </div>
            </form>
        </section>
    </main>
    <?php include 'components/main_footer.php'; ?>
</body>
</html>
