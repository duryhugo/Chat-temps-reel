<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION["id"])) {
    $_SESSION["id"] = "Hugo";
}

function send_chat($nick, $chat, $files = null) {
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
    $date = date('d/m/Y');
    $time = date('H:i');

    $file_infos = array();
    $maxFileSize = 20 * 1024 * 1024; // 20 Mo

    if ($files) {
        foreach ($files['name'] as $index => $name) {
            if ($files['error'][$index] == 0 && $files['size'][$index] <= $maxFileSize) {
                $file_name = basename($name);
                $file_path = 'uploads/' . $file_name;
                if (move_uploaded_file($files['tmp_name'][$index], $file_path)) {
                    $file_infos[] = $file_name;
                } else {
                    error_log("File upload failed: " . print_r($files, true));
                }
            } else {
                error_log("File upload error: " . $files['error'][$index]);
            }
        }
    }

    $file_info_str = implode(', ', $file_infos);

    $format = array($nick, $chat, $date, $time, $file_info_str);
    $decode[] = $format;
    $encode = json_encode($decode);

    $fopen_w = fopen($filename, "w");
    if (flock($fopen_w, LOCK_EX)) {
        fwrite($fopen_w, $encode);
        flock($fopen_w, LOCK_UN);
    }
    fclose($fopen_w);
}

function delete_chat($id) {
    $filename = "chat.json";
    if (!file_exists($filename)) {
        return;
    }

    $fopen = fopen($filename, "r");
    if (flock($fopen, LOCK_SH)) {
        $fgets = fgets($fopen);
        flock($fopen, LOCK_UN);
    }
    fclose($fopen);
    $decode = json_decode($fgets, true);

    if (isset($decode[$id])) {
        unset($decode[$id]);
        $decode = array_values($decode); // Réindexe le tableau
        $encode = json_encode($decode);

        $fopen_w = fopen($filename, "w");
        if (flock($fopen_w, LOCK_EX)) {
            fwrite($fopen_w, $encode);
            flock($fopen_w, LOCK_UN);
        }
        fclose($fopen_w);
    }
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

if ((isset($_POST["chat"]) && $_POST["chat"] != "") || (isset($_FILES['files']) && $_FILES['files']['error'][0] == 0)) {
    $nick = $_SESSION["id"];
    $chat = isset($_POST["chat"]) ? $_POST["chat"] : "";
    $files = isset($_FILES['files']) ? $_FILES['files'] : null;
    send_chat($nick, $chat, $files);
}

if (isset($_POST["delete"])) {
    $id = intval($_POST["delete"]);
    delete_chat($id);
    exit;
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
        #chat {
            height: 500px;
            max-height: 500px;
            overflow-y: auto;
            border: none;
            padding: 10px;
        }

        textarea {
            resize: none;
        }

        #file-input {
            display: none;
        }
        #file-name {
            margin-top: 10px;
        }

        #file-button, #emoji-button {
            margin-right: 5px;
            border: none;
        }

        .btn-primary {
            background: white;
            border: none;
        }

        .btn-primary:hover {
            background: white;
            border: none;
        }

        .context-menu {
            display: none;
            position: absolute;
            z-index: 1000;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            overflow: hidden;
        }

        .context-menu button {
            padding: 8px 12px;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }

        .context-menu button:hover {
            background-color: #f0f0f0;
        }

        .emoji-picker {
            display: none;
            position: absolute;
            background: #fff;
            border: 1px solid #ccc;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            padding: 10px;
            border-radius: 5px;
            max-height: 200px;
            overflow-y: auto;
        }

        .emoji-picker span {
            cursor: pointer;
            padding: 5px;
            font-size: 20px;
        }
    </style>
</head>
<body>
<div style="margin-top: 5px" class="container">
    <div class="row">
        <div class="col-md-12" id="chat"></div>
        <div class="col-md-12">
            <form id="input-chat" action="" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <div class="input-group">
                        <textarea class="form-control" name="chat" placeholder="Tapez un message"></textarea>
                        <span class="input-group-btn">
                            <button class="btn btn-sm btn-primary" value="Envoyer" type="submit">
                            <img src="envoie.png" alt="Attach" style="width: 18px; height: 18px;">
                            </button>
                        </span>
                        <span class="input-group-btn">
                            <button id="file-button" class="btn btn-default" type="button">
                            <img src="trombone.png" alt="Attach" style="width: 20px; height: 20px;">
                            </button>
                        </span>
                        <span class="input-group-btn">
                            <button id="emoji-button" class="btn btn-default" type="button">😊</button>
                        </span>
                    </div>
                    <br>
                    <input type="file" id="file-input" name="files[]" multiple><br>
                </div>
            </form>
            <div id="file-name"></div>
        </div>
    </div>

    <div class="emoji-picker" id="emoji-picker">
        <!-- Liste d'emojis -->
        <span>😊</span><span>❤️</span><span>😂</span><span>😍</span><span>😎</span><span>👍</span><span>🎉</span>
        <span>🌟</span><span>🔥</span><span>🙌</span><span>😄</span><span>😘</span><span>😉</span><span>😁</span>
        <span>😀</span><span>😃</span><span>😇</span><span>😆</span><span>😋</span><span>😌</span><span>😏</span>
        <!-- Ajoutez d'autres emojis ici -->
    </div>

    <div class="context-menu" id="context-menu">
        <button id="delete-message">Supprimer le message</button>
    </div>
