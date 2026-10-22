<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/certifications-data.php';

secure_session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$user = authenticated_user();

if (!$user) {
    redirect_to('index.php?page=login');
}

if (($user['role'] ?? '') !== 'hr') {
    http_response_code(403);
    exit('Access denied. HR only.');
}

$programId = (int) ($_GET['program_id'] ?? 0);

if ($programId < 1) {
    http_response_code(400);
    exit('Training program is required.');
}

try {
    $companyId = cert_hr_company_id(
        (int) $user['id']
    );

    $program = cert_program_for_company(
        $companyId,
        $programId
    );

    $sessions = cert_sessions_for_program(
        $programId
    );

    if (!$sessions) {
        throw new InvalidArgumentException(
            'This training program has no meetings.'
        );
    }

    $employeeStmt = database()->prepare(
        "SELECT
            e.employee_code,
            e.full_name
         FROM training_applications ta
         JOIN employees e
           ON e.id = ta.employee_id
          AND e.company_id = ?
          AND e.employment_status = 'active'
         WHERE ta.program_id = ?
           AND ta.status = 'joined'
         ORDER BY e.full_name, e.employee_code"
    );

    $employeeStmt->execute([
        $companyId,
        $programId,
    ]);

    $employees = $employeeStmt->fetchAll(
        PDO::FETCH_ASSOC
    );
} catch (Throwable $ex) {
    error_log(
        'Attendance template generation failed: '
        . $ex->getMessage()
    );

    http_response_code(400);
    exit('Unable to generate attendance template.');
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('PHP ZipArchive extension is required.');
}

function cert_template_xml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_XML1 | ENT_QUOTES,
        'UTF-8'
    );
}

function cert_template_column_name(int $number): string
{
    $name = '';

    while ($number > 0) {
        $number--;
        $name =
            chr(65 + ($number % 26))
            . $name;
        $number = intdiv($number, 26);
    }

    return $name;
}

$headers = cert_expected_attendance_headers(
    $sessions
);

$lastColumn =
    cert_template_column_name(
        count($headers)
    );

$lastEmployeeRow =
    max(2, count($employees) + 1);

$rowsXml = [];

$headerCells = [];

foreach ($headers as $index => $header) {
    $column =
        cert_template_column_name(
            $index + 1
        );

    $headerCells[] =
        '<c r="'
        . $column
        . '1" t="inlineStr" s="1"><is><t>'
        . cert_template_xml($header)
        . '</t></is></c>';
}

$rowsXml[] =
    '<row r="1" ht="28" customHeight="1">'
    . implode('', $headerCells)
    . '</row>';

$rowNumber = 2;

foreach ($employees as $employee) {
    $cells = [];

    $cells[] =
        '<c r="A'
        . $rowNumber
        . '" t="inlineStr" s="2"><is><t>'
        . cert_template_xml(
            (string) $employee['employee_code']
        )
        . '</t></is></c>';

    $cells[] =
        '<c r="B'
        . $rowNumber
        . '" t="inlineStr" s="2"><is><t>'
        . cert_template_xml(
            (string) $employee['full_name']
        )
        . '</t></is></c>';

    foreach ($sessions as $sessionIndex => $session) {
        $column =
            cert_template_column_name(
                $sessionIndex + 3
            );

        $cells[] =
            '<c r="'
            . $column
            . $rowNumber
            . '" t="inlineStr" s="3"><is><t></t></is></c>';
    }

    $rowsXml[] =
        '<row r="'
        . $rowNumber
        . '" ht="23" customHeight="1">'
        . implode('', $cells)
        . '</row>';

    $rowNumber++;
}

if (!$employees) {
    $rowsXml[] =
        '<row r="2" ht="23" customHeight="1">'
        . '<c r="A2" t="inlineStr" s="2"><is><t></t></is></c>'
        . '<c r="B2" t="inlineStr" s="2"><is><t></t></is></c>'
        . '</row>';
}

$meetingStartColumn = 'C';
$meetingEndColumn = $lastColumn;

