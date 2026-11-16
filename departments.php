
<?php

session_start();

require_once "db.php";


/* =========================
   SECURITY
========================= */

if (!isset($_SESSION["user_id"])) {

    header("Location: login.php");
    exit;

}

if ($_SESSION["role"] !== "admin") {

    header("Location: login.php");
    exit;

}


$full_name = $_SESSION["full_name"] ?? "Administrator";

$message = "";
$error = "";


/* =========================
   ADD DEPARTMENT
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_department"])
) {

    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");


    if ($name === "") {

        $error = "Department name is required.";

    } else {

        $stmt = $conn->prepare(
            "INSERT INTO departments
            (name, description, status)
            VALUES (?, ?, 'active')"
        );

        $stmt->bind_param(
            "ss",
            $name,
            $description
        );


        if ($stmt->execute()) {

            $message = "Department added successfully.";

        } else {

            if ($conn->errno == 1062) {

                $error = "A department with this name already exists.";

            } else {

                $error = "Unable to add department.";

            }

        }

        $stmt->close();

    }

}


/* =========================
   EDIT DEPARTMENT
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["edit_department"])
) {

    $id = intval($_POST["id"] ?? 0);

    $name = trim($_POST["name"] ?? "");

    $description = trim(
        $_POST["description"] ?? ""
    );


    if ($id <= 0 || $name === "") {

        $error = "Please provide valid department information.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE departments
             SET name = ?, description = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "ssi",
            $name,
            $description,
            $id
        );


        if ($stmt->execute()) {

            $message = "Department updated successfully.";

        } else {

            if ($conn->errno == 1062) {

                $error = "Another department already uses this name.";

            } else {

                $error = "Unable to update department.";

            }

        }

        $stmt->close();

    }

}


/* =========================
   ACTIVATE / DEACTIVATE
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["toggle_status"])
) {

    $id = intval($_POST["id"] ?? 0);


    if ($id > 0) {

        $stmt = $conn->prepare(
            "UPDATE departments
             SET status =
                 CASE
                     WHEN status = 'active'
                     THEN 'inactive'
                     ELSE 'active'
                 END
             WHERE id = ?"
        );

        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {

            $message = "Department status updated.";

        } else {

            $error = "Unable to update department status.";

        }

        $stmt->close();

    }

}


/* =========================
   DELETE DEPARTMENT
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_department"])
) {

    $id = intval($_POST["id"] ?? 0);


    if ($id > 0) {

        $stmt = $conn->prepare(
            "DELETE FROM departments
             WHERE id = ?"
        );

        $stmt->bind_param(
            "i",
            $id
        );


        if ($stmt->execute()) {

            $message = "Department deleted successfully.";

        } else {

            $error = "Unable to delete department.";

        }

        $stmt->close();

    }

}


/* =========================
   SEARCH
========================= */

$search = trim(
    $_GET["search"] ?? ""
);


if ($search !== "") {

    $stmt = $conn->prepare(
        "SELECT *
         FROM departments
         WHERE name LIKE ?
         OR description LIKE ?
         ORDER BY id DESC"
    );

    $search_value = "%" . $search . "%";

    $stmt->bind_param(
        "ss",
        $search_value,
        $search_value
    );

    $stmt->execute();

    $departments = $stmt
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

} else {

    $result = $conn->query(
        "SELECT *
         FROM departments
         ORDER BY id DESC"
    );

    $departments = $result
        ? $result->fetch_all(MYSQLI_ASSOC)
        : [];

}


/* =========================
   STATISTICS
========================= */

$total_departments = count($departments);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Departments | SmartHospital
</title>


<style>

* {

    margin: 0;

    padding: 0;

    box-sizing: border-box;

}


body {

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    background: #f5f9fa;

    color: #17324d;

}


/* =========================
   SIDEBAR
========================= */

.sidebar {

    position: fixed;

    left: 0;

    top: 0;

    width: 245px;

    height: 100vh;

    background: #102d44;

    color: white;

    padding: 25px 18px;

}


.logo {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 0 10px 30px;

    border-bottom:
        1px solid
        rgba(255,255,255,0.10);

    color: white;

    text-decoration: none;

    font-size: 20px;

    font-weight: 700;

}


