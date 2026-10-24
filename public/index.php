<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/departments-data.php';
require_once __DIR__ . '/../app/core/employee-account.php';

secure_session_start();

// ==========================================
// SECURITY HEADERS
// ==========================================

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');

// ==========================================
// PAGE ROUTING
// ==========================================

$action = (string) ($_GET['page'] ?? 'dashboard');

$allowed = [
    'dashboard',
    'training',
    'available-training',
    'performance',
    'employees',
    'departments',
    'managers',
    'analytics',
    'points-badges',
    'login',
    'logout'
];

if (!in_array($action, $allowed, true)) {
    http_response_code(404);
    $action = 'not-found';
}

$error = '';

// ==========================================
// LOGOUT
// ==========================================

if ($action === 'logout') {

    if (
        $_SERVER['REQUEST_METHOD'] !== 'POST'
        || !csrf_valid($_POST['csrf'] ?? null)
    ) {
        http_response_code(405);
        exit('Invalid logout request.');
    }

    logout_user();
    redirect_to('?page=login');
    exit;
}

// ==========================================
// LOGIN
// ==========================================

    if ($action === 'login') {

    if (authenticated_user()) {

        $loggedInUser =
            current_user();

        if (
            ($loggedInUser['role'] ?? '')
            === 'employee'
        ) {
            redirect_to(
                'employee.php?module=home'
            );
        }

        redirect_to(
            '?page=dashboard'
        );
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!csrf_valid($_POST['csrf'] ?? null)) {

            $error = 'Session expired. Refresh the page and try again.';

        } elseif (
            time() < (int) ($_SESSION['blocked_until'] ?? 0)
        ) {

            $error = 'Too many attempts. Please try again in a few minutes.';

        } else {

            $email = filter_var(
                trim((string) ($_POST['email'] ?? '')),
                FILTER_VALIDATE_EMAIL
            );

            $password = (string) ($_POST['password'] ?? '');

            if (
                is_string($email)
                && strlen($password) <= 1024
            ) {

                try {

                    if (login_user($email, $password)) {

                        $loggedInUser = current_user();

                        if (
                            ($loggedInUser['role'] ?? '')
                            === 'employee'
                        ) {
                            redirect_to(
                                'employee.php?module=home'
                            );
                        }

                        redirect_to(
                            '?page=dashboard'
                        );
                    }

                    if (
                    employee_activation_begin(
                        $email,
                        $password
                    )
                ) {
                    redirect_to(
                        'activate-account.php'
                    );
                }

                } catch (PDOException $ex) {

                    error_log(
                        'Login database error: '
                        . $ex->getMessage()
                    );

                    http_response_code(503);

                    $error =
                        'Database connection unavailable. Contact your administrator.';

                    require __DIR__ . '/../app/pages/login.php';
                    exit;
                }
            }

            $_SESSION['attempts'] =
                (int) ($_SESSION['attempts'] ?? 0) + 1;

            if ($_SESSION['attempts'] >= 5) {

                $_SESSION['blocked_until'] = time() + 300;
                $_SESSION['attempts'] = 0;
            }

            $error = 'Invalid email or password.';
        }
    }

    require __DIR__ . '/../app/pages/login.php';
    exit;
}

// ==========================================
// AUTHENTICATION
// ==========================================

$user = authenticated_user();

if (!$user) {
    redirect_to('?page=login');
    exit;
}

if ($action === 'not-found') {
    http_response_code(404);
    exit('Page not found.');
}

// ==========================================
// HR EMPLOYEE MANAGEMENT
// ==========================================

if ($action === 'employees') {

    if (($user['role'] ?? '') !== 'hr') {
        http_response_code(403);
        exit('Access denied. HR only.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!csrf_valid($_POST['csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }

        $employeeAction =
            (string) ($_POST['employee_action'] ?? '');

        if ($employeeAction !== 'create') {
            http_response_code(400);
            exit('Invalid employee action.');
        }

        require_once __DIR__
            . '/../app/core/employees-data.php';

        try {

            $result = hr_create_employee(
                (int) $user['id'],
                $_POST
            );

            $_SESSION['employee_created'] = $result;

            unset($_SESSION['employee_error']);

        } catch (InvalidArgumentException $ex) {

            $_SESSION['employee_error'] =
                $ex->getMessage();

        } catch (Throwable $ex) {

            error_log(
                'Employee creation failed: '
                . $ex->getMessage()
            );

            $_SESSION['employee_error'] =
                'Unable to create employee. Check the server logs.';
        }

        redirect_to('?page=employees');
        exit;
    }
}

