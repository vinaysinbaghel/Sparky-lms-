{# Modern Responsive Unified FullCalendar View #}
<style>
/* Modern Agenda & FullCalendar Styling */
.et-calendar-page-wrapper {
    max-width: 1260px;
    margin: 15px auto 40px auto;
    padding: 0 15px;
    box-sizing: border-box;
}

.et-calendar-hero {
    background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
    color: #FFFFFF;
    border-radius: 14px;
    padding: 20px 26px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.08);
}

.et-calendar-title {
    font-size: 20px;
    font-weight: 800;
    margin: 0 0 4px 0;
    color: #FFFFFF;
    letter-spacing: -0.02em;
    display: flex;
    align-items: center;
    gap: 10px;
}

.et-calendar-subtitle {
    color: #94A3B8;
    font-size: 13px;
    margin: 0;
}

.et-calendar-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.et-btn-add-event {
    background: #E3122B !important;
    border: 1px solid #E3122B !important;
    color: #FFFFFF !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    border-radius: 8px !important;
    padding: 8px 18px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    box-shadow: 0 4px 12px rgba(227, 18, 43, 0.3) !important;
    transition: all 0.15s ease !important;
    cursor: pointer !important;
}

.et-btn-add-event:hover {
    background: #C20F23 !important;
    border-color: #C20F23 !important;
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(227, 18, 43, 0.4) !important;
}

.et-btn-list-view {
    background: rgba(255, 255, 255, 0.1) !important;
    color: #FFFFFF !important;
    font-weight: 600 !important;
    font-size: 13px !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 8px !important;
    padding: 8px 16px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    transition: all 0.15s ease !important;
    text-decoration: none !important;
}

.et-btn-list-view:hover {
    background: rgba(255, 255, 255, 0.18) !important;
    color: #FFFFFF !important;
}

/* Calendar Card Container */
.et-calendar-card {
    background: #FFFFFF;
    border-radius: 16px;
    box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.05), 0 0 0 1px rgba(0, 0, 0, 0.04);
    padding: 22px 26px;
    border: 1px solid #E2E8F0;
}

/* Legend Pill Bar */
.et-calendar-legend-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 10px 16px;
    background: #F8FAFC;
    border: 1px solid #EDF2F7;
    border-radius: 10px;
    margin-bottom: 20px;
    font-size: 12.5px;
}

.et-legend-items {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.et-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #475569;
    font-weight: 600;
}

.et-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

/* Controlled Grid Cell Height */
.fc-basic-view .fc-body .fc-row {
    min-height: 85px !important;
}

.fc-day-grid-container {
    height: auto !important;
}

/* Clean Table Grid Borders */
.fc-theme-standard td, .fc-theme-standard th, 
.fc table, .fc td, .fc th {
    border-color: #E2E8F0 !important;
}

/* Weekday Table Header */
.fc-head-container thead th,
.fc th.fc-day-header {
    background: #F8FAFC !important;
    color: #475569 !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    padding: 10px 0 !important;
    border-bottom: 1px solid #CBD5E1 !important;
    text-align: center !important;
}

/* Day Numbers */
.fc-day-number {
    font-size: 12.5px !important;
    font-weight: 600 !important;
    color: #334155 !important;
    padding: 6px 8px !important;
    float: right !important;
    display: inline-block;
}

/* Hover on day cell */
.fc-day:hover {
    background: #F8FAFC !important;
}

/* Other month days */
.fc-other-month {
    background: #FAFAFC !important;
}

.fc-other-month .fc-day-number {
    color: #CBD5E1 !important;
}

/* Today Highlight (modern, no yellow) */
.fc-today {
    background: #F0F9FF !important;
}

.fc-today .fc-day-number {
    background: #E3122B;
    color: #FFFFFF !important;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    font-size: 11.5px !important;
    font-weight: 700 !important;
    margin: 3px 6px !important;
    box-shadow: 0 2px 6px rgba(227, 18, 43, 0.35);
}

/* Event Badges Styling */
.fc-event {
    border-radius: 6px !important;
    padding: 2px 6px !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    border: none !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08) !important;
    cursor: pointer !important;
    margin: 2px 3px !important;
    line-height: 1.35 !important;
    transition: all 0.15s ease !important;
}

.fc-event:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15) !important;
}

.fc-event .fc-time {
    font-weight: 700 !important;
    opacity: 0.95;
    margin-right: 4px;
}

/* Toolbar Styling */
.fc-toolbar {
    margin-bottom: 16px !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}

