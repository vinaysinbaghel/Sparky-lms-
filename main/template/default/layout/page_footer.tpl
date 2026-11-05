<div class="container">
    {% if plugin_pre_footer is not null %}
        <div id="plugin_pre_footer">
            {{ plugin_pre_footer }}
        </div>
    {% endif %}
    <section class="sub-footer">
        <div class="row" style="margin-top: 15px;">
            <div class="col-xs-12 col-md-5">
                <div class="et-footer-brand">
                    <img src="{{ _p.web }}web/css/themes/chamilo/images/et/et-mark-placeholder.svg" alt="Sparky LMS" style="width: 28px; height: 28px; border-radius: 6px;" />
                    <span>⚡ <strong>Sparky</strong> <span style="display:inline-block; margin-left:6px; padding:2px 8px; border-radius:10px; background:rgba(79,70,229,0.25); color:#c7d2fe; font-size:10.5px; font-weight:700; letter-spacing:0.5px;">LMS</span></span>
                </div>
                <p style="color: #94A3B8; font-size: 12.5px; line-height: 1.5; margin-bottom: 12px;">
                    Modern learning platform empowering students, educators, and creators worldwide.
                </p>
            </div>
            <div class="col-xs-12 col-md-4">
                <div style="font-weight: 700; color: #FFFFFF; font-size: 13px; margin-bottom: 8px;">QUICK LINKS</div>
                <ul style="list-style: none; padding: 0; margin: 0; font-size: 12.5px; line-height: 2;">
                    <li><a href="{{ _p.web }}user_portal.php"><em class="fa fa-th-large"></em> My Dashboard</a></li>
                    <li><a href="{{ _p.web_main }}auth/courses.php"><em class="fa fa-book"></em> Course Catalog</a></li>
                    <li><a href="{{ _p.web_main }}calendar/agenda_js.php"><em class="fa fa-calendar"></em> Live Schedule</a></li>
                </ul>
            </div>
            <div class="col-xs-12 col-md-3 text-right">
                <div style="font-size: 12px; color: #94A3B8; margin-top: 10px;">
                    &copy; {{ "now"|date("Y") }} Sparky LMS.<br/>
                    Education &amp; Learning Platform.
                </div>
            </div>
        </div>
        {% if footer_extra_content  %}
            {{ footer_extra_content }}
        {% endif %}
    </section>
</div>

{{ execution_stats }}