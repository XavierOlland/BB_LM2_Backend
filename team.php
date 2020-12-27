<?php

//Fetch team info
function team_fetch($con, $id){

    //Get team info
    $sqlTeam = "SELECT * FROM site_teams WHERE cyanide_id=".$id;
    $resultTeam = $con->query($sqlTeam);
    $team = $resultTeam->fetch_object();

    //Get current competition
    $team->competition = team_competition_fetch($con, $team->id);

    //Get players
    $team->players = team_roster_fetch($con, $team->id, $id);

    //Get coach
    $team->coach = coach_fetch($con, $team->coach_id);

    //Get sponsor
    if($team->sponsor_id){
        $team->sponsor = sponsor_fetch($con, $team->sponsor_id);
    };

    echo json_encode($team,JSON_NUMERIC_CHECK);


};

//Create new team
function team_create($con, $Cyanide_Key, $id){

    $request = 'https://web.cyanide-studio.com/ws/bb2/team/?key='.$Cyanide_Key.'&id='.$id;
    $response  = file_get_contents($request);
    $json = json_decode($response);
    $team = $json->team;
    $roster = $json->roster;

    $team->name = str_replace("'","\'",$team->name);
    $team->stadium_name = str_replace("'","\'",$team->stadiumname);
    $team->leitmotiv = str_replace("'","\'",$team->leitmotiv);
    $sqlCreate = "INSERT INTO site_teams ( name, cyanide_id, coach_id, param_id_race, active, apothecary, assistantcoaches,  cheerleaders, cash, rerolls, popularity, value, stadium_name, stadium_level, leitmotiv, logo) VALUES ('".$team->name."', '".$team->id."', '".$team->idcoach."', '".$team->idraces."', '1', '".$team->apothecary."', '".$team->assistantcoaches."', '".$team->cheerleaders."', '".$team->cash."', '".$team->rerolls."', '".$team->popularity."', '".$team->value."', '".$team->stadium_name."', ".$team->stadiumlevel.", '".$team->leitmotiv."', '".$team->logo."')";
    $con->query($sqlCreate);
    $team->bbblID = $con->insert_id;

    team_roster_update($con, $team->id, $roster);

    return $team->bbblID;

};

//Update team
function team_update($con, $Cyanide_Key, $teamID){

    $request = 'https://web.cyanide-studio.com/ws/bb2/team/?key='.$Cyanide_Key.'&id='.$teamID;
    $response  = file_get_contents($request);
    $json = json_decode($response);
    $team = $json->team;
    $roster = $json->roster;

    //Update team info
    $sqlTeam = "UPDATE site_teams SET
      param_id_race = ".$team->idraces.",
      apothecary = ".$team->apothecary.",
      assistantcoaches = ".$team->assistantcoaches.",
      cheerleaders = ".$team->cheerleaders.",
      cash = '".$team->cash."',
      rerolls = '".$team->rerolls."',
      popularity = ".$team->popularity.",
      value = ".$team->value.",
      stadium_level = ".$team->stadiumlevel.",
      leitmotiv = '".str_replace("'","\'",$team->leitmotiv)."'
      WHERE cyanide_id = '".$team->id."'";
    $con->query($sqlTeam);

    team_roster_update($con, $team->id, $roster);

};

//Fetch roster details
function team_roster_fetch($con, $bbblID, $teamID){
    $roster = [];
    $sqlPlayers = "SELECT id, name, param_name_type AS position, attributes, skills, casualties, level, xp, dead, fired FROM site_players WHERE team_id=".$bbblID;
    $resultPlayers = $con->query($sqlPlayers);
    while($player = $resultPlayers->fetch_object()) {
        $player->stats = player_stats_fetch($con, $player->id);
        array_push($roster, $player);
    };
    return $roster;
};

