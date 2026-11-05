{% if not courses is empty %}
    <div class="classic-courses">
    {% for item in courses %}
        <div class="panel panel-default et-course-card" onclick="window.location.href='{{ item.link }}';" style="margin-bottom: 20px; cursor: pointer; border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; transition: all 0.2s ease;">
            <div class="panel-body" style="padding: 20px;">
                <div class="row" style="display: flex; align-items: center; flex-wrap: wrap;">
                    <div class="col-md-2 col-sm-3 col-xs-12" style="margin-bottom: 12px;">
                        {% if item.visibility == constant('COURSE_VISIBILITY_CLOSED') and not item.current_user_is_teacher %}
                            <span class="thumbnail" style="margin-bottom: 0; border-radius: 10px; overflow: hidden; border: 1px solid #E2E8F0;">
                                {% if item.thumbnails != '' %}
                                    <img src="{{ item.thumbnails }}" title="{{ item.title }}" alt="{{ item.title }}" style="width: 100%; height: auto; object-fit: cover;" />
                                {% else %}
                                    {{ 'blackboard.png' | img(48, item.title ) }}
                                {% endif %}
                            </span>
                        {% else %}
                            <a href="{{ item.link }}" class="thumbnail" style="margin-bottom: 0; border-radius: 10px; overflow: hidden; display: block; border: 1px solid #E2E8F0;">
                                {% if item.thumbnails != '' %}
                                    <img src="{{ item.thumbnails }}" title="{{ item.title }}" alt="{{ item.title }}" style="width: 100%; height: auto; object-fit: cover;" />
                                {% else %}
                                    {{ 'blackboard.png' | img(48, item.title ) }}
                                {% endif %}
                            </a>
                        {% endif %}
                    </div>
                    <div class="col-md-7 col-sm-6 col-xs-12">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; flex-wrap: wrap;">
                            {% if item.category %}
                                <span class="badge" style="background: #E2E8F0; color: #334155; font-size: 11px;">{{ item.category }}</span>
                            {% endif %}
                            {% if item.code_course %}
                                <span class="badge" style="background: #F1F5F9; color: #64748B; font-size: 11px;">#{{ item.code_course }}</span>
                            {% endif %}
                            {% if item.lesson_count is defined and item.lesson_count > 0 %}
                                <span class="badge" style="background: #EEF2FF; color: #4338CA; font-size: 11px; font-weight: 600;">
                                    <i class="fa fa-book"></i> {{ item.lesson_count }} {{ item.lesson_count == 1 ? 'Lesson' : 'Lessons' }}
                                </span>
                            {% endif %}
                            {% if item.duration_formatted and item.duration_formatted != '' %}
                                <span class="badge" style="background: #F0FDF4; color: #15803D; font-size: 11px; font-weight: 600;">
                                    <i class="fa fa-clock-o"></i> {{ item.duration_formatted }}
                                </span>
                            {% endif %}
                        </div>

                        <h4 class="course-items-title" style="margin: 0 0 8px 0;">
                            {% if item.visibility == constant('COURSE_VISIBILITY_CLOSED') and not item.current_user_is_teacher %}
                                {{ item.title }}
                            {% else %}
                                <a href="{{ item.link }}" style="font-weight: 700; color: #0F172A; font-size: 16.5px; text-decoration: none;">
                                    {{ item.title }}
                                </a>
                            {% endif %}
                        </h4>

                        <div class="course-items-session" style="margin-bottom: 8px;">
                            <div class="list-teachers">
                                {% if item.teachers|length > 0 %}
                                    {% for teacher in item.teachers %}
                                        <a href="{{ teacher.url }}" class="teacher-link" data-title="{{ teacher.firstname }} {{ teacher.lastname }}" onclick="event.stopPropagation();" style="color: #64748B; font-size: 12.5px; font-weight: 500; text-decoration: none;">
                                            <em class="fa fa-user-circle" style="color: #E63946;"></em> {{ teacher.firstname }} {{ teacher.lastname }}
                                        </a>
                                    {% endfor %}
                                {% endif %}
                            </div>

                            <!-- Dynamic Course Visual Completion Progress Bar -->
                            {% set prog = (item.student_info.progress is defined and item.student_info.progress is not null) ? item.student_info.progress : 0 %}
                            <div class="et-course-progress-container" style="margin-top: 10px; max-width: 440px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; font-size: 11.5px;">
                                    <span style="font-weight: 600; color: #475569;">Course Completion</span>
                                    <span style="font-weight: 700; color: {{ prog == 100 ? '#16A34A' : (prog > 0 ? '#2563EB' : '#64748B') }};">{{ prog }}% Completed</span>
                                </div>
                                <div class="progress" style="height: 7px; margin: 0; background: #E2E8F0; border-radius: 4px; overflow: hidden; box-shadow: none;">
                                    <div class="progress-bar" role="progressbar" aria-valuenow="{{ prog }}" aria-valuemin="0" aria-valuemax="100" style="width: {{ prog }}%; background: {{ prog == 100 ? '#16A34A' : (prog > 0 ? 'linear-gradient(90deg, #10B981 0%, #059669 100%)' : '#CBD5E1') }};"></div>
                                </div>
                            </div>

                            {% include 'user_portal/course_student_info.tpl'|get_template with { 'student_info': item.student_info } %}
                        </div>
                    </div>

                    <div class="col-md-3 col-sm-3 col-xs-12 text-right" style="display: flex; flex-direction: column; gap: 8px; align-items: flex-end;">
                        <a href="{{ item.link }}" class="btn btn-et-primary btn-sm btn-block" style="font-weight: 600; padding: 8px 16px; border-radius: 6px;">
                            <em class="fa fa-play-circle"></em> Continue Learning
                        </a>
                        {% if item.unregister_button and et_view_mode == 'admin' %}
                            <span onclick="event.stopPropagation();">{{ item.unregister_button }}</span>
                        {% endif %}
                        {% if item.edit_actions != '' and et_view_mode == 'admin' %}
                            <a class="btn btn-default btn-xs" href="{{ item.edit_actions }}" onclick="event.stopPropagation();" style="border-radius: 4px;">
                                <em class="fa fa-pencil"></em> Manage
                            </a>
                        {% endif %}
                    </div>
                </div>
            </div>
        </div>
    {% endfor %}
    </div>
{% endif %}