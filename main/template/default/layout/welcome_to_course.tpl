<div class="sparky-empty-state-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 48px 32px; text-align: center; max-width: 680px; margin: 32px auto; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);">
    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(99, 102, 241, 0.15) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#4F46E5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
        </svg>
    </div>
    
    <h3 style="font-size: 22px; font-weight: 700; color: #1e293b; margin-bottom: 8px;">
        {{ "HelloXAsYouCanSeeYourCourseListIsEmpty"|get_lang | format(_u.complete_name) }}
    </h3>
    
    <p style="font-size: 15px; color: #64748b; line-height: 1.6; margin-bottom: 28px; max-width: 520px; margin-left: auto; margin-right: auto;">
        {% if count_courses == 0 %}
            {{ "PleaseAllowUsALittleTimeToSubscribeYouToOneOfOurCourses"|get_lang }}
        {% else %}
            You are not currently enrolled in any active courses. Start your learning journey by browsing available course programs or certifications below.
        {% endif %}
    </p>

    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
        {% if count_courses > 0 %}
            <a class="btn btn-primary" href="{{ course_catalog_url }}" style="background: linear-gradient(135deg, #4F46E5 0%, #4338CA 100%); border: none; padding: 10px 24px; font-size: 14px; font-weight: 600; border-radius: 8px; color: #ffffff; text-decoration: none; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);">
                <i class="fa fa-compass" style="margin-right: 6px;"></i> {{ "CourseCatalog"|get_lang }}
            </a>
        {% endif %}
        <a class="btn btn-default" href="{{ course_list_url }}?history=1" style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 10px 20px; font-size: 14px; font-weight: 600; border-radius: 8px; color: #334155; text-decoration: none;">
            <i class="fa fa-history" style="margin-right: 6px;"></i> Completed Courses
        </a>
    </div>
</div>