.fc-toolbar .fc-center h2 {
    font-size: 20px !important;
    font-weight: 800 !important;
    color: #0F172A !important;
    letter-spacing: -0.02em;
    margin: 0 !important;
}

.fc-button-group {
    border-radius: 8px !important;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
}

.fc-button {
    background: #FFFFFF !important;
    color: #334155 !important;
    border: 1px solid #CBD5E1 !important;
    font-weight: 600 !important;
    font-size: 12.5px !important;
    padding: 6px 14px !important;
    box-shadow: none !important;
    text-shadow: none !important;
    outline: none !important;
    transition: all 0.15s ease !important;
}

.fc-button:hover, .fc-button.fc-state-hover {
    background: #F1F5F9 !important;
    border-color: #94A3B8 !important;
    color: #0F172A !important;
}

.fc-state-active, .fc-button.fc-state-active {
    background: #0F172A !important;
    color: #FFFFFF !important;
    border-color: #0F172A !important;
}

/* Modal and Form Styling */
.ui-dialog {
    border-radius: 12px !important;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2) !important;
    border: 1px solid #CBD5E1 !important;
    background: #FFFFFF !important;
    overflow: hidden !important;
    padding: 0 !important;
}

.ui-dialog .ui-dialog-titlebar {
    background: #0F172A !important;
    color: #FFFFFF !important;
    border: none !important;
    border-radius: 0 !important;
    padding: 14px 20px !important;
    font-weight: 700 !important;
    font-size: 16px !important;
}

.ui-dialog .ui-dialog-titlebar-close {
    background: transparent !important;
    border: none !important;
    color: #FFFFFF !important;
    right: 15px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
}

.ui-dialog .ui-dialog-content {
    padding: 20px 24px !important;
}

.ui-dialog .ui-dialog-buttonpane {
    background: #F8FAFC !important;
    border-top: 1px solid #E2E8F0 !important;
    margin: 0 !important;
    padding: 12px 20px !important;
}
</style>

<div class="et-calendar-page-wrapper">
    <!-- Hero Banner Header -->
    <div class="et-calendar-hero">
        <div>
            <h1 class="et-calendar-title">
                <i class="fa fa-calendar-check-o" style="color: #818CF8;"></i> Personal Agenda & Schedule
            </h1>
            <p class="et-calendar-subtitle">
                Track personal reminders, live class sessions, and platform announcements in one unified workspace.
            </p>
        </div>
        <div class="et-calendar-actions">
            <a href="agenda_list.php?type={{ type }}{% if _cid %}&{{ cid_req }}{% endif %}" class="et-btn-list-view">
                <i class="fa fa-list"></i> Agenda List View
            </a>
            {% if can_add_events %}
                <button type="button" class="btn et-btn-add-event" onclick="window.openAddNewEventModal();">
                    <i class="fa fa-plus-circle"></i> Add Event
                </button>
            {% endif %}
        </div>
    </div>

    <!-- Calendar Card Container -->
    <div class="et-calendar-card">
        <!-- Legend Pill Bar -->
        <div class="et-calendar-legend-bar">
            <div class="et-legend-items">
                <span class="et-legend-item">
                    <span class="et-legend-dot" style="background: #4F46E5;"></span> Personal Event
                </span>
                <span class="et-legend-item">
                    <span class="et-legend-dot" style="background: #10B981;"></span> Course Session
                </span>
                <span class="et-legend-item">
                    <span class="et-legend-dot" style="background: #E3122B;"></span> Platform Announcement
                </span>
            </div>
            <div style="color: #64748B; font-size: 11.5px;">
                <i class="fa fa-mouse-pointer"></i> Click any day to create an event, or click an existing event to view details.
            </div>
        </div>

        <div id="calendar"></div>
    </div>
</div>

