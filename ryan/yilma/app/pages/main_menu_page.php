<?php
session_start();
require_once __DIR__ . '/../database.php';

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function normalizeUserKey($value)
{
    return strtolower(trim((string)$value));
}

function formatTimeLabel($startTime, $endTime)
{
    if (empty($startTime) || empty($endTime)) {
        return 'Time not scheduled';
    }
    return date('g:i A', strtotime($startTime)) . ' - ' . date('g:i A', strtotime($endTime));
}

function formatPercent($value)
{
    if ($value === null) {
        return 'N/A';
    }
    return number_format((float)$value, 1) . '%';
}

function parseDateOrFallback($dateValue, DateTimeImmutable $fallback)
{
    $value = trim((string)$dateValue);
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if (!$parsed || $parsed->format('Y-m-d') !== $value) {
        return $fallback;
    }
    return $parsed;
}

function buildReportRange($period, $dayDate, $customStartDate, $customEndDate)
{
    $allowedPeriods = ['day', 'last7', 'custom'];
    if (!in_array($period, $allowedPeriods, true)) {
        $period = 'day';
    }

    $today = new DateTimeImmutable('today');
    $dayAnchor = parseDateOrFallback($dayDate, $today);
    $customStart = parseDateOrFallback($customStartDate, $today->modify('-6 days'));
    $customEnd = parseDateOrFallback($customEndDate, $today);

    if ($customStart > $customEnd) {
        $swap = $customStart;
        $customStart = $customEnd;
        $customEnd = $swap;
    }

    if ($period === 'last7') {
        $anchor = $today;
        $start = $today->modify('-6 days');
        $end = $today;
        $title = 'Last 7 Days';
    } elseif ($period === 'custom') {
        $anchor = $customEnd;
        $start = $customStart;
        $end = $customEnd;
        $title = 'Custom Range';
    } else {
        $anchor = $dayAnchor;
        $start = $dayAnchor;
        $end = $dayAnchor;
        $title = 'Daily Snapshot';
    }

    return [
        'period' => $period,
        'title' => $title,
        'anchor' => $anchor->format('Y-m-d'),
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'day_date' => $dayAnchor->format('Y-m-d'),
        'custom_start' => $customStart->format('Y-m-d'),
        'custom_end' => $customEnd->format('Y-m-d'),
        'label' => $start->format('F j, Y') . ' to ' . $end->format('F j, Y'),
    ];
}

function buildScheduleSummary($scheduleRows)
{
    if (!$scheduleRows) {
        return 'No schedule on file';
    }

    $parts = [];
    foreach ($scheduleRows as $row) {
        $parts[] = $row['day_of_week'] . ' ' . formatTimeLabel($row['start_time'], $row['end_time']);
    }
    return implode(' | ', $parts);
}

function buildScheduledMeetings($scheduleRows, $startDate, $endDate)
{
    $scheduleByDay = [];
    foreach ($scheduleRows as $row) {
        $scheduleByDay[$row['day_of_week']][] = $row;
    }

    $meetings = [];
    $cursor = new DateTimeImmutable($startDate);
    $end = new DateTimeImmutable($endDate);

    while ($cursor <= $end) {
        $dayCode = $cursor->format('D');
        if (isset($scheduleByDay[$dayCode])) {
            $dayRows = $scheduleByDay[$dayCode];
            $startTimes = array_column($dayRows, 'start_time');
            $endTimes = array_column($dayRows, 'end_time');
            sort($startTimes);
            rsort($endTimes);

            $dateKey = $cursor->format('Y-m-d');
            $meetings[$dateKey] = [
                'meeting_date' => $dateKey,
                'display_date' => $cursor->format('M j, Y'),
                'day_label' => $dayCode,
                'start_time' => $startTimes[0],
                'end_time' => $endTimes[0],
                'time_label' => formatTimeLabel($startTimes[0], $endTimes[0]),
            ];
        }
        $cursor = $cursor->modify('+1 day');
    }

    return $meetings;
}