.logo-icon {

    width: 38px;

    height: 38px;

    background: #087f8c;

    border-radius: 10px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 21px;

}


.menu {

    margin-top: 30px;

}


.menu-title {

    color: #8fa5b5;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: 1px;

    padding: 0 12px;

    margin-bottom: 12px;

}


.menu a {

    display: flex;

    align-items: center;

    gap: 12px;

    color: #d9e4ea;

    text-decoration: none;

    padding: 13px 12px;

    margin-bottom: 5px;

    border-radius: 8px;

    font-size: 14px;

}


.menu a:hover,
.menu a.active {

    background: #087f8c;

    color: white;

}


.menu-icon {

    width: 22px;

    text-align: center;

}


.logout {

    position: absolute;

    bottom: 25px;

    left: 18px;

    right: 18px;

}


.logout a {

    display: block;

    text-align: center;

    padding: 11px;

    border-radius: 8px;

    background:
        rgba(255,255,255,0.08);

    color: #dce7ed;

    text-decoration: none;

    font-size: 13px;

}


.logout a:hover {

    background: #b42318;

    color: white;

}


/* =========================
   MAIN
========================= */

.main {

    margin-left: 245px;

    min-height: 100vh;

}


.topbar {

    height: 76px;

    background: white;

    border-bottom:
        1px solid #e8eef1;

    padding: 0 35px;

    display: flex;

    align-items: center;

    justify-content: space-between;

}


.page-title h1 {

    font-size: 21px;

}


.page-title p {

    margin-top: 3px;

    color: #8494a0;

    font-size: 12px;

}


.admin-profile {

    display: flex;

    align-items: center;

    gap: 11px;

}


.avatar {

    width: 38px;

    height: 38px;

    border-radius: 50%;

    background: #e8f7f8;

    color: #087f8c;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 700;

}


.admin-info strong {

    display: block;

    font-size: 13px;

}


.admin-info span {

    color: #8a99a4;

    font-size: 11px;

}


/* =========================
   CONTENT
========================= */

.content {

    padding: 32px 35px;

}


.page-heading {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;

}


.page-heading h2 {

    font-size: 24px;

    margin-bottom: 5px;

}


.page-heading p {

    color: #8494a0;

    font-size: 13px;

}


.add-btn {

    background: #087f8c;

    color: white;

    border: none;

    border-radius: 8px;

    padding: 12px 18px;

    font-size: 13px;

    font-weight: 600;

    cursor: pointer;

}


.add-btn:hover {

    background: #066b76;

}


/* =========================
   ALERTS
========================= */

.alert {

    padding: 12px 15px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 13px;

}


.success {

    background: #eaf8f1;

    color: #137a4b;

    border: 1px solid #c9ead9;

}


.error {

    background: #fff1f1;

    color: #b42318;

    border: 1px solid #f3c7c7;

}


/* =========================
   TOOLBAR
========================= */

.toolbar {

    background: white;

    border: 1px solid #e9eff2;

    border-radius: 12px;

    padding: 18px;

    margin-bottom: 18px;

}


.search-form {

    display: flex;

    gap: 10px;

}


.search-input {

    flex: 1;

    height: 43px;

    border: 1px solid #dce6ea;

    border-radius: 7px;

    padding: 0 13px;

    outline: none;

    font-family: inherit;

}


.search-input:focus {

    border-color: #087f8c;

}


.search-btn {

    height: 43px;

    padding: 0 20px;

    border: none;

    border-radius: 7px;

    background: #102d44;

    color: white;

    cursor: pointer;

}


.clear-btn {

    display: flex;

    align-items: center;

    padding: 0 15px;

    border-radius: 7px;

    border: 1px solid #dce6ea;

    color: #647789;

    text-decoration: none;

    font-size: 13px;

}


/* =========================
   TABLE
========================= */

.table-card {

    background: white;

    border: 1px solid #e9eff2;

    border-radius: 12px;

    overflow: hidden;

}


.table-header {

    padding: 20px 22px;

    border-bottom:
        1px solid #edf1f3;

    display: flex;

    justify-content: space-between;

}


.table-header h3 {

    font-size: 16px;

}


