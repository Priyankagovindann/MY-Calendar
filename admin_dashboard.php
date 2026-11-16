
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyCalendar | Admin Dashboard</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --olive: #59633d;
            --olive-dark: #414a2c;
            --beige: #f3ecdf;
            --card: #fffaf2;
            --border: #e5dac8;
            --text: #343a24;
            --muted: #858678;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: var(--beige);
            color: var(--text);
            min-height: 100vh;
        }

        /* =========================
           SIDEBAR
        ========================= */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 287px;
            height: 100vh;
            padding: 30px 21px 20px;
            background: var(--olive);
            color: var(--white);
            display: flex;
            flex-direction: column;
            z-index: 100;
        }

        .brand {
            display: flex;
            align-items: center;
            padding: 8px 9px 0;
            margin-bottom: 34px;
        }

        /* Sidebar logo icon removed */
        .brand-name {
            color: var(--white);
            font-size: 23px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .menu-heading {
            margin: 0 13px 13px;
            color: #e0e4d2;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            min-height: 49px;
            padding: 13px 17px;
            border-radius: 11px;
            color: var(--white);
            text-decoration: none;
            font-size: 15px;
            font-weight: 400;
            transition: background 0.2s ease;
        }

        /* Sidebar navigation icons removed */
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.15);
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.11);
        }

        .sidebar-menu a.active:hover {
            background: rgba(255, 255, 255, 0.18);
        }

        .sidebar-spacer {
            flex: 1;
            min-height: 25px;
        }

        .sidebar-bottom {
            flex-shrink: 0;
        }

        .logout-link {
            margin-bottom: 14px;
        }

        .admin-profile {
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding: 20px 9px 9px;
            display: flex;
            align-items: center;
            gap: 13px;
        }

        /* Avatar icon removed; retain the circular theme */
        .admin-avatar {
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            border-radius: 50%;
            background: var(--beige);
            color: var(--olive);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            font-weight: 700;
        }

        .admin-info {
            min-width: 0;
        }

        .admin-info strong {
            display: block;
            font-size: 14px;
            margin-bottom: 7px;
        }

        .admin-info span {
            display: block;
            color: #e1e5d4;
            font-size: 12px;
        }

        /* =========================
           MAIN CONTENT
        ========================= */

        .main-content {
            margin-left: 287px;
            min-height: 100vh;
            padding: 37px 40px 32px 43px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 39px;
        }

        .eyebrow {
            margin-bottom: 8px;
            color: var(--olive);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .page-header h1 {
            color: var(--text);
            font-size: 33px;
            font-weight: 700;
            letter-spacing: -0.5px;
            line-height: 1.25;
        }

        .page-subtitle {
            margin-top: 11px;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.5;
        }

        .date-display {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--text);
            font-size: 14px;
        }

        /* =========================
           SUMMARY CARDS
        ========================= */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 23px;
            margin-bottom: 32px;
        }

        .stat-card {
            min-width: 0;
            min-height: 197px;
            padding: 26px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 17px;
            box-shadow: 0 5px 18px rgba(72, 66, 47, 0.025);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e7e8dc;
            color: var(--olive);
            border-radius: 15px;
            font-size: 21px;
            margin-bottom: 25px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-value {
            color: var(--text);
            font-size: 29px;
            font-weight: 700;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .stat-value.platform-name {
            font-size: 27px;
        }

        /* =========================
           LOWER CONTENT CARDS
        ========================= */

        .content-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(0, 1.08fr);
            gap: 23px;
        }

        .content-card {
            min-width: 0;
            min-height: 382px;
            padding: 27px 28px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 17px;
        }

        .content-card h2 {
            color: var(--text);
            font-size: 21px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .section-description {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 25px;
        }

        /* =========================
           QUICK ACCESS
        ========================= */

        .quick-access-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 13px;
        }

        .quick-link {
            min-width: 0;
            min-height: 58px;
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 14px 16px;
            border: 1px solid var(--border);
            border-radius: 13px;
            color: var(--text);
            background: var(--card);
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            transition: background 0.2s ease,
                        border-color 0.2s ease;
        }

        .quick-link i {
            color: var(--olive);
            font-size: 20px;
            flex-shrink: 0;
        }

        .quick-link:hover {
            background: #f4efe5;
            border-color: #cfc4af;
        }

        /* =========================
           ADMIN OVERVIEW
        ========================= */

        .overview-list {
            display: flex;
            flex-direction: column;
            margin-top: 4px;
        }

        .overview-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
        }

        .overview-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .overview-dot {
            width: 11px;
            height: 11px;
            margin-top: 6px;
            flex-shrink: 0;
            border-radius: 50%;
            background: var(--olive);
        }

        .overview-text {
            min-width: 0;
        }

        .overview-text strong {
            display: block;
            color: var(--text);
            font-size: 14px;
            margin-bottom: 7px;
        }

        .overview-text p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.65;
            overflow-wrap: anywhere;
        }

        /* =========================
           RESPONSIVE DESIGN
        ========================= */

        @media (min-width: 1500px) {
            .main-content {
                padding-right: 40px;
            }
        }

        @media (max-width: 1200px) {
            .sidebar {
                width: 245px;
            }

            .main-content {
                margin-left: 245px;
                padding: 30px 25px;
            }

            .stats-grid {
                gap: 16px;
            }

            .stat-card {
                padding: 22px;
            }

            .content-grid {
                grid-template-columns: minmax(0, 1fr);
            }

            .content-card {
                min-height: auto;
            }
        }

        @media (max-width: 760px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
                min-height: auto;
                padding: 20px;
            }

            .brand {
                margin-bottom: 24px;
                padding: 0 8px;
            }

            .brand-name {
                font-size: 22px;
            }

            .menu-heading {
                margin-top: 0;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
            }

            .sidebar-menu a {
                padding: 12px;
                min-height: 45px;
                font-size: 14px;
            }

            .sidebar-spacer {
                display: none;
            }

            .sidebar-bottom {
                margin-top: 20px;
            }

            .logout-link {
                margin-bottom: 14px;
            }

            .admin-profile {
                padding-top: 15px;
            }

            .main-content {
                margin-left: 0;
                padding: 26px 20px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
                margin-bottom: 27px;
            }

            .page-header h1 {
                font-size: 28px;
            }

            .page-subtitle {
                font-size: 14px;
            }

            .date-display {
                font-size: 13px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
                margin-bottom: 23px;
            }

            .stat-card {
                min-height: auto;
                padding: 21px;
            }

            .stat-icon {
                margin-bottom: 18px;
            }

            .stat-value {
                font-size: 27px;
            }

            .content-grid {
                gap: 18px;
            }

            .content-card {
                padding: 22px 19px;
            }

            .content-card h2 {
                font-size: 19px;
            }
        }

        @media (max-width: 420px) {
            .main-content {
                padding: 23px 13px;
            }

            .quick-access-grid {
                grid-template-columns: 1fr;
            }

            .quick-link {
                min-height: 52px;
            }

            .sidebar-menu a {
                font-size: 13px;
                padding: 11px 9px;
            }
        }
    </style>