//Update roster
function team_roster_update($con, $teamID, $roster){
    $bbblID = $con->query("SELECT id FROM site_teams WHERE cyanide_id = '".$teamID."'")->fetch_row();

    //Get current roster and store it to control players to remove
    $rosterControl = [];
    $sqlRosterControl = $con->query("SELECT cyanide_id FROM site_players WHERE dead != 1 AND fired != 1 AND team_id = ".$bbblID[0]);
    while($row = $sqlRosterControl->fetch_array(MYSQLI_NUM)){
        array_push($rosterControl, (int) $row[0]);
    };

    foreach ( $roster as $player ) {

        $sqlPlayerBBBL = "SELECT id FROM site_players WHERE cyanide_id = ".$player->id;
        $resultPlayerBBBL = $con->query($sqlPlayerBBBL);
        $playerBBBL = $resultPlayerBBBL->fetch_row();
        //Update
        if ( $playerBBBL[0] > 0 ){
            player_update($con, $bbblID[0], $teamID, $player);
            //Remove player from control
            $rosterControl = array_diff($rosterControl, [(int) $player->id]);
        }
        //Create
        else {
            player_create($con, $bbblID[0], $teamID, $player);
        };
    };

    //Update fired/sold players
    foreach ( $rosterControl as $player ){
        player_fire($con, $player);
    };
};

function team_competition_fetch($con, $teamID){
    $sqlCompetition = "SELECT MAX(m.competition_id) AS id, c.game_name AS name FROM site_matchs AS m INNER JOIN site_competitions AS c ON c.id = m.competition_id WHERE (m.team_id_1=".$teamID." OR m.team_id_2=".$teamID.") LIMIT 1";
    $resultCompetition = $con->query($sqlCompetition);
    $competition = $resultCompetition->fetch_object();
    return $competition;
};

//Change colors
function team_colours_update($con, $teamID, $colours){
  $sqlColours = "UPDATE site_teams SET color_1='".$colours[0]."',color_2='".$colours[1]."' WHERE id=".$teamID;
  $con->query($sqlColours);
};

//Change picture
function team_photo_update($con,$name,$photo){
    $result = move_uploaded_file($photo['tmp_name'], "./../img/teams/".$name.".jpg");
    $result == 0? http_response_code(304) : http_response_code(200);
    return $result;
}

//Get coach info
function coach_fetch($con, $coachID){
    $sqlCoach = "SELECT name FROM site_coachs WHERE cyanide_id='".$coachID."'";
    $resultCoach = $con->query($sqlCoach);
    $coach = $resultCoach->fetch_row();
    return $coach[0];
};

//Get last games
function team_last_games($con, $teamID, $limit){
    $games =  [];
    $sqlGames = "SELECT m.id,
          m.cyanide_id,
          m.competition_id,
          c.season as season,
          c.site_name as competition_name,
          DATE_ADD(m.started, INTERVAL 500 YEAR) AS started,
          m.team_id_1,
          m.team_id_2,
          t1.name AS team_1_name,
          t2.name AS team_2_name,
          t1.logo AS team_1_logo,
          t2.logo AS team_2_logo,
          t1.param_id_race AS team_1_race,
          t2.param_id_race AS team_2_race,
          m.score_1 AS team_1_score,
          m.score_2 AS team_2_score,
          c1.name AS team_1_coach,
          c2.name AS team_2_coach,
          IF(m.team_id_1=".$teamID.",m.score_1-m.score_2,m.score_2-m.score_1) as diff
          FROM site_matchs as m
          LEFT JOIN site_competitions as c ON c.id=m.competition_id
          LEFT JOIN site_teams as t1 ON t1.id=m.team_id_1
          LEFT JOIN site_teams as t2 ON t2.id=m.team_id_2
          LEFT JOIN site_coachs as c1 ON c1.cyanide_id=t1.coach_id
          LEFT JOIN site_coachs as c2 ON c2.cyanide_id=t2.coach_id
          WHERE (m.team_ID_1=".$teamID." OR m.team_ID_2=".$teamID.") AND m.cyanide_ID IS NOT NULL ORDER BY m.started DESC LIMIT 5";
    $resultGames = $con->query($sqlGames);
    while($dataGames = $resultGames->fetch_assoc()) {
        array_push($games, $dataGames);
    }
    return $games;
};

//Get team history
function team_history($con, $teamID){
    $history = [];
    $sqlHistory = "SELECT * FROM site_competitions_standings WHERE team_id=$teamID ORDER BY id DESC";
    $resultHistory = $con->query($sqlHistory);
    while($dataHistory = $resultHistory->fetch_assoc()) {
        array_push($history, $dataHistory);
    }
    return $history;
};

?>