</div>

<script>
    let lastId = -1;
    let isScrolledToBottom = true;
    let userSentMessage = false;
    let messageToDelete = null;

    const chatDiv = document.getElementById('chat');
    const contextMenu = document.getElementById('context-menu');
    const deleteButton = document.getElementById('delete-message');
    const emojiButton = document.getElementById('emoji-button');
    const emojiPicker = document.getElementById('emoji-picker');
    const textarea = document.querySelector('textarea[name="chat"]');
    const form = document.getElementById('input-chat');

    chatDiv.addEventListener('scroll', function() {
        isScrolledToBottom = chatDiv.scrollHeight - chatDiv.scrollTop === chatDiv.clientHeight;
    });

    document.getElementById('file-button').addEventListener('click', function() {
        document.getElementById('file-input').click();
    });

    document.getElementById('file-input').addEventListener('change', function() {
        const fileInput = document.getElementById('file-input');
        const fileNameDiv = document.getElementById('file-name');
        const files = fileInput.files;

        if (files.length > 0) {
            let fileNames = '';
            for (let i = 0; i < files.length; i++) {
                fileNames += files[i].name + (i < files.length - 1 ? ', ' : '');
            }
            fileNameDiv.textContent = `Fichiers sélectionnés: ${fileNames}`;
        } else {
            fileNameDiv.textContent = '';
        }
    });

    textarea.addEventListener('keypress', function(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    chatDiv.addEventListener('contextmenu', function(event) {
        event.preventDefault();
        const target = event.target.closest('div');
        if (target) {
            messageToDelete = target;
            contextMenu.style.top = `${event.clientY}px`;
            contextMenu.style.left = `${event.clientX}px`;
            contextMenu.style.display = 'block';
        }
    });

    document.addEventListener('click', function() {
        contextMenu.style.display = 'none';
    });

    deleteButton.addEventListener('click', async function() {
        if (messageToDelete) {
            const messageId = messageToDelete.dataset.id;
            await fetch(`?delete=${messageId}`, {
                method: 'POST'
            });
            messageToDelete.remove();
            messageToDelete = null;
        }
    });

    emojiButton.addEventListener('click', function() {
        emojiPicker.style.display = emojiPicker.style.display === 'block' ? 'none' : 'block';
    });

    emojiPicker.addEventListener('click', function(event) {
        if (event.target.tagName === 'SPAN') {
            textarea.value += event.target.textContent;
            emojiPicker.style.display = 'none';
        }
    });

    async function fetchChat() {
        try {
            const response = await fetch(`?chat=1&last_id=${lastId}`);
            const data = await response.json();
            if (data.status !== 'no data') {
                Object.keys(data).forEach(key => {
                    const post = data[key];
                    const row = document.createElement('div');
                    row.dataset.id = key;
                    let message = `<b>${post[0]}</b> `;
                    message += `<span style="color:gray; font-size:smaller;">${post[2]}</span> `;
                    message += `<span style="color:gray; font-size:smaller;">${post[3]}</span><br>`;
                    if (post[1] != ""){
                        message += `${post[1]} <br><br>`;
                    }
                    if (post[4]) {
                        const files = post[4].split(',').map(file => file.trim());
                        files.forEach(file => {
                            message += `<a href="uploads/${file}" download>${file}</a><br>`;
                        });
                    }
                    row.innerHTML = message;
                    chatDiv.appendChild(row);
                    lastId = key;
                });
            }
            if (isScrolledToBottom || userSentMessage) {
                chatDiv.scrollTop = chatDiv.scrollHeight;
                userSentMessage = false;
            }
        } catch (error) {
            console.error('Erreur lors de la récupération des messages:', error);
        }
    }

    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        const formData = new FormData(form);
        try {
            await fetch('', {
                method: 'POST',
                body: formData
            });
            form.reset();
            userSentMessage = true;
        } catch (error) {
            console.error('Erreur lors de l\'envoi du message:', error);
        }
    });

    fetchChat();
    setInterval(fetchChat, 2000);
</script>
</body>
</html>