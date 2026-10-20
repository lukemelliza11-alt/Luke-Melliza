<?php
// ---------------------------------------------------------------
// Kara David's Wish Note - single-file PHP app (QA test target)
// ---------------------------------------------------------------
mysqli_report(MYSQLI_REPORT_OFF); // handle errors manually below

$servername = "localhost";
$username   = "root";
$password   = "";               // no password (development only)
$dbname     = "birthday_wishnote";

$conn = new mysqli($servername, $username, $password, $dbname);

$message = "";
$error   = "";

if ($conn->connect_error) {
    $error = "Database connection failed: " . htmlspecialchars($conn->connect_error);
} elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Capture all four variables from the form
    $FirstName   = trim($_POST["FirstName"]   ?? "");
    $LastName    = trim($_POST["LastName"]    ?? "");
    $GovPosition = trim($_POST["GovPosition"] ?? "");
    $Location    = trim($_POST["Location"]    ?? "");   // captured, but never saved (see flaw below)

    // =============================================================
    // INTENTIONAL FLAW (for QA testing):
    // The form collects four fields, but this INSERT statement
    // completely omits the `Location` column - both in the SQL string
    // and in bind_param(). Only FirstName, LastName and GovPosition
    // are stored, so Location will be empty/NULL in the database
    // even though the user typed it and saw it in the modal.
    // =============================================================
    $stmt = $conn->prepare(
        "INSERT INTO main_corrupt_politicians (FirstName, LastName, GovPosition) VALUES (?, ?, ?)"
    );

    if ($stmt === false) {
        // Fallback error if the query fails to prepare
        $error = "Error: the query could not be prepared. " . htmlspecialchars($conn->error);
    } else {
        $stmt->bind_param("sss", $FirstName, $LastName, $GovPosition); // Location omitted on purpose
        if ($stmt->execute()) {
            $message = "Success! The wish note has been sent.";
        } else {
            $error = "Error: the wish note could not be saved. " . htmlspecialchars($stmt->error);
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kara David's Wish Note</title>
<style>
  :root { --blue:#1e6fd9; --blue-dark:#1558b0; --bg:#eceff3; --text:#1f2937; --muted:#5b6675; }
  * { box-sizing: border-box; }
  body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
         padding:20px; background:var(--bg); color:var(--text);
         font-family:"Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
  .card { width:100%; max-width:460px; background:#fff; border-radius:10px; padding:32px;
          box-shadow:0 6px 24px rgba(15,40,80,.12); }
  h2 { margin:0 0 20px; color:var(--blue); font-size:1.6rem; }
  label { display:block; margin:14px 0 6px; font-weight:600; font-size:.92rem; }
  input[type=text] { width:100%; padding:10px 12px; border:1px solid #c5ccd6; border-radius:6px; font-size:1rem; }
  input[type=text]:focus { outline:2px solid var(--blue); outline-offset:1px; border-color:var(--blue); }
  button { cursor:pointer; border:0; border-radius:6px; padding:11px 18px; font-size:1rem; font-weight:600; transition:background .15s; }
  .primary { width:100%; margin-top:22px; background:var(--blue); color:#fff; }
  .primary:hover { background:var(--blue-dark); }
  .secondary { background:#e3e8ef; color:var(--text); }
  .secondary:hover { background:#d3dae4; }
  .alert { padding:12px 14px; border-radius:6px; margin-bottom:16px; font-weight:600; }
  .success { background:#e3f1ff; color:#0e4a9a; border-left:4px solid var(--blue); }
  .error { background:#fdeaea; color:#9b1c1c; border-left:4px solid #d33; }
  .overlay { position:fixed; inset:0; display:none; align-items:center; justify-content:center;
             background:rgba(15,25,45,.55); padding:20px; }
  .overlay.open { display:flex; }
  .modal { width:100%; max-width:420px; background:#fff; border-radius:10px; padding:26px;
           box-shadow:0 10px 36px rgba(0,0,0,.3); }
  .modal h3 { margin:0 0 14px; color:var(--blue); }
  .modal dl { margin:0 0 20px; }
  .modal dt { font-size:.82rem; color:var(--muted); margin-top:10px; }
  .modal dd { margin:2px 0 0; font-weight:600; word-break:break-word; }
  .actions { display:flex; gap:10px; justify-content:flex-end; }
  .actions .primary { width:auto; margin:0; }
</style>
</head>
<body>
<div class="card">
  <h2>Kara David's Wish Note</h2>

  <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert error"><?= $error ?></div><?php endif; ?>

  <form id="wishForm" method="POST" action="">
    <label for="FirstName">Target's First Name</label>
    <input type="text" id="FirstName" name="FirstName" maxlength="30" required>

    <label for="LastName">Target's Last Name</label>
    <input type="text" id="LastName" name="LastName" maxlength="30" required>

    <label for="GovPosition">Target's Government Position</label>
    <input type="text" id="GovPosition" name="GovPosition" maxlength="20" required>

    <label for="Location">Target's Location</label>
    <input type="text" id="Location" name="Location" maxlength="40">

    <button type="submit" class="primary">Send Wish Note</button>
  </form>
</div>

<div class="overlay" id="overlay" role="dialog" aria-modal="true" aria-labelledby="mTitle">
  <div class="modal">
    <h3 id="mTitle">Verify Target Data</h3>
    <dl>
      <dt>First Name</dt><dd id="vFirst"></dd>
      <dt>Last Name</dt><dd id="vLast"></dd>
      <dt>Government Position</dt><dd id="vPos"></dd>
      <dt>Location</dt><dd id="vLoc"></dd>
    </dl>
    <div class="actions">
      <button type="button" class="secondary" id="cancelBtn">Cancel</button>
      <button type="button" class="primary" id="confirmBtn">Confirm &amp; Send</button>
    </div>
  </div>
</div>

<script>
  const form = document.getElementById('wishForm');
  const overlay = document.getElementById('overlay');
  const val = id => document.getElementById(id).value.trim();

  form.addEventListener('submit', function (e) {
    e.preventDefault();                       // intercept the submission
    document.getElementById('vFirst').textContent = val('FirstName');
    document.getElementById('vLast').textContent  = val('LastName');
    document.getElementById('vPos').textContent   = val('GovPosition');
    document.getElementById('vLoc').textContent   = val('Location') || '(not provided)';
    overlay.classList.add('open');
  });

  document.getElementById('cancelBtn').addEventListener('click', () => overlay.classList.remove('open'));
  document.getElementById('confirmBtn').addEventListener('click', () => form.submit()); // bypasses the interception
</script>
</body>
</html>
