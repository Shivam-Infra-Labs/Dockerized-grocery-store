<!DOCTYPE html>
<html>
<head>
<title>Grocery Store</title>
</head>
<body>

<?php
require_once 'dbcon.php';   // 🔥 IMPORTANT: __DIR__ हटाओ
require_once 'header.php';
/** @var mysqli $conn */

$msg = '';

// 🔥 DEBUG (optional - check connection)
if (!$conn) {
    die("Database connection not working");
}

if (isset($_POST['submit'])) {

    if (!empty($_POST['Name']) && !empty($_POST['Mobile']) && !empty($_POST['msg'])) {

        $n = mysqli_real_escape_string($conn, trim($_POST['Name']));
        $m = mysqli_real_escape_string($conn, trim($_POST['Mobile']));
        $p = mysqli_real_escape_string($conn, trim($_POST['msg']));

        $sql = "INSERT INTO feedback (`name`, `mobile`, `msg`, `uid`) 
                VALUES ('$n', '$m', '$p', NULL)";

        if ($conn->query($sql)) {
            $msg = "Feedback Saved ✅";
        } else {
            $msg = "Error: " . $conn->error;
        }

    } else {
        $msg = "All fields are required ❌";
    }
}
?>

<div class="w3l_banner_nav_right">

<div class="mail">
    <h3>Mail Us</h3>

    <div class="agileinfo_mail_grids">

        <div class="col-md-4 agileinfo_mail_grid_left">
            <ul>
                <li>address<span>Asoda</span></li>
            </ul>
            <ul>
                <li>email<span>mrs228891@gmail.com</span></li>
            </ul>
            <ul>
                <li>call<span>8317732570</span></li>
            </ul>
        </div>

        <div class="col-md-8 agileinfo_mail_grid_right">

            <form method="post">

                <div style="color: green;">
                    <?php echo $msg; ?>
                </div>

                <div class="col-md-6">
                    <input type="text" name="Name" placeholder="Name" required>
                </div>

                <div class="col-md-6">
                    <input type="text" name="Mobile" placeholder="Mobile" required>
                </div>

                <div class="clearfix"></div>

                <textarea name="msg" placeholder="Message..." required></textarea>

                <input type="submit" name="submit" value="Submit">
                <input type="reset" value="Clear">

            </form>

        </div>

        <div class="clearfix"></div>
    </div>
</div>

</div>

<?php include 'footer.php'; ?>

</body>
</html>