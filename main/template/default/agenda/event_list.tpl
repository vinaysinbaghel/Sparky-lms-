<style>
.et-event-list-hero {
    background: linear-gradient(135deg, #0B1727 0%, #1E293B 100%);
    color: #FFFFFF;
    border-radius: 12px;
    padding: 22px 28px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(11, 23, 39, 0.12);
}

.et-event-list-title {
    font-size: 22px;
    font-weight: 800;
    margin: 0 0 6px 0;
    color: #FFFFFF;
    letter-spacing: -0.02em;
}

.et-event-card {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    margin-bottom: 14px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    transition: transform 0.15s, box-shadow 0.15s;
}

.et-event-card:hover {
    box-shadow: 0 4px 14px rgba(0,0,0,0.06);
}

.et-event-card-header {
    padding: 16px 20px;
    background: #FFFFFF;
    border-bottom: 1px solid transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.et-event-title {
    font-size: 15.5px;
    font-weight: 700;
    color: #0F172A;
    margin: 0 0 6px 0;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.et-event-date-badge {
    font-size: 13px;
    font-weight: 600;
    color: #64748B;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.et-badge-course {
    background: #16A34A;
    color: #FFFFFF;
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
}

.et-badge-personal {
    background: #4F46E5;
    color: #FFFFFF;
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
}

.et-badge-platform {
    background: #EF4444;
    color: #FFFFFF;
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
}
</style>

<!-- Modern Hero Header -->
<div class="et-event-list-hero">
    <div>
        <h1 class="et-event-list-title"><i class="fa fa-list-alt" style="color: #818CF8;"></i> Schedule & Agenda Events List</h1>
        <p style="color: #94A3B8; font-size: 13.5px; margin: 0;">Comprehensive timeline of all scheduled course sessions, deadlines, and personal milestones.</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <a href="agenda_js.php?type={{ type }}{% if _cid %}&{{ cid_req }}{% endif %}" class="btn btn-sm" style="background: rgba(255,255,255,0.12); color: #fff; font-weight: 600; border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; padding: 7px 14px;">
            <i class="fa fa-calendar"></i> Month Calendar View
        </a>
        <a href="agenda_js.php?type={{ type }}{% if _cid %}&{{ cid_req }}{% endif %}&action=add_modal" class="btn btn-sm" style="background: #E3122B; border: 1px solid #E3122B; color: #fff; font-weight: 700; border-radius: 6px; padding: 7px 16px; box-shadow: 0 2px 6px rgba(227, 18, 43, 0.28);">
            <i class="fa fa-plus-circle"></i> Add Event
        </a>
    </div>
</div>

<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
    {% for event in agenda_events %}
        <div class="et-event-card">
            <div class="et-event-card-header" role="tab" id="heading-{{ event.id }}" data-toggle="collapse" data-parent="#accordion" data-target="#collapse-{{ event.id }}">
                <div>
                    <h4 class="et-event-title">
                        {% if event.type == 'course' %}
                            <span class="et-badge-course"><i class="fa fa-graduation-cap"></i> {{ event.course_name ? event.course_name : 'Course' }}</span>
                        {% elseif event.type == 'admin' %}
                            <span class="et-badge-platform"><i class="fa fa-bullhorn"></i> Platform</span>
                        {% else %}
                            <span class="et-badge-personal"><i class="fa fa-user"></i> Personal</span>
                        {% endif %}
                        <span>{{ event.title }}</span>
                    </h4>
                    <span class="et-event-date-badge">
                        <i class="fa fa-clock-o text-muted"></i>
                        {{ event.start_date_localtime }}
                        &dash;
                        {% if event.allDay %}
                            {{ 'AllDay' | get_lang }}
                        {% else %}
                            {{ event.end_date_localtime }}
                        {% endif %}
                    </span>
                </div>

                {% if is_allowed_to_edit and show_action %}
                    <div class="pull-right" onclick="event.stopPropagation();">
                        {% if event.visibility == 1 %}
                            <a class="btn btn-default btn-xs"
                               href="{% if url %}{{ url }}{% else %}{{ event.url }}{% endif %}&action=change_visibility&visibility=0&id={{ event.real_id }}&type={{ event.type }}" title="Visible">
                                <i class="fa fa-eye text-success"></i>
                            </a>
                        {% else %}
                            {% if event.type == 'course' or event.type == 'session' %}
                                <a class="btn btn-default btn-xs"
                                   href="{% if url %}{{ url }}{% else %}{{ event.url }}{% endif %}&action=change_visibility&visibility=1&id={{ event.real_id }}&type={{ event.type }}" title="Hidden">
                                    <i class="fa fa-eye-slash text-muted"></i>
                                </a>
                            {% endif %}
                        {% endif %}
                    </div>
                {% endif %}
            </div>
            <div id="collapse-{{ event.id }}" class="panel-collapse collapse" role="tabpanel"
                 aria-labelledby="heading-{{ event.id }}">
                <div style="padding: 16px 20px; background: #F8FAFC; border-top: 1px solid #E2E8F0;">
                    {% if event.description %}
                        <div style="font-size: 13.5px; color: #334155; line-height: 1.6; margin-bottom: 8px;">
                            {{ event.description }}
                        </div>
                    {% else %}
                        <div style="font-size: 13.5px; color: #94A3B8; font-style: italic;">
                            No additional description provided.
                        </div>
                    {% endif %}

                    {% if event.comment %}
                        <div style="font-size: 13px; color: #64748B; margin-top: 6px;">
                            <strong>Note:</strong> {{ event.comment }}
                        </div>
                    {% endif %}

                    {% if event.attachment %}
                        <div style="margin-top: 10px;">
                            <i class="fa fa-paperclip"></i> {{ event.attachment }}
                        </div>
                    {% endif %}
                </div>
            </div>
        </div>
    {% else %}
        <div class="alert alert-info" style="border-radius:8px; padding:30px 20px; text-align:center; background:#F8FAFC; border:1px solid #E2E8F0;">
            <i class="fa fa-calendar-o" style="font-size:36px; margin-bottom:12px; display:block; color:#818CF8;"></i>
            <h4 style="font-weight:700; color:#0F172A; margin:0 0 6px 0;">No Scheduled Events Found</h4>
            <p style="margin:0; color:#64748B; font-size:13.5px;">You do not have any upcoming course workshops, live sessions, or milestones.</p>
        </div>
    {% endfor %}
</div>