function buildAttendanceReport($rosterRows, $attendanceRows, $scheduledMeetings, $period)
{
    $studentRows = [];
    $studentOrder = [];
    $scheduledDates = array_keys($scheduledMeetings);
    $rosterCount = count($rosterRows);
    $attendanceByDate = [];
    $extraScans = [];

    foreach ($rosterRows as $row) {
        $studentKey = normalizeUserKey($row['student_username']);
        $studentOrder[] = $studentKey;
        $studentRows[$studentKey] = [
            'student_username' => $row['student_username'],
            'full_name' => $row['full_name'],
            'present_count' => 0,
            'meeting_count' => 0,
            'absent_count' => 0,
            'attendance_rate' => null,
            'status' => 'Absent',
            'scanned_at' => '',
            'scan_image_path' => '',
        ];
    }

    foreach ($attendanceRows as $row) {
        $studentKey = normalizeUserKey($row['student_username']);
        $meetingDate = $row['meeting_date'];
        if (isset($studentRows[$studentKey])) {
            $attendanceByDate[$meetingDate][$studentKey] = $row;
        } else {
            $extraScans[] = $row;
        }
    }

    $meetingRows = [];
    $totalPresent = 0;

    foreach ($scheduledMeetings as $meetingDate => $meeting) {
        $presentCount = 0;
        $firstScan = '';

        foreach ($studentOrder as $studentKey) {
            $studentRows[$studentKey]['meeting_count']++;

            if (isset($attendanceByDate[$meetingDate][$studentKey])) {
                $presentCount++;
                $totalPresent++;
                $studentRows[$studentKey]['present_count']++;
                $studentRows[$studentKey]['status'] = 'Present';
                $studentRows[$studentKey]['scanned_at'] = $attendanceByDate[$meetingDate][$studentKey]['scanned_at'];
                $studentRows[$studentKey]['scan_image_path'] = isset($attendanceByDate[$meetingDate][$studentKey]['scan_image_path'])
                    ? (string)$attendanceByDate[$meetingDate][$studentKey]['scan_image_path']
                    : '';

                if ($firstScan === '' || strtotime($attendanceByDate[$meetingDate][$studentKey]['scanned_at']) < strtotime($firstScan)) {
                    $firstScan = $attendanceByDate[$meetingDate][$studentKey]['scanned_at'];
                }
            } elseif ($period === 'day' && count($scheduledDates) > 0) {
                $studentRows[$studentKey]['status'] = 'Absent';
                $studentRows[$studentKey]['scan_image_path'] = '';
            }
        }

        $absentCount = max(0, $rosterCount - $presentCount);
        $attendanceRate = $rosterCount > 0 ? ($presentCount / $rosterCount) * 100 : null;
        $meetingRows[] = [
            'meeting_date' => $meetingDate,
            'display_date' => $meeting['display_date'],
            'day_label' => $meeting['day_label'],
            'time_label' => $meeting['time_label'],
            'present_count' => $presentCount,
            'absent_count' => $absentCount,
            'attendance_rate' => $attendanceRate,
            'first_scan' => $firstScan !== '' ? date('g:i A', strtotime($firstScan)) : '-',
        ];
    }

    if ($period === 'day' && count($scheduledDates) === 0) {
        foreach ($studentOrder as $studentKey) {
            $studentRows[$studentKey]['status'] = 'No class scheduled';
            $studentRows[$studentKey]['scanned_at'] = '';
            $studentRows[$studentKey]['scan_image_path'] = '';
        }
    }

    $studentsBelowThreshold = 0;
    foreach ($studentOrder as $studentKey) {
        $meetingCount = $studentRows[$studentKey]['meeting_count'];
        $presentCount = $studentRows[$studentKey]['present_count'];
        $studentRows[$studentKey]['absent_count'] = max(0, $meetingCount - $presentCount);
        if ($meetingCount > 0) {
            $studentRows[$studentKey]['attendance_rate'] = ($presentCount / $meetingCount) * 100;
            if ($studentRows[$studentKey]['attendance_rate'] < 75) {
                $studentsBelowThreshold++;
            }
        }
    }

    $unmatchedMeetingRows = [];
    foreach ($attendanceByDate as $meetingDate => $scans) {
        if (!isset($scheduledMeetings[$meetingDate])) {
            foreach ($scans as $scan) {
                $unmatchedMeetingRows[] = $scan;
            }
        }
    }
    foreach ($extraScans as $scan) {
        $unmatchedMeetingRows[] = $scan;
    }

    $scheduledMeetingCount = count($meetingRows);
    $overallRate = ($rosterCount > 0 && $scheduledMeetingCount > 0)
        ? ($totalPresent / ($rosterCount * $scheduledMeetingCount)) * 100
        : null;

    $orderedStudents = [];
    foreach ($studentOrder as $studentKey) {
        $orderedStudents[] = $studentRows[$studentKey];
    }

    return [
        'metrics' => [
            'roster_count' => $rosterCount,
            'scheduled_meetings' => $scheduledMeetingCount,
            'overall_attendance_rate' => $overallRate,
            'students_below_threshold' => $studentsBelowThreshold,
        ],
        'meeting_rows' => $meetingRows,
        'student_rows' => $orderedStudents,
        'unmatched_rows' => $unmatchedMeetingRows,
    ];
}

