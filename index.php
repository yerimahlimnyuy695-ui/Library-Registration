
<?php

$conn = new mysqli("localhost", "root", "", "library_loans_db");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");


$errors = [];
$success = "";
$db_error = "";

$student_id = "";
$isbn = "";
$book_title = "";
$borrow_date = "";
$return_date = "";
$loan_duration = "";
$purpose = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // COLLECT FORM DATA
    $student_id = trim($_POST["student_id"] ?? "");
    $isbn = trim($_POST["isbn"] ?? "");
    $book_title = trim($_POST["book_title"] ?? "");
    $borrow_date = trim($_POST["borrow_date"] ?? "");
    $return_date = trim($_POST["return_date"] ?? "");
    $loan_duration = trim($_POST["loan_duration"] ?? "");
    $purpose = trim($_POST["purpose"] ?? "");

    if (empty($student_id)) {
        $errors["student_id"] = "Student ID is required.";
    }

    if (empty($isbn)) {

        $errors["isbn"] = "Book ISBN is required.";

    } else {

        $clean_isbn = preg_replace('/[\s-]/', '', $isbn);

        $valid_isbn = false;

        if (preg_match('/^[0-9]{9}[0-9X]$/i', $clean_isbn)) {

            $sum = 0;

            for ($i = 0; $i < 10; $i++) {

                $digit = strtoupper($clean_isbn[$i]);

                $digit = ($digit == "X") ? 10 : (int)$digit;

                $sum += (10 - $i) * $digit;
            }

            if ($sum % 11 == 0) {
                $valid_isbn = true;
            }
        }

        elseif (preg_match('/^[0-9]{13}$/', $clean_isbn)) {

            $sum = 0;

            for ($i = 0; $i < 13; $i++) {

                $digit = (int)$clean_isbn[$i];

                $sum += ($i % 2 == 0) ? $digit : $digit * 3;
            }

            if ($sum % 10 == 0) {
                $valid_isbn = true;
            }
        }

        if (!$valid_isbn) {
            $errors["isbn"] = "Enter a valid ISBN-10 or ISBN-13.";
        } else {
            $isbn = $clean_isbn;
        }
    }

    if (empty($book_title)) {
        $errors["book_title"] = "Book title is required.";
    }

    if (empty($borrow_date)) {

        $errors["borrow_date"] = "Borrow date is required.";

    } else {

        $borrow = DateTime::createFromFormat("!Y-m-d", $borrow_date);

        if (
            !$borrow ||
            $borrow->format("Y-m-d") !== $borrow_date
        ) {

            $errors["borrow_date"] = "Enter a valid borrow date.";

        } elseif ($borrow > new DateTime("today")) {

            $errors["borrow_date"] = "Borrow date cannot be in the future.";
        }
    }

    if (empty($return_date)) {

        $errors["return_date"] = "Return due date is required.";

    } else {

        $return = DateTime::createFromFormat("!Y-m-d", $return_date);

        if (
            !$return ||
            $return->format("Y-m-d") !== $return_date
        ) {

            $errors["return_date"] = "Enter a valid return date.";

        } elseif (!isset($errors["borrow_date"]) &&
                  $return <= $borrow) {

            $errors["return_date"] = "Return date must be after borrow date.";
        }
    }

    if (empty($loan_duration)) {

        $errors["loan_duration"] = "Loan duration is required.";

    } elseif (
        !ctype_digit($loan_duration) ||
        (int)$loan_duration < 1
    ) {

        $errors["loan_duration"] = "Loan duration must be a positive whole number of days.";
    }

    if (empty($purpose)) {
        $errors["purpose"] = "Borrowing purpose is required.";
    }

    if (($_POST["agreement"] ?? "") !== "1") {
        $errors["agreement"] = "You must agree to the library rules.";
    }

    if (empty($errors)) {

        $sql = "INSERT INTO loans
                (student_id, isbn, book_title, borrow_date,
                 return_date, loan_duration, purpose, agreement)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";

        $stmt = $conn->prepare($sql);

        if ($stmt) {

            $duration = (int)$loan_duration;

            $stmt->bind_param(
                "sssssis",
                $student_id,
                $isbn,
                $book_title,
                $borrow_date,
                $return_date,
                $duration,
                $purpose
            );

            if ($stmt->execute()) {

                $success = "Library loan request submitted successfully!";

                $student_id = "";
                $isbn = "";
                $book_title = "";
                $borrow_date = "";
                $return_date = "";
                $loan_duration = "";
                $purpose = "";

            } else {

                if ($stmt->errno == 1062) {

                    $errors["isbn"] = "This student already has an active loan for this book.";

                } else {

                    $db_error = "Loan registration failed. Please try again.";
                }
            }

            $stmt->close();

        } else {

            $db_error = "Unable to prepare database query.";
        }
    }
}