// ==========================================
// HR DEPARTMENTS MANAGEMENT
// ==========================================

if ($action === 'departments') {

    if (($user['role'] ?? '') !== 'hr') {
        http_response_code(403);
        exit('Access denied. HR only.');
    }

    require_once __DIR__
        . '/../app/core/departments-data.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!csrf_valid($_POST['csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }

        // Clear previous messages before a new action.
        unset(
            $_SESSION['dept_message'],
            $_SESSION['dept_error']
        );

        $deptAction =
            (string) ($_POST['department_action'] ?? '');

        try {

            // ----------------------------------
            // CREATE DEPARTMENT
            // ----------------------------------

            if ($deptAction === 'create') {

                dept_new(
                    (int) $user['id'],
                    $_POST
                );

                $_SESSION['dept_message'] =
                    'Department and job titles saved successfully.';

            // ----------------------------------
            // ADD JOB TITLE
            // ----------------------------------

            } elseif ($deptAction === 'position') {

                dept_add_position(
                    (int) $user['id'],
                    $_POST
                );

                $_SESSION['dept_message'] =
                    'Job title added successfully.';

            // ----------------------------------
            // PREVIEW EXCEL IMPORT
            // ----------------------------------

            } elseif ($deptAction === 'preview') {

                $file = $_FILES['excel_file'] ?? null;

                if (
                    !$file
                    || ($file['error'] ?? UPLOAD_ERR_NO_FILE)
                        !== UPLOAD_ERR_OK
                    || ($file['size'] ?? 0) > 2097152
                    || ($file['size'] ?? 0) < 1
                    || strtolower(
                        pathinfo(
                            $file['name'] ?? '',
                            PATHINFO_EXTENSION
                        )
                    ) !== 'xlsx'
                    || !is_uploaded_file($file['tmp_name'])
                ) {
                    throw new InvalidArgumentException(
                        'Choose a valid .xlsx file (maximum 2 MB).'
                    );
                }

                // Remove any previous preview.
                unset($_SESSION['dept_preview']);

                $rows = dept_parse_xlsx(
                    $file['tmp_name']
                );

                $_SESSION['dept_preview'] =
                    dept_validate_import(
                        dept_company_id((int) $user['id']),
                        $rows
                    );

                $_SESSION['dept_message'] =
                    'Excel validated. Review the records before saving.';

            // ----------------------------------
            // CANCEL EXCEL IMPORT - FIXED
            // ----------------------------------

            } elseif ($deptAction === 'cancel_import') {

                // Discard preview without DB changes.
                unset(
                    $_SESSION['dept_preview'],
                    $_SESSION['dept_error']
                );

                $_SESSION['dept_message'] =
                    'Import cancelled. No records were saved.';

            // ----------------------------------
            // SAVE EXCEL TO DATABASE
            // ----------------------------------

            } elseif ($deptAction === 'import') {

                $rows = $_SESSION['dept_preview'] ?? null;

                if (!is_array($rows) || !$rows) {

                    throw new InvalidArgumentException(
                        'Preview your Excel workbook first.'
                    );
                }

                // Service must validate again and
                // save within a database transaction.
                $result = dept_import_commit(
                    (int) $user['id'],
                    $rows
                );

                // Clear preview after successful import.
                unset($_SESSION['dept_preview']);

                $_SESSION['dept_message'] =
                    'Imported '
                    . $result['departments']
                    . ' departments and '
                    . $result['positions']
                    . ' job titles successfully.';

            // ----------------------------------
            // INVALID ACTION
            // ----------------------------------

            } else {

                throw new InvalidArgumentException(
                    'Unknown department action.'
                );
            }

        } catch (InvalidArgumentException $ex) {

            $_SESSION['dept_error'] =
                $ex->getMessage();

        } catch (Throwable $ex) {

            error_log(
                'Department action failed: '
                . $ex->getMessage()
            );

            $_SESSION['dept_error'] =
                'Could not complete the request. Check the PHP error log.';
        }

        // Prevent duplicate submissions on refresh.
        redirect_to('?page=departments');
        exit;
    }
}


