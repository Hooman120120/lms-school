<?php
if (!isset($_SESSION['auth']['codemeli'])) {
    die('ابتدا وارد شوید');
}
$codemeli = $_SESSION['auth']['codemeli'];

if (isset($_GET['get_events']) && $_GET['get_events'] == 1) {
    header('Content-Type: application/json');

    $enrolledCourseCodes = getcourses($codemeli, 2);
    if (!is_array($enrolledCourseCodes)) {
        $enrolledCourseCodes = [];
    }

    $enrolledCourseIds = [];
    if (!empty($enrolledCourseCodes)) {
        $in = str_repeat('?,', count($enrolledCourseCodes) - 1) . '?';
        $stmt = $conn->prepare("SELECT idcourse FROM codtbl_course WHERE codecourse IN ($in)");
        $stmt->execute($enrolledCourseCodes);
        $enrolledCourseIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $sql = "SELECT e.*, COALESCE(g.color, '#B5B5B5') AS event_color
            FROM calendar_events e
            LEFT JOIN calendar_groups g ON e.calendar_groups_id = g.id
            WHERE e.target_type IN ('user', 'both')
              AND (e.visibility = 0 OR e.visibility IS NULL) ";

    $params = [];

    if (empty($enrolledCourseIds)) {
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

    // --- 4. Execute and build response ---
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $start_j = gregorian_to_jalali(
            substr($row['start_date'], 0, 4),
            substr($row['start_date'], 5, 2),
            substr($row['start_date'], 8, 2)
        );
        $jalali_start = sprintf(
            "%04d/%02d/%02d %s",
            $start_j[0], $start_j[1], $start_j[2],
            date('H:i', strtotime($row['start_date']))
        );

        $jalali_end = '';
        if ($row['end_date']) {
            $end_j = gregorian_to_jalali(
                substr($row['end_date'], 0, 4),
                substr($row['end_date'], 5, 2),
                substr($row['end_date'], 8, 2)
            );
            $jalali_end = sprintf(
                "%04d/%02d/%02d %s",
                $end_j[0], $end_j[1], $end_j[2],
                date('H:i', strtotime($row['end_date']))
            );
        }

        // رویداد با ساعت شروع ۰۰:۰۰ یعنی تمام روز؛ بقیه رویدادها ساعت دار هستند.
        // همان قاعده تقویم مدیریت، تا هر دو تقویم یکسان نمایش دهند.
        $start_ts = strtotime($row['start_date']);
        $end_ts   = $row['end_date'] ? strtotime($row['end_date']) : null;
        $is_all_day = (date('H:i:s', $start_ts) === '00:00:00');

        if ($is_all_day) {
            $start_out = date('Y-m-d', $start_ts);
            // در حالت تمام روز تاریخ پایان انحصاری است، پس یک روز اضافه می شود
            if ($end_ts === null || date('Y-m-d', $end_ts) === date('Y-m-d', $start_ts)) {
                $end_out = null;
            } else {
                $end_out = date('Y-m-d', strtotime('+1 day', $end_ts));
            }
        } else {
            $start_out = date('Y-m-d\TH:i:s', $start_ts);
            $end_out   = $end_ts ? date('Y-m-d\TH:i:s', $end_ts) : null;
        }

        $events[] = [
            'id'          => $row['id'],
            'title'       => $row['title'],
            'start'       => $start_out,
            'end'         => $end_out,
            'allDay'      => $is_all_day,
            'color'       => $row['event_color'],
            'description' => $row['description'],
            'jalali_start'=> $jalali_start,
            'jalali_end'  => $jalali_end,
        ];
    }

    echo json_encode($events);
    exit;
}
function renderUserCalendar() {
?>
<style>
    /* ---------- نمای جابجاشده روز/هفته ----------
       روزها در سطر و ساعت ها در ستون؛ محور زمان راست به چپ است. */
    #transposedCalendar {
        background: transparent;
        padding: 0;
        overflow-x: auto;
    }

    #transposedCalendar table {
        width: 100%;
        min-width: 780px;
        border-collapse: collapse;
        table-layout: fixed;
    }

    #transposedCalendar th,
    #transposedCalendar td {
        background: transparent;
        border: 1px solid #e3e3ef;
        padding: 0;
        vertical-align: top;
    }

    #transposedCalendar .tc-pill {
        display: inline-block;
        background: #f2f2f8;
        border: 2px solid #e3e3ef;
        border-radius: 10px;
        padding: 2px 4px;
        margin: 4px 2px;
        font-size: 13px;
        font-weight: 700;
        color: #3b3b52;
    }

    #transposedCalendar thead th {
        text-align: center;
    }

    #transposedCalendar th.tc-day-head,
    #transposedCalendar td.tc-day-head {
        width: 118px;
        text-align: center;
    }

    #transposedCalendar .tc-frame {
        background: #ffffff;
        border: 1px solid #e3e3ef;
        border-radius: 10px;
        height: 100%;
        position: relative;
    }

    #transposedCalendar td.tc-day-head .tc-frame {
        padding: 4px;
        text-align: center;
        /* محتوای برچسب روز وسط چین شود تا زیر عنوان فضای خالی نماند */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    #transposedCalendar .tc-dayname {
        font-size: 14px;
        font-weight: 600;
        color: #3b3b52;
        display: block;
    }

    #transposedCalendar .tc-daydate {
        font-size: 12px;
        font-weight: 400;
        color: #8b8fa7;
        display: block;
    }

    #transposedCalendar tr.tc-today td.tc-day-head .tc-frame {
        background: #f3edff;
        border-color: #7b5cd6;
    }

    #transposedCalendar td.tc-track {
        position: relative;
    }

    #transposedCalendar .tc-hours {
        position: absolute;
        inset: 0;
        display: flex;
    }

    #transposedCalendar .tc-hour {
        flex: 1 1 0;
        background: #ffffff;
        border: 1px solid #e3e3ef;
        border-radius: 10px;
        margin: 0 1px;
    }

    #transposedCalendar .tc-events {
        position: absolute;
        inset: 0;
    }

    #transposedCalendar .tc-event {
        position: absolute;
        height: 26px;
        line-height: 24px;
        border: 1px solid rgba(0, 0, 0, .08);
        border-radius: 10px;
        font-size: 12px;
        font-weight: 600;
        padding: 0 7px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
    }

    #transposedCalendar .tc-event:hover {
        filter: brightness(.94);
    }

    #transposedCalendar .tc-allday {
        display: block;
        margin: 3px 0 0;
        padding: 1px 6px;
        border-radius: 3px;
        font-size: 12px;
        cursor: pointer;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    #transposedCalendar .tc-empty {
        text-align: center;
        color: #8b8fa7;
        font-size: 12px;
        padding: 18px;
    }
