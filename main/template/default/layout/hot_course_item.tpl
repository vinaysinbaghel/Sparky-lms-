{% for item in hot_courses %}
    {% if item.title %}
        <div class=col-xs-12 style=padding: 0;>
            <div class=et-course-small-icon-card style=display: flex; align-items: center; gap: 16px; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 16px 20px; margin-bottom: 14px; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.03);>
                {# Small Course Icon (56x56px) with Actual Course Image #}
                <div class=et-course-small-icon-box style=width: 56px; height: 56px; min-width: 56px; border-radius: 12px; overflow: hidden; background: #0F172A; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 1px solid #CBD5E1;>
                    {% if item.course_image_large %}
                        <img src={{ item.course_image_large }} alt={{ item.title }} style=width: 100%; height: 100%; object-fit: cover; onerror=this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex'; />
                        <div style=display: none; width: 100%; height: 100%; align-items: center; justify-content: center; background: #0F172A;>
                            <i class=fa fa-graduation-cap style=color: #FFFFFF; font-size: 22px;></i>
                        </div>
                    {% else %}
                        <div style=width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #0F172A;>
                            <i class=fa fa-graduation-cap style=color: #FFFFFF; font-size: 22px;></i>
                        </div>
                    {% endif %}
                </div>

                {# Course Details from LMS #}
                <div style=flex: 1; min-width: 0;>
                    <div style=display: flex; align-items: center; gap: 8px; margin-bottom: 4px;>
                        {% if item.categoryName %}
                            <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #4F46E5; background: #EEF2FF; padding: 2px 8px; border-radius: 4px;">
                                {{ item.categoryName }}
                            </span>
                        {% endif %}
                        <span style=font-size: 11px; color: #64748B; font-weight: 600;>
                            Code: {{ item.visual_code|default(item.code) }}
                        </span>
                    </div>

                    <h4 style=margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 16px; font-weight: 700; color: #0F172A; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;>
                        {{ item.title }}
                    </h4>

                    {# LMS Rating and visits stats #}
                    <div class=et-course-stats-line style=display: flex; align-items: center; gap: 10px; margin-top: 4px; font-size: 12px; color: #64748B;>
                        {{ item.rating_html }}
                    </div>
                </div>
            </div>
        </div>
    {% endif %}
{% endfor %}