// ==========================================
// HR ANALYTICS
// ==========================================

if ($action === 'analytics') {
    if (($user['role'] ?? '') !== 'hr') {
        http_response_code(403);
        exit('Access denied. HR only.');
    }
}
// ==========================================
// HR POINTS & BADGES
// ==========================================

if ($action === 'points-badges') {
    if (($user['role'] ?? '') !== 'hr') {
        http_response_code(403);
        exit('Access denied. HR only.');
    }

    require_once __DIR__
        . '/../app/core/points-badges-data.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_valid($_POST['csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }

        unset(
            $_SESSION['points_badges_message'],
            $_SESSION['points_badges_error']
        );

        try {
            $pbAction = (string)($_POST['points_badges_action'] ?? '');

            if ($pbAction === 'award_points') {
                $_SESSION['points_badges_message'] =
                    pb_award_points((int)$user['id'], $_POST);
            } elseif ($pbAction === 'award_badge') {
                $_SESSION['points_badges_message'] =
                    pb_award_badge((int)$user['id'], $_POST);
            } elseif ($pbAction === 'create_badge') {
                $_SESSION['points_badges_message'] =
                    pb_create_badge((int)$user['id'], $_POST);
            } else {
                throw new InvalidArgumentException(
                    'Unknown Points & Badges action.'
                );
            }
        } catch (InvalidArgumentException $ex) {
            $_SESSION['points_badges_error'] = $ex->getMessage();
        } catch (Throwable $ex) {
            error_log(
                'Points & Badges action failed: '
                . $ex->getMessage()
            );
            $_SESSION['points_badges_error'] =
                'Unable to post the recognition entry. Check the PHP error log.';
        }

        redirect_to('?page=points-badges');
        exit;
    }
}

// ==========================================
// HR TRAINING PROGRAM MANAGEMENT
// ==========================================

if ($action === 'performance') {

    if (($user['role'] ?? '') !== 'hr') {
        http_response_code(403);
        exit('Access denied. HR only.');
    }

    require_once __DIR__
        . '/../app/core/performance-data.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!csrf_valid($_POST['csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }

        unset(
            $_SESSION['performance_message'],
            $_SESSION['performance_error']
        );

        $performanceAction =
            (string) ($_POST['performance_action'] ?? '');

        try {

            if ($performanceAction === 'manual') {

                $saved = performance_save_manual(
                    (int) $user['id'],
                    $_POST
                );

                $_SESSION['performance_message'] =
                    'Saved '
                    . $saved['employee']
                    . ' · '
                    . $saved['month']
                    . ' · '
                    . number_format(
                        (float) $saved['score'],
                        2
                    )
                    . '%.';

            } elseif ($performanceAction === 'preview') {

    $file =
        $_FILES['excel_file']
        ?? null;

    if (
        !$file
        || ($file['error'] ?? UPLOAD_ERR_NO_FILE)
            !== UPLOAD_ERR_OK
        || ($file['size'] ?? 0) > 2097152
        || ($file['size'] ?? 0) < 1
        || strtolower(
            pathinfo(
                $file['name'] ?? '',
                PATHINFO_EXTENSION
            )
        ) !== 'xlsx'
        || !is_uploaded_file($file['tmp_name'])
    ) {
        throw new InvalidArgumentException(
            'Choose a valid .xlsx file (maximum 2 MB).'
        );
    }

    unset(
        $_SESSION['performance_preview']
    );

    $sheetRows =
        performance_parse_xlsx(
            $file['tmp_name']
        );

    $previewRows =
        performance_validate_import(
            (int) $user['id'],
            $sheetRows
        );

    $_SESSION['performance_preview'] =
        $previewRows;

    $validCount = 0;
    $invalidCount = 0;

    foreach ($previewRows as $previewRow) {
        if (!empty($previewRow['valid'])) {
            $validCount++;
        } else {
            $invalidCount++;
        }
    }

    $_SESSION['performance_message'] =
        'Excel validated. '
        . $validCount
        . ' valid row'
        . ($validCount === 1 ? '' : 's')
        . (
            $invalidCount > 0
                ? ' and '
                    . $invalidCount
                    . ' invalid row'
                    . ($invalidCount === 1 ? '' : 's')
                : ''
        )
        . '. Review the preview before saving.';

    // Force the session data to be written before the 303 redirect.
    // This is safe on localhost and Railway.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    header(
        'Location: ?page=performance&preview=1#performancePreview',
        true,
        303
    );
    exit;

                    

            } elseif ($performanceAction === 'cancel_import') {

                unset($_SESSION['performance_preview']);

                $_SESSION['performance_message'] =
                    'Import cancelled. No performance ratings were saved.';

            } elseif ($performanceAction === 'import') {

                $preview =
                    $_SESSION['performance_preview']
                    ?? null;

                if (
                    !is_array($preview)
                    || !$preview
                ) {
                    throw new InvalidArgumentException(
                        'Preview your Excel workbook first.'
                    );
                }

                $result =
                    performance_import_commit(
                        (int) $user['id'],
                        $preview
                    );

                unset($_SESSION['performance_preview']);

                $_SESSION['performance_message'] =
                    'Saved '
                    . $result['saved']
                    . ' monthly rating'
                    . (
                        $result['saved'] === 1
                            ? ''
                            : 's'
                    )
                    . '.';

            } else {

                throw new InvalidArgumentException(
                    'Unknown performance action.'
                );
            }

        } catch (InvalidArgumentException $ex) {

            $_SESSION['performance_error'] =
                $ex->getMessage();

        } catch (Throwable $ex) {

            error_log(
                'Performance action failed: '
                . $ex->getMessage()
            );

            $_SESSION['performance_error'] =
                'Could not complete the performance request. Check the PHP error log.';
        }

        redirect_to('?page=performance');
        exit;
    }
}