$dataValidationRange =
    $meetingStartColumn
    . '2:'
    . $meetingEndColumn
    . max(501, $lastEmployeeRow);

$sheetXml =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<sheetViews><sheetView workbookViewId="0">'
    . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
    . '</sheetView></sheetViews>'
    . '<sheetFormatPr defaultRowHeight="20"/>'
    . '<cols>'
    . '<col min="1" max="1" width="18" customWidth="1"/>'
    . '<col min="2" max="2" width="30" customWidth="1"/>'
    . '<col min="3" max="40" width="22" customWidth="1"/>'
    . '</cols>'
    . '<sheetData>'
    . implode('', $rowsXml)
    . '</sheetData>'
    . '<autoFilter ref="A1:'
    . $lastColumn
    . max(1, $lastEmployeeRow)
    . '"/>'
    . '<dataValidations count="1">'
    . '<dataValidation type="list" allowBlank="0" showErrorMessage="1" errorTitle="Invalid attendance" error="Choose Present or Absent." sqref="'
    . $dataValidationRange
    . '">'
    . '<formula1>&quot;Present,Absent&quot;</formula1>'
    . '</dataValidation>'
    . '</dataValidations>'
    . '</worksheet>';

$workbookXml =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
    . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets>'
    . '<sheet name="Attendance" sheetId="1" r:id="rId1"/>'
    . '</sheets>'
    . '</workbook>';

$stylesXml =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="2">'
    . '<font><sz val="11"/><name val="Calibri"/></font>'
    . '<font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font>'
    . '</fonts>'
    . '<fills count="3">'
    . '<fill><patternFill patternType="none"/></fill>'
    . '<fill><patternFill patternType="gray125"/></fill>'
    . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F6B45"/><bgColor indexed="64"/></patternFill></fill>'
    . '</fills>'
    . '<borders count="2">'
    . '<border><left/><right/><top/><bottom/><diagonal/></border>'
    . '<border>'
    . '<left style="thin"><color rgb="FFDCE7DF"/></left>'
    . '<right style="thin"><color rgb="FFDCE7DF"/></right>'
    . '<top style="thin"><color rgb="FFDCE7DF"/></top>'
    . '<bottom style="thin"><color rgb="FFDCE7DF"/></bottom>'
    . '<diagonal/>'
    . '</border>'
    . '</borders>'
    . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    . '<cellXfs count="4">'
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
    . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
    . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
    . '</cellXfs>'
    . '</styleSheet>';

$contentTypesXml =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    . '</Types>';

$rootRelsXml =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '</Relationships>';

$workbookRelsXml =
    '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>';

$tempFile =
    tempnam(
        sys_get_temp_dir(),
        'growflow_attendance_'
    );

if ($tempFile === false) {
    http_response_code(500);
    exit('Unable to create Excel file.');
}

$zip = new ZipArchive();

if (
    $zip->open(
        $tempFile,
        ZipArchive::CREATE
        | ZipArchive::OVERWRITE
    ) !== true
) {
    @unlink($tempFile);
    http_response_code(500);
    exit('Unable to create Excel workbook.');
}

$zip->addFromString(
    '[Content_Types].xml',
    $contentTypesXml
);

$zip->addFromString(
    '_rels/.rels',
    $rootRelsXml
);

$zip->addFromString(
    'xl/workbook.xml',
    $workbookXml
);

$zip->addFromString(
    'xl/_rels/workbook.xml.rels',
    $workbookRelsXml
);

$zip->addFromString(
    'xl/worksheets/sheet1.xml',
    $sheetXml
);

$zip->addFromString(
    'xl/styles.xml',
    $stylesXml
);

$zip->close();

$filename =
    'GrowFlow-Attendance-Program-'
    . $programId
    . '.xlsx';

header(
    'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
);

header(
    'Content-Disposition: attachment; filename="'
    . $filename
    . '"'
);

header(
    'Content-Length: '
    . (string) filesize($tempFile)
);

readfile($tempFile);

@unlink($tempFile);
exit;
