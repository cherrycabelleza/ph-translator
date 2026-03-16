<?php
require_once "config.php";
session_start();

if(!isset($_SESSION['email'])){
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];

function logTranslationAction($link, $action, $row){
    $stmt = mysqli_prepare($link,
        "INSERT INTO logs
         (action, source_lang, target_lang, word, translation)
         VALUES (?, ?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "sssss",
        $action,
        $row['source_lang'],
        $row['target_lang'],
        $row['word'],
        $row['translation']
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$error = "";
$success = "";

/* ================================
   HANDLE SUBMIT
================================ */

if(isset($_POST['btnsubmit'])){

    $word        = strtolower(trim($_POST['word']));
    $source_lang = strtolower(trim($_POST['source_lang']));
    $target_lang = strtolower(trim($_POST['target_lang']));
    $translation = trim($_POST['translation']);

    if($word && $source_lang && $target_lang && $translation){

        $check_sql = "SELECT id FROM users_translations
                      WHERE word=? AND source_lang=? AND target_lang=? AND translation=? LIMIT 1";

        $check_stmt = mysqli_prepare($link,$check_sql);

        mysqli_stmt_bind_param($check_stmt,"ssss",
            $word,$source_lang,$target_lang,$translation);

        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if(mysqli_stmt_num_rows($check_stmt)>0){

            $error="This translation already exists.";

        }else{

            $insert_sql="INSERT INTO users_translations
            (email,word,source_lang,target_lang,translation,date_added)
            VALUES (?,?,?,?,?,NOW())";

            $insert_stmt=mysqli_prepare($link,$insert_sql);

            mysqli_stmt_bind_param($insert_stmt,"sssss",
                $email,$word,$source_lang,$target_lang,$translation);

            if(mysqli_stmt_execute($insert_stmt)){

                logTranslationAction($link,'USER_ADD',[
                    'word'=>$word,
                    'source_lang'=>$source_lang,
                    'target_lang'=>$target_lang,
                    'translation'=>$translation
                ]);

                $success="Translation submitted successfully!";

            }else{
                $error="Insert failed.";
            }

            mysqli_stmt_close($insert_stmt);
        }

        mysqli_stmt_close($check_stmt);

    }else{
        $error="All fields required.";
    }
}

/* ================================
   LOAD USER TRANSLATIONS
================================ */

$sql="SELECT word,source_lang,target_lang,translation,date_added
      FROM users_translations
      WHERE email=?
      ORDER BY date_added DESC";

$stmt=mysqli_prepare($link,$sql);
mysqli_stmt_bind_param($stmt,"s",$email);
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>User Translation Panel | Philippine Languages Translator</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #1a73e8;
            --primary-hover: #1557b0;
            --bg-light: #f0f4ff;
            --card-bg: #ffffff;
            --text-main: #1a2a3a;
            --text-muted: #5c7a94;
            --border-color: #e0e6ed;
            --success-bg: #dcfce7;
            --success-text: #166534;
            --error-bg: #fef2f2;
            --error-text: #dc2626;
            --radius: 12px;
            --shadow: 0 10px 25px rgba(26, 115, 232, 0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-light);
            color: var(--text-main);
            margin: 0;
            padding: 20px;
            line-height: 1.6;
            min-height: 100vh;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-container h1 {
            margin: 0;
            color: var(--primary);
            font-weight: 700;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-btn {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            padding: 8px 16px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid var(--border-color);
        }

        .back-btn:hover {
            color: var(--primary);
            border-color: var(--primary);
            background: #f8fbff;
            transform: translateX(-3px);
        }

        .wrapper {
            display: flex;
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .card-header {
            margin-bottom: 25px;
            border-bottom: 2px solid var(--bg-light);
            padding-bottom: 15px;
        }

        .card-title {
            margin: 0;
            font-size: 1.25rem;
            color: var(--text-main);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .left {
            width: 380px;
            flex-shrink: 0;
        }

        .right {
            flex: 1;
            min-width: 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            font-size: 0.875rem;
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 14px;
            color: var(--text-muted);
            transition: color 0.2s;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 0.95rem;
            box-sizing: border-box;
            background: #fdfdfe;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        input {
            padding-left: 40px;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1);
            background: #fff;
        }

        input:focus + i {
            color: var(--primary);
        }

        button[name="btnsubmit"] {
            background: var(--primary);
            color: white;
            border: none;
            width: 100%;
            padding: 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        button[name="btnsubmit"]:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(26, 115, 232, 0.2);
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid transparent;
        }

        .success {
            background: var(--success-bg);
            color: var(--success-text);
            border-color: rgba(22, 101, 52, 0.1);
        }

        .error {
            background: var(--error-bg);
            color: var(--error-text);
            border-color: rgba(220, 38, 38, 0.1);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.9rem;
        }

        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        th {
            background: #f8fbff;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr {
            transition: background 0.2s ease;
        }

        tr:hover {
            background: #f0f7ff;
        }

        .latest-row {
            background: #eef6ff !important;
            box-shadow: inset 4px 0 0 var(--primary);
        }

        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--bg-light);
            color: var(--primary);
            text-transform: uppercase;
        }

        .date-cell {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        @media (max-width: 1024px) {
            .wrapper {
                flex-direction: column;
            }
            .left {
                width: 100%;
            }
        }

        @media (max-width: 600px) {
            body {
                padding: 15px;
            }
            .header-container {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .card {
                padding: 20px;
            }
            .hide-mobile {
                display: none;
            }
        }
    </style>
</head>

<body>

<div class="header-container">
    <h1><i class="fas fa-language"></i> Translation Panel</h1>
    <a href="PLT_MainPage.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Translator
    </a>
</div>

<div class="wrapper">

    <!-- LEFT FORM -->
    <div class="card left">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-plus-circle"></i> Add Translation</h2>
        </div>

        <?php if($success): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if($error): ?>
        <div class="alert error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="word">Source Word / Phrase</label>
                <div class="input-wrapper">
                    <input type="text" id="word" name="word" placeholder="e.g. Hello" required>
                    <i class="fas fa-font"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="source_lang">Source Language</label>
                <select id="source_lang" name="source_lang" required>
                    <option value="">Select Language</option>
                    <option value="tl">Tagalog</option>
                    <option value="ceb">Cebuano</option>
                    <option value="ilo">Ilocano</option>
                    <option value="war">Waray</option>
                    <option value="bik">Bikol</option>
                    <option value="hil">Hiligaynon</option>
                </select>
            </div>

            <div class="form-group">
                <label for="target_lang">Target Language</label>
                <select id="target_lang" name="target_lang" required>
                    <option value="">Select Language</option>
                    <option value="tl">Tagalog</option>
                    <option value="ceb">Cebuano</option>
                    <option value="ilo">Ilocano</option>
                    <option value="war">Waray</option>
                    <option value="bik">Bikol</option>
                    <option value="hil">Hiligaynon</option>
                </select>
            </div>

            <div class="form-group">
                <label for="translation">Translation</label>
                <textarea id="translation" name="translation" placeholder="Enter the translation here..." required></textarea>
            </div>

            <button type="submit" name="btnsubmit">
                <i class="fas fa-paper-plane"></i> Submit Translation
            </button>
        </form>
    </div>

    <!-- RIGHT VIEW PANEL -->
    <div class="card right">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-list-ul"></i> My Submitted Translations</h2>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Word</th>
                        <th>From/To</th>
                        <th>Translation</th>
                        <th class="hide-mobile">Date Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $first = true;
                    while($row = mysqli_fetch_assoc($result)): 
                        $rowClass = $first ? 'latest-row' : '';
                        $first = false;
                    ?>
                    <tr class="<?php echo $rowClass; ?>">
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($row['word']); ?></td>
                        <td>
                            <span class="badge"><?php echo htmlspecialchars($row['source_lang']); ?></span>
                            <i class="fas fa-long-arrow-alt-right" style="color: var(--text-muted); margin: 0 4px;"></i>
                            <span class="badge"><?php echo htmlspecialchars($row['target_lang']); ?></span>
                        </td>
                        <td style="color: var(--primary); font-weight: 500;"><?php echo htmlspecialchars($row['translation']); ?></td>
                        <td class="date-cell hide-mobile">
                            <?php echo date('M d, Y h:i A', strtotime($row['date_added'])); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <?php if(mysqli_num_rows($result) == 0): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 60px; color: var(--text-muted);">
                            <i class="fas fa-folder-open" style="font-size: 3rem; display: block; margin-bottom: 15px; opacity: 0.3;"></i>
                            No translations submitted yet.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>