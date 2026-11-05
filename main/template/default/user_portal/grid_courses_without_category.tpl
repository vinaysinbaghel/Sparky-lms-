{% if not courses is empty %}
    <div class="grid-courses courses-grid-view">
        <div class="row">
            {% for item in courses %}
                <div class="col-xs-12 col-sm-6 col-md-4">
                    <div class="et-course-card my-courses" onclick="window.location.href='{{ item.link }}';" style="cursor: pointer; position: relative;">
                        <div class="et-course-media">
                            {% if item.visibility == constant('COURSE_VISIBILITY_CLOSED') and not item.current_user_is_teacher %}
                                <img src="{{ item.image }}" alt="{{ item.title }}" class="img-responsive">
                            {% else %}
                                <a title="{{ item.title }}" href="{{ item.link }}">
                                    <img src="{{ item.image }}" alt="{{ item.title }}" class="img-responsive">
                                </a>
                            {% endif %}

                            {% if item.category != '' %}
                                <span class="et-course-badge">{{ item.category }}</span>
                            {% else %}
                                <span class="et-course-badge live">Masterclass</span>
                            {% endif %}

                            {% if item.edit_actions != '' %}
                                <div class="admin-actions" style="position: absolute; top: 10px; right: 10px; z-index: 3;" onclick="event.stopPropagation();">
                                    <a class="btn btn-default btn-xs" href="{{ item.edit_actions }}" style="background: rgba(255,255,255,0.9); border-radius: 50%; width: 28px; height: 28px; padding: 0;" title="Edit Course">
                                        <i class="fa fa-pencil" aria-hidden="true"></i>
                                    </a>
                                </div>
                            {% endif %}
                        </div>

                        <div class="et-course-body">
                            <h4 class="et-course-title" title="{{ item.title }}">
                                {% if item.visibility == constant('COURSE_VISIBILITY_CLOSED') and not item.current_user_is_teacher %}
                                    {{ item.title_cut }}
                                {% else %}
                                    <a title="{{ item.title }}" href="{{ item.link }}">{{ item.title_cut }}</a>
                                {% endif %}
                            </h4>

                            <div class="et-course-meta">
                                <span class="et-course-meta-item">
                                    <img src="{{ _p.web }}web/css/themes/chamilo/images/et/icon-play.svg" alt="Lessons" style="width: 14px; height: 14px;" />
                                    <span>{% if item.lesson_count is defined and item.lesson_count > 0 %}{{ item.lesson_count }} {{ item.lesson_count == 1 ? 'Lesson' : 'Lessons' }}{% else %}Modules{% endif %}</span>
                                </span>
                                {% if item.duration_formatted and item.duration_formatted != '' %}
                                    <span class="et-course-meta-item" style="margin-left: 8px;">
                                        <i class="fa fa-clock-o text-muted"></i>
                                        <span>{{ item.duration_formatted }}</span>
                                    </span>
                                {% endif %}
                                {% if item.code_course %}
                                    <span class="et-course-meta-item" style="margin-left: auto;">
                                        <span class="label label-default" style="font-size: 10px; background: #E2E8F0; color: #475569;">#{{ item.code_course }}</span>
                                    </span>
                                {% endif %}
                            </div>

                            <div class="block-author" style="margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                {% for teacher in item.teachers %}
                                    {% if loop.index <= 2 %}
                                        <a href="{{ teacher.url }}" class="ajax" data-title="{{ teacher.firstname }} {{ teacher.lastname }}" title="{{ teacher.firstname }} {{ teacher.lastname }}" onclick="event.stopPropagation();">
                                            <img title="{{ teacher.firstname }} {{ teacher.lastname }}" src="{{ teacher.avatar }}" style="width: 26px; height: 26px; border-radius: 50%; border: 1px solid #CBD5E1;" />
                                        </a>
                                        <span style="font-size: 12px; color: #475569; font-weight: 500;">{{ teacher.firstname }} {{ teacher.lastname }}</span>
                                    {% endif %}
                                {% endfor %}
                            </div>

                            {% if item.notifications %}
                                <div class="notifications" style="margin-bottom: 10px;" onclick="event.stopPropagation();">{{ item.notifications }}</div>
                            {% endif %}

                            {% include 'user_portal/grid_course_student_info.tpl'|get_template with { 'student_info':item.student_info } %}

                            <div class="et-course-footer">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                    <a href="{{ item.link }}" class="btn btn-et-primary btn-sm" style="flex-grow: 1;">
                                        <em class="fa fa-play-circle"></em> Continue Learning
                                    </a>
                                    {% if item.unregister_button %}
                                        <span onclick="event.stopPropagation();">{{ item.unregister_button }}</span>
                                    {% endif %}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            {% endfor %}
        </div>
    </div>
{% endif %}
