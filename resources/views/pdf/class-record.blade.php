<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>Class Record</title>

    <style>
        @page {
            margin: 20px 15px 20px 15px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            color: #111827;
            margin: 0;
            padding: 0;
        }

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .header {
            width: 100%;
            text-align: center;
            margin-bottom: 12px;
        }

        .school-name {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .document-title {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .section-info {
            width: 100%;
            margin-bottom: 10px;
        }

        .section-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .section-info td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .label {
            font-weight: bold;
        }

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        .summary {
            width: 100%;
            margin-bottom: 10px;
        }

        .summary table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary td {
            border: 1px solid #9ca3af;
            padding: 4px;
            text-align: center;
        }

        .summary-label {
            font-weight: bold;
        }

        /*
        |--------------------------------------------------------------------------
        | Class Record Table
        |--------------------------------------------------------------------------
        */

        .record {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        .record th,
        .record td {
            border: 0.5px solid #6b7280;
            padding: 3px 2px;
            text-align: center;
            vertical-align: middle;
        }

        .record th {
            font-weight: bold;
            background: #e5e7eb;
        }

        .student-column {
            text-align: left !important;
            min-width: 150px;
        }

        .student-number {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 7px;
            color: #4b5563;
        }

        .student-name {
            font-weight: bold;
            font-size: 8px;
        }

        .component-header {
            background: #d1d5db;
            font-size: 7px;
        }

        .item-header {
            background: #f3f4f6;
            font-size: 7px;
        }

        .score {
            font-size: 7px;
        }

        .empty {
            color: #9ca3af;
        }

        /*
        |--------------------------------------------------------------------------
        | Grade Columns
        |--------------------------------------------------------------------------
        */

        .grade-header {
            background: #dbeafe;
        }

        .final-header {
            background: #dcfce7;
        }

        .grade-value {
            font-weight: bold;
        }

        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        .footer {
            margin-top: 12px;
            width: 100%;
        }

        .footer table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer td {
            padding: 4px;
            vertical-align: top;
        }

        .signature {
            margin-top: 35px;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #111827;
            width: 220px;
            margin: 0 auto 3px auto;
        }

        /*
        |--------------------------------------------------------------------------
        | Page Number
        |--------------------------------------------------------------------------
        */

        .page-number {
            position: fixed;
            bottom: -5px;
            right: 0;
            font-size: 7px;
            color: #6b7280;
        }

        /*
        |--------------------------------------------------------------------------
        | Avoid Row Splitting
        |--------------------------------------------------------------------------
        */

        tr {
            page-break-inside: avoid;
        }
    </style>
</head>

<body>

    {{-- ====================================================================== --}}
    {{-- HEADER --}}
    {{-- ====================================================================== --}}

    <div class="header">

        <div class="school-name">
            CLASS RECORD
        </div>

        <div class="document-title">
            {{ $section['subject']['code'] ?? '' }}
            -
            {{ $section['subject']['name'] ?? '' }}
        </div>

    </div>


    {{-- ====================================================================== --}}
    {{-- SECTION INFORMATION --}}
    {{-- ====================================================================== --}}

    <div class="section-info">

        <table>

            <tr>
                <td width="12%">
                    <span class="label">Section:</span>
                </td>

                <td width="38%">
                    {{ $section['name'] ?? 'N/A' }}
                </td>

                <td width="12%">
                    <span class="label">Semester:</span>
                </td>

                <td width="38%">
                    {{ $section['semester']['name'] ?? 'N/A' }}
                </td>
            </tr>

            <tr>
                <td>
                    <span class="label">School Year:</span>
                </td>

                <td>
                    {{ $section['semester']['school_year'] ?? 'N/A' }}
                </td>

                <td>
                    <span class="label">Faculty:</span>
                </td>

                <td>
                    {{ $section['faculty']['name'] ?? 'N/A' }}
                </td>
            </tr>

        </table>

    </div>


    {{-- ====================================================================== --}}
    {{-- GRADING SUMMARY --}}
    {{-- ====================================================================== --}}

    <div class="summary">

        <table>

            <tr>

                <td class="summary-label">
                    Midterm Weight
                </td>

                <td>
                    {{ number_format((float) ($midtermWeight ?? 0), 2) }}%
                </td>

                <td class="summary-label">
                    Finals Weight
                </td>

                <td>
                    {{ number_format((float) ($finalsWeight ?? 0), 2) }}%
                </td>

                <td class="summary-label">
                    General Weight
                </td>

                <td>
                    {{ number_format((float) ($generalWeight ?? 0), 2) }}%
                </td>

                <td class="summary-label">
                    Students
                </td>

                <td>
                    {{ count($rows ?? []) }}
                </td>

            </tr>

        </table>

    </div>


    {{-- ====================================================================== --}}
    {{-- CLASS RECORD --}}
    {{-- ====================================================================== --}}

    <table class="record">

        {{-- ================================================================== --}}
        {{-- COMPONENT HEADER --}}
        {{-- ================================================================== --}}

        <thead>

            <tr>

                <th
                    rowspan="2"
                    class="student-column"
                >
                    Student
                </th>


                {{-- ========================================================== --}}
                {{-- GRADING COMPONENTS --}}
                {{-- ========================================================== --}}

                @foreach ($components as $component)

                    @php
                        $componentItems = collect($items ?? [])
                            ->where('component_id', $component['id'])
                            ->values();

                        $itemCount = $componentItems->count();
                    @endphp

                    <th
                        colspan="{{ max($itemCount, 1) }}"
                        class="component-header"
                    >
                        {{ $component['name'] }}

                        @if (
                            isset($component['weight_percentage'])
                        )
                            <br>
                            <small>
                                {{ number_format(
                                    (float) $component['weight_percentage'],
                                    2
                                ) }}%
                            </small>
                        @endif

                    </th>

                @endforeach


                {{-- ========================================================== --}}
                {{-- STANDALONE ASSIGNMENTS --}}
                {{-- ========================================================== --}}

                @foreach ($assignments as $assignment)

                    <th
                        rowspan="2"
                        class="component-header"
                    >
                        {{ $assignment['title'] }}

                        <br>

                        <small>
                            / {{ $assignment['max_score'] }}
                        </small>
                    </th>

                @endforeach


                {{-- ========================================================== --}}
                {{-- GRADE COLUMNS --}}
                {{-- ========================================================== --}}

                <th
                    rowspan="2"
                    class="grade-header"
                >
                    Midterm
                </th>

                <th
                    rowspan="2"
                    class="grade-header"
                >
                    Finals
                </th>

                <th
                    rowspan="2"
                    class="grade-header"
                >
                    Total
                </th>

                <th
                    rowspan="2"
                    class="final-header"
                >
                    Final Grade
                </th>

            </tr>


            {{-- ================================================================= --}}
            {{-- ITEM HEADER --}}
            {{-- ================================================================= --}}

            <tr>

                @foreach ($components as $component)

                    @php
                        $componentItems = collect($items ?? [])
                            ->where('component_id', $component['id'])
                            ->values();
                    @endphp

                    @if ($componentItems->isEmpty())

                        <th class="item-header">
                            -
                        </th>

                    @else

                        @foreach ($componentItems as $item)

                            <th class="item-header">

                                {{ $item['name'] }}

                                <br>

                                <small>
                                    / {{ $item['max_score'] }}
                                </small>

                            </th>

                        @endforeach

                    @endif

                @endforeach

            </tr>

        </thead>


        {{-- ====================================================================== --}}
        {{-- STUDENT ROWS --}}
        {{-- ====================================================================== --}}

        <tbody>

            @forelse ($rows as $row)

                <tr>

                    {{-- ========================================================== --}}
                    {{-- STUDENT --}}
                    {{-- ========================================================== --}}

                    <td class="student-column">

                        <div class="student-name">

                            {{ $row['student']['last_name'] ?? '' }},
                            {{ $row['student']['first_name'] ?? '' }}

                            @if (!empty($row['student']['middle_name']))
                                {{ ' ' . $row['student']['middle_name'] }}
                            @endif

                        </div>

                        @if (!empty($row['student']['student_number']))

                            <div class="student-number">
                                {{ $row['student']['student_number'] }}
                            </div>

                        @endif

                    </td>


                    {{-- ========================================================== --}}
                    {{-- COMPONENT ITEMS --}}
                    {{-- ========================================================== --}}

                    @foreach ($components as $component)

                        @php
                            $componentItems = collect($items ?? [])
                                ->where('component_id', $component['id'])
                                ->values();
                        @endphp

                        @if ($componentItems->isEmpty())

                            <td class="empty">
                                -
                            </td>

                        @else

                            @foreach ($componentItems as $item)

                                @php
                                    $itemId = $item['id'];

                                    $score =
                                        $row['scores'][$itemId]
                                        ?? null;
                                @endphp

                                <td class="score">

                                    @if ($score !== null)

                                        {{ rtrim(
                                            rtrim(
                                                number_format(
                                                    (float) $score,
                                                    2,
                                                    '.',
                                                    ''
                                                ),
                                                '0'
                                            ),
                                            '.'
                                        ) }}

                                    @else

                                        <span class="empty">
                                            -
                                        </span>

                                    @endif

                                </td>

                            @endforeach

                        @endif

                    @endforeach


                    {{-- ========================================================== --}}
                    {{-- STANDALONE ASSIGNMENTS --}}
                    {{-- ========================================================== --}}

                    @foreach ($assignments as $assignment)

                        @php
                            $assignmentId =
                                $assignment['id'];

                            $assignmentGrade =
                                $row['assignment_grades'][$assignmentId]
                                ?? null;
                        @endphp

                        <td class="score">

                            @if (
                                $assignmentGrade !== null &&
                                isset($assignmentGrade['score'])
                            )

                                {{ rtrim(
                                    rtrim(
                                        number_format(
                                            (float) $assignmentGrade['score'],
                                            2,
                                            '.',
                                            ''
                                        ),
                                        '0'
                                    ),
                                    '.'
                                ) }}

                            @else

                                <span class="empty">
                                    -
                                </span>

                            @endif

                        </td>

                    @endforeach


                    {{-- ========================================================== --}}
                    {{-- MIDTERM --}}
                    {{-- ========================================================== --}}

                    <td class="grade-value">

                        @if ($row['midterm_final_grade'] !== null)

                            {{ number_format(
                                (float) $row['midterm_final_grade'],
                                2
                            ) }}

                        @elseif ($row['midterm_grade'] !== null)

                            {{ number_format(
                                (float) $row['midterm_grade'],
                                2
                            ) }}

                        @else

                            <span class="empty">
                                -
                            </span>

                        @endif

                    </td>


                    {{-- ========================================================== --}}
                    {{-- FINALS --}}
                    {{-- ========================================================== --}}

                    <td class="grade-value">

                        @if ($row['finals_final_grade'] !== null)

                            {{ number_format(
                                (float) $row['finals_final_grade'],
                                2
                            ) }}

                        @elseif ($row['finals_grade'] !== null)

                            {{ number_format(
                                (float) $row['finals_grade'],
                                2
                            ) }}

                        @else

                            <span class="empty">
                                -
                            </span>

                        @endif

                    </td>


                    {{-- ========================================================== --}}
                    {{-- TOTAL --}}
                    {{-- ========================================================== --}}

                    <td class="grade-value">

                        @if ($row['total_grade'] !== null)

                            {{ number_format(
                                (float) $row['total_grade'],
                                2
                            ) }}

                        @else

                            <span class="empty">
                                -
                            </span>

                        @endif

                    </td>


                    {{-- ========================================================== --}}
                    {{-- FINAL TRANSMUTED GRADE --}}
                    {{-- ========================================================== --}}

                    <td class="grade-value">

                        @if ($row['final_grade'] !== null)

                            {{ number_format(
                                (float) $row['final_grade'],
                                2
                            ) }}

                        @else

                            <span class="empty">
                                -
                            </span>

                        @endif

                    </td>

                </tr>

            @empty

                <tr>

                    <td
                        colspan="100"
                        style="padding: 15px;"
                    >
                        No enrolled students found.
                    </td>

                </tr>

            @endforelse

        </tbody>

    </table>


    {{-- ====================================================================== --}}
    {{-- FOOTER / SIGNATURE --}}
    {{-- ====================================================================== --}}

    <div class="footer">

        <table>

            <tr>

                <td width="50%">

                    <div class="signature">

                        <div class="signature-line"></div>

                        <strong>
                            {{ $section['faculty']['name'] ?? '' }}
                        </strong>

                        <br>

                        <small>
                            Faculty
                        </small>

                    </div>

                </td>


                <td width="50%">

                    <div class="signature">

                        <div class="signature-line"></div>

                        <strong>
                            Department / Program Chair
                        </strong>

                        <br>

                        <small>
                            Signature
                        </small>

                    </div>

                </td>

            </tr>

        </table>

    </div>


    {{-- ====================================================================== --}}
    {{-- PAGE NUMBER --}}
    {{-- ====================================================================== --}}

    <div class="page-number">

        Page
        <script type="text/php">
            if (isset($pdf)) {
                $pdf->page_text(
                    750,
                    570,
                    "Page {PAGE_NUM} of {PAGE_COUNT}",
                    null,
                    7
                );
            }
        </script>

    </div>

</body>
</html>