{% if is_course_admin and not is_student_view %}
    <div class="et-course-control-center" style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:16px; padding:24px; margin-bottom:32px; box-shadow:0 6px 20px rgba(0,0,0,0.04);">
        <!-- Executive Header & Metadata -->
        <div style="display:flex; align-items:flex-start; justify-content:space-between; flex-wrap:wrap; gap:16px; border-bottom:1px solid #F1F5F9; padding-bottom:20px; margin-bottom:24px;">
            <div>
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                    <span class="label" style="background:#4F46E5; color:#FFFFFF; font-size:11px; font-weight:700; letter-spacing:0.5px; text-transform:uppercase; border-radius:4px; padding:4px 8px;">
                        Sparky Masterclass
                    </span>
                    {% if course_info.category_name %}
                        <span class="label" style="background:#0F172A; color:#FFFFFF; font-size:11px; font-weight:600; border-radius:4px; padding:4px 8px;">
                            {{ course_info.category_name }}
                        </span>
                    {% endif %}
                </div>
                <h2 style="font-size:22px; font-weight:800; color:#0F172A; margin:0 0 8px 0; letter-spacing:-0.4px;">
                    {{ course_info.title }}
                </h2>
                <div style="display:flex; align-items:center; gap:16px; font-size:13px; color:#64748B; flex-wrap:wrap;">
                    <span><em class="fa fa-hashtag text-muted"></em> Code: <strong>{{ course_info.visual_code }}</strong></span>
                    <span><em class="fa fa-users text-muted"></em> Enrolled Learners: <strong>{{ student_count }}</strong></span>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <a href="?{{ cid_req }}&isStudentView=true" class="btn btn-default" style="display:inline-flex; align-items:center; gap:8px; font-weight:600; border:1px solid #CBD5E1; color:#334155; border-radius:8px; padding:8px 16px;">
                    <em class="fa fa-eye" style="color:#0284C7;"></em> Preview as Learner
                </a>
            </div>
        </div>

        <!-- Primary 4 Sparky Quick Action Cards -->
        <h4 style="font-size:14px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:16px;">
            Quick Management Hub
        </h4>
        <div class="row" style="margin-bottom:20px;">
            <!-- Card 1: Videos & Study Material -->
            <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom:16px;">
                <div class="et-action-card" style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:18px; height:100%; display:flex; flex-direction:column; justify-content:space-between; transition:all 0.2s ease;">
                    <div>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                            <div style="width:40px; height:40px; border-radius:10px; background:rgba(79,70,229,0.1); color:#4F46E5; display:flex; align-items:center; justify-content:center; font-size:18px;">
                                <em class="fa fa-film"></em>
                            </div>
                            <span style="font-size:11px; font-weight:600; color:#64748B; background:#FFFFFF; border:1px solid #E2E8F0; padding:2px 8px; border-radius:12px;">Documents</span>
                        </div>
                        <h4 style="font-size:15px; font-weight:700; color:#0F172A; margin:0 0 6px 0;">Videos & Material</h4>
                        <p style="font-size:12px; color:#64748B; margin:0 0 16px 0; line-height:1.4;">Upload MP4 video masterclasses, PDFs, slide decks and resources.</p>
                    </div>
                    <div>
                        <a href="{{ web_code_path }}document/document.php?{{ cid_req }}" class="btn btn-et-primary btn-block" style="font-weight:600; font-size:13px; border-radius:6px; padding:8px 12px; margin-bottom:6px;">
                            <em class="fa fa-upload"></em> + Add Videos & Docs
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card 2: Learning Paths & Modules -->
            <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom:16px;">
                <div class="et-action-card" style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:18px; height:100%; display:flex; flex-direction:column; justify-content:space-between; transition:all 0.2s ease;">
                    <div>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                            <div style="width:40px; height:40px; border-radius:10px; background:rgba(2,132,199,0.1); color:#0284C7; display:flex; align-items:center; justify-content:center; font-size:18px;">
                                <em class="fa fa-sitemap"></em>
                            </div>
                            <span style="font-size:11px; font-weight:600; color:#64748B; background:#FFFFFF; border:1px solid #E2E8F0; padding:2px 8px; border-radius:12px;">Curriculum</span>
                        </div>
                        <h4 style="font-size:15px; font-weight:700; color:#0F172A; margin:0 0 6px 0;">Learning Paths</h4>
                        <p style="font-size:12px; color:#64748B; margin:0 0 16px 0; line-height:1.4;">Structure course modules, chapter sequences and video lessons.</p>
                    </div>
                    <div>
                        <a href="{{ web_code_path }}lp/lp_controller.php?{{ cid_req }}" class="btn btn-et-primary btn-block" style="font-weight:600; font-size:13px; border-radius:6px; padding:8px 12px; margin-bottom:6px;">
                            <em class="fa fa-sitemap"></em> Build Curriculum
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card 3: Certificates & Evaluation -->
            <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom:16px;">
                <div class="et-action-card" style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:18px; height:100%; display:flex; flex-direction:column; justify-content:space-between; transition:all 0.2s ease;">
                    <div>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                            <div style="width:40px; height:40px; border-radius:10px; background:rgba(217,119,6,0.1); color:#D97706; display:flex; align-items:center; justify-content:center; font-size:18px;">
                                <em class="fa fa-certificate"></em>
                            </div>
                            <span style="font-size:11px; font-weight:600; color:#64748B; background:#FFFFFF; border:1px solid #E2E8F0; padding:2px 8px; border-radius:12px;">Credentials</span>
                        </div>
                        <h4 style="font-size:15px; font-weight:700; color:#0F172A; margin:0 0 6px 0;">Certificates</h4>
                        <p style="font-size:12px; color:#64748B; margin:0 0 16px 0; line-height:1.4;">Configure passing score rules and generate official certificates.</p>
                    </div>
                    <div>
                        <a href="{{ web_code_path }}gradebook/gradebook.php?{{ cid_req }}" class="btn btn-et-primary btn-block" style="font-weight:600; font-size:13px; border-radius:6px; padding:8px 12px; margin-bottom:6px;">
                            <em class="fa fa-certificate"></em> Manage Certificates
                        </a>
                    </div>
                </div>
            </div>

            <!-- Card 4: Learners & Bulk CSV Import -->
            <div class="col-xs-12 col-sm-6 col-md-3" style="margin-bottom:16px;">
                <div class="et-action-card" style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:12px; padding:18px; height:100%; display:flex; flex-direction:column; justify-content:space-between; transition:all 0.2s ease;">
                    <div>
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
                            <div style="width:40px; height:40px; border-radius:10px; background:rgba(16,185,129,0.1); color:#10B981; display:flex; align-items:center; justify-content:center; font-size:18px;">
                                <em class="fa fa-users"></em>
                            </div>
                            <span style="font-size:11px; font-weight:600; color:#64748B; background:#FFFFFF; border:1px solid #E2E8F0; padding:2px 8px; border-radius:12px;">{{ student_count }} Learners</span>
                        </div>
                        <h4 style="font-size:15px; font-weight:700; color:#0F172A; margin:0 0 6px 0;">Bulk Student Enroll</h4>
                        <p style="font-size:12px; color:#64748B; margin:0 0 16px 0; line-height:1.4;">Bulk enroll learners via CSV and manage registered student roster.</p>
                    </div>
                    <div>
                        <a href="{{ web_code_path }}user/user_import.php?{{ cid_req }}&action=import&type=5" class="btn btn-et-primary btn-block" style="font-weight:600; font-size:13px; border-radius:6px; padding:8px 12px; margin-bottom:6px;">
                            <em class="fa fa-file-excel-o"></em> Bulk Enroll (CSV)
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Quick Admin Action Bar -->
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:12px 16px;">
            <span style="font-size:13px; font-weight:700; color:#475569;">
                <em class="fa fa-sliders" style="color: #4F46E5;"></em> Course Controls:
            </span>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <a href="{{ web_code_path }}user/user.php?{{ cid_req }}" class="btn btn-default btn-sm" style="font-weight:600; border-radius:6px;">
                    <em class="fa fa-users text-muted"></em> Student Roster ({{ student_count }})
                </a>
                <a href="{{ web_code_path }}exercise/exercise.php?{{ cid_req }}" class="btn btn-default btn-sm" style="font-weight:600; border-radius:6px;">
                    <em class="fa fa-check-square-o text-muted"></em> Assessments & Tests
                </a>
                <a href="{{ web_code_path }}tracking/courseLog.php?{{ cid_req }}" class="btn btn-default btn-sm" style="font-weight:600; border-radius:6px;">
                    <em class="fa fa-line-chart text-muted"></em> Progress & Tracking
                </a>
                <a href="{{ web_code_path }}course_info/infocours.php?{{ cid_req }}" class="btn btn-default btn-sm" style="font-weight:600; border-radius:6px;">
                    <em class="fa fa-cog text-muted"></em> Course Settings
                </a>
            </div>
        </div>
    </div>
{% elseif is_student_view %}
    <div style="display:flex; align-items:center; justify-content:space-between; background:#FFFBEB; border:1px solid #FDE68A; border-radius:10px; padding:12px 20px; margin-bottom:24px;">
        <span style="font-size:13px; font-weight:600; color:#92400E;">
            <em class="fa fa-info-circle"></em> You are previewing this course in <strong>Student / Learner Mode</strong>.
        </span>
        <a href="?{{ cid_req }}&isStudentView=false" class="btn btn-warning btn-sm" style="font-weight:600; border-radius:6px;">
            <em class="fa fa-pencil"></em> Return to Teacher Mode
        </a>
    </div>
{% endif %}

