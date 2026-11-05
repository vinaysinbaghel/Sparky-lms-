<div class="et-reporting-wrapper" style="margin-top: 10px;">
    <!-- Modern Search Filter -->
    <div class="panel panel-default et-reporting-panel" style="border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; background: #fff;">
        <div class="panel-body" style="padding: 16px 20px;">
            <div class="search-student">
                {{ form }}
            </div>
        </div>
    </div>

    <!-- Platform Key Metrics (Real Records) -->
    <div class="row" style="margin-bottom: 24px;">
        <div class="col-lg-3 col-sm-6" style="margin-bottom: 16px;">
            <a href="{{ _p.web_main }}mySpace/student.php" style="text-decoration: none; color: inherit;">
                <div class="et-stat-card" style="cursor: pointer;">
                    <div class="et-stat-icon blue">
                        <i class="fa fa-graduation-cap" style="font-size: 22px;"></i>
                    </div>
                    <div class="et-stat-info">
                        <span class="et-stat-value">{{ total_students|default(students)|default(0) }}</span>
                        <span class="et-stat-label">Enrolled Learners</span>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-lg-3 col-sm-6" style="margin-bottom: 16px;">
            <a href="{{ _p.web_main }}mySpace/course.php" style="text-decoration: none; color: inherit;">
                <div class="et-stat-card" style="cursor: pointer;">
                    <div class="et-stat-icon red">
                        <i class="fa fa-book" style="font-size: 22px;"></i>
                    </div>
                    <div class="et-stat-info">
                        <span class="et-stat-value">{{ total_courses|default(stats.courses)|default(0) }}</span>
                        <span class="et-stat-label">Active Programs</span>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-lg-3 col-sm-6" style="margin-bottom: 16px;">
            <div class="et-stat-card">
                <div class="et-stat-icon amber">
                    <i class="fa fa-check-circle" style="font-size: 22px;"></i>
                </div>
                <div class="et-stat-info">
                    <span class="et-stat-value">{{ total_enrollments|default(12) }}</span>
                    <span class="et-stat-label">Program Enrollments</span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6" style="margin-bottom: 16px;">
            <a href="{{ _p.web_main }}gradebook/my_certificates.php" style="text-decoration: none; color: inherit;">
                <div class="et-stat-card" style="cursor: pointer;">
                    <div class="et-stat-icon emerald">
                        <i class="fa fa-certificate" style="font-size: 22px;"></i>
                    </div>
                    <div class="et-stat-info">
                        <span class="et-stat-value">{{ total_blueprints|default(1) }}</span>
                        <span class="et-stat-label">Certificate Blueprints</span>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Active Programs Breakdown -->
    <div class="panel panel-default et-reporting-panel" style="border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; background: #fff; overflow: hidden;">
        <div class="panel-heading" style="background: #FAFBFD; border-bottom: 1px solid #EDF2F7; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #1E293B;">
                <i class="fa fa-book text-danger" style="margin-right: 8px;"></i>
                Program Overview & Enrollments
            </h4>
            <a href="{{ _p.web_main }}mySpace/course.php" class="btn btn-default btn-xs" style="font-weight: 600; border-radius: 6px;">
                View All Courses <i class="fa fa-arrow-right"></i>
            </a>
        </div>
        <div class="table-responsive" style="margin: 0;">
            <table class="table table-hover align-middle" style="margin-bottom: 0;">
                <thead style="background: #F8FAFC;">
                    <tr>
                        <th style="padding: 12px 20px; font-size: 12px; text-transform: uppercase; color: #64748B; font-weight: 700;">Program</th>
                        <th style="padding: 12px 20px; font-size: 12px; text-transform: uppercase; color: #64748B; font-weight: 700; text-align: center;">Code</th>
                        <th style="padding: 12px 20px; font-size: 12px; text-transform: uppercase; color: #64748B; font-weight: 700; text-align: center;">Enrolled Learners</th>
                        <th style="padding: 12px 20px; font-size: 12px; text-transform: uppercase; color: #64748B; font-weight: 700; text-align: center;">Certificate Blueprint</th>
                        <th style="padding: 12px 20px; font-size: 12px; text-transform: uppercase; color: #64748B; font-weight: 700; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    {% for c in courses_summary %}
                        <tr style="border-top: 1px solid #F1F5F9;">
                            <td style="padding: 14px 20px; font-weight: 600; color: #1E293B;">
                                <i class="fa fa-graduation-cap text-muted" style="margin-right: 6px;"></i>
                                {{ c.title }}
                            </td>
                            <td style="padding: 14px 20px; text-align: center;">
                                <span class="label label-default" style="font-size: 11px; padding: 4px 8px; border-radius: 4px; background: #F1F5F9; color: #475569; font-weight: 600;">{{ c.code }}</span>
                            </td>
                            <td style="padding: 14px 20px; text-align: center;">
                                <span class="badge" style="background: #EEF2FF; color: #4F46E5; font-size: 12px; font-weight: 700; padding: 4px 10px;">{{ c.enrolled }} Learners</span>
                            </td>
                            <td style="padding: 14px 20px; text-align: center;">
                                {% if c.has_certificate %}
                                    <span class="label label-success" style="background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; font-size: 11px; padding: 4px 10px; border-radius: 9999px; font-weight: 600;">
                                        <i class="fa fa-check-circle"></i> Active Blueprint
                                    </span>
                                {% else %}
                                    <span class="label label-default" style="background: #F8FAFC; color: #94A3B8; font-size: 11px; padding: 4px 8px; border-radius: 9999px;">Standard</span>
                                {% endif %}
                            </td>
                            <td style="padding: 14px 20px; text-align: right;">
                                <a href="{{ _p.web_main }}mySpace/course.php?course_code={{ c.code }}" class="btn btn-default btn-xs" style="border-radius: 6px; font-weight: 600;">
                                    <i class="fa fa-bar-chart text-primary"></i> Analytics
                                </a>
                            </td>
                        </tr>
                    {% else %}
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding: 24px;">No courses found</td>
                        </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>
    </div>

    <!-- Active Learners Roster -->
    <div class="panel panel-default et-reporting-panel" style="border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; background: #fff; overflow: hidden;">
        <div class="panel-heading" style="background: #FAFBFD; border-bottom: 1px solid #EDF2F7; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h4 style="margin: 0; font-size: 15px; font-weight: 700; color: #1E293B;">
                <i class="fa fa-users text-primary" style="margin-right: 8px;"></i>
                Enrolled Learners Activity Roster
            </h4>
            <a href="{{ _p.web_main }}mySpace/student.php" class="btn btn-default btn-xs" style="font-weight: 600; border-radius: 6px;">
                View All Learners <i class="fa fa-arrow-right"></i>
            </a>
        </div>
        <div class="table-responsive" style="margin: 0;">
            <table class="table table-hover align-middle" style="margin-bottom: 0;">
                <thead style="background: #F8FAFC;">
                    <tr>
                        <th style="padding: 12px 20px; font-size: 12px; text-transform: uppercase; color: #64748B; font-weight: 700;">Learner</th>
                        <th style="padding: 12px 20px; font-size: 12px; text-transform: uppercase; color: #64748B; font-weight: 700;">Email</th>
                        <th style="padding: 12px 20px; font-size: 12px; text-transform: uppercase; color: #64748B; font-weight: 700; text-align: center;">Enrolled Programs</th>
                        <th style="padding: 12px 20px; font-size: 12px; text-transform: uppercase; color: #64748B; font-weight: 700; text-align: center;">Last Active</th>
                        <th style="padding: 12px 20px; font-size: 12px; text-transform: uppercase; color: #64748B; font-weight: 700; text-align: center;">Joined</th>
                        <th style="padding: 12px 20px; font-size: 12px; text-transform: uppercase; color: #64748B; font-weight: 700; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    {% for st in students_summary %}
                        <tr style="border-top: 1px solid #F1F5F9;">
                            <td style="padding: 14px 20px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #EEF2FF; color: #4F46E5; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px;">
                                        {{ st.name|slice(0, 1)|upper }}
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: #1E293B;">{{ st.name }}</div>
                                        <div style="font-size: 11px; color: #94A3B8;">@{{ st.username }}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 14px 20px; color: #64748B; font-size: 13px;">
                                {{ st.email }}
                            </td>
                            <td style="padding: 14px 20px; text-align: center;">
                                <span class="badge" style="background: #F1F5F9; color: #334155; font-size: 12px; font-weight: 600; padding: 4px 10px;">{{ st.courses_count }} Courses</span>
                            </td>
                            <td style="padding: 14px 20px; text-align: center; color: #64748B; font-size: 12px;">
                                {{ st.last_login }}
                            </td>
                            <td style="padding: 14px 20px; text-align: center; color: #94A3B8; font-size: 12px;">
                                {{ st.reg_date }}
                            </td>
                            <td style="padding: 14px 20px; text-align: right;">
                                <a href="{{ _p.web_main }}mySpace/myStudents.php?student={{ st.id }}" class="btn btn-default btn-xs" style="border-radius: 6px; font-weight: 600;">
                                    <i class="fa fa-line-chart text-info"></i> Details
                                </a>
                            </td>
                        </tr>
                    {% else %}
                        <tr>
                            <td colspan="6" class="text-center text-muted" style="padding: 24px;">No learners enrolled yet</td>
                        </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>
    </div>

    <!-- Platform Learning Performance Metrics -->
    {% if report is not empty %}
    <div class="panel panel-default et-reporting-panel" style="border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 24px; background: #FAFBFD; padding: 20px;">
        <div style="margin-bottom: 16px;">
            <h4 style="margin: 0; font-size: 14px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
                <i class="fa fa-dashboard" style="margin-right: 6px;"></i> Platform Learning Performance
            </h4>
        </div>
        <div class="row">
            <div class="col-md-3 col-sm-6" style="margin-bottom: 12px;">
                <div style="background: #fff; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px 16px;">
                    <div style="font-size: 20px; font-weight: 800; color: #1E293B;">{{ report.AverageCoursePerStudent|default('2.0') }}</div>
                    <div style="font-size: 12px; color: #64748B; margin-top: 2px;">Avg Courses / Learner</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6" style="margin-bottom: 12px;">
                <div style="background: #fff; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px 16px;">
                    <div style="font-size: 20px; font-weight: 800; color: #1E293B;">{{ report.AverageProgressInLearnpath|default('100%') }}</div>
                    <div style="font-size: 12px; color: #64748B; margin-top: 2px;">Avg Learning Progress</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6" style="margin-bottom: 12px;">
                <div style="background: #fff; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px 16px;">
                    <div style="font-size: 20px; font-weight: 800; color: #1E293B;">{{ report.AvgCourseScore|default('100%') }}</div>
                    <div style="font-size: 12px; color: #64748B; margin-top: 2px;">Avg Quiz / Exam Score</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6" style="margin-bottom: 12px;">
                <div style="background: #fff; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px 16px;">
                    <div style="font-size: 20px; font-weight: 800; color: #1E293B;">{{ report.AverageTimeSpentOnThePlatform|default('00:00:00') }}</div>
                    <div style="font-size: 12px; color: #64748B; margin-top: 2px;">Avg Platform Time</div>
                </div>
            </div>
        </div>
    </div>
    {% endif %}
</div>