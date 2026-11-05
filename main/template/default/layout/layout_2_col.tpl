{% extends 'layout/page.tpl'|get_template %}
{% import 'default/macro/macro.tpl' as display %}
{% set sidebar_hide = 'sidebar_hide'|api_get_configuration_value %}

{% block body %}
    {% if plugin_main_top %}
        {{ display.pluginPanel('main-top', plugin_main_top) }}
    {% endif %}

    {# Sparky Learner Hero & Stats Bar (Logged in Users) #}
    {% if _u.logged == 1 %}
        {% if _u.is_admin == 1 and et_view_mode == 'admin' %}
            {# ================= ADMINISTRATOR VIEW (Full Proportions - No Event Card) ================= #}
            <div class="row" style="margin-bottom: 24px;">
                <div class="col-xs-12">
                    <div class="et-hero-card-left" style="min-height: 220px; padding: 36px 42px; margin-bottom: 24px;">
                        <div class="et-hero-card-content">
                            <div class="et-hero-card-badge" style="padding: 5px 14px; font-size: 11.5px; margin-bottom: 14px;">
                                <i class="fa fa-shield" style="margin-right: 6px;"></i>
                                Administrator Control Portal
                            </div>
                            <h1 class="et-hero-card-title" style="font-size: 30px; margin-bottom: 10px;">
                                Welcome back, {{ _u.complete_name|default('Administrator') }}!
                            </h1>
                            <p class="et-hero-card-subtitle" style="font-size: 15px; max-width: 680px; margin-bottom: 24px; line-height: 1.55;">
                                Manage executive programs, learner enrollments, credential blueprints, and live masterclass schedules across the platform.
                            </p>
                        </div>
                        <div class="et-hero-card-actions" style="gap: 14px;">
                            <a href="{{ _p.web_main }}admin/course_list.php" class="btn btn-et-hero-light" style="padding: 10px 22px !important; font-size: 13.5px !important; border-radius: 8px !important;">
                                <em class="fa fa-book"></em> Course Manager
                            </a>
                            <a href="{{ _p.web_main }}admin/user_dashboard.php?role=student" class="btn btn-et-hero-outline" style="padding: 10px 22px !important; font-size: 13.5px !important; border-radius: 8px !important;">
                                <em class="fa fa-users"></em> Student Manager
                            </a>
                            <a href="{{ _p.web_main }}admin/index.php" class="btn btn-et-hero-outline" style="padding: 10px 22px !important; font-size: 13.5px !important; border-radius: 8px !important;">
                                <em class="fa fa-cogs"></em> System Administration
                            </a>
                        </div>
                    </div>

                    {# Full-Scale Admin Platform Quick Stats Grid with Trends #}
                    <div class="et-stats-grid">
                        <a href="{{ _p.web_main }}admin/course_list.php" class="et-stat-card" style="text-decoration: none; color: inherit;" title="Manage Platform Courses">
                            <div class="et-stat-icon red">
                                <img src="{{ _p.web }}web/css/themes/chamilo/images/et/icon-courses.svg" alt="Courses" />
                            </div>
                            <div class="et-stat-info">
                                <span class="et-stat-value">{{ total_platform_courses|default(1) }}</span>
                                <span class="et-stat-label">Active Platform Courses</span>
                                <div style="font-size: 11px; color: #10B981; font-weight: 600; margin-top: 4px;"><i class="fa fa-arrow-up"></i> +12% this month</div>
                            </div>
                        </a>
                        <a href="{{ _p.web_main }}admin/user_dashboard.php?role=student" class="et-stat-card" style="text-decoration: none; color: inherit;" title="Manage Enrolled Students">
                            <div class="et-stat-icon blue">
                                <i class="fa fa-users" style="font-size: 20px; color: #4F46E5;"></i>
                            </div>
                            <div class="et-stat-info">
                                <span class="et-stat-value">{{ total_enrolled_students|default(1) }}</span>
                                <span class="et-stat-label">Enrolled Students</span>
                                <div style="font-size: 11px; color: #10B981; font-weight: 600; margin-top: 4px;"><i class="fa fa-arrow-up"></i> +8% new learners</div>
                            </div>
                        </a>
                        <a href="{{ _p.web_main }}admin/awarded_certificates.php" class="et-stat-card" style="text-decoration: none; color: inherit;" title="View Awarded Certificates">
                            <div class="et-stat-icon emerald">
                                <img src="{{ _p.web }}web/css/themes/chamilo/images/et/icon-certificate.svg" alt="Certificates" />
                            </div>
                            <div class="et-stat-info">
                                <span class="et-stat-value">{{ total_awarded_credentials|default(0) }}</span>
                                <span class="et-stat-label">Issued Credentials</span>
                                <div style="font-size: 11px; color: #10B981; font-weight: 600; margin-top: 4px;"><i class="fa fa-arrow-up"></i> +15% this quarter</div>
                            </div>
                        </a>
                        <a href="{{ _p.web_main }}calendar/agenda_js.php" class="et-stat-card" style="text-decoration: none; color: inherit;" title="Live Masterclasses & Webinars Schedule">
                            <div class="et-stat-icon amber">
                                <img src="{{ _p.web }}web/css/themes/chamilo/images/et/icon-live-class.svg" alt="Live Classes" />
                            </div>
                            <div class="et-stat-info">
                                <span class="et-stat-value">{{ total_live_events }}</span>
                                <span class="et-stat-label">Scheduled Webinar{{ total_live_events == 1 ? '' : 's' }}</span>
                                <div style="font-size: 11px; color: #6366F1; font-weight: 600; margin-top: 4px;"><i class="fa fa-calendar"></i> {{ total_live_events > 0 ? (total_live_events ~ ' scheduled upcoming') : 'No upcoming webinars' }}</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        {% else %}
            {# ================= LEARNER / USER VIEW (2-Card Hero with Spotlight Masterclass Pass) ================= #}
            <div class="row et-hero-row" style="margin-bottom: 24px; display: flex; flex-wrap: wrap;">
                {# Left Column: Welcome Card (Sparky Indigo) #}
                <div class="col-md-7 col-lg-8" style="margin-bottom: 16px; display: flex; flex-direction: column;">
                    <div class="et-hero-card-left" style="flex: 1 1 auto;">
                        <div class="et-hero-card-content">
                            <div class="et-hero-card-badge">
                                <img src="{{ _p.web }}web/css/themes/chamilo/images/et/icon-live-class.svg" alt="Live" style="width: 13px; height: 13px; filter: brightness(0) invert(1);" />
                                Sparky Learning Platform
                            </div>
                            <h1 class="et-hero-card-title">
                                Welcome back, {{ _u.complete_name|default('Learner') }}!
                            </h1>
                            <p class="et-hero-card-subtitle">
                                Advance your skills with interactive courses, masterclasses, and verified certifications.
                            </p>
                        </div>
                        <div class="et-hero-card-actions">
                            <a href="{{ _p.web }}user_portal.php" class="btn btn-et-hero-light">
                                <em class="fa fa-play-circle"></em> Resume Learning
                            </a>
                            <a href="{{ _p.web_main }}auth/courses.php" class="btn btn-et-hero-outline">
                                <em class="fa fa-compass"></em> Explore Programs
                            </a>
                        </div>
                    </div>
                </div>

                {# Right Column: Spotlight Scheduled Masterclass Pass #}
                <div class="col-md-5 col-lg-4" style="margin-bottom: 16px; display: flex; flex-direction: column;">
                    <div class="et-spotlight-card" style="flex: 1 1 auto; display: flex; flex-direction: column; justify-content: space-between;">
                        {% if primary_upcoming_event %}
                            <div>
                                <div class="et-spotlight-top">
                                    <span class="et-spotlight-live-badge">
                                        <span class="et-live-pulse-dot"></span> UPCOMING MASTERCLASS
                                    </span>
                                    <span class="et-spotlight-platform">
                                        <i class="fa fa-video-camera"></i> Live Webinar
                                    </span>
                                </div>
                                <h3 class="et-spotlight-title" title="{{ primary_upcoming_event.title }}">
                                    {{ primary_upcoming_event.title }}
                                </h3>
                                <div class="et-spotlight-schedule">
                                    <div class="et-spotlight-schedule-item">
                                        <i class="fa fa-calendar"></i>
                                        <span><strong>{{ primary_upcoming_event.formatted_date }}</strong></span>
                                    </div>
                                    <div class="et-spotlight-schedule-item time">
                                        <i class="fa fa-clock-o"></i>
                                        <span>{{ primary_upcoming_event.formatted_time }}</span>
                                    </div>
                                    {% if primary_upcoming_event.course_title %}
                                        <div class="et-spotlight-schedule-item" style="color: #64748B;">
                                            <i class="fa fa-bookmark-o"></i>
                                            <span>Course: {{ primary_upcoming_event.course_title }}</span>
                                        </div>
                                    {% endif %}
                                </div>
                            </div>

                            <div class="et-spotlight-actions">
                                {% if primary_upcoming_event.meet_url %}
                                    <a href="{{ primary_upcoming_event.meet_url }}" target="_blank" class="btn-et-spotlight-join">
                                        <em class="fa fa-video-camera"></em> Join Live Masterclass
                                    </a>
                                {% endif %}
                                <a href="{{ _p.web_main }}calendar/agenda_js.php?type=personal" class="btn-et-spotlight-agenda" title="View Personal Agenda Calendar">
                                    <em class="fa fa-calendar-check-o"></em> Agenda
                                </a>
                                {% if upcoming_agenda_events|length > 1 %}
                                    <a href="{{ _p.web_main }}calendar/agenda_js.php?type=personal" class="badge" style="background: #EEF2FF; color: #4338CA; font-size: 11px; font-weight: 600; padding: 6px 8px; text-decoration: none; border: 1px solid #E0E7FF;" title="View all scheduled sessions">
                                        +{{ upcoming_agenda_events|length - 1 }}
                                    </a>
                                {% endif %}
                            </div>
                        {% else %}
                            <div>
                                <div class="et-spotlight-top">
                                    <span class="et-spotlight-live-badge" style="background: #F1F5F9; color: #475569; border-color: #CBD5E1;">
                                        <i class="fa fa-calendar-o"></i> PERSONAL AGENDA
                                    </span>
                                </div>
                                <h3 class="et-spotlight-title">
                                    Personal Masterclass Agenda
                                </h3>
                                <p style="font-size: 13px; color: #64748B; line-height: 1.45; margin-bottom: 16px;">
                                    Plan your learning schedule, track assignments, and view upcoming executive webinars on your calendar.
                                </p>
                            </div>
                            <div class="et-spotlight-actions">
                                <a href="{{ _p.web_main }}calendar/agenda_js.php?type=personal" class="btn-et-spotlight-join" style="background: #334155 !important;">
                                    <em class="fa fa-calendar-plus-o"></em> Open Calendar
                                </a>
                            </div>
                        {% endif %}
                    </div>
                </div>
            </div>
        {% endif %}
    {% endif %}

    {# ========================================================================= #}
    {# GUEST / LOGGED-OUT VIEW: CLEAN CENTERED LOGIN PORTAL #}
    {# ========================================================================= #}
    {% if _u.logged != 1 %}
        <div class="row et-guest-portal-hero" style="margin-top: 36px; margin-bottom: 54px;">
            <div class="col-xs-12 col-sm-8 col-sm-offset-2 col-md-6 col-md-offset-3 col-lg-5 col-lg-offset-3" style="margin-left: auto; margin-right: auto; float: none; max-width: 480px;">
                <div class="et-guest-login-stage">
                    {% include 'layout/login_form.tpl'|get_template %}
                </div>
            </div>
        </div>
    {% endif %}

    {# Normal Content Area for Logged-in Users #}
    {% if _u.logged == 1 %}
        <div class="row">
            <div class="col-xs-12 col-md-12">
                <div class="page-content">
                    {% if plugin_content_top %}
                        <div class="page-content-top">
                            {{ plugin_content_top }}
                        </div>
                    {% endif %}

                    {{ sniff_notification }}

                    {% block page_body %}
                        {% include 'layout/page_body.tpl'|get_template %}
                    {% endblock %}

                    {% if home_welcome %}
                        <article id="home-welcome">
                            {{ home_welcome }}
                        </article>
                    {% endif %}

                    {% if home_include %}
                    <article id="home-include">
                        {{ home_include }}
                    </article>
                    {% endif %}

                    {% if welcome_to_course_block %}
                        <article id="homepage-course">
                            {{ welcome_to_course_block }}
                        </article>
                    {% endif %}

                    {% if _u.is_admin == 1 and et_view_mode == 'admin' %}
                        {# Admin Platform Course Catalog & Interactive Search #}
                        {% include 'layout/admin_course_catalog.tpl'|get_template %}
                    {% else %}
                        <!-- Dashboard Controls Bar -->
                        <div class="et-controls-bar">
                            <ul class="et-filter-pills">
                                <li><a href="{{ _p.web }}user_portal.php" class="et-filter-pill active"><em class="fa fa-th"></em> All Programs</a></li>
                                <li><a href="{{ _p.web }}user_portal.php?history=0" class="et-filter-pill"><em class="fa fa-play"></em> In Progress</a></li>
                                <li><a href="{{ _p.web_main }}calendar/agenda_js.php" class="et-filter-pill"><img src="{{ _p.web }}web/css/themes/chamilo/images/et/icon-live-class.svg" alt="Live" style="width: 14px; height: 14px;" /> Masterclasses</a></li>
                                <li><a href="{{ _p.web_main }}gradebook/my_certificates.php" class="et-filter-pill"><img src="{{ _p.web }}web/css/themes/chamilo/images/et/icon-certificate.svg" alt="Cert" style="width: 14px; height: 14px;" /> Certificates</a></li>
                            </ul>
                        </div>

                        {% block content %}
                            {% if content is not null %}
                                <section id="page" class="{{ course_history_page }}">
                                    {{ content }}
                                </section>
                            {% endif %}
                        {% endblock %}
                    {% endif %}

                    {% if announcements_block %}
                        <article id="homepage-announcements">
                            {{ announcements_block }}
                        </article>
                    {% endif %}

                    {% if course_category_block %}
                        <article id="homepage-course-category">
                            {{ course_category_block }}
                        </article>
                    {% endif %}
                    {% include 'session/sessions_current.tpl'|get_template %}
                    {% if plugin_content_bottom %}
                        <div id="plugin_content_bottom">
                            {{ plugin_content_bottom }}
                        </div>
                    {% endif %}
                </div>
            </div>

            {# Collapsible Admin Offcanvas Drawer (Only rendered in Admin Mode) #}
            {% if _u.is_admin == 1 and et_view_mode == 'admin' %}
                <button type="button" class="et-admin-drawer-trigger" onclick="toggleEtAdminDrawer()" title="Open Administrator Navigation">
                    <span class="gf-brand-dot"></span>
                    <span>Sparky Admin</span>
                    <i class="fa fa-sliders" style="font-size: 13px; margin-left: 2px;"></i>
                </button>
                {% include 'layout/sidebar_tree.tpl'|get_template %}
            {% endif %}
        </div>
    {% endif %}

    {% if plugin_main_bottom %}
        {{ display.pluginPanel('main-bottom', plugin_main_bottom) }}
    {% endif %}
{% endblock %}
