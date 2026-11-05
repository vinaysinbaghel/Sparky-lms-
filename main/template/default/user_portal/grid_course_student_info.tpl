{% if student_info %}
    <div class="course-student-info" style="margin: 8px 0 12px 0;">
        <div class="student-info">
            <div style="display: flex; align-items: center; justify-content: space-between; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px; padding: 6px 12px;">
                {% if _u.is_admin == 1 and et_view_mode == 'admin' %}
                    <div style="display: flex; align-items: center; gap: 6px; color: #334155; font-size: 12px; font-weight: 600;">
                        <i class="fa fa-users" style="color: #6366F1; font-size: 13px;"></i>
                        <span>{{ student_info.student_count|default(0) }} {{ student_info.student_count == 1 ? 'Student Enrolled' : 'Students Enrolled' }}</span>
                    </div>
                {% else %}
                    {% set prog = student_info.progress|default(0) %}
                    {% if prog == 100 %}
                        <div style="display: flex; align-items: center; gap: 5px; color: #15803D; font-size: 11.5px; font-weight: 600;">
                            <i class="fa fa-check-circle" style="color: #16A34A; font-size: 12px;"></i>
                            <span>Completed</span>
                        </div>
                    {% elseif prog > 0 %}
                        <div style="display: flex; align-items: center; gap: 5px; color: #1D4ED8; font-size: 11.5px; font-weight: 600;">
                            <i class="fa fa-play" style="color: #2563EB; font-size: 11px;"></i>
                            <span>In Progress</span>
                        </div>
                    {% else %}
                        <div style="display: flex; align-items: center; gap: 5px; color: #64748B; font-size: 11.5px; font-weight: 600;">
                            <i class="fa fa-circle-o" style="color: #94A3B8; font-size: 12px;"></i>
                            <span>Not Started</span>
                        </div>
                    {% endif %}
                {% endif %}

                {% if student_info.has_certificate %}
                    <div style="font-size: 11.5px; color: #B45309; font-weight: 600; display: flex; align-items: center; gap: 4px;">
                        <i class="fa fa-certificate" style="color: #D97706; font-size: 12px;"></i>
                        <span>Certificate</span>
                    </div>
                {% endif %}
            </div>
            {% if not student_info.score is null and student_info.score > 0 %}
                <div style="font-size: 11.5px; color: #64748B; margin-top: 4px; text-align: right;">
                    Grade: {{ student_info.score }}%
                </div>
            {% endif %}
        </div>
    </div>
{% endif %}