</style>
<div id="calendar"></div>
<div id="transposedCalendar" style="display:none;"></div>

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

        const isMobile = window.innerWidth < 768;

        const TC_MONTH_HEIGHT = 500; // همان ارتفاعی که تقویم با آن ساخته می شود

        // جزئیات رویداد؛ هم از تقویم اصلی و هم از نمای جابجاشده صدا زده می شود
        function showStudentEventDetail(ev) {
            const desc = ev.extendedProps.description || '—';
            document.getElementById('eventDetailContent').innerHTML = `
            <div style="margin-bottom:15px;"><strong>عنوان:</strong> ${ev.title}</div>
            <div style="margin-bottom:15px;"><strong>توضیحات:</strong> ${desc}</div>
            <div style="margin-bottom:15px;"><strong>تاریخ شروع:</strong> ${ev.extendedProps.jalali_start}</div>
            ${ev.extendedProps.jalali_end ? `<div style="margin-bottom:15px;"><strong>تاریخ پایان:</strong> ${ev.extendedProps.jalali_end}</div>` : ''}
        `;
            detailModal.show();
        }

        // ------------------------------------------------------------------
        // نمای جابجاشده برای روز و هفته
        // روزها در سطر و ساعت ها در ستون؛ محور زمان راست به چپ است
        // یعنی ساعت های ابتدایی سمت راست قرار می گیرند.
        // ------------------------------------------------------------------
        var TC_DAY_NAMES = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];

        function tcEscape(text) {
            var d = document.createElement('div');
            d.textContent = (text === null || text === undefined) ? '' : String(text);
            return d.innerHTML.replace(/"/g, '&quot;');
        }

        function tcDayLabel(date) {
            return TC_DAY_NAMES[date.getDay()];
        }

        function tcJalaliDate(date) {
            try {
                return new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
                    month: '2-digit', day: '2-digit'
                }).format(date);
            } catch (e) {
                return (date.getMonth() + 1) + '/' + date.getDate();
            }
        }

        function tcSameDay(a, b) {
            return a.getFullYear() === b.getFullYear()
                && a.getMonth() === b.getMonth()
                && a.getDate() === b.getDate();
        }

        function tcMinutes(date) {
            return date.getHours() * 60 + date.getMinutes();
        }

        function tcHourRange(events) {
            var min = 8, max = 18;
            events.forEach(function (ev) {
                if (ev.allDay || !ev.start) return;
                var sh = ev.start.getHours();
                var eh = ev.end ? Math.ceil(tcMinutes(ev.end) / 60) : sh + 1;
                if (sh < min) min = sh;
                if (eh > max) max = eh;
            });
            min = Math.max(0, Math.min(min, 23));
            max = Math.min(24, Math.max(max, min + 1));
            return { min: min, max: max };
        }

        // ---- کمکی های کنتراست رنگ ----
        function tcChannelLuminance(value) {
            var c = value / 255;
            return (c <= 0.03928) ? (c / 12.92) : Math.pow((c + 0.055) / 1.055, 2.4);
        }

        function tcRelativeLuminance(r, g, b) {
            return 0.2126 * tcChannelLuminance(r)
                 + 0.7152 * tcChannelLuminance(g)
                 + 0.0722 * tcChannelLuminance(b);
        }

        function tcContrastRatio(l1, l2) {
            var hi = Math.max(l1, l2), lo = Math.min(l1, l2);
            return (hi + 0.05) / (lo + 0.05);
        }

        var TC_DARK_TEXT_LUM = tcRelativeLuminance(47, 50, 69);

        // رنگ گروه ها هگز است و رنگ محاسبه شده مرورگر rgb()، هر دو پشتیبانی می شود
        function tcParseColor(color) {
            if (!color) return null;
            var c = String(color).trim();
            var hex = c.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
            if (hex) {
                var h = hex[1];
                if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
                return [
                    parseInt(h.substr(0, 2), 16),
                    parseInt(h.substr(2, 2), 16),
                    parseInt(h.substr(4, 2), 16)
                ];
            }
            var nums = c.match(/[\d.]+/g);
            if (nums && nums.length >= 3) {
                if (nums.length >= 4 && Number(nums[3]) === 0) return null;
                return [Number(nums[0]), Number(nums[1]), Number(nums[2])];
            }
            return null;
        }

        function tcTextColorFor(bg) {
            var rgb = tcParseColor(bg);
            if (!rgb) return '#ffffff';
            var lum = tcRelativeLuminance(rgb[0], rgb[1], rgb[2]);
            return tcContrastRatio(lum, TC_DARK_TEXT_LUM) >= tcContrastRatio(lum, 1) ? '#2f3245' : '#ffffff';
        }

        function renderTransposed(viewStart, viewEnd, events) {
            var host = document.getElementById('transposedCalendar');
            if (!host) return;

            var days = [];
            var cur = new Date(viewStart.getFullYear(), viewStart.getMonth(), viewStart.getDate());
            while (cur < viewEnd) {
                days.push(new Date(cur));
                cur.setDate(cur.getDate() + 1);
            }

            if (!days.length) {
                host.innerHTML = '<div class="tc-empty">بازه‌ای برای نمایش وجود ندارد.</div>';
                return;
            }

            var range = tcHourRange(events);
            var hourCount = range.max - range.min;
            var totalMin = hourCount * 60;
            var today = new Date();

            var html = '<table>';
            html += '<thead><tr><th class="tc-day-head"><span class="tc-pill">روز</span></th>';
            for (var h = range.min; h < range.max; h++) {
                html += '<th><span class="tc-pill">' + String(h).padStart(2, '0') + ':00</span></th>';
            }
            html += '</tr></thead><tbody>';

            days.forEach(function (day) {
                var dayStart = new Date(day.getFullYear(), day.getMonth(), day.getDate());
                var dayEnd = new Date(dayStart.getTime() + 86400000);

                var dayEvents = events.filter(function (ev) {
                    if (!ev.start) return false;
                    var e = ev.end || new Date(ev.start.getTime() + 3600000);
                    return ev.start < dayEnd && e > dayStart;
                });

                var allDay = dayEvents.filter(function (ev) { return ev.allDay; });
                var timed = dayEvents.filter(function (ev) { return !ev.allDay; });

                // رویدادهای هم زمان در لِین های جداگانه می نشینند تا روی هم نیفتند
                var lanes = [];
                timed.forEach(function (ev) {
                    var s = Math.max(tcMinutes(ev.start), range.min * 60);
                    var e = ev.end ? tcMinutes(ev.end) : s + 60;
                    if (e <= s) e = s + 30;
                    e = Math.min(e, range.max * 60);
                    ev.__s = s;
                    ev.__e = e;
                    var placed = false;
                    for (var i = 0; i < lanes.length && !placed; i++) {
                        if (lanes[i][lanes[i].length - 1].__e <= s) {
                            lanes[i].push(ev);
                            placed = true;
                        }
                    }
                    if (!placed) lanes.push([ev]);
                });

                var laneCount = Math.max(1, lanes.length);
                var rowHeight = 16 + laneCount * 32;

                html += '<tr class="' + (tcSameDay(day, today) ? 'tc-today' : '') + '">';
                // ارتفاع سلول روز هم مثل ردیف ساعت ها ست می شود،
                // وگرنه height:100% قاب داخلی جایی برای محاسبه ندارد و زیر عنوان فضای خالی می ماند
                html += '<td class="tc-day-head" style="height:' + rowHeight + 'px"><div class="tc-frame">'
                      + '<span class="tc-dayname">' + tcDayLabel(day) + '</span>'
                      + '<span class="tc-daydate">' + tcJalaliDate(day) + '</span>';
                allDay.forEach(function (ev) {
                    var bg = ev.backgroundColor || ev.borderColor || '#B5B5B5';
                    html += '<span class="tc-allday" data-ev="' + tcEscape(ev.id) + '" style="background:'
                          + bg + ';color:' + tcTextColorFor(bg) + '" title="' + tcEscape(ev.title) + '">'
                          + tcEscape(ev.title) + '</span>';
                });
                html += '</div></td>';

                html += '<td class="tc-track" colspan="' + hourCount + '" style="height:' + rowHeight + 'px">';
                html += '<div class="tc-hours">';
                for (var hh = 0; hh < hourCount; hh++) { html += '<div class="tc-hour"></div>'; }
                html += '</div><div class="tc-events">';
                lanes.forEach(function (lane, laneIndex) {
                    lane.forEach(function (ev) {
                        var offset = ((ev.__s - range.min * 60) / totalMin) * 100;
                        var width = ((ev.__e - ev.__s) / totalMin) * 100;
                        var bg = ev.backgroundColor || ev.borderColor || '#B5B5B5';
                        var label = ev.start.toTimeString().slice(0, 5) + '  ' + ev.title;
                        html += '<div class="tc-event" data-ev="' + tcEscape(ev.id) + '"'
                              + ' style="right:' + offset + '%; width:' + width + '%; top:'
                              + (4 + laneIndex * 32) + 'px; background:' + bg
                              + '; color:' + tcTextColorFor(bg) + '"'
                              + ' title="' + tcEscape(label) + '">' + tcEscape(label) + '</div>';
                    });
                });
                html += '</div></td></tr>';
            });

            html += '</tbody></table>';
            host.innerHTML = html;

            host.querySelectorAll('[data-ev]').forEach(function (node) {
                node.addEventListener('click', function () {
                    var ev = calendar.getEventById(node.getAttribute('data-ev'));
                    if (ev) showStudentEventDetail(ev);
                });
            });
        }

        // جابجایی بین تقویم استاندارد (ماه/لیست) و نمای جابجاشده (روز/هفته)
        function applyTransposedMode(info) {
            var host = document.getElementById('transposedCalendar');
            var calEl = document.getElementById('calendar');
            var harness = calEl ? calEl.querySelector('.fc-view-harness') : null;
            // در FullCalendar 5 کلاس fc روی خود عنصر #calendar است، نه فرزند آن
            var fcRoot = calEl
                ? (calEl.classList.contains('fc') ? calEl : calEl.querySelector('.fc'))
                : null;
            if (!host || !harness || !fcRoot) return;

            var isTimeGrid = (info.view.type === 'timeGridWeek' || info.view.type === 'timeGridDay');

            harness.style.display = isTimeGrid ? 'none' : '';
            host.style.display = isTimeGrid ? '' : 'none';

            if (isTimeGrid) {
                var toolbar = fcRoot.querySelector('.fc-header-toolbar');
                if (toolbar && host.parentNode !== fcRoot) {
                    toolbar.insertAdjacentElement('afterend', host);
                }
                // ارتفاع ثابت از طریق آپشن آزاد می شود تا محاسبات داخلی خراب نشود
                if (calendar.getOption('height') !== 'auto') {
                    calendar.setOption('height', 'auto');
                }
                renderTransposed(info.view.activeStart, info.view.activeEnd, calendar.getEvents());
            } else {
                if (calendar.getOption('height') !== TC_MONTH_HEIGHT) {
                    calendar.setOption('height', TC_MONTH_HEIGHT);
                }
            }
        }

        const calendar = new FullCalendar.Calendar(calendarEl, {
            height: 500,
            locale: 'fa',
            firstDay: 6,
            direction: 'rtl',
            initialView: isMobile ? 'listWeek' : 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: isMobile ? 'listWeek,listDay' : 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'امروز',
                month: 'ماه',
                week: 'هفته',
                day: 'روز',
                listDay: 'برنامه روزانه',
                listWeek: 'برنامه هفتگی'
            },
            editable: false,
            selectable: false,
            events: function(fetchInfo, successCallback, failureCallback) {
                const baseUrl = window.location.origin + window.location.pathname;
                fetch(baseUrl + '?get_events=1')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(events => {
                        events.forEach(ev => {
                            ev.extendedProps = ev.extendedProps || {};
                            ev.extendedProps.description = ev.description;
                            ev.extendedProps.jalali_start = ev.jalali_start;
                            ev.extendedProps.jalali_end = ev.jalali_end;
                        });
                        successCallback(events);
                    })
                    .catch(error => {
                        console.error('Error fetching events:', error);
                        failureCallback(error);
                    });
            },
            dayHeaderContent: function(arg) {
                const fullNames = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
                let dayIndex = arg.date.getDay();
                let persianIndex = (dayIndex + 1) % 7;

                return { html: fullNames[persianIndex] };
            },
            datesSet: function(info) { applyTransposedMode(info); },
            // رویدادها بعد از datesSet می رسند، پس نمای جابجاشده دوباره ساخته می شود
            eventsSet: function() {
                const v = calendar.view;
                if (v && (v.type === 'timeGridWeek' || v.type === 'timeGridDay')) {
                    renderTransposed(v.activeStart, v.activeEnd, calendar.getEvents());
                }
            },
            eventClick: function(info) { showStudentEventDetail(info.event); }
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