<script>
$(document).ready(function() {
    var region_value = '{{ region_value }}';
    var defaultStartDate = Cookies.get('agenda_cookies') ? JSON.parse(Cookies.get('agenda_cookies')).start : null;
    var defaultView = Cookies.get('agenda_cookies') ? JSON.parse(Cookies.get('agenda_cookies')).view : '{{ default_view }}';

    window.onEventScopeChange = function(scopeVal) {
        if (!scopeVal) return;
        if (scopeVal.indexOf('course_') === 0) {
            $('#color_calendar').html('📚 Course Session').css('background-color', '#10B981').css('color', '#fff').css('padding', '3px 8px').css('border-radius', '4px');
        } else if (scopeVal === 'platform') {
            $('#color_calendar').html('📢 Platform Announcement').css('background-color', '#E3122B').css('color', '#fff').css('padding', '3px 8px').css('border-radius', '4px');
        } else {
            $('#color_calendar').html('👤 Personal Event').css('background-color', '#4F46E5').css('color', '#fff').css('padding', '3px 8px').css('border-radius', '4px');
        }
    };

    window.openAddNewEventModal = function(start, end) {
        if (!start) {
            start = moment();
        }
        if (!end) {
            end = start.clone().add(1, 'hour');
        }

        // Format dates into local ISO strings for datetime-local
        var startLocalStr = start.format('YYYY-MM-DDTHH:mm');
        var endLocalStr = end.format('YYYY-MM-DDTHH:mm');

        $('#event_start_input').val(startLocalStr);
        $('#event_end_input').val(endLocalStr);

        $('#start_date').html(start.format('ll LT'));
        $('#end_date').html(' - ' + end.format('LT'));

        // Reset text fields
        $('#title').val('').show();
        $('#content').val('').show();
        if ($('#comment').length) {
            $('#comment').val('').show();
        }

        // Remove any view-only injected elements
        $('#title_edit, #content_edit, #comment_edit').remove();

        $('#cke_content').show();
        if (window.CKEDITOR && CKEDITOR.instances['content']) {
            CKEDITOR.instances['content'].setData('');
        }

        if ($('#event_scope').length) {
            $('#event_scope').val('personal');
            window.onEventScopeChange('personal');
        }

        $('#dialog-form').dialog('open');
    };

    window.saveAddEventForm = function() {
        var titleVal = $.trim($('#title').val());
        if (!titleVal) {
            $('#title').addClass('ui-state-error').focus();
            alert('Please enter a title for the event.');
            return false;
        }
        $('#title').removeClass('ui-state-error');

        if (window.CKEDITOR) {
            for (var instance in CKEDITOR.instances) {
                if (CKEDITOR.instances[instance]) {
                    CKEDITOR.instances[instance].updateElement();
                }
            }
        }

        var $btn = $('#add_event_submit_btn');
        var originalBtnHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        var postData = $('#add_event_form').serialize();
        var submitUrl = '{{ _p.web_ajax }}agenda.ajax.php?a=add_event&type={{ type }}';

        $.ajax({
            url: submitUrl,
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(resp) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                $('#dialog-form').dialog('close');
                $('#calendar').fullCalendar('refetchEvents');
                $('#calendar').fullCalendar('rerenderEvents');
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html(originalBtnHtml);
                // Fallback attempt with refetch
                $('#calendar').fullCalendar('refetchEvents');
                $('#dialog-form').dialog('close');
            }
        });

        return false;
    };

    // Initialize Dialog Form for adding events
    $('#dialog-form').dialog({
        autoOpen: false,
        modal: true,
        position: { my: "center", at: "center", of: window },
        width: 680,
        height: 640,
        zIndex: 20000
    });

    // Initialize Details Dialog for viewing/deleting events
    $('#simple-dialog-form').dialog({
        autoOpen: false,
        modal: true,
        position: { my: "center", at: "center", of: window },
        width: 620,
        height: 520,
        zIndex: 20000
    });

    $(document).on('submit', '#add_event_form', function(e) {
        e.preventDefault();
        window.saveAddEventForm();
        return false;
    });

    // Initialize FullCalendar with controlled height and elegant proportions
    var calendar = $('#calendar').fullCalendar({
        header: {
            left: 'today prev,next',
            center: 'title',
            right: 'month,agendaWeek,agendaDay'
        },
        buttonText: {
            today: 'Today',
            month: 'Month',
            week: 'Week',
            day: 'Day'
        },
        aspectRatio: 1.85,
        contentHeight: 600,
        fixedWeekCount: false,
        locale: region_value,
        defaultView: defaultView,
        defaultDate: defaultStartDate,
        firstHour: 8,
        firstDay: 1,
        selectable: true,
        selectHelper: true,
        viewRender: function(view, element) {
            var data = {
                'view': view.name,
                'start': view.intervalStart.format('YYYY-MM-DD')
            };
            Cookies.set('agenda_cookies', data, { expires: 1 });
        },
        select: function(start, end, jsEvent, view) {
            window.openAddNewEventModal(start, end);
        },
        eventClick: function(calEvent, jsEvent, view) {
            // Show Event Details in #simple-dialog-form
            var scopeBadge = '<span class="label" style="background:#4F46E5; color:#fff; padding:4px 8px; border-radius:4px; font-weight:700;">Personal Event</span>';
            if (calEvent.type === 'course') {
                var cName = calEvent.course_name || 'Course Session';
                scopeBadge = '<span class="label" style="background:#10B981; color:#fff; padding:4px 8px; border-radius:4px; font-weight:700;"><i class="fa fa-graduation-cap"></i> ' + cName + '</span>';
            } else if (calEvent.type === 'admin') {
                scopeBadge = '<span class="label" style="background:#E3122B; color:#fff; padding:4px 8px; border-radius:4px; font-weight:700;"><i class="fa fa-bullhorn"></i> Platform Announcement</span>';
            }

            var startStr = calEvent.start ? calEvent.start.format('LLLL') : '';
            var endStr = calEvent.end ? (' &ndash; ' + calEvent.end.format('LLLL')) : '';

            var detailsHtml = '<div style="padding: 10px 0;">' +
                '<div style="margin-bottom: 12px;">' + scopeBadge + '</div>' +
                '<h3 style="margin: 0 0 10px 0; color: #0F172A; font-weight: 800; font-size: 20px;">' + calEvent.title + '</h3>' +
                '<div style="font-size: 13.5px; color: #64748B; margin-bottom: 16px; font-weight: 600;">' +
                    '<i class="fa fa-clock-o text-muted"></i> ' + startStr + endStr +
                '</div>' +
                '<div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px 18px; margin-bottom: 16px; font-size: 14px; color: #334155; line-height: 1.6;">' +
                    (calEvent.description || '<em class="text-muted">No additional description provided.</em>') +
                '</div>';

            if (calEvent.comment) {
                detailsHtml += '<div style="font-size: 13px; color: #64748B; margin-bottom: 12px;"><strong>Note:</strong> ' + calEvent.comment + '</div>';
            }

            detailsHtml += '<div style="margin-top: 24px; padding-top: 14px; border-top: 1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">' +
                '<button type="button" class="btn btn-default" onclick="$(\'#simple-dialog-form\').dialog(\'close\');" style="font-weight:600; border-radius:6px;">Close</button>';

            // Permission check: users can only delete their OWN personal events; non-admins cannot delete course or admin/platform events
            var canDeleteEvent = false;
            var currentUserId = {{ _u.id|default(_u.user_id)|default(0) }};
            var isPlatformAdmin = {{ _u.is_admin ? 'true' : 'false' }};

            if (isPlatformAdmin) {
                canDeleteEvent = true;
            } else if (calEvent.type === 'personal') {
                if (calEvent.user_id && calEvent.user_id == currentUserId) {
                    canDeleteEvent = true;
                } else if (calEvent.editable && (!calEvent.user_id || calEvent.user_id == currentUserId)) {
                    canDeleteEvent = true;
                }
            }

            if (canDeleteEvent) {
                detailsHtml += '<button type="button" class="btn btn-danger" id="delete_modal_event_btn" style="background:#E3122B; border-color:#E3122B; font-weight:700; border-radius:6px;"><i class="fa fa-trash"></i> Delete Event</button>';
            }

            detailsHtml += '</div></div>';

            $('#simple-dialog-form').html(detailsHtml);
            $('#simple-dialog-form').dialog('open');

            $('#delete_modal_event_btn').on('click', function() {
                if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                    var delBtn = $(this);
                    delBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');
                    $.ajax({
                        url: '{{ _p.web_ajax }}agenda.ajax.php?a=delete_event&id=' + calEvent.id,
                        type: 'POST',
                        dataType: 'json',
                        success: function() {
                            $('#simple-dialog-form').dialog('close');
                            $('#calendar').fullCalendar('refetchEvents');
                            $('#calendar').fullCalendar('rerenderEvents');
                        },
                        error: function() {
                            $('#simple-dialog-form').dialog('close');
                            $('#calendar').fullCalendar('refetchEvents');
                        }
                    });
                }
            });
        },
        events: {
            url: '{{ _p.web_ajax }}agenda.ajax.php?a=get_events&type={{ type }}{% if _cid %}&{{ cid_req }}{% endif %}',
            type: 'GET',
            error: function() {
                console.error('Error fetching calendar events');
            }
        }
    });

    window.calendar = calendar;

    // Trigger open modal if action=add_modal
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'add_modal') {
        window.openAddNewEventModal();
    }
});
</script>

<!-- Add Event Modal Container -->
<div id="dialog-form" style="display:none;" title="Add Calendar Event">
    <div class="dialog-form-content">
        {{ form_add }}
    </div>
</div>

<!-- Event Details Modal Container -->
<div id="simple-dialog-form" style="display:none;" title="Event Details"></div>