.table-header span {

    color: #8494a0;

    font-size: 12px;

}


table {

    width: 100%;

    border-collapse: collapse;

}


th {

    text-align: left;

    background: #f8fafb;

    color: #71818d;

    font-size: 11px;

    text-transform: uppercase;

    letter-spacing: .5px;

    padding: 14px 18px;

}


td {

    padding: 16px 18px;

    border-top:
        1px solid #edf1f3;

    font-size: 13px;

    vertical-align: middle;

}


.department-name {

    font-weight: 600;

    color: #17324d;

}


.description {

    color: #7c8d98;

    max-width: 300px;

}


.status {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 600;

}


.status.active {

    background: #eaf8f1;

    color: #137a4b;

}


.status.inactive {

    background: #fff1f1;

    color: #b42318;

}


.actions-cell {

    white-space: nowrap;

}


.action-btn {

    border: none;

    border-radius: 6px;

    padding: 7px 10px;

    font-size: 11px;

    cursor: pointer;

    margin-right: 4px;

}


.edit-btn {

    background: #e8f7f8;

    color: #087f8c;

}


.toggle-btn {

    background: #fff7e6;

    color: #a66a00;

}


.delete-btn {

    background: #fff1f1;

    color: #b42318;

}


.empty {

    text-align: center;

    padding: 45px;

    color: #8494a0;

}


/* =========================
   MODAL
========================= */

.modal {

    display: none;

    position: fixed;

    inset: 0;

    background:
        rgba(16,45,68,0.55);

    z-index: 500;

    align-items: center;

    justify-content: center;

    padding: 20px;

}


.modal.show {

    display: flex;

}


.modal-box {

    width: 100%;

    max-width: 480px;

    background: white;

    border-radius: 14px;

    padding: 28px;

    box-shadow:
        0 20px 60px
        rgba(0,0,0,.2);

}


.modal-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 22px;

}


.modal-header h3 {

    font-size: 18px;

}


.close {

    border: none;

    background: none;

    font-size: 24px;

    color: #8494a0;

    cursor: pointer;

}


.form-group {

    margin-bottom: 18px;

}


.form-group label {

    display: block;

    margin-bottom: 7px;

    font-size: 12px;

    font-weight: 600;

    color: #334d62;

}


.form-group input,
.form-group textarea {

    width: 100%;

    border: 1px solid #dce6ea;

    border-radius: 7px;

    padding: 11px 12px;

    outline: none;

    font-family: inherit;

    font-size: 13px;

}


.form-group input {

    height: 43px;

}


.form-group textarea {

    min-height: 90px;

    resize: vertical;

}


.form-group input:focus,
.form-group textarea:focus {

    border-color: #087f8c;

}


.modal-actions {

    display: flex;

    justify-content: flex-end;

    gap: 10px;

    margin-top: 22px;

}


.cancel-btn {

    border: 1px solid #dce6ea;

    background: white;

    color: #647789;

    padding: 10px 17px;

    border-radius: 7px;

    cursor: pointer;

}


.save-btn {

    border: none;

    background: #087f8c;

    color: white;

    padding: 10px 18px;

    border-radius: 7px;

    cursor: pointer;

    font-weight: 600;

}


/* =========================
   MOBILE
========================= */

@media (max-width: 800px) {

    .sidebar {

        width: 70px;

        padding: 20px 10px;

    }

    .logo {

        justify-content: center;

        padding: 0 0 20px;

    }

    .logo span:last-child {

        display: none;

    }

    .menu-title,
    .menu a span:not(.menu-icon),
    .logout a {

        display: none;

    }

    .menu a {

        justify-content: center;

    }

    .main {

        margin-left: 70px;

    }

    .topbar {

        padding: 0 20px;

    }

    .admin-info {

        display: none;

    }

    .content {

        padding: 22px 15px;

    }

    .page-heading {

        align-items: flex-start;

        gap: 15px;

        flex-direction: column;

    }

    .search-form {

        flex-wrap: wrap;

    }

    .search-input {

        width: 100%;

        flex-basis: 100%;

    }

    .table-card {

        overflow-x: auto;

    }

    table {

        min-width: 750px;

    }

}