$db = new database();
if (!isset($_SESSION['valid_user'])) {
    header('Location: ./index.php');
    exit();
}
if (method_exists($db->connection, 'set_charset')) {
    $db->connection->set_charset('utf8mb4');
}

$currentUser = $_SESSION['valid_user'];
$isAdmin = $db->isAdmin($currentUser);
$isProfessor = $db->isProf($currentUser);
if (!$isAdmin && !$isProfessor) {
    header('Location: ./index.php');
    exit();
}

$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selectedPeriod = isset($_GET['report_period']) ? $_GET['report_period'] : 'day';
$selectedDate = isset($_GET['report_date']) ? $_GET['report_date'] : date('Y-m-d');
$selectedStartDate = isset($_GET['report_start_date']) ? $_GET['report_start_date'] : date('Y-m-d', strtotime('-6 days'));
$selectedEndDate = isset($_GET['report_end_date']) ? $_GET['report_end_date'] : date('Y-m-d');
$reportRange = buildReportRange($selectedPeriod, $selectedDate, $selectedStartDate, $selectedEndDate);
$classes = $db->getClassList($currentUser);
$displayName = $db->getUserInfo($currentUser, 'full_name');
$dashboardLabel = $isAdmin && $isProfessor
    ? 'Professor / Administrator Reporting'
    : ($isAdmin ? 'Administrator Reporting' : 'Professor Reporting');

$selectedClass = null;
$scheduleRows = [];
$reportData = null;
$reportError = '';