</head>

<body>

    <!-- SIDEBAR: NO ICONS -->
    <aside class="sidebar">

        <div class="brand">
            <span class="brand-name">MyCalendar</span>
        </div>

        <div class="menu-heading">Administration</div>

        <div class="sidebar-menu">
            <a href="#home" class="active" data-page="home">
                Home
            </a>

            <a href="admin_user.php">
                Users
            </a>

            <a href="admin_activity.php">
                User Activity
            </a>

            <a href="#accounts" data-page="accounts">
                Account Management
            </a>

            <a href="#profile" data-page="profile">
                Admin Profile
            </a>
        </div>

        <div class="sidebar-spacer"></div>

        <div class="sidebar-bottom">

            <div class="sidebar-menu">
                <a href="#logout" class="logout-link" data-page="logout">
                    Logout
                </a>
            </div>

            <div class="admin-profile">
                <div class="admin-avatar">A</div>

                <div class="admin-info">
                    <strong>Administrator</strong>
                    <span>Administrator</span>
                </div>
            </div>

        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content" id="mainContent">

        <header class="page-header">
            <div>
                <div class="eyebrow">ADMIN PANEL</div>

                <h1>Welcome, Administrator!</h1>

                <p class="page-subtitle">
                    Here's an overview of your MyCalendar platform.
                </p>
            </div>

            <div class="date-display">
                <i class="fa-regular fa-calendar"></i>
                <span id="currentDate">October 12, 2026</span>
            </div>
        </header>

        <!-- SUMMARY CARDS -->
        <section class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

                <div class="stat-label">Total Registered Users</div>

                <div class="stat-value" id="totalUsers">5</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>

                <div class="stat-label">Administrator Access</div>

                <div class="stat-value">Protected</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-regular fa-calendar-check"></i>
                </div>

                <div class="stat-label">Platform</div>

                <div class="stat-value platform-name">MyCalendar</div>
            </div>

        </section>

        <!-- QUICK ACCESS AND ADMIN OVERVIEW -->
        <section class="content-grid">

            <div class="content-card">
                <h2>Quick Access</h2>

                <p class="section-description">
                    Open an administration section to manage and review user information.
                </p>

                <div class="quick-access-grid">

                    <a href="#users" class="quick-link" data-page="users">
                        <i class="fa-solid fa-users"></i>
                        <span>View Users</span>
                    </a>

                    <a href="#activity" class="quick-link" data-page="activity">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>User Activity</span>
                    </a>

                    <a href="#accounts" class="quick-link" data-page="accounts">
                        <i class="fa-solid fa-user-gear"></i>
                        <span>Account Management</span>
                    </a>

                    <a href="#profile" class="quick-link" data-page="profile">
                        <i class="fa-regular fa-circle-user"></i>
                        <span>Admin Profile</span>
                    </a>

                </div>
            </div>

            <div class="content-card">
                <h2>Admin Overview</h2>

                <p class="section-description">
                    Important details about your administrator workspace.
                </p>

                <div class="overview-list">

                    <div class="overview-item">
                        <span class="overview-dot"></span>

                        <div class="overview-text">
                            <strong>Secure sign-in</strong>

                            <p>
                                This page requires an authenticated admin session.
                            </p>
                        </div>
                    </div>

                    <div class="overview-item">
                        <span class="overview-dot"></span>

                        <div class="overview-text">
                            <strong>User information</strong>

                            <p>
                                Review registered user accounts from the Users section.
                            </p>
                        </div>
                    </div>

                    <div class="overview-item">
                        <span class="overview-dot"></span>

                        <div class="overview-text">
                            <strong>Privacy reminder</strong>

                            <p>
                                Account passwords and private financial details are not displayed on this dashboard.
                            </p>
                        </div>
                    </div>

                </div>
            </div>

        </section>

    </main>

    <script>
        // Display the current date.
        const dateElement = document.getElementById("currentDate");

        const today = new Date();

        dateElement.textContent = today.toLocaleDateString("en-US", {
            month: "long",
            day: "numeric",
            year: "numeric"
        });

        // Frontend navigation preview.
        // Replace these demo handlers with actual PHP page URLs
        // when the admin modules are ready.

        const mainContent = document.getElementById("mainContent");
        const originalDashboard = mainContent.innerHTML;

        const pageContent = {
            users: {
                title: "Users",
                description: "View and manage registered MyCalendar users."
            },
            activity: {
                title: "User Activity",
                description: "Review user activity and application usage."
            },
            accounts: {
                title: "Account Management",
                description: "Manage account access and administrator settings."
            },
            profile: {
                title: "Admin Profile",
                description: "View your administrator account information."
            }
        };

        document.querySelectorAll("[data-page]").forEach(link => {
            link.addEventListener("click", event => {
                const page = link.dataset.page;

                if (page === "logout") {
                    event.preventDefault();

                    const confirmed = window.confirm(
                        "Are you sure you want to log out?"
                    );

                    if (confirmed) {
                        window.location.href = "admin_logout.php";
                    }

                    return;
                }

                event.preventDefault();

                document.querySelectorAll(".sidebar-menu a").forEach(item => {
                    item.classList.remove("active");
                });

                document.querySelectorAll(
                    '.sidebar-menu a[data-page="' + page + '"]'
                ).forEach(item => {
                    item.classList.add("active");
                });

                if (page === "home") {
                    mainContent.innerHTML = originalDashboard;
                    window.location.hash = "home";
                    window.location.reload();
                    return;
                }

                if (pageContent[page]) {
                    mainContent.innerHTML = `
                        <header class="page-header">
                            <div>
                                <div class="eyebrow">ADMIN PANEL</div>
                                <h1>${pageContent[page].title}</h1>
                                <p class="page-subtitle">
                                    ${pageContent[page].description}
                                </p>
                            </div>
                        </header>

                        <section class="content-grid">
                            <div class="content-card">
                                <h2>${pageContent[page].title}</h2>
                                <p class="section-description">
                                    This frontend preview is ready for integration
                                    with your PHP and MySQL backend.
                                </p>
                            </div>
                        </section>
                    `;

                    window.location.hash = page;
                    window.scrollTo({ top: 0, behavior: "smooth" });
                }
            });
        });
    </script>

</body>
</html>
