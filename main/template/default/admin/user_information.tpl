{% import 'default/macro/macro.tpl' as display %}

<div class="user-information-dashboard" style="margin-bottom: 24px;">
    <!-- Profile & Metrics Row -->
    <div class="row">
        <!-- Left: Profile Identity Card -->
        <div class="col-md-4 col-sm-12" style="margin-bottom: 20px;">
            <div class="panel panel-default" style="border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 1px 4px rgba(0,0,0,0.05); background: #ffffff; padding: 24px 20px; text-align: center;">
                <div style="position: relative; display: inline-block; margin-bottom: 16px;">
                    <img src="{{ user.avatar }}" alt="{{ user.complete_name }}" style="width: 104px; height: 104px; border-radius: 50%; object-fit: cover; border: 3px solid #E2E8F0; box-shadow: 0 4px 10px rgba(0,0,0,0.08);" />
                    {% if user.user_is_online %}
                        <span style="position: absolute; bottom: 4px; right: 4px; width: 18px; height: 18px; border-radius: 50%; background: #10B981; border: 2px solid #ffffff;" title="Online"></span>
                    {% else %}
                        <span style="position: absolute; bottom: 4px; right: 4px; width: 18px; height: 18px; border-radius: 50%; background: #94A3B8; border: 2px solid #ffffff;" title="Offline"></span>
                    {% endif %}
                </div>

                <h3 style="margin: 0 0 4px 0; font-size: 20px; font-weight: 700; color: #0F172A;">
                    {{ user.complete_name }}
                </h3>
                <div style="color: #64748B; font-size: 13.5px; font-weight: 500; margin-bottom: 12px;">
                    @{{ user.username }}
                </div>

                <!-- Role & Status Badges -->
                <div style="display: flex; gap: 8px; justify-content: center; align-items: center; margin-bottom: 20px; flex-wrap: wrap;">
                    {% if user.is_admin == 1 %}
                        <span class="label" style="background: #E3122B; color: #fff; font-size: 11.5px; padding: 4px 10px; border-radius: 20px; font-weight: 700;">Administrator</span>
                    {% elseif user.status_id == 1 %}
                        <span class="label" style="background: #F59E0B; color: #fff; font-size: 11.5px; padding: 4px 10px; border-radius: 20px; font-weight: 700;">Instructor</span>
                    {% else %}
                        <span class="label" style="background: #4F46E5; color: #fff; font-size: 11.5px; padding: 4px 10px; border-radius: 20px; font-weight: 700;">Learner</span>
                    {% endif %}

                    {% if user.active == 1 %}
                        <span class="label" style="background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; font-size: 11.5px; padding: 3px 9px; border-radius: 20px; font-weight: 600;">Active Account</span>
                    {% else %}
                        <span class="label" style="background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; font-size: 11.5px; padding: 3px 9px; border-radius: 20px; font-weight: 600;">Inactive</span>
                    {% endif %}
                </div>

                <hr style="border: 0; border-top: 1px solid #F1F5F9; margin: 16px 0;" />

                
                <!-- Magic Login Link Direct Box -->
                <div style="background: #F8FAFC; border: 1px solid #C7D2FE; border-radius: 10px; padding: 14px 16px; margin-top: 18px; text-align: left;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 12px; font-weight: 700; color: #4F46E5; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;">
                            <i class="fa fa-magic"></i> Magic Login Link
                        </span>
                        <span class="label label-success" style="background: #10B981; font-size: 10.5px; padding: 2px 7px; border-radius: 4px;">Passwordless</span>
                    </div>
                    <p style="font-size: 12px; color: #64748B; margin-bottom: 8px; line-height: 1.4;">
                        Direct link to log in as this user without requiring password credentials:
                    </p>
                    <div class="input-group" style="margin-bottom: 10px;">
                        <input type="text" id="directMagicLinkInput" class="form-control" readonly value="{{ user.magic_login_url }}" style="font-size: 12px; font-family: monospace; background: #ffffff; color: #0F172A; cursor: pointer; height: 38px;" onclick="this.select();" />
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-primary" id="copyDirectMagicBtn" onclick="quickCopyMagicLink('{{ user.magic_login_url }}', '{{ user.complete_name }}')" style="background: #4F46E5; border-color: #4F46E5; font-weight: 600; height: 38px; padding: 0 16px;">
                                <i class="fa fa-copy"></i> Copy
                            </button>
                        </span>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11.5px; color: #64748B;">
                        <span><i class="fa fa-shield" style="color: #10B981;"></i> Valid for 30 days</span>
                        <a href="{{ user.magic_login_url }}" target="_blank" style="color: #4F46E5; font-weight: 600; text-decoration: none;">
                            <i class="fa fa-external-link"></i> Test Login in New Tab
                        </a>
                    </div>
                </div>

                <!-- Contact & Account Details -->
                <div style="text-align: left; font-size: 13px; color: #334155; line-height: 1.8;">
                    <div style="display: flex; align-items: center; margin-bottom: 8px;">
                        <i class="fa fa-envelope-o" style="width: 24px; color: #64748B;"></i>
                        <span style="color: #64748B; width: 60px;">Email:</span>
                        <a href="mailto:{{ user.email }}" style="color: #0F172A; font-weight: 600; text-decoration: none; word-break: break-all;">
                            {{ user.email|default('N/A') }}
                        </a>
                    </div>
                    <div style="display: flex; align-items: center; margin-bottom: 8px;">
                        <i class="fa fa-phone" style="width: 24px; color: #64748B;"></i>
                        <span style="color: #64748B; width: 60px;">Phone:</span>
                        <span style="color: #0F172A; font-weight: 600;">
                            {{ user.phone|default('Not provided') }}
                        </span>
                    </div>
                    {% if user.official_code %}
                    <div style="display: flex; align-items: center; margin-bottom: 8px;">
                        <i class="fa fa-id-card-o" style="width: 24px; color: #64748B;"></i>
                        <span style="color: #64748B; width: 60px;">Code:</span>
                        <span style="color: #0F172A; font-weight: 600;">{{ user.official_code }}</span>
                    </div>
                    {% endif %}
                    {% if user.created %}
                    <div style="display: flex; align-items: baseline; margin-top: 10px; font-size: 11.5px; color: #94A3B8;">
                        <i class="fa fa-calendar-check-o" style="width: 24px; color: #94A3B8;"></i>
                        <span>{{ user.created|raw }}</span>
                    </div>
                    {% endif %}
                </div>
            </div>
        </div>

        <!-- Right: Learning Metrics & Activity Summary -->
        <div class="col-md-8 col-sm-12">
            <!-- 4 Quick Stats Cards Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 16px;">
                <div class="panel panel-default" style="border-radius: 10px; border: 1px solid #E2E8F0; padding: 16px; margin: 0; display: flex; align-items: center; gap: 14px; background: #FAFBFD;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #EEF2FF; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fa fa-book" style="font-size: 18px; color: #4F46E5;"></i>
                    </div>
                    <div>
                        <div style="font-size: 20px; font-weight: 700; color: #0F172A;">{{ user.courses_count|default(0) }}</div>
                        <div style="font-size: 12px; color: #64748B; font-weight: 500;">Enrolled Courses</div>
                    </div>
                </div>

                <div class="panel panel-default" style="border-radius: 10px; border: 1px solid #E2E8F0; padding: 16px; margin: 0; display: flex; align-items: center; gap: 14px; background: #FAFBFD;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #FFFBEB; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fa fa-calendar-check-o" style="font-size: 18px; color: #D97706;"></i>
                    </div>
                    <div>
                        <div style="font-size: 20px; font-weight: 700; color: #0F172A;">{{ user.sessions_count|default(0) }}</div>
                        <div style="font-size: 12px; color: #64748B; font-weight: 500;">Active Sessions</div>
                    </div>
                </div>

                <div class="panel panel-default" style="border-radius: 10px; border: 1px solid #E2E8F0; padding: 16px; margin: 0; display: flex; align-items: center; gap: 14px; background: #FAFBFD;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #ECFDF5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fa fa-certificate" style="font-size: 18px; color: #059669;"></i>
                    </div>
                    <div>
                        <div style="font-size: 20px; font-weight: 700; color: #0F172A;">{{ user.certificates_count|default(0) }}</div>
                        <div style="font-size: 12px; color: #64748B; font-weight: 500;">ET Credentials</div>
                    </div>
                </div>

                <div class="panel panel-default" style="border-radius: 10px; border: 1px solid #E2E8F0; padding: 16px; margin: 0; display: flex; align-items: center; gap: 14px; background: #FAFBFD;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #FEF2F2; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <i class="fa fa-clock-o" style="font-size: 18px; color: #E3122B;"></i>
                    </div>
                    <div>
                        <div style="font-size: 13px; font-weight: 700; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px;" title="{{ user.last_connection|default('Never') }}">
                            {{ user.last_connection|default('Never') }}
                        </div>
                        <div style="font-size: 12px; color: #64748B; font-weight: 500;">Latest Login</div>
                    </div>
                </div>
            </div>

            <!-- Detailed Activity Panel -->
            <div class="panel panel-default" style="border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 1px 4px rgba(0,0,0,0.05); background: #ffffff; padding: 20px 24px;">
                <h4 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 700; color: #1E293B; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-line-chart text-danger"></i> Login & Platform Activity
                </h4>
                <div class="row">
                    <div class="col-sm-4" style="margin-bottom: 12px;">
                        <div style="font-size: 11.5px; color: #64748B; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">First Platform Login</div>
                        <div style="font-size: 13.5px; font-weight: 600; color: #0F172A;">{{ user.first_connection|default('Not recorded') }}</div>
                    </div>
                    <div class="col-sm-4" style="margin-bottom: 12px;">
                        <div style="font-size: 11.5px; color: #64748B; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Latest Platform Login</div>
                        <div style="font-size: 13.5px; font-weight: 600; color: #0F172A;">{{ user.last_connection|default('Not recorded') }}</div>
                    </div>
                    <div class="col-sm-4" style="margin-bottom: 12px;">
                        <div style="font-size: 11.5px; color: #64748B; text-transform: uppercase; font-weight: 600; margin-bottom: 4px;">Latest Course Connection</div>
                        <div style="font-size: 13.5px; font-weight: 600; color: #0F172A;">{{ user.last_connection_in_course|default('Never') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
