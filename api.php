<?php
    $db = new SQLite3('db.sqlite', SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
    $db->exec('CREATE TABLE IF NOT EXISTS games (id INTEGER PRIMARY KEY, gameId TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS players (id INTEGER PRIMARY KEY, gameId INTEGER, color TEXT, ip TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS moves (id INTEGER PRIMARY KEY, gameId INTEGER, playerId INTEGER, moveCount INTEGER, columnId INTEGER, timestamp TEXT)'); 

    switch ($_POST['action']) {
        case 'registerGame':
            registerGame();
            break;
        case 'hasPlayer':
            hasPlayer();
            break;
        case 'move':
            move();
            break;
        case 'waitForTurn':
            waitForTurn();
            break;
    }

    function hasPlayer() {
        if(!isset($_POST['gameId'])) {
            echo json_encode(array('status' => 'error', 'message' => 'Missing parameters'));
        } else {
            global $db;
            $stmt = $db->prepare('SELECT COUNT(*) FROM players WHERE gameId = :gameId');
            $stmt->bindValue(':gameId', $_POST['gameId'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray();

            if($row[0] == 2) {
                echo json_encode(array('status' => 'success', 'enough_players' => true));
            } else {
                echo json_encode(array('status' => 'error', 'enough_players' => false, 'message' => 'Not enough players'));
            }
        }
    }

    function registerGame() {
        if(!(isset($_POST['gameId']) && isset($_POST['color']))) {
            echo json_encode(array('status' => 'error', 'message' => 'Missing parameters'));
        } else {
            global $db;
            $stmt = $db->prepare('SELECT COUNT(*) FROM games WHERE gameId = :gameId');
            $stmt->bindValue(':gameId', $_POST['gameId'], SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray();
            if($row[0] > 0) {
                $stmt = $db->prepare('SELECT id FROM games WHERE gameId = :gameId');
                $stmt->bindValue(':gameId', $_POST['gameId'], SQLITE3_TEXT);
                $result = $stmt->execute();
                $row = $result->fetchArray();
                $id = $row[0];
                $stmt = $db->prepare('SELECT COUNT(*) FROM players WHERE gameId = :gameId');
                $stmt->bindValue(':gameId', $id, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $row = $result->fetchArray();
                if($row[0] == 1) {
                    $stmt = $db->prepare('SELECT color FROM players WHERE gameId = :gameId');
                    $stmt->bindValue(':gameId', $id, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $row = $result->fetchArray();
                    $color = $row[0];

                    $stmt = $db->prepare('INSERT INTO players (gameId, color, ip) VALUES (:gameId, :color, :ip)');
                    $stmt->bindValue(':gameId', $id, SQLITE3_INTEGER);
                    $stmt->bindValue(':color', $color == 'red' ? 'blue' : 'red', SQLITE3_TEXT);
                    $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'], SQLITE3_TEXT);
                    $stmt->execute();
                    $playerId = $db->lastInsertRowID();

                    echo json_encode(array('status' => 'success', 'gameId' => $id, 'playerId' => $playerId, 'wait_for_player' => false, 'color' => $color == 'red' ? 'blue' : 'red'));
                } else {
                    echo json_encode(array('status' => 'error', 'message' => 'Game already has two players'));
                }
            }else{
                $stmt = $db->prepare('INSERT INTO games (gameId) VALUES (:gameId)');
                $stmt->bindValue(':gameId', $_POST['gameId'], SQLITE3_TEXT);
                $stmt->execute();
                $gameId = $db->lastInsertRowID();
    
                $stmt = $db->prepare('INSERT INTO players (gameId, color, ip) VALUES (:gameId, :color, :ip)');
                $stmt->bindValue(':gameId', $gameId, SQLITE3_INTEGER);
                $stmt->bindValue(':color', $_POST['color'], SQLITE3_TEXT);
                $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'], SQLITE3_TEXT);
                $stmt->execute();
                $playerId = $db->lastInsertRowID();

                $stmt = $db->prepare('SELECT color FROM players WHERE gameId = :gameId');
                $stmt->bindValue(':gameId', $gameId, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $row = $result->fetchArray();
                $color = $row[0];
    
                echo json_encode(array('status' => 'success', 'gameId' => $gameId, 'playerId' => $playerId, 'wait_for_player' => true, 'color' => $color));
            }
        }
    }

    function move(){
        if(!(isset($_POST['playerId']) || isset($_POST['gameId']) || isset($_POST['col']))){
            echo json_encode(array('status' => 'error', 'message' => 'Missing parameters'));
        }else{
            global $db;
            $stmt = $db->prepare('SELECT COUNT(*) FROM moves WHERE gameId = :gameId AND columnId = :columnId');
            $stmt->bindValue(':gameId', $_POST['gameId'], SQLITE3_INTEGER);
            $stmt->bindValue(':columnId', $_POST['col'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray();
            if($row[0] > 0){
                echo json_encode(array('status' => 'error', 'message' => 'Column already taken'));
            }else{
                $stmt = $db->prepare('SELECT MAX(moveCount) FROM moves WHERE gameId = :gameId');
                $stmt->bindValue(':gameId', $_POST['gameId'], SQLITE3_INTEGER);
                $result = $stmt->execute();
                $row = $result->fetchArray();
                $moveCount = $row[0] + 1;
                //$_POST['col'] is in format col1, col2 etc. convert it to int
                $col = intval(substr($_POST['col'], 3));
                $stmt = $db->prepare('INSERT INTO moves (gameId, playerId, moveCount, columnId, timestamp) VALUES (:gameId, :playerId, :moveCount, :columnId, :timestamp)');
                $stmt->bindValue(':gameId', $_POST['gameId'], SQLITE3_INTEGER);
                $stmt->bindValue(':playerId', $_POST['playerId'], SQLITE3_INTEGER);
                $stmt->bindValue(':moveCount', $moveCount, SQLITE3_INTEGER);
                $stmt->bindValue(':columnId', $col, SQLITE3_INTEGER);
                $stmt->bindValue(':timestamp', date('Y-m-d H:i:s'), SQLITE3_TEXT);
                $stmt->execute();
                echo json_encode(array('status' => 'success'));
            }
        }
    }

    function waitForTurn(){
        if(!(isset($_POST['gameId']) || isset($_POST['playerId']))){
            echo json_encode(array('status' => 'error', 'message' => 'Missing parameters'));
        }else{
            global $db;
            $stmt = $db->prepare('SELECT playerId, columnId FROM moves WHERE gameId = :gameId ORDER BY moveCount DESC LIMIT 1');
            $stmt->bindValue(':gameId', $_POST['gameId'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $row = $result->fetchArray();
            //check if there is any result
            if($row){
                if($row[0] != $_POST['playerId']){
                    echo json_encode(array('status' => 'success', 'colId' => $row[1]));
                }else{
                    echo json_encode(array('status' => 'error', 'message' => 'Wait for other player'));
                }
            }else{
                echo json_encode(array('status' => 'error', 'message' => 'Wait for other player'));
            }
            

        }
    }
?>