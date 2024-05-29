<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION["id"])) {
    $_SESSION["id"] = "Charles";
}

function send_chat($nick, $chat, $file = null) {
    $filename = "chat.json";
    if (!file_exists($filename)) {
        $decode = array();
    } else {
        $fopen = fopen($filename, "r");
        if (flock($fopen, LOCK_SH)) {
            $fgets = fgets($fopen);
            flock($fopen, LOCK_UN);
        }
        fclose($fopen);
        $decode = json_decode($fgets, true);
    }

    if (!is_array($decode)) {
        $decode = array();
    }

    $new_key = count($decode);

    $chat = htmlspecialchars($chat, ENT_QUOTES, 'UTF-8');
    $date = date('d/m/Y');  // Format de la date
    $time = date('H:i');  // Format de l'heure

    $file_info = null;
    $maxFileSize = 20 * 1024 * 1024; // 20 Mo

    if ($file && $file['error'] == 0) {
        if ($file['size'] <= $maxFileSize) {
            $file_name = basename($file['name']);
            $file_path = 'uploads/' . $file_name;
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $file_info = $file_name;
            } else {
                error_log("File upload failed: " . print_r($file, true));
            }
        } else {
            error_log("File is too large: " . $file['size']);
        }
    } else {
        error_log("File upload error: " . $file['error']);
    }

    $format = array($nick, $chat, $date, $time, $file_info);
    $decode[] = $format;
    $encode = json_encode($decode);

    $fopen_w = fopen($filename, "w");
    if (flock($fopen_w, LOCK_EX)) {
        fwrite($fopen_w, $encode);
        flock($fopen_w, LOCK_UN);
    }
    fclose($fopen_w);
}

function show_chat($last_id = -1) {
    $filename = "chat.json";
    if (!file_exists($filename)) {
        return json_encode(array('status' => 'no data'));
    }

    $fopen = fopen($filename, "r");
    if (flock($fopen, LOCK_SH)) {
        $fgets = fgets($fopen);
        flock($fopen, LOCK_UN);
    }
    fclose($fopen);
    $decode = json_decode($fgets, true);

    $filtered_data = array();
    foreach ($decode as $key => $value) {
        if ($key > $last_id) {
            $filtered_data[$key] = $value;
        }
    }

    return json_encode($filtered_data);
}

if ((isset($_POST["chat"]) && $_POST["chat"] != "") || (isset($_FILES['file']) && $_FILES['file']['error'] == 0)) {
    $nick = $_SESSION["id"];
    $chat = isset($_POST["chat"]) ? $_POST["chat"] : "";
    $file = isset($_FILES['file']) ? $_FILES['file'] : null;
    send_chat($nick, $chat, $file);
}

if (isset($_GET["chat"])) {
    $last_id = isset($_GET["last_id"]) ? intval($_GET["last_id"]) : -1;
    echo show_chat($last_id);
    exit;
}
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Chat</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.4/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <style>
        .msg { list-style-type: none; }
        .msg .nick { text-shadow: 1px 2px 3px red; }
        #chat { height: 400px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px; }
    </style>
</head>
<body>
    <div style="margin-top: 5px" class="container">
        <div class="row">
            <div class="col-md-12" id="chat"></div>
            <div class="col-md-12">
                <form id="input-chat" action="" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Chat</label>
                        <div class="input-group">
                            <textarea class="form-control" name="chat"></textarea>
                            <span class="input-group-btn">
                                <button id="emoji-button" class="btn btn-default" type="button">😊</button>
                            </span>
                        </div>
                        <br>
                        <label>Fichier</label>
                        <input type="file" class="form-control" name="file"><br>
                        <input class="btn btn-sm btn-primary" value="Envoyer" type="submit"/>
                    </div>
                </form>
            </div>
        </div>

        <script>
        let lastId = -1;

        async function fetchChat() {
            try {
                const response = await fetch(`?chat=1&last_id=${lastId}`);
                const data = await response.json();
                if (data.status !== 'no data') {
                    const chatDiv = document.getElementById('chat');
                    Object.keys(data).forEach(key => {
                        const post = data[key];
                        const row = document.createElement('div');
                        let message = `<b>${post[0]}</b>: `;
if (post[4]) {
    message += ` <a href="uploads/${post[4]}" download>${post[4]}</a>`;
}
message += `<span style="color:gray; font-size:smaller;">${post[3]}</span> `;
message += `<span style="color:gray; font-size:smaller;">(${post[2]})</span><br>${post[1]}`;
                        row.innerHTML = message;
                        chatDiv.appendChild(row);
                        lastId = Math.max(lastId, parseInt(key));
                    });
                    chatDiv.scrollTop = chatDiv.scrollHeight;
                }
            } catch (error) {
                console.error('Error fetching chat data:', error);
            }
        }

        document.getElementById('input-chat').addEventListener('submit', async function(e) {
            e.preventDefault();
            const fileInput = document.querySelector('input[type="file"]');
            const maxFileSize = 20 * 1024 * 1024;
            if (fileInput.files[0] && fileInput.files[0].size > maxFileSize) {
                alert('Le fichier est trop volumineux. La taille maximale autorisée est de 20 Mo.');
                return;
            }
            const formData = new FormData(this);
            await fetch(this.action, {
                method: 'POST',
                body: formData
            });
            this.reset();
            await fetchChat();
        });

        setInterval(fetchChat, 2000);
        </script>
    </div>
</body>
</html>