<!-- Course Tools Grid -->
{% for block in blocks %}
    {% if block.title %}
        <div class="page-header" style="margin:24px 0 16px 0; border-bottom:2px solid #E2E8F0; padding-bottom:8px;">
            <h4 class="title-tools" style="font-size:16px; font-weight:800; color:#0F172A; text-transform:uppercase; letter-spacing:0.5px; margin:0;">
                {{ block.title }}
            </h4>
        </div>
    {% endif %}

    <div class="row {{ block.class }}" style="display:flex; flex-wrap:wrap;">
        {% for item in block.content %}
            <div class="col-xs-6 col-sm-4 col-md-3" style="margin-bottom:16px;">
                <div class="course-tool et-tool-card" style="background:#FFFFFF; border:1px solid #E2E8F0; border-radius:12px; padding:16px; height:100%; display:flex; flex-direction:column; align-items:center; text-align:center; transition:all 0.2s ease; position:relative; box-shadow:0 2px 8px rgba(0,0,0,0.03);">
                    <div style="position:absolute; top:8px; right:8px; z-index:2;">
                        {{ item.visibility }}
                        {{ item.extra }}
                    </div>
                    <div class="big_icon" style="margin-bottom:10px; width:48px; height:48px; display:flex; align-items:center; justify-content:center;">
                        {% if item.tool.image %}
                            {{ item.tool.image }}
                        {% elseif item.only_icon_small %}
                            {{ item.only_icon_small }}
                        {% endif %}
                    </div>
                    <div class="content" style="width:100%;">
                        <div style="font-weight:700; font-size:14px; color:#0F172A; line-height:1.3;">
                            {{ item.link }}
                        </div>
                    </div>
                </div>
            </div>
        {% endfor %}
    </div>
{% endfor %}
