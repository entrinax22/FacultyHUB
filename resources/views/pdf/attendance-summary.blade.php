<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>Attendance Summary</title>

    <style>
        @page {
            margin: 35px 35px 45px 35px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
        }

        .header {
            text-align: center;
            margin-bottom: 18px;
        }

        .school-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .report-title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
        }

        .subtitle {
            font-size: 10px;
            margin-top: 3px;
            color: #4b5563;
        }

        .details {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }

        .details td {
            padding: 4px 6px;
            vertical-align: top;
        }

        .details .label {
            font-weight: bold;
            width: 90px;
        }

        table.attendance {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.attendance th,
        table.attendance td {
            border: 1px solid #9ca3af;
            padding: 5px 4px;
        }

        table.attendance th {
            background: #e5e7eb;
            text-align: center;
            font-weight: bold;
        }

        table.attendance td {
            text-align: center;
        }

        table.attendance td.student {
            text-align: left;
        }

        .percentage {
            font-weight: bold;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            border: 1px solid #9ca3af;
        }

        .footer {
            margin-top: 20px;
            font-size: 9px;
            color: #6b7280;
        }

        .signature-section {
            margin-top: 35px;
            width: 100%;
        }

        .signature {
            width: 40%;
            display: inline-block;
            text-align: center;
            vertical-align: top;
        }

        .signature-line {
            border-top: 1px solid #111827;
            margin: 35px auto 5px;
            width: 80%;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>

<body>

    <div class="header">
        <div class="school-name">
            SNSU
        </div>

        <div>
            College of Computing and Information Sciences
        </div>

        <div class="report-title">
            ATTENDANCE SUMMARY
        </div>

        <div class="subtitle">
            Generated on {{ now()->format('F d, Y h:i A') }}
        </div>
    </div>

    <table class="details">
        <tr>
            <td class="label">
                Subject:
            </td>

            <td>
                {{ $section->subject?->code ?? 'N/A' }}
                -
                {{ $section->subject?->name ?? 'N/A' }}
            </td>

            <td class="label">
                Section:
            </td>

            <td>
                {{ $section->name }}
            </td>
        </tr>

        <tr>
            <td class="label">
                Semester:
            </td>

            <td>
                {{ $section->semester?->name ?? 'N/A' }}
            </td>

            <td class="label">
                Sessions:
            </td>

            <td>
                {{ $totalSessions }}
            </td>
        </tr>
    </table>

    @if ($rows->isNotEmpty())

        <table class="attendance">
            <thead>
                <tr>
                    <th style="width: 4%;">
                        #
                    </th>

                    <th style="width: 12%;">
                        Student No.
                    </th>

                    <th style="width: 25%;">
                        Student Name
                    </th>

                    <th style="width: 8%;">
                        Present
                    </th>

                    <th style="width: 8%;">
                        Late
                    </th>

                    <th style="width: 8%;">
                        Absent
                    </th>

                    <th style="width: 8%;">
                        Excused
                    </th>

                    <th style="width: 8%;">
                        Attended
                    </th>

                    <th style="width: 11%;">
                        Attendance
                    </th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $index => $row)

                    <tr>
                        <td>
                            {{ $index + 1 }}
                        </td>

                        <td>
                            {{ $row['student_no'] ?? 'N/A' }}
                        </td>

                        <td class="student">
                            {{ $row['last_name'] }},
                            {{ $row['first_name'] }}

                            @if (!empty($row['middle_name']))
                                {{ ' ' . $row['middle_name'] }}
                            @endif
                        </td>

                        <td>
                            {{ $row['present'] }}
                        </td>

                        <td>
                            {{ $row['late'] }}
                        </td>

                        <td>
                            {{ $row['absent'] }}
                        </td>

                        <td>
                            {{ $row['excused'] }}
                        </td>

                        <td>
                            {{ $row['attended'] }}
                            / {{ $row['total'] }}
                        </td>

                        <td class="percentage">
                            @if ($row['percentage'] !== null)
                                {{ number_format($row['percentage'], 2) }}%
                            @else
                                —
                            @endif
                        </td>
                    </tr>

                @endforeach
            </tbody>
        </table>

    @else

        <div class="no-data">
            No active students or attendance records found.
        </div>

    @endif

    <div class="signature-section">

        <div class="signature">
            <div class="signature-line"></div>
            Instructor
        </div>

        <div class="signature">
            <div class="signature-line"></div>
            Date
        </div>

    </div>

    <div class="footer">
        Attendance Summary Report
        &nbsp; | &nbsp;
        {{ $section->name }}
    </div>

</body>
</html>