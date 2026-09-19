<?php
if (!isset($_SESSION['auth']['codemeli'])) {
    die('ابتدا وارد شوید');
}
$codemeli = $_SESSION['auth']['codemeli'];

if (isset($_GET['get_events']) && $_GET['get_events'] == 1) {
    header('Content-Type: application/json');

    $enrolledCourseCodes = getcourses($codemeli, 2); // returns array of codecourse (coc)
    $enrolledCourseIds = [];
    if (!empty($enrolledCourseCodes)) {
        $in = str_repeat('?,', count($enrolledCourseCodes) - 1) . '?';
        $stmt = $conn->prepare("SELECT idcourse FROM codtbl_course WHERE codecourse IN ($in)");
        $stmt->execute($enrolledCourseCodes);
        $enrolledCourseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    $sql = "SELECT e.* FROM calendar_events e WHERE e.target_type = 'user' ";
    $params = [];

    if (empty($enrolledCourseIds)) {
        // Student not enrolled in any active course → only show events with no course targets
        $sql .= " AND NOT EXISTS (SELECT 1 FROM calendar_course_targets ct WHERE ct.event_id = e.id)";
    } else {
        $placeholders = implode(',', array_fill(0, count($enrolledCourseIds), '?'));
        $sql .= " AND (
            NOT EXISTS (SELECT 1 FROM calendar_course_targets ct WHERE ct.event_id = e.id)
            OR EXISTS (
                SELECT 1 FROM calendar_course_targets ct 
                WHERE ct.event_id = e.id AND ct.course_id IN ($placeholders)
            )
        )";
        $params = $enrolledCourseIds;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $start_j = gregorian_to_jalali(substr($row['start_date'],0,4), substr($row['start_date'],5,2), substr($row['start_date'],8,2));
        $jalali_start = sprintf("%04d/%02d/%02d %s", $start_j[0], $start_j[1], $start_j[2], date('H:i', strtotime($row['start_date'])));
        $jalali_end = '';
        if ($row['end_date']) {
            $end_j = gregorian_to_jalali(substr($row['end_date'],0,4), substr($row['end_date'],5,2), substr($row['end_date'],8,2));
            $jalali_end = sprintf("%04d/%02d/%02d %s", $end_j[0], $end_j[1], $end_j[2], date('H:i', strtotime($row['end_date'])));
        }
        $events[] = [
            'id'          => $row['id'],
            'title'       => $row['title'],
            'start'       => date('Y-m-d\TH:i:s', strtotime($row['start_date'])),
            'end'         => $row['end_date'] ? date('Y-m-d\TH:i:s', strtotime($row['end_date'])) : null,
            'color'       => $row['color'] ?? '#3788d8',
            'description' => $row['description'],
            'jalali_start'=> $jalali_start,
            'jalali_end'  => $jalali_end,
        ];
    }
    echo json_encode($events);
    exit;
}

function renderUserCalendar() {
    // Your existing HTML and JavaScript (keep as is, only the event fetching logic above changed)
?>
<style>
    #calendar {
    }
</style>
<div id="calendar"></div>

<!-- Modal نمایش جزئیات رویداد (فقط خواندنی) -->
<div class="modal fade" id="eventDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">جزئیات رویداد</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="eventDetailContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const calendarEl = document.getElementById('calendar');
        const detailModal = new bootstrap.Modal(document.getElementById('eventDetailModal'));

        const calendar = new FullCalendar.Calendar(calendarEl, {
            height: 500,
            locale: 'fa',
            firstDay: 6,
            direction: 'rtl',
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'امروز',
                month: 'ماه',
                week: 'هفته',
                day: 'روز',
                list: 'برنامه'
            },
            editable: false,
            selectable: false,
            events: function(fetchInfo, successCallback, failureCallback) {
                fetch(window.location.href + '?get_events=1')
                    .then(response => response.json())
                    .then(events => {
                        events.forEach(ev => {
                            ev.extendedProps = ev.extendedProps || {};
                            ev.extendedProps.description = ev.description;
                            ev.extendedProps.jalali_start = ev.jalali_start;
                            ev.extendedProps.jalali_end = ev.jalali_end;
                        });
                        successCallback(events);
                    })
                    .catch(error => failureCallback(error));
            },
            dayHeaderContent: function(arg) {
                const fullNames = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
                const shortNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
                let dayIndex = arg.date.getDay();
                let persianIndex = (dayIndex + 1) % 7;
                const isSmall = window.innerWidth < 768;
                return { html: isSmall ? shortNames[persianIndex] : fullNames[persianIndex] };
            },
            eventClick: function(info) {
                const ev = info.event;
                const desc = ev.extendedProps.description || '—';
                document.getElementById('eventDetailContent').innerHTML = `
                    <div style="margin-bottom:15px;"><strong>عنوان:</strong> ${ev.title}</div>
                    <div style="margin-bottom:15px;"><strong>توضیحات:</strong> ${desc}</div>
                    <div style="margin-bottom:15px;"><strong>تاریخ شروع:</strong> ${ev.extendedProps.jalali_start}</div>
                    ${ev.extendedProps.jalali_end ? `<div style="margin-bottom:15px;"><strong>تاریخ پایان:</strong> ${ev.extendedProps.jalali_end}</div>` : ''}
                `;
                detailModal.show();
            }
        });
        calendar.render();

        window.addEventListener('resize', function() {
            calendar.render();
        });

        if (window.location.search && !window.location.search.includes('get_events=1')) {
            const cleanUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }
    });
</script>
<?php
}