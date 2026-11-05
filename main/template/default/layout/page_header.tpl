<div id="navigation" class="notification-panel">
    {{ help_content }}
    {{ bug_notification }}
</div>
{% block topbar %}
    {% include 'layout/topbar.tpl'|get_template %}
{% endblock %}

{% if header_extra_content is not null %}
    {{ header_extra_content }}
{% endif %}
<div class="header-wrapper">
    <div class="container">
        <div class="row" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
            <div class="col-xs-12 col-sm-6 col-md-5">
                <a href="{% if _u.logged == 1 and (et_view_mode == 'learner' or _u.is_admin != 1) %}{{ _p.web }}user_portal.php{% else %}{{ _p.web }}{% endif %}" class="et-brand-logo" title="Sparky LMS">
                    <img src="{{ _p.web }}web/css/themes/chamilo/images/et/et-logo.svg" alt="Sparky LMS" class="et-main-brand-logo" style="height: 46px; width: auto; max-width: 100%; object-fit: contain;" />
                </a>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-7">
                <div class="et-header-actions">
                    <div class="et-search-bar hidden-xs" style="position: relative;">
                        <em class="fa fa-search"></em>
                        <input type="text" placeholder="Search masterclasses, skills, courses..." id="et-global-search" autocomplete="off" onkeypress="if(event.key === 'Enter'){ window.location.href='{{ _p.web_main }}auth/courses.php?keyword=' + encodeURIComponent(this.value); }" />
                        <div id="et-search-results-dropdown" class="et-search-dropdown" style="display:none; position:absolute; top:calc(100% + 8px); left:0; width:100%; min-width:380px; max-width:540px; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 20px 40px rgba(15,23,42,0.18); z-index:99999; max-height:420px; overflow-y:auto; padding:6px 0;"></div>
                    </div>
                    {% if _u.logged == 1 %}
                        <a href="{{ _p.web_main }}messages/inbox.php" class="et-action-pill" title="Notifications">
                            <img src="{{ _p.web }}web/css/themes/chamilo/images/et/icon-notifications.svg" alt="Notifications" style="width:18px;height:18px;" />
                        </a>
                        <a href="{{ _p.web_main }}calendar/agenda_js.php" class="et-action-pill" title="Live Schedule">
                            <img src="{{ _p.web }}web/css/themes/chamilo/images/et/icon-calendar.svg" alt="Schedule" style="width:18px;height:18px;" />
                        </a>

                        <!-- Top-Right User Profile Dropdown -->
                        <div class="dropdown et-user-dropdown-wrapper" style="display:inline-block; vertical-align:middle; margin-left:10px;">
                            <a href="#" class="dropdown-toggle et-user-pill-btn" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" style="display:inline-flex; align-items:center; gap:8px; padding:3px 12px 3px 4px; border-radius:30px; background:#ffffff; border:1.5px solid #e2e8f0; text-decoration:none; box-shadow:0 1px 3px rgba(0,0,0,0.06); transition:all 0.2s ease;">
                                <div style="width:34px; height:34px; border-radius:50%; overflow:hidden; border:2px solid #4f46e5; display:flex; align-items:center; justify-content:center; background:#f1f5f9; flex-shrink:0;">
                                    {% if _u.avatar_small %}
                                        <img src="{{ _u.avatar_small }}" alt="{{ _u.complete_name }}" style="width:100%; height:100%; object-fit:cover;" onerror="this.onerror=null; this.src='{{ _p.web_main }}img/unknown.jpg';" />
                                    {% else %}
                                        <em class="fa fa-user" style="color:#64748b; font-size:16px;"></em>
                                    {% endif %}
                                </div>
                                <span class="hidden-xs hidden-sm" style="font-weight:600; font-size:13px; color:#1e293b; max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ _u.complete_name|default('Account') }}</span>
                                <em class="fa fa-chevron-down" style="font-size:10px; color:#94a3b8; margin-left:2px;"></em>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-right et-user-dropdown-menu" style="min-width:250px; padding:0; border-radius:12px; box-shadow:0 12px 32px rgba(15,23,42,0.15); border:1px solid #e2e8f0; margin-top:8px; overflow:hidden; z-index:9999;">
                                <li style="padding:16px; background:linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color:#ffffff;">
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div style="width:44px; height:44px; border-radius:50%; overflow:hidden; border:2px solid #4f46e5; flex-shrink:0; background:#ffffff;">
                                            <img src="{{ _u.avatar_medium|default(_u.avatar_small) }}" alt="{{ _u.complete_name }}" style="width:100%; height:100%; object-fit:cover;" onerror="this.onerror=null; this.src='{{ _p.web_main }}img/unknown.jpg';" />
                                        </div>
                                        <div style="overflow:hidden; flex:1;">
                                            <div style="font-weight:700; font-size:14px; color:#ffffff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ _u.complete_name }}</div>
                                            <div style="font-size:12px; color:#94a3b8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ _u.email }}</div>
                                        </div>
                                    </div>
                                </li>
                                                                {% if _u.is_admin == 1 %}
                                    <li style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                                        {% if et_view_mode == 'admin' %}
                                            <a href="{{ _p.web }}user_portal.php?action=set_view_mode&mode=learner" class="btn btn-sm btn-block" style="background: #eef2ff; color: #4338ca; border: 1.5px solid #c7d2fe; font-weight: 700; display: flex; align-items: center; justify-content: space-between; border-radius: 8px; padding: 7px 12px; text-decoration: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                                <span style="display: flex; align-items: center; gap: 8px;">
                                                    <i class="fa fa-graduation-cap" style="font-size: 15px;"></i> Switch to Learner View
                                                </span>
                                                <span class="badge" style="background: #4338ca; font-size: 9px; text-transform: uppercase;">Admin Mode</span>
                                            </a>
                                        {% else %}
                                            <a href="{{ _p.web }}index.php?action=set_view_mode&mode=admin" class="btn btn-sm btn-block" style="background: #f1f5f9; color: #334155; border: 1.5px solid #cbd5e1; font-weight: 700; display: flex; align-items: center; justify-content: space-between; border-radius: 8px; padding: 7px 12px; text-decoration: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                                <span style="display: flex; align-items: center; gap: 8px;">
                                                    <i class="fa fa-sliders" style="font-size: 15px;"></i> Switch to Admin View
                                                </span>
                                                <span class="badge" style="background: #4f46e5; font-size: 9px; text-transform: uppercase;">Learner Mode</span>
                                            </a>
                                        {% endif %}
                                    </li>
                                {% endif %}
