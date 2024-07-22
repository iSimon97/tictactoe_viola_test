let gameDisabled = true;
let myGameId = null;
let myPlayerId = null;
let myTurn = false;
let myColor = null;

$('#saveGameId').click(function() {
    let gameId = $('#gameId').val();
    let color = $('input[name=color]:checked').val();
    $.post('/api.php', {action: "registerGame", gameId: gameId, color: color}, function(data) {
        //parse json data
        
        let jsonData = JSON.parse(data);
        if (jsonData.status === 'success') {
            myColor = jsonData.color;
            myGameId = jsonData.gameId;
            myPlayerId = jsonData.playerId;
            gameDisabled = false;
            $('#gameId').prop('disabled', true);
            $('#saveGameId').prop('disabled', true);
            $('input[name=color]').hide();
            $(".color_label").hide();
        
            if(jsonData.wait_for_player == true){
                waitForPlayer();
                $(".overlay").html("Wait for player...");
            }else{
                $(".overlay").html("Player 1s turn");
                waitForTurn();
            }
            
        }
    });
});

function waitForPlayer(){
    let intervalId = setInterval(function(){
        $.post('/api.php', {action: "hasPlayer", gameId: myGameId}, function(data) {
            let jsonData = JSON.parse(data);
            if (jsonData.status === 'success') {
                clearInterval(intervalId);
                $(".overlay").addClass("disabled");
                myTurn = true;
            }
        });
    }, 1000);
}

$('.col').click(function() {
    if (gameDisabled) {
        return;
    }
    if (!myTurn) {
        alert('Not your turn');
    }
    let colId = $(this).attr('id');
    $.post('/api.php', {action: "move", gameId: myGameId, playerId: myPlayerId, col: colId}, function(data) {
        let jsonData = JSON.parse(data);
        if (jsonData.status === 'success') {
            if(myColor == 'red'){
                $("#" + colId).addClass("checkedX");
            }else if(myColor == 'blue'){
                $("#" + colId).addClass("checkedO");
            }
            myTurn = false;
            $(".overlay").html("Player 2s turn");
            $(".overlay").removeClass("disabled");
            waitForTurn();
            checkWinner();
        }
    });
});

function waitForTurn(){
    let intervalId = setInterval(function(){
        $.post('/api.php', {action: "waitForTurn", gameId: myGameId, playerId: myPlayerId}, function(data) {
            let jsonData = JSON.parse(data);
            if (jsonData.status === 'success') {
                //jsonData.colId contains the column id of the last move
                if(myColor == 'red'){
                    $("#col" + jsonData.colId).addClass("checkedO");
                }else if(myColor == 'blue'){
                    $("#col" + jsonData.colId).addClass("checkedX");
                }
                $(".overlay").addClass("disabled");
                myTurn = true;
                clearInterval(intervalId);
            }
        });
    }, 1000);
}

function checkWinner(){
    //check later if there is a winner;
}