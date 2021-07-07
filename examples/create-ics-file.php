<?php

error_reporting(E_ALL);
ini_set('display_errors', true);

$params = [];

// Organizer
$params['organizer']['cn'] = 'LUMS'; // Organizer Name
$params['organizer']['mailto'] = 'no-reply@lums.edu.pk'; // Organizer Email

// Attendees
$attendees = [];
$attendees[0]['cn'] = 'Faisal Rehman'; // Attendee Name
$attendees[0]['mailto'] = 'faisal.rehman@lums.edu.pk'; // Attendee Email

$attendees[1]['cn'] = 'Faisal Rehman Yahoo'; // Attendee Name
$attendees[1]['mailto'] = 'faisalrehmanid@yahoo.com'; // Attendee Email

$attendees[2]['cn'] = 'Faisal Rehman Gmail'; // Attendee Name
$attendees[2]['mailto'] = 'faisalrehmanid@gmail.com'; // Attendee Email

$attendees[3]['cn'] = 'Faisal Rehman Hotmail'; // Attendee Name
$attendees[3]['mailto'] = 'faisalrehmanid@hotmail.com'; // Attendee Email

$params['attendees'] = $attendees;

// Start Time
$params['start_at'] = '2021-02-26 17:30:00'; // Format Y-m-d H:i:s

// End Time
$params['end_at'] = '2021-02-26 18:30:00'; // Format Y-m-d H:i:s

// Summary
$params['summary'] = 'Meeting Subject'; // Meeting summary

// Location
$params['location'] = 'Meeting Location'; // Meeting location

function createICalObject(array $params)
{
    date_default_timezone_set('Asia/Karachi');

    @$organizer_cn = $params['organizer']['cn'];
    @$organizer_mailto = $params['organizer']['mailto'];
    @$attendees = $params['attendees'];
    @$start_at = $params['start_at'];
    @$end_at = $params['end_at'];
    @$summary = $params['summary'];
    @$location = $params['location'];

    $ICAL = '';
    $ICAL .= 'BEGIN:VCALENDAR' . PHP_EOL;
    $ICAL .= 'PRODID:-//Microsoft Corporation//Outlook 10.0 MIMEDIR//EN' . PHP_EOL;
    $ICAL .= 'VERSION:2.0' . PHP_EOL;
    $ICAL .= 'METHOD:REQUEST' . PHP_EOL;

    $ICAL .= 'BEGIN:VTIMEZONE' . PHP_EOL;
    $ICAL .= 'TZID:Pakistan Standard Time' . PHP_EOL;
    $ICAL .= 'BEGIN:STANDARD' . PHP_EOL;
    $ICAL .= 'DTSTART:16010101T000000' . PHP_EOL;
    $ICAL .= 'TZOFFSETFROM:+0500' . PHP_EOL;
    $ICAL .= 'TZOFFSETTO:+0500' . PHP_EOL;
    $ICAL .= 'END:STANDARD' . PHP_EOL;
    $ICAL .= 'END:VTIMEZONE' . PHP_EOL;

    $ICAL .= 'BEGIN:VEVENT' . PHP_EOL;
    $ICAL .= 'ORGANIZER;CN="' . $organizer_cn . '":MAILTO:' . $organizer_mailto . PHP_EOL;

    foreach ($attendees as $attendee) {
        @$ICAL .= 'ATTENDEE;CN="' . $attendee['cn'] . '";ROLE=REQ-PARTICIPANT;RSVP=TRUE:MAILTO:' . $attendee['mailto'] . PHP_EOL;
    }

    $ICAL .= 'LAST-MODIFIED:' . date("Ymd\TGis") . PHP_EOL;
    $ICAL .= 'UID:' . md5(microtime() . rand()) . PHP_EOL;
    $ICAL .= 'DTSTAMP:' . date("Ymd\TGis") . PHP_EOL;
    $ICAL .= 'DTSTART;TZID="Pakistan Standard Time":' . date("Ymd\THis", strtotime($start_at)) . PHP_EOL;
    $ICAL .= 'DTEND;TZID="Pakistan Standard Time":' . date("Ymd\THis", strtotime($end_at)) . PHP_EOL;
    $ICAL .= 'TRANSP:OPAQUE' . PHP_EOL;
    $ICAL .= 'SEQUENCE:0' . PHP_EOL;
    $ICAL .= 'SUMMARY:' . $summary . PHP_EOL;
    $ICAL .= 'LOCATION:' . $location . PHP_EOL;
    $ICAL .= 'CLASS:PUBLIC' . PHP_EOL;
    $ICAL .= 'PRIORITY:5' . PHP_EOL;

    $ICAL .= 'BEGIN:VALARM' . PHP_EOL;
    $ICAL .= 'TRIGGER:-PT15M' . PHP_EOL;
    $ICAL .= 'ACTION:DISPLAY' . PHP_EOL;
    $ICAL .= 'DESCRIPTION:Reminder' . PHP_EOL;
    $ICAL .= 'END:VALARM' . PHP_EOL;

    $ICAL .= 'END:VEVENT' . PHP_EOL;
    $ICAL .= 'END:VCALENDAR' . PHP_EOL;

    return $ICAL;
}

$content = createICalObject($params);

$path = './sample-attachments/invite.ics';
$fp = fopen($path, 'w');
fwrite($fp, $content);
fclose($fp);

echo 'ICS File Created. Check `' . $path . '` ';
