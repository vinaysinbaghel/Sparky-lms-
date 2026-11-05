{% if _u.logged == 0 %}
    {% if login_form %}
        <div id="login-block" class="panel panel-default et-login-panel" style="border: 1px solid #E2E8F0; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); background: #FFFFFF;">
            {# Executive Header #}
            <div style="background: #0F172A; padding: 24px 28px; border-bottom: 3px solid #4F46E5; color: white; display: flex; align-items: center; gap: 14px;">
                <img src="{{ _p.web }}web/css/themes/chamilo/images/et/et-mark-placeholder.svg" alt="Sparky LMS" style="width: 38px; height: 38px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.3);" />
                <div>
                    <div style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 17px; font-weight: 800; line-height: 1.2; letter-spacing: -0.01em;">Sparky Portal</div>
                    <div style="font-size: 12px; color: #94A3B8; margin-top: 3px;">Sign in to access your courses & dashboard</div>
                </div>
            </div>

            <div class="panel-body" style="padding: 28px 28px 24px;">
                {{ login_language_form }}

                {% if plugin_login_top is not null %}
                    <div id="plugin_login_top">
                        {{ plugin_login_top }}
                    </div>
                {% endif %}

                {{ login_failed }}

                {# Standard Credentials Form #}
                <div class="et-credentials-form-wrap">
                    {{ login_form }}
                </div>

                {# Links & Password Reset #}
                {% if "allow_lostpassword" | api_get_setting == 'true' or "allow_registration" | api_get_setting == 'true' %}
                    <ul class="nav nav-pills nav-stacked" style="margin-top: 18px; border-top: 1px solid #E2E8F0; padding-top: 14px;">
                        {% if "allow_registration" | api_get_setting != 'false' %}
                            <li><a href="{{ _p.web_main }}auth/inscription.php" style="font-weight: 700; color: #4F46E5; font-size: 13.5px;"> <em class="fa fa-user-plus"></em> {{ 'SignUp' | get_lang }} </a></li>
                        {% endif %}

                        {% if "allow_lostpassword" | api_get_setting == 'true' %}
                            {% set pass_reminder_link = 'pass_reminder_custom_link'|api_get_configuration_value %}
                            {% set lost_password_link = _p.web_main ~ 'auth/lostPassword.php' %}

                            {% if not pass_reminder_link is empty %}
                                {% set lost_password_link = pass_reminder_link %}
                            {% endif %}

                            <li><a href="{{ lost_password_link }}" style="font-size: 13px; color: #64748B; font-weight: 500;"> <em class="fa fa-key"></em> {{ 'LostPassword' | get_lang }} </a></li>
                        {% endif %}
                    </ul>
                {% endif %}

                {% if plugin_login_bottom is not null %}
                    <div id="plugin_login_bottom">
                        {{ plugin_login_bottom }}
                    </div>
                {% endif %}
            </div>
        </div>

        <style>
            .et-credentials-form-wrap form .form-control {
                height: 44px;
                border-radius: 8px;
                border: 1px solid #CBD5E1;
                font-size: 14px;
                padding-left: 12px;
                box-shadow: none;
                transition: border-color 0.2s;
            }
            .et-credentials-form-wrap form .form-control:focus {
                border-color: #4F46E5;
                box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            }
            .et-credentials-form-wrap form button[type="submit"],
            .et-credentials-form-wrap form #formLogin_submitAuth {
                height: 44px;
                background: #4F46E5 !important;
                border: none !important;
                border-radius: 8px !important;
                font-family: 'Plus Jakarta Sans', sans-serif !important;
                font-size: 14.5px !important;
                font-weight: 700 !important;
                letter-spacing: 0.01em;
                box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25) !important;
                transition: all 0.2s ease !important;
                margin-top: 10px;
            }
            .et-credentials-form-wrap form button[type="submit"]:hover,
            .et-credentials-form-wrap form #formLogin_submitAuth:hover {
                background: #4338CA !important;
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(79, 70, 229, 0.35) !important;
            }
        </style>
    {% endif %}
{% endif %}