</style>

</head>


<body>


<!-- =========================
     SIDEBAR
========================= -->

<aside class="sidebar">


    <a
        href="admin_dashboard.php"
        class="logo"
    >

        <span class="logo-icon">
            +
        </span>

        <span>
            SmartHospital
        </span>

    </a>


    <div class="menu">


        <div class="menu-title">
            Main Menu
        </div>


        <a href="admin_dashboard.php">

            <span class="menu-icon">
                ⌂
            </span>

            <span>
                Dashboard
            </span>

        </a>


        <a href="users.php">

            <span class="menu-icon">
                👥
            </span>

            <span>
                Users
            </span>

        </a>


        <a
            href="departments.php"
            class="active"
        >

            <span class="menu-icon">
                🏥
            </span>

            <span>
                Departments
            </span>

        </a>


        <a href="#">

            <span class="menu-icon">
                🩺
            </span>

            <span>
                Doctors
            </span>

        </a>


        <a href="#">

            <span class="menu-icon">
                🧑‍⚕️
            </span>

            <span>
                Nurses
            </span>

        </a>


        <div
            class="menu-title"
            style="margin-top:25px;"
        >
            Management
        </div>


        <a href="#">

            <span class="menu-icon">
                🎫
            </span>

            <span>
                Queue
            </span>

        </a>


        <a href="#">

            <span class="menu-icon">
                📊
            </span>

            <span>
                Reports
            </span>

        </a>


        <a href="#">

            <span class="menu-icon">
                ⚙
            </span>

            <span>
                Settings
            </span>

        </a>


    </div>


    <div class="logout">

        <a href="logout.php">
            Logout
        </a>

    </div>


</aside>



<!-- =========================
     MAIN
========================= -->