if ($action === 'managers') {

    if (($user['role'] ?? '') !== 'hr') {
        http_response_code(403);
        exit('Access denied. HR only.');
    }

    require_once __DIR__
        . '/../app/core/managers-data.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!csrf_valid($_POST['csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }

        unset(
            $_SESSION['manager_created'],
            $_SESSION['manager_error']
        );

        $managerAction =
            (string) (
                $_POST['manager_action']
                ?? ''
            );

        try {

            if ($managerAction !== 'create') {
                throw new InvalidArgumentException(
                    'Unknown Department Head action.'
                );
            }

            $_SESSION['manager_created'] =
                manager_create(
                    (int) $user['id'],
                    $_POST
                );

        } catch (InvalidArgumentException $ex) {

            $_SESSION['manager_error'] =
                $ex->getMessage();

        } catch (Throwable $ex) {

            error_log(
                'Department Head creation failed: '
                . $ex->getMessage()
            );

            $_SESSION['manager_error'] =
                'Unable to create the Department Head account. Check the PHP error log.';
        }

        redirect_to('?page=managers');
        exit;
    }
}


if ($action === 'training') {

    if (($user['role'] ?? '') !== 'hr') {
        http_response_code(403);
        exit('Access denied. HR only.');
    }

    require_once __DIR__
        . '/../app/core/training-data.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!csrf_valid($_POST['csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }

        $trainingAction =
            (string) ($_POST['training_action'] ?? '');

        if ($trainingAction !== 'create') {
            http_response_code(400);
            exit('Invalid training action.');
        }

        try {

            $programId = training_create(
                (int) $user['id'],
                $_POST
            );

            $_SESSION['training_message'] =
                'Training program published successfully.';

            unset($_SESSION['training_error']);

        } catch (InvalidArgumentException $ex) {

            $_SESSION['training_error'] =
                $ex->getMessage();

        } catch (Throwable $ex) {

            error_log(
                'Training save failed: '
                . $ex->getMessage()
            );

            $_SESSION['training_error'] =
                'Unable to save training program. Check PHP error logs.';
        }

        redirect_to('?page=training');
        exit;
    }
}


// ==========================================
// PAGE RENDERING
// ==========================================

$page = $action;

require __DIR__
    . '/../app/partials/layout-start.php';

echo '<div class="app-layout">';

require __DIR__
    . '/../app/partials/sidebar.php';

echo '<div class="main-shell">';

require __DIR__
    . '/../app/partials/header.php';

require __DIR__
    . '/../app/pages/' . $page . '.php';

echo '</div>';
echo '</div>';

require __DIR__
    . '/../app/partials/layout-end.php';
