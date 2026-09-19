<?php

if (isset($_GET['get_events']) && $_GET['get_events'] == 1) {
    header('Content-Type: application/json');
    $sql = "SELECT * FROM calendar_events WHERE target_type = 'user'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
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

/**
 * Renders the user calendar (read‑only, only events with target_type='user')
 * Call this function in any page where you want the calendar to appear.
 */
function renderUserCalendar() {
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
                firstDay: 6,           // week starts on Saturday (شنبه)
                direction: 'rtl',      // right‑to‑left for Persian
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
                // Dynamic weekday headers: full names on desktop, single letters on mobile
                dayHeaderContent: function(arg) {
                    const fullNames = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
                    const shortNames = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
                    let dayIndex = arg.date.getDay(); // 0=Sun..6=Sat
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

            // Re‑render headers when window resizes (to switch between full/short names)
            window.addEventListener('resize', function() {
                calendar.render();
            });

            // Clean URL from any extra parameters
            if (window.location.search && !window.location.search.includes('get_events=1')) {
                const cleanUrl = window.location.origin + window.location.pathname;
                window.history.replaceState({}, document.title, cleanUrl);
            }
        });
    </script>
    <?php
}?>