<div class="main">


    <header class="topbar">


        <div class="page-title">

            <h1>
                Department Management
            </h1>

            <p>
                SmartHospital Queue Management System
            </p>

        </div>


        <div class="admin-profile">


            <div class="avatar">

                <?= strtoupper(
                    substr(
                        $full_name,
                        0,
                        1
                    )
                ) ?>

            </div>


            <div class="admin-info">

                <strong>

                    <?= htmlspecialchars(
                        $full_name
                    ) ?>

                </strong>

                <span>
                    System Administrator
                </span>

            </div>


        </div>


    </header>



    <main class="content">


        <!-- PAGE HEADING -->

        <section class="page-heading">


            <div>

                <h2>
                    Departments
                </h2>

                <p>
                    Create and manage hospital departments.
                </p>

            </div>


            <button
                class="add-btn"
                onclick="openAddModal()"
            >

                + Add Department

            </button>


        </section>



        <!-- ALERT -->

        <?php if ($message !== ""): ?>

            <div class="alert success">

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="alert error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>



        <!-- SEARCH -->

        <section class="toolbar">


            <form
                method="GET"
                class="search-form"
            >


                <input
                    type="text"
                    name="search"
                    class="search-input"
                    placeholder="Search department name or description..."
                    value="<?= htmlspecialchars($search) ?>"
                >


                <button
                    type="submit"
                    class="search-btn"
                >
                    Search
                </button>


                <?php if ($search !== ""): ?>

                    <a
                        href="departments.php"
                        class="clear-btn"
                    >
                        Clear
                    </a>

                <?php endif; ?>


            </form>


        </section>



        <!-- TABLE -->

        <section class="table-card">


            <div class="table-header">

                <h3>
                    Hospital Departments
                </h3>

                <span>
                    <?= $total_departments ?>
                    department(s)
                </span>

            </div>



            <?php if (count($departments) > 0): ?>


                <table>


                    <thead>

                        <tr>

                            <th>
                                Department
                            </th>

                            <th>
                                Description
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Created
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $departments
                        as $department
                    ): ?>


                        <tr>


                            <td>

                                <div class="department-name">

                                    🏥
                                    <?= htmlspecialchars(
                                        $department["name"]
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <div class="description">

                                    <?= htmlspecialchars(
                                        $department["description"]
                                        ?: "No description"
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <span
                                    class="status
                                    <?= $department["status"] === "active"
                                        ? "active"
                                        : "inactive"
                                    ?>"
                                >

                                    <?= ucfirst(
                                        $department["status"]
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= date(
                                    "d M Y",
                                    strtotime(
                                        $department["created_at"]
                                    )
                                ) ?>

                            </td>


                            <td class="actions-cell">


                                <!-- EDIT -->

                                <button
                                    class="action-btn edit-btn"
                                    onclick='openEditModal(
                                        <?= json_encode(
                                            $department,
                                            JSON_HEX_TAG |
                                            JSON_HEX_APOS |
                                            JSON_HEX_QUOT |
                                            JSON_HEX_AMP
                                        ) ?>
                                    )'
                                >

                                    Edit

                                </button>



                                <!-- TOGGLE -->

                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="
                                        return confirm(
                                            'Change this department status?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= $department["id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="toggle_status"
                                        class="action-btn toggle-btn"
                                    >

                                        <?= $department["status"] === "active"
                                            ? "Deactivate"
                                            : "Activate"
                                        ?>

                                    </button>

                                </form>



                                <!-- DELETE -->

                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="
                                        return confirm(
                                            'Are you sure you want to permanently delete this department?'
                                        );
                                    "
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?= $department["id"] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="delete_department"
                                        class="action-btn delete-btn"
                                    >

                                        Delete

                                    </button>

                                </form>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            <?php else: ?>


                <div class="empty">

                    No departments found.

                </div>


            <?php endif; ?>


        </section>


    </main>


</div>



<!-- =========================
     ADD MODAL
========================= -->

<div
    class="modal"
    id="addModal"
>


    <div class="modal-box">


        <div class="modal-header">

            <h3>
                Add Department
            </h3>

            <button
                class="close"
                onclick="closeAddModal()"
            >
                ×
            </button>

        </div>


        <form
            method="POST"
        >


            <div class="form-group">

                <label>
                    Department Name
                </label>

                <input
                    type="text"
                    name="name"
                    placeholder="e.g. Cardiology"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    placeholder="Describe the department..."
                ></textarea>

            </div>


            <div class="modal-actions">


                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeAddModal()"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    name="add_department"
                    class="save-btn"
                >

                    Add Department

                </button>


            </div>


        </form>


    </div>


</div>



<!-- =========================
     EDIT MODAL
========================= -->

<div
    class="modal"
    id="editModal"
>


    <div class="modal-box">


        <div class="modal-header">

            <h3>
                Edit Department
            </h3>

            <button
                class="close"
                onclick="closeEditModal()"
            >
                ×
            </button>

        </div>


        <form method="POST">


            <input
                type="hidden"
                name="id"
                id="edit_id"
            >


            <div class="form-group">

                <label>
                    Department Name
                </label>

                <input
                    type="text"
                    name="name"
                    id="edit_name"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Description
                </label>

                <textarea
                    name="description"
                    id="edit_description"
                ></textarea>

            </div>


            <div class="modal-actions">


                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeEditModal()"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    name="edit_department"
                    class="save-btn"
                >

                    Save Changes

                </button>


            </div>


        </form>


    </div>


</div>



<script>

/* =========================
   ADD MODAL
========================= */

function openAddModal() {

    document
        .getElementById("addModal")
        .classList.add("show");

}


function closeAddModal() {

    document
        .getElementById("addModal")
        .classList.remove("show");

}


/* =========================
   EDIT MODAL
========================= */

function openEditModal(department) {

    document.getElementById("edit_id").value =
        department.id;

    document.getElementById("edit_name").value =
        department.name;

    document.getElementById("edit_description").value =
        department.description || "";


    document
        .getElementById("editModal")
        .classList.add("show");

}


function closeEditModal() {

    document
        .getElementById("editModal")
        .classList.remove("show");

}


/* =========================
   CLOSE WHEN CLICKING
   OUTSIDE MODAL
========================= */

window.addEventListener(
    "click",
    function(event) {

        const addModal =
            document.getElementById("addModal");

        const editModal =
            document.getElementById("editModal");


        if (event.target === addModal) {

            closeAddModal();

        }


        if (event.target === editModal) {

            closeEditModal();

        }

    }
);

</script>


</body>

</html>
```
