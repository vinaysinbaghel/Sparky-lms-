{% set group_courses = 'view_grid_courses_grouped_categories_in_sessions'| api_get_configuration_value %}

{% macro course_block(course, show_category) %}
    <div class="col-xs-12 col-sm-6 col-md-4">
        <div class="et-course-card items items-sessions" onclick="window.location.href='{{ course.link }}';" style="cursor: pointer; position: relative;">
            <div class="et-course-media">
                {% set title %}
                    {% if course.visibility == constant('COURSE_VISIBILITY_CLOSED') or course.requirements %}
                        <span title="{{ course.name }}" >
                            <img src="{{ course.image }}" class="img-responsive">
                        </span>
                    {% else %}
                        <a title="{{ course.name }}" href="{{ course.link }}">
                            <img src="{{ course.image }}" class="img-responsive">
                        </a>
                    {% endif %}
                {% endset %}
                {{ title |  remove_xss }}

                {% if course.category != '' and show_category %}
                    <span class="et-course-badge">{{ course.category }}</span>
                {% else %}
                    <span class="et-course-badge live">Live Cohort</span>
                {% endif %}

                {% if course.edit_actions %}
                    <div class="admin-actions" style="position: absolute; top: 10px; right: 10px; z-index: 3;">
                        <a class="btn btn-default btn-xs" href="{{ course.edit_actions }}" style="background: rgba(255,255,255,0.9); border-radius: 50%; width: 28px; height: 28px; padding: 0;">
                            <i class="fa fa-pencil" aria-hidden="true"></i>
                        </a>
                    </div>
                {% endif %}
            </div>
            <div class="et-course-body">
                <h4 class="et-course-title">
                    {% set title %}
                        {% if course.visibility == constant('COURSE_VISIBILITY_CLOSED') or course.requirements %}
                            {{ course.name }}
                            <span class="code-title">{{ course.visual_code }}</span>
                        {% else %}
                            <a href="{{ course.link }}">{{ course.title }}</a>
                        {% endif %}
                    {% endset %}
                    {{ title |  remove_xss }}
                </h4>

                <div class="et-course-meta">
                    <span class="et-course-meta-item">
                        <img src="{{ _p.web }}web/css/themes/chamilo/images/et/icon-live-class.svg" alt="Cohort" style="width: 14px; height: 14px;" />
                        <span>Executive Cohort</span>
                    </span>
                </div>

                <div class="block-author" style="margin-bottom: 10px;">
                    {{ course.requirements }}
                    {% for teacher in course.coaches %}
                        {% if loop.index <= 2 %}
                            <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                                <a href="{{ teacher.url }}" class="ajax" data-title="{{ teacher.firstname }} {{ teacher.lastname }}">
                                    <img src="{{ teacher.avatar }}" style="width: 24px; height: 24px; border-radius: 50%;" />
                                </a>
                                <span style="font-size: 12px; color: #475569;">{{ teacher.firstname }} {{ teacher.lastname }}</span>
                            </div>
                        {% endif %}
                    {% endfor %}
                </div>

                <div class="notifications">
                    {{ course.notifications }}
                </div>

                {% include 'user_portal/grid_course_student_info.tpl'|get_template with { 'student_info':course.student_info } %}

                <div class="et-course-footer">
                    <a href="{{ course.link }}" class="btn btn-et-primary btn-sm btn-block">
                        <em class="fa fa-play-circle"></em> Access Masterclass
                    </a>
                </div>
            </div>
        </div>
    </div>
{% endmacro %}

{% import _self as blocks %}

{% for row in session %}
    {% set collapsable = '' %}
    <div id="session-{{ row.id }}" class="session panel panel-default" style="margin-bottom: 30px;">
        <div class="panel-heading" style="display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <img src="{{ _p.web }}web/css/themes/chamilo/images/et/icon-calendar.svg" alt="Session" style="width: 20px; height: 20px;" />
                {% set session_link = _p.web_main ~ 'session/index.php?session_id=' ~ row.id %}
                <a href="{{ session_link }}" style="font-weight: 700; color: #0F172A; font-size: 16px;">
                    {{ row.title }}
                </a>
            </div>
            {% if row.show_actions %}
                <div class="pull-right">
                    <a href="{{ _p.web_main ~ "session/resume_session.php?id_session=" ~ row.id }}" class="btn btn-default btn-xs">
                        <em class="fa fa-pencil"></em> {{ "Edit"|get_lang }}
                    </a>
                </div>
            {% endif %}
        </div>

        <div class="session panel-body" id="collapse_{{ row.id }}">
            {% if row.show_description %}
                <div style="margin-bottom: 12px; color: #475569;">{{ row.description }}</div>
            {% endif %}
            <ul class="info-session list-inline" style="color: #64748B; font-size: 12.5px; margin-bottom: 16px;">
                {% if row.coach_name %}
                    <li>
                        <i class="fa fa-user-circle text-primary" aria-hidden="true"></i>
                        <strong>Mentor:</strong> {{ row.coach_name }}
                    </li>
                {% endif %}

                {% if hide_session_dates_in_user_portal == false %}
                    {% if row.date %}
                        <li>
                            <i class="fa fa-calendar-check-o text-success" aria-hidden="true"></i> {{ row.date }}
                        </li>
                    {% elseif row.duration %}
                        <li>
                            <i class="fa fa-clock-o text-warning" aria-hidden="true"></i> {{ row.duration }}
                        </li>
                    {% endif %}
                {% endif %}
            </ul>
            <div class="grid-courses courses-grid-view">
                {% if not group_courses %}
                    <div class="row">
                        {% for item in row.courses %}
                            {{ blocks.course_block(item, true) }}
                        {% endfor %}
                    </div>
                {% else %}
                    {% for category_code in row.course_categories %}
                        <h4>{{ category_code }}</h4>
                        <div class="row">
                            {% for course in row.courses %}
                                {% if course.category == category_code %}
                                    {{ blocks.course_block(course, false) }}
                                {% endif %}
                            {% endfor %}
                        </div>
                    {% endfor %}
                {% endif %}
            </div>
        </div>
    </div>
{% endfor %}
