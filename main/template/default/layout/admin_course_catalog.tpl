{# Administrator Platform Courses Showcase & Live Search #}
<div class="et-admin-courses-catalog" id="adminCoursesCatalogSection">
    {# Catalog Header #}
    <div class="et-catalog-header">
        <div class="et-catalog-header-left">
            <div class="et-catalog-title-row">
                <span class="et-catalog-icon-box">
                    <i class="fa fa-graduation-cap"></i>
                </span>
                <div>
                    <h2 class="et-catalog-title">Platform Course Catalog</h2>
                    <p class="et-catalog-subtitle">
                        Manage executive programs, curriculums, learner enrollments, and configurations across the platform.
                    </p>
                </div>
            </div>
        </div>
        <div class="et-catalog-header-actions">
            <a href="{{ _p.web_main }}admin/course_add.php" class="btn btn-et-action-primary" title="Create a new course">
                <i class="fa fa-plus-circle"></i> Create New Course
            </a>
            <a href="{{ _p.web_main }}admin/course_category.php" class="btn btn-et-action-secondary" title="Manage categories">
                <i class="fa fa-folder-open"></i> Categories
            </a>
            <a href="{{ _p.web_main }}admin/course_list.php" class="btn btn-et-action-secondary" title="Switch to full tabular directory">
                <i class="fa fa-table"></i> Course Table
            </a>
        </div>
    </div>

    {# Search & Filter Toolbar #}
    <div class="et-catalog-toolbar">
        <div class="et-toolbar-row-top">
            {# Interactive Search Box #}
            <div class="et-search-container">
                <i class="fa fa-search et-search-icon"></i>
                <input 
                    type="text" 
                    id="adminCourseSearchInput" 
                    class="et-search-input" 
                    placeholder="Search courses by title, course code (e.g. 9090), category, or instructor... (Press '/' to focus)" 
                    autocomplete="off"
                    spellcheck="false"
                />
                <button type="button" id="adminCourseSearchClear" class="et-search-clear-btn" onclick="clearAdminCourseSearch()" title="Clear search" style="display: none;">
                    <i class="fa fa-times"></i>
                </button>
            </div>

            {# Category Filter Select #}
            <div class="et-select-wrapper">
                <i class="fa fa-filter et-select-icon"></i>
                <select id="adminCourseCategorySelect" class="et-select-input" onchange="applyAdminCourseFilters()">
                    <option value="all">All Categories</option>
                    {% for cat in platform_categories %}
                        <option value="{{ cat.code }}">{{ cat.name }}</option>
                    {% endfor %}
                </select>
            </div>

            {# Sort By Select #}
            <div class="et-select-wrapper">
                <i class="fa fa-sort-amount-asc et-select-icon"></i>
                <select id="adminCourseSortSelect" class="et-select-input" onchange="applyAdminCourseFilters()">
                    <option value="title_asc">Sort: Title (A &rarr; Z)</option>
                    <option value="title_desc">Sort: Title (Z &rarr; A)</option>
                    <option value="students_desc">Sort: Most Students</option>
                    <option value="lessons_desc">Sort: Most Lessons</option>
                    <option value="id_desc">Sort: Recently Added</option>
                </select>
            </div>
        </div>

        {# Filter Pills Row #}
        <div class="et-toolbar-row-bottom">
            <div class="et-filter-chips">
                <button type="button" class="et-filter-chip active" data-filter="all" onclick="setAdminStatusFilter('all', this)">
                    <i class="fa fa-th-large"></i> All Courses <span class="et-chip-count">{{ course_counts.total|default(all_platform_courses|length) }}</span>
                </button>
                <button type="button" class="et-filter-chip" data-filter="private" onclick="setAdminStatusFilter('private', this)">
                    <i class="fa fa-lock"></i> Registered Only <span class="et-chip-count">{{ course_counts.private|default(0) }}</span>
                </button>
                <button type="button" class="et-filter-chip" data-filter="open" onclick="setAdminStatusFilter('open', this)">
                    <i class="fa fa-globe"></i> Public / Open <span class="et-chip-count">{{ course_counts.open|default(0) }}</span>
                </button>
                {% if course_counts.closed > 0 %}
                <button type="button" class="et-filter-chip" data-filter="closed" onclick="setAdminStatusFilter('closed', this)">
                    <i class="fa fa-archive"></i> Draft / Closed <span class="et-chip-count">{{ course_counts.closed|default(0) }}</span>
                </button>
                {% endif %}
            </div>

            <div class="et-results-summary" id="adminCatalogResultsSummary">
                Showing <strong id="lblShownCoursesCount">{{ all_platform_courses|length }}</strong> of {{ all_platform_courses|length }} courses
            </div>
        </div>
    </div>

    {# Course Cards Grid #}
    <div class="et-courses-grid" id="adminCoursesCatalogGrid">
        {% for course in all_platform_courses %}
            <div 
                class="et-course-card" 
                data-id="{{ course.id }}"
                data-title="{{ course.title|lower|e('html_attr') }}"
                data-code="{{ course.code|lower|e('html_attr') }}"
                data-visual-code="{{ course.visual_code|lower|e('html_attr') }}"
                data-category-code="{{ course.category_code|e('html_attr') }}"
                data-category-name="{{ course.category_name|lower|e('html_attr') }}"
                data-teacher="{{ course.teacher_name|lower|e('html_attr') }}"
                data-status="{{ course.filter_group }}"
                data-students="{{ course.student_count }}"
                data-lessons="{{ course.lesson_count }}"
            >
                {# Card Cover / Thumbnail #}
                <div class="et-card-cover">
                    {% if course.has_custom_image %}
                        <img src="{{ course.image_url }}" alt="{{ course.title }}" class="et-card-cover-img" loading="lazy" />
                    {% else %}
                        <div class="et-card-cover-placeholder">
                            <div class="et-cover-pattern"></div>
                            <span class="et-cover-initials">{{ course.title|slice(0, 2)|upper }}</span>
                            <span class="et-cover-brand">SPARKY LMS</span>
                        </div>
                    {% endif %}
                    
                    {# Overlay Floating Badges #}
                    <div class="et-cover-badges">
                        <span class="et-cat-pill" title="Category: {{ course.category_name }}">
                            <i class="fa fa-folder-open-o"></i> {{ course.category_name }}
                        </span>
                        <span class="et-status-pill {{ course.visibility_badge }}">
                            <i class="fa fa-circle"></i> {{ course.visibility_label }}
                        </span>
                    </div>
                </div>

                {# Card Body #}
                <div class="et-card-body">
                    <div class="et-card-meta-top">
                        <span class="et-code-badge" title="Course Code: {{ course.visual_code }}">
                            <i class="fa fa-hashtag"></i> {{ course.visual_code }}
                        </span>
                        {% if course.creation_date %}
                            <span class="et-date-meta" title="Created on {{ course.creation_date }}">
                                <i class="fa fa-calendar-o"></i> {{ course.creation_date }}
                            </span>
                        {% endif %}
                    </div>

                    <h3 class="et-card-title" title="{{ course.title }}">
                        <a href="{{ course.home_url }}">{{ course.title }}</a>
                    </h3>

                    <div class="et-card-faculty" title="Instructor: {{ course.teacher_name }}">
                        <i class="fa fa-user-circle"></i>
                        <span>{{ course.teacher_name }}</span>
                    </div>

                    {# Metrics Bar #}
                    <div class="et-card-metrics">
                        <a href="{{ course.students_url }}" class="et-metric-item" title="View & manage {{ course.student_count }} enrolled students">
                            <i class="fa fa-users text-primary"></i>
                            <div class="et-metric-data">
                                <span class="et-metric-val">{{ course.student_count }}</span>
                                <span class="et-metric-lbl">Students</span>
                            </div>
                        </a>
                        <a href="{{ course.curriculum_url }}" class="et-metric-item" title="View {{ course.chapter_count }} chapters in curriculum">
                            <i class="fa fa-bookmark-o text-warning"></i>
                            <div class="et-metric-data">
                                <span class="et-metric-val">{{ course.chapter_count }}</span>
                                <span class="et-metric-lbl">Chapters</span>
                            </div>
                        </a>
                        <a href="{{ course.curriculum_url }}" class="et-metric-item" title="View {{ course.lesson_count }} lessons in curriculum">
                            <i class="fa fa-play-circle text-success"></i>
                            <div class="et-metric-data">
                                <span class="et-metric-val">{{ course.lesson_count }}</span>
                                <span class="et-metric-lbl">Lessons</span>
                            </div>
                        </a>
                    </div>
                </div>

                {# Card Footer / Action Buttons #}
                <div class="et-card-footer">
                    <a href="{{ course.curriculum_url }}" class="btn btn-sm btn-et-primary-card" title="Edit course curriculum chapters and lessons">
                        <i class="fa fa-pencil-square-o"></i> Curriculum Editor
                    </a>
                    <a href="{{ course.home_url }}" class="btn btn-sm btn-et-secondary-card" title="Open course classroom page">
                        <i class="fa fa-external-link"></i> Classroom
                    </a>
                    
                    <div class="btn-group dropup">
                        <button type="button" class="btn btn-sm btn-et-more-card dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="More course actions">
                            <i class="fa fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-right et-card-dropdown">
                            <li>
                                <a href="{{ course.edit_url }}">
                                    <i class="fa fa-cog text-muted"></i> Course Settings
                                </a>
                            </li>
                            <li>
                                <a href="{{ course.students_url }}">
                                    <i class="fa fa-user-plus text-primary"></i> Enrolled Learners
                                </a>
                            </li>
                            <li>
                                <a href="{{ course.tracking_url }}">
                                    <i class="fa fa-bar-chart text-info"></i> Course Analytics
                                </a>
                            </li>
                            <li role="separator" class="divider"></li>
                            <li>
                                <a href="{{ course.home_url }}" target="_blank">
                                    <i class="fa fa-window-restore text-success"></i> Open in New Tab
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        {% else %}
            {# Fallback if platform has zero courses #}
            <div class="et-empty-catalog-state" style="grid-column: 1 / -1;">
                <div class="et-empty-icon-wrap">
                    <i class="fa fa-book"></i>
                </div>
                <h3>No Platform Courses Yet</h3>
                <p>Get started by creating your very first course program on the platform.</p>
                <a href="{{ _p.web_main }}admin/course_add.php" class="btn btn-et-action-primary">
                    <i class="fa fa-plus-circle"></i> Create New Course
                </a>
            </div>
        {% endfor %}
    </div>

    {# No Search Results State #}
    <div id="adminCoursesNoResults" class="et-empty-catalog-state" style="display: none;">
        <div class="et-empty-icon-wrap">
            <i class="fa fa-search"></i>
        </div>
        <h3>No courses found</h3>
        <p>No platform courses match your current search and filter criteria.</p>
        <button type="button" class="btn btn-et-action-secondary" onclick="resetAdminCourseFilters()">
            <i class="fa fa-refresh"></i> Reset Search & Filters
        </button>
    </div>
</div>

{# Styles for Admin Course Catalog #}
<style>
/* Catalog Container */
.et-admin-courses-catalog {
    margin-top: 10px;
    margin-bottom: 40px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

/* Catalog Header */
.et-catalog-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 22px;
    padding-bottom: 18px;
    border-bottom: 1.5px solid #EEF2F6;
}

.et-catalog-title-row {
    display: flex;
    align-items: center;
    gap: 14px;
}

.et-catalog-icon-box {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: linear-gradient(135deg, #FFF1F2 0%, #FFE4E6 100%);
    color: #E11D48;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    box-shadow: 0 2px 6px rgba(225, 29, 72, 0.12);
    flex-shrink: 0;
}

.et-catalog-title {
    font-size: 22px;
    font-weight: 700;
    color: #0F172A;
    margin: 0 0 4px 0;
    letter-spacing: -0.3px;
}

.et-catalog-subtitle {
    font-size: 13.5px;
    color: #64748B;
    margin: 0;
}

.et-catalog-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-et-action-primary {
    background: linear-gradient(135deg, #4F46E5 0%, #4338CA 100%) !important;
    color: #FFFFFF !important;
    border: none !important;
    font-weight: 600 !important;
    font-size: 13px !important;
    padding: 9px 18px !important;
    border-radius: 8px !important;
    box-shadow: 0 2px 6px rgba(79, 70, 229, 0.25) !important;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none !important;
    transition: all 0.2s ease;
}

.btn-et-action-primary:hover {
    background: linear-gradient(135deg, #4338CA 0%, #3730A3 100%) !important;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35) !important;
    transform: translateY(-1px);
    color: #FFFFFF !important;
}

.btn-et-action-secondary {
    background: #FFFFFF !important;
    color: #334155 !important;
    border: 1.5px solid #CBD5E1 !important;
    font-weight: 600 !important;
    font-size: 13px !important;
    padding: 8.5px 16px !important;
    border-radius: 8px !important;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none !important;
    transition: all 0.2s ease;
}

.btn-et-action-secondary:hover {
    background: #F8FAFC !important;
    border-color: #94A3B8 !important;
    color: #0F172A !important;
}

/* Toolbar & Search Controls */
.et-catalog-toolbar {
    background: #FFFFFF;
    border: 1.5px solid #E2E8F0;
    border-radius: 14px;
    padding: 16px 20px;
    margin-bottom: 26px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.et-toolbar-row-top {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 14px;
    flex-wrap: wrap;
}

.et-search-container {
    position: relative;
    flex: 1 1 380px;
}

.et-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94A3B8;
    font-size: 15px;
    pointer-events: none;
}

.et-search-input {
    width: 100%;
    height: 44px;
    padding: 10px 42px 10px 40px;
    font-size: 14px;
    color: #0F172A;
    background: #F8FAFC;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    outline: none;
    transition: all 0.2s ease;
}

.et-search-input:focus {
    background: #FFFFFF;
    border-color: #4F46E5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
}

.et-search-clear-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: #E2E8F0;
    border: none;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    color: #475569;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.2s ease;
}

.et-search-clear-btn:hover {
    background: #CBD5E1;
    color: #0F172A;
}

.et-select-wrapper {
    position: relative;
    flex: 0 1 200px;
}

.et-select-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #94A3B8;
    font-size: 13px;
    pointer-events: none;
}

.et-select-input {
    width: 100%;
    height: 44px;
    padding: 8px 12px 8px 34px;
    font-size: 13px;
    font-weight: 500;
    color: #334155;
    background: #F8FAFC;
    border: 1.5px solid #E2E8F0;
    border-radius: 10px;
    outline: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.et-select-input:focus {
    background: #FFFFFF;
    border-color: #4F46E5;
}

.et-toolbar-row-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    padding-top: 10px;
    border-top: 1px dashed #E2E8F0;
}

.et-filter-chips {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.et-filter-chip {
    background: #F1F5F9;
    border: 1px solid #E2E8F0;
    color: #475569;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: all 0.2s ease;
}

.et-filter-chip:hover {
    background: #E2E8F0;
    color: #0F172A;
}

.et-filter-chip.active {
    background: #EEF2FF;
    border-color: #C7D2FE;
    color: #4F46E5;
    box-shadow: 0 1px 3px rgba(79, 70, 229, 0.1);
}

.et-chip-count {
    background: rgba(0, 0, 0, 0.07);
    padding: 1px 7px;
    border-radius: 10px;
    font-size: 11px;
}

.et-filter-chip.active .et-chip-count {
    background: #E0E7FF;
    color: #4338CA;
}

.et-results-summary {
    font-size: 13px;
    color: #64748B;
    font-weight: 500;
}

.et-results-summary strong {
    color: #0F172A;
}

/* Course Cards Grid */
.et-courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
    gap: 22px;
}

/* Individual Course Card */
.et-course-card {
    background: #FFFFFF;
    border: 1.5px solid #E2E8F0;
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.et-course-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.09);
    border-color: #CBD5E1;
}

/* Card Cover */
.et-card-cover {
    position: relative;
    height: 165px;
    width: 100%;
    background: #0F172A;
    overflow: hidden;
}

.et-card-cover-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.3s ease;
}

.et-course-card:hover .et-card-cover-img {
    transform: scale(1.04);
}

.et-card-cover-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%);
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #FFFFFF;
}

.et-cover-pattern {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 0);
    background-size: 16px 16px;
    opacity: 0.6;
}

.et-cover-initials {
    font-size: 42px;
    font-weight: 800;
    letter-spacing: 2px;
    color: rgba(255, 255, 255, 0.85);
    z-index: 1;
}

.et-cover-brand {
    font-size: 9px;
    letter-spacing: 2px;
    color: #FECDD3;
    font-weight: 700;
    margin-top: 4px;
    z-index: 1;
}

.et-cover-badges {
    position: absolute;
    top: 12px;
    left: 12px;
    right: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 2;
    pointer-events: none;
}

.et-cat-pill {
    background: rgba(15, 23, 42, 0.78);
    backdrop-filter: blur(6px);
    color: #FFFFFF;
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.et-status-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.et-status-pill i {
    font-size: 7px;
}

.et-badge-blue {
    background: #EFF6FF;
    color: #1D4ED8;
    border: 1px solid #BFDBFE;
}

.et-badge-emerald {
    background: #ECFDF5;
    color: #047857;
    border: 1px solid #A7F3D0;
}

.et-badge-gray {
    background: #F1F5F9;
    color: #475569;
    border: 1px solid #CBD5E1;
}

/* Card Body */
.et-card-body {
    padding: 16px 18px 12px;
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
}

.et-card-meta-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 11.5px;
}

.et-code-badge {
    background: #F8FAFC;
    color: #475569;
    border: 1px solid #E2E8F0;
    padding: 2px 8px;
    border-radius: 5px;
    font-weight: 700;
    font-family: monospace;
    font-size: 11.5px;
}

.et-date-meta {
    color: #94A3B8;
    font-size: 11.5px;
}

.et-card-title {
    font-size: 16px;
    font-weight: 700;
    line-height: 1.4;
    margin: 0 0 8px 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 44px;
}

.et-card-title a {
    color: #0F172A;
    text-decoration: none;
    transition: color 0.2s ease;
}

.et-card-title a:hover {
    color: #4F46E5;
}

.et-card-faculty {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #64748B;
    font-size: 12.5px;
    margin-bottom: 14px;
}

.et-card-faculty i {
    color: #94A3B8;
    font-size: 14px;
}

/* Metrics Row */
.et-card-metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
    background: #F8FAFC;
    border: 1px solid #EEF2F6;
    border-radius: 8px;
    padding: 8px;
    margin-top: auto;
}

.et-metric-item {
    display: flex;
    align-items: center;
    gap: 6px;
    text-decoration: none !important;
    padding: 3px 6px;
    border-radius: 6px;
    transition: background 0.15s ease;
}

.et-metric-item:hover {
    background: #EDF2F7;
}

.et-metric-item i {
    font-size: 15px;
}

.et-metric-data {
    display: flex;
    flex-direction: column;
    line-height: 1.1;
}

.et-metric-val {
    font-size: 13px;
    font-weight: 700;
    color: #0F172A;
}

.et-metric-lbl {
    font-size: 10px;
    color: #64748B;
    font-weight: 500;
}

/* Card Footer */
.et-card-footer {
    padding: 12px 18px 16px;
    background: #FFFFFF;
    border-top: 1px solid #F1F5F9;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-et-primary-card {
    flex: 1 1 auto;
    background: #EEF2FF !important;
    color: #4F46E5 !important;
    border: 1.5px solid #C7D2FE !important;
    font-weight: 700 !important;
    font-size: 12.5px !important;
    padding: 7px 12px !important;
    border-radius: 7px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none !important;
    transition: all 0.2s ease;
}

.btn-et-primary-card:hover {
    background: #4F46E5 !important;
    color: #FFFFFF !important;
    border-color: #4F46E5 !important;
}

.btn-et-secondary-card {
    background: #F8FAFC !important;
    color: #334155 !important;
    border: 1.5px solid #CBD5E1 !important;
    font-weight: 600 !important;
    font-size: 12px !important;
    padding: 7px 10px !important;
    border-radius: 7px !important;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none !important;
    transition: all 0.2s ease;
}

.btn-et-secondary-card:hover {
    background: #EDF2F7 !important;
    color: #0F172A !important;
}

.btn-et-more-card {
    background: #F8FAFC !important;
    color: #64748B !important;
    border: 1.5px solid #E2E8F0 !important;
    padding: 7px 10px !important;
    border-radius: 7px !important;
}

.btn-et-more-card:hover {
    background: #EDF2F7 !important;
    color: #0F172A !important;
}

.et-card-dropdown {
    border-radius: 10px;
    border: 1.5px solid #E2E8F0;
    box-shadow: 0 10px 25px rgba(15, 23, 42, 0.12);
    padding: 6px;
}

.et-card-dropdown > li > a {
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12.5px;
    font-weight: 500;
    color: #334155;
    display: flex;
    align-items: center;
    gap: 8px;
}

.et-card-dropdown > li > a:hover {
    background: #F1F5F9;
    color: #0F172A;
}

/* Empty State */
.et-empty-catalog-state {
    text-align: center;
    padding: 60px 20px;
    background: #FFFFFF;
    border: 2px dashed #CBD5E1;
    border-radius: 16px;
    margin: 20px 0;
}

.et-empty-icon-wrap {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #F1F5F9;
    color: #94A3B8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    margin-bottom: 16px;
}

.et-empty-catalog-state h3 {
    font-size: 18px;
    font-weight: 700;
    color: #0F172A;
    margin: 0 0 6px 0;
}

.et-empty-catalog-state p {
    font-size: 14px;
    color: #64748B;
    max-width: 440px;
    margin: 0 auto 20px auto;
}

/* Responsive queries */
@media (max-width: 768px) {
    .et-catalog-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .et-catalog-header-actions {
        width: 100%;
    }
    .et-catalog-header-actions .btn {
        flex: 1 1 auto;
        justify-content: center;
    }
    .et-toolbar-row-top {
        flex-direction: column;
        align-items: stretch;
    }
    .et-select-wrapper {
        flex: 1 1 100%;
    }
    .et-toolbar-row-bottom {
        flex-direction: column;
        align-items: flex-start;
    }
    .et-courses-grid {
        grid-template-columns: 1fr;
    }
}
</style>

{# JavaScript Controller for Real-Time Instant Filtering & Search #}
<script>
var currentAdminStatusFilter = 'all';

function applyAdminCourseFilters() {
    var searchInput = document.getElementById('adminCourseSearchInput');
    var clearBtn = document.getElementById('adminCourseSearchClear');
    var catSelect = document.getElementById('adminCourseCategorySelect');
    var sortSelect = document.getElementById('adminCourseSortSelect');
    var grid = document.getElementById('adminCoursesCatalogGrid');
    var noResultsDiv = document.getElementById('adminCoursesNoResults');
    var lblShown = document.getElementById('lblShownCoursesCount');

    if (!grid) return;

    var query = searchInput ? searchInput.value.trim().toLowerCase() : '';
    var selectedCat = catSelect ? catSelect.value : 'all';
    var selectedSort = sortSelect ? sortSelect.value : 'title_asc';

    // Show/hide clear button
    if (clearBtn) {
        clearBtn.style.display = query.length > 0 ? 'flex' : 'none';
    }

    var cards = Array.from(grid.querySelectorAll('.et-course-card'));
    var visibleCount = 0;

    // Split search query into multiple search words for forgiving matching
    var searchTokens = query.split(/\s+/).filter(function(t) { return t.length > 0; });

    cards.forEach(function(card) {
        var title = card.getAttribute('data-title') || '';
        var code = card.getAttribute('data-code') || '';
        var visualCode = card.getAttribute('data-visual-code') || '';
        var catName = card.getAttribute('data-category-name') || '';
        var catCode = card.getAttribute('data-category-code') || '';
        var teacher = card.getAttribute('data-teacher') || '';
        var status = card.getAttribute('data-status') || '';

        var textPool = (title + ' ' + code + ' ' + visualCode + ' ' + catName + ' ' + teacher).toLowerCase();

        // 1. Check Search Query
        var matchesSearch = true;
        for (var i = 0; i < searchTokens.length; i++) {
            if (textPool.indexOf(searchTokens[i]) === -1) {
                matchesSearch = false;
                break;
            }
        }

        // 2. Check Category Filter
        var matchesCat = (selectedCat === 'all' || catCode === selectedCat);

        // 3. Check Status Pill Filter
        var matchesStatus = (currentAdminStatusFilter === 'all' || status === currentAdminStatusFilter);

        if (matchesSearch && matchesCat && matchesStatus) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Update Counter
    if (lblShown) {
        lblShown.textContent = visibleCount;
    }

    // Show or hide No Results state
    if (noResultsDiv) {
        noResultsDiv.style.display = (visibleCount === 0 && cards.length > 0) ? 'block' : 'none';
    }

    // Sort visible cards in DOM
    cards.sort(function(a, b) {
        switch (selectedSort) {
            case 'title_desc':
                return (b.getAttribute('data-title') || '').localeCompare(a.getAttribute('data-title') || '');
            case 'students_desc':
                return parseInt(b.getAttribute('data-students') || 0, 10) - parseInt(a.getAttribute('data-students') || 0, 10);
            case 'lessons_desc':
                return parseInt(b.getAttribute('data-lessons') || 0, 10) - parseInt(a.getAttribute('data-lessons') || 0, 10);
            case 'id_desc':
                return parseInt(b.getAttribute('data-id') || 0, 10) - parseInt(a.getAttribute('data-id') || 0, 10);
            case 'title_asc':
            default:
                return (a.getAttribute('data-title') || '').localeCompare(b.getAttribute('data-title') || '');
        }
    });

    cards.forEach(function(card) {
        grid.appendChild(card);
    });
}

function setAdminStatusFilter(statusGroup, btn) {
    currentAdminStatusFilter = statusGroup;
    var container = btn.closest('.et-filter-chips');
    if (container) {
        var buttons = container.querySelectorAll('.et-filter-chip');
        buttons.forEach(function(b) { b.classList.remove('active'); });
    }
    btn.classList.add('active');
    applyAdminCourseFilters();
}

function clearAdminCourseSearch() {
    var searchInput = document.getElementById('adminCourseSearchInput');
    if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
    }
    applyAdminCourseFilters();
}

function resetAdminCourseFilters() {
    var searchInput = document.getElementById('adminCourseSearchInput');
    if (searchInput) searchInput.value = '';

    var catSelect = document.getElementById('adminCourseCategorySelect');
    if (catSelect) catSelect.value = 'all';

    var sortSelect = document.getElementById('adminCourseSortSelect');
    if (sortSelect) sortSelect.value = 'title_asc';

    var firstChip = document.querySelector('.et-filter-chip[data-filter="all"]');
    if (firstChip) {
        setAdminStatusFilter('all', firstChip);
    } else {
        currentAdminStatusFilter = 'all';
        applyAdminCourseFilters();
    }
}

// Bind live events on DOMContentLoaded / immediately
(function() {
    function initCatalog() {
        var searchInput = document.getElementById('adminCourseSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', applyAdminCourseFilters);
            searchInput.addEventListener('keyup', applyAdminCourseFilters);
        }

        // Global hotkey: press '/' to jump directly to search input
        window.addEventListener('keydown', function(e) {
            if (e.key === '/' && document.activeElement !== searchInput && ['INPUT', 'TEXTAREA', 'SELECT'].indexOf(document.activeElement.tagName) === -1) {
                e.preventDefault();
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCatalog);
    } else {
        initCatalog();
    }
})();
</script>
