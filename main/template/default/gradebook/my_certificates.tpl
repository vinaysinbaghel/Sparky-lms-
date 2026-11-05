{% if course_list is not empty %}
    <h2 class="page-header">{{ "Courses"|get_lang }}</h2>
    <div class="table-responsive">
        <table class="table table-hover table-striped align-middle">
            <thead>
                <tr>
                    <th>{{ "Course"|get_lang }}</th>
                    <th class="text-center">{{ "Date"|get_lang }}</th>
                    <th width="15%" class="text-right">&nbsp;</th>
                    <th width="12%" class="text-right">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                {% for row in course_list %}
                    <tr>
                        <td class="fw-bold">{{ row.course }}</td>
                        <td class="text-center text-muted">{{ row.date }}</td>

                        {% if allow_export %}
                        <td class="text-right">
                            <a href="{{ row.pdf }}" target="_blank" class="btn btn-danger btn-sm rounded-pill px-3 fw-bold" style="background-color: #e3122b; border-color: #e3122b;">
                                <i class="fa fa-file-pdf-o" aria-hidden="true"></i>
                                {{ 'DownloadCertificatePdf'|get_lang }}
                            </a>
                        </td>
                        {% endif %}

                        <td class="text-right">
                            <a href="{{ row.link }}" target="_blank" class="btn btn-default btn-sm rounded-pill px-3 border">
                                <em class="fa fa-external-link"></em> {{ 'Certificate'|get_lang }}
                            </a>
                        </td>
                    </tr>
                {% endfor %}
            </tbody>
        </table>
    </div>
{% endif %}

{% if session_list is not empty %}
    <h2 class="page-header">{{ "Sessions"|get_lang }}</h2>
    <div class="table-responsive">
        <table class="table table-hover table-striped align-middle">
            <thead>
                <tr>
                    <th>{{ "Session"|get_lang }}</th>
                    <th>{{ "Course"|get_lang }}</th>
                    <th class="text-center">{{ "Date"|get_lang }}</th>
                    <th width="15%" class="text-right">&nbsp;</th>
                    <th width="12%" class="text-right">&nbsp;</th>
                </tr>
            </thead>
            <tbody>
                {% for row in session_list %}
                    <tr>
                        <td>{{ row.session }}</td>
                        <td>{{ row.course }}</td>
                        <td class="text-center text-muted">{{ row.date }}</td>
                        {% if allow_export %}
                        <td class="text-right">
                            <a href="{{ row.pdf }}" target="_blank" class="btn btn-danger btn-sm rounded-pill px-3 fw-bold" style="background-color: #e3122b; border-color: #e3122b;">
                                <i class="fa fa-file-pdf-o" aria-hidden="true"></i>
                                 {{ 'DownloadCertificatePdf'|get_lang }}
                            </a>
                        </td>
                        {% endif %}
                        <td class="text-right">
                            <a href="{{ row.link }}" target="_blank" class="btn btn-default btn-sm rounded-pill px-3 border">
                                <em class="fa fa-external-link"></em> {{ 'Certificate'|get_lang }}
                            </a>
                        </td>
                    </tr>
                {% endfor %}
            </tbody>
        </table>
    </div>
{% endif %}