if ($selectedClassId > 0) {
    if (!$db->userCanAccessClass($currentUser, $selectedClassId)) {
        $reportError = 'You do not have access to that class report.';
    } else {
        $selectedClass = $db->getClassDetails($selectedClassId);
        if (!$selectedClass) {
            $reportError = 'That class could not be found.';
        } else {
            $scheduleRows = $db->getClassScheduleEntries($selectedClassId);
            $rosterRows = $db->getClassRoster($selectedClassId);
            $attendanceRows = $db->getAttendanceForRange(
                $selectedClassId,
                $reportRange['start'],
                $reportRange['end']
            );
            $scheduledMeetings = buildScheduledMeetings(
                $scheduleRows,
                $reportRange['start'],
                $reportRange['end']
            );
            $reportData = buildAttendanceReport(
                $rosterRows,
                $attendanceRows,
                $scheduledMeetings,
                $reportRange['period']
            );
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Metro State University | Attendance Reports</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="style.css">
<script>
document.addEventListener('DOMContentLoaded', function () {
    var periodSelect = document.getElementById('reportPeriod');
    var dayDateControl = document.getElementById('reportDateControl');
    var dayDateInput = document.getElementById('reportDate');
    var customStartControl = document.getElementById('reportStartControl');
    var customStartInput = document.getElementById('reportStartDate');
    var customEndControl = document.getElementById('reportEndControl');
    var customEndInput = document.getElementById('reportEndDate');
    if (
        !periodSelect ||
        !dayDateControl || !dayDateInput ||
        !customStartControl || !customStartInput ||
        !customEndControl || !customEndInput
    ) {
        return;
    }

    function syncRangeState() {
        var period = periodSelect.value;
        var isDay = period === 'day';
        var isCustom = period === 'custom';

        dayDateControl.style.display = isDay ? '' : 'none';
        dayDateInput.disabled = !isDay;

        customStartControl.style.display = isCustom ? '' : 'none';
        customEndControl.style.display = isCustom ? '' : 'none';
        customStartInput.disabled = !isCustom;
        customEndInput.disabled = !isCustom;
    }

    periodSelect.addEventListener('change', syncRangeState);
    syncRangeState();
});
</script>
</head>
<body class="report-page">
<div class="bg-animation">
    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>
    <div class="bg-shape shape-3"></div>
</div>

<div class="dashboard-buttons">
    <?php if ($isAdmin): ?>
        <a href="admin.php" class="btn">Admin Page</a>
    <?php endif; ?>
    <a href="logout.php" class="btn logout">Logout</a>
</div>

<div id="dashboard" class="report-dashboard">
    <h2 id="dashTitle"><?php echo e('Welcome ' . $displayName); ?></h2>
    <p class="report-subtitle"><?php echo e($dashboardLabel); ?></p>

    <form method="get" class="report-controls">
        <div class="report-control">
            <label for="classSelect">Class</label>
            <select id="classSelect" name="class_id">
                <option value="">Select a class</option>
                <?php foreach ($classes as $row): ?>
                    <?php
                    $cid = (int)$row['class_id'];
                    $optionLabel = $row['class_name'];
                    if ($isAdmin) {
                        $optionLabel .= ' | ' . $row['professor_name'];
                    }
                    ?>
                    <option value="<?php echo $cid; ?>"<?php echo $cid === $selectedClassId ? ' selected' : ''; ?>>
                        <?php echo e($optionLabel); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="report-control">
            <label for="reportPeriod">Report Type</label>
            <select id="reportPeriod" name="report_period">
                <option value="day"<?php echo $reportRange['period'] === 'day' ? ' selected' : ''; ?>>Day</option>
                <option value="last7"<?php echo $reportRange['period'] === 'last7' ? ' selected' : ''; ?>>Last 7 days</option>
                <option value="custom"<?php echo $reportRange['period'] === 'custom' ? ' selected' : ''; ?>>Custom</option>
            </select>
        </div>
        <div class="report-control" id="reportDateControl">
            <label for="reportDate">Date</label>
            <input
                id="reportDate"
                type="date"
                name="report_date"
                value="<?php echo e($reportRange['day_date']); ?>"
                <?php echo $reportRange['period'] === 'day' ? '' : 'disabled'; ?>
            >
        </div>
        <div class="report-control" id="reportStartControl">
            <label for="reportStartDate">Start Date</label>
            <input
                id="reportStartDate"
                type="date"
                name="report_start_date"
                value="<?php echo e($reportRange['custom_start']); ?>"
                <?php echo $reportRange['period'] === 'custom' ? '' : 'disabled'; ?>
            >
        </div>
        <div class="report-control" id="reportEndControl">
            <label for="reportEndDate">End Date</label>
            <input
                id="reportEndDate"
                type="date"
                name="report_end_date"
                value="<?php echo e($reportRange['custom_end']); ?>"
                <?php echo $reportRange['period'] === 'custom' ? '' : 'disabled'; ?>
            >
        </div>
        <div class="report-actions">
            <button type="submit" class="mini-btn action-btn">Run Report</button>
            <?php if ($selectedClass && $reportData): ?>
                <button type="button" class="mini-btn secondary-btn" onclick="window.print()">Print / Save PDF</button>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($reportError !== ''): ?>
        <div class="report-alert error"><?php echo e($reportError); ?></div>
    <?php elseif (!$classes): ?>
        <div class="report-alert info">No classes are assigned to this account yet.</div>
    <?php elseif (!$selectedClass): ?>
        <div class="report-alert info">Choose a class and report type to see attendance summaries.</div>
    <?php else: ?>
        <div class="class-info report-class-info">
            <p><strong><?php echo e($selectedClass['class_name']); ?></strong></p>
            <p>
                Room <?php echo e($selectedClass['roomNumber']); ?>
                | Professor: <?php echo e($selectedClass['professor_name']); ?>
                | <?php echo e($reportRange['title']); ?>: <?php echo e($reportRange['label']); ?>
            </p>
            <p>Weekly schedule: <?php echo e(buildScheduleSummary($scheduleRows)); ?></p>
        </div>

        <div class="report-metrics">
            <div class="metric-card">
                <span class="metric-label">Enrolled Students</span>
                <strong class="metric-value"><?php echo e($reportData['metrics']['roster_count']); ?></strong>
            </div>
            <div class="metric-card">
                <span class="metric-label">Scheduled Meetings</span>
                <strong class="metric-value"><?php echo e($reportData['metrics']['scheduled_meetings']); ?></strong>
            </div>
            <div class="metric-card">
                <span class="metric-label">Average Attendance</span>
                <strong class="metric-value"><?php echo e(formatPercent($reportData['metrics']['overall_attendance_rate'])); ?></strong>
            </div>
            <div class="metric-card">
                <span class="metric-label">Below 75%</span>
                <strong class="metric-value"><?php echo e($reportData['metrics']['students_below_threshold']); ?></strong>
            </div>
        </div>

        <section class="report-section">
            <div class="section-heading">
                <h3>Meeting Summary</h3>
                <span class="section-note"><?php echo e($reportRange['label']); ?></span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Rate</th>
                            <th>First Scan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$reportData['meeting_rows']): ?>
                            <tr><td colspan="6" style="text-align:center;opacity:.8">No scheduled meetings were found in this date range.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reportData['meeting_rows'] as $meeting): ?>
                                <tr>
                                    <td data-label="Date"><?php echo e($meeting['display_date'] . ' (' . $meeting['day_label'] . ')'); ?></td>
                                    <td data-label="Time"><?php echo e($meeting['time_label']); ?></td>
                                    <td data-label="Present"><?php echo e($meeting['present_count']); ?></td>
                                    <td data-label="Absent"><?php echo e($meeting['absent_count']); ?></td>
                                    <td data-label="Rate"><?php echo e(formatPercent($meeting['attendance_rate'])); ?></td>
                                    <td data-label="First Scan"><?php echo e($meeting['first_scan']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="report-section">
            <div class="section-heading">
                <h3><?php echo $reportRange['period'] === 'day' ? 'Student Snapshot' : 'Student Attendance Summary'; ?></h3>
                <span class="section-note"><?php echo e($reportRange['title']); ?></span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <?php if ($reportRange['period'] === 'day'): ?>
                                <th>Status</th>
                                <th>Scan Time</th>
                                <th>Scan Photo</th>
                            <?php else: ?>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Attendance</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$reportData['student_rows']): ?>
                            <tr><td colspan="<?php echo $reportRange['period'] === 'day' ? '5' : '5'; ?>" style="text-align:center;opacity:.8">No enrolled students were found for this class.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reportData['student_rows'] as $student): ?>
                                <tr>
                                    <td data-label="Student ID"><?php echo e($student['student_username']); ?></td>
                                    <td data-label="Name"><?php echo e($student['full_name']); ?></td>
                                    <?php if ($reportRange['period'] === 'day'): ?>
                                        <td data-label="Status"><?php echo e($student['status']); ?></td>
                                        <td data-label="Scan Time">
                                            <?php echo $student['scanned_at'] !== '' ? e(date('Y-m-d g:i:s A', strtotime($student['scanned_at']))) : '-'; ?>
                                        </td>
                                        <td data-label="Scan Photo">
                                            <?php if (!empty($student['scan_image_path'])): ?>
                                                <a href="<?php echo e($student['scan_image_path']); ?>" target="_blank" rel="noopener">
                                                    <img class="report-scan-image" src="<?php echo e($student['scan_image_path']); ?>" alt="Student scan photo">
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    <?php else: ?>
                                        <td data-label="Present"><?php echo e($student['present_count']); ?></td>
                                        <td data-label="Absent"><?php echo e($student['absent_count']); ?></td>
                                        <td data-label="Attendance"><?php echo e(formatPercent($student['attendance_rate'])); ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($reportData['unmatched_rows']): ?>
            <section class="report-section">
                <div class="section-heading">
                    <h3>Attendance Exceptions</h3>
                    <span class="section-note">Scans outside the enrolled roster or scheduled meeting dates</span>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student / Label</th>
                                <th>Scan Time</th>
                                <th>Scan Photo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['unmatched_rows'] as $scan): ?>
                                <tr>
                                    <td data-label="Date"><?php echo e($scan['meeting_date']); ?></td>
                                    <td data-label="Student / Label"><?php echo e($scan['full_name']); ?></td>
                                    <td data-label="Scan Time"><?php echo e(date('Y-m-d g:i:s A', strtotime($scan['scanned_at']))); ?></td>
                                    <td data-label="Scan Photo">
                                        <?php if (!empty($scan['scan_image_path'])): ?>
                                            <a href="<?php echo e($scan['scan_image_path']); ?>" target="_blank" rel="noopener">
                                                <img class="report-scan-image" src="<?php echo e($scan['scan_image_path']); ?>" alt="Student scan photo">
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