<li style="padding:4px 0 0;">
                                    <a href="{{ _p.web_main }}auth/profile.php" class="et-menu-item-link" style="display:flex; align-items:center; gap:10px; padding:10px 18px; color:#334155; font-size:13px; font-weight:500; text-decoration:none;">
                                        <em class="fa fa-user-circle" style="color:#4f46e5; font-size:16px; width:18px; text-align:center;"></em>
                                        <span>{{ 'EditProfile'|get_lang }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ _p.web_main }}social/personal_data.php" class="et-menu-item-link" style="display:flex; align-items:center; gap:10px; padding:10px 18px; color:#334155; font-size:13px; font-weight:500; text-decoration:none;">
                                        <em class="fa fa-shield" style="color:#2563eb; font-size:16px; width:18px; text-align:center;"></em>
                                        <span>{{ 'PersonalDataReport'|get_lang }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ _p.web_main }}gradebook/my_certificates.php" class="et-menu-item-link" style="display:flex; align-items:center; gap:10px; padding:10px 18px; color:#334155; font-size:13px; font-weight:500; text-decoration:none;">
                                        <em class="fa fa-graduation-cap" style="color:#16a34a; font-size:16px; width:18px; text-align:center;"></em>
                                        <span>{{ 'MyCertificates'|get_lang }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ _p.web_main }}messages/inbox.php" class="et-menu-item-link" style="display:flex; align-items:center; gap:10px; padding:10px 18px; color:#334155; font-size:13px; font-weight:500; text-decoration:none;">
                                        <em class="fa fa-envelope" style="color:#8b5cf6; font-size:16px; width:18px; text-align:center;"></em>
                                        <span>{{ 'Inbox'|get_lang }}</span>
                                    </a>
                                </li>
                                <li>
                                    <a href="{{ _p.web_main }}social/myfiles.php" class="et-menu-item-link" style="display:flex; align-items:center; gap:10px; padding:10px 18px; color:#334155; font-size:13px; font-weight:500; text-decoration:none;">
                                        <em class="fa fa-folder-open" style="color:#f59e0b; font-size:16px; width:18px; text-align:center;"></em>
                                        <span>{{ 'MyFiles'|get_lang }}</span>
                                    </a>
                                </li>
                                <li role="separator" class="divider" style="margin:6px 0; border-top:1px solid #f1f5f9;"></li>
                                <li style="padding-bottom:6px;">
                                    <a href="{{ _p.web }}index.php?logout=logout&uid={{ _u.user_id }}" class="et-menu-item-link" style="display:flex; align-items:center; gap:10px; padding:10px 18px; color:#dc2626; font-size:13px; font-weight:600; text-decoration:none;">
                                        <em class="fa fa-sign-out" style="color:#dc2626; font-size:16px; width:18px; text-align:center;"></em>
                                        <span>{{ 'Logout'|get_lang }}</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    {% else %}
                        <a href="#login-block" id="btn-login" class="btn btn-et-primary">
                            <em class="fa fa-sign-in"></em> {{ 'Login'|get_lang }}
                        </a>
                    {% endif %}
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.et-user-pill-btn:hover, .et-user-pill-btn:focus {
    background: #f8fafc !important;
    border-color: #cbd5e1 !important;
}
.et-menu-item-link:hover, .et-menu-item-link:focus {
    background: #f8fafc !important;
    color: #0f172a !important;
}
</style>

{% block menu %}
{% include 'layout/menu.tpl'|get_template %}
{% endblock %}

{% include 'layout/course_navigation.tpl'|get_template %}