$loans = $conn->query(
    "SELECT * FROM loans ORDER BY loan_id DESC"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Library Book Borrowing</title>

    <link rel="stylesheet" href="style.css">

</head>

<body>

    <h2>Library Book Borrowing</h2>

    <p>Record a library loan request.</p>


    <form action="" method="POST">

        <div class="form-header">
          Please Fill Out the Form Below to Register a Library Loan
        </div>

        <div class="form-group">

            <label>Student ID</label>

            <input type="text" name="student_id"
                value="<?php echo htmlspecialchars($student_id); ?>">

            <?php if (isset($errors["student_id"])): ?>
                <p class="error"><?php echo htmlspecialchars($errors["student_id"]); ?></p>
            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Book ISBN</label>

            <input type="text" name="isbn"
                value="<?php echo htmlspecialchars($isbn); ?>">

            <?php if (isset($errors["isbn"])): ?>
                <p class="error"><?php echo htmlspecialchars($errors["isbn"]); ?></p>
            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Book title</label>

            <input type="text" name="book_title"
                value="<?php echo htmlspecialchars($book_title); ?>">

            <?php if (isset($errors["book_title"])): ?>
                <p class="error"><?php echo htmlspecialchars($errors["book_title"]); ?></p>
            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Borrow date</label>

            <input type="date" name="borrow_date"
                value="<?php echo htmlspecialchars($borrow_date); ?>">

            <?php if (isset($errors["borrow_date"])): ?>
                <p class="error"><?php echo htmlspecialchars($errors["borrow_date"]); ?></p>
            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Return due date</label>

            <input type="date" name="return_date"
                value="<?php echo htmlspecialchars($return_date); ?>">

            <?php if (isset($errors["return_date"])): ?>
                <p class="error"><?php echo htmlspecialchars($errors["return_date"]); ?></p>
            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Loan duration</label>

            <input type="number" name="loan_duration"
                value="<?php echo htmlspecialchars($loan_duration); ?>">

            <?php if (isset($errors["loan_duration"])): ?>
                <p class="error"><?php echo htmlspecialchars($errors["loan_duration"]); ?></p>
            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Borrowing purpose</label>

            <input type="text" name="purpose"
                value="<?php echo htmlspecialchars($purpose); ?>">

            <?php if (isset($errors["purpose"])): ?>
                <p class="error"><?php echo htmlspecialchars($errors["purpose"]); ?></p>
            <?php endif; ?>

        </div>

        <div class="form-group">

            <label>Agree to library rules</label>

            <div>

                <input type="checkbox" name="agreement" value="1"
                    <?php if (($_POST["agreement"] ?? "") === "1") echo "checked"; ?>>

                I agree

            </div>

            <?php if (isset($errors["agreement"])): ?>
                <p class="error"><?php echo htmlspecialchars($errors["agreement"]); ?></p>
            <?php endif; ?>

        </div>

        <div class="form-buttons">

            <button type="submit">Submit Form</button>

            <button type="reset">Reset</button>

        </div>

    </form>

    <?php if ($success != ""): ?>

        <script>
            alert(<?php echo json_encode($success); ?>);
        </script>

    <?php endif; ?>


    <!-- DATABASE ERROR -->

    <?php if ($db_error != ""): ?>

        <p class="error">
            <?php echo htmlspecialchars($db_error); ?>
        </p>

    <?php endif; ?>

    <h2>Registered Library Loans</h2>

    <table border="1" cellpadding="10" cellspacing="0">

        <tr>
            <th>Loan ID</th>
            <th>Student ID</th>
            <th>ISBN</th>
            <th>Book Title</th>
            <th>Borrow Date</th>
            <th>Return Date</th>
            <th>Duration</th>
            <th>Purpose</th>
            <th>Status</th>
            <th>Registration Date</th>
        </tr>

        <?php if ($loans && $loans->num_rows > 0): ?>

            <?php while ($row = $loans->fetch_assoc()): ?>

                <tr>

                    <td><?php echo htmlspecialchars($row["loan_id"]); ?></td>

                    <td><?php echo htmlspecialchars($row["student_id"]); ?></td>

                    <td><?php echo htmlspecialchars($row["isbn"]); ?></td>

                    <td><?php echo htmlspecialchars($row["book_title"]); ?></td>

                    <td><?php echo htmlspecialchars($row["borrow_date"]); ?></td>

                    <td><?php echo htmlspecialchars($row["return_date"]); ?></td>

                    <td><?php echo htmlspecialchars($row["loan_duration"]); ?> days</td>

                    <td><?php echo htmlspecialchars($row["purpose"]); ?></td>

                    <td><?php echo htmlspecialchars($row["status"]); ?></td>

                    <td><?php echo htmlspecialchars($row["created_at"]); ?></td>

                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="10">No library loans found.</td>
            </tr>

        <?php endif; ?>

    </table>

</body>
</html>

<?php

$conn->close();

?>
